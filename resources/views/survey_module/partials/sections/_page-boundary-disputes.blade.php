@php
    use App\Http\Controllers\Survey\BoundaryDisputeController as C;
    use App\Models\Survey\SurveyBoundaryDispute as BD;

    $editing   = $dispute->exists;
    $panelOpen = $editing || $errors->any();
    $svItem    = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $badge = fn ($s) => match ($s) { BD::RESOLVED => 'completed', BD::ESCALATED => 'rejected', BD::INVESTIGATING => 'review', default => 'pending' };

    $natVal   = old('nature', $dispute->nature);
    $natKnown = $natVal === null || $natVal === '' || $natVal === C::NATURE_OTHER || in_array($natVal, $natures, true);
    $natPick  = $natKnown ? $natVal : C::NATURE_OTHER;
    $natOther = old('nature_other', $natKnown ? '' : $natVal);
    $filters  = ['q', 'status', 'nature'];
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('bdPanel').classList.toggle('open')">
        <i class="fas fa-plus"></i> {{ $editing ? 'Edit Dispute' : 'Log Dispute' }}
    </button>
</div>

<div class="kpi-grid">
    @foreach ($stats as $s => $n)
        <a class="kpi-card" href="{{ route('survey-module.workflow.boundary-disputes.index', ['status' => $s]) }}" style="text-decoration:none;color:inherit;">
            <div class="kpi-label">{{ $s }}</div><div class="kpi-value">{{ number_format($n) }}</div>
        </a>
    @endforeach
</div>

