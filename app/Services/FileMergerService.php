<?php

namespace App\Services;

use App\Models\FileMerger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Derives and maintains parcel-update / merger groups (Change of Purpose, Subdivision,
 * Merger, Extension, Separation, Temporary-File consolidation) so the File Tracking Sheet
 * and on-screen File Movement History can stitch every related file's movement log into a
 * single continuous lineage.
 *
 * The relationships already exist across decommissioned_files (old -> successor_file_no) and
 * file_indexings / fileNumber (related_fileno, parent_prop_id). This service materialises them
 * into the file_merger registry keyed by a stable MergerID, and stamps file_tracker.merger_id.
 *
 * Nothing here may throw into the parcel-update workflow: every public entry point is guarded.
 */
class FileMergerService
{
    /** Hard cap on group traversal so a bad lineage cycle can never hang a request. */
    private const MAX_MEMBERS = 60;

    private string $conn = 'sqlsrv';

    /**
     * Restrict a decommissioned_files query to REAL decommissions.
     *
     * See App\Support\DecommissionScope for the authoritative definition. In short:
     * false_decommissioning = 1 (a Title Status FLAG raised from File Indexing) is the
     * only value meaning "not really decommissioned". 0/NULL is an ordinary
     * decommissioning and 2 is an ST handover — the land file WAS decommissioned, taken
     * over by its Sectional Titling primary, which it names in successor_file_no. So an
     * ST handover is genuine lineage and belongs in the merger group.
     *
     * Without this filter every reader here treated all three as lineage, so a
     * title-status flag became a "decommissioned parent" complete with a bogus
     * decommissioning date drawn from a file that was never decommissioned at all.
     */
    private function onlyRealDecommissions($query)
    {
        if (!Schema::connection($this->conn)->hasColumn('decommissioned_files', 'false_decommissioning')) {
            return $query;
        }

        return $query->where(function ($q) {
            $q->where('false_decommissioning', '<>', \App\Support\DecommissionScope::FALSE_DECOMMISSIONING)->orWhereNull('false_decommissioning');
        });
    }

