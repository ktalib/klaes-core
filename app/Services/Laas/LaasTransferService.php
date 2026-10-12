<?php

namespace App\Services\Laas;

use App\Models\Laas\LaasApplication;
use App\Models\Laas\LaasApplicationEvent;
use App\Models\Laas\LaasTransferCandidate;
use App\Support\HolderName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ownership transfers on the LAAS Portal (plan §3.3).
 *
 * DETECT, NEVER MOVE. A registered assignment-type instrument (Deed of
 * Assignment / Purchase / Gift, Transfer of Title) on a portal file, registered
 * after the file was commissioned, whose transferee (party 2) is not the portal
 * holder, becomes a PENDING candidate. Nothing moves until staff approve it: on
 * a conversion file the Deed of Purchase names the buyer, who is already the
 * holder, and name spellings drift (ALH / ALHAJI), so an automatic move would
 * hand files to the wrong account.
 *
 * APPROVE. The old holder's row becomes transferred_out with visible_until =
 * the registration date (they keep the history up to then, nothing after). A
 * new row opens on the transferee's account, found or created by the same rule
 * as commissioning. No SMS: a new account's temporary password is returned for
 * the office to hand over, as with flagged accounts.
 */
class LaasTransferService
{
    /** instrument_capture.instrument_type patterns that move ownership. */
    private const TRANSFER_TYPES = ['%assignment%', '%purchase%', '%gift%', '%transfer of title%', '%deed of transfer%'];

    /** Property answers carried to the new holder's row; nothing personal. */
    private const PROPERTY_KEYS = ['plot_no', 'plan_no', 'location', 'district', 'lga', 'land_use', 'land_area', 'property_description', 'purpose'];

    public function __construct(private LaasCommissioningAccountService $accounts)
    {
    }

