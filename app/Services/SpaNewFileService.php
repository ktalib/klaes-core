<?php

namespace App\Services;

use App\Models\ChangeOfPurposeApplication;
use App\Models\SpaApplication;
use App\Models\SpaBill;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SPAS → a new file number, once the contravention bill is settled — sent by an
 * officer from the Bills table (Send to Change of Purpose), never on its own.
 *
 *   Change of Purpose   an existing title (Direct Allocation, or an existing
 *                       Conversion): a Change of Purpose record is created,
 *                       already Approved — its KAMMA / application /
 *                       recommendation / decision steps are bypassed — so it is
 *                       ready to pick on the MLS commissioning screen, where the
 *                       new number comes from the old one. Source: SPAS.
 *   New Conversion      a new entry with no record before: waits for a new file
 *                       number under Conversion. Manual for now — the officer
 *                       issues it on the MLS screen and records it here.
 *
 * The route comes from the land title type: Statutory with a real file number
 * is an existing title; Customary (the SPAS ID stands in) is a new entry.
 *
 * Never throws into the payment that triggered it: a fault here must not undo
 * money that was received. Every step is safe to run again.
 */
class SpaNewFileService
{
    public const COP            = 'change_of_purpose';
    public const NEW_CONVERSION = 'new_conversion';
    public const SOURCE         = 'spas';

    private const PURPOSE_CODES = [
        'RESIDENTIAL' => 'RES', 'COMMERCIAL' => 'COM', 'INDUSTRIAL' => 'IND',
        'AGRICULTURAL' => 'AG', 'AGRICULTURE' => 'AG', 'AGRIC' => 'AG',
        'MIXED' => 'MIX', 'MIXED USE' => 'MIX',
    ];

    private static ?bool $supported = null;

    /** The columns arrive with migration 2026_10_12_090000; until then nothing happens. */
    public static function supported(): bool
    {
        return self::$supported ??= Schema::connection('sqlsrv')->hasColumn('spa_applications', 'new_file_route');
    }

    /** Which route this record takes to its new file number. */
    public function routeFor(SpaApplication $app): string
    {
        $file = trim((string) $app->file_number);

        return $app->land_title_type !== 'customary' && $file !== '' && $file !== trim((string) $app->spa_id)
            ? self::COP
            : self::NEW_CONVERSION;
    }

    /**
     * Settled = it has a bill and every bill on it is either fully paid (older
     * SPAS-paid bills) or handed to KLAES REV-M, where it is generated and paid.
     * Payment under REV-M never holds the workflow back (user decision, 2026-10-19).
     */
    public function settled(SpaApplication $app): bool
    {
        $bills = SpaBill::where('spa_application_id', $app->id)->get(['id', 'status']);
        $inRevm = \App\Services\Billing\Modules\SpasBillMirror::inBillMaster($bills->pluck('id'));

        return $bills->isNotEmpty() && $bills->every(fn ($b) => $b->status === 'paid' || $inRevm->has((int) $b->id));
    }

    /** Types the officer picks on the Send to Change of Purpose card. */
    public const TYPE_COP        = 'change_of_purpose';   // existing title: old file no. required
    public const TYPE_CONVERSION = 'conversion';          // conversion: old file no. optional

    /** Whether the record carries a real file number (not just its SPAS ID). */
    public function hasFileNumber(SpaApplication $app): bool
    {
        $file = trim((string) $app->file_number);

        return $file !== '' && $file !== trim((string) $app->spa_id);
    }

    /** The type the card starts on: Conversion for a CON- file or a record with no file number. */
    public function defaultType(SpaApplication $app): string
    {
        return ! $this->hasFileNumber($app) || preg_match('/^CON\b|^CON-/i', trim((string) $app->file_number))
            ? self::TYPE_CONVERSION : self::TYPE_COP;
    }

    /** Why this record cannot be sent yet, or null when it can. */
    public function sendBlocker(SpaApplication $app): ?string
    {
        if (! self::supported()) {
            return 'Run migration 2026_10_12_090000 first.';
        }
        if ($app->new_file_route) {
            return 'This record has already been sent to Change of Purpose.';
        }
        if (! $this->settled($app)) {
            return 'The bill must be settled (paid, or handed to KLAES REV-M) before the record can be sent to Change of Purpose.';
        }

        return null;
    }

