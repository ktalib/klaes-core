@include('cadastral_module.partials._flash')


{{-- Temporarily hide the correspondence commissioning notice. --}}
{{--
<div class="caveat">
    <i class="fas fa-circle-info"></i>
    <div>
        <strong>Correspondence files are commissioned when a file is registered.</strong>
        Registering a receipt on the Intake Queue creates its cadastral shadow file in the same register
        <em>Commission Correspondence File (Match MLSFileNo)</em> uses — or, if the file already has one,
        matches it instead, so no file ever gets two. A file that hits the duplicate register or shares its
        plot is held for investigation and gets no correspondence file until an officer clears it.
        Boundary conflicts can only be checked once a file is charted.
    </div>
</div>
--}}

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Created Here</div>
        <div class="kpi-value">{{ number_format($stats['created']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Matched to Existing</div>
        <div class="kpi-value">{{ number_format($stats['matched']) }}</div>
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
        <select name="correspondence_status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">Created & Matched</option>
            @foreach (['created' => 'Created here', 'matched' => 'Matched to existing', 'pending' => 'Pending'] as $k => $label)
                <option value="{{ $k }}" @selected(request('correspondence_status')===$k)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="flag" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">Any duplicate state</option>
            <option value="duplicate" @selected(request('flag')==='duplicate')>Flagged duplicate</option>
            <option value="clean" @selected(request('flag')==='clean')>Not flagged</option>
            @if ($holdsEnabled)
                <option value="held" @selected(request('flag')==='held')>On hold</option>
                <option value="cleared" @selected(request('flag')==='cleared')>Hold cleared</option>
            @endif
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','correspondence_status','flag']))
            <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.registry.correspondence') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Cadastral File No</th>
                    <th>Source File No</th>
                    <th>Owner</th>
                    <th>Created</th>
                    <th>Duplicate</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipts as $receipt)
                    @php
                        $shadow   = $receipt->shadowFile;
                        $held     = $receipt->isOnHold();
                        $conflict = $conflicts[$receipt->file_number] ?? null;
                    @endphp
                    <tr @if ($held) style="background:#fff7ed;" @endif>
                        <td>
                            @if ($shadow)
                                <strong>{{ $shadow->full_number }}</strong>
                                <div style="font-size:11px;color:var(--gray-500);">{{ $shadow->ref_number }}</div>
                            @elseif ($receipt->index_corresponding_fileno)
                                <strong>{{ $receipt->index_corresponding_fileno }}</strong>
                                <div style="font-size:11px;color:var(--gray-500);">already in the index</div>
                            @else
                                <span style="color:var(--gray-500);">—</span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $receipt->file_number }}</strong>
                            <div style="font-size:11px;color:var(--gray-500);">{{ $receipt->source_registry }} · {{ $receipt->receipt_ref }}</div>
                        </td>
                        <td>{{ Str::limit($receipt->file_title, 40) ?: '—' }}</td>
                        <td>
                            @if ($shadow && $receipt->correspondence_status === 'created')
                                {{ optional($shadow->created_at)->format('d M Y') ?: $shadow->formatted_date }}
                            @elseif ($receipt->registered_at)
                                {{ $receipt->registered_at->format('d M Y') }}
                                @if ($receipt->correspondence_status === 'matched')
                                    <div style="font-size:11px;color:var(--gray-500);">matched on registration</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($receipt->duplicate_flag)
                                <span class="status-badge rejected" title="{{ $receipt->duplicate_note }}"><span class="dot"></span>Flagged</span>
                                <div style="font-size:11px;color:var(--gray-500);max-width:260px;">{{ Str::limit($receipt->duplicate_note, 90) }}</div>
                            @else
                                <span class="status-badge completed"><span class="dot"></span>None found</span>
                            @endif

                            @if ($conflict && count($conflict['items']))
                                <div style="font-size:11px;margin-top:4px;">
                                    <a href="{{ route('cadastral-module.charting.edit', $conflict['chart']) }}" style="color:var(--danger);"
                                       title="{{ collect($conflict['items'])->pluck('reason')->take(5)->implode(' ') }}">
                                        <i class="fas fa-draw-polygon"></i> {{ count($conflict['items']) }} boundary conflict(s)
                                    </a>
                                </div>
                            @elseif ($conflict)
                                <div style="font-size:11px;color:var(--gray-500);margin-top:4px;">Charted · no boundary conflict</div>
                            @endif
                        </td>
                        <td>
                            @if ($held)
                                <span class="status-badge rejected" title="{{ $receipt->hold_reason }}"><span class="dot"></span>On Hold – Investigation</span>
                                <div style="font-size:11px;color:var(--gray-500);max-width:260px;">{{ Str::limit($receipt->hold_reason, 90) }}</div>
                            @else
                                @php
                                    [$label, $badge] = match ($receipt->correspondence_status) {
                                        'created' => ['Created', 'completed'],
                                        'matched' => ['Matched', 'active'],
                                        default   => [ucfirst(str_replace('_', ' ', $receipt->correspondence_status)), 'pending'],
                                    };
                                @endphp
                                <span class="status-badge {{ $badge }}"><span class="dot"></span>{{ $label }}</span>
                                <div style="font-size:11px;color:var(--gray-500);">Receipt {{ $receipt->status }}</div>
                                @if ($holdsEnabled && $receipt->hold_status === \App\Models\Cadastral\CadastralFileReceipt::HOLD_CLEARED)
                                    <div style="font-size:11px;color:var(--gray-500);max-width:260px;" title="{{ $receipt->hold_clear_note }}">
                                        Hold cleared {{ optional($receipt->hold_cleared_at)->format('d M Y') }}: {{ Str::limit($receipt->hold_clear_note, 60) }}
                                    </div>
                                @endif
                            @endif
                        </td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('cadastral-module.registry.receipts.edit', $receipt) }}" title="Open the receipt">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="{{ route('cadastral-module.registry.duplicates', ['file_number' => $receipt->file_number]) }}" title="Duplicate check">
                                    <i class="fas fa-clone"></i>
                                </a>
                                @if ($holdsEnabled)
                                    @include('cadastral_module.partials._hold_actions', ['receipt' => $receipt])
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-link-slash" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No correspondence files yet. They are created when a file is registered on the
                            <a href="{{ route('cadastral-module.registry.receipts') }}">Intake Queue</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $receipts->firstItem() ?? 0 }}–{{ $receipts->lastItem() ?? 0 }}
            of {{ number_format($receipts->total()) }} · registered files and files on hold
        </span>
        <div class="pagination">{{ $receipts->links() }}</div>
    </div>
</div>
