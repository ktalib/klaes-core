<?php

namespace App\Console\Commands;

use App\Services\KangisParentLinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfills the KANGIS three-file parent_prop_id linkage (guide-faithful "Option A")
 * for records that were indexed before the link could be resolved.
 *
 * Because the three files (Old KANGIS parent, Land, New KANGIS) are indexed
 * independently and in any order, a child is often indexed before its Old KANGIS
 * parent exists — so its parent_prop_id can't be set at index time. This command
 * sweeps both directions and fills the gaps:
 *
 *   Pass 1 (child -> parent): a Land / New KANGIS row whose related_fileno quotes a
 *       legacy KANGIS number gets parent_prop_id = that KANGIS file's prop_id.
 *   Pass 2 (parent -> child): a legacy KANGIS (Old KANGIS) row re-parents the
 *       Land / New KANGIS files it references (related_fileno + new_kangis_file_no).
 *
 * Forward-only: it never re-splits or reassigns existing prop_ids — it only MERGES
 * a missing parent into the parent_prop_id ancestor list (idempotent).
 */
class LinkKangisParentPropIds extends Command
{
    protected $signature = 'kangis:link-parent-propids
        {--dry-run : Report what would be linked without writing}
        {--chunk=500 : Rows to scan per chunk}';

    protected $description = 'Backfill KANGIS three-file parent_prop_id links (Land + New KANGIS -> Old KANGIS parent). Idempotent, forward-only.';

    public function __construct(private KangisParentLinkService $links)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));
        $conn = DB::connection('sqlsrv');

        $fileLinks = 0;
        $txnLinks = 0;

        // ── Pass 1: child -> parent ──────────────────────────────────────────
        // Land / New KANGIS rows that carry a related_fileno pointing at a legacy
        // KANGIS number. Set their parent to that KANGIS file's prop_id.
        $this->info('Pass 1: linking children (Land / New KANGIS) up to their Old KANGIS parent...');

        $conn->table('file_indexings')
            ->whereNotNull('related_fileno')
            ->where('related_fileno', '<>', '')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk($chunk, function ($rows) use (&$fileLinks, &$txnLinks, $dryRun) {
                foreach ($rows as $row) {
                    $childNo = trim((string) $row->file_number);
                    if ($childNo === '' || $this->links->isLegacyKangisNumber($childNo)) {
                        continue; // only non-legacy files can be children in this pass
                    }

                    foreach ($this->parseRelated($row->related_fileno) as $rel) {
                        if (!$this->links->isLegacyKangisNumber($rel)) {
                            continue;
                        }
                        $parentPropId = $this->links->lookupExistingPropId($rel);
                        if ($parentPropId === null) {
                            continue;
                        }

                        if ($this->alreadyLinked($row->parent_prop_id, $parentPropId)) {
                            break;
                        }

                        $this->line("  [child] {$childNo}  ->  parent {$rel} (prop_id {$parentPropId})");
                        if (!$dryRun) {
                            $this->links->assignFileLevelParent($childNo, $parentPropId);
                            $txnLinks += $this->links->assignTransactionParent($childNo, $parentPropId);
                        }
                        $fileLinks++;
                        break; // one Old KANGIS parent per child
                    }
                }
            });

        // ── Pass 2: parent -> child ──────────────────────────────────────────
        // Legacy KANGIS (Old KANGIS) rows with a prop_id re-parent the files they
        // reference (related_fileno entries + the new_kangis_file_no column).
        $this->info('Pass 2: linking Old KANGIS parents down to their referenced children...');

        $conn->table('file_indexings')
            ->whereNotNull('prop_id')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk($chunk, function ($rows) use (&$fileLinks, &$txnLinks, $dryRun) {
                foreach ($rows as $row) {
                    $parentNo = trim((string) $row->file_number);
                    if (!$this->links->isLegacyKangisNumber($parentNo) || empty($row->prop_id)) {
                        continue;
                    }
                    $parentPropId = (int) $row->prop_id;

                    $children = $this->parseRelated($row->related_fileno);
                    $newKn = trim((string) ($row->new_kangis_file_no ?? ''));
                    if ($newKn !== '') {
                        $children[] = $newKn;
                    }

                    foreach (array_unique($children) as $child) {
                        $child = trim((string) $child);
                        // A child is a Land or New KANGIS file — not another legacy KANGIS number.
                        if ($child === '' || $this->links->isLegacyKangisNumber($child)) {
                            continue;
                        }
                        // Must actually be indexed already.
                        if ($this->links->lookupExistingPropId($child) === null
                            && !$this->isIndexed($child)) {
                            continue;
                        }

                        $current = $this->currentFileParent($child);
                        if ($this->alreadyLinked($current, $parentPropId)) {
                            continue;
                        }

                        $this->line("  [parent] {$parentNo} (prop_id {$parentPropId})  ->  child {$child}");
                        if (!$dryRun) {
                            $this->links->assignFileLevelParent($child, $parentPropId);
                            $txnLinks += $this->links->assignTransactionParent($child, $parentPropId);
                        }
                        $fileLinks++;
                    }
                }
            });

        $verb = $dryRun ? 'Would link' : 'Linked';
        $this->info("Done. {$verb} {$fileLinks} file(s); {$txnLinks} transaction row(s) stamped.");

        return self::SUCCESS;
    }

    /**
     * related_fileno is usually a JSON array string; can be a plain file number.
     *
     * @return array<int,string>
     */
    private function parseRelated($value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $decoded), fn ($v) => $v !== ''));
        }

        return [$value];
    }

    private function alreadyLinked(?string $existing, int $propId): bool
    {
        $ids = array_map('trim', explode(',', (string) $existing));

        return in_array((string) $propId, $ids, true);
    }

    private function currentFileParent(string $fileNumber): ?string
    {
        $row = DB::connection('sqlsrv')->table('file_indexings')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [trim($fileNumber)])
            ->orderByDesc('id')
            ->first(['parent_prop_id']);

        return $row->parent_prop_id ?? null;
    }

    private function isIndexed(string $fileNumber): bool
    {
        return DB::connection('sqlsrv')->table('file_indexings')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [trim($fileNumber)])
            ->exists();
    }
}
