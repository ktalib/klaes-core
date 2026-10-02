@php
    use App\Http\Controllers\Survey\ExaminationController;

    $reviewing = $reviewing ?? null;
    $formOpen  = !$reviewing && ($errors->any() || old('linked_ref') !== null);

    $statusBadge = [
        'Queued'    => 'pending',
        'In Review' => 'review',
        'Passed'    => 'active',
        'Returned'  => 'rejected',
    ];
    $priorityBadge = [
        'High'   => 'rejected',
        'Normal' => 'review',
        'Low'    => 'completed',
    ];
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary btn-sm"
            onclick="document.getElementById('examForm').classList.toggle('open')">
        <i class="fas fa-plus"></i> Submit for Examination
    </button>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Awaiting Review</div>
        <div class="kpi-value">{{ number_format($stats['awaiting']) }}</div>
        <div class="kpi-sub">Queued or in review</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Passed Today</div>
        <div class="kpi-value">{{ number_format($stats['passed_today']) }}</div>
        <div class="kpi-sub">{{ now()->format('d M Y') }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Returned</div>
        <div class="kpi-value">{{ number_format($stats['returned']) }}</div>
        <div class="kpi-sub">Sent back for correction</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Avg Turnaround</div>
        <div class="kpi-value">{{ $stats['avg_label'] }}</div>
        <div class="kpi-sub">Submission to decision</div>
    </div>
</div>

{{-- Review panel: opened from a row with ?review=<id>. Pass and Return post to
     their own routes; Return refuses to go through without a reason. --}}
@if ($reviewing)
    <form method="POST" id="examReview" class="farmer-entry-form open"
          action="{{ route('survey-module.workflow.examination.pass', $reviewing) }}">
        @csrf

        <div class="form-row" style="grid-template-columns:1fr 1fr 1fr 1fr;">
            <div>
                <label>Exam Ref</label>
                <input type="text" value="{{ $reviewing->exam_ref }}" readonly
                       style="background:var(--gray-100);color:var(--gray-600);" />
            </div>
            <div>
                <label>Linked Ref</label>
                <input type="text" value="{{ $reviewing->linked_ref }}" readonly
                       style="background:var(--gray-100);color:var(--gray-600);" />
            </div>
            <div>
                <label>Type</label>
                <input type="text" value="{{ $reviewing->exam_type }}" readonly
                       style="background:var(--gray-100);color:var(--gray-600);" />
            </div>
            <div>
                <label>Submitted By</label>
                <input type="text" value="{{ $reviewing->submitted_by }}" readonly
                       style="background:var(--gray-100);color:var(--gray-600);" />
            </div>
        </div>

        <div class="form-row" style="grid-template-columns:1fr;">
            <div>
                <label>Findings <span class="required">*</span> <span style="font-weight:400;color:var(--gray-600);">(required to return)</span></label>
                <textarea name="findings" placeholder="What was checked, and what must be corrected…"
                          style="min-height:90px;width:100%;padding:8px 12px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;">{{ old('findings', $reviewing->findings) }}</textarea>
            </div>
        </div>

        <div class="form-row" style="grid-template-columns:1fr auto;">
            <div></div>
            <div style="display:flex;align-items:end;gap:6px;">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="fas fa-check"></i> Pass
                </button>
                <button type="submit" class="btn btn-danger btn-sm"
                        formaction="{{ route('survey-module.workflow.examination.return', $reviewing) }}">
                    <i class="fas fa-undo"></i> Return
                </button>
                <a class="btn btn-secondary btn-sm" href="{{ route('survey-module.workflow.examination') }}">
                    <i class="fas fa-times"></i> Close
                </a>
            </div>
        </div>
    </form>
@endif

<form method="POST" id="examForm" class="farmer-entry-form {{ $formOpen ? 'open' : '' }}"
      action="{{ route('survey-module.workflow.examination.store') }}">
    @csrf

    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
        <div>
            <label>Linked Case / GKN / LPKN <span class="required">*</span></label>
            <input type="text" name="linked_ref" value="{{ old('linked_ref', $exam->linked_ref) }}"
                   placeholder="e.g. C-2026-004" required />
        </div>
        <div>
            <label>Examination Type <span class="required">*</span></label>
            <select name="exam_type">
                @foreach (ExaminationController::TYPES as $t)
                    <option value="{{ $t }}" @selected(old('exam_type', $exam->exam_type) === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Submitted By <span class="required">*</span></label>
            <input type="text" name="submitted_by" value="{{ old('submitted_by', $exam->submitted_by) }}"
                   placeholder="Officer or desk" required />
        </div>
    </div>

    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
        <div>
            <label>Priority <span class="required">*</span></label>
            <select name="priority">
                @foreach (ExaminationController::PRIORITIES as $p)
                    <option value="{{ $p }}" @selected(old('priority', $exam->priority) === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Status</label>
            <select name="status">
                @foreach (ExaminationController::OPEN_STATUSES as $s)
                    <option value="{{ $s }}" @selected(old('status', $exam->status) === $s)>{{ $s }}</option>
                @endforeach
            </select>
            <p class="helper-text" style="font-size:12px;">Passed and Returned are set by the reviewer, not here.</p>
        </div>
        <div>
            <label>Notes</label>
            <input type="text" name="findings" value="{{ old('findings') }}"
                   placeholder="Optional context for the reviewer" />
        </div>
    </div>

    <div class="form-row" style="grid-template-columns:1fr auto;">
        <div></div>
        <div style="display:flex;align-items:end;gap:6px;">
            <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-check"></i> Add to Queue
            </button>
            <button type="button" class="btn btn-secondary btn-sm"
                    onclick="document.getElementById('examForm').classList.remove('open')">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</form>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search exam ref, linked ref, officer…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:260px;" />
        <select name="status"
                style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach (ExaminationController::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="priority"
                style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Priorities</option>
            @foreach (ExaminationController::PRIORITIES as $p)
                <option value="{{ $p }}" @selected(request('priority') === $p)>{{ $p }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'status', 'priority']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.workflow.examination') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Exam ID</th>
                    <th>Linked Case / GKN</th>
                    <th>Type</th>
                    <th>Submitted By</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Reviewed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($exams as $e)
                    <tr>
                        <td><strong>{{ $e->exam_ref }}</strong></td>
                        <td>{{ $e->linked_ref ?: '—' }}</td>
                        <td>{{ $e->exam_type ?: '—' }}</td>
                        <td>{{ $e->submitted_by ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $priorityBadge[$e->priority] ?? 'pending' }}">
                                <span class="dot"></span>{{ $e->priority }}
                            </span>
                        </td>
                        <td>
                            <span class="status-badge {{ $statusBadge[$e->status] ?? 'pending' }}">
                                <span class="dot"></span>{{ $e->status }}
                            </span>
                            @if ($e->findings)
                                <div style="font-size:12px;color:var(--gray-600);">{{ Str::limit($e->findings, 60) }}</div>
                            @endif
                        </td>
                        <td>{{ optional($e->reviewed_at)->format('Y-m-d H:i') ?: '—' }}</td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('survey-module.workflow.examination', array_merge(request()->query(), ['review' => $e->id])) }}"
                                   title="Open review panel"><i class="fas fa-search"></i></a>

                                @if (in_array($e->status, ExaminationController::OPEN_STATUSES, true))
                                    <form method="POST" action="{{ route('survey-module.workflow.examination.pass', $e) }}"
                                          style="display:inline">
                                        @csrf
                                        <button type="submit" title="Pass without comment"><i class="fas fa-check"></i></button>
                                    </form>
                                    <a href="{{ route('survey-module.workflow.examination', array_merge(request()->query(), ['review' => $e->id])) }}"
                                       title="Return — a reason is required"><i class="fas fa-undo"></i></a>
                                @endif

                                <form method="POST" action="{{ route('survey-module.workflow.examination.destroy', $e) }}"
                                      style="display:inline"
                                      onsubmit="return confirm('Remove {{ $e->exam_ref }} from the queue?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-clipboard-check" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny(['q', 'status', 'priority']))
                                Nothing matches that filter.
                                <a href="{{ route('survey-module.workflow.examination') }}">Clear the filter</a>.
                            @else
                                The examination queue is empty.
                                <a href="#" onclick="document.getElementById('examForm').classList.add('open');return false;">Submit the first item</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $exams->firstItem() ?? 0 }}–{{ $exams->lastItem() ?? 0 }}
            of {{ number_format($exams->total()) }} item(s)
            · {{ number_format($stats['awaiting']) }} awaiting examination
        </span>
        <div class="pagination">{{ $exams->links() }}</div>
    </div>
</div>
