@include('cadastral_module.partials._flash')


<div class="caveat">
    <i class="fas fa-circle-info"></i>
    <div>
        <strong>Read live from the KLAES file tracker.</strong>
        The Cadastral Module keeps no movements table of its own: <code>file_tracker</code> already
        holds the ministry's 52,054 tracked files and already knows the Cadastral registry.
        A second log would start disagreeing with the first the day it was written.
    </div>
</div>

{{-- Looking a file up goes through the global file-number selector, like
     every other file number in the module; the page then reads ?file_number=. --}}
<div class="table-toolbar" style="align-items:flex-end;">
    <div class="left" style="flex:1;max-width:720px;">
        @include('cadastral_module.partials._file_picker', [
            'navigate' => route('cadastral-module.registry.movements'),
            'label'    => 'File to trace',
            'number'   => $fileNumber,
        ])
    </div>
    @if ($fileNumber)
        <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.registry.movements') }}">Clear</a>
    @endif
</div>

@if ($fileNumber === '')
    <div class="table-wrapper">
        <div class="table-scroll">
            <table>
                <tbody>
                    <tr>
                        <td style="text-align:center;padding:36px;color:var(--gray-500);">
                            <i class="fas fa-route" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            Select a file number to see where it has been.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@elseif (! $tracker)
    <div class="table-wrapper">
        <div class="table-scroll">
            <table>
                <tbody>
                    <tr>
                        <td style="text-align:center;padding:36px;color:var(--gray-500);">
                            <i class="fas fa-circle-question" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            <strong>{{ $fileNumber }}</strong> is not in the file tracker.
                            That means it has never been tracked, not that it does not exist.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="dash-card" style="margin-bottom:18px;">
        <div class="card-header"><strong>{{ $tracker->file_number }}</strong> — {{ $tracker->file_title ?: '(untitled)' }}</div>

        <div class="calc-grid" style="padding:12px 0;">
            <div class="calc-card">
                <div class="kpi-label">Tracking ID</div>
                <div class="kpi-value" style="font-size:16px;">{{ $tracker->tracking_id ?: '—' }}</div>
            </div>
            <div class="calc-card">
                <div class="kpi-label">Currently With</div>
                <div class="kpi-value" style="font-size:16px;">{{ $tracker->current_office_name ?: '—' }}</div>
            </div>
            <div class="calc-card">
                <div class="kpi-label">Status</div>
                <div class="kpi-value" style="font-size:16px;">{{ Str::limit($tracker->status, 30) ?: '—' }}</div>
            </div>
            <div class="calc-card">
                <div class="kpi-label">Module</div>
                <div class="kpi-value" style="font-size:16px;">{{ $tracker->module ?: '—' }}</div>
            </div>
        </div>
    </div>

    <div class="table-wrapper">
        <div class="table-toolbar">
            <div class="left"><strong>Movement record</strong> — newest first</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>#</th><th>From</th><th>To</th><th>By</th><th>When</th><th>Note</th></tr>
                </thead>
                <tbody>
                    @forelse ($movements as $i => $move)
                        <tr>
                            <td>{{ count($movements) - $i }}</td>
                            <td>{{ $move['from_office_name'] ?? $move['from'] ?? $move['from_office'] ?? '—' }}</td>
                            <td>{{ $move['to_office_name'] ?? $move['to'] ?? $move['to_office'] ?? '—' }}</td>
                            <td>{{ $move['user_name'] ?? $move['by'] ?? $move['officer'] ?? '—' }}</td>
                            <td>{{ $move['timestamp'] ?? $move['date'] ?? $move['moved_at'] ?? '—' }}</td>
                            <td>{{ Str::limit($move['note'] ?? $move['remarks'] ?? $move['action'] ?? '', 60) ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;padding:24px;color:var(--gray-500);">
                                The file is tracked but its movement log is empty.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Showing the most recent {{ count($movements) }} movement(s)</span>
        </div>
    </div>
@endif
