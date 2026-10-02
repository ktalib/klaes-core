<?php

namespace App\Console\Commands;

use App\Services\OpHolderMatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes the missing Transfer of Title on files whose Occupancy Permit names one
 * holder while File Indexing names another and nothing on the file explains it.
 *
 * The CLI counterpart of the Match button on the Recommendation capture form
 * (LandRecommendationOpMatchController). Same rule, same writer, same guards — it
 * calls OpHolderMatchService for every decision rather than reimplementing any of
 * them, so a file the form would refuse is refused here too.
 *
 * SCOPE: files that carry a non-batch RofO recommendation, and nothing else. The
 * wider estate holds thousands of files in the same state; those are left alone
 * deliberately — this repairs the ones officers are working, ahead of them reaching
 * the Match button, rather than rewriting the register wholesale.
 *
 * TWO STAGES, because the authoritative check is expensive:
 *
 *   1. A cheap SQL shortlist — files with an Occupancy Permit whose party_2 is not
 *      the file title. Seconds for the whole estate, and deliberately generous: it
 *      over-includes, and stage 2 throws the extras out.
 *   2. OpHolderMatchService::check() per shortlisted file. This reads the file's
 *      whole chain through LegalSearchService::buildPrintReport() (four registers,
 *      3-5 seconds each), which is what catches the Deeds of Assignment that a
 *      pra-only rule misses. Only `applies` files are written.
 *
 * Budget ~4 seconds per shortlisted file. Use --limit to run it in sittings.
 *
 * SAFE TO RE-RUN. Every write is guarded by the same check that selected it, so a
 * file already transferred is skipped rather than transferred twice.
 *
 * EVERY WRITE IS TAGGED with the run id, in pra.remarks, so one run can be undone:
 *     php artisan op-match:backfill --dry-run --report=storage/app/backfill-preview.csv
 *     php artisan op-match:backfill --limit=25
 *     php artisan op-match:rollback {run-id}
 */
class BackfillOpHolderTots extends Command
{
    protected $signature = 'op-match:backfill
        {--dry-run : Show what would be written, write nothing}
        {--file= : Comma-separated file numbers to limit the run to}
        {--limit= : Stop after this many shortlisted files}
        {--report= : Write a CSV of every decision to this path}
        {--user= : User id to stamp as created_by}
        {--force : Skip the confirmation prompt (for scheduled runs)}';

    protected $description = 'Write the missing Transfer of Title on files whose OP holder is not the indexed holder';

    public function __construct(private OpHolderMatchService $matcher)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $runId  = 'OPMB-' . now()->format('Ymd-His');

        if ($dryRun) {
            $this->warn('DRY RUN — nothing will be written.');
        }

        $this->info('Stage 1: shortlisting files…');
        $shortlist = $this->shortlist();

        if ($files = $this->csvOption('file')) {
            $wanted = array_flip(array_map(fn ($f) => $this->norm($f), $files));
            $shortlist = array_values(array_filter($shortlist, fn ($f) => isset($wanted[$this->norm($f)])));
        }

        if ($limit = (int) $this->option('limit')) {
            $shortlist = array_slice($shortlist, 0, $limit);
        }

        if (! $shortlist) {
            $this->info('Nothing to do — no file matched the shortlist.');
            return self::SUCCESS;
        }

        $this->line('  shortlisted: ' . count($shortlist) . ' file(s)');
        $this->line('  estimated time: ~' . ceil(count($shortlist) * 4 / 60) . ' minute(s) (the chain read is 3-5s per file)');
        $this->newLine();

        if (! $dryRun && ! $this->option('force')) {
            $this->warn('This writes Transfer of Title rows into pra on ' . config('database.connections.sqlsrv.database') . '.');
            $this->line('Run id: ' . $runId . '   (undo with: php artisan op-match:rollback ' . $runId . ')');

            if (! $this->confirm('Proceed?', false)) {
                $this->warn('Aborted.');
                return self::SUCCESS;
            }
        }

        $this->info('Stage 2: checking each file against the live rule…');

        $userId  = $this->option('user') !== null ? (int) $this->option('user') : null;
        $written = 0;
        $skipped = [];
        $tally   = [];
        $rows    = [];

