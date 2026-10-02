<?php

namespace App\Services\Prs;

use App\Services\GenderNormalizer;
use App\Services\Prs\Support\InstrumentTypeNormalizer;
use App\Services\Prs\Support\LandUseNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Sectional Titling, both sides of it. Added 2026-08-05.
 *   
 * ST produces two different measures, and they must never be added together —
 * the same trap the report already navigates between OP files commissioned (983). *
 *   commissioned()  st_file_numbers      — ST file numbers issued. A block is
 *                                          commissioned as one PRIMARY file, then
 *                                          fragmented into PUA and SUA unit files.
 *   registered()    deed_registrations   — ST instruments entered in the deeds
 *                                          register: Fragmentation, and unit
 *                                          transfers.
 *
 * WHY THIS CLASS EXISTS RATHER THAN TWO MORE DeedRegistrationStats GROUPS.
 * ST file numbers (ST-RES-2026-2-001) carry no land-use prefix the file-number
 * fallback can read, so routing them through the generic deed query would report
 * every sectional registration as Uncategorised. But st_file_numbers holds the
 * land use outright, and the applicant's name and title besides — and all 62 ST
 * rows in deed_registrations join to it cleanly on fileno or np_fileno. Joining is
 * what makes ST the best-dimensioned section on the page rather than the worst:
 *
 *   land use   100% (MIXED 47, RESIDENTIAL 39, COMMERCIAL 17), no fallback needed
 *   gender     ~72% of individuals, via GenderNormalizer::classify on the title,
 *              against 0.0% for the OP section and 30% for assignment
 *
 * Volumes are small — 103 commissioned files, 62 registrations — so both methods
 * pull rows and aggregate in PHP. That buys the honorific-based gender inference,
 * which has no SQL equivalent, at no cost worth measuring.
 */
class StStats
{
    /** The three ST file types, in the order the process runs. */
    public const FILE_TYPES = [
        'PRIMARY' => 'Primary (block)',
        'PUA'     => 'Parented unit',
        'SUA'     => 'Standalone unit',
    ];

    public function __construct(
        private LandUseNormalizer $landUse,
        private GenderNormalizer $genders,
        private InstrumentTypeNormalizer $instruments,
    ) {
    }

    private function conn()
    {
        return DB::connection('sqlsrv');
    }

    /** Per-request memo. sections() and highlights() ask for the same cuts. */
    private array $memo = [];

    // ── Commissioning ───────────────────────────────────────────────────────

    /**
     * ST file numbers commissioned in the year, cut by land use, gender and file
     * type.
     *
     * Dated on `date_commissioned` — the registry's own act, and the direct
     * equivalent of the deeds_date basis chosen for the deed sections on
     * 2026-08-05. Rows with no commissioning date are reported as undated rather
     * than quietly folded into their created_at month.
     */
    public function commissioned(int $year): array
    {
        return $this->memo["commissioned|$year"] ??= $this->computeCommissioned($year);
    }

