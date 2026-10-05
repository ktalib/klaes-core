<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardApplicationAnalytics
{
    public function get(string $period = 'week', string $filter = 'all'): array
    {
        $days = ['week' => 7, 'month' => 30, 'quarter' => 90, 'year' => 365][$period] ?? 7;
        $end = Carbon::now('Africa/Lagos')->endOfDay();
        $start = $end->copy()->startOfDay()->subDays($days - 1);
        $buckets = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            $buckets[$date] = ['date' => $date, 'day' => $days === 7 ? $day->format('D') : $day->format('j M'),
                'sectional' => 0, 'recertification' => 0, 'allocation' => 0, 'count' => 0, 'approved' => 0];
        }
        $db = DB::connection('sqlsrv');
        $dateExpression = $db->getDriverName() === 'sqlsrv' ? 'CAST(created_at AS date)' : 'DATE(created_at)';
        foreach (['mother_applications' => ['sectional', 'application_status'],
            'subapplications' => ['recertification', 'application_status'], 'oss_applications' => ['allocation', 'status']] as $table => [$series, $status]) {
            $rows = $db->table($table)->whereBetween('created_at', [$start, $end])
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->selectRaw("{$dateExpression} AS application_date, {$status} AS application_status, COUNT(*) AS count")
                ->groupByRaw("{$dateExpression}, {$status}")->get();
            foreach ($rows as $row) {
                $state = strtolower(trim((string) ($row->application_status ?? 'pending')));
                if ($filter !== 'all' && $state !== $filter) continue;
                $date = substr((string) $row->application_date, 0, 10);
                if (!isset($buckets[$date])) continue;
                $count = (int) $row->count;
                $buckets[$date][$series] += $count;
                $buckets[$date]['count'] += $count;
                if ($state === 'approved') $buckets[$date]['approved'] += $count;
            }
        }
        return array_values($buckets);
    }
}