    /**
     * Discover the full group that $fileNumber belongs to, (re)materialise it into
     * file_merger, and stamp file_tracker.merger_id. Returns the MergerID, or null when the
     * file is not part of any parcel-update group.
     */
    public function rebuildForFile(string $fileNumber): ?string
    {
        try {
            $fileNumber = $this->clean($fileNumber);
            if ($fileNumber === '' || !Schema::connection($this->conn)->hasTable('decommissioned_files')) {
                return null;
            }

            $members = $this->discoverMembers($fileNumber);
            if (count($members) < 2) {
                return null; // a lone file is not a group
            }

            // Never clobber a manually-curated group: if any discovered file already belongs to a
            // manual group, that grouping is authoritative — leave it as-is.
            if ($manualId = $this->manualGroupIdForMembers(array_keys($members))) {
                return $manualId;
            }

            $meta = $this->collectMetadata($members);
            $parents = array_values(array_filter($meta, fn ($m) => $m['role'] === FileMerger::ROLE_PARENT));

            // Anchor the MergerID on the (stable) set of decommissioned parents so it does not
            // change as later subdivision siblings are discovered. Fall back to all members.
            $anchorKeys = $parents
                ? array_map(fn ($m) => $this->norm($m['file_number']), $parents)
                : array_map(fn ($m) => $this->norm($m['file_number']), $meta);
            sort($anchorKeys);
            $mergerId = 'MRG-' . strtoupper(substr(sha1(implode('|', array_unique($anchorKeys))), 0, 12));

            $this->persistGroup($mergerId, $meta);

            return $mergerId;
        } catch (\Throwable $e) {
            Log::warning('FileMergerService::rebuildForFile failed', [
                'file' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Ordered members of the group containing $fileNumber: decommissioned parents first
     * (by decommission date), then surviving children (by commission date). Empty when the
     * file has no related files. Each element carries file_number, role, title, location,
     * dates, reason and parent/child pointers.
     *
     * @return array<int,array<string,mixed>>
     */
    public function resolveGroup(string $fileNumber, bool $rebuild = true): array
    {
        try {
            $fileNumber = $this->clean($fileNumber);
            if ($fileNumber === '' || !Schema::connection($this->conn)->hasTable('file_merger')) {
                return [];
            }

            $mergerId = $this->findMergerId($fileNumber);
            if (!$mergerId && $rebuild) {
                $mergerId = $this->rebuildForFile($fileNumber);
            }
            if (!$mergerId) {
                return [];
            }

            $rows = FileMerger::forGroup($mergerId)->get();
            if ($rows->isEmpty()) {
                return [];
            }

            return $this->orderMembers($rows->map(function ($r) {
                return [
                    'file_number' => $r->file_number,
                    'role' => $r->role,
                    'file_title' => $r->file_title,
                    'location' => $r->location,
                    'date_commissioned' => $r->date_commissioned,
                    'date_decommissioned' => $r->date_decommissioned,
                    'reason' => $r->reason,
                    'parent_file' => $r->parent_file,
                    'child_file' => $r->child_file,
                    'merger_id' => $r->merger_id,
                ];
            })->all());
        } catch (\Throwable $e) {
            Log::warning('FileMergerService::resolveGroup failed', [
                'file' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * The other file numbers in $fileNumber's group (excluding itself). Fast [] for
     * files that are not part of any group.
     *
     * @return array<int,string>
     */
    public function relatedFileNumbers(string $fileNumber): array
    {
        $group = $this->resolveGroup($fileNumber);
        if (!$group) {
            return [];
        }
        $self = $this->norm($fileNumber);
        return array_values(array_filter(
            array_map(fn ($m) => $m['file_number'], $group),
            fn ($fn) => $this->norm($fn) !== $self
        ));
    }

    // ------------------------------------------------------------------
    // Manual management (File Merger admin UI)
    // ------------------------------------------------------------------

    /**
     * Create or replace a manually-curated group — files a user selects to be merged together or
     * to function as a single unit. Each entry: ['file_number' => string, 'role' => 'parent'|'child'].
     * Saved with source='manual' so the auto rebuild never overwrites it. Returns the MergerID.
     *
     * @param array<int,array{file_number:string,role?:string}> $entries
     */
    public function createManualGroup(array $entries, ?string $reason = null, ?string $mergerId = null): string
    {
        $meta = [];
        $seen = [];
        foreach ($entries as $e) {
            $file = $this->clean((string) ($e['file_number'] ?? ''));
            if ($file === '') {
                continue;
            }
            $norm = $this->norm($file);
            if (isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;

            $role = (($e['role'] ?? '') === FileMerger::ROLE_PARENT) ? FileMerger::ROLE_PARENT : FileMerger::ROLE_CHILD;
            $d = $this->describeFile($file);
            $meta[] = [
                'file_number' => $file,
                'role' => $role,
                'file_title' => $d['title'],
                'location' => $d['location'],
                'date_commissioned' => $d['commissioned'],
                'date_decommissioned' => $role === FileMerger::ROLE_PARENT ? $d['decommissioned'] : null,
                'reason' => $reason,
                'parent_file' => null,
                'child_file' => null,
            ];
        }

        if (count($meta) < 2) {
            throw new \InvalidArgumentException('A merger group needs at least two files.');
        }

        // Cross-link parent/child pointers for readability.
        $parentList = implode(', ', array_map(fn ($m) => $m['file_number'], array_filter($meta, fn ($m) => $m['role'] === FileMerger::ROLE_PARENT))) ?: null;
        $childList = implode(', ', array_map(fn ($m) => $m['file_number'], array_filter($meta, fn ($m) => $m['role'] === FileMerger::ROLE_CHILD))) ?: null;
        foreach ($meta as &$m) {
            if ($m['role'] === FileMerger::ROLE_CHILD) {
                $m['parent_file'] = $parentList;
            } else {
                $m['child_file'] = $childList;
            }
        }
        unset($m);

        $mergerId = $mergerId ?: ('MRG-M-' . strtoupper(substr(sha1(implode('|', array_keys($seen)) . microtime(true)), 0, 12)));
        $this->persistGroup($mergerId, $meta, 'manual');

        return $mergerId;
    }

    /**
     * Delete a group entirely and clear its stamp from any trackers.
     */
    public function deleteGroup(string $mergerId): void
    {
        DB::connection($this->conn)->transaction(function () use ($mergerId) {
            if (Schema::connection($this->conn)->hasColumn('file_tracker', 'merger_id')) {
                DB::connection($this->conn)->table('file_tracker')->where('merger_id', $mergerId)->update(['merger_id' => null]);
            }
            DB::connection($this->conn)->table('file_merger')->where('merger_id', $mergerId)->delete();
        });
    }

    /**
     * Title / location / dates for a single file — powers autofill and manual group creation.
     *
     * @return array{title:?string,location:?string,commissioned:?Carbon,decommissioned:?Carbon,exists:bool}
     */
    public function describeFile(string $fileNumber): array
    {
        $cur = $this->norm($fileNumber);
        $title = null;
        $location = null;
        $commissioned = null;
        $decommissioned = null;
        $exists = false;

        $live = $this->liveFileDetails($cur);
        if ($live) {
            $exists = true;
            $title = $live['title'];
            $location = $live['location'];
            $commissioned = $live['commissioned'];
        }

        if (Schema::connection($this->conn)->hasTable('decommissioned_files')) {
            $dec = $this->onlyRealDecommissions(
                DB::connection($this->conn)->table('decommissioned_files')
                    ->where(function ($q) use ($cur) {
                        $q->whereRaw('UPPER(LTRIM(RTRIM(file_no))) = ?', [$cur])
                          ->orWhereRaw('UPPER(LTRIM(RTRIM(mls_file_no))) = ?', [$cur])
                          ->orWhereRaw('UPPER(LTRIM(RTRIM(kangis_file_no))) = ?', [$cur]);
                    })
            )
                ->orderByDesc('decommissioning_date')
                ->first();
            if ($dec) {
                $exists = true;
                $title = $title ?: (trim((string) ($dec->file_name ?? '')) ?: null);
                $decommissioned = $this->toDate($dec->decommissioning_date ?? null);
                $commissioned = $commissioned ?: $this->toDate($dec->commissioning_date ?? null);
                $location = $location ?: $this->locationFromDeprecated($cur);
            }
        }

        return compact('title', 'location', 'commissioned', 'decommissioned', 'exists');
    }

    /**
     * Movement-log entries (snake_case, ready for the on-screen timeline) that stitch the whole
     * lineage into one continuous history: a File Decommissioning marker for each decommissioned
     * parent, a File Commissioning marker for each surviving child, and every related file's real
     * movement-log rows. The current file's OWN real rows are skipped (they are rendered already)
     * via $excludeFileNumbers, but its lineage marker is still included.
     *
     * Returns [] fast for files that are not part of any parcel-update group.
     *
     * @param array<int,string> $excludeFileNumbers file numbers whose real movement rows to skip
     * @return array<int,array<string,mixed>>
     */
    public function groupMovementEntries(string $fileNumber, array $excludeFileNumbers = []): array
    {
        // Lineage-stitching disabled for now — parcel-update/merger group history should not
        // be woven into a file's own tracker log until re-enabled.
        return [];

        try {
            // Read the persisted registry (maintained by the backfill + decommission hook) rather
            // than rebuilding on every call — this runs per tracker on list-heavy screens.
            $group = $this->resolveGroup($fileNumber, false);
            if (!$group) {
                return [];
            }

            $exclude = [];
            foreach ($excludeFileNumbers as $ex) {
                $exclude[$this->norm($ex)] = true;
            }

            $entries = [];
            foreach ($group as $member) {
                $memberFile = trim((string) ($member['file_number'] ?? ''));
                if ($memberFile === '') {
                    continue;
                }
                $isParent = ($member['role'] ?? '') === FileMerger::ROLE_PARENT;

                // Lineage marker (always included, even for the current/searched file).
                if ($isParent && $member['date_decommissioned'] instanceof Carbon) {
                    $entries[] = $this->markerEntry(
                        'FILE DECOMMISSIONING — ' . $memberFile,
                        'File Decommissioning',
                        $member['date_decommissioned'],
                        '',
                        $memberFile
                    );
                } elseif (!$isParent && $member['date_commissioned'] instanceof Carbon) {
                    $entries[] = $this->markerEntry(
                        'FILE COMMISSIONING — ' . $memberFile,
                        'File Commissioning',
                        $member['date_commissioned'],
                        'New file commissioned',
                        $memberFile
                    );
                }

                // Real movement rows for related files (skip the excluded/current file).
                if (isset($exclude[$this->norm($memberFile)])) {
                    continue;
                }
                foreach ($this->movementRowsForFile($memberFile) as $row) {
                    $entries[] = $row;
                }
            }

            return $entries;
        } catch (\Throwable $e) {
            Log::warning('FileMergerService::groupMovementEntries failed', [
                'file' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function markerEntry(string $officeName, string $label, Carbon $date, string $notes, string $file): array
    {
        return [
            'log_id' => 'MERGER-' . strtoupper(substr(sha1($label . '|' . $file), 0, 10)),
            'office_code' => null,
            'office_name' => $officeName,
            'log_in_date' => $date->format('Y-m-d'),
            'log_in_time' => $date->format('H:i'),
            'log_out_date' => '',
            'log_out_time' => '',
            'status' => 'completed',
            'status_label' => $label,
            'notes' => $notes,
            'timestamp' => $date->toIso8601String(),
            'user_name' => 'System',
            '_merger_marker' => true,
            '_merger_file' => $file,
        ];
    }

    /**
     * Flattened movement_log rows across every tracker of $file, tagged with the source file
     * so the combined timeline stays legible.
     *
     * @return array<int,array<string,mixed>>
     */
    private function movementRowsForFile(string $file): array
    {
        if (!Schema::connection($this->conn)->hasTable('file_tracker')) {
            return [];
        }

        $cur = $this->norm($file);
        $trackers = DB::connection($this->conn)->table('file_tracker')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$cur])
            ->orderBy('id')
            ->get(['movement_log']);

        $rows = [];
        foreach ($trackers as $tracker) {
            $log = $tracker->movement_log;
            if (!is_array($log)) {
                $log = json_decode((string) ($log ?? '[]'), true);
            }
            if (!is_array($log)) {
                continue;
            }
            foreach ($log as $entry) {
                if (is_array($entry)) {
                    $entry['_merger_file'] = $file;
                    $rows[] = $entry;
                }
            }
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Discovery
    // ------------------------------------------------------------------

    /**
     * Breadth-first walk of the lineage graph starting at $seed. Returns a map of
     * normalised => original file number for every file reachable through
     * decommissioned_files (old<->successor) and related_fileno / parent_prop_id links.
     *
     * @return array<string,string>
     */
    private function discoverMembers(string $seed): array
    {
        $hasSuccessor = Schema::connection($this->conn)->hasColumn('decommissioned_files', 'successor_file_no');

        $members = [];              // norm => original
        $queue = [$seed];
        $visited = [];

        while ($queue && count($members) < self::MAX_MEMBERS) {
            $current = $this->clean(array_shift($queue));
            $cur = $this->norm($current);
            if ($cur === '' || isset($visited[$cur])) {
                continue;
            }
            $visited[$cur] = true;
            $members[$cur] = $members[$cur] ?? $current;

            // (a) $current as a decommissioned parent -> its successor child.
            $asParent = $this->onlyRealDecommissions(
                DB::connection($this->conn)->table('decommissioned_files')
                    ->where(function ($q) use ($cur) {
                        $q->whereRaw('UPPER(LTRIM(RTRIM(file_no))) = ?', [$cur])
                          ->orWhereRaw('UPPER(LTRIM(RTRIM(mls_file_no))) = ?', [$cur])
                          ->orWhereRaw('UPPER(LTRIM(RTRIM(kangis_file_no))) = ?', [$cur]);
                    })
            )->get();
            foreach ($asParent as $row) {
                $members[$cur] = $members[$cur] ?? trim((string) ($row->file_no ?? $current));
                if ($hasSuccessor) {
                    $child = $this->clean((string) ($row->successor_file_no ?? ''));
                    if ($child !== '') {
                        $this->enqueue($queue, $members, $child);
                    }
                }
            }

            // (b) $current as a successor child -> the parents it superseded.
            if ($hasSuccessor) {
                $asChild = $this->onlyRealDecommissions(
                    DB::connection($this->conn)->table('decommissioned_files')
                        ->whereRaw('UPPER(LTRIM(RTRIM(successor_file_no))) = ?', [$cur])
                )->get(['file_no']);
                foreach ($asChild as $row) {
                    $parent = $this->clean((string) ($row->file_no ?? ''));
                    if ($parent !== '') {
                        $this->enqueue($queue, $members, $parent);
                    }
                }
            }

            // (c) Subdivision/merger siblings: live files whose related_fileno references
            //     $current (i.e. $current is their parent). The LIKE is only a cheap prefilter —
            //     each candidate's related_fileno is then parsed and checked for EXACT membership,
            //     so "COM-2016-164" does not falsely match "CON-COM-2016-164" and chain unrelated
            //     files into one mega-group.
            foreach (['file_indexings' => 'file_number', 'fileNumber' => 'mlsfNo'] as $table => $col) {
                if (!Schema::connection($this->conn)->hasTable($table)
                    || !Schema::connection($this->conn)->hasColumn($table, 'related_fileno')) {
                    continue;
                }
                $candidates = DB::connection($this->conn)->table($table)
                    ->where('related_fileno', 'like', '%' . $current . '%')
                    ->limit(self::MAX_MEMBERS)
                    ->get([$col, 'related_fileno']);
                foreach ($candidates as $candidate) {
                    $kid = $this->clean((string) ($candidate->{$col} ?? ''));
                    if ($kid === '') {
                        continue;
                    }
                    // Confirm $current is an exact element of this candidate's related_fileno list.
                    $parents = array_map(fn ($p) => $this->norm($p), $this->parseFileList($candidate->related_fileno ?? null));
                    if (in_array($cur, $parents, true)) {
                        $this->enqueue($queue, $members, $kid);
                    }
                }
            }

            // (d) $current's own parents via its related_fileno.
            $related = $this->relatedFilenoFor($current);
            foreach ($related as $parent) {
                $parent = $this->clean($parent);
                if ($parent !== '') {
                    $this->enqueue($queue, $members, $parent);
                }
            }
        }

        return $members;
    }

    private function enqueue(array &$queue, array &$members, string $file): void
    {
        $norm = $this->norm($file);
        if ($norm === '' || isset($members[$norm]) || count($members) >= self::MAX_MEMBERS) {
            return; // already known, empty, or group is full — nothing to traverse
        }
        $members[$norm] = $file;
        $queue[] = $file;
    }

    /**
     * Parent file numbers referenced by $file's related_fileno column (JSON array or CSV),
     * looked up across the live tables.
     *
     * @return array<int,string>
     */
    private function relatedFilenoFor(string $file): array
    {
        $cur = $this->norm($file);
        foreach (['file_indexings' => 'file_number', 'fileNumber' => 'mlsfNo'] as $table => $col) {
            if (!Schema::connection($this->conn)->hasTable($table)
                || !Schema::connection($this->conn)->hasColumn($table, 'related_fileno')) {
                continue;
            }
            $raw = DB::connection($this->conn)->table($table)
                ->whereRaw("UPPER(LTRIM(RTRIM($col))) = ?", [$cur])
                ->value('related_fileno');
            if (!$raw) {
                continue;
            }
            return $this->parseFileList($raw);
        }
        return [];
    }

    /**
     * @return array<int,string>
     */
    private function parseFileList($raw): array
    {
        if (is_array($raw)) {
            return array_map('strval', $raw);
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_map('strval', $decoded);
        }
        return array_map('trim', explode(',', $raw));
    }

    // ------------------------------------------------------------------
    // Metadata
    // ------------------------------------------------------------------

    /**
     * Enrich each discovered member with role, title, location, dates and pointers.
     *
     * @param array<string,string> $members norm => original
     * @return array<int,array<string,mixed>>
     */
    private function collectMetadata(array $members): array
    {
        $out = [];
        foreach ($members as $original) {
            $file = $this->clean($original);
            $cur = $this->norm($file);

            $dec = $this->onlyRealDecommissions(
                DB::connection($this->conn)->table('decommissioned_files')
                    ->where(function ($q) use ($cur) {
                        $q->whereRaw('UPPER(LTRIM(RTRIM(file_no))) = ?', [$cur])
                          ->orWhereRaw('UPPER(LTRIM(RTRIM(mls_file_no))) = ?', [$cur])
                          ->orWhereRaw('UPPER(LTRIM(RTRIM(kangis_file_no))) = ?', [$cur]);
                    })
            )
                ->orderByDesc('decommissioning_date')
                ->first();

            $isParent = $dec !== null;
            $hasSuccessor = Schema::connection($this->conn)->hasColumn('decommissioned_files', 'successor_file_no');

            $title = null;
            $location = null;
            $commissioned = null;
            $decommissioned = null;
            $reason = null;
            $childFile = null;
            $parentFile = null;

            if ($isParent) {
                $title = trim((string) ($dec->file_name ?? '')) ?: null;
                $decommissioned = $this->toDate($dec->decommissioning_date ?? null);
                $commissioned = $this->toDate($dec->commissioning_date ?? null);
                $reason = trim((string) ($dec->decommissioning_reason ?? '')) ?: null;
                if ($hasSuccessor) {
                    $childFile = trim((string) ($dec->successor_file_no ?? '')) ?: null;
                }
                $location = $this->locationFromDeprecated($cur);
            }

            // Overlay live-record details (children, and any parent still surviving).
            $live = $this->liveFileDetails($cur);
            if ($live) {
                $title = $title ?: $live['title'];
                $location = $location ?: $live['location'];
                $commissioned = $commissioned ?: $live['commissioned'];
            }

            $parents = $this->relatedFilenoFor($file);
            if ($parents) {
                $parentFile = implode(', ', $parents);
            }

            $out[] = [
                'file_number' => $file,
                'role' => $isParent ? FileMerger::ROLE_PARENT : FileMerger::ROLE_CHILD,
                'file_title' => $title,
                'location' => $location,
                'date_commissioned' => $commissioned,
                'date_decommissioned' => $decommissioned,
                'reason' => $reason,
                'parent_file' => $parentFile,
                'child_file' => $childFile,
            ];
        }

        return $out;
    }

    /**
     * @return array{title:?string,location:?string,commissioned:?Carbon}|null
     */
    private function liveFileDetails(string $curNorm): ?array
    {
        if (Schema::connection($this->conn)->hasTable('file_indexings')) {
            $row = DB::connection($this->conn)->table('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$curNorm])
                ->orWhereRaw('UPPER(LTRIM(RTRIM(kangis_file_no))) = ?', [$curNorm])
                ->first();
            if ($row) {
                return [
                    'title' => trim((string) ($row->file_title ?? '')) ?: null,
                    'location' => $this->composeLocation($row->location ?? null, $row->district ?? null, $row->lga ?? null),
                    'commissioned' => $this->toDate($row->created_at ?? null),
                ];
            }
        }

        if (Schema::connection($this->conn)->hasTable('fileNumber')) {
            $row = DB::connection($this->conn)->table('fileNumber')
                ->whereRaw('UPPER(LTRIM(RTRIM(mlsfNo))) = ?', [$curNorm])
                ->orWhereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = ?', [$curNorm])
                ->first();
            if ($row) {
                return [
                    'title' => trim((string) ($row->FileName ?? '')) ?: null,
                    'location' => $this->composeLocation($row->location ?? null, $row->district ?? null, $row->lga ?? null),
                    'commissioned' => $this->toDate($row->commissioning_date ?? ($row->created_at ?? null)),
                ];
            }
        }

        return null;
    }

    private function locationFromDeprecated(string $curNorm): ?string
    {
        if (!Schema::connection($this->conn)->hasTable('deprecated_records')) {
            return null;
        }
        $row = DB::connection($this->conn)->table('deprecated_records')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$curNorm])
            ->first();
        if (!$row) {
            return null;
        }
        return $this->composeLocation($row->location ?? null, $row->district ?? null, $row->lga ?? null);
    }

    private function composeLocation($location, $district, $lga): ?string
    {
        $parts = array_filter([
            trim((string) ($location ?? '')),
            trim((string) ($district ?? '')),
            trim((string) ($lga ?? '')),
        ], fn ($p) => $p !== '');
        return $parts ? implode(', ', array_unique($parts)) : null;
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    /**
     * @param array<int,array<string,mixed>> $meta
     */
    private function persistGroup(string $mergerId, array $meta, string $source = 'auto'): void
    {
        $memberUpper = array_map(fn ($m) => $this->norm($m['file_number']), $meta);

        DB::connection($this->conn)->transaction(function () use ($mergerId, $meta, $memberUpper, $source) {
            // Clear this group and evict any of its files that lingered in a stale group from an
            // earlier, less-complete discovery — a file belongs to exactly one lineage component.
            // The auto rebuild never touches manually-curated rows; a manual save may replace them.
            DB::connection($this->conn)->table('file_merger')->where('merger_id', $mergerId)->delete();
            if ($memberUpper) {
                $evict = DB::connection($this->conn)->table('file_merger')
                    ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $memberUpper);
                if ($source !== 'manual') {
                    $evict->where('source', '!=', 'manual');
                }
                $evict->delete();
            }

            $now = now();
            $rows = [];
            $fileNumbers = [];
            foreach ($meta as $m) {
                $fileNumbers[] = $m['file_number'];
                $rows[] = [
                    'merger_id' => $mergerId,
                    'fmerge_id' => 'FMRG-' . strtoupper(substr(sha1($mergerId . '|' . $this->norm($m['file_number'])), 0, 12)),
                    'role' => $m['role'],
                    'parent_file' => $m['parent_file'] ? mb_substr((string) $m['parent_file'], 0, 255) : null,
                    'child_file' => $m['child_file'] ? mb_substr((string) $m['child_file'], 0, 255) : null,
                    'file_number' => mb_substr($m['file_number'], 0, 255),
                    'file_title' => $m['file_title'] ? mb_substr((string) $m['file_title'], 0, 500) : null,
                    'location' => $m['location'] ? mb_substr((string) $m['location'], 0, 500) : null,
                    'date_commissioned' => $m['date_commissioned'] instanceof Carbon ? $m['date_commissioned'] : null,
                    'date_decommissioned' => $m['date_decommissioned'] instanceof Carbon ? $m['date_decommissioned'] : null,
                    'source' => $source,
                    'reason' => $m['reason'] ? mb_substr((string) $m['reason'], 0, 255) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows) {
                DB::connection($this->conn)->table('file_merger')->insert($rows);
            }

            // Stamp the group id onto every related tracker.
            if (Schema::connection($this->conn)->hasColumn('file_tracker', 'merger_id') && $fileNumbers) {
                $upper = array_map(fn ($f) => $this->norm($f), $fileNumbers);
                DB::connection($this->conn)->table('file_tracker')
                    ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $upper)
                    ->update(['merger_id' => $mergerId]);
            }
        });
    }

    /**
     * If any of the given normalised file numbers already belongs to a manually-curated group,
     * return that group's MergerID (so the auto rebuild leaves it alone). Null otherwise.
     *
     * @param array<int,string> $normFiles
     */
    private function manualGroupIdForMembers(array $normFiles): ?string
    {
        if (!$normFiles) {
            return null;
        }
        $mid = DB::connection($this->conn)->table('file_merger')
            ->where('source', 'manual')
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), array_values(array_unique($normFiles)))
            ->value('merger_id');
        return $mid ? (string) $mid : null;
    }

    private function findMergerId(string $fileNumber): ?string
    {
        $cur = $this->norm($fileNumber);

        $mid = DB::connection($this->conn)->table('file_merger')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$cur])
            ->value('merger_id');
        if ($mid) {
            return (string) $mid;
        }

        if (Schema::connection($this->conn)->hasColumn('file_tracker', 'merger_id')) {
            $mid = DB::connection($this->conn)->table('file_tracker')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$cur])
                ->whereNotNull('merger_id')
                ->value('merger_id');
            if ($mid) {
                return (string) $mid;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Ordering & helpers
    // ------------------------------------------------------------------

    /**
     * Parents first (by decommission date asc, nulls last), then children (by commission
     * date asc, nulls last) — the lineage reading order the spec requires.
     *
     * @param array<int,array<string,mixed>> $members
     * @return array<int,array<string,mixed>>
     */
    private function orderMembers(array $members): array
    {
        $parents = array_values(array_filter($members, fn ($m) => ($m['role'] ?? '') === FileMerger::ROLE_PARENT));
        $children = array_values(array_filter($members, fn ($m) => ($m['role'] ?? '') !== FileMerger::ROLE_PARENT));

        $byDate = function (string $key) {
            return function ($a, $b) use ($key) {
                $ta = $this->ts($a[$key] ?? null);
                $tb = $this->ts($b[$key] ?? null);
                return $ta <=> $tb;
            };
        };

        usort($parents, $byDate('date_decommissioned'));
        usort($children, $byDate('date_commissioned'));

        return array_merge($parents, $children);
    }

    private function ts($date): float
    {
        if ($date instanceof Carbon) {
            return (float) $date->timestamp;
        }
        if (is_string($date) && trim($date) !== '') {
            try {
                return (float) Carbon::parse($date)->timestamp;
            } catch (\Throwable $e) {
                // fall through
            }
        }
        return PHP_FLOAT_MAX; // nulls last
    }

    private function toDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function clean(string $value): string
    {
        // Collapse whitespace; strip a trailing temporary-file "(T)" marker for matching parity
        // with how the file's permanent number is stored elsewhere.
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        return $value;
    }

    private function norm(string $value): string
    {
        return mb_strtoupper($this->clean($value));
    }
}
