<?php

namespace App\Console\Commands;

use App\Services\Prs\Support\LandUseNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill land_use from the file-number prefix.
 *
 * Every KLAES-generated file number encodes the land use: RES-2026-1862,
 * CON-RES-2026-1308, AG-RC-2026-12. The mapping is documented in
 * .agent/skills/klaes/SKILL.md §5 and implemented in LandUseNormalizer.
 *
 * Measured 2026-08-03, rows with an empty land_use that carry a usable prefix:
 *
 *   file_history_staging   4,888 of 4,897   (99.8%)
 *   CofO_staging             191 of   229   (83.4%)
 *   pra                      316 of 8,885   ( 3.6%)
 *   file_indexings            68 of   264   (25.8%)
 *
 * pra is low because most of its empty rows are legacy or temporary numbers —
 * TEMP-10823, KN 7593 — which carry no land use at all. Those are left null
 * rather than guessed; they surface on the report as Uncategorised, which is the
 * honest answer.
 *
 * Writes land_use_source so a derived value is never mistaken for a captured one,
 * and so --reset can undo the whole thing.
 */
class BackfillLandUse extends Command
{
    protected $signature = 'landuse:backfill
                            {--connection=sqlsrv : Database connection to run against}
                            {--dry-run : Report what would change, write nothing}
                            {--reset : Undo the backfill (clears rows whose land_use_source is file_number)}
                            {--table= : Restrict to one table}';

    protected $description = 'Fill empty land_use columns from the file-number prefix (RES-, CON-RES-, AG-RC- ...). Records provenance in land_use_source.';

    /**
     * table => [land use column, file-number expression, soft-delete guard]
     *
     * The file-number expression mirrors how each table identifies a file: deed
     * tables carry up to four numbering systems and any of them may hold the one
     * with a prefix.
     */
    private const TARGETS = [
        'pra' => [
            'column'  => 'land_use',
            'fileno'  => "COALESCE(NULLIF(mlsFNo,''), NULLIF(kangisFileNo,''), NULLIF(NewKANGISFileno,''), NULLIF(fileno,''), '')",
            'deleted' => 'is_deleted',
        ],
        'file_history_staging' => [
            'column'  => 'land_use',
            'fileno'  => "COALESCE(NULLIF(mlsFNo,''), NULLIF(kangisFileNo,''), NULLIF(NewKANGISFileno,''), NULLIF(fileno,''), '')",
            'deleted' => 'is_deleted',
        ],
        'CofO_staging' => [
            'column'  => 'land_use',
            'fileno'  => "COALESCE(NULLIF(mlsFNo,''), NULLIF(kangisFileNo,''), NULLIF(NewKANGISFileno,''), NULLIF(fileno,''), '')",
            'deleted' => 'is_deleted',
        ],
        'file_indexings' => [
            'column'  => 'land_use_type',
            'fileno'  => 'file_number',
            'deleted' => 'is_deleted',
        ],
    ];

    public function handle(LandUseNormalizer $normalizer): int
    {
        $connName = $this->option('connection');
        $conn     = DB::connection($connName);
        $dryRun   = (bool) $this->option('dry-run');
        $only     = $this->option('table');

        $targets = $only ? array_intersect_key(self::TARGETS, [$only => true]) : self::TARGETS;

        if ($targets === []) {
            $this->error("Unknown table '$only'. Known: " . implode(', ', array_keys(self::TARGETS)));

            return self::FAILURE;
        }

        foreach (array_keys($targets) as $table) {
            if (!Schema::connection($connName)->hasColumn($table, 'land_use_source')) {
                $this->error("$table.land_use_source is missing. Run the migration first:");
                $this->line('  php artisan migrate --database=sqlsrv');

                return self::FAILURE;
            }
        }

        if ($this->option('reset')) {
            return $this->reset($conn, $targets, $dryRun);
        }

        if ($dryRun) {
            $this->warn('--dry-run set: nothing will be written.');
        }

        foreach ($targets as $table => $meta) {
            $this->newLine();
            $this->info($table);

            $col     = $meta['column'];
            $fileno  = $meta['fileno'];
            $guard   = "ISNULL({$meta['deleted']}, 0) = 0";
            $isEmpty = "($col IS NULL OR LTRIM(RTRIM($col)) = '')";

            $empty = (int) $conn->selectOne("SELECT COUNT(*) n FROM $table WHERE $guard AND $isEmpty")->n;

            // One UPDATE per land-use category rather than per row: four statements
            // instead of five thousand round trips.
            $filled = 0;

            foreach ($this->prefixGroups() as $canonical => $prefixes) {
                $like = implode(' OR ', array_map(
                    fn ($p) => "UPPER($fileno) LIKE '$p-%'",
                    $prefixes
                ));

                $where = "$guard AND $isEmpty AND ($like)";

                $n = (int) $conn->selectOne("SELECT COUNT(*) n FROM $table WHERE $where")->n;

                if ($n > 0 && !$dryRun) {
                    $conn->statement(
                        "UPDATE $table SET $col = ?, land_use_source = 'file_number' WHERE $where",
                        [$canonical]
                    );
                }

                if ($n > 0) {
                    $this->line(sprintf('   %-14s %7s', $canonical, number_format($n)));
                }

                $filled += $n;
            }

            $this->line(sprintf('   %-14s %7s empty · %s recovered (%s%%) · %s left with no prefix',
                '',
                number_format($empty),
                number_format($filled),
                $empty > 0 ? number_format($filled / $empty * 100, 1) : '0',
                number_format($empty - $filled)
            ));
        }

        $this->newLine();
        $this->info('Rows with no usable prefix keep an empty land_use and render as Uncategorised.');

        return self::SUCCESS;
    }

    /**
     * Canonical category => the prefixes that map to it, longest first so
     * CON-RES-RC is matched before CON-RES and RES.
     */
    private function prefixGroups(): array
    {
        return [
            LandUseNormalizer::RESIDENTIAL => ['CON-RES-RC', 'CON-RES', 'RES-RC', 'RES'],
            LandUseNormalizer::COMMERCIAL  => ['CON-COM-RC', 'CON-COM', 'COM-RC', 'COM'],
            LandUseNormalizer::INDUSTRIAL  => ['CON-IND-RC', 'CON-IND', 'IND-RC', 'IND'],
            LandUseNormalizer::AGRICULTURE => ['CON-AG-RC', 'CON-AG', 'AG-RC', 'AG'],
        ];
    }

    private function reset($conn, array $targets, bool $dryRun): int
    {
        $this->warn('Reset: clearing land_use values that were derived from file numbers.');

        foreach ($targets as $table => $meta) {
            $n = (int) $conn->table($table)->where('land_use_source', 'file_number')->count();

            if ($n > 0 && !$dryRun) {
                $conn->table($table)
                    ->where('land_use_source', 'file_number')
                    ->update([$meta['column'] => null, 'land_use_source' => null]);
            }

            $this->line(sprintf('   %-22s %7s cleared', $table, number_format($n)));
        }

        return self::SUCCESS;
    }
}
