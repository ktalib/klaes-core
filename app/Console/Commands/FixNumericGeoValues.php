<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs district / lga / lgsaOrCity columns that hold a bare reference id
 * ("16") instead of the name ("GAYA"), and strips the same id out of the
 * `location` string that was built from them.
 *
 * Cause: populateSelectFromText() in public/js/fileindexing/create-indexing-dialog.js
 * preferred the reference-row id when autofilling, and appended a synthetic
 * <option value="16">GAYA</option> to selects whose options are keyed by NAME.
 * The dropdown read correctly while the id was what got submitted. Fixed at
 * source; this command cleans up what was already written.
 *
 * Dry run by default — nothing is written without --apply.
 */
class FixNumericGeoValues extends Command
{
    protected $signature = 'geo:fix-numeric-ids
                            {--apply : Write the changes (default is a dry run)}
                            {--table= : Restrict to a single table}
                            {--connection=sqlsrv : Database connection holding the tables}
                            {--samples=8 : How many example rows to print per column}';

    protected $description = 'Replace numeric district/lga ids with their names and repair the location strings built from them';

    /**
     * [table, column, kind] — kind selects the reference map.
     * Tables are skipped automatically when absent or lacking an `id` key.
     *
     * ORDER MATTERS: within a table the lga column is listed BEFORE district.
     * location reads "PLOT, STREET, DISTRICT, LGA, KANO STATE" and each pass
     * replaces the rightmost token equal to its id, so where a row's district
     * id and lga id are the same number the lga pass must consume the trailing
     * token first — otherwise district would claim it and the two would swap.
     */
    private const TARGETS = [
        ['file_indexings',                'lga',        'lga'],
        ['file_indexings',                'district',   'district'],
        ['fileNumber',                    'lga',        'lga'],
        ['pra',                           'lgsaOrCity', 'lga'],
        ['CofO_staging',                  'lgsaOrCity', 'lga'],
        ['file_history_staging',          'lgsaOrCity', 'lga'],
        ['pic',                           'lgsaOrCity', 'lga'],
        ['title_status_applications',     'lga',        'lga'],
        ['title_status_applications',     'district',   'district'],
        ['plot_subdivision_applications', 'lga',        'lga'],
        ['plot_subdivision_applications', 'district',   'district'],
        ['plot_merger_applications',      'lga',        'lga'],
        ['plot_merger_applications',      'district',   'district'],
        ['plot_extension_applications',   'lga',        'lga'],
        ['plot_extension_applications',   'district',   'district'],
        ['plot_separation_applications',  'lga',        'lga'],
        ['deed_registrations',            'district',   'district'],
        ['deeds_bill_balances_metadata',  'district',   'district'],
        ['deprecated_records',            'lga',        'lga'],
        ['deprecated_records',            'district',   'district'],
        ['edms',                          'lga',        'lga'],
    ];

    /** SQL Server LIKE pattern meaning "contains a character that is not a digit". */
    private const HAS_NON_DIGIT = '%[^0-9]%';

