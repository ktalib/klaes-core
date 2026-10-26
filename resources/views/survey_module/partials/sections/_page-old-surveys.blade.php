@php
    use App\Http\Controllers\Survey\OldSurveyController as C;
    use App\Models\Survey\SurveyOldSurvey as OS;

    $editing   = $old->exists;
    $panelOpen = $editing || $errors->any();
    $svItem    = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $chip = fn ($label, $c) => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">' . e($label) . '</span>';

    $srcVal   = old('record_source', $old->record_source);
    $srcKnown = $srcVal === null || $srcVal === '' || $srcVal === C::SOURCE_OTHER || in_array($srcVal, $sources, true);
    $srcPick  = $srcKnown ? $srcVal : C::SOURCE_OTHER;
    $srcOther = old('record_source_other', $srcKnown ? '' : $srcVal);
    $filters  = ['q', 'status'];
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('osPanel').classList.toggle('open')">
        <i class="fas fa-plus"></i> {{ $editing ? 'Edit Old Survey' : 'Record Old Survey' }}
    </button>
</div>

<div class="kpi-grid">
    @foreach ($stats as $s => $n)
        <a class="kpi-card" href="{{ route('survey-module.workflow.old-surveys.index', ['status' => $s]) }}" style="text-decoration:none;color:inherit;">
            <div class="kpi-label">{{ $s }}</div><div class="kpi-value">{{ number_format($n) }}</div>
        </a>
    @endforeach
</div>

<div class="farmer-entry-form {{ $panelOpen ? 'open' : '' }}" id="osPanel"
     data-sv-modal="{{ $editing ? 'Edit Old Survey ' . $old->old_survey_ref : 'Record Old Survey' }}"
     @if ($editing) data-sv-modal-close="{{ route('survey-module.workflow.old-surveys.index') }}" @endif>
    <form method="POST" action="{{ $editing ? route('survey-module.workflow.old-surveys.update', $old) : route('survey-module.workflow.old-surveys.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="form-grid cols-3">
            <div class="sv-group"><i class="fas fa-scroll"></i><span>Survey record</span></div>
            @if ($editing)
                <div class="form-group"><label>Reference</label><input type="text" value="{{ $old->old_survey_ref }}" readonly style="background:var(--gray-100);" /></div>
            @endif
            <div class="form-group"><label>Old Plan No.</label>
                <input type="text" name="old_plan_no" maxlength="100" value="{{ old('old_plan_no', $old->old_plan_no) }}" placeholder="As on the plan" /></div>
            <div class="form-group"><label>KN</label>
                <input type="text" name="kn_number" maxlength="100" value="{{ old('kn_number', $old->kn_number) }}" placeholder="e.g. KN 1234" /></div>
        </div>

        <div class="form-grid">
            <div class="sv-group"><i class="fas fa-folder-open"></i><span>File</span></div>
            @include('survey_module.partials._file_picker', [
                'id' => 'os', 'name' => 'file_number', 'value' => $old->file_number, 'label' => 'File Number',
                'fill' => ['plot' => 'osPlot', 'district' => 'osDistrict', 'lga' => 'osLga'],
            ])

            <div class="form-group full">
                <label>From a Register File line</label>
                <select name="survey_register_file_id">
                    <option value="">— Not linked —</option>
                    @foreach ($lines as $l)
                        <option value="{{ $l->id }}" @selected((string) old('survey_register_file_id', $old->survey_register_file_id) === (string) $l->id)>{{ $l->register_ref }} · {{ $l->file_number }} ({{ ucfirst($l->purpose) }})</option>
                    @endforeach
                </select>
                <p class="helper-text">Give at least one of: old plan no., KN, file number.</p>
            </div>

        </div>

        <div class="form-grid cols-3">
            <div class="sv-group"><i class="fas fa-drafting-compass"></i><span>Survey details</span></div>
            <div class="form-group"><label>Surveyor</label>
                <input type="text" name="surveyor_name" maxlength="255" value="{{ old('surveyor_name', $old->surveyor_name) }}" /></div>
            <div class="form-group"><label>Survey Date</label>
                <input type="date" name="survey_date" max="{{ now()->toDateString() }}" value="{{ old('survey_date', optional($old->survey_date)->format('Y-m-d')) }}" /></div>
            <div class="form-group"><label>Record found in</label>
                <select name="record_source" id="osSource">
                    <option value="">— Select —</option>
                    @foreach ($sources as $s)<option value="{{ $s }}" @selected($srcPick === $s)>{{ $s }}</option>@endforeach
                    <option value="{{ C::SOURCE_OTHER }}" @selected($srcPick === C::SOURCE_OTHER)>Other (specify)</option>
                </select></div>
            <div class="form-group" id="osSourceOtherBox" @style(['display:none' => $srcPick !== C::SOURCE_OTHER])><label>Specify <span class="required">*</span></label>
                <input type="text" name="record_source_other" id="osSourceOther" maxlength="100" value="{{ $srcOther }}" @disabled($srcPick !== C::SOURCE_OTHER) /></div>
        </div>

        <div class="form-grid cols-3">
            <div class="sv-group"><i class="fas fa-map-marker-alt"></i><span>Property</span></div>
            <div class="form-group"><label>Plot No.</label><input type="text" name="plot_no" id="osPlot" maxlength="100" value="{{ old('plot_no', $old->plot_no) }}" /></div>
            <div class="form-group"><label>District</label><input type="text" name="district" id="osDistrict" maxlength="255" value="{{ old('district', $old->district) }}" /></div>
            <div class="form-group"><label>LGA</label><input type="text" name="lga" id="osLga" maxlength="255" value="{{ old('lga', $old->lga) }}" /></div>
        </div>
        <div class="form-actions">
            <div class="left">
                @if ($editing)
                    <a class="btn btn-secondary" href="{{ route('survey-module.workflow.old-surveys.index') }}"><i class="fas fa-times"></i> Cancel Edit</a>
                @else
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('osPanel').classList.remove('open')"><i class="fas fa-times"></i> Close</button>
                @endif
            </div>
            <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Record Old Survey' }}</button></div>
        </div>
    </form>
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search plan no., KN, file, surveyor…" style="{{ $inputStyle }}width:260px;" />
        <select name="status" style="{{ $inputStyle }}"><option value="">All Verdicts</option>
            @foreach (OS::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny($filters))<a class="btn btn-outline btn-sm" href="{{ route('survey-module.workflow.old-surveys.index') }}">Clear</a>@endif
    </div>
