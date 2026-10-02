<?php

namespace App\Console\Commands;

use App\Support\LgaNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fold free-text `file_indexings.lga` onto the canonical `lgas` table.
 *
 * WHY
 * The column was never constrained to the reference table: 196 distinct values
 * against 45 canonical rows, with 5,409 files (4%) under a spelling no dropdown
 * will ever produce — "NASSARAWA" alone accounts for 3,388.
 *
 * This is a data-quality clean-up, NOT a prerequisite for anything. The SPAS
 * offline cache already resolves aliases at query time via
 * LgaNormalizer::variantsFor(), so nothing is broken while this stays unrun.
 *
 * WHY DRY-RUN IS THE DEFAULT
 * Unlike the other backfills in this directory, which fill empty columns, this
 * one OVERWRITES values that are already there, on a table read by legal search,
 * file tracking and reporting. It therefore writes nothing without --apply, and
 * every write is journalled so it can be reversed exactly.
 *
 * UPDATES ARE BY VALUE, NOT BY ROW
 * Every row sharing a raw spelling gets the same answer, so this issues roughly
 * fifty UPDATE ... WHERE lga = ? statements rather than 133,000 row writes.
 *
 * Anything LgaNormalizer cannot place with confidence is LEFT ALONE and
 * reported. That is deliberate: the column also holds other states' LGAs
 * (Hadejia, Dutse — Jigawa), ward names that are not LGAs (Waje, Sharada), and
 * junk ("29-12-1984"). Filing a record under the wrong LGA is worse than
 * leaving it unresolved for a human.
 *
 * @see docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md §11.2
 */
class NormalizeFileIndexingLga extends Command
{
    protected $signature = 'lga:normalize
                            {--connection=sqlsrv : Database connection to run against}
                            {--table=file_indexings : Table carrying the free-text lga column}
                            {--apply : Actually write. Without this the command only reports.}
                            {--revert= : Path to a journal file from a previous --apply run; restores those rows}';

    protected $description = 'Normalise free-text lga values onto the canonical lgas table. Reports by default; writes only with --apply, journalling every change so it can be reverted.';

