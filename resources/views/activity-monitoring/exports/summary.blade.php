<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Inter', DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        h2 { font-size: 16px; margin: 16px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background-color: #f3f4f6; font-weight: 600; font-size: 11px; }
        .muted { color: #6b7280; font-size: 11px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 9999px; font-size: 10px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
    </style>
</head>
@php
    $range = data_get($filters ?? [], 'start') && data_get($filters ?? [], 'end')
        ? sprintf('%s → %s', data_get($filters, 'start'), data_get($filters, 'end'))
        : 'Current selection';
    $cards = data_get($payload, 'summary_cards.items', []);
    $rows = array_slice(data_get($payload, 'leaderboard.rows', []), 0, 15);
    $columns = data_get($payload, 'leaderboard.columns', []);
@endphp
<body>
    <h1>User Activity Monitoring</h1>
    <p class="muted">Reporting window: {{ $range }}</p>

    <h2>Summary</h2>
    <table>
        <thead>
            <tr>
                @foreach($cards as $card)
                    <th>{{ data_get($card, 'label') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach($cards as $card)
                    <td>{{ number_format(data_get($card, 'value', 0)) }} {{ data_get($card, 'suffix') }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    <h2>Leaderboard (Top 15)</h2>
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Department</th>
                <th>Work Station</th>
                @foreach($columns as $column)
                    <th>{{ data_get($column, 'label', \Illuminate\Support\Str::title(str_replace('_', ' ', data_get($column, 'key', '')))) }}</th>
                @endforeach
                <th>Total</th>
                <th>Target</th>
                <th>% Complete</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ data_get($row, 'user.name') }}</td>
                    <td>{{ data_get($row, 'user.department', '—') }}</td>
                    <td>{{ data_get($row, 'user.work_station', '—') }}</td>
                    @foreach($columns as $column)
                        <td>{{ number_format(data_get($row, 'metrics.' . data_get($column, 'key'), 0)) }}</td>
                    @endforeach
                    <td>{{ number_format(data_get($row, 'total', 0)) }}</td>
                    <td>{{ number_format(data_get($row, 'target', 0)) }}</td>
                    <td>{{ number_format(data_get($row, 'percent_complete', 0), 1) }}%</td>
                    @php
                        $status = data_get($row, 'status', 'pending');
                        $badge = $status === 'exceeded' ? 'badge-success' : ($status === 'on_track' ? 'badge-warning' : 'badge-danger');
                        $label = $status === 'exceeded' ? 'Exceeded' : ($status === 'on_track' ? 'On Track' : 'Behind');
                    @endphp
                    <td><span class="badge {{ $badge }}">{{ $label }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 7 + count($columns) }}">No data available for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
