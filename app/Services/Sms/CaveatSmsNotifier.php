<?php

namespace App\Services\Sms;

use App\Models\Caveat;
use App\Models\SmsSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tells the caveator when a caveat goes on, and when it comes off.
 *
 * Three moments, two of which are the same event reached different ways:
 *
 *   placed        CaveatController::store()
 *   lifted        CaveatController::lift()          -> status 'released'
 *                 CaveatController::removeByFile()  -> status 'lifted'
 *   auto-lifted   the caveats:expire command        -> status 'expired'
 *
 * The two manual paths write DIFFERENT status strings for the same act, which is
 * pre-existing and deliberately left alone -- changing them would reinterpret
 * every historical row. Both are one message here regardless.
 *
 * WHO IS TOLD: the caveator (or their solicitor), on caveats.petitioner_phone,
 * which was added with this feature -- the table recorded a petitioner and their
 * address and no way to reach them. The property's holder is NOT told. A caveat
 * is an assertion of a competing interest, and texting the registered holder
 * "somebody has placed a caveat on your land" is a decision for the Ministry to
 * take deliberately, not a side effect of switching SMS on.
 */
class CaveatSmsNotifier
{
    public function __construct(
        private KlaesSmsDispatcher $dispatcher,
        private ApplicantPhoneResolver $phones
    ) {
    }

    /** A caveat has just been placed. */
    public function placed(Caveat $caveat, ?string $phone = null, bool $phoneConfirmed = false): void
    {
        $this->send(SmsSetting::KEY_CAVEAT_PLACED, $caveat, $phone, $phoneConfirmed, [
            'Applicant' => $caveat->petitioner,
        ]);
    }

    /** A caveat has been lifted by hand -- either lift path. */
    public function lifted(Caveat $caveat, ?string $liftedBy = null): void
    {
        $this->send(SmsSetting::KEY_CAVEAT_LIFTED, $caveat, null, false, [
            'Applicant' => $liftedBy ?: ($caveat->updated_by ?: $caveat->petitioner),
        ]);
    }

    /** A caveat has expired and been lifted by the scheduled sweep. */
    public function autoLifted(Caveat $caveat): void
    {
        $this->send(SmsSetting::KEY_CAVEAT_AUTO_LIFTED, $caveat, null, false);
    }

    /**
     * Compose and queue one caveat message.
     *
     * @param  array  $extra  Tokens beyond the three every caveat message shares.
     */
    private function send(string $key, Caveat $caveat, ?string $phone, bool $phoneConfirmed, array $extra = []): void
    {
        try {
            if (!$this->dispatcher->enabled($key)) {
                return;
            }

            $fileNumber = $this->fileNumberFor($caveat);

            $phone = trim((string) ($phone ?? $caveat->petitioner_phone ?? ''));

            if ($phone === '') {
                /*
                 | Historical caveats have no number of their own. prop_id is the
                 | better key than the file number here -- caveats has no plain
                 | file_number column, only three typed ones and an id -- and it
                 | reaches the parcel's indexed contact directly.
                 */
                $resolved = $caveat->prop_id
                    ? $this->phones->forPropId($caveat->prop_id)
                    : $this->phones->forFileNumber($fileNumber);

                $phone = $resolved['phone'];
                $phoneConfirmed = false;
            }

            $this->dispatcher->queue($key, $phone, array_merge([
                'FileNo' => $fileNumber,
                'Instrument Type' => $this->instrumentLabel($caveat),
                'DateTime' => $this->moment($key, $caveat),
            ], $extra), [
                // Keyed on the caveat AND the event, so a caveat that is placed,
                // lifted, re-placed and lifted again produces four messages and
                // not two.
                'dedupe_key' => $key . ':' . $caveat->id,
                'file_number' => $fileNumber,
                'subject_type' => 'caveats',
                'subject_id' => $caveat->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CaveatSmsNotifier: caveat SMS not queued', [
                'key' => $key,
                'caveat_id' => $caveat->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The file number to quote back to the caveator.
     *
     * caveats has no plain `file_number` column -- only three typed ones
     * (file_number_mlsf / _kangis / _new_kangis) plus a file_number_id pointing
     * at one of two different tables. Note that CaveatController::removeByFile()
     * reads $caveat->file_number, which resolves to null on every row; that is a
     * pre-existing bug and the reason this method exists rather than reusing it.
     */
    private function fileNumberFor(Caveat $caveat): string
    {
        foreach (['file_number_mlsf', 'file_number_kangis', 'file_number_new_kangis'] as $column) {
            $value = trim((string) ($caveat->{$column} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        if (!empty($caveat->file_number_id)) {
            try {
                $row = DB::connection('sqlsrv')->table('fileNumber')
                    ->where('id', $caveat->file_number_id)
                    ->first();

                if ($row) {
                    $value = $row->kangisFileNo ?: ($row->mlsfNo ?: ($row->NewKANGISFileNo ?: null));

                    if (trim((string) $value) !== '') {
                        return (string) $value;
                    }
                }

                $value = DB::connection('sqlsrv')->table('file_numbers')
                    ->where('id', $caveat->file_number_id)
                    ->value('file_number');

                if (trim((string) $value) !== '') {
                    return (string) $value;
                }
            } catch (\Throwable $e) {
                // Fall through to the caveat number below.
            }
        }

        // Nothing else identifies the property to the caveator, and a message
        // with a blank where the file number should be is not worth sending --
        // so quote the caveat's own number instead.
        return (string) ($caveat->caveat_number ?? '');
    }

    /**
     * The instrument the caveat sits on, in words.
     *
     * instrument_type_id is nullable and encumbrance_type is the free-text field
     * officers actually fill in, so the name is tried first and the encumbrance
     * used as the fallback.
     */
    private function instrumentLabel(Caveat $caveat): string
    {
        try {
            $name = $caveat->relationLoaded('instrumentType')
                ? ($caveat->instrumentType->InstrumentName ?? null)
                : ($caveat->instrument_type_id
                    ? DB::connection('sqlsrv')->table('InstrumentTypes')
                        ->where('InstrumentTypeID', $caveat->instrument_type_id)
                        ->value('InstrumentName')
                    : null);

            if (trim((string) $name) !== '') {
                return (string) $name;
            }
        } catch (\Throwable $e) {
            // Fall through.
        }

        return trim((string) ($caveat->encumbrance_type ?? '')) ?: 'the registered instrument';
    }

    /**
     * The moment the message reports, on the office clock.
     *
     * Placing quotes start_date (when the caveat took effect, which an officer
     * may back-date); lifting quotes release_date. Both fall back to now, because
     * a message that says "on" and then nothing reads as broken.
     */
    private function moment(string $key, Caveat $caveat): string
    {
        $timezone = config('klaes_sms.timezone', 'Africa/Lagos');
        $format = config('klaes_sms.datetime_format', 'd/m/Y h:i A');

        $raw = $key === SmsSetting::KEY_CAVEAT_PLACED
            ? ($caveat->start_date ?? null)
            : ($caveat->release_date ?? null);

        try {
            return \Carbon\Carbon::parse($raw ?: now())
                ->setTimezone($timezone)
                ->format($format);
        } catch (\Throwable $e) {
            return \Carbon\Carbon::now($timezone)->format($format);
        }
    }
}
