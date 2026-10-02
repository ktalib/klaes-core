@include('cadastral_module.partials._flash')

<div class="unit-tag"><i class="fas fa-toggle-on"></i> 4.3 · Cadastral Information</div>

<div class="page-header">
    <div></div>
    <a href="{{ route('cadastral-module.file-status.history') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-clock-rotate-left"></i> Full History
    </a>
</div>

<div class="kpi-grid">
    @foreach (\App\Models\Cadastral\CadastralIndexCard::FILE_STATUSES as $key => $label)
        <div class="kpi-card">
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value">{{ number_format($counts[$key] ?? 0) }}</div>
        </div>
    @endforeach
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="File number, title or card ref…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:280px;" />
        <select name="file_status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Statuses</option>
            @foreach (\App\Models\Cadastral\CadastralIndexCard::FILE_STATUSES as $k => $label)
                <option value="{{ $k }}" @selected(request('file_status')===$k)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','file_status']))
            <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.file-status.index') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>File Number</th>
                    <th>Title</th>
                    <th>Location</th>
                    <th>Current Status</th>
                    <th>Changed</th>
                    <th>Last Reason</th>
                    <th style="width:38%">Change it</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cards as $card)
                    <tr>
                        <td><strong>{{ $card->file_number }}</strong></td>
                        <td>{{ Str::limit($card->file_title, 30) ?: '—' }}</td>
                        {{-- District, LGA, State. --}}
                        <td>{{ $card->property_location ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $card->status_badge }}">
                                <span class="dot"></span>{{ $card->file_status_label }}
                            </span>
                        </td>
                        <td>{{ optional($card->file_status_changed_at)->format('d M Y') ?: '—' }}</td>
                        <td>{{ Str::limit($card->file_status_reason, 40) ?: '—' }}</td>
                        <td>
                            @canDo('Cad - Records', 'edit')
                            <form method="POST" action="{{ route('cadastral-module.file-status.update', $card) }}"
                                  enctype="multipart/form-data"
                                  style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                @csrf @method('PUT')

                                <select name="to_status" required style="padding:5px 8px;border:1px solid var(--gray-300);border-radius:4px;font-size:12px;">
                                    @foreach (\App\Models\Cadastral\CadastralIndexCard::FILE_STATUSES as $k => $label)
                                        <option value="{{ $k }}" @disabled($k === $card->file_status)>{{ $label }}</option>
                                    @endforeach
                                </select>

                                <input type="date" name="effective_date" required value="{{ now()->toDateString() }}"
                                       title="Effective date (required)"
                                       style="padding:5px 8px;border:1px solid var(--gray-300);border-radius:4px;font-size:12px;" />

                                <input type="text" name="authority_ref" placeholder="Authority ref"
                                       style="padding:5px 8px;border:1px solid var(--gray-300);border-radius:4px;font-size:12px;width:110px;" />

                                <input type="text" name="reason" required placeholder="Remarks (required)"
                                       style="padding:5px 8px;border:1px solid var(--gray-300);border-radius:4px;font-size:12px;width:170px;" />

                                @if ($canUpload)
                                    <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                           title="Supporting documents: PDF, JPG or PNG, up to 10 MB each, at most {{ $maxDocs }}"
                                           style="font-size:11px;max-width:200px;" />
                                @endif

                                <button type="submit" class="btn btn-primary btn-xs">
                                    <i class="fas fa-check"></i> Apply
                                </button>
                            </form>
                            @endcanDo
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-id-card" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No index cards yet — a file's status is held on its card.
                            <a href="{{ route('cadastral-module.index-cards.index') }}">Commission one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $cards->firstItem() ?? 0 }}–{{ $cards->lastItem() ?? 0 }}
            of {{ number_format($cards->total()) }} · remarks and an effective date are required, and every change is logged
            @if ($canUpload)
                · supporting documents (PDF, JPG, PNG, 10 MB each) are filed into the file's EDMS folder and appear in Page Typing
            @else
                · supporting-document upload is pending installation
            @endif
        </span>
        <div class="pagination">{{ $cards->links() }}</div>
    </div>
</div>

@if ($recent->isNotEmpty())
    <div class="table-wrapper" style="margin-top:18px;">
        <div class="table-toolbar">
            <div class="left"><strong>Most recent changes</strong></div>
            <a class="btn btn-outline btn-xs" href="{{ route('cadastral-module.file-status.history') }}">All of them</a>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>File Number</th><th>From</th><th>To</th><th>Effective</th><th>By</th><th>Remarks</th><th>Documents</th></tr>
                </thead>
                <tbody>
                    @foreach ($recent as $event)
                        <tr>
                            <td>{{ $event->file_number }}</td>
                            <td>{{ $event->from_status ? ucfirst(str_replace('_', ' ', $event->from_status)) : '—' }}</td>
                            <td><strong>{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}</strong></td>
                            <td>{{ optional($event->effective_date)->format('d M Y') ?: '—' }}</td>
                            <td>{{ $event->actor_name ?: '—' }}</td>
                            <td>{{ Str::limit($event->reason, 60) }}</td>
                            <td>@include('cadastral_module.partials._documents', ['docs' => $documents[$event->id] ?? collect()])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
