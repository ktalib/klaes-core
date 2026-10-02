<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PropIdMasterController extends Controller
{
    /**
     * Tables whose rows can be dropped from a prop_id group and re-allocated a
     * fresh prop_id. Mirrors LegalSearchService::VALID_TABLES (all carry prop_id).
     */
    private const REALLOCATABLE_TABLES = [
        'file_history_staging',
        'CofO_staging',
        'pra',
        'deed_registrations',
    ];

    /**
     * Drop selected records from their current prop_id group and allocate a brand
     * NEW prop_id to EACH record (one fresh prop_id per record). The new prop_id is
     * reserved in PropID_Master with a synthetic, guaranteed-unique
     * primary_file_number so the number can never be reused, while the record's real
     * file numbers are left untouched (they still resolve to their former group's
     * master row — we do not "steal" identifiers here).
     *
     * Replaces the old "orphan (prop_id = null)" drop behaviour for Legal Search.
     */
    public function dropReallocate(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        try {
            $result = $this->allocateNewForRecords(
                $request->input('table'),
                $request->input('ids')
            );

            return response()->json([
                'success' => true,
                'message' => "{$result['affected']} record(s) dropped; a new prop_id was allocated to each.",
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Allocate a brand-new prop_id to each of the given records and detach them from
     * their old group. Returns ['affected' => int, 'prop_ids' => [recordId => propId]].
     */
    public function allocateNewForRecords(string $table, array $ids): array
    {
        if (!in_array($table, self::REALLOCATABLE_TABLES, true)) {
            throw new \InvalidArgumentException("Invalid table: {$table}");
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
        if (empty($ids)) {
            return ['affected' => 0, 'prop_ids' => []];
        }

        $connection = DB::connection('sqlsrv');
        $masterExists = Schema::connection('sqlsrv')->hasTable('PropID_Master');
        $masterHasSourceCols = $masterExists
            && Schema::connection('sqlsrv')->hasColumn('PropID_Master', 'source_table');

        return $connection->transaction(function () use ($connection, $table, $ids, $masterExists, $masterHasSourceCols) {
            // Authoritative sequence lives in PropID_Master; hold the top of it for the
            // duration so concurrent drops cannot hand out the same number.
            $next = $masterExists
                ? ((int) $connection->table('PropID_Master')
                    ->lockForUpdate()
                    ->where('prop_id', '<', 2147483647)
                    ->max('prop_id')) + 1
                : $this->nextPropIdFromStaging($connection) + 1;

            $now = now();
            $assigned = [];

            foreach ($ids as $id) {
                $record = $connection->table($table)->where('id', $id)->first();
                if (!$record) {
                    continue;
                }

                $newPropId = $next++;

                $connection->table($table)->where('id', $id)->update([
                    'prop_id' => $newPropId,
                    'updated_at' => $now,
                ]);

                if ($masterExists) {
                    // Reserve the number. Synthetic primary_file_number keeps the unique
                    // norm index happy; identifier columns stay NULL so we never collide
                    // with (or steal from) the record's former group.
                    $hint = $this->firstFileNo($record);
                    $syntheticPrimary = mb_substr(($hint !== '' ? $hint : 'DROP') . '-DROP-' . $newPropId, 0, 90);

                    $row = [
                        'prop_id' => $newPropId,
                        'primary_file_number' => $syntheticPrimary,
                        'status' => 'reallocated',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    if ($masterHasSourceCols) {
                        $row['source_table'] = $table;
                        $row['source_record_id'] = $id;
                    }
                    $connection->table('PropID_Master')->insert($row);
                }

                $assigned[$id] = $newPropId;
            }

            return ['affected' => count($assigned), 'prop_ids' => $assigned];
        });
    }

    /**
     * Next prop_id fallback when PropID_Master is unavailable — max across the
     * staging tables that carry prop_id.
     */
    private function nextPropIdFromStaging($connection): int
    {
        $max = 0;
        foreach (self::REALLOCATABLE_TABLES as $table) {
            try {
                $max = max($max, (int) $connection->table($table)->max('prop_id'));
            } catch (\Throwable $e) {
                // table/column missing — skip
            }
        }
        return $max;
    }

    /**
     * First usable file number on a dropped record, across the varied column names
     * the staging tables use.
     */
    private function firstFileNo($record): string
    {
        foreach (['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno', 'np_fileno', 'temp_fileno'] as $col) {
            $val = trim((string) ($record->$col ?? ''));
            if ($val !== '' && $val !== '-') {
                return $val;
            }
        }
        return '';
    }

    public function index(Request $request)
    {
        $connection = DB::connection('sqlsrv');
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 25);

        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 25;
        }

        $columnSets = [
            'file_history_staging' => ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
            'pra' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
            'pic' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
            'CofO_staging' => ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'np_fileno', 'temp_fileno'],
        ];

        $availableColumns = [];
        foreach ($columnSets as $table => $columns) {
            $availableColumns[$table] = $this->resolveExistingColumns($table, $columns);
        }

        $query = $connection
            ->table('PropID_Master as pm')
            ->select([
                'pm.id',
                'pm.prop_id',
                'pm.primary_file_number',
                'pm.mlsFNo',
                'pm.kangisFileNo',
                'pm.NewKANGISFileno',
                'pm.temp_fileno',
                'pm.source_table',
                'pm.status',
                'pm.created_at',
                'pm.updated_at',
            ])
            ->selectRaw($this->linkCountSubquery(
                'file_history_staging',
                'fh',
                'file_history_links',
                $availableColumns['file_history_staging']
            ))
            ->selectRaw($this->linkCountSubquery(
                'pra',
                'pra',
                'pra_links',
                $availableColumns['pra']
            ))
            ->selectRaw($this->linkCountSubquery(
                'pic',
                'pic',
                'pic_links',
                $availableColumns['pic']
            ))
            ->selectRaw($this->linkCountSubquery(
                'CofO_staging',
                'co',
                'cofo_links',
                $availableColumns['CofO_staging']
            ));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                if (is_numeric($search)) {
                    $builder->orWhere('pm.prop_id', (int) $search);
                }

                $like = '%' . $search . '%';

                $builder->orWhere('pm.primary_file_number', 'LIKE', $like)
                    ->orWhere('pm.mlsFNo', 'LIKE', $like)
                    ->orWhere('pm.kangisFileNo', 'LIKE', $like)
                    ->orWhere('pm.NewKANGISFileno', 'LIKE', $like)
                    ->orWhere('pm.temp_fileno', 'LIKE', $like)
                    ->orWhere('pm.source_table', 'LIKE', $like);
            });
        }

        $records = $query
            ->orderByDesc('pm.updated_at')
            ->orderByDesc('pm.created_at')
            ->paginate($perPage)
            ->withQueryString();

        try {
            $conflicts = (int) $connection->table(DB::raw('vw_prop_id_conflicts'))->count();
        } catch (\Throwable $exception) {
            $conflicts = 0;
        }

        $metrics = [
            'total_master' => (int) $connection->table('PropID_Master')->count(),
            'with_official_file' => (int) $connection->table('PropID_Master')
                ->where(function ($builder) {
                    $builder->whereNotNull('primary_file_number')
                        ->orWhereNotNull('mlsFNo')
                        ->orWhereNotNull('kangisFileNo')
                        ->orWhereNotNull('NewKANGISFileno');
                })
                ->count(),
            'temp_only' => (int) $connection->table('PropID_Master')
                ->whereNull('primary_file_number')
                ->whereNull('mlsFNo')
                ->whereNull('kangisFileNo')
                ->whereNull('NewKANGISFileno')
                ->count(),
            'conflicts' => $conflicts,
        ];

        return view('propid.master_dashboard', [
            'records' => $records,
            'search' => $search,
            'perPage' => $perPage,
            'metrics' => $metrics,
            'PageTitle' => 'PropID Master Dashboard',
        ]);
    }

    /**
     * Build a COUNT(*) subquery that links a downstream table back to the master file number.
     */
    private function linkCountSubquery(string $table, string $alias, string $selectAlias, array $columns): string
    {
        $propMatch = "{$alias}.prop_id = pm.prop_id";
        $normalized = $this->buildNormalizedExpression($alias, $columns);

        if ($normalized === null) {
            return "(SELECT COUNT(*) FROM {$table} {$alias} WHERE {$propMatch}) AS {$selectAlias}";
        }

        return "(SELECT COUNT(*) FROM {$table} {$alias} WHERE {$propMatch} OR {$normalized} = pm.primary_file_number_norm) AS {$selectAlias}";
    }

    private function buildNormalizedExpression(string $alias, array $columns): ?string
    {
        if (empty($columns)) {
            return null;
        }

        $qualified = array_map(fn ($column) => "{$alias}." . $column, $columns);

        $valueExpression = count($qualified) === 1
            ? $qualified[0]
            : 'COALESCE(' . implode(', ', $qualified) . ')';

        return "UPPER(LTRIM(RTRIM({$valueExpression})))";
    }

    private function resolveExistingColumns(string $table, array $columns): array
    {
        return array_values(array_filter($columns, function ($column) use ($table) {
            try {
                return Schema::connection('sqlsrv')->hasColumn($table, $column);
            } catch (\Throwable $exception) {
                return false;
            }
        }));
    }
}
