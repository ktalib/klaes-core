@include('cadastral_module.partials._flash')


<div class="page-header">
    <div></div>
    @canDo('Cad - Records', 'create')
        <a href="{{ route('cadastral-module.registry.receipts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Log Incoming File
        </a>
    @endcanDo
</div>

{{-- Queue statuses are derived from status + correspondence_status (and the
     hold, once its columns exist); the mapping lives in
     FileReceiptController::queueStatus(). --}}
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Queued</div>
        <div class="kpi-value">{{ number_format($stats['queued']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">In Progress</div>
        <div class="kpi-value">{{ number_format($stats['in_progress']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Correspondence Done</div>
        <div class="kpi-value">{{ number_format($stats['done']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Flagged Duplicates</div>
        <div class="kpi-value">{{ number_format($stats['duplicates']) }}</div>
    </div>
    @if ($holdsEnabled)
        <div class="kpi-card">
            <div class="kpi-label">On Hold</div>
            <div class="kpi-value">{{ number_format($stats['on_hold']) }}</div>
        </div>
    @endif
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="File number, owner or ref…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:250px;" />

        <select name="source_registry" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Sources</option>
            @foreach (app(\App\Services\Cadastral\CadastralSettings::class)->sourceRegistries() as $reg)
                <option value="{{ $reg }}" @selected(request('source_registry')===$reg)>{{ $reg }}</option>
            @endforeach
        </select>

        <select name="queue" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">Any Queue Status</option>
            @foreach (\App\Http\Controllers\Cadastral\FileReceiptController::queueStatuses() as $qs)
                <option value="{{ $qs }}" @selected(request('queue')===$qs)>{{ $qs }}</option>
            @endforeach
        </select>

        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Receipt Status</option>
            @foreach (\App\Models\Cadastral\CadastralFileReceipt::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
            @endforeach
        </select>

        <select name="file_class" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">Direct & Conversion</option>
            <option value="direct" @selected(request('file_class')==='direct')>Direct only</option>
            <option value="conversion" @selected(request('file_class')==='conversion')>Conversion only</option>
        </select>

        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
            <input type="checkbox" name="duplicates" value="1" @checked(request('duplicates')==='1') />
            Flagged only
        </label>

        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>

        @if (request()->hasAny(['q','source_registry','queue','status','file_class','duplicates']))
            <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.registry.receipts') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>File No</th>
                    <th>Source</th>
                    <th>Type</th>
                    <th>Owner</th>
                    <th>Location</th>
                    <th>Received</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipts as $receipt)
                    <tr @class(['duplicate-row' => $receipt->duplicate_flag])
                        @if ($receipt->duplicate_flag) style="background:#fff7ed;" @endif>
                        @php
                            [$queueLabel, $queueBadge] = \App\Http\Controllers\Cadastral\FileReceiptController::queueStatus($receipt);
                        @endphp
                        <td>
                            <strong>{{ $receipt->file_number }}</strong>
                            @if ($receipt->duplicate_flag)
                                <i class="fas fa-triangle-exclamation" style="color:var(--danger);"
                                   title="{{ $receipt->duplicate_note }}"></i>
                            @endif
                            <div style="font-size:11px;color:var(--gray-500);">{{ $receipt->receipt_ref }}</div>
                        </td>
                        <td>{{ $receipt->source_registry }}</td>
                        <td>
                            {{ \App\Services\Cadastral\CadastralRegistryLookup::typeLabel($receipt->file_number, $receipt->source_registry, $receipt->file_class) }}
                            @if ($receipt->isConversion())
                                <div style="font-size:11px;color:var(--gray-500);">Charting not required</div>
                            @endif
                        </td>
                        <td>{{ Str::limit($receipt->file_title, 40) ?: '—' }}</td>
                        {{-- District, LGA, State — the plot number lives in its own field. --}}
                        <td>{{ $receipt->property_location ?: '—' }}</td>
                        <td>{{ optional($receipt->received_at)->format('d M Y') ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $queueBadge }}" title="Receipt {{ $receipt->status }} · correspondence {{ str_replace('_', ' ', $receipt->correspondence_status) }}">
                                <span class="dot"></span>{{ $queueLabel }}
                            </span>
                            @if ($receipt->isOnHold())
                                <div style="font-size:11px;color:var(--gray-500);max-width:220px;" title="{{ $receipt->hold_reason }}">
                                    {{ Str::limit($receipt->hold_reason, 70) }}
                                </div>
                            @elseif ($holdsEnabled && $receipt->hold_status === \App\Models\Cadastral\CadastralFileReceipt::HOLD_CLEARED)
                                <div style="font-size:11px;color:var(--gray-500);" title="{{ $receipt->hold_clear_note }}">Hold cleared</div>
                            @endif
                        </td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('cadastral-module.registry.receipts.edit', $receipt) }}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>

                                @if ($receipt->status === 'Received' && $receipt->isOnHold())
                                    {{-- No Register button while held; the server refuses it too. --}}
                                    <span title="On hold — clear the hold before registering" style="color:var(--gray-400);">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                @elseif ($receipt->status === 'Received')
                                    <form method="POST" action="{{ route('cadastral-module.registry.receipts.mark-registered', $receipt) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" title="Register (creates or matches the correspondence file)"
                                                onclick="this.disabled=true;this.form.submit();"><i class="fas fa-check"></i></button>
                                    </form>
                                @elseif ($receipt->status === 'Registered')
                                    <form method="POST" action="{{ route('cadastral-module.registry.receipts.mark-archived', $receipt) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" title="Archive"><i class="fas fa-box-archive"></i></button>
                                    </form>
                                @endif

                                @if ($holdsEnabled)
                                    @include('cadastral_module.partials._hold_actions', ['receipt' => $receipt])
                                @endif

                                @canDo('Cad - Records', 'create')
                                    <a href="{{ route('cadastral-module.reports.create', ['receipt' => $receipt->id]) }}" title="Open a cadastral report">
                                        <i class="fas fa-file-circle-plus"></i>
                                    </a>
                                @endcanDo

                                @if ($receipt->status === 'Received')
                                    @canDo('Cad - Records', 'delete')
                                        <form method="POST" action="{{ route('cadastral-module.registry.receipts.destroy', $receipt) }}"
                                              style="display:inline"
                                              onsubmit="return confirm('Delete {{ $receipt->receipt_ref }}? This cannot be undone.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endcanDo
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No files in the intake queue.
                            @canDo('Cad - Records', 'create')
                                <a href="{{ route('cadastral-module.registry.receipts.create') }}">Log the first one</a>
                            @endcanDo.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $receipts->firstItem() ?? 0 }}–{{ $receipts->lastItem() ?? 0 }}
            of {{ number_format($receipts->total()) }} receipts · a file may be logged more than once
        </span>
        <div class="pagination">{{ $receipts->links() }}</div>
    </div>
</div>