    /** Queue a candidate for one registered instrument, if it is one. Never throws. */
    public function detectFromCapture(int $captureId): ?LaasTransferCandidate
    {
        try {
            $capture = DB::connection('sqlsrv')->table('instrument_capture')->where('id', $captureId)->first();

            return $capture ? $this->consider($capture) : null;
        } catch (\Throwable $e) {
            Log::warning('LAAS transfer detection failed', ['capture_id' => $captureId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Sweep every registered transfer-type instrument on a portal file. Returns new candidates. */
    public function scanAll(): int
    {
        $found = 0;

        DB::connection('sqlsrv')->table('instrument_capture as c')
            ->whereExists(fn ($q) => $q->from('laas_applications as a')->whereColumn('a.file_number', 'c.mlsFNo'))
            ->where('c.is_deed_registered', 1)
            ->where(fn ($q) => $q->whereNull('c.is_deleted')->orWhere('c.is_deleted', 0))
            ->where(function ($q) {
                foreach (self::TRANSFER_TYPES as $pattern) {
                    $q->orWhere('c.instrument_type', 'like', $pattern);
                }
            })
            ->whereNotExists(fn ($q) => $q->from('laas_transfer_candidates as t')->whereColumn('t.instrument_capture_id', 'c.id'))
            ->select('c.*')
            ->orderBy('c.id')
            ->chunk(500, function ($captures) use (&$found) {
                foreach ($captures as $capture) {
                    if ($this->consider($capture)) {
                        $found++;
                    }
                }
            });

        return $found;
    }

    private function consider(object $capture): ?LaasTransferCandidate
    {
        $type = strtolower((string) $capture->instrument_type);
        $isTransfer = collect(self::TRANSFER_TYPES)->contains(fn ($p) => str_contains($type, trim($p, '%')));
        if (!$isTransfer || (int) ($capture->is_deed_registered ?? 0) !== 1) {
            return null;
        }

        $fileNumber = trim((string) $capture->mlsFNo);
        $current = $fileNumber === '' ? null : LaasApplication::where('file_number', $fileNumber)->current()->orderByDesc('id')->first();
        if (!$current) {
            return null;
        }

        // Registered before the file reached the portal: already reflected in
        // the commissioning holder (a conversion's root of title).
        $registered = $capture->reg_date ?? $capture->created_at ?? null;
        $registeredAt = $registered ? rescue(fn () => \Carbon\Carbon::parse($registered), null, false) : null;
        if ($registeredAt && $current->fileno_assigned_at && $registeredAt->lte($current->fileno_assigned_at)) {
            return null;
        }

        $transferee = trim((string) $capture->party_2_name);
        if ($transferee === '' || HolderName::normalise($transferee) === HolderName::normalise((string) $current->applicant_name)) {
            return null;
        }

        return LaasTransferCandidate::firstOrCreate(
            ['instrument_capture_id' => (int) $capture->id],
            [
                'file_number'         => $fileNumber,
                'laas_application_id' => $current->id,
                'instrument_type'     => $capture->instrument_type,
                'current_holder'      => $current->applicant_name,
                'transferee_name'     => $transferee,
                'transferee_phone'    => $capture->party_2_phone ?? null,
                'transferee_address'  => $capture->party_2_address ?? null,
                'registered_at'       => $registeredAt,
                'status'              => LaasTransferCandidate::STATUS_PENDING,
            ]
        );
    }

    /**
     * Move the file to the transferee's account.
     *
     * @return array{new_application:LaasApplication,username:string,temp_password:?string,account_created:bool}
     */
    public function approve(LaasTransferCandidate $candidate, int $staffId, ?string $note = null): array
    {
        if ($candidate->status !== LaasTransferCandidate::STATUS_PENDING) {
            throw new \RuntimeException('This transfer has already been decided.');
        }

        return DB::connection('sqlsrv')->transaction(function () use ($candidate, $staffId, $note) {
            $old = LaasApplication::where('file_number', $candidate->file_number)->current()
                ->orderByDesc('id')->lockForUpdate()->first();
            if (!$old) {
                throw new \RuntimeException('The file is no longer on the portal.');
            }

            [$account, $tempPassword, $created] = $this->accounts->findOrCreateAccount(
                (string) $candidate->transferee_name,
                $candidate->transferee_phone,
                null
            );
            if ((int) $account->id === (int) $old->laas_applicant_id) {
                throw new \RuntimeException('The transferee is already the holder of this account.');
            }

            $until = $candidate->registered_at ?? now();
            $form = array_intersect_key((array) ($old->form_data ?? []), array_flip(self::PROPERTY_KEYS));

            $new = LaasApplication::create([
                'reference_no'                    => LaasApplication::nextReference(),
                'laas_applicant_id'               => $account->id,
                'applicant_name'                  => mb_strtoupper((string) $candidate->transferee_name),
                'applicant_phone'                 => $account->textablePhone(),
                'applicant_email'                 => $account->email,
                'applicant_address'               => $candidate->transferee_address,
                'land_use'                        => $old->land_use,
                'purpose_id'                      => $old->purpose_id,
                'location'                        => $old->location,
                'plot_no'                         => $old->plot_no,
                'stage'                           => $old->stage,
                'origin'                          => $old->origin,
                'land_type'                       => $old->land_type,
                'form_data'                       => $form,
                'file_number'                     => $old->file_number,
                'mls_file_no_id'                  => $old->mls_file_no_id,
                'survey_report_request_id'        => $old->survey_report_request_id,
                'land_recommendation_id'          => $old->land_recommendation_id,
                'rofo_id'                         => $old->rofo_id,
                'fileno_assigned_at'              => $old->fileno_assigned_at,
                'submitted_at'                    => $old->submitted_at,
                'completed_at'                    => $old->completed_at,
                'lifecycle_status'                => $old->isRetired() ? LaasApplication::LIFECYCLE_RETIRED : LaasApplication::LIFECYCLE_ACTIVE,
                'lifecycle_changed_at'            => now(),
                'transferred_from_application_id' => $old->id,
                'entity_id'                       => $old->entity_id,
                'holder_source'                   => 'transfer:' . $candidate->instrument_capture_id,
            ]);

            $old->forceFill([
                'lifecycle_status'              => LaasApplication::LIFECYCLE_TRANSFERRED_OUT,
                'lifecycle_changed_at'          => now(),
                'transferred_to_application_id' => $new->id,
                'visible_until'                 => $until,
            ])->save();

            $type = $candidate->instrument_type ?: 'a registered instrument';
            // Dated at the cut-off, so the old holder still sees why the file left.
            $oldEvent = new LaasApplicationEvent([
                'laas_application_id'  => $old->id,
                'stage'                => $old->stage,
                'title'                => 'File transferred',
                'body'                 => "This file passed to a new holder by {$type}. You can see its history up to that date.",
                'actor_type'           => 'staff',
                'actor_id'             => $staffId,
                'visible_to_applicant' => true,
            ]);
            $oldEvent->created_at = $until;
            $oldEvent->save();

            LaasApplicationEvent::create([
                'laas_application_id'  => $new->id,
                'stage'                => $new->stage,
                'title'                => 'File transferred to you',
                'body'                 => "File Number {$new->file_number} was added to your portal account after {$type} was registered.",
                'actor_type'           => 'staff',
                'actor_id'             => $staffId,
                'visible_to_applicant' => true,
            ]);

            $candidate->forceFill([
                'status'             => LaasTransferCandidate::STATUS_APPROVED,
                'decision_note'      => $note,
                'decided_by'         => $staffId,
                'decided_at'         => now(),
                'new_application_id' => $new->id,
            ])->save();

            return [
                'new_application' => $new,
                'username'        => (string) $account->username,
                'temp_password'   => $tempPassword,
                'account_created' => $created,
            ];
        });
    }

    public function dismiss(LaasTransferCandidate $candidate, int $staffId, ?string $note = null): void
    {
        if ($candidate->status !== LaasTransferCandidate::STATUS_PENDING) {
            throw new \RuntimeException('This transfer has already been decided.');
        }

        $candidate->forceFill([
            'status'        => LaasTransferCandidate::STATUS_DISMISSED,
            'decision_note' => $note,
            'decided_by'    => $staffId,
            'decided_at'    => now(),
        ])->save();
    }
}
