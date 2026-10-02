<?php

namespace App\Services\Sms;

use App\Models\SmsSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Your Letter of Grant is ready" -- for Land, Sectional Titling and SLTR.
 *
 * One message, three departments, three completely different record shapes:
 *
 *   Land   land_recommendations, keyed by file_number.   No phone column.
 *   ST     rofo + subapplications + mother_applications. Phone on both of the
 *          application tables, which is unusual here and worth using.
 *   SLTR   sltr_recommendations, keyed by sltr_number.   No phone column.
 *
 * Rather than repeat the lookup-and-guard dance in three controllers, each one
 * calls send() with what it has. Two of the three have nowhere to store a phone
 * number at all, so for those the resolver's file_indexings fallback is the only
 * route -- and its shared-number guard is doing real work: a Letter of Grant
 * texted to the agent who indexed a thousand files helps nobody.
 */
class RofoSmsNotifier
{
    public const SOURCE_LAND = 'land';
    public const SOURCE_ST = 'st';
    public const SOURCE_SLTR = 'sltr';

    public function __construct(
        private KlaesSmsDispatcher $dispatcher,
        private ApplicantPhoneResolver $phones
    ) {
    }

    /**
     * Queue the Letter of Grant message for one file.
     *
     * @param  string       $source      One of the SOURCE_* constants.
     * @param  string|null  $fileNumber  The number the applicant knows the file by.
     * @param  mixed        $recordId    Recommendation / rofo row id, for the audit trail.
     * @param  string       $department  Named in the message, so it says where to collect.
     * @param  string|null  $phone       A number the caller already holds, if any.
     */
    public function send(string $source, ?string $fileNumber, $recordId, string $department, ?string $phone = null): void
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber === '') {
            // Nothing to tell the applicant to quote, and nothing to look their
            // number up by. Not an error -- some SLTR rows have no number yet.
            return;
        }

        try {
            $key = SmsSetting::KEY_ROFO_GENERATED;

            if (!$this->dispatcher->enabled($key)) {
                return;
            }

            $phone = trim((string) $phone);

            if ($phone === '') {
                $phone = $this->phones->forFileNumber($fileNumber)['phone'];
            }

            $this->dispatcher->queue($key, $phone, [
                'FileNo' => $fileNumber,
                'Department' => $department,
            ], [
                /*
                 | Keyed on the file, not the recommendation row: a RofO can be
                 | re-generated (a correction, a batch member picked up twice)
                 | and the applicant should be told once that theirs is ready,
                 | not once per generation.
                 */
                'dedupe_key' => $key . ':' . $source . ':' . $fileNumber,
                'file_number' => $fileNumber,
                'subject_type' => $this->subjectTypeFor($source),
                'subject_id' => $recordId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('RofoSmsNotifier: RofO SMS not queued', [
                'source' => $source,
                'file_number' => $fileNumber,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The ST applicant's own number, from the application the unit belongs to.
     *
     * Sectional Titling is the one flow here with a real phone number on file --
     * subapplications.phone_number for the unit holder, and the mother
     * application's for a primary. Preferring it over file_indexings avoids the
     * shared-number problem entirely for this department.
     */
    public function phoneForStUnit($subApplicationId, $motherApplicationId = null): ?string
    {
        try {
            if ($subApplicationId) {
                $phone = DB::connection('sqlsrv')->table('subapplications')
                    ->where('id', $subApplicationId)
                    ->value('phone_number');

                if (trim((string) $phone) !== '') {
                    return (string) $phone;
                }
            }

            if ($motherApplicationId) {
                $phone = DB::connection('sqlsrv')->table('mother_applications')
                    ->where('id', $motherApplicationId)
                    ->value('phone_number');

                if (trim((string) $phone) !== '') {
                    /*
                     | mother_applications.phone_number is a varchar(1000) that
                     | sometimes holds a LIST for a jointly-owned property. Take
                     | the first entry: texting the first-named owner is the
                     | convention the rest of the ST screens already follow.
                     */
                    return trim(explode(',', (string) $phone)[0]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('RofoSmsNotifier: ST phone lookup failed', [
                'subapplication_id' => $subApplicationId,
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function subjectTypeFor(string $source): string
    {
        return match ($source) {
            self::SOURCE_ST => 'rofo',
            self::SOURCE_SLTR => 'sltr_recommendations',
            default => 'land_recommendations',
        };
    }
}
