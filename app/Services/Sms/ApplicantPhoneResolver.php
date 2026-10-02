<?php

namespace App\Services\Sms;

use App\Services\BulkSmsNgService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finds the phone number to text about a file, and says how much to trust it.
 *
 * WHY THE TRUST PART MATTERS MORE THAN THE FINDING PART
 *
 * file_indexings.phone is the only phone column in this database with any real
 * volume -- 17,496 rows -- and it is badly polluted. Across those rows there are
 * only 8,901 distinct numbers: one sits on 1,211 different files, the next on
 * 492, the next on 245. Those are indexing clerks, agents and solicitors who
 * keyed a batch of files, not the applicants who own them.
 *
 * So a resolver that just returned the number would, the first time somebody
 * enabled the commissioning SMS, text one clerk about a thousand strangers'
 * files. Every answer therefore carries `shared_count` -- how many DISTINCT file
 * numbers that same number appears against -- and `shared`, which is true past
 * config('klaes_sms.shared_phone_threshold'). The forms show that as a warning
 * the officer must clear, and KlaesSmsDispatcher refuses to send to a shared,
 * unconfirmed number.
 *
 * Every other candidate column was measured and rejected:
 *   mls_file_no.phone_no, fileNumber.rep_phone_no   100% empty (never written)
 *   fileNumber.phone_no                             117 rows, 47 of them "080"
 *   oss_applications.phone                          5% coverage
 *   pra, PropID_Master, st_file_numbers, caveats    no phone column at all
 *
 * That is why the forms now capture a number directly: this resolver is a
 * prefill and a fallback, never the only route.
 */
class ApplicantPhoneResolver
{
    public const SOURCE_NONE = 'none';
    public const SOURCE_FILE_INDEXINGS = 'file_indexings';
    public const SOURCE_PROP_ID = 'file_indexings.prop_id';
    public const SOURCE_SUPPLIED = 'supplied';

