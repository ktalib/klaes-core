<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * MOVE REGISTRATION - re-file a registration as a RELATED instrument without
 * capturing it again.
 *
 * Related means captured on the same form: the capture screen's grouped cards
 * (instruments-capture.js INSTRUMENT_FAMILIES) plus the two Occupancy Permit
 * registers. A registration only moves within its own family - an Assignment
 * becomes a Gift, never a Mortgage - because the parties, solicitor and
 * property details the form recorded mean the same thing for every member.
 *
 *   Deed of Assignment            <-> Deed of Gift
 *   Deed of Mortgage              <-> Tripartite Mortgage
 *   Deed of Surrender and Release <-> Power of Attorney <-> Irrevocable PoA
 *   OP Resettlement               <-> OP Direct Allocation
 *
 * The three Deeds families each share one vault, so a move there keeps its
 * registration number and only the type changes. The two OP registers number
 * separately: an OP move takes the target register's NEXT number (never a
 * skipped one - missing numbers are reused only by a fresh registration) and
 * the number it leaves becomes a gap in the source register. For an OP the
 * instrument type stays "Occupancy Permit (OP)"; what moves is op_type. The OP
 * serial number is the permit's own and does not change.
 *
 * An RDS / CoR already generated carries the old type and number, so a move
 * voids them: the rds_tracking row is removed (kept in the registration's
 * move_history and the audit entry) and rds_exists / cor_exists are reset, so
 * both are generated and printed again.
 */
class RegistrationMoveService
{
    private const OP_TYPE = 'Occupancy Permit (OP)';

    /** Instruments captured on the same form. Keyed by the family's label. */
    public const FAMILIES = [
        'Deed of Assignment / Gift' => ['Deed of Assignment', 'Deed of Gift'],
        'Mortgage' => ['Deed of Mortgage', 'Tripartite Mortgage'],
        'Surrender and Release / Power of Attorney' => [
            'Deed of Surrender and Release', 'Power of Attorney', 'Irrevocable Power of Attorney',
        ],
        'Occupancy Permit' => ['OP Resettlement', 'OP Direct Allocation'],
    ];

    public function __construct(
        protected InstrumentRegistrationService $vaults
    ) {
    }

    /**
     * The registration and the related instruments it can move to, with the
     * number each would give. Read-only.
     */
    public function options(int $deedId): array
    {
        $deed = $this->findDeed($deedId);
        $member = $this->memberOf($deed);
        $family = $this->familyOf($member);

        $targets = [];
        foreach (self::FAMILIES[$family] as $type) {
            if (strcasecmp($type, $member) === 0) {
                continue;
            }

            $sameVault = $this->sameVault($member, $type);
            $peek = $this->peek($type);

            $targets[] = [
                'type' => $type,
                'vault' => $peek['vault'],
                'configured' => (bool) $peek['configured'],
                'same_vault' => $sameVault,
                'next' => $peek['configured'] ? $peek['formatted'] : null,
                'blocked' => !$sameVault && (bool) ($peek['blocked'] ?? false),
                'block_message' => $peek['block_message'] ?? null,
            ];
        }

        return [
            'registration' => [
                'id' => $deed->id,
                'fileno' => $deed->fileno,
                'instrument_type' => $member,
                'family' => $family,
                'registration_number' => $deed->registration_number,
                'rds_exists' => $this->rdsRows($deed)->isNotEmpty() || !empty($deed->rds_exists),
                'cor_exists' => !empty($deed->cor_exists),
            ],
            'targets' => $targets,
        ];
    }

