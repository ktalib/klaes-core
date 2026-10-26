@php
    use App\Models\Survey\SurveyOpRecord as OP;

    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $chip = fn ($label, $c) => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">' . e($label) . '</span>';
    $filters = ['q', 'verification', 'co_file'];
@endphp

@include('survey_module.partials._flash')

@if ($coFile)
    <div class="comp-type-note" style="margin-bottom:14px;"><i class="fas fa-folder"></i> OPs from CO file <strong>{{ $coFile->co_file_no }}</strong> · {{ $coFile->title }}.
        <a href="{{ route('survey-module.compensation.op-verification') }}">Show all</a></div>
@endif

<div class="kpi-grid">
    @foreach ($stats as $s => $n)
        <a class="kpi-card" href="{{ route('survey-module.compensation.op-verification', ['verification' => $s] + array_filter(['co_file' => request('co_file')])) }}" style="text-decoration:none;color:inherit;">
            <div class="kpi-label">{{ $s }}</div><div class="kpi-value">{{ number_format($n) }}</div>
        </a>
    @endforeach
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search OP no., case, beneficiary…" style="{{ $inputStyle }}width:250px;" />
        <select name="verification" style="{{ $inputStyle }}"><option value="">All</option>
            @foreach (OP::V_STATUSES as $s)<option value="{{ $s }}" @selected(request('verification') === $s)>{{ $s }}</option>@endforeach</select>
        <select name="co_file" style="{{ $inputStyle }}max-width:240px;"><option value="">All CO Files</option>
            @foreach ($coFiles as $f)<option value="{{ $f->id }}" @selected((string) request('co_file') === (string) $f->id)>{{ $f->co_file_no }}</option>@endforeach</select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny($filters))<a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.op-verification') }}">Clear</a>@endif
    </div>
</form>

<div class="table-wrapper" x-data="{ v: { open: false, url: '', action: '', ref: '' } }">
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>S/N</th><th>OP No.</th><th>CO File / Project</th><th>Case</th><th>Beneficiary</th><th>Scheme</th><th>Plot / Cash</th>
                    <th>OP Status</th><th>Verification</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($ops as $o)
                    @php $vs = $o->verificationStatus(); $ref = $o->op_number ?: 'OP #' . $o->id; @endphp
                    <tr>
                        <td>{{ $ops->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $o->op_number ?: '—' }}</strong></td>
                        <td>{{ $o->case?->project?->coFile?->co_file_no ?: '—' }}<div style="font-size:11px;color:var(--gray-500);">{{ $o->case?->project?->project_code }}</div></td>
                        <td>@if ($o->case)<a href="{{ route('survey-module.compensation.cases.show', $o->case) }}">{{ $o->case->case_ref }}</a>@else — @endif</td>
                        <td>{{ $o->beneficiary?->full_name ?: '—' }}</td>
                        <td>{{ $o->scheme_type === 'land' ? 'Plot for Plot' : 'Cash for Trees' }}</td>
                        <td>{{ $o->scheme_type === 'land' ? ($o->plot_or_cash_ref ?: '—') : '₦' . number_format((float) $o->cash_amount, 2) }}</td>
                        <td>{{ $o->status }}</td>
                        <td>{!! $chip($vs, OP::V_COLOURS[$vs] ?? 'gray') !!}
                            @if ($o->verification_notes)<details style="font-size:12px;color:var(--gray-600);"><summary>Notes</summary><div style="white-space:pre-line;">{{ $o->verification_notes }}</div></details>@endif
                        </td>
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
                                    @php $url = route('survey-module.compensation.op-verification.act', $o); @endphp
                                    @if (in_array($vs, [OP::V_PENDING, OP::V_RETURNED], true))
                                        <button type="button" class="{{ $svItem }}" @click="open = false; v = { open: true, url: @js($url), action: 'verify', ref: @js($ref) }"><i data-lucide="badge-check" class="w-4 h-4 text-gray-500"></i> Verify</button>
                                    @endif
                                    @if ($vs === OP::V_VERIFIED)
                                        <button type="button" class="{{ $svItem }}" @click="open = false; v = { open: true, url: @js($url), action: 'send', ref: @js($ref) }"><i data-lucide="send" class="w-4 h-4 text-gray-500"></i> Send to Deeds OP Verification</button>
                                    @endif
                                    @if (in_array($vs, [OP::V_PENDING, OP::V_VERIFIED], true))
                                        <button type="button" class="{{ $svItem }}" @click="open = false; v = { open: true, url: @js($url), action: 'return', ref: @js($ref) }"><i data-lucide="undo-2" class="w-4 h-4 text-gray-500"></i> Return for correction</button>
                                    @endif
                                    <a href="{{ route('survey-module.compensation.op', ['q' => $o->op_number]) }}" class="{{ $svItem }}"><i data-lucide="file-signature" class="w-4 h-4 text-gray-500"></i> Open in OP Allocation</a>
                                    @if ($vs === OP::V_SENT)<div class="px-3 py-2 text-xs text-gray-500">Sent {{ optional($o->sent_to_deeds_at)->format('d M Y') }} · locked</div>@endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="text-align:center;padding:28px;color:var(--gray-500);">
                        @if (request()->hasAny($filters)) No OPs match these filters.
                        @else No OPs yet. Generate them in <a href="{{ route('survey-module.compensation.land') }}">OP Allocation</a>.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $ops->firstItem() ?? 0 }}–{{ $ops->lastItem() ?? 0 }} of {{ number_format($ops->total()) }} OPs · "Sent to Deeds" is recorded here in Survey</span>
        <div class="pagination">{{ $ops->links() }}</div>
    </div>

    <div x-show="v.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,.4);" @keydown.escape.window="v.open = false">
        <form method="POST" :action="v.url" class="bg-white rounded-lg shadow-xl p-5" style="width:min(460px,92vw);" @click.outside="v.open = false">
            @csrf
            <input type="hidden" name="action" :value="v.action" />
            <h3 style="margin:0 0 6px;font-size:16px;font-weight:700;"><span x-text="v.ref"></span> · <span x-text="{ verify: 'Verify', send: 'Send to Deeds OP Verification', return: 'Return for correction' }[v.action]"></span></h3>
            <label style="display:block;font-size:13px;margin:10px 0 4px;"><span x-text="v.action === 'return' ? 'What must be corrected?' : 'Note (optional)'"></span> <span class="required" x-show="v.action === 'return'">*</span></label>
            <textarea name="note" rows="3" maxlength="2000" style="width:100%;border:1.5px solid var(--gray-300);border-radius:6px;padding:8px;" :required="v.action === 'return'"></textarea>
            <p class="helper-text" x-show="v.action === 'send'">Once sent, the OP is locked here.</p>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
                <button type="button" class="btn btn-secondary btn-sm" @click="v.open = false">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
            </div>
        </form>
    </div>
</div>
