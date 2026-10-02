<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dropdown values for the Advanced Search panel on /indexed-files.
 *
 * Two of the four columns cannot be offered as their raw DISTINCT values:
 *
 *   lga        - free text captured over years by many hands. 196 distinct values, of
 *                which only 49 are an actual LGA name; the rest are misspellings
 *                ("NASSARAWA", "KUMBTSO"), abbreviations ("D/KUDU"), districts and
 *                streets ("SHARADA", "WUDIL ROAD"), and a few stray dates.
 *   created_by - holds a display name on most rows but a numeric users.id on ~2,500 of
 *                them, plus markers written by backfill scripts rather than a person.
 *
 * So each of those two is offered as a CANONICAL value with the raw spellings that
 * resolve to it kept alongside; the filter then matches the whole variant set, and the
 * count shown is the sum across it. Selecting "Kumbotso" finds the rows spelled
 * KUMBTSO and KUNBOTSO too, and selecting a person finds the rows that recorded them
 * by id as well as by name.
 */
class IndexedFilesFilterOptions
{
    /** Cache lifetime for the whole option set, in seconds. */
    private const TTL = 600;

    /** The LGA option that gathers every value which is not a listed LGA. */
    public const LGA_OTHER = '__other__';

    /**
     * Misspellings and abbreviations seen in file_indexings.lga, keyed by their
     * normalised form (see normalize()) and hand-checked one by one against the row
     * counts. Only unambiguous ones are listed.
     *
     * Deliberately absent, because a guess here silently files a property under the
     * wrong local government:
     *   GARKI       - a Jigawa State LGA, one letter from Kano's Garko (the same trap
     *                 KanoLgaDirectory documents), so it is left unmapped.
     *   KURU        - one edit from both Kura and Kiru.
     *   KANO CITY / K/CITY / CITY / WAJE - the old walled city and its quarters, which
     *                 span Dala, Gwale and Kano Municipal rather than naming one.
     *   HADEJIA, JAHUN, RINGIM, GUMEL, DUTSE, ... - real LGAs of other states.
     *   SHARADA, GIGINYU, DAKATA, HOTORO GRA, ... - districts and streets, not LGAs.
     * Those all fall into the LGA_OTHER bucket, so their rows stay reachable.
     */
    private const LGA_ALIASES = [
        // Nasarawa
        'NASSARAWA' => 'Nasarawa',
        'NASSARWA' => 'Nasarawa',
        'NASSSARAWA' => 'Nasarawa',
        'NSARAWA' => 'Nasarawa',
        'DNASSARAWA' => 'Nasarawa',
        'NADSARAWA' => 'Nasarawa',
        // Kano Municipal - the LGA name shortened, and its misspellings
        'MUNICIPAL' => 'Kano Municipal',
        'MUNINCIPAL' => 'Kano Municipal',
        'MUNISIPAL' => 'Kano Municipal',
        'MUNIPAL' => 'Kano Municipal',
        'MINICIPAL' => 'Kano Municipal',
        'MUNINSIPAL' => 'Kano Municipal',
        'MUNNINCIPAL' => 'Kano Municipal',
        'KANO MUNINCIPAL' => 'Kano Municipal',
        'KANO MUNICPAL' => 'Kano Municipal',
        'KANO MUNICIPLA' => 'Kano Municipal',
        'KANO MUNICIAL' => 'Kano Municipal',
        'KANO MUNUCIPAL' => 'Kano Municipal',
        'KAN0 MUNICIPAL' => 'Kano Municipal',
        'KKANO MUNICIPAL' => 'Kano Municipal',
        'KANO MINICIPAL' => 'Kano Municipal',
        'KANO MINUCIPAL' => 'Kano Municipal',
        // Dawakin Kudu
        'D KUDU' => 'Dawakin Kudu',
        'DAWAKIN KUDA' => 'Dawakin Kudu',
        'DAWAJIN KUDU' => 'Dawakin Kudu',
        'DAWAKI KUDU' => 'Dawakin Kudu',
        'DAWAKKUDU' => 'Dawakin Kudu',
        'DAWKIN KUDU' => 'Dawakin Kudu',
        'DAWAKN KUDU' => 'Dawakin Kudu',
        'DWAKIN KUDU' => 'Dawakin Kudu',
        'KAWAKIN KUDU' => 'Dawakin Kudu',
        // Dawakin Tofa
        'D TOFA' => 'Dawakin Tofa',
        'DAWAKI TOFA' => 'Dawakin Tofa',
        'DAKAWAKIN TOFA' => 'Dawakin Tofa',
        'DAKWAKIN TOFA' => 'Dawakin Tofa',
        // Tudun Wada / Rimin Gado
        'T WADA' => 'Tudun Wada',
        'R GADO' => 'Rimin Gado',
        // Ungogo
        'UNGOGGO' => 'Ungogo',
        'UNOGGO' => 'Ungogo',
        'UNOGO' => 'Ungogo',
        // Kumbotso
        'KUMBTSO' => 'Kumbotso',
        'KUNBOTSO' => 'Kumbotso',
        'KOMBOTSO' => 'Kumbotso',
        'KUMBOTO' => 'Kumbotso',
        // Dambatta
        'DANBATTA' => 'Dambatta',
        'DANBATT' => 'Dambatta',
        'DAMBATT' => 'Dambatta',
        'DANTATTA' => 'Dambatta',
        // Minjibir
        'MINGIBIR' => 'Minjibir',
        'MINJIR' => 'Minjibir',
        'MIJINBIR' => 'Minjibir',
        // Assorted single-LGA misspellings
        'GEAZAWA' => 'Gezawa',
        'GWAALE' => 'Gwale',
        'GWLE' => 'Gwale',
        'GWAE' => 'Gwale',
        'TARAUNA' => 'Tarauni',
        'TARAUNU' => 'Tarauni',
        'TARUANI' => 'Tarauni',
        'FAGE' => 'Fagge',
        'FAFFE' => 'Fagge',
        'GARIN MALLAM' => 'Garun Mallam',
        'GARUN MALAM' => 'Garun Mallam',
        'GARIN MALAM' => 'Garun Mallam',
        'GARUM MALLAM' => 'Garun Mallam',
        'SDALA' => 'Dala',
    ];