        $bar = $this->output->createProgressBar(count($shortlist));
        $bar->start();

        foreach ($shortlist as $fileNumber) {
            $bar->advance();

            try {
                $state = $this->matcher->check($fileNumber);
            } catch (\Throwable $e) {
                $this->tally($tally, 'Check failed');
                $skipped[] = $fileNumber . ': ' . $e->getMessage();
                $rows[] = [$fileNumber, 'ERROR', '', '', $e->getMessage(), ''];
                continue;
            }

            if (! $state['applies']) {
                $reason = $state['name_spelling_only']
                    ? 'Same holder spelt two ways'
                    : ($state['matched'] ? 'Already matched' : ($state['has_working_transfer'] ? 'A dealing already explains it' : 'Does not qualify'));

                $this->tally($tally, $reason);
                $rows[] = [$fileNumber, 'SKIPPED', $state['op']['holder'] ?? '', $state['indexing_name'] ?? '', $state['reason'], ''];
                continue;
            }

            if ($dryRun) {
                $written++;
                $this->tally($tally, 'Would write');
                $rows[] = [$fileNumber, 'WOULD WRITE', $state['op']['holder'], $state['indexing_name'], $state['reason'], ''];
                continue;
            }

            $result = $this->matcher->generateTot($fileNumber, $userId);

            if (! $result['ok']) {
                $this->tally($tally, 'Refused at write');
                $skipped[] = $fileNumber . ': ' . $result['message'];
                $rows[] = [$fileNumber, 'REFUSED', $state['op']['holder'], $state['indexing_name'], $result['message'], ''];
                continue;
            }

            // The run id goes onto the row itself, which is what makes one run
            // undoable without touching transfers written by any other run — or by
            // an officer pressing Match on the form.
            DB::connection('sqlsrv')->table('pra')->where('id', $result['pra_id'])->update([
                'remarks' => DB::raw("CONCAT(ISNULL(remarks, ''), ' [" . $runId . "]')"),
            ]);

            $written++;
            $this->tally($tally, 'Written');
            $rows[] = [$fileNumber, 'WRITTEN', $state['op']['holder'], $state['indexing_name'], $result['message'], $result['pra_id']];
        }

        $bar->finish();
        $this->newLine(2);

        $verb = $dryRun ? 'Would write' : 'Wrote';
        $this->info("{$verb} {$written} Transfer of Title row(s) from " . count($shortlist) . ' shortlisted file(s).');

        arsort($tally);
        $this->table(['Outcome', 'Files'], collect($tally)->map(fn ($n, $k) => [$k, $n])->values()->all());

        if ($report = $this->option('report')) {
            $this->writeReport($report, $rows);
            $this->line('Report: ' . $report);
        }

        if ($skipped) {
            $this->newLine();
            $this->warn('First ' . min(20, count($skipped)) . ' problem(s):');
            foreach (array_slice($skipped, 0, 20) as $line) {
                $this->line('  ' . $line);
            }
        }

        if (! $dryRun && $written > 0) {
            $this->newLine();
            $this->info('Run id ' . $runId . ' — undo with: php artisan op-match:rollback ' . $runId);
            Log::info('op-match:backfill completed', ['run' => $runId, 'written' => $written, 'shortlisted' => count($shortlist)]);
        }

