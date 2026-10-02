@include('cadastral_module.partials._flash')

<div class="unit-tag"><i class="fas fa-clock-rotate-left"></i> 4.3 · Cadastral Information</div>

<div class="page-header">
    <div></div>
    <a href="{{ route('cadastral-module.file-status.index') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to File Status
    </a>
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="File number…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:260px;" />
        <select name="to_status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Changes</option>
            @foreach (\App\Models\Cadastral\CadastralIndexCard::FILE_STATUSES as $k => $label)
                <option value="{{ $k }}" @selected(request('to_status')===$k)>Changed to {{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','to_status']))
            <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.file-status.history') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>File Number</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Effective</th>
                    <th>Authority</th>
                    <th>By</th>
                    <th>Remarks</th>
                    <th>Documents</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $event)
                    <tr>
                        <td>{{ optional($event->created_at)->format('d M Y H:i') ?: '—' }}</td>
                        <td>
                            @if ($event->card)
                                <a href="{{ route('cadastral-module.index-cards.show', $event->card) }}">
                                    <strong>{{ $event->file_number }}</strong>
                                </a>
                            @else
                                <strong>{{ $event->file_number }}</strong>
                            @endif
                        </td>
                        <td>{{ $event->from_status ? ucfirst(str_replace('_', ' ', $event->from_status)) : '—' }}</td>
                        <td><strong>{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}</strong></td>
                        <td>{{ optional($event->effective_date)->format('d M Y') ?: '—' }}</td>
                        <td>{{ $event->authority_ref ?: '—' }}</td>
                        <td>{{ $event->actor_name ?: '—' }}</td>
                        <td>{{ Str::limit($event->reason, 70) ?: '—' }}</td>
                        <td>@include('cadastral_module.partials._documents', ['docs' => $documents[$event->id] ?? collect()])</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-clock-rotate-left" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No status change has been recorded yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $events->firstItem() ?? 0 }}–{{ $events->lastItem() ?? 0 }}
            of {{ number_format($events->total()) }} · this log is append-only
        </span>
        <div class="pagination">{{ $events->links() }}</div>
    </div>
</div>