    /**
     * The whole option set for the panel, plus the raw-value map the filter needs.
     *
     * @return array{
     *     options: array<string, list<array{value: string, total: int, label?: string}>>,
     *     variants: array<string, array<string, list<string>>>
     * }
     */
    public function build(string $registry = ''): array
    {
        $cacheKey = 'indexed_files_filter_options_v2_' . ($registry !== '' ? strtoupper($registry) : 'ALL');

        return Cache::remember($cacheKey, self::TTL, function () use ($registry) {
            $lga = $this->lgaOptions($registry);
            $createdBy = $this->createdByOptions($registry);

            return [
                'options' => [
                    'general_registry' => $this->plainColumn('general_registry', $registry),
                    'land_use_type' => $this->plainColumn('land_use_type', $registry),
                    'lga' => $lga['options'],
                    'created_by' => $createdBy['options'],
                ],
                'variants' => [
                    'lga' => $lga['variants'],
                    'created_by' => $createdBy['variants'],
                ],
            ];
        });
    }

    /**
     * The raw file_indexings values a canonical dropdown choice should match.
     * Falls back to the choice itself for a column that needs no canonicalisation.
     *
     * @return list<string>
     */
    public function rawValuesFor(string $column, string $choice, string $registry = ''): array
    {
        $built = $this->build($registry);
        $map = $built['variants'][$column] ?? null;

        if ($map === null) {
            return [$choice];
        }

        return $map[$choice] ?? [$choice];
    }

    /**
     * Upper-case, punctuation reduced to single spaces, trimmed. This alone resolves
     * casing, stray backticks ("UNGOGO`") and double spaces ("KANO  MUNICIPAL").
     */
    public static function normalize($value): string
    {
        $upper = strtoupper(trim((string) $value));
        $upper = preg_replace('/[^A-Z0-9]+/', ' ', $upper);

        return trim(preg_replace('/\s+/', ' ', (string) $upper));
    }

    /**
     * A column offered as its own distinct values, ordered by how many files carry each.
     *
     * @return list<array{value: string, total: int}>
     */
    private function plainColumn(string $column, string $registry): array
    {
        return $this->countsFor($column, $registry)
            ->map(function ($total, $value) {
                return ['value' => $value, 'total' => (int) $total];
            })
            ->values()
            ->all();
    }

    /**
     * Raw value => row count for one column, junk and blanks dropped, biggest first.
     */
    private function countsFor(string $column, string $registry)
    {
        $query = DB::connection('sqlsrv')->table('file_indexings')
            ->select($column, DB::raw('COUNT(*) as total'))
            ->whereNotNull($column)
            ->where($column, '<>', '');

        if ($registry !== '') {
            if (strtoupper($registry) === 'KANGIS') {
                $query->whereIn('registry', ['KANGIS', 'KANGIS Registry']);
            } else {
                $query->where('registry', $registry);
            }
        }

        $rows = $query->groupBy($column)
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        // Accumulated rather than assigned: SQL Server groups "Kano" and "Kano " as two
        // rows, and trim() then collapses them onto one key here - a plain assignment
        // would throw the first group's count away.
        $counts = [];
        foreach ($rows as $row) {
            $value = trim((string) $row->{$column});
            if ($value === '') {
                continue;
            }

            $counts[$value] = ($counts[$value] ?? 0) + (int) $row->total;
        }

        arsort($counts);

        return collect($counts);
    }