    /**
     * The best number we hold for a file, with its provenance and trust.
     *
     * @return array{phone:?string, raw:?string, source:string, shared:bool, shared_count:int}
     */
    public function forFileNumber(?string $fileNumber): array
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber === '') {
            return $this->none();
        }

        try {
            /*
             | A file number can be recorded in any of four columns depending on
             | which registry indexed it, and the same physical file often lives
             | under a KANGIS number in one row and an MLS number in another.
             | Newest row first: a re-index is a correction of the older one.
             */
            $row = DB::connection('sqlsrv')->table('file_indexings')
                ->select('phone', 'prop_id')
                ->where(function ($q) use ($fileNumber) {
                    $q->where('file_number', $fileNumber)
                        ->orWhere('mls_file_no', $fileNumber)
                        ->orWhere('kangis_file_no', $fileNumber)
                        ->orWhere('new_kangis_file_no', $fileNumber);
                })
                ->orderByDesc('id')
                ->first();

            if ($row && $this->usable($row->phone ?? null)) {
                return $this->describe($row->phone, self::SOURCE_FILE_INDEXINGS);
            }

            /*
             | Nothing usable on the file's own row. Try the parcel: a
             | subdivision child or a re-issued file often has no contact details
             | of its own while a sibling under the same prop_id does.
             */
            $propId = $row->prop_id ?? null;

            if ($propId !== null && $propId !== '') {
                return $this->forPropId($propId);
            }
        } catch (\Throwable $e) {
            // A missing column or an unreachable database must not take a
            // commissioning down. No number simply means no message.
            Log::warning('ApplicantPhoneResolver: lookup failed', [
                'file_number' => $fileNumber,
                'message' => $e->getMessage(),
            ]);
        }

        return $this->none();
    }

    /**
     * The best number held against a parcel, for records that key on prop_id
     * rather than a file number -- caveats, chiefly.
     *
     * @return array{phone:?string, raw:?string, source:string, shared:bool, shared_count:int}
     */
    public function forPropId($propId): array
    {
        if ($propId === null || $propId === '') {
            return $this->none();
        }

        try {
            $row = DB::connection('sqlsrv')->table('file_indexings')
                ->select('phone')
                ->where('prop_id', $propId)
                ->whereNotNull('phone')
                ->where('phone', '<>', '')
                ->orderByDesc('id')
                ->first();

            if ($row && $this->usable($row->phone)) {
                return $this->describe($row->phone, self::SOURCE_PROP_ID);
            }
        } catch (\Throwable $e) {
            Log::warning('ApplicantPhoneResolver: prop_id lookup failed', [
                'prop_id' => $propId,
                'message' => $e->getMessage(),
            ]);
        }

        return $this->none();
    }

    /**
     * Describe a number the officer typed, or one the caller already holds, so a
     * hand-entered number gets the same shared-number verdict as a looked-up one.
     * An agent's number is no more the applicant's for having been typed in.
     *
     * @return array{phone:?string, raw:?string, source:string, shared:bool, shared_count:int}
     */
    public function describe(?string $raw, string $source = self::SOURCE_SUPPLIED): array
    {
        $normalised = BulkSmsNgService::normalizeNumber((string) $raw);
        $trimmed = trim((string) $raw);

        if ($normalised === null) {
            return [
                'phone' => null,
                'raw' => $trimmed !== '' ? $trimmed : null,
                'source' => $source,
                'shared' => false,
                'shared_count' => 0,
            ];
        }

        $count = $this->sharedCount($normalised);
        $threshold = (int) config('klaes_sms.shared_phone_threshold', 5);

        return [
            'phone' => $normalised,
            'raw' => $trimmed,
            'source' => $source,
            'shared' => $threshold > 0 && $count > $threshold,
            'shared_count' => $count,
        ];
    }

    /**
     * How many DISTINCT file numbers carry this number.
     *
     * Counted on the normalised form, because the same handset is stored as
     * 08031234567 on one row and +234 803 123 4567 on another; comparing the raw
     * strings would report every clerk as unique and defeat the guard. That means
     * normalising in SQL too -- strip the punctuation, then compare the last ten
     * digits, which identify the handset in every stored form.
     *
     * Cached for an hour: this runs on a form keystroke, and the answer moves
     * only as fast as the indexing backlog does.
     */
    public function sharedCount(string $normalised): int
    {
        $ttl = (int) config('klaes_sms.shared_phone_cache_ttl', 3600);

        return (int) Cache::remember(
            'klaes_sms.shared_phone.' . $normalised,
            $ttl,
            function () use ($normalised) {
                try {
                    $tail = substr($normalised, -10);

                    $stripped = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')";

                    return (int) DB::connection('sqlsrv')->table('file_indexings')
                        ->whereNotNull('phone')
                        ->where('phone', '<>', '')
                        ->whereRaw("RIGHT({$stripped}, 10) = ?", [$tail])
                        ->distinct()
                        ->count('file_number');
                } catch (\Throwable $e) {
                    /*
                     | Fail OPEN, not closed. If this count cannot be taken we
                     | return 0, which reads as "not shared" and lets the message
                     | go. Failing closed would silently stop every SMS in the
                     | system the moment this query broke, with nothing to say so.
                     */
                    Log::warning('ApplicantPhoneResolver: shared-number count failed', [
                        'message' => $e->getMessage(),
                    ]);

                    return 0;
                }
            }
        );
    }

    /** Is this string a usable Nigerian mobile number? */
    private function usable(?string $phone): bool
    {
        return BulkSmsNgService::normalizeNumber((string) $phone) !== null;
    }

    /** @return array{phone:null, raw:null, source:string, shared:bool, shared_count:int} */
    private function none(): array
    {
        return [
            'phone' => null,
            'raw' => null,
            'source' => self::SOURCE_NONE,
            'shared' => false,
            'shared_count' => 0,
        ];
    }
}