    /**
     * Send a settled record to the Change of Purpose register — by an officer,
     * from the Send to Change of Purpose card (2026-10-13; it used to happen on
     * its own when the bill was settled).
     *
     * The Change of Purpose is created APPROVED, its approvals bypassed, with
     * source SPAS and the PREVAILING land use as the new purpose. The old file
     * number comes from the card: required for a Change of Purpose of an
     * existing title, optional for a Conversion and for a record captured
     * without a file number.
     *
     * @throws \InvalidArgumentException with a message for the officer
     */
    public function sendToChangeOfPurpose(SpaApplication $app, ?string $oldFileNo, string $type, ?int $actorId = null): ChangeOfPurposeApplication
    {
        if ($blocker = $this->sendBlocker($app)) {
            throw new \InvalidArgumentException($blocker);
        }
        if (! in_array($type, [self::TYPE_COP, self::TYPE_CONVERSION], true)) {
            throw new \InvalidArgumentException('Choose Change of Purpose or Conversion.');
        }

        $old = strtoupper(trim((string) $oldFileNo));
        if ($old === '' && $type === self::TYPE_COP && $this->hasFileNumber($app)) {
            throw new \InvalidArgumentException('Select the old file number for a Change of Purpose.');
        }
        if (trim((string) $app->existing_use) === '') {
            throw new \InvalidArgumentException('The record has no prevailing land use, so there is no new purpose to apply.');
        }

        return \Illuminate\Support\Facades\DB::connection('sqlsrv')->transaction(function () use ($app, $old, $type, $actorId) {
            $cop = $this->changeOfPurposeFor($app, $old, $type, $actorId);
            $app->forceFill(['new_file_route' => self::COP, 'cop_application_id' => $cop->id])->save();
            Log::info('SPAS record sent to Change of Purpose', ['application' => $app->id, 'cop' => $cop->id, 'type' => $type, 'old_file_no' => $old, 'by' => $actorId]);

            return $cop;
        });
    }

    /**
     * The Change of Purpose for this record: one already in progress for the
     * same old file is reused (a file may carry only one live application),
     * otherwise a new one is created, approved.
     */
    private function changeOfPurposeFor(SpaApplication $app, string $old, string $type, ?int $actorId): ChangeOfPurposeApplication
    {
        if ($old !== '') {
            $live = ChangeOfPurposeApplication::query()
                ->where('file_no', $old)
                ->whereIn('status', [
                    ChangeOfPurposeApplication::STATUS_PENDING,
                    ChangeOfPurposeApplication::STATUS_PROCESSING,
                    ChangeOfPurposeApplication::STATUS_APPROVED,
                ])
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderByDesc('id')
                ->first();

            if ($live) {
                return $live;
            }
        }

        // The new purpose is the PREVAILING land use — what the property is used for.
        $newUse = strtoupper(trim((string) $app->existing_use));
        $bill   = SpaBill::where('spa_application_id', $app->id)->orderBy('id')->value('reference_id');

        return ChangeOfPurposeApplication::create([
            'applicant_name'      => $app->owner_name ?: '—',
            // '' rather than null when there is no old file: the commissioning
            // picker's details lookup takes a string.
            'file_no'             => $old,
            'land_use'            => ucwords(strtolower(trim((string) $app->proposed_use))) ?: null,
            'purpose'             => self::PURPOSE_CODES[$newUse] ?? mb_substr($newUse, 0, 50),
            'new_purpose'         => $newUse,
            'location'            => $app->location,
            'district'            => $app->district,
            'lga'                 => $app->lga,
            'phone'               => $app->phone,
            'status'              => ChangeOfPurposeApplication::STATUS_APPROVED,
            'knupda_status'       => 'Approved',
            'source_module'       => self::SOURCE,
            'plot_count'          => 1,
            'captured_by'         => $actorId,
            'remarks'             => 'Sent by SPAS ' . ($app->spa_id ?: '#' . $app->id)
                . ' (' . ($type === self::TYPE_CONVERSION ? 'Conversion' : 'Change of Purpose') . ')'
                . ($bill ? " after bill {$bill} was settled" : '')
                . ' — contravention: approved ' . ($app->proposed_use ?: '—') . ', in use as ' . $app->existing_use
                . ($old === '' ? '. No old file number.' : '.')
                . ' Approvals bypassed (SPAS).',
        ]);
    }

    /**
     * Pick up the new file number once the Change of Purpose was commissioned on
     * the MLS screen. That screen keeps the number only in the remarks
     * ("Commissioned: OLD -> NEW" / "Commissioned to File No: NEW").
     */
    public function sync(SpaApplication $app): ?string
    {
        if (! self::supported() || $app->new_file_number || $app->new_file_route !== self::COP || ! $app->cop_application_id) {
            return $app->new_file_number ?? null;
        }

        $cop = ChangeOfPurposeApplication::find($app->cop_application_id);
        if (! $cop || $cop->status !== ChangeOfPurposeApplication::STATUS_COMMISSIONED) {
            return null;
        }

        if (preg_match_all('/(?:->|File No:)\s*([^\s,;]+)/i', (string) $cop->remarks, $m) && ($number = end($m[1]))) {
            $app->forceFill(['new_file_number' => $number, 'new_file_at' => $cop->updated_at ?? now()])->save();

            return $number;
        }

        return null;
    }

    /** Sync every record still waiting on a commissioned Change of Purpose. Cheap: only routed, unnumbered rows. */
    public function syncAll(): int
    {
        if (! self::supported()) {
            return 0;
        }
        $n = 0;
        SpaApplication::where('new_file_route', self::COP)->whereNull('new_file_number')->whereNotNull('cop_application_id')
            ->get()->each(function ($app) use (&$n) { if ($this->sync($app)) { $n++; } });

        return $n;
    }

    /** Record a number issued by hand (New Conversion, for now). */
    public function record(SpaApplication $app, string $number): void
    {
        $app->forceFill(['new_file_number' => trim($number), 'new_file_at' => now()])->save();
    }
}