    /**
     * Move it to a related instrument.
     *
     * @return array{from:string, to:string, from_number:string, to_number:string, voided_documents:bool, snapshot:array, sync_result:mixed}
     */
    public function move(int $deedId, string $targetType): array
    {
        $targetType = trim($targetType);

        return DB::connection('sqlsrv')->transaction(function () use ($deedId, $targetType) {
            $db = DB::connection('sqlsrv');

            // Locked so two officers cannot move the same registration at once.
            $deed = $this->findDeed($deedId, true);
            $member = $this->memberOf($deed);
            $family = $this->familyOf($member);

            $target = collect(self::FAMILIES[$family])->first(fn ($t) => strcasecmp($t, $targetType) === 0);
            if ($target === null) {
                throw new RuntimeException("A {$member} can only be moved to a related instrument ("
                    . implode(', ', array_diff(self::FAMILIES[$family], [$member])) . ').');
            }
            if (strcasecmp($target, $member) === 0) {
                throw new RuntimeException("The registration is already a {$target}.");
            }

            $isOp = $family === 'Occupancy Permit';
            if (!$isOp) {
                $this->assertConsentAllows($deed, $target);
            }

            if ($this->sameVault($member, $target)) {
                // Shared register: the number already belongs to the target.
                $number = [
                    'volume' => (int) $deed->volume_no,
                    'page' => (int) $deed->page_no,
                    'serial' => (int) $deed->serial_no,
                    'formatted' => $deed->registration_number,
                ];
            } else {
                // Locks and advances the target vault; the lock is held by this
                // outer sqlsrv transaction until the move commits.
                $number = $isOp
                    ? $this->vaults->getRegistrationNumber(self::OP_TYPE, false, ['op_type' => $target])
                    : $this->vaults->getRegistrationNumber($target);
            }

            // The documents issued under the old particulars.
            $voidedRds = $this->rdsRows($deed);

            $data = json_decode((string) ($deed->data ?? ''), true) ?: [];
            $data['move_history'][] = [
                'from_type' => $member,
                'from_number' => $deed->registration_number,
                'to_type' => $target,
                'to_number' => $number['formatted'],
                'moved_by' => Auth::id(),
                'moved_at' => now()->toDateTimeString(),
                'voided_cor' => !empty($deed->cor_exists),
                'voided_rds' => $voidedRds->map(fn ($r) => (array) $r)->values()->all(),
            ];
            if ($isOp) {
                $data['op_type'] = $target;
            }

            $numberColumns = [
                'registration_number' => $number['formatted'],
                'volume_no' => $number['volume'],
                'page_no' => $number['page'],
                'serial_no' => $number['serial'],
            ];

            if ($voidedRds->isNotEmpty()) {
                $db->table('rds_tracking')->whereIn('id', $voidedRds->pluck('id'))->delete();
            }

            $db->table('deed_registrations')->where('id', $deed->id)->update($numberColumns + [
                // An OP keeps its instrument type; its register is op_type.
                'instrument_type' => $isOp ? $deed->instrument_type : $target,
                'rds_exists' => 0,
                'cor_exists' => 0,
                'data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

            if (!empty($deed->instrument_capture_id)) {
                $db->table('instrument_capture')->where('id', $deed->instrument_capture_id)
                    ->update($numberColumns + ($isOp ? ['op_type' => $target] : ['instrument_type' => $target])
                        + ['updated_at' => now()]);
            }

            // As a fresh registration of the target type would: an Assignment or
            // Gift carries the grantee onto the file.
            $syncResult = $isOp ? null : $this->vaults->syncPartyNames($deed->fileno, $target, $deed->grantee, [
                'deed_registration_id' => $deed->id,
                'instrument_capture_id' => $deed->instrument_capture_id,
                'source' => 'registration_move',
            ]);

            Log::warning('Registration moved', [
                'deed_registration_id' => $deed->id,
                'from' => $member . ' ' . $deed->registration_number,
                'to' => $target . ' ' . $number['formatted'],
                'user_id' => Auth::id(),
            ]);

            return [
                'from' => $member,
                'to' => $target,
                'from_number' => $deed->registration_number,
                'to_number' => $number['formatted'],
                'voided_documents' => $voidedRds->isNotEmpty() || !empty($deed->cor_exists),
                'snapshot' => (array) $deed + ['voided_rds' => $voidedRds->all()],
                'sync_result' => $syncResult,
            ];
        });
    }

    private function findDeed(int $deedId, bool $lock = false): object
    {
        $query = DB::connection('sqlsrv')->table('deed_registrations')->where('id', $deedId);
        $deed = ($lock ? $query->lockForUpdate() : $query)->first();

        if (!$deed) {
            throw new RuntimeException('Registration not found.');
        }
        if (!empty($deed->is_deleted)) {
            throw new RuntimeException('This registration has been deleted.');
        }
        if (empty($deed->serial_no) || empty($deed->volume_no)) {
            throw new RuntimeException('This registration has no registration particulars to move.');
        }

        return $deed;
    }

    /**
     * The family member a registration is: its instrument type, or for an
     * Occupancy Permit the register its capture names in op_type (the
     * registration row itself does not record it).
     */
    private function memberOf(object $deed): string
    {
        $type = trim((string) $deed->instrument_type);

        if (stripos($type, 'Occupancy Permit') === false) {
            return $type;
        }

        $opType = trim((string) (json_decode((string) ($deed->data ?? ''), true)['op_type'] ?? ''));
        if ($opType === '' && !empty($deed->instrument_capture_id)) {
            $opType = trim((string) DB::connection('sqlsrv')->table('instrument_capture')
                ->where('id', $deed->instrument_capture_id)->value('op_type'));
        }

        if ($opType === '') {
            throw new RuntimeException('This Occupancy Permit does not say whether it is a Resettlement or a Direct Allocation, so it cannot be moved.');
        }

        return str_starts_with(strtoupper($opType), 'OP ') ? $opType : 'OP ' . $opType;
    }

    private function familyOf(string $member): string
    {
        foreach (self::FAMILIES as $label => $members) {
            foreach ($members as $m) {
                if (strcasecmp($m, $member) === 0) {
                    return $label;
                }
            }
        }

        throw new RuntimeException("{$member} has no related instrument to move to. Only instruments captured on the same form can be moved: "
            . implode('; ', array_map(fn ($m) => implode(' / ', $m), self::FAMILIES)) . '.');
    }

    private function isOpMember(string $member): bool
    {
        return in_array($member, self::FAMILIES['Occupancy Permit'], true);
    }

    private function vaultFor(string $member): string
    {
        return $this->isOpMember($member)
            ? $this->vaults->resolveVaultName(self::OP_TYPE, $member)
            : $this->vaults->resolveVaultName($member);
    }

    private function sameVault(string $a, string $b): bool
    {
        return strcasecmp($this->vaultFor($a), $this->vaultFor($b)) === 0;
    }

    private function peek(string $member): array
    {
        return $this->isOpMember($member)
            ? $this->vaults->peekRegistrationNumber(self::OP_TYPE, $member)
            : $this->vaults->peekRegistrationNumber($member);
    }

    /**
     * A capture linked to a consent may only move to that consent's own
     * instrument (Assignment consent -> Deed of Assignment, Gift -> Deed of
     * Gift, Mortgage -> Deed of Mortgage / Tripartite Mortgage), the same rule
     * the capture screen enforces. Moving to a type no consent governs is not
     * checked here.
     */
    private function assertConsentAllows(object $deed, string $targetType): void
    {
        $expected = app(DeedsPipelineStatus::class)->consentTypeFor($targetType);
        if ($expected === null || empty($deed->instrument_capture_id)) {
            return;
        }

        $consentId = DB::connection('sqlsrv')->table('instrument_capture')
            ->where('id', $deed->instrument_capture_id)
            ->value('consent_application_id');
        if (!$consentId) {
            return;
        }

        $consent = DB::connection('sqlsrv')->table('consent_applications')
            ->where('id', $consentId)->first(['file_number', 'consent_type']);

        if ($consent && strcasecmp(trim((string) $consent->consent_type), $expected) !== 0) {
            $type = trim((string) $consent->consent_type);
            throw new RuntimeException("This registration's consent for file {$consent->file_number} is "
                . (preg_match('/^[aeiou]/i', $type) ? 'an ' : 'a ') . "{$type} consent, so it cannot be moved to a {$targetType}.");
        }
    }

    private function rdsRows(object $deed)
    {
        return DB::connection('sqlsrv')->table('rds_tracking')
            ->where('instrument_id', 'deed_reg_' . $deed->id)
            ->get();
    }
}
