@php use App\Http\Controllers\Survey\GknController; @endphp

@include('survey_module.partials._flash')

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Movements Logged</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">In Transit</div>
        <div class="kpi-value">{{ number_format($stats['in_transit']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Received</div>
        <div class="kpi-value">{{ number_format($stats['received']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Files Tracked</div>
        <div class="kpi-value">{{ number_format($stats['files']) }}</div>
    </div>
</div>

{{-- Log a new movement --}}
<form method="POST" action="{{ route('survey-module.gkn.tracking.store') }}">
    @csrf
    <div class="form-container" style="margin-bottom:24px;">
        <div class="form-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>File Ref <span class="required">*</span></label>
                    <input type="text" name="file_ref" value="{{ old('file_ref') }}" list="gknFileRefs"
                           placeholder="e.g. GKN-2026-001" maxlength="100" required />
                    <datalist id="gknFileRefs">
                        @foreach ($refs as $ref)
                            <option value="{{ $ref }}"></option>
                        @endforeach
                    </datalist>
                    <p class="helper-text">A GKN number or any other file reference.</p>
                </div>

                <div class="form-group">
                    <label>From Office <span class="required">*</span></label>
                    <input type="text" name="from_office" value="{{ old('from_office', 'Survey Office') }}"
                           placeholder="e.g. Survey Office" maxlength="255" required />
                </div>

                <div class="form-group">
                    <label>To Office <span class="required">*</span></label>
                    <input type="text" name="to_office" value="{{ old('to_office') }}"
                           placeholder="e.g. GIS Unit (KANGIS)" maxlength="255" required />
                </div>

                <div class="form-group">
                    <label>Purpose</label>
                    <input type="text" name="purpose" value="{{ old('purpose') }}"
                           placeholder="e.g. Boundary verification" maxlength="255" />
                </div>

                <div class="form-group">
                    <label>Sent At</label>
                    <input type="datetime-local" name="sent_at" value="{{ old('sent_at') }}" />
                    <p class="helper-text">Defaults to now.</p>
                </div>

                <div class="form-group full">
                    <label>Remarks</label>
                    <textarea name="remarks" placeholder="Anything the receiving office should know…">{{ old('remarks') }}</textarea>
                </div>
            </div>

            <div class="form-actions">
                <div class="left">
                    <span class="helper-text" style="margin:0;">
                        A file can only be out on one leg at a time — receive the open one before sending it again.
                    </span>
                </div>
                <div class="right">
                    <button class="btn btn-success" type="submit"><i class="fas fa-paper-plane"></i> Log Movement</button>
                </div>
            </div>
        </div>
    </div>
</form>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search file ref, office or purpose…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:280px;" />
        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach ([GknController::MOVE_IN_TRANSIT, GknController::MOVE_RECEIVED] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','status']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.gkn.tracking') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>File Ref</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Purpose</th>
                    <th>Sent</th>
                    <th>Received</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $m)
                    <tr>
                        <td><strong>{{ $m->file_ref }}</strong></td>
                        <td>{{ $m->from_office ?: '—' }}</td>
                        <td>{{ $m->to_office ?: '—' }}</td>
                        <td>{{ $m->purpose ?: '—' }}</td>
                        <td>{{ optional($m->sent_at)->format('Y-m-d H:i') ?: '—' }}</td>
                        <td>{{ optional($m->received_at)->format('Y-m-d H:i') ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $m->status === GknController::MOVE_RECEIVED ? 'completed' : 'pending' }}">
                                <span class="dot"></span>{{ $m->status }}
                            </span>
                        </td>
                        <td>
                            @if ($m->status !== GknController::MOVE_RECEIVED)
                                <form method="POST" action="{{ route('survey-module.gkn.tracking.receive', $m) }}"
                                      style="display:inline">
                                    @csrf
                                    <button class="btn btn-success btn-xs" type="submit">
                                        <i class="fas fa-inbox"></i> Mark Received
                                    </button>
                                </form>
                            @else
                                <span class="helper-text" style="margin:0;">Closed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-exchange-alt" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No file movements logged yet. Use the form above to send the first file out.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $movements->firstItem() ?? 0 }}–{{ $movements->lastItem() ?? 0 }}
            of {{ number_format($movements->total()) }} movements ·
            {{ number_format($stats['in_transit']) }} still in transit
        </span>
        <div class="pagination">{{ $movements->links() }}</div>
    </div>
</div>
