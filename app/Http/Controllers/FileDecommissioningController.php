<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\FileNumber;
use Carbon\Carbon;

class FileDecommissioningController extends Controller
{
    /**
     * The file-number columns each listing carries. The two tables select the same
     * three numbers under different names (the main list aliases the archive columns
     * to the fileNumber ones so the union lines up), and both the temp-number lookup
     * and its per-row key are built from them.
     */
    private const ARCHIVE_NUMBER_COLUMNS = ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo'];
    private const FALSE_NUMBER_COLUMNS = ['mls_file_no', 'kangis_file_no', 'new_kangis_file_no'];

    /** One current listing row per retired file; the complete archive remains untouched. */
    private function archiveListingQuery()
    {
        $conn = \Illuminate\Support\Facades\DB::connection('sqlsrv');
        // Do not group by PropID or successor: distinct backfiles can share both.
        // Unidentified rows keep their own ID instead of collapsing all blanks.
        $identity = "COALESCE(NULLIF(NULLIF(UPPER(LTRIM(RTRIM(mls_file_no))), ''), 'N/A'),"
            . " NULLIF(NULLIF(UPPER(LTRIM(RTRIM(kangis_file_no))), ''), 'N/A'),"
            . " NULLIF(NULLIF(UPPER(LTRIM(RTRIM(file_no))), ''), 'N/A'),"
            . " CONCAT('__archive_id__', id))";
        $ranked = $conn->table('decommissioned_files')
            ->where(function ($q) {
                $q->where('false_decommissioning', '<>', \App\Support\DecommissionScope::FALSE_DECOMMISSIONING)
                    ->orWhereNull('false_decommissioning');
            })
            ->select('decommissioned_files.*')
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY {$identity} ORDER BY decommissioning_date DESC, id DESC) AS archive_rank");

