<?php

namespace App\Console\Commands;

use App\Models\LandRecommendation;
use App\Models\SltrRecommendation;
use App\Services\Pra\RofoPraSyncer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillRofoToPra extends Command
{
    protected $signature = 'rofo:backfill-pra
        {--source=all : land | sltr | st | all}
        {--chunk=200 : Rows per chunk}
        {--dry-run : List what would be synced without inserting}';

    protected $description = 'Backfill already-generated ROFOs (Land/OSS, SLTR, ST) into the pra table. Idempotent.';

    public function handle(RofoPraSyncer $syncer): int
    {
        $source = strtolower((string) $this->option('source'));
        $chunk = max(50, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $totals = ['land' => 0, 'sltr' => 0, 'st' => 0];

        if (in_array($source, ['all', 'land'], true)) {
            $totals['land'] = $this->backfillLand($syncer, $chunk, $dryRun);
        }

        if (in_array($source, ['all', 'sltr'], true)) {
            $totals['sltr'] = $this->backfillSltr($syncer, $chunk, $dryRun);
        }

        if (in_array($source, ['all', 'st'], true)) {
            $totals['st'] = $this->backfillSt($syncer, $chunk, $dryRun);
        }

        $this->info(sprintf(
            '%s — Land: %d, SLTR: %d, ST: %d (total %d)',
            $dryRun ? 'Would sync' : 'Synced',
            $totals['land'],
            $totals['sltr'],
            $totals['st'],
            array_sum($totals)
        ));

        return self::SUCCESS;
    }

    private function backfillLand(RofoPraSyncer $syncer, int $chunk, bool $dryRun): int
    {
        $this->info('Backfilling Land / OSS ROFOs...');
        $synced = 0;

        // A ROFO counts as issued if rofo_status says so, if it came from OSS, if
        // it holds a security paper serial — the serial is only ever written against
        // a physically printed document, and a batch of older records was left on
        // rofo_status=pending despite having one — or if it has simply been printed.
        //
        // The print-count branch is the one that catches RofOs printed without ever
        // being generated. LandRofoController::print() has no "must be generated"
        // guard (unlike batchPrint(), which filters on it), so a single print of a
        // pending record puts the letter in the applicant's hand, increments
        // rofo_print_count and never trips RofoPraSyncer. Those records carry no
        // rofo_generated_at and no serial, so without this branch they match nothing
        // above and can never reach PRA at all — while still counting as printed on
        // the RofO register.
        $query = LandRecommendation::query()
            ->where(function ($q) {
                $q->where('rofo_status', LandRecommendation::ROFO_GENERATED)
                  ->orWhereRaw("UPPER(ISNULL(type, '')) = 'OSS'")
                  ->orWhereRaw("LTRIM(RTRIM(ISNULL(land_rofo_serial_no, ''))) <> ''")
                  ->orWhereRaw("ISNULL(rofo_print_count, 0) > 0");
            })
            ->whereNotNull('file_number')
            ->where('file_number', '<>', '');

        $query->orderBy('id')->chunkById($chunk, function ($recs) use ($syncer, $dryRun, &$synced) {
            foreach ($recs as $rec) {
                if ($dryRun) {
                    if (!$this->praExists($rec->file_number)) {
                        $this->line("  [Land] would sync file_number={$rec->file_number}");
                        $synced++;
                    }
                    continue;
                }

                if ($syncer->syncLand($rec)) {
                    $synced++;
                }
            }
        });

        return $synced;
    }

    private function backfillSltr(RofoPraSyncer $syncer, int $chunk, bool $dryRun): int
    {
        $this->info('Backfilling SLTR ROFOs...');
        $synced = 0;

        $query = SltrRecommendation::query()
            ->where('rofo_status', SltrRecommendation::ROFO_GENERATED)
            ->whereNotNull('sltr_number')
            ->where('sltr_number', '<>', '');

        $query->orderBy('id')->chunkById($chunk, function ($recs) use ($syncer, $dryRun, &$synced) {
            foreach ($recs as $rec) {
                if ($dryRun) {
                    if (!$this->praExists($rec->sltr_number)) {
                        $this->line("  [SLTR] would sync sltr_number={$rec->sltr_number}");
                        $synced++;
                    }
                    continue;
                }

                if ($syncer->syncSltr($rec)) {
                    $synced++;
                }
            }
        });

        return $synced;
    }

    private function backfillSt(RofoPraSyncer $syncer, int $chunk, bool $dryRun): int
    {
        $this->info('Backfilling ST (Sectional Titling) ROFOs...');
        $synced = 0;
        $conn = DB::connection('sqlsrv');

        // ST ROFOs live in the `rofo` table, but rofo.sub_application_id is
        // unreliable for legacy rows (it points at IDs that no longer exist in
        // subapplications). The dependable link is the mother application:
        // any subapplication whose mother (main_application_id) has ROFO rows.
        // We also union the directly-referenced sub_application_ids so the
        // correctly-linked rows are never missed.
        $motherIds = $conn->table('rofo')
            ->whereNotNull('application_id')
            ->distinct()->pluck('application_id')
            ->map(fn ($v) => (int) $v)->filter()->values()->all();

        $directSubIds = $conn->table('rofo')
            ->whereNotNull('sub_application_id')
            ->distinct()->pluck('sub_application_id')
            ->map(fn ($v) => (int) $v)->filter()->values()->all();

        if (empty($motherIds) && empty($directSubIds)) {
            return 0;
        }

        $conn->table('subapplications')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->where(function ($q) use ($motherIds, $directSubIds) {
                if (!empty($motherIds)) {
                    $q->orWhereIn('main_application_id', $motherIds);
                }
                if (!empty($directSubIds)) {
                    $q->orWhereIn('id', $directSubIds);
                }
            })
            ->whereNotNull('fileno')
            ->where('fileno', '<>', '')
            ->orderBy('id')
            ->chunk($chunk, function ($rows) use ($syncer, $dryRun, &$synced) {
                $ids = $rows->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (empty($ids)) {
                    return;
                }

                if ($dryRun) {
                    foreach ($rows as $row) {
                        $fileNumber = trim((string) ($row->fileno ?? ''));
                        if ($fileNumber !== '' && !$this->praExists($fileNumber)) {
                            $this->line("  [ST] would sync sub_application_id={$row->id} file_number={$fileNumber}");
                            $synced++;
                        }
                    }
                    return;
                }

                $synced += $syncer->syncStUnits($ids);
            });

        return $synced;
    }

    private function praExists(string $rofoNumber): bool
    {
        return DB::connection('sqlsrv')->table('pra')
            ->where('rofo_number', $rofoNumber)
            ->exists();
    }
}
