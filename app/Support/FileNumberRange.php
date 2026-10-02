<?php

namespace App\Support;

/**
 * Writes a set of file numbers the way the commissioning screen writes them:
 * RES-2026-3029-3033 rather than five numbers in a row.
 *
 * WHY A RANGE AND NOT A LIST
 * A batch commissioned for one applicant can run to 200 files. Listing them
 * costs a page every five numbers and produces a message nobody can read; the
 * range is a fixed length, so 200 files cost exactly what 2 files cost. It is
 * also the notation the officer already sees on the Generated File Number
 * Preview, so the applicant and the counter are quoting the same thing.
 *
 * THE RULE THAT KEEPS IT HONEST: only CONSECUTIVE serials collapse.
 *
 * "RES-2026-3029-3033" asserts that all five files between those serials belong
 * to the same person, and that is not always true:
 *
 *   - the batch itself skips serials already taken by another file
 *     (see $skippedSerials in MlsFileNoController::generateBatch), so a batch of
 *     five can come out as 3029, 3030, 3032, 3033, 3034;
 *   - within one batch the files can be split between applicants, leaving one
 *     of them holding 3029, 3031 and 3033.
 *
 * Collapsing the whole set start-to-end in either case would tell somebody they
 * own files that belong to a stranger. So runs are collapsed individually and
 * joined, and a run of one stays a plain file number.
 */
class FileNumberRange
{
    /**
     * Render a set of file numbers as ranges where they are contiguous.
     *
     * Numbers that do not parse as <prefix>-<year>-<serial>, or that come from
     * more than one prefix or year, are listed verbatim rather than forced into
     * a range -- being unable to abbreviate is not a reason to be wrong.
     *
     * @param  array<int,string>  $fileNumbers
     */
    public static function describe(array $fileNumbers): string
    {
        $fileNumbers = array_values(array_unique(array_filter(array_map('trim', $fileNumbers))));

        if (empty($fileNumbers)) {
            return '';
        }

        if (count($fileNumbers) === 1) {
            return $fileNumbers[0];
        }

        $parsed = [];

        foreach ($fileNumbers as $number) {
            $bits = self::parse($number);

            if ($bits === null) {
                // One unparseable number and the whole set is listed as-is.
                return self::join($fileNumbers);
            }

            $parsed[$bits['stem']][] = $bits['serial'];
        }

        $parts = [];

        foreach ($parsed as $stem => $serials) {
            foreach (self::runs($serials) as [$from, $to]) {
                $parts[] = $from === $to
                    ? $stem . '-' . $from
                    : $stem . '-' . $from . '-' . $to;
            }
        }

        return self::join($parts);
    }

    /**
     * Split "RES-2026-3029" into its stem and serial.
     *
     * The serial is the LAST hyphen-separated segment and must be all digits;
     * everything before it is the stem, which keeps this working for the longer
     * shapes in use (CON-RES-1987-1081, ST-COM-2026-15) without needing to know
     * what each prefix means.
     *
     * @return array{stem:string, serial:int}|null
     */
    public static function parse(string $fileNumber): ?array
    {
        $fileNumber = trim($fileNumber);
        $at = strrpos($fileNumber, '-');

        if ($at === false || $at === 0) {
            return null;
        }

        $serial = substr($fileNumber, $at + 1);

        if ($serial === '' || !ctype_digit($serial)) {
            return null;
        }

        return [
            'stem' => substr($fileNumber, 0, $at),
            // Kept as an int so 3029 and 03029 group together, and so the
            // sort below is numeric rather than lexical (which would put
            // 10 before 9).
            'serial' => (int) $serial,
        ];
    }

    /**
     * Consecutive runs within a set of serials.
     *
     * @param  array<int,int>  $serials
     * @return array<int,array{0:int,1:int}>
     */
    public static function runs(array $serials): array
    {
        $serials = array_values(array_unique($serials));
        sort($serials, SORT_NUMERIC);

        $runs = [];
        $start = $previous = null;

        foreach ($serials as $serial) {
            if ($start === null) {
                $start = $previous = $serial;
                continue;
            }

            if ($serial === $previous + 1) {
                $previous = $serial;
                continue;
            }

            $runs[] = [$start, $previous];
            $start = $previous = $serial;
        }

        if ($start !== null) {
            $runs[] = [$start, $previous];
        }

        return $runs;
    }

    /** "A, B and C" -- read aloud at a counter, not parsed by a machine. */
    private static function join(array $parts): string
    {
        if (count($parts) === 1) {
            return $parts[0];
        }

        $last = array_pop($parts);

        return implode(', ', $parts) . ' and ' . $last;
    }
}
