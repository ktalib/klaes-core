@php
    use App\Http\Controllers\Survey\ComplaintController;
    use App\Models\Survey\SurveyComplaint;
    use App\Models\Survey\SurveyProject;

    $editing   = $complaint->exists;
    $panelOpen = $editing || $errors->any();
    $svItem    = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $badge = fn ($s) => match ($s) {
        SurveyComplaint::RESOLVED     => 'completed',
        SurveyComplaint::ADEQUATE     => 'approved',
        SurveyComplaint::OVERPAID, SurveyComplaint::UNDERPAID => 'rejected',
        SurveyComplaint::UNDER_REVIEW => 'review',
        default                       => 'pending',
    };
    $naira = fn ($v) => $v === null ? '—' : '₦' . number_format((float) $v, 2);

    // Section 3: the form's own category, else the tab the panel was opened from.
    $catPick = old('category', $complaint->category_key ?? $tab);
    $scheme  = old('compensation_type', $complaint->compensation_type);

    $ptVal   = old('project_type', $complaint->project_type);
    $ptKnown = $ptVal === null || $ptVal === '' || $ptVal === ComplaintController::PROJECT_TYPE_OTHER || in_array($ptVal, $projectTypes, true);
    $ptPick  = $ptKnown ? $ptVal : ComplaintController::PROJECT_TYPE_OTHER;
    $ptOther = old('project_type_other', $ptKnown ? '' : $ptVal);

    $docs = (array) old('supporting_documents', $complaint->supporting_documents ?? []);

    $tabIcons = [
        SurveyComplaint::CAT_INADEQUATE        => 'fa-coins',
        SurveyComplaint::CAT_MISIDENTIFICATION => 'fa-map-marker-alt',
        SurveyComplaint::CAT_BOUNDARY          => 'fa-draw-polygon',
    ];
    $filters = ['q', 'status', 'project'];
@endphp

@include('survey_module.partials._flash')

