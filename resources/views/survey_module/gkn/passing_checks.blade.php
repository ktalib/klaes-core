@extends('survey_module.layouts.klaes')

@section('page-title', 'Checking & Passing — KLAES')

@php
  use App\Models\Survey\SurveyPassingCheck as PC;

  $PageTitle = 'Checking & Passing (Survey Plan)';
  $PageDescription = 'Check the distances on the survey plan, beacon to beacon, against the distances measured.';
  $field = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $e = $editing ?? null;
  $open = $e || $errors->any();
  $v = fn ($k) => old($k, $e?->$k);
  $lines = old('lines', $e?->lines ?: [['from' => '', 'to' => '', 'plan_distance' => '', 'measured_distance' => '']]);
  $resultTone = ['Passed' => 'completed', 'Failed' => 'rejected', 'Returned for Correction' => 'pending'];
@endphp

@section('survey-content')
{{-- CR 30102026 item 21 (user, 2026-10-31): a first version of the form; Survey will give its own fields. --}}
@include('survey_module.partials._flash')

<div class="page-header">
    <div style="font-size:13px;color:var(--gray-500);">A first version of the form. The fields will follow Survey's own checklist once it is given.</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">@include('survey_module.partials._records_export', ['endpoint' => route('survey-module.gkn.passing-checks.export'), 'title' => 'Checking and Passing', 'dateLabel' => 'Date Checked'])
    <button type="button" class="btn btn-primary" onclick="document.getElementById('pcForm').classList.add('open')"><i class="fas fa-plus"></i> New Check</button>
    </div>
</div>

<form method="POST" id="pcForm"
      action="{{ $e ? route('survey-module.gkn.passing-checks.update', $e) : route('survey-module.gkn.passing-checks.store') }}"
      class="farmer-entry-form {{ $open ? 'open' : '' }}" data-sv-modal="{{ $e ? 'Edit ' . $e->check_ref : 'New Check' }}"
      x-data="{ tol: @js($v('tolerance_m') !== null ? (float) $v('tolerance_m') : ''), lines: @js(array_values($lines)),
                diff(l) { const p = parseFloat(l.plan_distance), m = parseFloat(l.measured_distance); return (p > 0 && m > 0) ? Math.round((m - p) * 1000) / 1000 : null; },
                over(l) { const d = this.diff(l), t = parseFloat(this.tol); return d !== null && t >= 0 && !isNaN(t) && Math.abs(d) > t; } }">
    @csrf
    @if ($e) @method('PUT') @endif
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-file-signature"></i><span>Plan</span></div>
        <div class="form-group"><label>File No.</label><input type="text" name="file_number" value="{{ $v('file_number') }}" maxlength="100" placeholder="e.g. GKN-2148" /></div>
        <div class="form-group"><label>Survey Plan No.</label><input type="text" name="plan_no" value="{{ $v('plan_no') }}" maxlength="100" /></div>
        <div class="form-group"><label>Computation File No.</label><input type="text" name="computation_no" value="{{ $v('computation_no') }}" maxlength="30" placeholder="e.g. LPKN-2026-1" /></div>
        <div class="form-group">
            <label>Surveyor</label>
            <select name="surveyors_list_id" class="sv-searchable">
                <option value="">Select surveyor…</option>
                @foreach ($surveyors as $s)<option value="{{ $s->id }}" @selected((string) $v('surveyors_list_id') === (string) $s->id)>{{ $s->full_name }}{{ $s->rank ? ' · ' . $s->rank : '' }}</option>@endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Tolerance (m)</label>
            <input type="number" name="tolerance_m" step="0.001" min="0" x-model="tol" placeholder="Optional" />
            <p class="helper-text">A line differing by more than this is marked.</p>
        </div>
    </div>

    <div class="form-grid">
        <div class="sv-group"><i class="fas fa-ruler-combined"></i><span>Lines checked</span></div>
        <div class="form-group full">
            <div class="table-wrapper" style="margin:0;">
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>S/N</th><th>From Beacon *</th><th>To Beacon *</th><th>Distance on Plan (m) *</th><th>Distance Measured (m) *</th><th>Difference (m)</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="(l, i) in lines" :key="i">
                                <tr :style="over(l) ? 'background:#fef2f2;' : ''">
                                    <td x-text="i + 1"></td>
                                    <td><input type="text" :name="'lines[' + i + '][from]'" x-model="l.from" maxlength="30" style="text-transform:uppercase;" /></td>
                                    <td><input type="text" :name="'lines[' + i + '][to]'" x-model="l.to" maxlength="30" style="text-transform:uppercase;" /></td>
                                    <td><input type="number" step="0.001" min="0" :name="'lines[' + i + '][plan_distance]'" x-model="l.plan_distance" /></td>
                                    <td><input type="number" step="0.001" min="0" :name="'lines[' + i + '][measured_distance]'" x-model="l.measured_distance" /></td>
                                    <td :style="over(l) ? 'color:#b91c1c;font-weight:700;' : ''" x-text="diff(l) === null ? '—' : (diff(l) > 0 ? '+' : '') + diff(l).toFixed(3) + (over(l) ? ' (over)' : '')"></td>
                                    <td><button type="button" class="btn btn-danger btn-sm" title="Remove line" @click="lines.length > 1 ? lines.splice(i, 1) : (lines[0] = { from: '', to: '', plan_distance: '', measured_distance: '' })"><i class="fas fa-trash"></i></button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" style="margin-top:8px;"
                    @click="lines.push({ from: lines.length ? lines[lines.length - 1].to : '', to: '', plan_distance: '', measured_distance: '' })">
                <i class="fas fa-plus"></i> Add line
            </button>
            <p class="helper-text">A new line starts from the beacon the last one ended at.</p>
        </div>
    </div>

    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-clipboard-check"></i><span>Result</span></div>
        <div class="form-group">
            <label>Result <span class="required">*</span></label>
            <select name="result" required>
                <option value="">Select…</option>
                @foreach (PC::RESULTS as $res)<option value="{{ $res }}" @selected($v('result') === $res)>{{ $res }}</option>@endforeach
            </select>
        </div>
        <div class="form-group"><label>Date Checked <span class="required">*</span></label><input type="date" name="checked_at" max="{{ now()->toDateString() }}" value="{{ old('checked_at', $e?->checked_at?->format('Y-m-d') ?? now()->toDateString()) }}" required /></div>
        <div class="form-group span-3"><label>Remarks</label><textarea name="remarks" maxlength="1000">{{ $v('remarks') }}</textarea></div>
    </div>
    <div class="form-actions">
        <div class="left"><a class="btn btn-secondary" href="{{ route('survey-module.gkn.passing-checks') }}"><i class="fas fa-times"></i> Close</a></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $e ? 'Save Changes' : 'Save Check' }}</button></div>
    </div>