    private function computeCommissioned(int $year): array
    {
        $rows = $this->conn()->select("
            SELECT land_use, file_no_type, applicant_type, applicant_title,
                   first_name, middle_name, surname, corporate_name,
                   multiple_owners_names, date_commissioned
            FROM st_file_numbers
            WHERE YEAR(date_commissioned) = ? OR date_commissioned IS NULL
        ", [$year]);

        $acc = $this->newAccumulator();

        foreach ($rows as $r) {
            $month = $r->date_commissioned ? (int) date('n', strtotime($r->date_commissioned)) : 0;

            $this->tally($acc, $month, $r->land_use, $this->genderOf($r), $r->file_no_type);
        }

        return $this->finish($acc);
    }

    // ── Registration ────────────────────────────────────────────────────────

    /**
     * ST instruments registered in the year, for one instrument group.
     *
     * Land use and gender come from st_file_numbers rather than from the deed row,
     * which carries neither. The join tries fileno first and then np_fileno: a
     * fragmentation is registered against the parent land file (CON-COM-2004-45)
     * while the units carry the ST number (ST-MIXED-2025-1-001), and both forms
     * appear in deed_registrations.fileno.
     */
    public function registered(string $group, int $year): array
    {
        return $this->memo["registered|$group|$year"] ??= $this->computeRegistered($group, $year);
    }

    private function computeRegistered(string $group, int $year): array
    {
        $where = $this->instruments->sqlPredicate($group, 'd.instrument_type');

        $rows = $this->conn()->select("
            SELECT d.deeds_date,
                   d.grantee,
                   s.land_use,
                   s.file_no_type,
                   s.applicant_type,
                   s.applicant_title,
                   s.first_name,
                   s.middle_name,
                   s.surname,
                   s.corporate_name,
                   s.multiple_owners_names
            FROM deed_registrations d
            LEFT JOIN st_file_numbers s
                   ON s.fileno = LTRIM(RTRIM(d.fileno))
                   OR s.np_fileno = LTRIM(RTRIM(d.fileno))
            WHERE ISNULL(d.is_deleted, 0) = 0
              AND $where
              AND (YEAR(d.deeds_date) = ? OR d.deeds_date IS NULL)
        ", [$year]);

        $acc = $this->newAccumulator();

        foreach ($rows as $r) {
            $month = $r->deeds_date ? (int) date('n', strtotime($r->deeds_date)) : 0;

            // The grantee is the party acquiring the unit, so on a transfer it is a
            // better subject than the file's original applicant. Falls back to the
            // ST applicant when the deed row names nobody usable.
            $gender = $this->classify($r->grantee) ?? $this->genderOf($r);

            $this->tally($acc, $month, $r->land_use, $gender, $r->file_no_type);
        }

        return $this->finish($acc);
    }

    /** Years carrying ST activity, from both sides, so the selector stays honest. */
    public function availableYears(): array
    {
        $years = [];

        $sets = [
            "SELECT YEAR(date_commissioned) y, COUNT(*) n FROM st_file_numbers
             WHERE date_commissioned IS NOT NULL GROUP BY YEAR(date_commissioned)",
            "SELECT YEAR(d.deeds_date) y, COUNT(*) n FROM deed_registrations d
             WHERE ISNULL(d.is_deleted,0)=0 AND d.deeds_date IS NOT NULL
               AND (" . $this->instruments->sqlPredicate(InstrumentTypeNormalizer::ST_FRAGMENTATION, 'd.instrument_type') . "
                    OR " . $this->instruments->sqlPredicate(InstrumentTypeNormalizer::ST_TRANSFER, 'd.instrument_type') . ")
             GROUP BY YEAR(d.deeds_date)",
        ];

        foreach ($sets as $sql) {
            foreach ($this->conn()->select($sql) as $r) {
                if ($r->y >= 1970 && $r->y <= (int) date('Y') + 1) {
                    $years[(int) $r->y] = ($years[(int) $r->y] ?? 0) + (int) $r->n;
                }
            }
        }

        return $years;
    }

    // ── Shared aggregation ──────────────────────────────────────────────────

    private function newAccumulator(): array
    {
        return [
            'months'  => array_fill(0, 12, 0),
            'landuse' => [],
            'gender'  => [],
            'types'   => [],
            'total'   => 0,
            'undated' => 0,
            'nogender' => 0,
        ];
    }

    /** One row into every cut at once, so all three foot to the same total. */
    private function tally(array &$acc, int $month, ?string $landUse, ?string $gender, ?string $fileType): void
    {
        if ($month < 1 || $month > 12) {
            $acc['undated']++;

            return;
        }

        $i = $month - 1;
        $acc['total']++;
        $acc['months'][$i]++;

        $lu = $this->landUse->normalize($landUse);
        $acc['landuse'][$lu] ??= array_fill(0, 12, 0);
        $acc['landuse'][$lu][$i]++;

        $g = $gender ?: 'Not Recorded';
        $acc['gender'][$g] ??= array_fill(0, 12, 0);
        $acc['gender'][$g][$i]++;

        if ($g === 'Not Recorded') {
            $acc['nogender']++;
        }

        $type = self::FILE_TYPES[strtoupper((string) $fileType)] ?? 'Not linked';
        $acc['types'][$type] ??= array_fill(0, 12, 0);
        $acc['types'][$type][$i]++;
    }

    private function finish(array $acc): array
    {
        // Fixed display order, so the chart keeps a stable series order and colour
        // however the rows happened to arrive.
        $types = [];
        foreach (array_merge(array_values(self::FILE_TYPES), ['Not linked']) as $label) {
            if (isset($acc['types'][$label])) {
                $types[$label] = $acc['types'][$label];
            }
        }

        return [
            'months'   => $acc['months'],
            'landuse'  => $acc['landuse'],
            'gender'   => $acc['gender'],
            'types'    => $types,
            'total'    => $acc['total'],
            'coverage' => [
                'gender_resolved' => $acc['total'] - $acc['nogender'],
                'gender_missing'  => $acc['nogender'],
                'undated'         => $acc['undated'],
            ],
        ];
    }

    /**
     * Gender of the party on an ST file.
     *
     * applicant_type is authoritative when it says Corporate — 8 of the 103 files
     * are companies, and a company has no gender to infer. Otherwise the honorific
     * decides, through the same GenderNormalizer the gender:backfill used, so a
     * sectional applicant is classified by exactly the rules applied everywhere
     * else. Alhaji 65, Hajia 2, Mr 1; Dr. is deliberately undecidable.
     */
    private function genderOf(object $r): ?string
    {
        if (strcasecmp((string) ($r->applicant_type ?? ''), 'Corporate') === 0
            || trim((string) ($r->corporate_name ?? '')) !== '') {
            return GenderNormalizer::CORPORATE;
        }

        if (trim((string) ($r->multiple_owners_names ?? '')) !== '') {
            return GenderNormalizer::JOINT;
        }

        $name = trim(implode(' ', array_filter([
            $r->applicant_title ?? null,
            $r->first_name ?? null,
            $r->middle_name ?? null,
            $r->surname ?? null,
        ])));

        return $this->classify($name);
    }

    private function classify(?string $name): ?string
    {
        if (trim((string) $name) === '') {
            return null;
        }

        return $this->genders->classify($name)[0] ?? null;
    }
}