        return self::SUCCESS;
    }

    /**
     * Stage 1. Files carrying an Occupancy Permit whose grantee is not the file
     * title, with no Transfer of Title already naming that title.
     *
     * Runs against pra alone on purpose: it is a cheap net, not the rule. Files it
     * over-includes (their transfer lives in file_history_staging, or the two names
     * are one person spelt twice) are dropped by check() in stage 2.
     *
     * @return array<int,string>
     */
    private function shortlist(): array
    {
        $db = DB::connection('sqlsrv');

        // SCOPE: only files carrying a non-batch RofO recommendation. These are the
        // files officers are actually working, and the ones the Match button would
        // offer anyway — the command just gets there first. The estate holds
        // thousands more in the same state; repairing a file nobody is working is
        // not this command's job, and doing it silently at that scale would be a
        // mass edit of the register nobody asked for.
        $recommended = [];
        foreach (
            $db->table('land_recommendations')
                ->whereRaw("LTRIM(RTRIM(ISNULL(file_number, ''))) <> ''")
                ->whereRaw("ISNULL(rofo_batch_id, '') = ''")
                ->whereRaw("ISNULL(batch_mother_file_no, '') = ''")
                ->pluck('file_number') as $fn
        ) {
            $key = $this->norm($fn);
            if ($key !== '') {
                $recommended[$key] = true;
            }
        }

        if (! $recommended) {
            return [];
        }

        $titles = [];
        $db->table('file_indexings')
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
            ->whereNotNull('file_title')->where('file_title', '<>', '')
            ->select('id', 'file_number', 'file_title')->orderBy('id')
            ->chunk(20000, function ($chunk) use (&$titles, $recommended) {
                foreach ($chunk as $r) {
                    $key = $this->norm($r->file_number);
                    if ($key !== '' && isset($recommended[$key])) {
                        $titles[$key] = $this->norm($r->file_title);
                    }
                }
            });

        $ops = [];
        $tots = [];
        $db->table('pra')
            ->where(function ($q) {
                $q->where('transaction_type', 'LIKE', '%Occupancy Permit%')
                  ->orWhere('instrument_type', 'LIKE', '%Occupancy Permit%')
                  ->orWhere('transaction_type', 'LIKE', '%Transfer of Title%')
                  ->orWhere('instrument_type', 'LIKE', '%Transfer of Title%');
            })
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
            ->select('id', 'mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno',
                     'party_1', 'party_2', 'transaction_type', 'instrument_type')
            ->orderBy('id')
            ->chunk(20000, function ($chunk) use (&$ops, &$tots, $titles) {
                foreach ($chunk as $r) {
                    $type = (string) ($r->transaction_type ?: $r->instrument_type);
                    $isOp = stripos($type, 'Occupancy Permit') !== false;

                    foreach ([$r->mlsFNo, $r->fileno, $r->kangisFileNo, $r->NewKANGISFileno] as $fn) {
                        $key = $this->norm($fn);
                        if ($key === '' || ! isset($titles[$key])) {
                            continue;
                        }
                        if ($isOp) {
                            $ops[$key][] = $this->norm($r->party_2);
                        } else {
                            $tots[$key][] = $this->norm($r->party_2);
                        }
                    }
                }
            });

        // Files carrying a BATCH recommendation are out of scope. A subdivision
        // batch is one grant split into plots: a child's holder differs from the
        // mother's grantee because of the subdivision, not because a transfer went
        // unrecorded. The Match button stands down in batch mode for the same
        // reason, and a backfill that disagreed with the form would write dealings
        // no officer would ever have been offered.
        $batched = [];
        foreach (
            $db->table('land_recommendations')
                ->where(function ($q) {
                    $q->whereRaw("ISNULL(rofo_batch_id, '') <> ''")
                      ->orWhereRaw("ISNULL(batch_mother_file_no, '') <> ''");
                })
                ->pluck('file_number') as $fn
        ) {
            $key = $this->norm($fn);
            if ($key !== '') {
                $batched[$key] = true;
            }
        }

        $out = [];
        foreach ($ops as $key => $holders) {
            if (isset($batched[$key])) {
                continue;
            }

            $title = $titles[$key] ?? '';
            if ($title === '' || in_array($title, $holders, true)) {
                continue;
            }
            if (in_array($title, $tots[$key] ?? [], true)) {
                continue;
            }
            $out[] = $key;
        }

        sort($out);

        return $out;
    }

    private function writeReport(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, ['file_number', 'outcome', 'op_holder', 'indexing_name', 'detail', 'new_pra_id']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    private function tally(array &$tally, string $key): void
    {
        $tally[$key] = ($tally[$key] ?? 0) + 1;
    }

    /** @return array<int,string> */
    private function csvOption(string $name): array
    {
        $raw = (string) $this->option($name);

        return $raw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function norm($value): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim((string) $value)));
    }
}
