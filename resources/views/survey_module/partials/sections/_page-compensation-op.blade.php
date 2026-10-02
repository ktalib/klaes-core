@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    {{-- The button lives in the header, the checkboxes in the table below;
         the HTML5 form attribute keeps them in the same submission. --}}
    <button class="btn btn-success" type="submit" form="opGenerateForm"
            @disabled($candidates->isEmpty())>
        <i class="fas fa-file-signature"></i> Generate Selected
    </button>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">OPs in Queue</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Draft</div>
        <div class="kpi-value">{{ number_format($stats['draft']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Ready</div>
        <div class="kpi-value">{{ number_format($stats['ready']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Issued</div>
        <div class="kpi-value">{{ number_format($stats['issued']) }}</div>
    </div>
</div>

{{-- ------------------------------------------------------------------ --}}
{{-- Awaiting OP: approved cases / beneficiaries with no permit yet      --}}
{{-- ------------------------------------------------------------------ --}}
<form method="POST" action="{{ route('survey-module.compensation.op.generate') }}" id="opGenerateForm">
    @csrf
    <div class="table-wrapper" style="margin-bottom:24px;">
        <div class="table-toolbar">
            <div class="left">
                <strong>Awaiting OP</strong>
                <span class="helper-text" style="margin:0;">
                    Approved cases with no permit yet. The plot schedule or cash figure is taken
                    from the case, never typed in.
                </span>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th style="width:38px;"><input type="checkbox" onclick="toggleAllOpTargets(this)" /></th>
                        <th>Case Ref</th>
                        <th>Beneficiary</th>
                        <th>Scheme</th>
                        <th>Plot / Cash Ref</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($candidates as $c)
                        <tr>
                            <td><input type="checkbox" name="targets[]" value="{{ $c['key'] }}" class="op-target" /></td>
                            <td><strong>{{ $c['case']->case_ref }}</strong></td>
                            <td>{{ $c['beneficiary'] ?: '— no beneficiary on file —' }}</td>
                            <td>
                                @if ($c['case']->scheme_type === \App\Models\Survey\SurveyProject::SCHEME_MONETARY)
                                    <span class="status-badge active"><span class="dot"></span>Monetary</span>
                                @else
                                    <span class="status-badge completed"><span class="dot"></span>Land-for-Land</span>
                                @endif
                            </td>
                            <td>
                                @if ($c['ref'])
                                    {{ $c['ref'] }}
                                @else
                                    <span class="helper-text" style="margin:0;">
                                        Nothing to reference yet — will be raised as Draft
                                    </span>
                                @endif
                            </td>
                            <td>{{ $c['case']->property_location ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center;padding:24px;color:var(--gray-500);">
                                <i class="fas fa-check-circle" style="font-size:22px;display:block;margin-bottom:8px;opacity:.4;"></i>
                                Nothing awaiting an OP. Approve a case first —
                                <a href="{{ route('survey-module.compensation.cases') }}">go to cases</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>{{ number_format($candidates->count()) }} item(s) awaiting generation</span>
        </div>
    </div>
</form>

{{-- ------------------------------------------------------------------ --}}
{{-- The queue itself                                                    --}}
{{-- ------------------------------------------------------------------ --}}
<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search OP, case or beneficiary…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:250px;" />
        <select name="scheme" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Schemes</option>
            <option value="monetary" @selected(request('scheme')==='monetary')>Monetary</option>
            <option value="land" @selected(request('scheme')==='land')>Land-for-Land</option>
        </select>
        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach (\App\Http\Controllers\Survey\OpController::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','scheme','status']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.op') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>OP No.</th>
                    <th>Case Ref</th>
                    <th>Beneficiary</th>
                    <th>Scheme</th>
                    <th>Plot / Cash Ref</th>
                    <th>Stage</th>
                    <th>OP Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ops as $op)
                    @php
                        $active = $op->steps->firstWhere('status', 'active');
                        $done   = $op->steps->where('status', 'done')->count();
                    @endphp
                    <tr>
                        <td><strong>{{ $op->op_number }}</strong></td>
                        <td>{{ optional($op->case)->case_ref ?: '—' }}</td>
                        <td>{{ optional($op->beneficiary)->full_name ?: '—' }}</td>
                        <td>
                            @if ($op->scheme_type === \App\Models\Survey\SurveyProject::SCHEME_MONETARY)
                                <span class="status-badge active"><span class="dot"></span>Monetary</span>
                            @else
                                <span class="status-badge completed"><span class="dot"></span>Land-for-Land</span>
                            @endif
                        </td>
                        <td>{{ $op->plot_or_cash_ref ?: '—' }}</td>
                        <td>
                            @if ($op->status === \App\Http\Controllers\Survey\OpController::STATUS_ISSUED)
                                Issued {{ optional($op->issued_at)->format('Y-m-d') }}
                            @elseif ($active)
                                {{ $active->step_no }}/{{ count(\App\Models\Survey\SurveyOpRecord::STEPS) }} · {{ $active->step_name }}
                            @else
                                {{ $done }}/{{ count(\App\Models\Survey\SurveyOpRecord::STEPS) }} stages
                            @endif
                        </td>
                        <td>
                            <span class="status-badge {{ $op->status === 'Issued' ? 'active' : ($op->status === 'Ready' ? 'pending' : 'review') }}">
                                <span class="dot"></span>{{ $op->status }}
                            </span>
                        </td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('survey-module.workflow.occupancy', ['op' => $op->id]) }}"
                                   title="Open the OP pipeline"><i class="fas fa-eye"></i></a>
                                @if ($op->status !== \App\Http\Controllers\Survey\OpController::STATUS_ISSUED)
                                    <form method="POST" action="{{ route('survey-module.compensation.op.destroy', $op) }}"
                                          style="display:inline" onsubmit="return confirm('Delete {{ $op->op_number }}? This cannot be undone.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-file-signature" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No occupancy permits yet. Tick an approved case above and press
                            <strong>Generate Selected</strong>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $ops->firstItem() ?? 0 }}–{{ $ops->lastItem() ?? 0 }}
            of {{ number_format($ops->total()) }} permits · the reference is derived from the case's scheme
        </span>
        <div class="pagination">{{ $ops->links() }}</div>
    </div>
</div>

<script>
    // Header checkbox mirrors every row in the "Awaiting OP" table.
    function toggleAllOpTargets(master) {
        document.querySelectorAll('.op-target').forEach(function (cb) { cb.checked = master.checked; });
    }
</script>
