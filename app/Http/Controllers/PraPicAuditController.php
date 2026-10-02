<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PraPicAuditController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeView();

        $users = DB::connection('sqlsrv')
            ->table('users')
            ->select(['id', 'first_name', 'last_name', 'username'])
            ->whereIn('id', function ($query) {
                $query->selectRaw('TRY_CONVERT(int, created_by)')
                    ->from('pra')
                    ->whereRaw('TRY_CONVERT(int, created_by) IS NOT NULL');
            })
            ->orWhereIn('id', function ($query) {
                $query->selectRaw('TRY_CONVERT(int, created_by)')
                    ->from('pic')
                    ->whereRaw('TRY_CONVERT(int, created_by) IS NOT NULL');
            })
            ->distinct()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function ($user) {
                $first = trim((string) ($user->first_name ?? ''));
                $last = trim((string) ($user->last_name ?? ''));
                $composed = trim($first . ' ' . $last);

                $user->display_name = $composed !== ''
                    ? $composed
                    : trim((string) ($user->username ?? 'User ' . $user->id));

                return $user;
            });

        $defaultEnd = Carbon::now()->format('Y-m-d');
        $defaultStart = Carbon::now()->subDays(30)->format('Y-m-d');

        return view('audit.pra_pic.index', [
            'users' => $users,
            'defaultStart' => $defaultStart,
            'defaultEnd' => $defaultEnd,
        ]);
    }

    public function praUserRecords(Request $request)
    {
        $this->authorizeView();

        [$startDate, $endDate] = $this->extractDateRange($request);
        $userId = $request->input('user_id');

        $query = DB::connection('sqlsrv')
            ->table('pra as p')
            ->join('users as u', function ($join) {
                $join->on('u.id', '=', DB::raw('TRY_CONVERT(int, p.created_by)'));
            })
            ->select([
                'p.id',
                'p.mlsFNo',
                'p.related_file_number',
                'p.transaction_type',
                'p.transaction_date',
                DB::raw("ISNULL(p.serialNo, '0') as serialNo"),
                DB::raw("ISNULL(p.pageNo, '0') as pageNo"),
                DB::raw("ISNULL(p.volumeNo, '0') as volumeNo"),
                'p.grantor',
                'p.grantee',
                'p.created_by',
                'p.created_at',
                'u.id as user_id',
                'u.first_name',
                'u.last_name',
                'u.username',
                'u.email',
                DB::raw('COUNT(*) OVER (PARTITION BY u.id) AS entry_count'),
            ])
            ->whereRaw('TRY_CONVERT(int, p.created_by) IS NOT NULL');

        if ($userId !== null && $userId !== '') {
            $query->where('u.id', (int) $userId);
        }

        if ($startDate) {
            $query->where('p.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('p.created_at', '<=', $endDate);
        }

        $dataTable = DataTables::of($query)
            ->order(function ($builder) {
                $builder->orderBy('p.created_at', 'desc');
            })
            ->editColumn('created_at', function ($row) {
                return $row->created_at
                    ? Carbon::parse($row->created_at)->format('Y-m-d H:i')
                    : null;
            })
            ->addColumn('user_full_name', function ($row) {
                $first = trim((string) ($row->first_name ?? ''));
                $last = trim((string) ($row->last_name ?? ''));
                $composed = trim($first . ' ' . $last);

                if ($composed !== '') {
                    return $composed;
                }

                return trim((string) ($row->username ?? '')) ?: '—';
            })
            ->addColumn('entry_count', function ($row) {
                return isset($row->entry_count) ? (int) $row->entry_count : 0;
            })
            ->filterColumn('user_full_name', function ($builder, $keyword) {
                $builder->where(function ($nested) use ($keyword) {
                    $nested->where('u.first_name', 'like', "%{$keyword}%")
                        ->orWhere('u.last_name', 'like', "%{$keyword}%")
                        ->orWhereRaw("LTRIM(RTRIM(u.first_name + ' ' + COALESCE(u.last_name, ''))) LIKE ?", ["%{$keyword}%"])
                        ->orWhere('u.username', 'like', "%{$keyword}%")
                        ->orWhere('u.email', 'like', "%{$keyword}%");
                });
            })
            ->orderColumn('user_full_name', function ($builder, $direction) {
                $builder->orderBy('u.first_name', $direction)
                    ->orderBy('u.last_name', $direction);
            });

        return $dataTable->toJson();
    }

    public function picUserRecords(Request $request)
    {
        $this->authorizeView();

        [$startDate, $endDate] = $this->extractDateRange($request);
        $userId = $request->input('user_id');

        $query = DB::connection('sqlsrv')
            ->table('pic as p')
            ->join('users as u', function ($join) {
                $join->on('u.id', '=', DB::raw('TRY_CONVERT(int, p.created_by)'));
            })
            ->select([
                'p.id',
                'p.mlsFNo',
                'p.kangisFileNo',
                'p.transaction_type',
                'p.transaction_date',
                DB::raw("ISNULL(p.serialNo, '0') as serialNo"),
                DB::raw("ISNULL(p.pageNo, '0') as pageNo"),
                DB::raw("ISNULL(p.volumeNo, '0') as volumeNo"),
                'p.grantor',
                'p.grantee',
                'p.created_by',
                'p.created_at',
                'u.id as user_id',
                'u.first_name',
                'u.last_name',
                'u.username',
                'u.email',
                DB::raw('COUNT(*) OVER (PARTITION BY u.id) AS entry_count'),
            ])
            ->whereRaw('TRY_CONVERT(int, p.created_by) IS NOT NULL');

        if ($userId !== null && $userId !== '') {
            $query->where('u.id', (int) $userId);
        }

        if ($startDate) {
            $query->where('p.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('p.created_at', '<=', $endDate);
        }

        $dataTable = DataTables::of($query)
            ->order(function ($builder) {
                $builder->orderBy('p.created_at', 'desc');
            })
            ->editColumn('created_at', function ($row) {
                return $row->created_at
                    ? Carbon::parse($row->created_at)->format('Y-m-d H:i')
                    : null;
            })
            ->addColumn('user_full_name', function ($row) {
                $first = trim((string) ($row->first_name ?? ''));
                $last = trim((string) ($row->last_name ?? ''));
                $composed = trim($first . ' ' . $last);

                if ($composed !== '') {
                    return $composed;
                }

                return trim((string) ($row->username ?? '')) ?: '—';
            })
            ->addColumn('entry_count', function ($row) {
                return isset($row->entry_count) ? (int) $row->entry_count : 0;
            })
            ->filterColumn('user_full_name', function ($builder, $keyword) {
                $builder->where(function ($nested) use ($keyword) {
                    $nested->where('u.first_name', 'like', "%{$keyword}%")
                        ->orWhere('u.last_name', 'like', "%{$keyword}%")
                        ->orWhereRaw("LTRIM(RTRIM(u.first_name + ' ' + COALESCE(u.last_name, ''))) LIKE ?", ["%{$keyword}%"])
                        ->orWhere('u.username', 'like', "%{$keyword}%")
                        ->orWhere('u.email', 'like', "%{$keyword}%");
                });
            })
            ->orderColumn('user_full_name', function ($builder, $direction) {
                $builder->orderBy('u.first_name', $direction)
                    ->orderBy('u.last_name', $direction);
            });

        return $dataTable->toJson();
    }

    public function praStringRecords(Request $request)
    {
        $this->authorizeView();

        [$startDate, $endDate] = $this->extractDateRange($request);

        $query = $this->buildStringAggregateQuery('pra', $startDate, $endDate);

        return DataTables::of($query)
            ->order(function ($builder) {
                $builder->orderBy('total_records', 'desc');
            })
            ->editColumn('first_created_at', function ($row) {
                return $row->first_created_at
                    ? Carbon::parse($row->first_created_at)->format('Y-m-d H:i')
                    : null;
            })
            ->editColumn('last_created_at', function ($row) {
                return $row->last_created_at
                    ? Carbon::parse($row->last_created_at)->format('Y-m-d H:i')
                    : null;
            })
            ->toJson();
    }

    public function picStringRecords(Request $request)
    {
        $this->authorizeView();

        [$startDate, $endDate] = $this->extractDateRange($request);

        $query = $this->buildStringAggregateQuery('pic', $startDate, $endDate);

        return DataTables::of($query)
            ->order(function ($builder) {
                $builder->orderBy('total_records', 'desc');
            })
            ->editColumn('first_created_at', function ($row) {
                return $row->first_created_at
                    ? Carbon::parse($row->first_created_at)->format('Y-m-d H:i')
                    : null;
            })
            ->editColumn('last_created_at', function ($row) {
                return $row->last_created_at
                    ? Carbon::parse($row->last_created_at)->format('Y-m-d H:i')
                    : null;
            })
            ->toJson();
    }

    public function stats(Request $request)
    {
        $this->authorizeView();

        [$startDate, $endDate] = $this->extractDateRange($request);
        $userId = $request->input('user_id');

        $praUserQuery = DB::connection('sqlsrv')
            ->table('pra as p')
            ->join('users as u', function ($join) {
                $join->on('u.id', '=', DB::raw('TRY_CONVERT(int, p.created_by)'));
            })
            ->whereRaw('TRY_CONVERT(int, p.created_by) IS NOT NULL');

        if ($userId !== null && $userId !== '') {
            $praUserQuery->where('u.id', (int) $userId);
        }

        if ($startDate) {
            $praUserQuery->where('p.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $praUserQuery->where('p.created_at', '<=', $endDate);
        }

        $praUserTotal = (clone $praUserQuery)->count();
        $praUserDistinct = (clone $praUserQuery)->distinct('u.id')->count('u.id');

        $picUserQuery = DB::connection('sqlsrv')
            ->table('pic as p')
            ->join('users as u', function ($join) {
                $join->on('u.id', '=', DB::raw('TRY_CONVERT(int, p.created_by)'));
            })
            ->whereRaw('TRY_CONVERT(int, p.created_by) IS NOT NULL');

        if ($userId !== null && $userId !== '') {
            $picUserQuery->where('u.id', (int) $userId);
        }

        if ($startDate) {
            $picUserQuery->where('p.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $picUserQuery->where('p.created_at', '<=', $endDate);
        }

        $picUserTotal = (clone $picUserQuery)->count();
        $picUserDistinct = (clone $picUserQuery)->distinct('u.id')->count('u.id');

        $praStringQuery = $this->buildStringAggregateQuery('pra', $startDate, $endDate);
        $praStringCollection = collect((clone $praStringQuery)->get());
        $praStringRecords = (int) $praStringCollection->sum(function ($row) {
            return (int) ($row->total_records ?? 0);
        });
        $praStringLabels = $praStringCollection->count();

        $picStringQuery = $this->buildStringAggregateQuery('pic', $startDate, $endDate);
        $picStringCollection = collect((clone $picStringQuery)->get());
        $picStringRecords = (int) $picStringCollection->sum(function ($row) {
            return (int) ($row->total_records ?? 0);
        });
        $picStringLabels = $picStringCollection->count();

        return response()->json([
            'pra_user' => [
                'total_records' => (int) $praUserTotal,
                'distinct_users' => (int) $praUserDistinct,
            ],
            'pic_user' => [
                'total_records' => (int) $picUserTotal,
                'distinct_users' => (int) $picUserDistinct,
            ],
            'pra_strings' => [
                'labels' => (int) $praStringLabels,
                'total_records' => $praStringRecords,
            ],
            'pic_strings' => [
                'labels' => (int) $picStringLabels,
                'total_records' => $picStringRecords,
            ],
        ]);
    }

    private function buildStringAggregateQuery(string $table, ?Carbon $start, ?Carbon $end)
    {
        $connection = DB::connection('sqlsrv');

        if (!$start && !$end) {
            return $connection->table("{$table}_created_by_strings")
                ->select([
                    'created_by_label',
                    'total_records',
                    'first_created_at',
                    'last_created_at',
                ]);
        }

        $query = $connection
            ->table($table)
            ->selectRaw("COALESCE(NULLIF(LTRIM(RTRIM(created_by)), ''), 'Unattributed') AS created_by_label")
            ->selectRaw('COUNT_BIG(*) AS total_records')
            ->selectRaw('MIN(created_at) AS first_created_at')
            ->selectRaw('MAX(created_at) AS last_created_at')
            ->whereRaw('TRY_CONVERT(int, created_by) IS NULL');

        if ($start) {
            $query->whereNotNull('created_at')
                ->where('created_at', '>=', $start);
        }

        if ($end) {
            $query->whereNotNull('created_at')
                ->where('created_at', '<=', $end);
        }

        return $query->groupByRaw("COALESCE(NULLIF(LTRIM(RTRIM(created_by)), ''), 'Unattributed')");
    }

    private function extractDateRange(Request $request): array
    {
        $start = $this->parseDate($request->input('start_date'), false);
        $end = $this->parseDate($request->input('end_date'), true);

        return [$start, $end];
    }

    private function parseDate(?string $value, bool $endOfDay): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            $date = Carbon::parse($value);
            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable $exception) {
            report($exception);
            return null;
        }
    }

    private function authorizeView(): void
    {
        if (!Auth::check()) {
            abort(401);
        }
    }
}