        return $conn->query()->fromSub($ranked, 'decommissioned_files')->where('archive_rank', 1);
    }

    /**
     * Display the file decommissioning page
     */
    public function index()
    {
        $totalActiveFiles = FileNumber::active()
            ->where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->count();

        $decommissionedFromFileNumber = $this->excludeArchivedDuplicates(
                FileNumber::decommissioned()
                    ->where(function($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
            )
            ->count();

        $decommissionedFromArchive = $this->archiveListingQuery()
            ->count();

        $totalDecommissionedFiles = $decommissionedFromFileNumber + $decommissionedFromArchive;

        return view('file_decommissioning.index', compact('totalActiveFiles', 'totalDecommissionedFiles'));
    }

    /**
     * Display the decommissioned files list page
     */
    public function decommissionedIndex()
    {
        $conn = \Illuminate\Support\Facades\DB::connection('sqlsrv');

        // Both sources the list itself unions, with the fileNumber rows that are also
        // archived left out so the cards agree with the table's row count.
        $fromFileNumber = $this->excludeArchivedDuplicates(
            FileNumber::decommissioned()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
        );

        $fromArchive = $this->archiveListingQuery();

        $totalDecommissionedFiles = (clone $fromFileNumber)->count() + (clone $fromArchive)->count();

        $recentDecommissioned = (clone $fromFileNumber)
                ->where('decommissioning_date', '>=', now()->subDays(30))
                ->count()
            + (clone $fromArchive)
                ->where('decommissioning_date', '>=', now()->subDays(30))
                ->count();

        return view('file_decommissioning.decommissioned_list', compact('totalDecommissionedFiles', 'recentDecommissioned'));
    }

    /**
     * Get active files data for DataTables
     */
    public function getActiveFilesData(Request $request)
    {
        try {
            $draw = $request->input('draw');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value', '');

            // Base query for active files
            $baseQuery = FileNumber::active()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });

            // Get total count
            $totalRecords = $baseQuery->count();

            // Apply search if provided
            if (!empty($searchValue)) {
                $baseQuery->where(function($query) use ($searchValue) {
                    $query->where('kangisFileNo', 'like', "%{$searchValue}%")
                          ->orWhere('NewKANGISFileNo', 'like', "%{$searchValue}%")
                          ->orWhere('FileName', 'like', "%{$searchValue}%")
                          ->orWhere('mlsfNo', 'like', "%{$searchValue}%");
                });
            }

            // Get filtered count
            $filteredRecords = $baseQuery->count();

            // Get the actual data with ordering and pagination
            $data = $baseQuery->select([
                    'id',
                    'kangisFileNo',
                    'mlsfNo',
                    'NewKANGISFileNo', 
                    'FileName',
                    'type',
                    'created_at'
                ])
                ->orderBy('id', 'desc')
                ->skip($start)
                ->take($length)
                ->get();

            // Format the data
            $formattedData = $data->map(function($row) {
                return [
                    'id' => $row->id,
                    'mlsfNo' => trim($row->mlsfNo ?? '') ?: 'N/A',
                    'kangisFileNo' => trim($row->kangisFileNo ?? '') ?: 'N/A',
                    'NewKANGISFileNo' => trim($row->NewKANGISFileNo ?? '') ?: 'N/A',
                    'FileName' => trim($row->FileName ?? '') ?: 'N/A',
                    'type' => trim($row->type ?? '') ?: 'N/A',
                    'commissioning_date' => 'N/A', // Not available in current structure
                    'created_at' => $row->created_at ? $row->created_at : 'N/A',
                    'action' => '<div class="flex justify-center space-x-2">
                        <button onclick="decommissionFile(' . $row->id . ')" 
                                class="bg-red-600 hover:bg-red-700 text-white text-sm px-3 py-1 rounded" title="Decommission File">
                            <i data-lucide="archive" class="w-4 h-4 inline mr-1"></i>Decommission
                        </button>
                    </div>'
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $formattedData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in FileDecommissioningController getActiveFilesData: ' . $e->getMessage());
            
            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error loading data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get decommissioned files data for DataTables
     */
    public function getDecommissionedFilesData(Request $request)
    {
        try {
            $draw = $request->input('draw');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value', '');

            $conn = \Illuminate\Support\Facades\DB::connection('sqlsrv');

            // The archive table records the successor file(s) that replaced a decommissioned
            // file directly (populated by PlotWorkflowService at decommissioning time), which
            // is more reliable than the related_file_number staging table used below — that
            // table depends on a separate sync and can lag or miss a row entirely.
            $hasSuccessorColumn = \Illuminate\Support\Facades\Schema::connection('sqlsrv')
                ->hasColumn('decommissioned_files', 'successor_file_no');

            // Query 1: From the decommissioned_files archive table (hard-deleted via PlotWorkflowService).
            // Exclude "false decommissioning" rows (Title Status updates from File Indexing) —
            // those are surfaced in a separate table.
            $archiveQuery = $this->archiveListingQuery()
                ->select([
                    'id',
                    'mls_file_no as mlsfNo',
                    'kangis_file_no as kangisFileNo',
                    'new_kangis_file_no as NewKANGISFileNo',
                    'file_name as FileName',
                    'commissioning_date',
                    'decommissioning_date',
                    'decommissioning_reason',
                    'decommissioned_by',
                    'created_at',
                    \Illuminate\Support\Facades\DB::raw($hasSuccessorColumn ? 'successor_file_no' : "NULL as successor_file_no"),
                    \Illuminate\Support\Facades\DB::raw("'archive' as _source"),
                ]);

            // Query 2: From the fileNumber table (soft-decommissioned via is_decommissioned flag).
            // A file that was archived AND left flagged on fileNumber exists on both sides of the
            // union; skip the fileNumber copy so it is listed once. The archive row is the better
            // of the two — it carries the real decommissioning_date and the actor's name, where
            // fileNumber only has a null date and a raw user id.
            $fileNumberQuery = $conn->table('fileNumber')
                ->where('is_decommissioned', 1)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->tap(function ($q) {
                    $this->excludeArchivedDuplicates($q);
                })
                ->select([
                    'id',
                    'mlsfNo',
                    'kangisFileNo',
                    'NewKANGISFileNo',
                    'FileName',
                    'commissioning_date',
                    'decommissioning_date',
                    // fileNumber.decommissioning_reason is the legacy non-Unicode TEXT type;
                    // decommissioned_files.decommissioning_reason (the other UNION ALL side) is
                    // NVARCHAR(MAX). Left uncast, SQL Server's implicit conversion between the two
                    // mangles non-ASCII characters (e.g. the "→" in "Manual Linkage: ... → FILE-NO"
                    // silently becomes "?"), so the successor file number can no longer be parsed
                    // back out of it. Cast explicitly to keep both sides Unicode.
                    \Illuminate\Support\Facades\DB::raw('CAST(decommissioning_reason AS NVARCHAR(MAX)) as decommissioning_reason'),
                    \Illuminate\Support\Facades\DB::raw("COALESCE(updated_by, created_by, 'System') as decommissioned_by"),
                    'created_at',
                    \Illuminate\Support\Facades\DB::raw("NULL as successor_file_no"),
                    \Illuminate\Support\Facades\DB::raw("'filenumber' as _source"),
                ]);

            // UNION both sources
            $unionQuery = $archiveQuery->unionAll($fileNumberQuery);

            // Wrap in a subquery for counting/searching/pagination.
            // The union's bindings must live in the "from" slot, not be merged type-by-type:
            // mergeBindings() would drop the second SELECT's values into the outer query's
            // "union" bucket, which Laravel flattens *after* the outer "where" bucket. Any
            // search term added below would then bind to fileNumber.is_decommissioned (a bit
            // column) and blow up with "Conversion failed ... '%44%' to data type bit".
            $baseQuery = $conn->table(\Illuminate\Support\Facades\DB::raw("({$unionQuery->toSql()}) as combined"))
                ->addBinding($unionQuery->getBindings(), 'from');

            // Total count (before search)
            $totalRecords = $baseQuery->count();

            // Apply search
            if (!empty($searchValue)) {
                $baseQuery->where(function ($query) use ($searchValue) {
                    $query->where('mlsfNo', 'like', "%{$searchValue}%")
                        ->orWhere('kangisFileNo', 'like', "%{$searchValue}%")
                        ->orWhere('NewKANGISFileNo', 'like', "%{$searchValue}%")
                        ->orWhere('FileName', 'like', "%{$searchValue}%")
                        ->orWhere('decommissioning_reason', 'like', "%{$searchValue}%")
                        ->orWhere('decommissioned_by', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $baseQuery->count();

            // Get data with pagination, ordered by decommissioning_date desc
            $data = $baseQuery
                ->orderByDesc('decommissioning_date')
                ->skip($start)
                ->take($length)
                ->get();

            // Resolve PropIDs for the page's rows (decommissioned_files itself has no prop column;
            // the property id survives in the archive/staging tables).
            $propIds = $this->resolvePropIds(
                $data->flatMap(function ($row) {
                    return [trim($row->mlsfNo ?? ''), trim($row->kangisFileNo ?? ''), trim($row->NewKANGISFileNo ?? '')];
                })->filter()->unique()->values()->all()
            );

            // Resolve related file number(s) for the page's rows from the related_file_number
            // staging table (matched by any of the row's file numbers, or by resolved PropID).
            $relatedFiles = $this->resolveRelatedFiles($data, $propIds);

            // Temporary "(T)" counterpart for the page's rows.
            $tempFileNos = $this->resolveTempFileNumbers($data, self::ARCHIVE_NUMBER_COLUMNS);

            // Format for DataTables
            $formattedData = $data->map(function ($row) use ($propIds, $relatedFiles, $tempFileNos) {
                $propId = null;
                foreach ([trim($row->mlsfNo ?? ''), trim($row->kangisFileNo ?? ''), trim($row->NewKANGISFileNo ?? '')] as $n) {
                    if ($n !== '' && isset($propIds[$n])) {
                        $propId = $propIds[$n];
                        break;
                    }
                }

                // Single File No column: list every file number the row carries
                // (MLS, KANGIS, new KANGIS), deduplicated, one per line.
                $fileNos = array_values(array_unique(array_filter([
                    trim($row->mlsfNo ?? ''),
                    trim($row->kangisFileNo ?? ''),
                    trim($row->NewKANGISFileNo ?? ''),
                ], function ($n) {
                    return $n !== '';
                })));

                return [
                    'id' => $row->id,
                    'file_no' => $fileNos ? implode('<br>', array_map('e', $fileNos)) : 'N/A',
                    'temp_file_no' => $this->renderTempFileNo($tempFileNos[$this->tempFileKey($row, self::ARCHIVE_NUMBER_COLUMNS)] ?? null),
                    'related_file' => $this->renderRelatedFile($relatedFiles[$this->relatedFileKey($row)] ?? []),
                    'prop_id' => $propId !== null && trim((string) $propId) !== '' ? trim((string) $propId) : '-',
                    'file_name' => trim($row->FileName ?? '') ?: 'N/A',
                    'commissioning_date' => $row->commissioning_date ? Carbon::parse($row->commissioning_date)->format('d M Y, h:i A') : 'N/A',
                    'decommissioning_date' => $row->decommissioning_date ? Carbon::parse($row->decommissioning_date)->format('d M Y, h:i A') : 'N/A',
                    'decommissioned_by' => trim($row->decommissioned_by ?? '') ?: 'System',
                    'decommissioning_reason' => $this->renderReason($row->decommissioning_reason ?? null),
                    'action' => '<div class="flex justify-center space-x-2">
                        <button onclick="viewDecommissionedFile(' . $row->id . ', \'' . ($row->_source ?? 'archive') . '\')" 
                                class="text-blue-600 hover:text-blue-800 text-sm px-2 py-1 rounded hover:bg-blue-50" title="View Details">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>'
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $formattedData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in FileDecommissioningController getDecommissionedFilesData: ' . $e->getMessage());

            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error loading data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Resolve PropIDs for a set of file numbers. Decommissioned files are hard-deleted from
     * file_indexings, so the prop_id is recovered from the archive/staging tables that
     * decommissioning intentionally preserves, in order of authority.
     *
     * @param  string[] $fileNumbers
     * @return array<string, string> file number => prop_id
     */
    private function resolvePropIds(array $fileNumbers)
    {
        if (empty($fileNumbers)) {
            return [];
        }

        $conn = \Illuminate\Support\Facades\DB::connection('sqlsrv');
        $sources = [
            ['PropID_Master', 'primary_file_number', 'prop_id'],
            ['PropID_Master', 'mlsFNo', 'prop_id'],
            ['PropID_Master', 'kangisFileNo', 'prop_id'],
            ['PropID_Master', 'NewKANGISFileno', 'prop_id'],
            ['deprecated_records', 'file_number', 'prop_id'],
            ['file_history_staging', 'mlsfNo', 'prop_id'],
            ['CofO_staging', 'mlsFNo', 'prop_id'],
            ['CofO_staging', 'kangisFileNo', 'prop_id'],
            ['CofO_staging', 'fileno', 'prop_id'],
            ['pra', 'mlsFNo', 'prop_id'],
            ['pra', 'fileno', 'prop_id'],
            // LAST RESORT, and the order matters. The docblock's "hard-deleted from
            // file_indexings" holds for files retired through PlotWorkflowService, but a file
            // retired by the ST Direct Allocation flow keeps its file_indexings row — KNML 9479
            // still sits there as id 197274 carrying prop_id 68385, while PropID_Master's row
            // for 68385 is registered under the RELATED file (RES-RC-2021-4) and names KNML 9479
            // nowhere. Without this source the screen reported no PropID for a file that has
            // had one all along, which invites minting a second one.
            //
            // Placed after every other source so it can only fill a gap, never overrule a
            // PropID the authoritative tables already answered with: the two disagree on at
            // least one live row (CON-COM-1990-79 — PropID_Master 8134 vs file_indexings 2226),
            // and reordering this line would silently change that row's answer.
            ['related_file_number', 'file_number', 'prop_id'],
            ['related_file_number', 'related_fileno', 'prop_id'],
            ['file_indexings', 'file_number', 'prop_id'],
        ];

        $resolved = [];
        foreach ($sources as [$table, $numberColumn, $propColumn]) {
            $pending = array_values(array_diff($fileNumbers, array_keys($resolved)));
            if (empty($pending)) {
                break;
            }

            try {
                $found = $conn->table($table)
                    ->whereIn($numberColumn, $pending)
                    ->whereNotNull($propColumn)
                    ->where($propColumn, '!=', '')
                    ->pluck($propColumn, $numberColumn);

                foreach ($found as $number => $propId) {
                    $resolved[trim((string) $number)] = trim((string) $propId);
                }
            } catch (\Exception $e) {
                // Table/column missing on this environment — skip the source.
                continue;
            }
        }

        return $resolved;
    }

    /**
     * Stable per-row key used to look up a row's resolved related file number(s).
     */
    private function relatedFileKey($row): string
    {
        return trim($row->mlsfNo ?? '') . '|' . trim($row->kangisFileNo ?? '') . '|' . trim($row->NewKANGISFileNo ?? '');
    }

    /**
     * Resolve the related file number(s) for a page of decommissioned rows.
     *
     * Reads the related_file_number staging table once for the whole page and, for each
     * row, returns the counterpart endpoint(s) of any link that matches one of the row's
     * file numbers or its resolved PropID. Keyed by relatedFileKey($row).
     *
     * @return array<string,string>  key => comma-joined related file numbers ('-' when none)
     */
    private function resolveRelatedFiles($data, array $propIds): array
    {
        $result = [];
        if ($data->isEmpty()) {
            return $result;
        }

        $norm = function ($v) {
            $v = strtoupper(trim((string) $v));
            return preg_replace('/\s+/', ' ', $v);
        };

        // Collect all file numbers + resolved PropIDs across the page for one batched query.
        $allNumbers = $data->flatMap(function ($row) {
            return [trim($row->mlsfNo ?? ''), trim($row->kangisFileNo ?? ''), trim($row->NewKANGISFileNo ?? '')];
        })->filter()->unique()->values()->all();
        $allPropIds = array_values(array_unique(array_map(fn ($v) => trim((string) $v), $propIds)));

        if (empty($allNumbers) && empty($allPropIds)) {
            return $result;
        }

        try {
            $rfnRows = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                ->table('related_file_number')
                ->where(function ($w) use ($allNumbers, $allPropIds) {
                    if (!empty($allNumbers)) {
                        $w->orWhereIn('file_number', $allNumbers)
                          ->orWhereIn('related_fileno', $allNumbers);
                    }
                    if (!empty($allPropIds)) {
                        $w->orWhereIn('prop_id', $allPropIds);
                    }
                })
                ->select('file_number', 'related_fileno', 'prop_id')
                ->get();
        } catch (\Exception $e) {
            // Staging table missing on this environment — fall back to successor_file_no below.
            $rfnRows = collect();
        }

        foreach ($data as $row) {
            // The row's own identifiers (normalized) — used to pick the counterpart side
            // and to avoid echoing the row's own number back.
            $own = [];
            foreach ([$row->mlsfNo ?? '', $row->kangisFileNo ?? '', $row->NewKANGISFileNo ?? ''] as $n) {
                $n = $norm($n);
                if ($n !== '') {
                    $own[$n] = true;
                }
            }

            $rowPropId = null;
            foreach ([trim($row->mlsfNo ?? ''), trim($row->kangisFileNo ?? ''), trim($row->NewKANGISFileNo ?? '')] as $n) {
                if ($n !== '' && isset($propIds[$n]) && trim((string) $propIds[$n]) !== '') {
                    $rowPropId = trim((string) $propIds[$n]);
                    break;
                }
            }

            $related = [];
            foreach ($rfnRows as $r) {
                $fn  = trim((string) $r->file_number);
                $rf  = trim((string) $r->related_fileno);
                $fnN = $norm($fn);
                $rfN = $norm($rf);

                $counterpart = null;
                if ($fnN !== '' && isset($own[$fnN])) {
                    $counterpart = $rf;
                } elseif ($rfN !== '' && isset($own[$rfN])) {
                    $counterpart = $fn;
                } elseif ($rowPropId !== null && trim((string) $r->prop_id) === $rowPropId) {
                    // PropID match: show whichever side isn't the row's own number.
                    $counterpart = (!isset($own[$rfN]) && $rf !== '') ? $rf : $fn;
                }

                if ($counterpart === null) {
                    continue;
                }
                $counterpart = trim((string) $counterpart);
                $cN = $norm($counterpart);
                // Skip blanks, the row's own number, and obvious junk (too short / purely numeric).
                if ($cN === '' || isset($own[$cN]) || strlen($cN) < 3 || ctype_digit($cN)) {
                    continue;
                }
                $related[$cN] = $counterpart;
            }

            // Fallback / supplement: the archive row's own successor_file_no (set directly
            // by PlotWorkflowService at decommissioning time) when related_file_number has
            // no entry for it, or to fill in a successor the staging sync hasn't caught yet.
            // May be a CSV list (a batch subdivision retires the mother into several children).
            foreach (explode(',', (string) ($row->successor_file_no ?? '')) as $succ) {
                $succ = trim($succ);
                if ($succ === '') {
                    continue;
                }
                $sN = $norm($succ);
                if ($sN === '' || isset($own[$sN]) || isset($related[$sN])) {
                    continue;
                }
                $related[$sN] = $succ;
            }

            // Last resort for older rows decommissioned before successor_file_no existed:
            // Manual Linkage writes a reason like "Manual Linkage: Change of Purpose →
            // CON-COM-2026-426" — pull the file number(s) after the arrow.
            if (empty($related) && !empty($row->decommissioning_reason)
                && preg_match('/\x{2192}\s*(.+)$/u', (string) $row->decommissioning_reason, $m)) {
                foreach (explode(',', $m[1]) as $succ) {
                    $succ = trim($succ, " \t\n\r\0\x0B.");
                    if ($succ === '') {
                        continue;
                    }
                    $sN = $norm($succ);
                    if ($sN === '' || isset($own[$sN]) || isset($related[$sN]) || strlen($sN) < 3) {
                        continue;
                    }
                    $related[$sN] = $succ;
                }
            }

            $result[$this->relatedFileKey($row)] = array_values($related);
        }

        return $result;
    }

    /**
     * Temporary "(T)" file number for each row of a page.
     *
     * A temp number and its base are the SAME physical file, filed under different
     * columns, so a decommissioned file's temp counterpart cannot be read off the row
     * itself — TemporaryFileNumberResolver knows every column one can be stored in.
     * Resolved once per page (not per row) so the column costs a fixed handful of
     * indexed lookups regardless of page length.
     *
     * @param  string[] $columns  the row's file-number columns, in priority order
     * @return array<string, string>  tempFileKey() => "(T)" number
     */
    private function resolveTempFileNumbers($rows, array $columns): array
    {
        $numbers = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $number = trim((string) ($row->$column ?? ''));
                if ($number !== '') {
                    $numbers[] = $number;
                }
            }
        }

        if (empty($numbers)) {
            return [];
        }

        try {
            $index = app(\App\Services\TemporaryFileNumberResolver::class)
                ->resolve(array_values(array_unique($numbers)));
        } catch (\Exception $e) {
            // The column is informational — never let it break the listing.
            \Log::warning('Temporary file number lookup failed: ' . $e->getMessage());

            return [];
        }

        $resolved = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $number = trim((string) ($row->$column ?? ''));
                if ($number === '') {
                    continue;
                }

                // The row may already BE filed under the temporary number, in which
                // case there is nothing to look up.
                if (\App\Services\TemporaryFileNumberResolver::isTemp($number)) {
                    $resolved[$this->tempFileKey($row, $columns)] = $number;
                    break;
                }

                $key = \App\Services\TemporaryFileNumberResolver::baseKey($number);
                if (isset($index[$key])) {
                    $resolved[$this->tempFileKey($row, $columns)] = $index[$key]['temp_file_no'];
                    break;
                }
            }
        }

        return $resolved;
    }

    /**
     * Stable per-row key for the resolved temp number, built from the row's own file
     * numbers because neither listing has a single id namespace to key on (the main
     * one unions two tables whose ids collide).
     *
     * @param  string[] $columns
     */
    private function tempFileKey($row, array $columns): string
    {
        $parts = [];
        foreach ($columns as $column) {
            $parts[] = trim((string) ($row->$column ?? ''));
        }

        return implode('|', $parts);
    }

    /**
     * Render the Temporary File No cell. Badged rather than plain text so a "(T)"
     * reads as a marker on the file, not as another file number in its own right.
     */
    private function renderTempFileNo(?string $tempFileNo): string
    {
        $tempFileNo = trim((string) $tempFileNo);

        if ($tempFileNo === '') {
            return '<span class="text-gray-400">-</span>';
        }

        return '<span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700" title="Temporary file number">'
            . e($tempFileNo) . '</span>';
    }

    /**
     * Render the Related File cell: show the first number, and when there are more,
     * a "+N" pill that reveals the rest inline on click (handled in the view).
     */
    private function renderRelatedFile(array $items): string
    {
        if (empty($items)) {
            return '-';
        }

        $first = e($items[0]);
        $rest = array_slice($items, 1);
        if (empty($rest)) {
            return '<span class="text-gray-700">' . $first . '</span>';
        }

        return '<div class="rel-file-cell flex flex-wrap items-center gap-1">'
            . '<span class="text-gray-700">' . $first . '</span>'
            . '<button type="button" class="rel-more inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 hover:bg-blue-200" title="Show all related files">+' . count($rest) . '</button>'
            . '<span class="rel-rest hidden text-gray-700">, ' . e(implode(', ', $rest)) . '</span>'
            . '</div>';
    }

    /**
     * A file that was archived into decommissioned_files AND left flagged on fileNumber
     * (is_decommissioned = 1) exists in both sources this screen reads, and would be listed
     * and counted twice. Drop the fileNumber copy: the archive row is the better of the two,
     * carrying the real decommissioning_date and the actor's name where fileNumber has only
     * a null date and a raw user id.
     *
     * Applies to a query on the fileNumber table (query builder or Eloquent).
     */
    private function excludeArchivedDuplicates($query)
    {
        return $query->whereNotExists(function ($q) {
            $q->select(\Illuminate\Support\Facades\DB::raw(1))
                ->from('decommissioned_files as df')
                // Same false_decommissioning filter the archive side uses: only suppress
                // against archive rows that actually surface on this screen.
                ->where(function ($w) {
                    $w->where('df.false_decommissioning', '<>', \App\Support\DecommissionScope::FALSE_DECOMMISSIONING)->orWhereNull('df.false_decommissioning');
                })
                // EVERY archive number against EVERY fileNumber number, not column against
                // matching column. The same file is routinely recorded under different columns
                // on the two sides: KNML 9479 is decommissioned_files.mls_file_no on the archive
                // row, and fileNumber.kangisFileNo + .NewKANGISFileNo on the indexing row that
                // the ST Direct Allocation left behind. Compared position-for-position those two
                // never meet, so the file was listed twice — once from each source — while a
                // THIRD record for it (the ST Dept PRIMARY row, which does carry the number in
                // mlsfNo) was suppressed correctly. Which column a number landed in says nothing
                // about which file it identifies, so the match cannot depend on it.
                //
                // NULL/'' never matches (NULL = NULL is unknown in SQL), so a number a row does
                // not carry still cannot pair up with a blank on the other side.
                ->whereRaw('(' . implode(' OR ', $this->archiveNumberMatchPairs()) . ')');
        });
    }

    /**
     * The cross product of the archive's file-number columns and fileNumber's, as trimmed,
     * blank-safe SQL equality tests. Nine comparisons; built here rather than written out so
     * adding a numbering scheme to either side cannot leave half the pairs behind.
     *
     * @return array<int,string>
     */
    private function archiveNumberMatchPairs(): array
    {
        $archive = ['df.mls_file_no', 'df.kangis_file_no', 'df.new_kangis_file_no'];
        $fileNumber = ['fileNumber.mlsfNo', 'fileNumber.kangisFileNo', 'fileNumber.NewKANGISFileNo'];

        $pairs = [];
        foreach ($archive as $left) {
            foreach ($fileNumber as $right) {
                $pairs[] = "NULLIF(LTRIM(RTRIM({$left})), '') = NULLIF(LTRIM(RTRIM({$right})), '')";
            }
        }

        return $pairs;
    }

    /**
     * Reason cell: show a short preview, keep the full text on the element so the
     * table's hover tooltip can reveal the rest without widening the column.
     */
    private function renderReason($reason): string
    {
        $reason = trim((string) ($reason ?? ''));
        if ($reason === '') {
            return 'N/A';
        }

        // mb_* so multibyte reasons ("… → CON-COM-2026-426") never get cut mid-character.
        if (mb_strlen($reason) <= 50) {
            return '<span class="reason-cell">' . e($reason) . '</span>';
        }

        return '<span class="reason-cell reason-truncated" data-reason="' . e($reason) . '">'
            . e(mb_substr($reason, 0, 50)) . '…'
            . '</span>';
    }

    /**
     * Get FALSE decommissioned files data for DataTables.
     * These are Title Status updates raised from File Indexing — the file is flagged but
     * NOT actually decommissioned (decommissioned_files.false_decommissioning = 1).
     */
    public function getFalseDecommissionedFilesData(Request $request)
    {
        try {
            $draw = $request->input('draw');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value', '');

            $conn = \Illuminate\Support\Facades\DB::connection('sqlsrv');

            $baseQuery = $conn->table('decommissioned_files')
                ->where('false_decommissioning', 1);

            $totalRecords = (clone $baseQuery)->count();

            if (!empty($searchValue)) {
                $baseQuery->where(function ($query) use ($searchValue) {
                    $query->where('mls_file_no', 'like', "%{$searchValue}%")
                        ->orWhere('kangis_file_no', 'like', "%{$searchValue}%")
                        ->orWhere('new_kangis_file_no', 'like', "%{$searchValue}%")
                        ->orWhere('file_name', 'like', "%{$searchValue}%")
                        ->orWhere('decommissioning_reason', 'like', "%{$searchValue}%")
                        ->orWhere('decommissioned_by', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = (clone $baseQuery)->count();

            $data = $baseQuery
                ->orderByDesc('decommissioning_date')
                ->skip($start)
                ->take($length)
                ->get();

            $tempFileNos = $this->resolveTempFileNumbers($data, self::FALSE_NUMBER_COLUMNS);

            $formattedData = $data->map(function ($row) use ($tempFileNos) {
                return [
                    'id' => $row->id,
                    'mls_file_no' => trim($row->mls_file_no ?? '') ?: 'N/A',
                    'kangis_file_no' => trim($row->kangis_file_no ?? '') ?: 'N/A',
                    'temp_file_no' => $this->renderTempFileNo($tempFileNos[$this->tempFileKey($row, self::FALSE_NUMBER_COLUMNS)] ?? null),
                    'file_name' => trim($row->file_name ?? '') ?: 'N/A',
                    'decommissioning_date' => $row->decommissioning_date ? Carbon::parse($row->decommissioning_date)->format('d M Y, h:i A') : 'N/A',
                    'decommissioned_by' => trim($row->decommissioned_by ?? '') ?: 'System',
                    'decommissioning_reason' => $this->renderReason($row->decommissioning_reason ?? null),
                    'action' => '<div class="flex justify-center space-x-2">
                        <button onclick="viewFalseDecommissionedFile(' . $row->id . ')"
                                class="text-amber-600 hover:text-amber-800 text-sm px-2 py-1 rounded hover:bg-amber-50" title="View Details">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>'
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $formattedData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in FileDecommissioningController getFalseDecommissionedFilesData: ' . $e->getMessage());

            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error loading data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get a FALSE decommissioned file's details from the archive table.
     */
    public function getFalseDecommissionedFileDetails($id)
    {
        try {
            $row = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                ->table('decommissioned_files')
                ->where('id', $id)
                ->where('false_decommissioning', 1)
                ->first();

            if (!$row) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $row->id,
                    'file_number_id' => $row->file_number_id,
                    'mls_file_no' => $row->mls_file_no,
                    'kangis_file_no' => $row->kangis_file_no,
                    'new_kangis_file_no' => $row->new_kangis_file_no,
                    'file_name' => $row->file_name,
                    'commissioning_date' => $row->commissioning_date ? Carbon::parse($row->commissioning_date)->format('d M Y, h:i A') : 'N/A',
                    'decommissioning_date' => $row->decommissioning_date ? Carbon::parse($row->decommissioning_date)->format('d M Y, h:i A') : 'N/A',
                    'decommissioning_reason' => $row->decommissioning_reason,
                    'decommissioned_by' => trim($row->decommissioned_by ?? '') ?: 'System',
                    'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y, h:i A') : 'N/A',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving record details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file details for decommissioning
     */
    public function getFileDetails($id)
    {
        try {
            $file = FileNumber::where('id', $id)
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Check if file is already decommissioned
            if ($file->isDecommissioned()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is already decommissioned'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $file->id,
                    'mlsfNo' => $file->mlsfNo,
                    'kangisFileNo' => $file->kangisFileNo,
                    'NewKANGISFileNo' => $file->NewKANGISFileNo,
                    'FileName' => $file->FileName,
                    'type' => $file->type,
                    'commissioning_date' => null, // Not available in current structure
                    'created_at' => $file->created_at ? $file->created_at : null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Decommission a file
     */
    public function decommissionFile(Request $request)
    {
        // Log the incoming request data for debugging
        \Log::info('Decommission request data:', $request->all());

        $validator = Validator::make($request->all(), [
            'file_id' => 'required|integer',
            'commissioning_date' => 'nullable|date',
            'decommissioning_date' => 'required|date',
            'decommissioning_reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                'errors' => $validator->errors(),
                'request_data' => $request->all() // Include request data for debugging
            ], 422);
        }

        try {
            $file = FileNumber::where('id', $request->file_id)
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Check if file is already decommissioned
            if ($file->isDecommissioned()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is already decommissioned'
                ], 404);
            }

            // Decommission the file using the model method
            $commissioningDate = $request->commissioning_date ? Carbon::parse($request->commissioning_date) : null;
            $decommissioningDate = Carbon::parse($request->decommissioning_date);
            
            $file->decommission(
                $request->decommissioning_reason,
                $decommissioningDate,
                $commissioningDate
            );

            return response()->json([
                'success' => true,
                'message' => 'File decommissioned successfully',
                'data' => [
                    'file_id' => $file->id,
                    'mlsfNo' => $file->mlsfNo,
                    'decommissioning_date' => $request->decommissioning_date,
                    'decommissioning_reason' => $request->decommissioning_reason
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error decommissioning file: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error decommissioning file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get decommissioned file details
     */
    public function getDecommissionedFileDetails($id)
    {
        try {
            $decommissionedFile = FileNumber::decommissioned()
                ->where('id', $id)
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->first();

            if (!$decommissionedFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Decommissioned file record not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $decommissionedFile->id,
                    'file_number_id' => $decommissionedFile->id,
                    'mls_file_no' => $decommissionedFile->mlsfNo,
                    'kangis_file_no' => $decommissionedFile->kangisFileNo,
                    'new_kangis_file_no' => $decommissionedFile->NewKANGISFileNo,
                    'file_name' => $decommissionedFile->FileName,
                    'commissioning_date' => $decommissionedFile->commissioning_date ? Carbon::parse($decommissionedFile->commissioning_date)->format('d M Y, h:i A') : 'N/A',
                    'decommissioning_date' => $decommissionedFile->decommissioning_date ? Carbon::parse($decommissionedFile->decommissioning_date)->format('d M Y, h:i A') : 'N/A',
                    'decommissioning_reason' => $decommissionedFile->decommissioning_reason,
                    'decommissioned_by' => trim($decommissionedFile->updated_by ?? '') ?: (trim($decommissionedFile->created_by ?? '') ?: 'System'),
                    'created_at' => $decommissionedFile->updated_at ? Carbon::parse($decommissionedFile->updated_at)->format('d M Y, h:i A') : ($decommissionedFile->created_at ? Carbon::parse($decommissionedFile->created_at)->format('d M Y, h:i A') : 'N/A')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving decommissioned file details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for files by file number
     */
    public function searchFiles(Request $request)
    {
        $search = $request->get('search', '');
        
        if (empty($search)) {
            return response()->json([]);
        }

        try {
            $files = FileNumber::active()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->where(function($query) use ($search) {
                    $query->where('mlsfNo', 'like', "%{$search}%")
                          ->orWhere('kangisFileNo', 'like', "%{$search}%")
                          ->orWhere('NewKANGISFileNo', 'like', "%{$search}%")
                          ->orWhere('FileName', 'like', "%{$search}%");
                })
                ->select(['id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'FileName'])
                ->limit(20)
                ->get();

            $results = $files->map(function($file) {
                $displayText = '';
                if ($file->mlsfNo) $displayText .= $file->mlsfNo . ' - ';
                if ($file->FileName) $displayText .= $file->FileName;
                
                return [
                    'id' => $file->id,
                    'text' => trim($displayText, ' - '),
                    'mlsfNo' => $file->mlsfNo,
                    'kangisFileNo' => $file->kangisFileNo,
                    'NewKANGISFileNo' => $file->NewKANGISFileNo,
                    'FileName' => $file->FileName
                ];
            });

            return response()->json($results);

        } catch (\Exception $e) {
            \Log::error('Error searching files: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics()
    {
        try {
            $conn = \Illuminate\Support\Facades\DB::connection('sqlsrv');

            $totalFiles = FileNumber::where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })->count();

            $activeFiles = FileNumber::active()
                ->where(function($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->count();

            // Count from both sources (fileNumber rows that are also archived excluded,
            // so the cards match the list)
            $decommissionedFromFileNumber = $this->excludeArchivedDuplicates(
                    FileNumber::decommissioned()
                        ->where(function($q) {
                            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                        })
                )
                ->count();

            $decommissionedFromArchive = $this->archiveListingQuery()
                ->count();

            $decommissionedFiles = $decommissionedFromFileNumber + $decommissionedFromArchive;

            // Count of "false decommissioning" rows (Title Status updates from File Indexing)
            $falseDecommissioned = $conn->table('decommissioned_files')
                ->where('false_decommissioning', 1)
                ->count();

            $recentFromFileNumber = $this->excludeArchivedDuplicates(
                    FileNumber::decommissioned()
                        ->where(function($q) {
                            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                        })
                )
                ->where('decommissioning_date', '>=', now()->subDays(30))
                ->count();

            $recentFromArchive = $this->archiveListingQuery()
                ->where('decommissioning_date', '>=', now()->subDays(30))
                ->count();

            $recentDecommissioned = $recentFromFileNumber + $recentFromArchive;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_files' => $totalFiles + $decommissionedFromArchive,
                    'active_files' => $activeFiles,
                    'decommissioned_files' => $decommissionedFiles,
                    'false_decommissioned' => $falseDecommissioned,
                    'recent_decommissioned' => $recentDecommissioned
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