    /**
     * LGA options: the listed LGAs that files actually reference, plus one bucket for
     * every value that is not a listed LGA.
     *
     * @return array{options: list<array{value: string, total: int}>, variants: array<string, list<string>>}
     */
    private function lgaOptions(string $registry): array
    {
        // The lgas table is the source of truth for what counts as an LGA.
        $canonical = [];
        foreach (DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->pluck('name') as $name) {
            $canonical[self::normalize($name)] = trim((string) $name);
        }

        $totals = [];
        $variants = [];
        $unmapped = [];
        $unmappedTotal = 0;

        foreach ($this->countsFor('lga', $registry) as $raw => $total) {
            // PHP coerces a numeric-string array key to int, so a value like "2024"
            // arrives here as an int and has to be cast back before it is compared or
            // handed to whereIn against an nvarchar column.
            $raw = (string) $raw;
            $key = self::normalize($raw);
            $name = $canonical[$key] ?? (self::LGA_ALIASES[$key] ?? null);

            if ($name === null) {
                $unmapped[] = $raw;
                $unmappedTotal += $total;
                continue;
            }

            $totals[$name] = ($totals[$name] ?? 0) + $total;
            $variants[$name][] = $raw;
        }

        arsort($totals);

        $options = [];
        foreach ($totals as $name => $total) {
            $options[] = ['value' => $name, 'total' => $total];
        }

        // Offered last, and only when there is something in it. Without this the rows
        // whose lga holds a district or a date would be unreachable from the panel.
        if ($unmappedTotal > 0) {
            $options[] = [
                'value' => self::LGA_OTHER,
                'label' => 'Other / not a listed LGA',
                'total' => $unmappedTotal,
            ];
            $variants[self::LGA_OTHER] = $unmapped;
        }

        return ['options' => $options, 'variants' => $variants];
    }

    /**
     * Indexed By options: one entry per person, with the rows that recorded them by
     * users.id folded into the same entry as the rows that recorded them by name.
     *
     * Rows stamped by a backfill script are left out entirely - nobody indexed them,
     * so offering them as a person to filter by is a lie. The rows are still reachable
     * through every other filter.
     *
     * @return array{options: list<array{value: string, total: int}>, variants: array<string, list<string>>}
     */
    private function createdByOptions(string $registry): array
    {
        $counts = $this->countsFor('created_by', $registry);

        // Resolve the numeric ids in one query rather than one per value.
        $ids = $counts->keys()
            ->filter(function ($value) {
                return ctype_digit((string) $value);
            })
            ->map(function ($value) {
                return (int) $value;
            })
            ->all();

        $users = empty($ids)
            ? collect()
            : DB::connection('sqlsrv')->table('users')
                ->whereIn('id', $ids)
                ->get(['id', 'first_name', 'last_name', 'username'])
                ->mapWithKeys(function ($user) {
                    $name = trim(sprintf('%s %s', $user->first_name ?? '', $user->last_name ?? ''));
                    if ($name === '') {
                        $name = trim((string) ($user->username ?? ''));
                    }

                    return [(int) $user->id => $name];
                })
                ->filter();

        $totals = [];
        $variants = [];
        $labels = [];

        foreach ($counts as $raw => $total) {
            // See the cast in lgaOptions(): a created_by holding a users.id arrives as
            // an int key, and it has to go back into whereIn as the string it is stored as.
            $raw = (string) $raw;

            if ($this->isGeneratedCreator($raw)) {
                continue;
            }

            if (ctype_digit($raw)) {
                // An id with no user row behind it (0 is the system sentinel) names
                // nobody, so it is dropped the same way a backfill marker is.
                $name = $users[(int) $raw] ?? null;
                if ($name === null) {
                    continue;
                }
            } else {
                $name = $raw;
            }

            // Fold case variants of one name together ("mubarak dauda" / "Mubarak
            // Dauda"), keeping the spelling that the most rows use as the label.
            $key = mb_strtoupper($name);
            $totals[$key] = ($totals[$key] ?? 0) + $total;
            $variants[$key][] = $raw;
            if (!isset($labels[$key])) {
                $labels[$key] = $name;
            }
        }

        arsort($totals);

        $options = [];
        $keyed = [];
        foreach ($totals as $key => $total) {
            $label = $labels[$key];
            $options[] = ['value' => $label, 'total' => $total];
            $keyed[$label] = $variants[$key];
        }

        return ['options' => $options, 'variants' => $keyed];
    }

    /**
     * True for a created_by written by a script rather than typed by an indexer:
     * "KANGIS Related Land Backfill", "COFO Duplicate Backfill", "backfill_sql".
     */
    private function isGeneratedCreator(string $value): bool
    {
        return stripos($value, 'backfill') !== false;
    }
}