<div class="farmer-entry-form {{ $panelOpen ? 'open' : '' }}" id="bdPanel">
    <form method="POST" action="{{ $editing ? route('survey-module.workflow.boundary-disputes.update', $dispute) : route('survey-module.workflow.boundary-disputes.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="form-grid">
            @if ($editing)
                <div class="form-group"><label>Dispute No</label><input type="text" value="{{ $dispute->dispute_ref }}" readonly style="background:var(--gray-100);" /></div>
            @endif

            @include('survey_module.partials._file_picker', [
                'id' => 'bd', 'name' => 'file_number', 'value' => $dispute->file_number, 'label' => 'File Number',
                'fill' => ['plot' => 'bdPlot', 'district' => 'bdDistrict', 'lga' => 'bdLga'],
            ])
            @include('survey_module.partials._file_picker', [
                'id' => 'bdOther', 'name' => 'other_file_number', 'value' => $dispute->other_file_number, 'label' => 'Other party\'s file (if any)',
            ])

            <div class="form-group full">
                <label>From a Register File line</label>
                <select name="survey_register_file_id">
                    <option value="">— Not linked —</option>
                    @foreach ($lines as $l)
                        <option value="{{ $l->id }}" @selected((string) old('survey_register_file_id', $dispute->survey_register_file_id) === (string) $l->id)>
                            {{ $l->register_ref }} · {{ $l->file_number }} ({{ ucfirst($l->purpose) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group"><label>Complainant <span class="required">*</span></label>
                <input type="text" name="complainant_name" maxlength="255" value="{{ old('complainant_name', $dispute->complainant_name) }}" required /></div>
            <div class="form-group"><label>Complainant Phone</label>
                <input type="text" name="complainant_phone" maxlength="50" value="{{ old('complainant_phone', $dispute->complainant_phone) }}" /></div>
            <div class="form-group"><label>Other Party</label>
                <input type="text" name="respondent_name" maxlength="255" value="{{ old('respondent_name', $dispute->respondent_name) }}" /></div>

            <div class="form-group"><label>Nature <span class="required">*</span></label>
                <select name="nature" id="bdNature" required>
                    <option value="">— Select —</option>
                    @foreach ($natures as $n)<option value="{{ $n }}" @selected($natPick === $n)>{{ $n }}</option>@endforeach
                    <option value="{{ C::NATURE_OTHER }}" @selected($natPick === C::NATURE_OTHER)>Other (specify)</option>
                </select></div>
            <div class="form-group" id="bdNatureOtherBox" @style(['display:none' => $natPick !== C::NATURE_OTHER])><label>Specify Nature <span class="required">*</span></label>
                <input type="text" name="nature_other" id="bdNatureOther" maxlength="100" value="{{ $natOther }}" @disabled($natPick !== C::NATURE_OTHER) /></div>
            <div class="form-group"><label>Date Received <span class="required">*</span></label>
                <input type="date" name="received_date" max="{{ now()->toDateString() }}" value="{{ old('received_date', optional($dispute->received_date)->format('Y-m-d')) }}" required /></div>

            {{-- Plot No., District, LGA; the location is composed from district + LGA on save. --}}
            <div class="form-group"><label>Plot No.</label><input type="text" name="plot_no" id="bdPlot" maxlength="100" value="{{ old('plot_no', $dispute->plot_no) }}" /></div>
            <div class="form-group"><label>District</label><input type="text" name="district" id="bdDistrict" maxlength="255" value="{{ old('district', $dispute->district) }}" /></div>
            <div class="form-group"><label>LGA</label><input type="text" name="lga" id="bdLga" maxlength="255" value="{{ old('lga', $dispute->lga) }}" /></div>

            <div class="form-group full"><label>Details</label>
                <textarea name="description" maxlength="4000">{{ old('description', $dispute->description) }}</textarea></div>
        </div>
        <div class="form-actions">
            <div class="left">
                @if ($editing)
                    <a class="btn btn-secondary" href="{{ route('survey-module.workflow.boundary-disputes.index') }}"><i class="fas fa-times"></i> Cancel Edit</a>
                @else
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('bdPanel').classList.remove('open')"><i class="fas fa-times"></i> Close</button>
                @endif
            </div>
            <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Log Dispute' }}</button></div>
        </div>
    </form>
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search no., file, parties, district…" style="{{ $inputStyle }}width:260px;" />
        <select name="status" style="{{ $inputStyle }}"><option value="">All Status</option>
            @foreach (BD::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select>
        <select name="nature" style="{{ $inputStyle }}"><option value="">All Natures</option>
            @foreach ($natures as $n)<option value="{{ $n }}" @selected(request('nature') === $n)>{{ $n }}</option>@endforeach</select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny($filters))<a class="btn btn-outline btn-sm" href="{{ route('survey-module.workflow.boundary-disputes.index') }}">Clear</a>@endif
    </div>
</form>

<div class="table-wrapper" x-data="{ st: { open: false, url: '', status: '', ref: '', needsNote: false } }">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th><th>Dispute No</th><th>Received</th><th>File No.</th><th>Parties</th><th>Nature</th>
                    <th>Plot No.</th><th>District</th><th>LGA</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($disputes as $d)
                    <tr>
                        <td>{{ $disputes->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $d->dispute_ref }}</strong>@if ($d->registerFile)<div style="font-size:11px;color:var(--gray-500);">{{ $d->registerFile->register_ref }}</div>@endif</td>
                        <td>{{ optional($d->received_date)->format('d M Y') }}</td>
                        <td style="font-family:monospace;">{{ $d->file_number ?: '—' }}@if ($d->other_file_number)<div style="font-size:11px;">vs {{ $d->other_file_number }}</div>@endif</td>
                        <td>{{ $d->complainant_name }}@if ($d->respondent_name)<div style="font-size:12px;color:var(--gray-500);">vs {{ $d->respondent_name }}</div>@endif</td>
                        <td>{{ $d->nature }}
                            @if ($d->notes)<details style="font-size:12px;color:var(--gray-600);"><summary>Notes</summary><div style="white-space:pre-line;">{{ $d->notes }}</div></details>@endif
                        </td>
                        <td>{{ $d->plot_no ?: '—' }}</td>
                        <td>{{ $d->district ?: '—' }}</td>
                        <td>{{ $d->lga ?: '—' }}</td>
                        <td><span class="status-badge {{ $badge($d->status) }}"><span class="dot"></span>{{ $d->status }}</span></td>
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
                                    @foreach (BD::NEXT[$d->status] ?? [] as $next)
                                        <button type="button" class="{{ $svItem }}"
                                                @click="open = false; st = { open: true, url: @js(route('survey-module.workflow.boundary-disputes.status', $d)), status: @js($next), ref: @js($d->dispute_ref), needsNote: @js($next !== BD::INVESTIGATING) }">
                                            <i data-lucide="{{ ['Under Investigation' => 'search', 'Resolved' => 'check-circle', 'Escalated' => 'arrow-up-circle'][$next] }}" class="w-4 h-4 text-gray-500"></i>
                                            {{ $next === BD::INVESTIGATING ? ($d->status === BD::ESCALATED ? 'Back to Investigation' : 'Start Investigation') : ($next === BD::RESOLVED ? 'Resolve' : 'Escalate') }}
                                        </button>
                                    @endforeach
                                    @unless ($d->isFrozen())
                                        <a href="{{ route('survey-module.workflow.boundary-disputes.edit', $d) }}" class="{{ $svItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                                    @endunless
                                    @if ($d->status === BD::OPEN)
                                        <form method="POST" action="{{ route('survey-module.workflow.boundary-disputes.destroy', $d) }}" onsubmit="return confirm('Delete {{ $d->dispute_ref }}? Only for a dispute logged in error.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</button>
                                        </form>
                                    @endif
                                    @if ($d->isFrozen())<div class="px-3 py-2 text-xs text-gray-500">Resolved {{ optional($d->resolved_at)->format('d M Y') }} · locked</div>@endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" style="text-align:center;padding:28px;color:var(--gray-500);">
                        {{ request()->hasAny($filters) ? 'No disputes match these filters.' : 'No boundary disputes logged.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $disputes->firstItem() ?? 0 }}–{{ $disputes->lastItem() ?? 0 }} of {{ number_format($disputes->total()) }} disputes</span>
        <div class="pagination">{{ $disputes->links() }}</div>
    </div>

    <div x-show="st.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,.4);" @keydown.escape.window="st.open = false">
        <form method="POST" :action="st.url" class="bg-white rounded-lg shadow-xl p-5" style="width:min(480px,92vw);" @click.outside="st.open = false">
            @csrf
            <input type="hidden" name="status" :value="st.status" />
            <h3 style="margin:0 0 6px;font-size:16px;font-weight:700;"><span x-text="st.ref"></span> → <span x-text="st.status"></span></h3>
            <label style="display:block;font-size:13px;margin:10px 0 4px;">
                <span x-text="st.status === 'Resolved' ? 'How was it resolved?' : (st.status === 'Escalated' ? 'Why escalate, and to whom?' : 'Note (optional)')"></span>
                <span class="required" x-show="st.needsNote">*</span>
            </label>
            <textarea name="note" rows="4" maxlength="2000" style="width:100%;border:1.5px solid var(--gray-300);border-radius:6px;padding:8px;" :required="st.needsNote"></textarea>
            <p class="helper-text" x-show="st.status === 'Resolved'">A resolved dispute is locked.</p>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
                <button type="button" class="btn btn-secondary btn-sm" @click="st.open = false">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var nat = document.getElementById('bdNature'), box = document.getElementById('bdNatureOtherBox'), other = document.getElementById('bdNatureOther');
        if (nat) nat.addEventListener('change', function () {
            var on = nat.value === @json(C::NATURE_OTHER);
            box.style.display = on ? '' : 'none'; other.disabled = !on; other.required = on;
        });
    })();
</script>