    public function handle(): int
    {
        $conn  = DB::connection($this->option('connection'));
        $table = $this->option('table');

        if ($this->option('revert')) {
            return $this->revert($conn, $table, $this->option('revert'));
        }

        $canonical = $conn->table('lgas')->where('is_active', 1)->orderBy('name')->pluck('name')->all();

        if (empty($canonical)) {
            $this->error('The lgas reference table returned no active rows — refusing to normalise against an empty canon.');

            return self::FAILURE;
        }

        $this->info('Canonical LGAs: '.count($canonical));

        $distinct = $conn->table($table)
            ->whereNotNull('lga')->where('lga', '<>', '')
            ->select('lga', DB::raw('COUNT(*) as n'))
            ->groupBy('lga')->orderByDesc(DB::raw('COUNT(*)'))->get();

        $changes = [];
        $alreadyCanonical = 0;
        $unresolved = [];

        foreach ($distinct as $row) {
            $resolved = LgaNormalizer::normalize($row->lga, $canonical);

            if ($resolved === null) {
                $unresolved[] = [$row->lga, $row->n];
                continue;
            }

            if ($resolved === $row->lga) {
                $alreadyCanonical += $row->n;
                continue;
            }

            $changes[] = ['from' => $row->lga, 'to' => $resolved, 'rows' => $row->n];
        }

        $this->newLine();
        $this->line('<options=bold>Already canonical</>  '.number_format($alreadyCanonical).' rows');
        $this->line('<options=bold>Would change</>       '.number_format(array_sum(array_column($changes, 'rows')))
            .' rows across '.count($changes).' spellings');
        $this->line('<options=bold>Unresolved</>         '.number_format(array_sum(array_column($unresolved, 1)))
            .' rows across '.count($unresolved).' values');

        if ($changes) {
            $this->newLine();
            $this->table(['from', 'to', 'rows'], array_map(
                fn ($c) => [$c['from'], $c['to'], number_format($c['rows'])],
                $changes
            ));
        }

        if ($unresolved) {
            $this->newLine();
            $this->warn('Left alone — these need a human decision, not a guess:');
            $this->table(['value', 'rows'], array_map(
                fn ($u) => [$u[0], number_format($u[1])],
                array_slice($unresolved, 0, 25)
            ));

            if (count($unresolved) > 25) {
                $this->line('  ... and '.(count($unresolved) - 25).' more.');
            }
        }

        if (! $changes) {
            $this->newLine();
            $this->info('Nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->comment('Dry run — nothing written. Re-run with --apply to commit these changes.');

            return self::SUCCESS;
        }

        return $this->apply($conn, $table, $changes);
    }

    /**
     * Write the changes, journalling affected row ids first.
     *
     * The journal is what makes this reversible. Updating by value is not
     * self-inverting: once "NASSARAWA" rows read "Nasarawa" they are
     * indistinguishable from rows that always did, so without a record of which
     * ids moved there is no way back.
     */
    private function apply($conn, string $table, array $changes): int
    {
        $journal = [
            'ran_at'     => now()->toIso8601String(),
            'connection' => $this->option('connection'),
            'table'      => $table,
            'entries'    => [],
        ];

        $written = 0;

        $conn->beginTransaction();

        try {
            foreach ($changes as $change) {
                // Capture ids BEFORE the update, or they cannot be found again.
                $ids = $conn->table($table)->where('lga', $change['from'])->pluck('id')->all();

                if (empty($ids)) {
                    continue;
                }

                $journal['entries'][] = [
                    'from' => $change['from'],
                    'to'   => $change['to'],
                    'ids'  => $ids,
                ];

                // Chunk the WHERE IN — SQL Server caps parameters at 2,100.
                foreach (array_chunk($ids, 1000) as $chunk) {
                    $written += $conn->table($table)->whereIn('id', $chunk)->update(['lga' => $change['to']]);
                }

                $this->line(sprintf('  %-28s -> %-18s %s rows', $change['from'], $change['to'], number_format(count($ids))));
            }

            $path = storage_path('app/lga-normalize-'.now()->format('Ymd-His').'.json');
            file_put_contents($path, json_encode($journal, JSON_PRETTY_PRINT));

            $conn->commit();

            $this->newLine();
            $this->info('Updated '.number_format($written).' rows.');
            $this->line('Journal: '.$path);
            $this->comment('Reverse with:  php artisan lga:normalize --revert="'.$path.'"');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $conn->rollBack();
            $this->error('Rolled back — nothing written. '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /** Restore rows to the values recorded in a journal from a previous run. */
    private function revert($conn, string $table, string $path): int
    {
        if (! is_file($path)) {
            $this->error('Journal not found: '.$path);

            return self::FAILURE;
        }

        $journal = json_decode(file_get_contents($path), true);

        if (! is_array($journal) || empty($journal['entries'])) {
            $this->error('Journal is unreadable or has no entries.');

            return self::FAILURE;
        }

        if (($journal['table'] ?? null) !== $table) {
            $this->error('Journal is for table "'.($journal['table'] ?? '?').'", not "'.$table.'".');

            return self::FAILURE;
        }

        $restored = 0;
        $conn->beginTransaction();

        try {
            foreach ($journal['entries'] as $entry) {
                foreach (array_chunk($entry['ids'], 1000) as $chunk) {
                    $restored += $conn->table($table)->whereIn('id', $chunk)->update(['lga' => $entry['from']]);
                }

                $this->line(sprintf('  %-18s -> %-28s %s rows', $entry['to'], $entry['from'], number_format(count($entry['ids']))));
            }

            $conn->commit();
            $this->info('Restored '.number_format($restored).' rows from '.$journal['ran_at'].'.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $conn->rollBack();
            $this->error('Rolled back — nothing restored. '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
