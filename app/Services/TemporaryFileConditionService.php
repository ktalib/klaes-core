<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * The "Temporary File" condition on Problem Files (CR 11112026-2, item 1).
 *
 * A temporary file "X(T)" is opened when the main file X cannot be found. When the
 * main file turns up indexed under a DIFFERENT title, the two are not a duplicate:
 * one of them names the wrong holder, so the pair is flagged as a suspected forgery.
 *
 * Titles are compared loosely -- honorifics, initials and punctuation are dropped,
 * and a title whose remaining words all appear in the other counts as the same
 * holder -- so "ALHAJI ILLU MAI UNGUWA" vs "ALH ILLU MAI UNGUWA" is not flagged.
 */
class TemporaryFileConditionService
{
    public const SUSPECTED_FORGERY = 'suspected_forgery';

    private const HONORIFICS = [
        'ALHAJI', 'ALHAJ', 'ALHAJJI', 'ALH', 'AL', 'HAJIYA', 'HAJIA', 'HAJIYAH', 'HAJJIYA', 'HAJ',
        'MALLAM', 'MALAM', 'MAL', 'MALLAMA', 'MR', 'MRS', 'MISS', 'MS', 'DR', 'ENGR', 'ARC', 'PROF',
        'CHIEF', 'BARR', 'SIR', 'LATE', 'THE', 'HON', 'ALHAJIYA', 'SHEIKH', 'MADAM',
        // ranks, joiners and company suffixes
        'MAJ', 'MAJOR', 'GEN', 'GENERAL', 'COL', 'LT', 'CAPT', 'BRIG', 'CDR', 'WG', 'AIR', 'RTD',
        'AND', 'SONS', 'LTD', 'LIMITED', 'NIG', 'NIGERIA', 'CO', 'ENT', 'ENTERPRISES',
    ];

    /** Spellings of one name, reduced to a single form before comparing. */
    private const NAME_FORMS = [
        'MOHD' => 'MUHAMMAD', 'MUHD' => 'MUHAMMAD', 'MOHAMMED' => 'MUHAMMAD', 'MUHAMMED' => 'MUHAMMAD',
        'MOHAMMAD' => 'MUHAMMAD', 'MUHAMMADU' => 'MUHAMMAD', 'MOHAMED' => 'MUHAMMAD', 'MUHAMED' => 'MUHAMMAD',
    ];

    /** Main-file number for a temp number: "X(T)" / "X (T)" -> "X"; null if not a temp form. */
    public static function mainNumber(?string $number): ?string
    {
        if (!TemporaryFileNumberResolver::isTemp($number)) {
            return null;
        }

        $main = trim((string) preg_replace('/\s*\(T\)\s*$/i', '', (string) $number));

        return $main === '' ? null : $main;
    }

    /** Both titles present and naming different holders. */
    public static function titlesDiffer(?string $a, ?string $b): bool
    {
        $ta = self::titleTokens($a);
        $tb = self::titleTokens($b);

        if (!$ta || !$tb) {
            return false;
        }

        [$small, $large] = count($ta) <= count($tb) ? [$ta, $tb] : [$tb, $ta];

        foreach ($small as $word) {
            $matched = false;
            foreach ($large as $other) {
                if (self::sameWord($word, $other)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return true;
            }
        }

        return false;
    }

    /**
     * Equal, or a spelling slip: one letter in a word of 5+ letters ("ABUBAKAR" /
     * "HABUBAKAR"), two in 8+ ("ALASAN" is 6, but "ALHASSAN" is 8).
     */
    private static function sameWord(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $len = max(strlen($a), strlen($b));
        $distance = levenshtein($a, $b);

        return ($len >= 5 && $distance <= 1) || ($len >= 8 && $distance <= 2);
    }

    /** @return string[] */
    private static function titleTokens(?string $title): array
    {
        // Apostrophes join rather than split: "NA'ABBA" and "NAABBA" are one name.
        $joined = str_replace(["'", '`', '’'], '', (string) $title);
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', ' ', $joined));
        $words = preg_split('/\s+/', trim($clean)) ?: [];

        $words = array_map(fn ($w) => self::NAME_FORMS[$w] ?? $w, $words);

        return array_values(array_unique(array_filter(
            $words,
            fn ($w) => strlen($w) > 1 && !in_array($w, self::HONORIFICS, true)
        )));
    }

    /**
     * The indexed main file for each temp number, and whether the pair is a suspected
     * forgery. Non-temp numbers and temps whose main file is not indexed are left out.
     *
     * @param  array<int|string, array{file_number:?string, file_title:?string}>  $rows  keyed by caller id
     * @return array<int|string, array{main_file_number:string, main_file_title:?string, condition:?string}>
     */
    public function forRows(array $rows): array
    {
        $mains = [];
        foreach ($rows as $key => $row) {
            $main = self::mainNumber($row['file_number'] ?? null);
            if ($main !== null) {
                $mains[$key] = $main;
            }
        }

        if (!$mains) {
            return [];
        }

        $indexed = [];
        foreach (array_chunk(array_values(array_unique($mains)), 200) as $chunk) {
            $found = DB::connection('sqlsrv')->table('file_indexings')
                ->whereNull('deleted_at')
                ->whereIn('file_number', $chunk)
                ->orderBy('id')
                ->get(['file_number', 'file_title']);

            foreach ($found as $f) {
                $k = TemporaryFileNumberResolver::baseKey($f->file_number);
                $indexed[$k] ??= ['file_number' => trim((string) $f->file_number), 'file_title' => $f->file_title];
            }
        }

        $out = [];
        foreach ($mains as $key => $main) {
            $hit = $indexed[TemporaryFileNumberResolver::baseKey($main)] ?? null;
            if (!$hit) {
                continue;
            }
            $out[$key] = [
                'main_file_number' => $hit['file_number'],
                'main_file_title' => $hit['file_title'],
                'condition' => self::titlesDiffer($rows[$key]['file_title'] ?? null, $hit['file_title'])
                    ? self::SUSPECTED_FORGERY
                    : null,
            ];
        }

        return $out;
    }

    /**
     * Details of the indexed main file, for backfilling the Add Record card.
     *
     * @return array{file_number:string, file_title:?string, plot_number:?string, location:?string}|null
     */
    public function mainFile(string $number): ?array
    {
        $main = self::mainNumber($number) ?? trim($number);
        if ($main === '') {
            return null;
        }

        $row = DB::connection('sqlsrv')->table('file_indexings')
            ->whereNull('deleted_at')
            ->where('file_number', $main)
            ->orderBy('id')
            ->first(['file_number', 'file_title', 'plot_number', 'location']);

        if (!$row) {
            return null;
        }

        return [
            'file_number' => trim((string) $row->file_number),
            'file_title' => $row->file_title,
            'plot_number' => $row->plot_number,
            'location' => $row->location,
        ];
    }
}