</form>

<div class="table-wrapper" x-data="{ vd: { open: false, url: '', ref: '' } }">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th><th>Reference</th><th>Old Plan No.</th><th>KN</th><th>File No.</th><th>Surveyor</th><th>Survey Date</th>
                    <th>Plot No.</th><th>District</th><th>LGA</th><th>Record found in</th><th>Verdict</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($olds as $o)
                    <tr>
                        <td>{{ $olds->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $o->old_survey_ref }}</strong>@if ($o->registerFile)<div style="font-size:11px;color:var(--gray-500);">{{ $o->registerFile->register_ref }}</div>@endif</td>
                        <td style="font-family:monospace;">{{ $o->old_plan_no ?: '—' }}</td>
                        <td style="font-family:monospace;">{{ $o->kn_number ?: '—' }}</td>
                        <td style="font-family:monospace;">{{ $o->file_number ?: '—' }}</td>
                        <td>{{ $o->surveyor_name ?: '—' }}</td>
                        <td>{{ optional($o->survey_date)->format('d M Y') ?: '—' }}</td>
                        <td>{{ $o->plot_no ?: '—' }}</td>
                        <td>{{ $o->district ?: '—' }}</td>
                        <td>{{ $o->lga ?: '—' }}</td>
                        <td>{{ $o->record_source ?: '—' }}</td>
                        <td>{!! $chip($o->verification_status, OS::STATUS_COLOURS[$o->verification_status] ?? 'gray') !!}
                            @if ($o->findings)<details style="font-size:12px;color:var(--gray-600);"><summary>Findings</summary><div style="white-space:pre-line;">{{ $o->findings }}</div></details>@endif
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
                                    @unless ($o->isVerified())
                                        <button type="button" class="{{ $svItem }}" @click="open = false; vd = { open: true, url: @js(route('survey-module.workflow.old-surveys.verify', $o)), ref: @js($o->old_survey_ref) }">
                                            <i data-lucide="badge-check" class="w-4 h-4 text-gray-500"></i> Record Verdict
                                        </button>
                                        <a href="{{ route('survey-module.workflow.old-surveys.edit', $o) }}" class="{{ $svItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                                        <form method="POST" action="{{ route('survey-module.workflow.old-surveys.destroy', $o) }}" onsubmit="return confirm('Delete {{ $o->old_survey_ref }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</button>
                                        </form>
                                    @else
                                        <div class="px-3 py-2 text-xs text-gray-500">{{ $o->verification_status }} {{ optional($o->verified_at)->format('d M Y') }} · locked</div>
                                    @endunless
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" style="text-align:center;padding:28px;color:var(--gray-500);">
                        {{ request()->hasAny($filters) ? 'No old surveys match these filters.' : 'No old surveys recorded.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $olds->firstItem() ?? 0 }}–{{ $olds->lastItem() ?? 0 }} of {{ number_format($olds->total()) }} old surveys</span>
        <div class="pagination">{{ $olds->links() }}</div>
    </div>

    <div x-show="vd.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,.4);" @keydown.escape.window="vd.open = false">
        <form method="POST" :action="vd.url" class="bg-white rounded-lg shadow-xl p-5" style="width:min(480px,92vw);" @click.outside="vd.open = false">
            @csrf
            <h3 style="margin:0 0 10px;font-size:16px;font-weight:700;">Verdict · <span x-text="vd.ref"></span></h3>
            <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:10px;">
                @foreach ([OS::AUTHENTIC, OS::NOT_AUTHENTIC, OS::INCONCLUSIVE] as $v)
                    <label style="display:inline-flex;gap:6px;align-items:center;font-size:14px;"><input type="radio" name="verification_status" value="{{ $v }}" required> {{ $v }}</label>
                @endforeach
            </div>
            <label style="display:block;font-size:13px;margin-bottom:4px;">Findings <span class="required">*</span></label>
            <textarea name="findings" rows="4" maxlength="4000" required style="width:100%;border:1.5px solid var(--gray-300);border-radius:6px;padding:8px;"></textarea>
            <p class="helper-text">The record is locked once the verdict is given.</p>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
                <button type="button" class="btn btn-secondary btn-sm" @click="vd.open = false">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Verdict</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var s = document.getElementById('osSource'), box = document.getElementById('osSourceOtherBox'), other = document.getElementById('osSourceOther');
        if (s) s.addEventListener('change', function () {
            var on = s.value === @json(C::SOURCE_OTHER);
            box.style.display = on ? '' : 'none'; other.disabled = !on; other.required = on;
        });
    })();
</script>
