<?php

namespace App\Services\FileIndexing;

use Illuminate\Support\Facades\DB;

/**
 * Group tracking for block-indexed files (user, 2026-11-17): one tracking sheet lists
 * every file in the block, so the block is tracked as one group.
 *
 * Two kinds of block:
 *   land   - a file indexed as "Land Block Indexing" (file_indexings.indexing_type =
 *            'Block'). Its files are file_indexing_links rows (indexing_type 'Block',
 *            mfile = 1), each with its own holder and plot. Same list as the Block
 *            button on the Indexed Files table.
 *   kangis - a land file that holds several KANGIS files (KANGIS Block Indexing,
 *            allow_multiple_kangis = 1). The group is the land file plus every live
 *            KANGIS record that carries it as Related File No; tracking the land file
 *            or any of its KANGIS files prints the same list.
 *
 * Read only. forFileNumber() returns null when the file is not in a block.
 */
class BlockTrackingGroup
{
    public function __construct(private KangisBlockIndexing $kangis)
    {
    }

    /**
     * @return array{type:string, label:string, land_file:string, files:array<int, array<string, ?string>>}|null
     */
    public function forFileNumber(?string $fileNumber): ?array
    {
        // Matched on file_number as stored (the column is indexed and case-insensitive),
        // plus the spaceless form, so the lookup stays fast enough for a list of trackers.
        $no = trim((string) preg_replace('/\s+/', ' ', (string) $fileNumber));
        if ($no === '') {
            return null;
        }
        $variants = array_values(array_unique([$no, KangisBlockIndexing::key($no)]));

        try {
            return $this->landBlock($variants) ?? $this->kangisBlock($variants);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * The block as a tracker carries it: a tracker opened for a block (Log a File, user
     * 2026-11-18) lists only the files ticked for that movement and names the Block ID it
     * was tracked under. A tracker opened before Block IDs existed shows the whole block.
     */
    public static function forTracker($tracker): ?array
    {
        $group = app(self::class)->forFileNumber((string) ($tracker->file_number ?? ''));
        if (!$group) {
            return null;
        }

        $trackedBlockId = trim((string) ($tracker->block_id ?? ''));
        if ($trackedBlockId !== '') {
            $group['block_id'] = $trackedBlockId;
        }

        $picked = json_decode((string) ($tracker->block_members ?? ''), true);
        if (is_array($picked) && $picked) {
            $total = count($group['files']);
            $group['files'] = array_values(array_filter($group['files'], fn ($f) => in_array($f['key'] ?? null, $picked, true)));
            $group['selected_of'] = $total;
        }

        return $group;
    }

    private function landBlock(array $variants): ?array
    {
        $row = $this->indexRows($variants)->where('indexing_type', 'Block')->orderByDesc('id')
            ->first(['id', 'file_number', 'district', 'lga', 'block_id']);
        if (!$row) {
            // The number may be one of the block's own entries (RES-2015-743's entries
            // carry CON-RES-2015-743), so a tracker opened on it finds the same block.
            $parentId = DB::connection('sqlsrv')->table('file_indexing_links')
                ->whereIn('file_number', $variants)
                ->where('indexing_type', 'Block')->where('mfile', 1)->whereNull('deleted_at')
                ->orderByDesc('id')->value('file_indexing_id');
            $row = $parentId
                ? DB::connection('sqlsrv')->table('file_indexings')->where('id', $parentId)->where('indexing_type', 'Block')
                    ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                    ->first(['id', 'file_number', 'district', 'lga', 'block_id'])
                : null;
        }
        if (!$row) {
            return null;
        }

        $links = DB::connection('sqlsrv')->table('file_indexing_links')
            ->where('file_indexing_id', $row->id)
            ->where('indexing_type', 'Block')->where('mfile', 1)->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'file_number', 'file_title', 'plot_number', 'district', 'lga']);
        if ($links->isEmpty()) {
            return null;
        }

        // Same display rule as the Indexed Files Block panel: an older entry saved the
        // word "Other" (or nothing) as its district, so the file's own district shows.
        $files = $links->map(function ($l) use ($row) {
            $district = trim((string) $l->district);
            if ($district === '' || strcasecmp($district, 'Other') === 0) {
                $own = trim((string) ($row->district ?? ''));
                $district = strcasecmp($own, 'Other') === 0 ? '' : $own;
            }

            return $this->entry($l->file_number ?: $row->file_number, $l->file_title, $l->plot_number, $district, $l->lga ?: $row->lga)
                + ['key' => 'link:' . $l->id];
        })->values()->all();

        return [
            'type' => 'land',
            'label' => 'Land Block Indexing',
            'block_id' => $this->ensureBlockId((int) $row->id, $row->block_id ?? null),
            'land_file' => (string) $row->file_number,
            'files' => $files,
        ];
    }

