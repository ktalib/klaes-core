<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class GroupingDashboardController extends Controller
{
    /**
     * Display a minimal grouping dashboard shell.
     */
    public function index()
    {
        return view('grouping.dashboard');
    }

    /**
     * Provide server-side dataset for the grouping analytics DataTable.
     */
    public function data(Request $request)
    {
        $selectColumns = [
            'id',
            'awaiting_fileno',
            'mls_fileno',
            'tracking_id',
        ];

        $hasMappingColumn = $this->columnExists('grouping', 'mapping');
        $hasIndexingMappingColumn = $this->columnExists('grouping', 'indexing_mapping');

        $selectColumns[] = $hasMappingColumn
            ? 'mapping'
            : DB::raw('CAST(0 AS tinyint) as mapping');

        $selectColumns[] = $hasIndexingMappingColumn
            ? 'indexing_mapping'
            : DB::raw('CAST(0 AS tinyint) as indexing_mapping');

        if (! $hasMappingColumn) {
            $this->disableColumnFiltering($request, 'mapping');
        }

        if (! $hasIndexingMappingColumn) {
            $this->disableColumnFiltering($request, 'indexing_mapping');
        }

        $selectColumns = array_merge($selectColumns, [
            DB::raw('[group] as group_no'),
            'sys_batch_no',
            'mdc_batch_no',
            'registry',
            'shelf_rack',
            'indexed_by',
            'landuse',
            'year',
            'created_at',
        ]);

        $query = DB::connection('sqlsrv')
            ->table('grouping')
            ->select($selectColumns)
            ->where('id', '>=', 102)
            ->when($request->filled('mdc_batch_no'), function ($builder) use ($request) {
                $builder->where('mdc_batch_no', $request->input('mdc_batch_no'));
            })
            ->when($request->filled('sys_batch_no'), function ($builder) use ($request) {
                $builder->where('sys_batch_no', $request->input('sys_batch_no'));
            });

        $hasFilters = $this->hasActiveFilters($request);

        $dataTable = DataTables::of($query)
            ->editColumn('created_at', function ($row) {
                return $row->created_at
                    ? Carbon::parse($row->created_at)->format('Y-m-d H:i')
                    : null;
            });

        if (! $hasMappingColumn) {
            $dataTable->filterColumn('mapping', function ($query, $keyword) {
                // Column absent in some schemas; ignore search terms.
            });

            $dataTable->orderColumn('mapping', function ($query, $order) {
                $query->orderBy('id', $order);
            });
        }

        if (! $hasIndexingMappingColumn) {
            $dataTable->filterColumn('indexing_mapping', function ($query, $keyword) {
                // Column absent in some schemas; ignore search terms.
            });

            $dataTable->orderColumn('indexing_mapping', function ($query, $order) {
                $query->orderBy('id', $order);
            });
        }

        if (! $hasFilters) {
            $totalRows = $this->getGroupingRowCount();
            $dataTable->setTotalRecords($totalRows);
            $dataTable->setFilteredRecords($totalRows);
        }

        return $dataTable->toJson();
    }

    protected function hasActiveFilters(Request $request): bool
    {
        return $request->filled('search.value')
            || $request->filled('mdc_batch_no')
            || $request->filled('sys_batch_no');
    }

    protected function getGroupingRowCount(): int
    {
        return Cache::remember('grouping.table_row_count', now()->addMinutes(10), function () {
            try {
                $result = DB::connection('sqlsrv')->selectOne(
                    "SELECT CONVERT(bigint, IDENT_CURRENT('[dbo].[grouping]')) AS total_rows"
                );

                $identityEstimate = (int) ($result->total_rows ?? 0);
                if ($identityEstimate > 0) {
                    return $identityEstimate;
                }
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                return (int) DB::connection('sqlsrv')
                    ->table('grouping')
                    ->max('id');
            } catch (Throwable $exception) {
                report(exception: $exception);
            }

            return 0;
        });
    }

    protected function columnExists(string $table, string $column): bool
    {
        $cacheKey = sprintf('schema.sqlsrv.%s.%s', $table, $column);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($table, $column) {
            $result = DB::connection('sqlsrv')->selectOne(
                <<<'SQL'
SELECT TOP 1 1 AS exists_flag
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ? AND COLUMN_NAME = ?
SQL,
                [$table, $column]
            );

            return $result !== null;
        });
    }

    protected function disableColumnFiltering(Request $request, string $column): void
    {
        $columns = $request->input('columns', []);
        $updated = false;

        foreach ($columns as $index => $definition) {
            $data = $definition['data'] ?? null;
            $name = $definition['name'] ?? null;

            if ($data === $column || $name === $column) {
                $columns[$index]['searchable'] = 'false';
                $columns[$index]['orderable'] = 'false';
                $updated = true;
            }
        }

        if ($updated) {
            $request->merge(['columns' => $columns]);
        }
    }
 
}