</form>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ $term }}" placeholder="Check ref, file no., plan no., computation file, surveyor…" style="{{ $field }}width:320px;" />
        <select name="result" style="{{ $field }}">
            <option value="">All results</option>
            @foreach (PC::RESULTS as $res)<option value="{{ $res }}" @selected(request('result') === $res)>{{ $res }}</option>@endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i> Search</button>
        @if (request()->hasAny(['q', 'result']))<a class="btn btn-outline btn-sm" href="{{ route('survey-module.gkn.passing-checks') }}">Clear</a>@endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead><tr><th>S/N</th><th>Check Ref</th><th>File No.</th><th>Plan No.</th><th>Computation File</th><th>Surveyor</th><th>Lines</th><th>Largest Difference</th><th>Result</th><th>Checked</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $ls = collect($row->lines ?? []);
                        $worst = $ls->sortByDesc(fn ($l) => abs($l['difference'] ?? 0))->first();
                    @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td><strong style="font-family:monospace;">{{ $row->check_ref }}</strong></td>
                        <td style="font-family:monospace;white-space:nowrap;">{{ $row->file_number ?: '—' }}</td>
                        <td>{{ $row->plan_no ?: '—' }}</td>
                        <td style="font-family:monospace;">{{ $row->computation_no ?: '—' }}</td>
                        <td>{{ $row->surveyor_name ?: '—' }}</td>
                        <td>{{ $ls->count() }}</td>
                        <td>{{ $worst ? ($worst['from'] . '–' . $worst['to'] . ': ' . sprintf('%+.3f', $worst['difference']) . ' m') : '—' }}</td>
                        <td><span class="status-badge {{ $resultTone[$row->result] ?? 'pending' }}"><span class="dot"></span>{{ $row->result }}</span></td>
                        <td style="white-space:nowrap;">{{ $row->checked_at?->format('d M Y') }}<div style="font-size:12px;color:var(--gray-500);">{{ $row->checker?->name }}</div></td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a href="{{ route('survey-module.gkn.passing-checks', ['edit' => $row->id]) }}" class="{{ $svItem }}">
                                        <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Open / Edit
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" style="text-align:center;padding:28px;color:var(--gray-500);">
                        <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                        {{ request()->hasAny(['q', 'result']) ? 'No check matches the search.' : 'No checks yet. Use New Check.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }}</span>
        <div class="pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