    public function handle(): int
    {
        $connection = $this->option('connection');
        $db = DB::connection($connection);
        $apply = (bool) $this->option('apply');
        $only = $this->option('table');
        $sampleLimit = (int) $this->option('samples');

        $maps = [
            'district' => $db->table('districts')->pluck('name', 'id'),
            'lga'      => $db->table('lgas')->pluck('name', 'id'),
        ];

        $this->info(sprintf(
            'Reference data: %d districts, %d LGAs (connection: %s)',
            $maps['district']->count(),
            $maps['lga']->count(),
            $connection
        ));
        $this->line($apply
            ? '<fg=yellow>APPLY mode — changes will be written.</>'
            : '<fg=cyan>DRY RUN — no changes will be written. Re-run with --apply to commit.</>');
        $this->newLine();

        $totals = ['fixed' => 0, 'location' => 0, 'unresolved' => 0, 'skipped' => 0];
        $unresolvedValues = [];

        foreach (self::TARGETS as [$table, $column, $kind]) {
            if ($only && $only !== $table) {
                continue;
            }

            if (!$this->tableIsUsable($db, $table, $column)) {
                $totals['skipped']++;
                continue;
            }

            $hasLocation = $db->getSchemaBuilder()->hasColumn($table, 'location');

            $rows = $db->table($table)
                ->whereRaw("[$column] NOT LIKE ? AND LTRIM(RTRIM([$column])) <> ''", [self::HAS_NON_DIGIT])
                ->get(array_filter(['id', $column, $hasLocation ? 'location' : null]));

            if ($rows->isEmpty()) {
                continue;
            }

            $fixed = 0;
            $locationFixed = 0;
            $unresolved = 0;
            $samples = [];

            foreach ($rows as $row) {
                $oldValue = trim((string) $row->{$column});
                $name = $maps[$kind]->get((int) $oldValue);

                // Not a real reference id — junk typed into the field (plot numbers,
                // "00", ids far beyond the table). Leave it for a human to look at
                // rather than binding it to whatever row sits at that id.
                if ($name === null || $name === '') {
                    $unresolved++;
                    $unresolvedValues[$kind][$oldValue] = ($unresolvedValues[$kind][$oldValue] ?? 0) + 1;
                    continue;
                }

                $update = [$column => $name];

                if ($hasLocation) {
                    $newLocation = $this->replaceIdTokenInLocation((string) $row->location, $oldValue, $name);
                    if ($newLocation !== null && $newLocation !== (string) $row->location) {
                        $update['location'] = $newLocation;
                        $locationFixed++;
                    }
                }

                if (count($samples) < $sampleLimit) {
                    $samples[] = [
                        $row->id,
                        $oldValue . '  ->  ' . $name,
                        $hasLocation ? $this->truncate((string) $row->location) : '—',
                        $hasLocation ? $this->truncate((string) ($update['location'] ?? $row->location)) : '—',
                    ];
                }

                if ($apply) {
                    $db->table($table)->where('id', $row->id)->update($update);
                }

                $fixed++;
            }

            $this->line("<info>{$table}.{$column}</info>  ({$kind})");
            $this->line(sprintf(
                '  %d to fix, %d location strings rewritten, %d unresolved (left untouched)',
                $fixed, $locationFixed, $unresolved
            ));

            if ($samples) {
                $this->table(['id', $column, 'location (before)', 'location (after)'], $samples);
            }
            $this->newLine();

            $totals['fixed'] += $fixed;
            $totals['location'] += $locationFixed;
            $totals['unresolved'] += $unresolved;
        }

        $this->info('──────── summary ────────');
        $this->line("  values {$this->verb($apply, 'fixed', 'to fix')}          : {$totals['fixed']}");
        $this->line("  location strings rewritten : {$totals['location']}");
        $this->line("  unresolved (left as-is)    : {$totals['unresolved']}");
        $this->line("  tables skipped             : {$totals['skipped']}");

        foreach ($unresolvedValues as $kind => $values) {
            arsort($values);
            $preview = [];
            foreach (array_slice($values, 0, 20, true) as $value => $count) {
                $preview[] = "{$value} (x{$count})";
            }
            $this->newLine();
            $this->warn("Unresolved {$kind} values — these are NOT reference ids, review by hand:");
            $this->line('  ' . implode(', ', $preview));
        }

        if (!$apply) {
            $this->newLine();
            $this->line('<fg=cyan>Nothing was written. Re-run with --apply to commit.</>');
        }

        return self::SUCCESS;
    }

    /**
     * Swap the id for its name inside the comma-built location string.
     *
     * Only a token that is EXACTLY the id is touched, and the rightmost one is
     * chosen: location reads "PLOT, STREET, DISTRICT, LGA, KANO STATE", so the
     * district/lga ids are always the trailing numeric tokens. A plot number
     * that happens to equal the id sits to the left and is left alone.
     */
    private function replaceIdTokenInLocation(string $location, string $id, string $name): ?string
    {
        if ($location === '') {
            return null;
        }

        $tokens = array_map('trim', explode(',', $location));

        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            if ($tokens[$i] === $id) {
                $tokens[$i] = $name;
                return implode(', ', $tokens);
            }
        }

        return null;
    }

    /** Skip tables that are missing, or that have no `id` to update by. */
    private function tableIsUsable($db, string $table, string $column): bool
    {
        $schema = $db->getSchemaBuilder();

        if (!$schema->hasTable($table)) {
            $this->warn("skip {$table}.{$column} — table not found");
            return false;
        }

        if (!$schema->hasColumn($table, $column)) {
            $this->warn("skip {$table}.{$column} — column not found");
            return false;
        }

        if (!$schema->hasColumn($table, 'id')) {
            $this->warn("skip {$table}.{$column} — no `id` column to update rows by");
            return false;
        }

        return true;
    }

    private function truncate(string $value, int $length = 42): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1) . '…' : $value;
    }

    private function verb(bool $apply, string $done, string $pending): string
    {
        return $apply ? $done : $pending;
    }
}