    private function kangisBlock(array $variants): ?array
    {
        // The searched file is the land file itself, or one of its KANGIS files. A KANGIS
        // file can list several land files; the first that really holds two or more KANGIS
        // files is its block (KNML 3598's first land file, RES-2013-677, holds only it).
        $candidates = [];
        $own = $this->indexRows($variants)->orderByDesc('id')
            ->get(['file_number', 'general_registry', 'related_fileno', 'allow_multiple_kangis']);
        foreach ($own as $row) {
            if (!KangisBlockIndexing::isKangisRegistry($row->general_registry) && (int) $row->allow_multiple_kangis === 1) {
                $candidates[] = (string) $row->file_number;
                break;
            }
        }
        if (!$candidates) {
            $kangisRow = $own->first(fn ($r) => KangisBlockIndexing::isKangisRegistry($r->general_registry));
            foreach (KangisBlockIndexing::landFilesFrom($kangisRow->related_fileno ?? null) as $candidate) {
                if ($this->isBlockLandFile($candidate)) {
                    $candidates[] = $candidate;
                }
            }
        }

        $landFile = null;
        $members = collect();
        foreach ($candidates as $candidate) {
            $found = $this->kangis->kangisMembersFor($candidate);
            if ($found->count() >= 2) {
                $landFile = $candidate;
                $members = $found;
                break;
            }
        }
        if ($landFile === null) {
            return null;
        }

        $land = $this->indexRows([$landFile])
            ->where(fn ($q) => $q->whereNull('general_registry')->orWhere('general_registry', '!=', KangisBlockIndexing::REGISTRY))
            ->orderByDesc('id')->first(['id', 'file_number', 'file_title', 'plot_number', 'district', 'lga', 'kangis_base_title', 'block_id']);

        $files = [];
        if ($land) {
            // The land file's own holder and first plot, not the combined "A, B and C"
            // title and plot list the KANGIS files have added to it.
            $files[] = $this->entry(
                $land->file_number,
                $land->kangis_base_title ?: $land->file_title,
                trim(explode(',', (string) $land->plot_number)[0]),
                $land->district,
                $land->lga,
                'Land file'
            ) + ['key' => 'file:' . KangisBlockIndexing::key($land->file_number)];
        }
        foreach ($members as $m) {
            $files[] = $this->entry($m->file_number, $m->file_title, $m->plot_number, $m->district, $m->lga, 'KANGIS file')
                + ['key' => 'file:' . KangisBlockIndexing::key($m->file_number)];
        }

        return [
            'type' => 'kangis',
            'label' => 'KANGIS Block Indexing',
            'block_id' => $land ? $this->ensureBlockId((int) $land->id, $land->block_id ?? null) : null,
            'land_file' => $land ? (string) $land->file_number : $landFile,
            'files' => $files,
        ];
    }

    /**
     * The block's system-generated Block ID (user, 2026-11-18), kept on the block's land
     * file row. A block is tracked under it instead of the file's own tracking ID. Made
     * here when missing, so a block indexed by any path always has one; indexing also
     * assigns it on save (assignFor()). Same shape as a tracking ID, so a registry code
     * can be appended the same way: BLK-XXXXXXXX-XXXXX.
     */
    public function ensureBlockId(int $fileIndexingId, ?string $current = null): ?string
    {
        $current = trim((string) $current);
        if ($current !== '') {
            return $current;
        }

        try {
            $conn = DB::connection('sqlsrv');
            for ($i = 0; $i < 5; $i++) {
                $id = 'BLK-' . strtoupper(\Illuminate\Support\Str::random(8)) . '-' . strtoupper(\Illuminate\Support\Str::random(5));
                if ($conn->table('file_indexings')->where('block_id', $id)->exists()) {
                    continue;
                }
                // Only fill an empty one, so two requests never give one block two IDs.
                $conn->table('file_indexings')->where('id', $fileIndexingId)
                    ->where(fn ($q) => $q->whereNull('block_id')->orWhere('block_id', ''))
                    ->update(['block_id' => $id]);

                return (string) $conn->table('file_indexings')->where('id', $fileIndexingId)->value('block_id');
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    /** Give a block land file its Block ID when it is saved as a block (indexing hooks). */
    public function assignFor(int $fileIndexingId): ?string
    {
        $row = DB::connection('sqlsrv')->table('file_indexings')->where('id', $fileIndexingId)
            ->first(['id', 'indexing_type', 'allow_multiple_kangis', 'general_registry', 'block_id']);
        if (!$row) {
            return null;
        }
        $isBlock = $row->indexing_type === 'Block'
            || ((int) ($row->allow_multiple_kangis ?? 0) === 1 && !KangisBlockIndexing::isKangisRegistry($row->general_registry));

        return $isBlock ? $this->ensureBlockId((int) $row->id, $row->block_id) : null;
    }

    private function indexRows(array $variants)
    {
        return DB::connection('sqlsrv')->table('file_indexings')
            ->whereIn('file_number', $variants)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0));
    }

    private function isBlockLandFile(string $landFile): bool
    {
        return $this->indexRows([trim($landFile)])
            ->where('allow_multiple_kangis', 1)
            ->where(fn ($q) => $q->whereNull('general_registry')->orWhere('general_registry', '!=', KangisBlockIndexing::REGISTRY))
            ->exists();
    }

    private function entry($fileNo, $holder, $plot, $district, $lga, ?string $role = null): array
    {
        $clean = fn ($v) => ($v = trim((string) $v)) === '' || $v === '-' ? null : $v;
        $district = $clean($district);
        $lga = $clean($lga);

        // Property order: Plot No., District, LGA, Location (District + LGA, never the plot).
        return [
            'file_number' => $clean($fileNo),
            'holder' => $clean($holder),
            'plot_number' => $clean($plot),
            'district' => $district,
            'lga' => $lga,
            'location' => implode(', ', array_filter([$district, $lga])) ?: null,
            'role' => $role,
        ];
    }
}