<style>
    .survey-proto .cmp-tabs { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 18px; border-bottom: 1px solid var(--gray-200, #e5e7eb); }
    .survey-proto .cmp-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; font-size: 14px; color: var(--gray-600, #4b5563);
        border-bottom: 2px solid transparent; margin-bottom: -1px; text-decoration: none; }
    .survey-proto .cmp-tabs a:hover { color: var(--gray-900, #111827); }
    .survey-proto .cmp-tabs a.on { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }
    .survey-proto .cmp-tabs .n { font-size: 12px; background: var(--gray-100, #f3f4f6); border-radius: 999px; padding: 1px 8px; color: var(--gray-600); }
    .survey-proto .cmp-choices { display: flex; flex-wrap: wrap; gap: 8px 18px; padding: 4px 0; }
    .survey-proto .cmp-choices label { display: inline-flex; align-items: flex-start; gap: 8px; font-weight: 400; cursor: pointer; }
    .survey-proto .form-group .cmp-choices input { width: auto; padding: 0; margin-top: 3px; }
    .survey-proto .cmp-cat-desc { grid-column: 1 / -1; font-size: 13px; color: var(--gray-600); font-style: italic; margin: -4px 0 0; }
</style>

<div class="page-header">
    <div></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-secondary" href="{{ route('survey-module.compensation.complaints.print-blank') }}" target="_blank">
            <i class="fas fa-print"></i> Print Blank Form
        </a>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('complaintPanel').classList.toggle('open')">
            <i class="fas fa-comment-medical"></i> {{ $editing ? 'Edit Complaint' : 'Log Complaint' }}
        </button>
    </div>
</div>

{{-- Section 3 of the printed form: one tab per category. --}}
<div class="cmp-tabs" role="tablist">
    @foreach (SurveyComplaint::CATEGORIES as $k => $label)
        <a href="{{ route('survey-module.compensation.complaints', ['tab' => $k]) }}" class="{{ $tab === $k ? 'on' : '' }}"
           role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}">
            <i class="fas {{ $tabIcons[$k] }}"></i> {{ $label }} <span class="n">{{ number_format($tabCounts[$k]) }}</span>
        </a>
    @endforeach
</div>

<div class="kpi-grid">
    @foreach ($stats as $s => $n)
        <a class="kpi-card" href="{{ route('survey-module.compensation.complaints', ['tab' => $tab, 'status' => $s]) }}" style="text-decoration:none;color:inherit;">
            <div class="kpi-label">{{ $s }}</div>
            <div class="kpi-value">{{ number_format($n) }}</div>
        </a>
    @endforeach
</div>

<div class="farmer-entry-form {{ $panelOpen ? 'open' : '' }}" id="complaintPanel"
     data-sv-modal="{{ $editing ? 'Edit Complaint ' . $complaint->complaint_ref : 'Compensation Complaint Form' }}"
     @if ($editing) data-sv-modal-close="{{ route('survey-module.compensation.complaints', ['tab' => $tab]) }}" @endif>
    <form method="POST" id="cmpForm" action="{{ $editing ? route('survey-module.compensation.complaints.update', $complaint) : route('survey-module.compensation.complaints.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="form-grid">
            {{-- ===== Section 1: Complainant details ===== --}}
            <div class="sv-group"><i class="fas fa-user"></i><span>Section 1: Complainant Details</span></div>
            @if ($editing)
                <div class="form-group full">
                    <label>Complaint No</label>
                    <input type="text" value="{{ $complaint->complaint_ref }}" readonly style="background:var(--gray-100);color:var(--gray-700);" />
                </div>
            @endif

            <div class="form-group full">
                <label>Farmer (registered beneficiary)</label>
                <select name="survey_beneficiary_id" id="cmpBeneficiary">
                    <option value="">— Not a registered farmer —</option>
                    @foreach ($beneficiaries as $b)
                        <option value="{{ $b->id }}" @selected((string) old('survey_beneficiary_id', $complaint->survey_beneficiary_id) === (string) $b->id)>
                            {{ $b->full_name }}{{ $b->phone ? ' · ' . $b->phone : '' }}{{ $b->case ? ' · ' . $b->case->case_ref : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="helper-text">Picking a farmer fills the name, phone, case, project, location and compensation from their records.</p>
            </div>

            <div class="form-group full">
                <label>Full Name of Farm/Property Owner <span class="required">*</span></label>
                <input type="text" name="complainant_name" id="cmpName" maxlength="255"
                       value="{{ old('complainant_name', $complaint->complainant_name) }}" required />
            </div>
            <div class="form-group">
                <label>Farm No.</label>
                <input type="text" name="farm_no" maxlength="50" value="{{ old('farm_no', $complaint->farm_no) }}" />
            </div>
            <div class="form-group">
                <label>Section</label>
                <input type="text" name="section_no" maxlength="50" value="{{ old('section_no', $complaint->section_no) }}" />
            </div>

            <div class="form-group">
                <label>Project</label>
                <select name="survey_project_id" id="cmpProject">
                    <option value="">— None —</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}" @selected((string) old('survey_project_id', $complaint->survey_project_id) === (string) $p->id)>{{ $p->project_code }} · {{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Case</label>
                <select name="survey_comp_case_id" id="cmpCase">
                    <option value="">— None —</option>
                    @foreach ($cases as $c)
                        <option value="{{ $c->id }}" @selected((string) old('survey_comp_case_id', $complaint->survey_comp_case_id) === (string) $c->id)>{{ $c->case_ref }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Project Type (Layout / Public Facility)</label>
                <select name="project_type" id="cmpProjectType">
                    <option value="">— Select —</option>
                    @foreach ($projectTypes as $t)
                        <option value="{{ $t }}" @selected($ptPick === $t)>{{ $t }}</option>
                    @endforeach
                    <option value="{{ ComplaintController::PROJECT_TYPE_OTHER }}" @selected($ptPick === ComplaintController::PROJECT_TYPE_OTHER)>Other public facility (specify)</option>
                </select>
            </div>
            <div class="form-group" id="cmpProjectTypeOtherBox" @style(['display:none' => $ptPick !== ComplaintController::PROJECT_TYPE_OTHER])>
                <label>Specify Project Type <span class="required">*</span></label>
                <input type="text" name="project_type_other" id="cmpProjectTypeOther" maxlength="100" value="{{ $ptOther }}"
                       placeholder="e.g. Hospital, Market" @disabled($ptPick !== ComplaintController::PROJECT_TYPE_OTHER) />
            </div>

            <div class="form-group">
                <label>Phone Number <span class="required">*</span></label>
                <input type="text" name="complainant_phone" id="cmpPhone" maxlength="50"
                       value="{{ old('complainant_phone', $complaint->complainant_phone) }}" required />
            </div>
            <div class="form-group">
                <label>Email Address (Optional)</label>
                <input type="email" name="complainant_email" maxlength="255" value="{{ old('complainant_email', $complaint->complainant_email) }}" />
            </div>
            <div class="form-group">
                <label>Name of Authorised Representative (if applicable)</label>
                <input type="text" name="representative_name" id="cmpRep" maxlength="255" value="{{ old('representative_name', $complaint->representative_name) }}" />
            </div>
            <div class="form-group">
                <label>Relationship to Owner</label>
                <input type="text" name="representative_relationship" maxlength="100" value="{{ old('representative_relationship', $complaint->representative_relationship) }}"
                       placeholder="Required when a representative is named" />
            </div>
        </div>

        <div class="form-grid">
            {{-- ===== Section 2: Property & compensation ===== --}}
            <div class="sv-group"><i class="fas fa-map"></i><span>Section 2: Property &amp; Compensation Details</span></div>
            <div class="form-group">
                <label>File Reference / CO Number <span class="required">*</span></label>
                <input type="text" name="file_reference" id="cmpFileRef" maxlength="100" value="{{ old('file_reference', $complaint->file_reference) }}" required />
                <p class="helper-text" id="cmpFileRefNote" style="display:none;">From the project's CO file; locked.</p>
            </div>
            <div class="form-group">
                <label>Map Sheet No (MS No) <span class="required">*</span></label>
                <input type="text" name="map_sheet_no" maxlength="50" value="{{ old('map_sheet_no', $complaint->map_sheet_no) }}" required />
            </div>
            <div class="form-group">
                <label>Location / District <span class="required">*</span></label>
                <input type="text" name="district" id="cmpDistrict" maxlength="255" value="{{ old('district', $complaint->district) }}" required />
            </div>
            <div class="form-group">
                <label>LGA</label>
                <input type="text" name="lga" id="cmpLga" maxlength="255" value="{{ old('lga', $complaint->lga) }}" />
            </div>

            <div class="form-group full">
                <label>Compensation Type <span class="required">*</span></label>
                <select name="compensation_type" id="cmpScheme" required>
                    <option value="">— Select —</option>
                    @foreach (SurveyComplaint::COMP_TYPES as $k => $l)
                        <option value="{{ $k }}" @selected($scheme === $k)>{{ $l }}</option>
                    @endforeach
                </select>
                <p class="helper-text" id="cmpSchemeNote" style="display:none;">Set by the project's scheme; locked.</p>
            </div>
            <div class="form-group" data-scheme="{{ SurveyProject::SCHEME_MONETARY }}">
                <label>A — Original Compensation Amount (₦) <span class="required">*</span></label>
                <input type="number" step="0.01" min="0" name="original_amount" id="cmpOrigAmount" value="{{ old('original_amount', $complaint->original_amount) }}" required />
            </div>
            <div class="form-group" data-scheme="{{ SurveyProject::SCHEME_LAND }}">
                <label>B — No. of Plots (Land to Land) <span class="required">*</span></label>
                <input type="number" step="1" min="0" name="original_plots" id="cmpOrigPlots" value="{{ old('original_plots', $complaint->original_plots) }}" required />
                <p class="helper-text">From a case: the farmer's half of the 50:50 split.</p>
            </div>
        </div>

        <div class="form-grid">
            {{-- ===== Section 3: Complaint category (select one) ===== --}}
            <div class="sv-group"><i class="fas fa-comment-dots"></i><span>Section 3: Complaint Category (Select One)</span></div>
            <div class="form-group full">
                <div class="cmp-choices" role="radiogroup">
                    @foreach (SurveyComplaint::CATEGORIES as $k => $label)
                        <label><input type="radio" name="category" value="{{ $k }}" @checked($catPick === $k) required /> Category {{ $loop->iteration }}: {{ $label }}</label>
                    @endforeach
                </div>
            </div>

            {{-- Category 1 --}}
            <p class="cmp-cat-desc" data-cat="{{ SurveyComplaint::CAT_INADEQUATE }}">
                The assessed value of the economic trees, crops, or structures does not reflect the current market value or the actual quantity on the land.
            </p>
            <div class="form-group full" data-cat="{{ SurveyComplaint::CAT_INADEQUATE }}">
                <label>Details of Discrepancy <span class="required">*</span></label>
                <textarea name="description" maxlength="4000" required>{{ old('description', $catPick === SurveyComplaint::CAT_INADEQUATE ? $complaint->description : '') }}</textarea>
            </div>
            <div class="form-group" data-cat="{{ SurveyComplaint::CAT_INADEQUATE }}" data-scheme="{{ SurveyProject::SCHEME_MONETARY }}">
                <label>A — Expected/Correct Valuation (₦) <span class="required">*</span></label>
                <input type="number" step="0.01" min="0" name="expected_amount" value="{{ old('expected_amount', $complaint->expected_amount) }}" required />
            </div>
            <div class="form-group" data-cat="{{ SurveyComplaint::CAT_INADEQUATE }}" data-scheme="{{ SurveyProject::SCHEME_LAND }}">
                <label>B — Expected/Correct No. of Plots <span class="required">*</span></label>
                <input type="number" step="1" min="0" name="expected_plots" value="{{ old('expected_plots', $complaint->expected_plots) }}" required />
            </div>

            {{-- Category 2 --}}
            <p class="cmp-cat-desc" data-cat="{{ SurveyComplaint::CAT_MISIDENTIFICATION }}">
                The farm owner assigned an authorized person to show/identify the farmland, and this person incorrectly identified the wrong farm/plot for compensation.
            </p>
            <div class="form-group" data-cat="{{ SurveyComplaint::CAT_MISIDENTIFICATION }}">
                <label>Name of Field Officer</label>
                <input type="text" name="field_officer_name" maxlength="255" value="{{ old('field_officer_name', $complaint->field_officer_name) }}" />
            </div>
            <div class="form-group" data-cat="{{ SurveyComplaint::CAT_MISIDENTIFICATION }}">
                <label>Correct Plot/Coordinates of the Actual Farm</label>
                <input type="text" name="correct_plot_coordinates" maxlength="1000" value="{{ old('correct_plot_coordinates', $complaint->correct_plot_coordinates) }}" />
            </div>
            <div class="form-group full" data-cat="{{ SurveyComplaint::CAT_MISIDENTIFICATION }}">
                <label>Details of the Misidentification <span class="required">*</span></label>
                <textarea name="description" maxlength="4000" required>{{ old('description', $catPick === SurveyComplaint::CAT_MISIDENTIFICATION ? $complaint->description : '') }}</textarea>
            </div>

            {{-- Category 3 --}}
            <div class="form-group full" data-cat="{{ SurveyComplaint::CAT_BOUNDARY }}">
                <label>Type of Boundary Dispute <span class="required">*</span></label>
                <div class="cmp-choices" role="radiogroup">
                    @foreach (SurveyComplaint::BOUNDARY_TYPES as $bt)
                        <label><input type="radio" name="boundary_type" value="{{ $bt }}" @checked(old('boundary_type', $complaint->boundary_type) === $bt) required /> {{ $bt }}</label>
                    @endforeach
                </div>
            </div>
            <div class="form-group full" data-cat="{{ SurveyComplaint::CAT_BOUNDARY }}">
                <label>Name of Affected Neighbor/Party (if applicable)</label>
                <input type="text" name="affected_party" maxlength="255" value="{{ old('affected_party', $complaint->affected_party) }}" />
            </div>
            <div class="form-group full" data-cat="{{ SurveyComplaint::CAT_BOUNDARY }}">
                <label>Details of the Encroachment/Dispute <span class="required">*</span></label>
                <textarea name="description" maxlength="4000" required>{{ old('description', $catPick === SurveyComplaint::CAT_BOUNDARY ? $complaint->description : '') }}</textarea>
            </div>
        </div>

        <div class="form-grid">
            {{-- ===== Section 4: Supporting documents ===== --}}
            <div class="sv-group"><i class="fas fa-paperclip"></i><span>Section 4: Supporting Documents Attached</span></div>
            <div class="form-group full">
                <div class="cmp-choices">
                    @foreach (SurveyComplaint::DOCUMENTS as $k => $l)
                        <label><input type="checkbox" name="supporting_documents[]" value="{{ $k }}" @checked(in_array($k, $docs, true))
                                      @if ($k === 'other') id="cmpDocOther" @endif /> {{ $l }}</label>
                    @endforeach
                </div>
            </div>
            <div class="form-group full" id="cmpOtherDocsBox" @style(['display:none' => ! in_array('other', $docs, true)])>
                <label>Other relevant documents / reference <span class="required">*</span></label>
                <input type="text" name="other_documents" id="cmpOtherDocs" maxlength="500" value="{{ old('other_documents', $complaint->other_documents) }}"
                       @disabled(! in_array('other', $docs, true)) />
            </div>

            {{-- ===== Official use ===== --}}
            <div class="sv-group"><i class="fas fa-stamp"></i><span>For Official Use Only (Survey Department)</span></div>
            <div class="form-group">
                <label>Date Received <span class="required">*</span></label>
                <input type="date" name="received_date" max="{{ now()->toDateString() }}"
                       value="{{ old('received_date', optional($complaint->received_date)->format('Y-m-d')) }}" required />
            </div>
            <div class="form-group">
                <label>Assigned To</label>
                <input type="text" name="assigned_to" maxlength="255" value="{{ old('assigned_to', $complaint->assigned_to) }}" />
            </div>
        </div>

        <div class="form-actions">
            <div class="left">
                @if ($editing)
                    <a class="btn btn-secondary" href="{{ route('survey-module.compensation.complaints', ['tab' => $tab]) }}"><i class="fas fa-times"></i> Cancel Edit</a>
                @else
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('complaintPanel').classList.remove('open')"><i class="fas fa-times"></i> Close</button>
                @endif
            </div>
            <div class="right">
                <button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Log Complaint' }}</button>
            </div>
        </div>
    </form>
</div>

<form method="GET" class="table-toolbar">
    <input type="hidden" name="tab" value="{{ $tab }}" />
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search no., name, phone, farm, file ref, MS no…" style="{{ $inputStyle }}width:280px;" />
        <select name="status" style="{{ $inputStyle }}">
            <option value="">All Status</option>
            @foreach (SurveyComplaint::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="project" style="{{ $inputStyle }}max-width:240px;">
            <option value="">All Projects</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" @selected((string) request('project') === (string) $p->id)>{{ $p->project_code }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny($filters))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.complaints', ['tab' => $tab]) }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper" x-data="{ st: { open: false, url: '', status: '', ref: '', needsNote: false } }">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Complaint No</th>
                    <th>Date Received</th>
                    <th>Complainant</th>
                    <th>Project / Case</th>
                    <th>File Ref / MS No</th>
                    <th>District</th>
                    <th>LGA</th>
                    <th>{{ ['inadequate' => 'Original → Expected', 'misidentification' => 'Field Officer', 'boundary' => 'Dispute Type'][$tab] }}</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($complaints as $c)
                    <tr>
                        <td>{{ $complaints->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $c->complaint_ref }}</strong></td>
                        <td>{{ optional($c->received_date)->format('d M Y') }}</td>
                        <td>
                            {{ $c->complainant_name }}
                            <div style="font-size:12px;color:var(--gray-500);">
                                {{ collect([$c->complainant_phone, $c->farm_no ? 'Farm ' . $c->farm_no : null, $c->section_no ? 'Sec. ' . $c->section_no : null])->filter()->implode(' · ') }}
                            </div>
                        </td>
                        <td>
                            {{ $c->project?->project_code ?: '—' }}
                            @if ($c->project_type)<div style="font-size:12px;color:var(--gray-500);">{{ $c->project_type }}</div>@endif
                            @if ($c->case)<div style="font-size:12px;"><a href="{{ route('survey-module.compensation.cases.show', $c->case) }}">{{ $c->case->case_ref }}</a></div>@endif
                        </td>
                        <td>{{ $c->file_reference ?: '—' }}@if ($c->map_sheet_no)<div style="font-size:12px;color:var(--gray-500);">MS {{ $c->map_sheet_no }}</div>@endif</td>
                        <td>{{ $c->district ?: '—' }}</td>
                        <td>{{ $c->lga ?: '—' }}</td>
                        <td>
                            @if ($tab === SurveyComplaint::CAT_INADEQUATE)
                                @if ($c->isLandToLand())
                                    {{ $c->original_plots ?? '—' }} → {{ $c->expected_plots ?? '—' }} plots
                                @else
                                    {{ $naira($c->original_amount) }} → {{ $naira($c->expected_amount) }}
                                @endif
                            @elseif ($tab === SurveyComplaint::CAT_MISIDENTIFICATION)
                                {{ $c->field_officer_name ?: '—' }}
                            @else
                                {{ $c->boundary_type ?: '—' }}@if ($c->affected_party)<div style="font-size:12px;color:var(--gray-500);">{{ $c->affected_party }}</div>@endif
                            @endif
                            @if ($c->resolution)
                                <details style="font-size:12px;color:var(--gray-600);margin-top:4px;"><summary>Remarks</summary><div style="white-space:pre-line;">{{ $c->resolution }}</div></details>
                            @endif
                        </td>
                        <td><span class="status-badge {{ $badge($c->status) }}"><span class="dot"></span>{{ $c->status }}</span></td>
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
                                    @foreach (SurveyComplaint::NEXT[$c->status] ?? [] as $next)
                                        <button type="button" class="{{ $svItem }}"
                                                @click.stop="open = false; st = { open: true, url: @js(route('survey-module.compensation.complaints.status', $c)), status: @js($next), ref: @js($c->complaint_ref), needsNote: @js($next !== SurveyComplaint::UNDER_REVIEW) }">
                                            <i data-lucide="{{ [
                                                SurveyComplaint::UNDER_REVIEW => 'search',
                                                SurveyComplaint::OVERPAID     => 'trending-up',
                                                SurveyComplaint::UNDERPAID    => 'trending-down',
                                                SurveyComplaint::ADEQUATE     => 'badge-check',
                                                SurveyComplaint::RESOLVED     => 'check-circle',
                                            ][$next] }}" class="w-4 h-4 text-gray-500"></i>
                                            {{ $next === SurveyComplaint::UNDER_REVIEW ? ($c->status === SurveyComplaint::PENDING ? 'Start Review' : 'Back to Review') : ($next === SurveyComplaint::RESOLVED ? 'Resolve' : 'Mark ' . $next) }}
                                        </button>
                                    @endforeach
                                    <a href="{{ route('survey-module.compensation.complaints.print', $c) }}" target="_blank" class="{{ $svItem }}">
                                        <i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> Print Form
                                    </a>
                                    @unless ($c->isFrozen())
                                        <a href="{{ route('survey-module.compensation.complaints.edit', $c) }}" class="{{ $svItem }}">
                                            <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                        </a>
                                    @endunless
                                    @if ($c->status === SurveyComplaint::PENDING)
                                        <form method="POST" action="{{ route('survey-module.compensation.complaints.destroy', $c) }}"
                                              onsubmit="return confirm('Delete {{ $c->complaint_ref }}? Only for a complaint logged in error.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</button>
                                        </form>
                                    @endif
                                    @if ($c->isFrozen())
                                        <div class="px-3 py-2 text-xs text-gray-500">Resolved {{ optional($c->resolved_at)->format('d M Y') }} · locked</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-comments" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny($filters))
                                No complaints match these filters.
                            @else
                                No {{ SurveyComplaint::CATEGORIES[$tab] }} complaints logged. Use <strong>Log Complaint</strong> above.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $complaints->firstItem() ?? 0 }}–{{ $complaints->lastItem() ?? 0 }} of {{ number_format($complaints->total()) }} complaints</span>
        <div class="pagination">{{ $complaints->links() }}</div>
    </div>

    {{-- Status change, with the remark every step past Under Review requires. --}}
    <div x-show="st.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,.4);" @keydown.escape.window="st.open = false">
        <form method="POST" :action="st.url" class="bg-white rounded-lg shadow-xl p-5" style="width:min(480px,92vw);" @click.outside="st.open = false">
            @csrf
            <input type="hidden" name="status" :value="st.status" />
            <h3 style="margin:0 0 6px;font-size:16px;font-weight:700;">
                <span x-text="st.ref"></span> → <span x-text="st.status"></span>
            </h3>
            <label style="display:block;font-size:13px;margin:10px 0 4px;">
                <span x-text="st.status === 'Resolved' ? 'How was it resolved?' : (st.needsNote ? 'Remarks (what the review found)' : 'Remarks (optional)')"></span>
                <span class="required" x-show="st.needsNote">*</span>
            </label>
            <textarea name="resolution" rows="4" maxlength="4000" style="width:100%;border:1.5px solid var(--gray-300);border-radius:6px;padding:8px;" :required="st.needsNote"></textarea>
            <p class="helper-text" x-show="st.status === 'Resolved'">A resolved complaint is locked: it can no longer be edited or deleted.</p>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
                <button type="button" class="btn btn-secondary btn-sm" @click="st.open = false">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var form = document.getElementById('cmpForm');
        if (!form) return;
        var META = @json($meta);
        var $ = function (id) { return document.getElementById(id); };
        var ben = $('cmpBeneficiary'), kase = $('cmpCase'), project = $('cmpProject'), scheme = $('cmpScheme'), fileRef = $('cmpFileRef');
        var FIELDS = { name: $('cmpName'), phone: $('cmpPhone'), district: $('cmpDistrict'), lga: $('cmpLga'),
                       amount: $('cmpOrigAmount'), plots: $('cmpOrigPlots'), fileRef: fileRef, scheme: scheme };
        var filled = {};   // what the last pick put in, so a changed pick clears it

        // Show the chosen category's block and the chosen compensation type's figures;
        // hidden inputs are disabled so they neither submit nor block the browser check.
        function sync() {
            var cat = (form.querySelector('input[name="category"]:checked') || {}).value || '';
            var sch = scheme.value;
            form.querySelectorAll('[data-cat], [data-scheme]').forEach(function (el) {
                var on = (!el.dataset.cat || el.dataset.cat === cat) && (!el.dataset.scheme || el.dataset.scheme === sch);
                el.style.display = on ? '' : 'none';
                el.querySelectorAll('input, select, textarea').forEach(function (i) { i.disabled = !on; });
            });
        }

        function lookup() {
            var b = META.beneficiaries[ben.value] || null;
            var caseId = b && b.case ? b.case : kase.value;
            var c = META.cases[caseId] || null;
            var p = META.projects[c ? c.project : project.value] || null;
            return { b: b, c: c, p: p, caseId: b && b.case ? String(b.case) : null };
        }

        function apply(initial) {
            var m = lookup();
            if (m.caseId) kase.value = m.caseId;
            if (m.c && m.c.project) project.value = String(m.c.project);

            var vals = {
                name: m.b && m.b.name, phone: m.b && m.b.phone,
                district: (m.c && m.c.district) || (m.p && m.p.district),
                lga: (m.c && m.c.lga) || (m.p && m.p.lga),
                scheme: (m.c && m.c.scheme) || (m.p && m.p.scheme),
                amount: m.c && m.c.amount, plots: m.c && m.c.plots,
                fileRef: m.p && m.p.fileRef
            };
            Object.keys(FIELDS).forEach(function (k) {
                var el = FIELDS[k], v = vals[k] == null ? '' : String(vals[k]);
                if (!initial) {
                    if (filled[k] && el.value === filled[k]) el.value = '';
                    if (v && (!el.value || k === 'scheme' || k === 'fileRef')) el.value = v;
                }
                filled[k] = v;
            });

            // The server takes these from the linked record, so lock them while one is picked.
            kase.disabled = !!m.caseId;
            project.disabled = !!(m.c && m.c.project);
            scheme.disabled = !!vals.scheme;
            fileRef.readOnly = !!vals.fileRef;
            $('cmpSchemeNote').style.display = vals.scheme ? '' : 'none';
            $('cmpFileRefNote').style.display = vals.fileRef ? '' : 'none';
            sync();
        }

        [ben, kase, project].forEach(function (el) { el.addEventListener('change', function () { apply(false); }); });
        scheme.addEventListener('change', sync);
        form.querySelectorAll('input[name="category"]').forEach(function (r) { r.addEventListener('change', sync); });
        // Disabled selects do not submit; the server re-derives them, but send the visible values anyway.
        form.addEventListener('submit', function () { [kase, project, scheme].forEach(function (el) { el.disabled = false; }); });
        apply(true);

        var pt = $('cmpProjectType'), ptBox = $('cmpProjectTypeOtherBox'), ptOther = $('cmpProjectTypeOther');
        pt.addEventListener('change', function () {
            var on = pt.value === @json(ComplaintController::PROJECT_TYPE_OTHER);
            ptBox.style.display = on ? '' : 'none';
            ptOther.disabled = !on;
            ptOther.required = on;
            if (!on) ptOther.value = '';
        });
        var docOther = $('cmpDocOther'), docBox = $('cmpOtherDocsBox'), docText = $('cmpOtherDocs');
        docOther.addEventListener('change', function () {
            docBox.style.display = docOther.checked ? '' : 'none';
            docText.disabled = !docOther.checked;
            docText.required = docOther.checked;
        });
        if (docOther.checked) docText.required = true;
        if (pt.value === @json(ComplaintController::PROJECT_TYPE_OTHER)) ptOther.required = true;
    })();
</script>
