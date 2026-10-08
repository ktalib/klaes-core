<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExecutesMasterDelete;
use App\Services\IndexingDuplicateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DcivMasterDeleteController extends Controller
{
    use ExecutesMasterDelete;

    public function destroy(Request $request, string $id, IndexingDuplicateService $service)
    {
        if ($denied = $this->denyUnlessMasterDeleter()) {
            return $denied;
        }
        if (!preg_match('/^(fi-)?[1-9][0-9]*$/D', $id)) {
            return response()->json(['success' => false, 'message' => 'Invalid DCIV record.'], 422);
        }

        try {
            $result = DB::connection('sqlsrv')->transaction(function () use ($request, $id, $service) {
                $db = DB::connection('sqlsrv');
                $indexed = str_starts_with($id, 'fi-');
                $row = $db->table($indexed ? 'file_indexings' : 'dciv_file_no')
                    ->where('id', $indexed ? substr($id, 3) : $id)->lockForUpdate()->first();
                if (!$row || ($row->is_deleted ?? false) || ($indexed && !in_array($row->registry, ['DCIV', 'LPCC'], true))) {
                    return response()->json(['success' => false, 'message' => 'DCIV record not found.'], 404);
                }
                $number = trim((string) ($indexed ? $row->file_number : $row->full_file_number));
                if ($denied = $this->denyUnlessConfirmationMatches($request, $number)) {
                    return $denied;
                }

                // Resolve the actual source, never treat IDs from the union as interchangeable.
                $files = $db->table('file_indexings')->where('file_number', $number)->lockForUpdate()->get();
                if ($files->count() !== 1 || ($indexed && (int) $files[0]->id !== (int) $row->id)) {
                    throw new \DomainException('The file must have exactly one matching indexing record before Master Delete. Review the matching records first.');
                }
                $metadata = $db->table('dciv_file_no')->where('full_file_number', $number)->lockForUpdate()->get();
                if ($metadata->count() > 1) {
                    throw new \DomainException('Multiple DCIV generation records share this number. Review them before Master Delete.');
                }
                // A file used by another investigation must be unlinked there first.
                if ($db->table('dciv_link')->where('related_file_number', $number)->where('main_file_number', '<>', $number)->exists()
                    || $db->table('master_dciv_links')->where('related_file_number', $number)->where('dciv_file_number', '<>', $number)->exists()) {
                    throw new \DomainException('This file is linked to another DCIV investigation. Remove that link before Master Delete.');
                }

                $links = $db->table('dciv_link')->where('main_file_number', $number)->get();
                $masterLinks = $db->table('master_dciv_links')->where('dciv_file_number', $number)->get();
                $grouping = $db->table('dciv_grouping')->where('dciv_fileno', $number)->get();
                $flagged = $db->table('file_indexings')->where('dciv_fileno', $number)->lockForUpdate()->get();
                $tracking = $this->deleteTracking($number, (int) $files[0]->id);
                $purge = $service->purge((int) $files[0]->id);
                if ($purge['status'] !== 'purged') {
                    throw new \DomainException($purge['status'] === 'blocked'
                        ? 'Cannot delete this file: ' . implode(', ', $purge['dependencies']) . '.'
                        : 'The indexed file could not be deleted. Refresh the table and try again.');
                }

                $counts = [
                    'dciv_link' => $db->table('dciv_link')->where('main_file_number', $number)->delete(),
                    'master_dciv_links' => $db->table('master_dciv_links')->where('dciv_file_number', $number)->delete(),
                    'dciv_file_no' => $db->table('dciv_file_no')->where('full_file_number', $number)->delete(),
                    'dciv_grouping_reset' => $db->table('dciv_grouping')->where('dciv_fileno', $number)
                        ->update(['mapping' => 0, 'dciv_fileno' => null]),
                ];
                // Related land files survive; retain any other investigation still linking them.
                foreach ($flagged as $related) {
                    $remaining = $db->table('master_dciv_links')->where('related_file_number', $related->file_number)->orderByDesc('id')->first();
                    $fallback = $remaining ? null : $db->table('dciv_link')->where('related_file_number', $related->file_number)->orderByDesc('id')->first();
                    $db->table('file_indexings')->where('id', $related->id)->where('dciv_fileno', $number)->update([
                        'dciv_status' => ($remaining || $fallback) ? 1 : 0,
                        'dciv_fileno' => $remaining->dciv_file_number ?? $fallback->main_file_number ?? null,
                        'dciv_reason' => $remaining->dciv_reason ?? null,
                    ]);
                }
                $flatCounts = array_filter($purge['counts'], 'is_numeric');
                $flatCounts += ($purge['counts']['child_rows'] ?? []) + ($purge['counts']['file_number_rows'] ?? []);
                return [
                    'number' => $number,
                    'counts' => $counts + $flatCounts + $tracking['counts'],
                    'snapshot' => ['tracking' => $tracking['snapshot'], 'indexing' => $purge['snapshot'], 'dciv_file_no' => $metadata->all(),
                        'dciv_link' => $links->all(), 'master_dciv_links' => $masterLinks->all(),
                        'dciv_grouping' => $grouping->all(), 'related_file_flags' => $flagged->all()],
                ];
            });
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            Log::error('DCIV Master Delete rolled back', ['record' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Master Delete failed. No changes were saved.'], 500);
        }

        if (!is_array($result)) {
            return $result;
        }
        $this->logMasterDelete('DcivFileNo', $id, $result['snapshot'], $result['counts'], 'DCIV file ' . $result['number']);
        return response()->json(['success' => true, 'message' => $result['number'] . ' was permanently deleted.']);
    }

    // Called within the same SQL Server transaction as the file deletion.
    private function deleteTracking(string $number, int $fileId): array
    {
        $db = DB::connection('sqlsrv');
        $schema = Schema::connection('sqlsrv');
        $snapshot = [];
        $counts = [];
        $trackers = $schema->hasTable('file_tracker')
            ? $db->table('file_tracker')->where('file_number', $number)->lockForUpdate()->get()
            : collect();
        $ids = $trackers->pluck('id')->all();

        if ($ids) {
            foreach (['kangis_checkout_approvals', 'file_tracker_department_backfill'] as $table) {
                if (!$schema->hasTable($table)) {
                    continue;
                }
                $query = $db->table($table)->whereIn('file_tracker_id', $ids);
                $snapshot[$table] = (clone $query)->lockForUpdate()->get()->all();
                $counts[$table] = $query->delete();
            }
            if ($schema->hasTable('indexing_duplicates') && $schema->hasColumn('indexing_duplicates', 'file_tracker_id')) {
                $query = $db->table('indexing_duplicates')->whereIn('file_tracker_id', $ids);
                $snapshot['indexing_duplicates'] = (clone $query)->lockForUpdate()->get()->all();
                $counts['indexing_duplicate_tracking_links_cleared'] = $query->update(['file_tracker_id' => null]);
            }
            $snapshot['file_tracker'] = $trackers->all();
            $counts['file_tracker'] = $db->table('file_tracker')->whereIn('id', $ids)->delete();
        }

        foreach ([
            'file_trackings' => ['file_indexing_id', $fileId],
            'indexed_file_trackers' => ['file_indexing_id', $fileId],
            'rds_tracking' => ['file_number', $number],
            'digital_file_tracking_requests' => ['file_no', $number],
        ] as $table => [$column, $value]) {
            if (!$schema->hasTable($table)) {
                continue;
            }
            $query = $db->table($table)->where($column, $value);
            $snapshot[$table] = (clone $query)->lockForUpdate()->get()->all();
            $counts[$table] = $query->delete();
        }
        return compact('snapshot', 'counts');
    }
}
