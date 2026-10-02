<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Monitoring – Leaderboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #0f172a;
            background: #f8fafc;
        }
        body {
            margin: 0;
            padding: 40px 24px 80px;
        }
        .page {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08);
            padding: 48px 56px;
        }
        h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .meta {
            color: #64748b;
            margin-top: 4px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin: 32px 0 48px;
        }
        .summary-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 20px;
            background: linear-gradient(145deg, #f8fafc, #ffffff);
        }
        .summary-card label {
            font-size: 13px;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .summary-card span {
            margin-top: 8px;
            display: block;
            font-size: 26px;
            font-weight: 600;
            color: #0f172a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        thead {
            background: #f1f5f9;
        }
        th {
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: .08em;
            color: #475569;
            padding: 14px 16px;
            text-align: left;
        }
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        tr:nth-child(even) td {
            background: #f8fafc;
        }
        .status-pill {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .status-pill.green {
            background: #dcfce7;
            color: #15803d;
        }
        .status-pill.yellow {
            background: #fef3c7;
            color: #b45309;
        }
        .status-pill.red {
            background: #fee2e2;
            color: #b91c1c;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    @php
        $summaryItems = collect($payload['summary_cards']['items'] ?? [])->keyBy('key');
        $team = $payload['summary_cards']['team_completion'] ?? null;
        $rows = $payload['leaderboard']['rows'] ?? [];
        $columns = $payload['leaderboard']['columns'] ?? [];
        $dateRange = $payload['meta']['date_range'] ?? [];
    @endphp
    <div class="page">
        <h1>User Activity Monitoring</h1>
        <p class="meta">
            Reporting window:
            {{ \Carbon\Carbon::parse($dateRange['start'] ?? now())->toFormattedDateString() }}
            →
            {{ \Carbon\Carbon::parse($dateRange['end'] ?? now())->toFormattedDateString() }}
        </p>

        <div class="summary-grid">
            <div class="summary-card">
                <label>Total Files Indexed</label>
                <span>{{ number_format($summaryItems['file_indexing']['value'] ?? 0) }}</span>
            </div>
            <div class="summary-card">
                <label>Total Records Entered</label>
                <span>{{ number_format($summaryItems['records_entered']['value'] ?? 0) }}</span>
            </div>
            <div class="summary-card">
                <label>Total Pages Processed</label>
                <span>{{ number_format($summaryItems['pages_processed']['value'] ?? 0) }}</span>
            </div>
            <div class="summary-card">
                <label>Team Completion Rate</label>
                <span>{{ number_format($team['current'] ?? 0, 1) }}%</span>
            </div>
        </div>

        <h2 style="margin-bottom: 12px;">Leaderboard (Top 15)</h2>

        @if(empty($rows))
            <div class="empty-state">
                No users found for this time range.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Department</th>
                            <th>Work Station</th>
                            @foreach($columns as $column)
                                <th>{{ $column['label'] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $column['key'] ?? '')) }}</th>
                            @endforeach
                            <th>Total</th>
                            <th>Target</th>
                            <th>% Complete</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php
                                $status = $row['status'] ?? 'behind';
                                $statusClass = match($status) {
                                    'exceeded' => 'green',
                                    'on_track' => 'yellow',
                                    default => 'red',
                                };
                            @endphp
                            <tr>
                                <td>{{ $row['user']['name'] }}</td>
                                <td>{{ $row['user']['department'] ?? '—' }}</td>
                                <td>{{ $row['user']['work_station'] ?? '—' }}</td>
                                @foreach($columns as $column)
                                    <td>{{ number_format(data_get($row, 'metrics.' . $column['key'], 0)) }}</td>
                                @endforeach
                                <td>{{ number_format($row['total'] ?? 0) }}</td>
                                <td>{{ number_format($row['target'] ?? 0) }}</td>
                                <td>{{ number_format($row['percent_complete'] ?? 0, 1) }}%</td>
                                <td>
                                    <span class="status-pill {{ $statusClass }}">
                                        {{ $status === 'exceeded' ? 'Exceeded' : ($status === 'on_track' ? 'On Track' : 'Behind') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</body>
</html>
