@php
    use App\Http\Controllers\Survey\OpController;
    use App\Models\Survey\SurveyOpRecord;
    use App\Models\Survey\SurveyProject;

    $totalSteps = count(SurveyOpRecord::STEPS);
    $doneSteps  = $steps->where('status', 'done')->count();
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <span class="status-badge approved" style="font-size:12px;"><span class="dot"></span> QUICK WIN</span>
        <span class="helper-text" style="margin:0;">
            {{ number_format($stats['in_progress']) }} in progress · {{ number_format($stats['issued']) }} issued
        </span>
    </div>

    @if ($choices->isNotEmpty())
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <label for="opPicker" class="helper-text" style="margin:0;">Permit</label>
            <select name="op" id="opPicker" onchange="this.form.submit()"
                    style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;min-width:280px;">
                @foreach ($choices as $choice)
                    <option value="{{ $choice->id }}" @selected($op && $op->id === $choice->id)>
                        {{ $choice->op_number }}
                        @if ($choice->beneficiary) — {{ $choice->beneficiary->full_name }} @endif
                        ({{ $choice->status }})
                    </option>
                @endforeach
            </select>
            <noscript><button class="btn btn-secondary btn-sm" type="submit">Open</button></noscript>
        </form>
    @endif
</div>

@if (!$op)
    {{-- Nothing to walk through yet. --}}
    <div class="table-wrapper">
        <div style="text-align:center;padding:48px 24px;color:var(--gray-500);">
            <i class="fas fa-route" style="font-size:30px;display:block;margin-bottom:12px;opacity:.35;"></i>
            <div style="font-weight:600;margin-bottom:6px;">No occupancy permits yet</div>
            <div style="margin-bottom:16px;">
                The pipeline starts once an OP is generated for an approved case.
            </div>
            <a class="btn btn-primary" href="{{ route('survey-module.compensation.op') }}">
                <i class="fas fa-file-signature"></i> Go to OP Generation
            </a>
        </div>
    </div>
@else
    <div class="workflow-page">
        <div class="dash-card" style="margin-bottom:20px;">
            <div class="card-header">
                <h3 style="margin:0;">{{ $op->op_number }}</h3>
                <span class="status-badge {{ $op->status === OpController::STATUS_ISSUED ? 'active' : ($op->status === OpController::STATUS_READY ? 'pending' : 'review') }}">
                    <span class="dot"></span>{{ $op->status }}
                </span>
            </div>
            <div class="calc-grid" style="padding:4px 0 0;">
                <div class="calc-card">
                    <div class="kpi-label">Case</div>
                    <div class="kpi-value" style="font-size:18px;">{{ optional($op->case)->case_ref ?: '—' }}</div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Beneficiary</div>
                    <div class="kpi-value" style="font-size:18px;">{{ optional($op->beneficiary)->full_name ?: '—' }}</div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Scheme</div>
                    <div class="kpi-value" style="font-size:18px;">
                        {{ $op->scheme_type === SurveyProject::SCHEME_MONETARY ? 'Monetary' : 'Land-for-Land' }}
                    </div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">{{ $op->scheme_type === SurveyProject::SCHEME_MONETARY ? 'Cash Reference' : 'Plot Schedule' }}</div>
                    <div class="kpi-value" style="font-size:18px;">{{ $op->plot_or_cash_ref ?: '—' }}</div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Progress</div>
                    <div class="kpi-value" style="font-size:18px;">{{ $doneSteps }}/{{ $totalSteps }} stages</div>
                </div>
            </div>
        </div>

        @if ($steps->isEmpty())
            <div class="comp-type-note" style="border-left:4px solid var(--danger);background:#fdecea;">
                <i class="fas fa-exclamation-triangle"></i>
                This permit has no workflow rows, so it cannot be advanced. Re-generate it to seed the pipeline.
            </div>
        @else
            <div class="wf-chain">
                @foreach ($steps as $step)
                    <div class="wf-node {{ $step->status === 'done' ? 'done' : ($step->status === 'active' ? 'active' : '') }}"
                         onclick="toggleWfDetail(this)">
                        <div class="wf-title">
                            {{ $step->step_no }}. {{ $step->step_name }}
                            <span class="wf-status {{ $step->status === 'done' ? 'done' : ($step->status === 'active' ? 'active' : 'waiting') }}">
                                {{ $step->status === 'done' ? 'Done' : ($step->status === 'active' ? 'In Progress' : 'Waiting') }}
                            </span>
                        </div>
                        <div class="wf-meta">
                            <span>{{ $step->actor }}</span>
                            @if ($step->completed_at)
                                <span>{{ $step->completed_at->format('Y-m-d H:i') }}</span>
                            @elseif ($step->status === 'active')
                                <span>Since {{ optional($step->updated_at)->format('Y-m-d') }}</span>
                            @endif
                        </div>
                        <div class="wf-detail {{ $step->status === 'active' ? 'open' : '' }}">
                            {{ $step->note ?: ($step->status === 'done'
                                ? 'Completed and passed on.'
                                : ($step->status === 'active'
                                    ? 'Awaiting action from ' . $step->actor . '.'
                                    : 'Triggered once stage ' . ($step->step_no - 1) . ' is complete.')) }}
                        </div>
                    </div>
                @endforeach
            </div>

            @php $current = $steps->first(fn ($s) => $s->status !== 'done'); @endphp

            <form method="POST" action="{{ route('survey-module.compensation.op.advance', $op) }}"
                  class="form-actions" style="margin-top:20px;">
                @csrf
                <div class="left" style="flex:1;">
                    <input type="text" name="note" maxlength="1000"
                           placeholder="{{ $current ? 'Note for ' . $current->step_name . ' (optional)' : 'All stages complete' }}"
                           @disabled(!$current)
                           style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:100%;max-width:460px;" />
                </div>
                <div class="right">
                    @if ($current)
                        <button class="btn btn-success" type="submit">
                            <i class="fas fa-check"></i>
                            Complete “{{ $current->step_name }}”
                        </button>
                    @else
                        <span class="status-badge active"><span class="dot"></span>
                            Issued {{ optional($op->issued_at)->format('Y-m-d H:i') }}
                        </span>
                    @endif
                </div>
            </form>
        @endif

        {{-- The two scenarios the pipeline has to serve. --}}
        <div class="op-scenarios" style="margin-top:24px;">
            <div class="op-scenario">
                <div class="scenario-title">Monetary OP</div>
                <div class="scenario-desc">References cash compensation amount and beneficiary NIN. No plot schedule attached.</div>
            </div>
            <div class="op-scenario">
                <div class="scenario-title">Land-for-Land OP</div>
                <div class="scenario-desc">Includes plot schedule (Plot No., OP No.) from Plot Allocation under the 50:50 split.</div>
            </div>
        </div>
    </div>
@endif
