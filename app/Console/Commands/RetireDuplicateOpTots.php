<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retires superseded duplicate "Transfer of Title (OP)" rows.
 *
 * A file re-captured through FFR Existing Capture gets a second Transfer of Title
 * written alongside the one an earlier Change-of-Name session already created —
 * the flow does not notice the existing row. Both stay live, so the OP Details
 * card renders the same transfer twice (see RES-2026-2274: pra #151625 from May
 * beside pra #192482 from August).
 *
 * The survivor is the row carrying the source_op_id link to its OP. That link is
 * what the card, the ROFO row and the Legal Search timeline all navigate, so the
 * linked row is the one the rest of the system already treats as current; the
 * unlinked copy is the leftover.
 *
 * Deliberately conservative — a group is skipped and reported rather than guessed at
 * unless all of the following hold:
 *  - exactly one row in the group carries a source_op_id (nothing to choose between
 *    two linked rows, and nothing to prefer between two unlinked ones);
 *  - every row names the same Party 2, so the rows are copies of one transfer
 *    rather than a genuine chain of successive dealings;
 *  - no row in the group belongs to an op_batch (batch rows are out of scope).
 *
 * Retirement is a soft delete (is_deleted = 1) with the reason written to remarks —
 * the row stays recoverable and nothing is destroyed.
 *
 * Idempotent — an already-retired duplicate leaves a group with one live row, which
 * no longer matches. Use --dry-run to preview.
 */
class RetireDuplicateOpTots extends Command
{
    protected $signature = 'oss:retire-duplicate-op-tots
        {--dry-run : Show what would change without writing}
        {--only= : Comma-separated file numbers to limit the run to}';

    protected $description = 'Soft-delete superseded duplicate Transfer of Title (OP) rows, keeping the one linked to its OP';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        $db = DB::connection('sqlsrv');

        $only = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('only'))
        )));

        $rows = $db->table('pra')
            ->where('instrument_type', 'LIKE', '%Transfer of Title%')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->when($only, function ($q) use ($only) {
                $q->where(function ($inner) use ($only) {
                    $inner->whereIn('mlsFNo', $only)->orWhereIn('fileno', $only);
                });
            })
            ->orderBy('id')
            ->get([
                'id', 'prop_id', 'mlsFNo', 'fileno', 'temp_fileno', 'op_batch',
                'source_op_id', 'source_op_table', 'Grantor', 'Grantee',
                'party_1', 'party_2', 'created_at', 'remarks',
            ]);

        $groups = $rows->groupBy(fn ($r) => trim((string) ($r->mlsFNo ?: $r->fileno)))
            ->filter(fn ($group, $fileNo) => $fileNo !== '' && $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('Nothing to retire — no file number carries more than one live Transfer of Title (OP).');
            return self::SUCCESS;
        }

        $retired = 0;
        $skipped = [];

        foreach ($groups as $fileNo => $group) {
            if ($group->first(fn ($r) => ! empty($r->op_batch))) {
                $skipped[] = "{$fileNo}: belongs to an op_batch (out of scope)";
                continue;
            }

            $buyers = $group->map(fn ($r) => $this->norm($r->Grantee ?: $r->party_2))
                ->filter()
                ->unique();
            if ($buyers->count() > 1) {
                $skipped[] = "{$fileNo}: Party 2 differs across the rows ({$buyers->implode(' / ')}) — "
                    . 'these look like successive dealings, not duplicates';
                continue;
            }

            $linked = $group->filter(fn ($r) => ! empty($r->source_op_id))->values();
            if ($linked->count() !== 1) {
                $skipped[] = "{$fileNo}: " . $linked->count() . ' of ' . $group->count()
                    . ' rows carry a source_op_id — cannot tell which row is current';
                continue;
            }

            $keep = $linked->first();
            $drop = $group->reject(fn ($r) => $r->id === $keep->id)->values();

            // A row that other live rows point at through source_op_id is load-bearing —
            // some files carry a hybrid row that reads as a Transfer of Title while
            // serving as the OP its successor links to. Retiring it would sever that
            // link, so the whole group is left for a human.
            $referenced = $db->table('pra')
                ->where('source_op_table', 'pra')
                ->whereIn('source_op_id', $drop->pluck('id')->all())
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->pluck('source_op_id')
                ->unique();

            if ($referenced->isNotEmpty()) {
                $skipped[] = "{$fileNo}: row(s) #{$referenced->implode(', #')} are referenced as the source OP "
                    . 'of a live row — retiring them would break that link';
                continue;
            }

            $this->line("  {$fileNo}: keeping #{$keep->id} (linked to OP #{$keep->source_op_id})");

            foreach ($drop as $row) {
                $this->line("    retiring #{$row->id} (prop_id {$row->prop_id}, temp {$row->temp_fileno}, created {$row->created_at})");

                if (! $dryRun) {
                    $db->table('pra')->where('id', $row->id)->update([
                        'is_deleted' => 1,
                        'remarks' => trim(($row->remarks ?? '') . ' Retired as a duplicate Transfer of Title superseded by pra #'
                            . $keep->id . ' on ' . now()->toDateString() . ' (oss:retire-duplicate-op-tots).'),
                        'updated_at' => now()->toDateTimeString(),
                    ]);
                }

                $retired++;
            }
        }

        $verb = $dryRun ? 'Would retire' : 'Retired';
        $this->info("{$verb} {$retired} duplicate Transfer of Title (OP) row(s) across " . $groups->count() . ' file number(s) examined.');

        if ($skipped) {
            $this->newLine();
            $this->warn('Skipped ' . count($skipped) . ' group(s) needing a human decision:');
            foreach ($skipped as $line) {
                $this->line('  ' . $line);
            }
        }

        return self::SUCCESS;
    }

    private function norm($value): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim((string) $value)));
    }
}
