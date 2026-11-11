@php
    use App\Http\Controllers\Survey\CaseController;
    use App\Models\Survey\SurveyProject;

    /**
     * The six-step case register.
     *
     * All six steps live in one POST; the stepper only shows and hides them, and
     * CaseController::store()/update() re-validates the lot. Nothing here carries
     * a scheme_type field: the scheme is inherited from the selected project and
     * copied server-side, so it cannot be chosen — or forged — on the case.
     */
    $editing = $case->exists;

    // Known once a project is chosen (edit, ?project=, or a bounced submission).
    $scheme     = $case->scheme_type ?: null;
    $isMonetary = $scheme === SurveyProject::SCHEME_MONETARY;
    $isLand     = $scheme === SurveyProject::SCHEME_LAND;

    // An existing case can never change scheme, so only its own panel is rendered.
    // While creating, both panels exist but exactly one is ever .active.
    $showMonetaryPanel = ! $editing || $isMonetary;
    $showLandPanel     = ! $editing || $isLand;

    // Location to backfill when a project is picked (user, 2026-10-30): the project's own, and for each part it
    // leaves empty, its TP No. on the Public Layout Plans register, then its CO file.
    $projects->loadMissing('coFile');
    $tpRows = \App\Models\Survey\SurveyTpNumber::whereIn('tp_no', $projects->pluck('plan_no')->filter()->unique()->values()->all() ?: [''])
        ->get()->keyBy(fn ($t) => mb_strtoupper($t->tp_no));
    $projectLocation = function ($p) use ($tpRows) {
        $sources = array_filter([$p, $p->plan_no ? ($tpRows[mb_strtoupper($p->plan_no)] ?? null) : null, $p->coFile]);
        $pick = function (string $col, ?string $otherCol = null) use ($sources) {
            foreach ($sources as $s) {
                $v = \App\Support\AddressBuilder::split($s->{$col} ?? null);
                if ($v) return [$v, $otherCol ? ($s->{$otherCol} ?? null) : null];
            }
            return [[], null];
        };
        [$street, $streetOther] = $pick('prop_street', 'prop_street_other');
        [$district, $districtOther] = $pick('prop_district', 'prop_district_other');
        [$lga] = $pick('prop_lga');
        [$state] = $pick('prop_state');
        return ['street' => $street, 'street_other' => $streetOther, 'district' => $district, 'district_other' => $districtOther,
                'lga' => $lga, 'state' => $state[0] ?? null];
    };

    $projectMeta = $projects->mapWithKeys(fn ($p) => [$p->id => [
        'name'     => $p->name,
        'code'     => $p->project_code,
        'scheme'   => $p->scheme_type,
        'purpose'  => $p->purpose,
        'label'    => $p->scheme_label,
        'location' => $projectLocation($p),
        // Picking a project shows its CO file (user, 2026-11-03).
        'co'       => $p->coFile ? trim($p->coFile->co_file_no . ($p->coFile->title ? ' · ' . $p->coFile->title : '')) : '',
    ]])->all();

    // Catalogue rates per name and Category (Mature / Immature / Seedling, Kano schedule 2026-11-01), for the price
    // prefill: name => [Category => rate]. A name in both lists takes the economic tree's rate (per stand), as findFor().
    $treePrices = [];
    foreach ($treeTypes->sortBy(fn ($t) => $t->category === \App\Models\Survey\SurveyTreeType::ECONOMIC_TREE ? 1 : 0) as $t) {
        $treePrices[$t->name][$t->maturity ?: 'Mature'] = $t->default_unit_price;
    }
    $treeMaturities = \App\Models\Survey\SurveyTreeType::MATURITIES;
    // Names that are only Cash Crops (priced per m²), for the Area (m²) label on the tree line.
    $cashCropNames = $treeTypes->groupBy('name')
        ->filter(fn ($g) => $g->every(fn ($t) => $t->category === \App\Models\Survey\SurveyTreeType::CASH_CROP))
        ->keys()->values()->all();

    // Rows already on the case, or whatever bounced back from validation.
    $benRows = old('beneficiaries', $editing
        ? $case->beneficiaries->map(fn ($b) => [
            'id' => $b->id, 'full_name' => $b->full_name, 'phone' => $b->phone,
            'nin' => $b->nin, 'status' => $b->status, 'farm_id_no' => $b->farm_id_no, 'farm_no' => $b->farm_no,
            'survey_farmer_id' => $b->survey_farmer_id,
        ])->all()
        : []);
    $farmerIdNos = \App\Models\Survey\SurveyFarmer::whereIn(
        'id',
        collect($benRows)->pluck('survey_farmer_id')->filter()->unique()->values()->all() ?: [0]
    )->pluck('farmer_id_no', 'id');
    $farmIdService = app(\App\Services\Survey\FarmIds::class);
    $nextIdPreview = [
        'farmer' => $farmIdService->nextFarmerIdPreview(),
        'farm' => $farmIdService->nextFarmIdPreview(),
        'farmNo' => (int) $case->beneficiaries()->max('farm_no') + 1,
    ];
    $officerForPreview = trim((string) old('survey_officer', $case->survey_officer));
    $nextIdPreview['section'] = $case->section_no ?: ($officerForPreview !== '' ? $farmIdService->nextSectionPreview($officerForPreview) : null);

    $treeRows = old('trees', $editing
        ? $case->trees->map(fn ($t) => [
            'id' => $t->id, 'tree_type' => $t->tree_type, 'maturity' => $t->maturity ?: 'Mature',
            'quantity' => $t->quantity, 'unit_price' => $t->unit_price,
        ])->all()
        : []);

    $statusLabel = fn ($s) => $s === 'Review' ? 'Pending Review' : $s;
@endphp

<style>
    .case-id-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin: 18px 0 22px;
    }
    .case-id-card {
        min-width: 0;
        padding: 14px 16px;
        border: 1px solid;
        border-radius: 10px;
        background: #fff;
    }
    .case-id-section { border-color: #bfdbfe; background: #eff6ff; color: #1d4ed8; }
    .case-id-farmer { border-color: #bbf7d0; background: #f0fdf4; color: #15803d; }
    .case-id-farm { border-color: #fde68a; background: #fffbeb; color: #b45309; }
    .case-id-farm-no { border-color: #e9d5ff; background: #faf5ff; color: #7e22ce; }
    .case-id-label {
        display: block;
        margin-bottom: 7px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .case-id-card > strong { font-size: 18px; }
    .case-id-values { display: flex; flex-wrap: wrap; gap: 5px; }
    .case-id-value, .case-id-placeholder {
        overflow-wrap: anywhere;
        font-size: 13px;
        font-weight: 600;
    }
    @media (max-width: 767px) {
        .case-id-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>

@include('survey_module.partials._flash')

<form method="POST" id="caseRegisterForm"
      action="{{ $editing
                  ? route('survey-module.compensation.cases.update', $case)
                  : route('survey-module.compensation.cases.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="form-container">
        <!-- STEPPER -->
        <div class="form-stepper" id="caseStepper">
            <div class="step-indicator active" data-step="0" onclick="caseReg.go(0)">
                <span class="num">1</span>
                <span class="label">General</span>
                <span class="sep">›</span>
            </div>
            <div class="step-indicator" data-step="1" onclick="caseReg.go(1)">
                <span class="num">2</span>
                <span class="label">Beneficiaries</span>
                <span class="sep">›</span>
            </div>
            <div class="step-indicator" data-step="2" onclick="caseReg.go(2)">
                <span class="num">3</span>
                <span class="label">Farm Info</span>
                <span class="sep">›</span>
            </div>
            <div class="step-indicator" data-step="3" onclick="caseReg.go(3)">
                <span class="num">4</span>
                <span class="label">Trees</span>
                <span class="sep">›</span>
            </div>
            <div class="step-indicator" data-step="4" onclick="caseReg.go(4)">
                <span class="num">5</span>
                <span class="label">Compensation</span>
                <span class="sep">›</span>
            </div>
            <div class="step-indicator" data-step="5" onclick="caseReg.go(5)">
                <span class="num">6</span>
                <span class="label">Preview</span>
            </div>
        </div>

        <!-- FORM BODY -->
        <div class="form-body" id="caseSteps">

            <!-- STEP 1: GENERAL INFORMATION -->
            <div class="form-step active" data-step="0">
                <div class="step-title">General Information</div>
                <div class="step-subtitle">
                    Select a Project. The compensation scheme (Monetary Compensation or Land to Land, with its sharing ratio) is defined at
                    Project level and cannot be changed on the farm assessment.
                </div>

                <div class="case-id-summary" aria-label="Farm identifiers">
                    <div class="case-id-card case-id-section">
                        <span class="case-id-label">Section No.</span>
                        <strong id="caseSectionIdValue">{{ $nextIdPreview['section'] ?? 'Enter survey officer' }}</strong>
                    </div>
                    <div class="case-id-card case-id-farmer">
                        <span class="case-id-label">Farmer ID</span>
                        <div class="case-id-values" id="caseFarmerIdValues">
                            @forelse ($benRows as $row)
                                @php $farmerIdNo = $farmerIdNos[$row['survey_farmer_id'] ?? 0] ?? null; @endphp
                                <span class="case-id-value">{{ $farmerIdNo ?: 'Given on save' }}{{ ! empty($row['full_name']) ? ' · ' . $row['full_name'] : '' }}</span>
                            @empty
                                <span class="case-id-placeholder">{{ $nextIdPreview['farmer'] }} · next</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="case-id-card case-id-farm">
                        <span class="case-id-label">Farm ID</span>
                        <div class="case-id-values" id="caseFarmIdValues">
                            @forelse ($benRows as $row)
                                <span class="case-id-value">{{ $row['farm_id_no'] ?? 'Given on save' }}{{ ! empty($row['full_name']) ? ' · ' . $row['full_name'] : '' }}</span>
                            @empty
                                <span class="case-id-placeholder">{{ $nextIdPreview['farm'] }} · next</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="case-id-card case-id-farm-no">
                        <span class="case-id-label">Farm No.</span>
                        <div class="case-id-values" id="caseFarmNoValues">
                            @forelse ($benRows as $row)
                                <span class="case-id-value">{{ $row['farm_no'] ?? 'Given on save' }}{{ ! empty($row['full_name']) ? ' · ' . $row['full_name'] : '' }}</span>
                            @empty
                                <span class="case-id-placeholder">{{ $nextIdPreview['farmNo'] }} · next</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Select Project <span class="required">*</span></label>
                        <select name="survey_project_id" id="caseProject" onchange="caseReg.onProject()"
                                class="sv-searchable" data-placeholder="Search project, code or TP No.…">
                            <option value="">— Choose a registered project —</option>
                            @foreach ($projects as $p)
                                @php $mismatch = $editing && $p->scheme_type !== $case->scheme_type; @endphp
                                <option value="{{ $p->id }}"
                                        @selected((string) old('survey_project_id', $case->survey_project_id) === (string) $p->id)
                                        @disabled($mismatch)>
                                    {{ $p->project_code }} · {{ $p->name }}
                                    ({{ $p->isMonetary() ? 'Monetary Compensation' : 'Land to Land' }}){{ $mismatch ? ' — different scheme' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="helper-text">
                            Active projects appear here. Create new ones under
                            <a href="{{ route('survey-module.compensation.projects') }}">Project Management</a>.
                            @if ($editing)
                                A farm assessment keeps the scheme it was registered under, so projects on the other scheme are not selectable.
                            @endif
                        </p>
                    </div>

                    {{-- Surveyor + portion: shown when the project has surveyors assigned (see _portion_picker_js). --}}
                    <div class="form-group full" id="casePortionBox" style="display:none;">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
                            <div>
                                <label>Surveyor <span class="required">*</span></label>
                                <select name="survey_project_surveyor_id" id="caseAllocation"
                                        data-value="{{ old('survey_project_surveyor_id', $case->survey_project_surveyor_id) }}"></select>
                            </div>
                            <div>
                                <label>Portion <span class="required">*</span></label>
                                <select name="portion_no" id="casePortionNo"
                                        data-value="{{ old('portion_no', $case->portion_no) }}"></select>
                            </div>
                            <div>
                                <label>Size of Portion (Ha)</label>
                                <input type="text" id="casePortionSize" readonly style="background:var(--gray-100);color:var(--gray-700);"
                                       value="{{ $case->portion_size_ha !== null ? (float) $case->portion_size_ha : '' }}" />
                            </div>
                        </div>
                        <p class="helper-text">Each portion allocated to a surveyor on the project is surveyed on its own farm assessment. The size comes from the allocation.</p>
                    </div>

                    {{-- Scheme inherited from the project — read-only, and never posted. --}}
                    <div class="form-group full" id="caseSchemeBox" @style(['display:none' => ! $scheme])>
                        <label>Compensation Scheme (from Project)</label>
                        <div id="caseSchemeBadge" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:4px;">
                            @if ($scheme)
                                <span class="status-badge {{ $isMonetary ? 'active' : 'completed' }}" style="font-size:14px;padding:6px 16px;">
                                    <span class="dot"></span>
                                    {{ $isMonetary ? 'Monetary Compensation' : 'Land to Land (' . ($case->project?->share_label ?? '50:50') . ')' }}
                                </span>
                                <span style="font-size:13px;color:var(--gray-600);">Inherited from the project · cannot be changed on the farm assessment</span>
                            @endif
                        </div>
                        <div class="comp-type-note" style="margin-top:10px;" id="caseSchemeNote">
                            @if ($scheme)
                                <i class="fas fa-info-circle"></i>
                                @if ($isMonetary)
                                    <strong>Monetary Compensation scheme:</strong> record economic trees and the cash they are worth. No plots are allocated.
                                @else
                                    <strong>Land to Land scheme:</strong> plots are split by the project's sharing ratio (Farmer : Government), 50:50 unless the project set another. No cash is paid for trees.
                                @endif
                            @endif
                        </div>
                    </div>

                    @if ($editing)
                        <div class="form-group">
                            <label>Assessment No</label>
                            <input type="text" value="{{ $case->case_ref }}" readonly
                                   style="background:var(--gray-100);color:var(--gray-700);" />
                            <p class="helper-text">Generated automatically; cannot be changed.</p>
                        </div>
                    @endif

                    <div class="form-group">
                        <label>Project Name</label>
                        <input type="text" id="caseProjectName" readonly
                               style="background:var(--gray-100);color:var(--gray-700);"
                               placeholder="Auto-filled from Project"
                               value="{{ $case->survey_project_id ? ($projectMeta[$case->survey_project_id]['name'] ?? '') : '' }}" />
                    </div>

                    {{-- The project's CO file, filled when the project is picked (user, 2026-11-03). --}}
                    <div class="form-group">
                        <label>CO File</label>
                        <input type="text" id="caseCoFile" readonly
                               style="background:var(--gray-100);color:var(--gray-700);"
                               placeholder="Auto-filled from Project"
                               value="{{ $case->survey_project_id ? ($projectMeta[$case->survey_project_id]['co'] ?? '') : '' }}" />
                        <p class="helper-text" id="caseCoFileNote" style="{{ $case->survey_project_id && empty($projectMeta[$case->survey_project_id]['co']) ? '' : 'display:none;' }}">
                            This project has no CO file yet. Add it with Update on Project Management.
                        </p>
                    </div>

                    <div class="form-group">
                        <label>Purpose</label>
                        <select name="purpose" id="casePurpose">
                            <option value="">Select purpose…</option>
                            @foreach (['Infrastructure Development','Housing','Commercial','Agricultural','Government Acquisition','Urban Renewal'] as $p)
                                <option value="{{ $p }}" @selected(old('purpose', $case->purpose) === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Survey Officer <span class="required">*</span></label>
                        <input type="text" name="survey_officer" id="caseOfficer"
                               value="{{ old('survey_officer', $case->survey_officer) }}" placeholder="e.g. Clement Joseph" />
                    </div>

                    <div class="form-group">
                        <label>Date <span class="required">*</span></label>
                        <input type="date" name="case_date" id="caseDate"
                               value="{{ old('case_date', optional($case->case_date)->format('Y-m-d') ?? $case->case_date) }}" />
                    </div>

                    <div class="form-group">
                        <label>Area (Ha) <span class="required">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="area_ha" id="caseArea"
                               value="{{ old('area_ha', $case->area_ha) }}" placeholder="e.g. 2.40"
                               oninput="caseReg.mirrorArea()" />
                    </div>

                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" id="caseStatus">
                            @foreach (CaseController::STATUSES as $s)
                                <option value="{{ $s }}" @selected(old('status', $case->status ?? CaseController::STATUS_DRAFT) === $s)>
                                    {{ $statusLabel($s) }}
                                </option>
                            @endforeach
                        </select>
                        <p class="helper-text">Submitting from the Preview step sets this to Pending Review.</p>
                    </div>

                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description" id="caseDescription"
                                  placeholder="Brief description of the case…">{{ old('description', $case->description) }}</textarea>
                    </div>
                </div>

                {{-- Property location: District, LGA, State. The plot number lives in its own field. --}}
                @include('survey_module.partials._address_builder', [
                    'prefix' => 'prop_',
                    'mode'   => 'property',
                    'model'  => $case,
                    'legend' => 'Farm Location',
                ])
            </div>

            <!-- STEP 2: BENEFICIARIES -->
            <div class="form-step" data-step="1">
                <div class="step-title">Beneficiaries</div>
                <div class="step-subtitle">Farmers and landowners affected by this farm assessment. They also appear in the Farmers list.</div>

                <button type="button" class="btn btn-primary btn-sm" onclick="caseReg.toggle('caseBenForm')">
                    <i class="fas fa-user-plus"></i> Add Farmer
                </button>

                <div class="beneficiary-form" id="caseBenForm" data-sv-modal="Add Farmer" data-sv-modal-size="sm">
                    <div class="form-grid cols-3">
                        <div class="sv-group"><i class="fas fa-user"></i><span>Farmer</span></div>
                        <div class="form-group full">
                            <label>Farmer Name <span class="required">*</span></label>
                            <input type="text" id="newBenName" placeholder="e.g. Chidi Okafor" autocomplete="off" oninput="caseReg.farmerSearch()" />
                            {{-- "Is this the existing farmer?" (recording, 2026-11-05): offered, never applied on its own. --}}
                            <div id="newBenMatches" hidden style="margin-top:8px;border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;"></div>
                            <input type="hidden" id="newBenFarmer" />
                        </div>
                        <div class="form-group full" id="newBenFarmBox" hidden>
                            <label>Farm</label>
                            <select id="newBenFarm"></select>
                            <p class="helper-text">Same farm keeps its Farm ID in this section. A different farm gets a new one.</p>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" id="newBenPhone" placeholder="e.g. 080-1234-5678" oninput="caseReg.farmerSearch()" />
                        </div>
                        <div class="form-group">
                            <label>NIN</label>
                            <input type="text" id="newBenNin" placeholder="e.g. 12345678901" oninput="caseReg.farmerSearch()" />
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select id="newBenStatus">
                                <option value="Pending">Pending</option>
                                <option value="Verified">Verified</option>
                                <option value="Review">Review</option>
                            </select>
                        </div>
                        <p class="helper-text full" style="margin:0;">
                            Bank details and the full address are captured in the
                            <a href="{{ route('survey-module.compensation.beneficiaries') }}">Beneficiaries register</a>.
                        </p>
                    </div>
                    <div class="form-actions">
                        <div class="left"><button type="button" class="btn btn-secondary" onclick="caseReg.toggle('caseBenForm')"><i class="fas fa-times"></i> Cancel</button></div>
                        <div class="right"><button type="button" class="btn btn-success" onclick="caseReg.addBen()"><i class="fas fa-check"></i> Add Farmer</button></div>
                    </div>
                </div>

                <div class="beneficiary-list">
                    <div class="list-header">
                        <span>Farmer Name</span>
                        <span>Phone</span>
                        <span class="hide-mobile">NIN</span>
                        <span>Status</span>
                        <span class="hide-mobile">Record</span>
                        <span>Actions</span>
                    </div>

                    {{-- Hidden prototype row; cloned by addBen() so no markup is built from user text. --}}
                    <div class="list-item" id="caseBenTemplate" style="display:none;">
                        <span><input type="text" data-f="full_name" /><input type="hidden" data-f="id" /><input type="hidden" data-f="survey_farmer_id" /><input type="hidden" data-f="farm_id_no" /></span>
                        <span><input type="text" data-f="phone" /></span>
                        <span class="hide-mobile"><input type="text" data-f="nin" /></span>
                        <span>
                            <select data-f="status">
                                <option value="Pending">Pending</option>
                                <option value="Verified">Verified</option>
                                <option value="Review">Review</option>
                            </select>
                        </span>
                        <span class="hide-mobile" data-role="marker">New</span>
                        <span>
                            <button type="button" class="btn btn-danger btn-xs" onclick="caseReg.removeRow(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </span>
                    </div>

                    <div id="caseBenRows">
                        @foreach ($benRows as $i => $row)
                            <div class="list-item" data-farmer-id="{{ $farmerIdNos[$row['survey_farmer_id'] ?? 0] ?? '' }}" data-farm-no="{{ $row['farm_no'] ?? '' }}">
                                <span>
                                    <input type="text" name="beneficiaries[{{ $i }}][full_name]" value="{{ $row['full_name'] ?? '' }}" />
                                    <input type="hidden" name="beneficiaries[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}" />
                                    <input type="hidden" name="beneficiaries[{{ $i }}][survey_farmer_id]" value="{{ $row['survey_farmer_id'] ?? '' }}" />
                                    <input type="hidden" name="beneficiaries[{{ $i }}][farm_id_no]" value="{{ $row['farm_id_no'] ?? '' }}" />
                                </span>
                                <span><input type="text" name="beneficiaries[{{ $i }}][phone]" value="{{ $row['phone'] ?? '' }}" /></span>
                                <span class="hide-mobile"><input type="text" name="beneficiaries[{{ $i }}][nin]" value="{{ $row['nin'] ?? '' }}" /></span>
                                <span>
                                    <select name="beneficiaries[{{ $i }}][status]">
                                        @foreach (['Pending','Verified','Review'] as $s)
                                            <option value="{{ $s }}" @selected(($row['status'] ?? 'Pending') === $s)>{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </span>
                                <span class="hide-mobile">
                                    {{ !empty($row['id']) ? 'Saved' : 'New' }}
                                    @if (! empty($row['farm_id_no']))<small style="display:block;color:var(--gray-500);">{{ $row['farm_id_no'] }} · Farm No. {{ $row['farm_no'] ?? '—' }}</small>@endif
                                </span>
                                <span>
                                    <button type="button" class="btn btn-danger btn-xs" onclick="caseReg.removeRow(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div id="caseBenEmpty" style="padding:14px 0;color:var(--gray-500);@if(count($benRows)) display:none; @endif">
                        No beneficiaries added yet. A farm assessment can be saved as a draft without them, but it cannot be submitted.
                    </div>
                </div>

                <div style="margin-top:12px;font-size:13px;color:var(--gray-500);">
                    <i class="fas fa-info-circle"></i> Total Beneficiaries: <span id="caseBenCount">{{ count($benRows) }}</span>
                    · removing a row here detaches the person from the farm assessment; it does not delete their record.
                </div>
            </div>

            <!-- STEP 3: FARM INFORMATION -->
            <div class="form-step" data-step="2">
                <div class="step-title">Farm Information</div>
                <div class="step-subtitle">Survey detail for the affected land.</div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Area (Ha)</label>
                        <input type="text" id="caseAreaMirror" readonly
                               style="background:var(--gray-100);color:var(--gray-700);"
                               value="{{ old('area_ha', $case->area_ha) }}" />
                        <p class="helper-text">Entered on Step 1.</p>
                    </div>
                    <div class="form-group">
                        <label>Coordinates</label>
                        <input type="text" name="coordinates" id="caseCoordinates"
                               value="{{ old('coordinates', $case->coordinates) }}" placeholder="e.g. 9.0765° N, 7.3986° E" />
                    </div>
                    <div class="form-group">
                        <label>GPS Reading</label>
                        <input type="text" name="gps_reading" id="caseGps"
                               value="{{ old('gps_reading', $case->gps_reading) }}" placeholder="e.g. N 09°04'35.4&quot;, E 07°23'55.0&quot;" />
                    </div>
                    <div class="form-group" id="caseNumPlotsGroup">
                        <label>Number of Plots <span class="required" id="caseNumPlotsReq" @style(['display:none' => ! $isLand])>*</span></label>
                        <input type="number" min="0" step="1" name="num_plots" id="caseNumPlots"
                               value="{{ old('num_plots', $case->num_plots) }}" placeholder="e.g. 10"
                               oninput="caseReg.mirrorPlots()" @disabled($isMonetary) />
                        <p class="helper-text" id="caseNumPlotsHelp">
                            @if ($isMonetary)
                                Not used — a Monetary Compensation assessment allocates no plots.
                            @else
                                Drives the farmer / Government split on Step 5.
                            @endif
                        </p>
                    </div>
                    {{-- Plot calculator inside the form (user, 2026-11-03: the calculator is "supposed to be attached to this place",
                         e.g. low density residential): Land Use > Purpose > Density from the Resettlement Calculator's SG plot
                         sizes; plots = area / plot size, rounded down, as that calculator's defaults. Fills Number of Plots. --}}
                    @php
                        $rcPurposes = \App\Services\Survey\ResettlementCalculator::purposes();
                        $rcRates = \App\Models\Survey\SurveyResettlementRate::query()->where('is_active', 1)->orderBy('land_use')->orderBy('sort_order')
                            ->get(['land_use', 'purpose', 'density', 'plot_size_ha'])
                            ->map(fn ($x) => ['land_use' => $x->land_use, 'purpose' => $x->purpose, 'density' => $x->density, 'size' => (float) $x->plot_size_ha])->values();
                    @endphp
                    <div class="form-group full" id="caseDensityCalc" @style(['display:none' => ! $isLand])>
                        <div style="border:1px dashed var(--gray-300);border-radius:10px;padding:12px 14px;background:var(--gray-50,#f9fafb);">
                            <div style="font-weight:600;margin-bottom:8px;"><i class="fas fa-calculator"></i> Plot Calculator (by density)</div>
                            <div class="form-grid cols-3" style="margin:0;">
                                <div class="form-group">
                                    <label>Land Use</label>
                                    <select id="calcLandUse" onchange="caseReg.densityCalc('landuse')">
                                        <option value="">Select…</option>
                                        @foreach (\App\Services\Survey\ResettlementCalculator::LAND_USES as $lu)<option value="{{ $lu }}">{{ $lu }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Purpose</label>
                                    <select id="calcPurpose" onchange="caseReg.densityCalc('purpose')" disabled><option value="">Select land use first</option></select>
                                </div>
                                <div class="form-group">
                                    <label>Density</label>
                                    <select id="calcDensity" onchange="caseReg.densityCalc()" disabled><option value="">Select purpose first</option></select>
                                </div>
                            </div>
                            <p class="helper-text" id="calcResult" style="margin:6px 0 0;">Area (Step 1) ÷ plot size for the density, rounded down.</p>
                            <button type="button" class="btn btn-secondary btn-sm" id="calcUse" style="margin-top:8px;display:none;" onclick="caseReg.useDensityPlots()">
                                <i class="fas fa-check"></i> Use <span id="calcUseN">0</span> plots
                            </button>
                        </div>
                    </div>
                    <div class="form-group full">
                        <label>Boundary File (Shapefile / KML / GeoJSON)</label>
                        <input type="text" name="boundary_file" id="caseBoundary"
                               value="{{ old('boundary_file', $case->boundary_file) }}"
                               placeholder="Path or reference of the lodged boundary file" />
                        <p class="helper-text">Reference only — file upload is handled by the GIS unit.</p>
                    </div>
                </div>
            </div>

            <!-- STEP 4: ECONOMIC TREES -->
            <div class="form-step" data-step="3">
                <div class="step-title">Economic Trees</div>
                <div class="step-subtitle" id="caseTreesSubtitle">
                    @if ($isLand)
                        Not applicable — this farm assessment is compensated with plots, not cash.
                    @else
                        List the economic trees on the affected land. Required for a Monetary Compensation assessment: Tree Type × Quantity × Unit Price.
                    @endif
                </div>

                {{-- Land to Land: the whole step is not applicable and records nothing. --}}
                <div class="comp-type-note" id="caseTreesNA"
                     style="border-left:4px solid var(--gray-400);@if(! $isLand) display:none; @endif">
                    <i class="fas fa-ban"></i>
                    <strong>Not applicable to this farm assessment.</strong>
                    Land to Land compensation pays no cash for economic trees, so nothing is recorded on this step.
                    Compensation is the plot allocation (by the project's sharing ratio) on Step 5. Any tree line entered here is discarded on save.
                </div>

                <div id="caseTreesActive" @style(['display:none' => $isLand])>
                    <button type="button" class="btn btn-primary btn-sm" onclick="caseReg.toggle('caseTreeForm')">
                        <i class="fas fa-tree"></i> Add Tree
                    </button>

                    <div class="tree-form" id="caseTreeForm" data-sv-modal="Add Tree" data-sv-modal-size="sm">
                        <div class="form-grid">
                            <div class="sv-group"><i class="fas fa-tree"></i><span>Tree line</span></div>
                            <div class="form-group full">
                                <label>Tree Type <span class="required">*</span></label>
                                <input type="text" id="newTreeName" list="caseTreeCatalogue" placeholder="e.g. Palm Oil"
                                       oninput="caseReg.priceFor()" />
                                <datalist id="caseTreeCatalogue">
                                    {{-- Cash Crops and Economic Trees, labelled with their type (user, 2026-11-03). --}}
                                    @foreach ($treeTypes->unique('name') as $t)
                                        <option value="{{ $t->name }}" label="{{ $t->category === \App\Models\Survey\SurveyTreeType::CASH_CROP ? 'Cash Crop · per m²' : 'Economic Tree · per stand' }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="form-group">
                                <label>Category <span class="required">*</span></label>
                                <select id="newTreeMaturity" onchange="caseReg.priceFor(true)">
                                    @foreach ($treeMaturities as $tm)<option value="{{ $tm }}">{{ $tm }}</option>@endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label><span id="newTreeQtyLabel">Quantity</span> <span class="required">*</span></label>
                                <input type="number" min="1" step="1" id="newTreeQty" placeholder="e.g. 45" />
                            </div>
                            <div class="form-group">
                                <label>Unit Price (₦) <span class="required">*</span></label>
                                <input type="number" min="0" step="0.01" id="newTreePrice" placeholder="e.g. 15000" />
                            </div>
                            <p class="helper-text full" style="margin:0;">
                                Line total is recalculated on save.
                                @if ($treeTypes->isEmpty())
                                    The tree catalogue is empty, so type the name and price by hand. Maintain it under
                                    <a href="{{ route('survey-module.compensation.trees') }}">Economic Trees</a>.
                                @endif
                            </p>
                        </div>
                        <div class="form-actions">
                            <div class="left"><button type="button" class="btn btn-secondary" onclick="caseReg.toggle('caseTreeForm')"><i class="fas fa-times"></i> Cancel</button></div>
                            <div class="right"><button type="button" class="btn btn-success" onclick="caseReg.addTree()"><i class="fas fa-check"></i> Add Tree</button></div>
                        </div>
                    </div>

                    <style>
                        /* Category column (Mature / Immature / Seedling), this page only. */
                        .survey-proto .tree-list.tl-m .list-header, .survey-proto .tree-list.tl-m .list-item { grid-template-columns: 2fr 1fr 1fr 1.5fr 1.5fr auto; }
                    </style>
                    <div class="tree-list tl-m">
                        <div class="list-header">
                            <span>Tree Type</span>
                            <span>Category</span>
                            <span>Quantity</span>
                            <span class="hide-mobile">Unit Price (₦)</span>
                            <span>Total (₦)</span>
                            <span>Actions</span>
                        </div>

                        {{-- Hidden prototype row, cloned by addTree(). --}}
                        <div class="list-item" id="caseTreeTemplate" style="display:none;">
                            <span><input type="text" data-f="tree_type" /><input type="hidden" data-f="id" /></span>
                            <span><select data-f="maturity">@foreach ($treeMaturities as $tm)<option value="{{ $tm }}">{{ $tm }}</option>@endforeach</select></span>
                            <span><input type="number" min="1" step="1" data-f="quantity" oninput="caseReg.lineTotal(this)" /></span>
                            <span class="hide-mobile"><input type="number" min="0" step="0.01" data-f="unit_price" oninput="caseReg.lineTotal(this)" /></span>
                            <span class="total" data-role="line">0.00</span>
                            <span>
                                <button type="button" class="btn btn-danger btn-xs" onclick="caseReg.removeRow(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </span>
                        </div>

                        <div id="caseTreeRows">
                            @foreach ($treeRows as $i => $row)
                                <div class="list-item">
                                    <span>
                                        <input type="text" name="trees[{{ $i }}][tree_type]" value="{{ $row['tree_type'] ?? '' }}" />
                                        <input type="hidden" name="trees[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}" />
                                    </span>
                                    <span><select name="trees[{{ $i }}][maturity]">
                                        @foreach ($treeMaturities as $m)<option value="{{ $m }}" @selected(($row['maturity'] ?? 'Mature') === $m)>{{ $m }}</option>@endforeach
                                    </select></span>
                                    <span><input type="number" min="1" step="1" name="trees[{{ $i }}][quantity]"
                                                 value="{{ $row['quantity'] ?? '' }}" oninput="caseReg.lineTotal(this)" /></span>
                                    <span class="hide-mobile"><input type="number" min="0" step="0.01" name="trees[{{ $i }}][unit_price]"
                                                 value="{{ $row['unit_price'] ?? '' }}" oninput="caseReg.lineTotal(this)" /></span>
                                    <span class="total" data-role="line">
                                        {{ number_format((float) ($row['quantity'] ?? 0) * (float) ($row['unit_price'] ?? 0), 2) }}
                                    </span>
                                    <span>
                                        <button type="button" class="btn btn-danger btn-xs" onclick="caseReg.removeRow(this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <div id="caseTreeEmpty" style="padding:14px 0;color:var(--gray-500);@if(count($treeRows)) display:none; @endif">
                            No tree lines yet. A Monetary Compensation assessment needs at least one before it can be saved.
                        </div>

                        <div class="list-total">
                            Total Trees Value: <span id="caseTreeTotal">₦0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 5: COMPENSATION -->
            <div class="form-step" data-step="4">
                <div class="step-title">Compensation Summary</div>
                <div class="step-subtitle" id="caseCompSubtitle">
                    The scheme is exclusive — monetary or land-to-land, never both.
                </div>

                {{-- Shown only until a project (and therefore a scheme) is chosen. --}}
                <div class="comp-panel {{ $scheme ? '' : 'active' }}" id="casePanelUnknown">
                    <div class="comp-type-note">
                        <i class="fas fa-info-circle"></i>
                        Choose a project on Step 1. The compensation panel follows the scheme that project defines.
                    </div>
                </div>

                @if ($showMonetaryPanel)
                    <div class="comp-panel {{ $isMonetary ? 'active' : '' }}" id="casePanelMonetary">
                        <div class="comp-cards single">
                            <div class="comp-card" style="border-color:var(--primary);background:var(--primary-50);">
                                <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
                                <div class="card-label">Cash Compensation (Economic Trees)</div>
                                <div class="card-amount" id="caseCashAmount">₦0.00</div>
                                <div class="card-detail">Tree Type × Quantity × Unit Price · paid to the beneficiaries</div>
                                <div class="divider"></div>
                                <div style="text-align:left;font-size:14px;color:var(--gray-600);" id="caseCashBreakdown"></div>
                            </div>
                        </div>
                        <div class="comp-total">
                            <span class="total-label"><i class="fas fa-calculator"></i> Scheme Total (Monetary Compensation)</span>
                            <span class="total-amount" id="caseCashTotalLabel">₦0.00 cash to beneficiaries</span>
                        </div>
                        <div class="comp-type-note" style="margin-top:16px;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Monetary Compensation only.</strong> No plots are allocated on this farm assessment.
                            Line totals shown here are a preview; the stored values are recalculated on save.
                        </div>
                    </div>
                @endif

                @if ($showLandPanel)
                    <div class="comp-panel {{ $isLand ? 'active' : '' }}" id="casePanelLand">
                        <div class="comp-cards single">
                            <div class="comp-card" style="border-color:var(--primary);background:var(--primary-50);">
                                <div class="card-icon"><i class="fas fa-map-marked-alt"></i></div>
                                <div class="card-label">Land to Land Compensation ({{ $case->project?->share_label ?? '50:50' }})</div>
                                <div style="font-size:20px;font-weight:600;color:var(--primary);margin:8px 0;" id="caseLandPlots">
                                    {{ number_format($split['total'] ?? (int) old('num_plots', $case->num_plots)) }} Plots Total
                                </div>
                                <div class="card-detail">Physical land allocation · no cash component</div>

                                <div class="land-split">
                                    <div class="split-item">
                                        <div class="num" id="caseLandFarmer">{{ $split['farmer'] ?? '—' }}</div>
                                        <div class="label"><i class="fas fa-user" style="color:var(--secondary-dark);"></i> Farmer</div>
                                    </div>
                                    <div style="font-size:32px;color:var(--gray-300);">:</div>
                                    <div class="split-item">
                                        <div class="num" id="caseLandGovt">{{ $split['govt'] ?? '—' }}</div>
                                        <div class="label"><i class="fas fa-building" style="color:var(--info);"></i> Government</div>
                                    </div>
                                </div>

                                <div class="divider"></div>
                                <div style="font-size:14px;color:var(--gray-600);">
                                    <div><strong>Farmer share:</strong> personal ownership of the allocated plots</div>
                                    <div><strong>Government share:</strong> public allotment / infrastructure</div>
                                    <div style="margin-top:8px;">
                                        <strong>Formula:</strong> total plots ÷ 2, with the odd plot going to Government.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="comp-total">
                            <span class="total-label"><i class="fas fa-calculator"></i> Scheme Total (Land to Land)</span>
                            <span class="total-amount" id="caseLandTotalLabel">
                                @if ($split)
                                    {{ $split['total'] }} plots · {{ $split['farmer'] }} Farmer : {{ $split['govt'] }} Government
                                @else
                                    Split is calculated when the farm assessment is saved
                                @endif
                            </span>
                        </div>
                        {{-- The split itself is produced by SurveyCompCase::plotSplit(); the page never
                             re-derives it, so a changed plot count is shown as pending until saved. --}}
                        <div class="comp-type-note" id="caseLandPending"
                             data-saved-plots="{{ $split['total'] ?? '' }}"
                             style="margin-top:16px;display:none;">
                            <i class="fas fa-sync"></i>
                            The farmer / government figures come from the server. Save the farm assessment to recalculate them for the
                            plot count entered on Step 3.
                        </div>
                        <div class="comp-type-note" style="margin-top:16px;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Land to Land only.</strong> No cash is paid for trees on this farm assessment. Individual plot and
                            OP numbers are assigned under
                            <a href="{{ route('survey-module.tools.plot-allocation') }}">Plot Allocation</a>.
                        </div>
                    </div>
                @endif
            </div>

            <!-- STEP 6: PREVIEW & SUBMIT -->
            <div class="form-step" data-step="5">
                <div class="step-title">Preview &amp; Submit</div>
                <div class="step-subtitle">Check the details before saving. Everything is validated again on the server.</div>

                <div class="preview-grid">
                    <div class="preview-item">
                        <span class="label">Project</span>
                        <span class="value" id="pvProject">—</span>
                    </div>
                    <div class="preview-item">
                        <span class="label">Location</span>
                        <span class="value" id="pvLocation">—</span>
                    </div>
                    <div class="preview-item">
                        <span class="label">Purpose</span>
                        <span class="value" id="pvPurpose">—</span>
                    </div>
                    <div class="preview-item">
                        <span class="label">Survey Officer</span>
                        <span class="value" id="pvOfficer">—</span>
                    </div>
                    <div class="preview-item">
                        <span class="label">Surveyor / Portion</span>
                        <span class="value" id="pvPortion">—</span>
                    </div>
                    <div class="preview-item">
                        <span class="label">Date</span>
                        <span class="value" id="pvDate">—</span>
                    </div>
                    <div class="preview-item">
                        <span class="label">Area</span>
                        <span class="value" id="pvArea">—</span>
                    </div>
                    <div class="preview-item full">
                        <span class="label">Description</span>
                        <span class="value" id="pvDescription">—</span>
                    </div>
                    <div class="preview-item">
                        <span class="label">Beneficiaries</span>
                        <span class="value" id="pvBeneficiaries">0</span>
                    </div>
                    <div class="preview-item">
                        <span class="label">Compensation Scheme (from Project)</span>
                        <span class="value" id="pvScheme" style="color:var(--primary);font-weight:700;">—</span>
                    </div>
                    <div class="preview-item" id="pvTreesItem">
                        <span class="label">Economic Trees</span>
                        <span class="value" id="pvTrees">—</span>
                    </div>
                    <div class="preview-item" id="pvCashItem">
                        <span class="label">Cash Compensation</span>
                        <span class="value" id="pvCash" style="color:var(--primary);font-weight:700;">₦0.00</span>
                    </div>
                    <div class="preview-item" id="pvLandItem" style="display:none;">
                        <span class="label">Land Compensation</span>
                        <span class="value" id="pvLand" style="color:var(--primary);font-weight:700;">—</span>
                    </div>
                    <div class="preview-item full" style="background:var(--primary-50);border-radius:var(--radius-sm);padding:12px;border-left:4px solid var(--primary);">
                        <span class="label">Status</span>
                        <span class="value" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span class="status-badge pending"><span class="dot"></span> <span id="pvStatus">Pending</span></span>
                            <span style="font-size:13px;color:var(--gray-500);">
                                Submitting routes the farm assessment through Survey → GIS → Commissioner → Deeds → Land/OSS.
                            </span>
                        </span>
                    </div>
                </div>

                <div class="preview-actions">
                    <a class="btn btn-secondary" href="{{ route('survey-module.compensation.cases') }}">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button class="btn btn-primary" type="submit" name="submit_now" value="0">
                        <i class="fas fa-save"></i> {{ $editing ? 'Save Changes' : 'Save as Draft' }}
                    </button>
                    @if ($editing)
                        {{-- Posts to CaseController::submit(); it saves no edits, hence the warning. --}}
                        <button class="btn btn-success" type="submit" form="caseSubmitForm"
                                onclick="return confirm('Submit {{ $case->case_ref }} for review? Unsaved changes on this form are not included.');">
                            <i class="fas fa-paper-plane"></i> Submit Farm Assessment
                        </button>
                    @else
                        <button class="btn btn-success" type="submit" name="submit_now" value="1">
                            <i class="fas fa-paper-plane"></i> Save &amp; Submit Farm Assessment
                        </button>
                    @endif
                </div>

                <div style="margin-top:12px;font-size:13px;color:var(--gray-500);">
                    <i class="fas fa-info-circle"></i>
                    A farm assessment needs at least one beneficiary before it can be submitted; a Monetary Compensation assessment also needs at least one tree line.
                </div>
            </div>

            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <div class="left">
                    <button type="button" class="btn btn-secondary" id="caseStepPrev" onclick="caseReg.step(-1)" style="display:none;">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                </div>
                <div class="right">
                    <button class="btn btn-secondary" type="submit" name="submit_now" value="0">
                        <i class="fas fa-save"></i> {{ $editing ? 'Save Changes' : 'Save Draft' }}
                    </button>
                    <button type="button" class="btn btn-primary" id="caseStepNext" onclick="caseReg.step(1)">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

        </div><!-- /form-body -->
    </div><!-- /form-container -->
</form>

@if ($editing)
    {{-- Kept outside the main form: HTML forms cannot nest. --}}
    <form method="POST" id="caseSubmitForm" action="{{ route('survey-module.compensation.cases.submit', $case) }}">
        @csrf
    </form>
@endif

<script>
(function () {
    'use strict';

    // Everything is namespaced on window.caseReg: the shared survey_module script
    // declares its own stepper globals, and two top-level declarations of the same
    // name in classic scripts would break both.
    var PROJECTS    = @json($projectMeta);
    var TREE_PRICES = @json($treePrices);
    // Names that are only Cash Crops (priced per m²): Quantity is then the area in m² (user, 2026-11-03).
    var CASH_CROPS = @json($cashCropNames);
    var NEXT_ID_PREVIEW = @json($nextIdPreview);
    var autoPrice = null;   // the last price filled from the catalogue
    var TOTAL_STEPS = 6;

    var step     = 0;
    var benIndex = {{ count($benRows) }};
    var treeIndex = {{ count($treeRows) }};
    var scheme   = @json($scheme);

    function qs(id) { return document.getElementById(id); }
    function money(n) {
        return '₦' + (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /* ---------------------------- stepper ---------------------------- */

    function render() {
        var indicators = document.querySelectorAll('#caseStepper .step-indicator');
        Array.prototype.forEach.call(indicators, function (el, i) {
            el.classList.remove('active', 'done');
            if (i === step) el.classList.add('active');
            else if (i < step) el.classList.add('done');
        });

        var steps = document.querySelectorAll('#caseSteps > .form-step');
        Array.prototype.forEach.call(steps, function (el, i) {
            el.classList.toggle('active', i === step);
        });

        var prev = qs('caseStepPrev'), next = qs('caseStepNext');
        if (prev) prev.style.display = step === 0 ? 'none' : 'inline-flex';
        if (next) next.style.display = step === TOTAL_STEPS - 1 ? 'none' : 'inline-flex';

        if (step === 4) refreshTotals();
        if (step === 5) fillPreview();
    }

    function go(n) {
        if (n < 0 || n >= TOTAL_STEPS) return;
        step = n;
        render();
        var c = document.querySelector('.form-container');
        if (c) c.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ------------------------ scheme inheritance ---------------------- */

    // Plot Calculator (by density) on Step 3 (user, 2026-11-03).
    var RC_PURPOSES = @json($rcPurposes), RC_RATES = @json($rcRates), calcPlots = null;
    function densityCalc(changed) {
        var lu = qs('calcLandUse').value, pSel = qs('calcPurpose'), dSel = qs('calcDensity');
        var opt = function (v, t) { var o = document.createElement('option'); o.value = v; o.textContent = t; return o; };
        if (changed === 'landuse') {
            pSel.innerHTML = ''; pSel.appendChild(opt('', lu ? 'Select purpose…' : 'Select land use first'));
            (RC_PURPOSES[lu] || []).forEach(function (p) { pSel.appendChild(opt(p, p)); });
            pSel.disabled = !lu;
        }
        if (changed === 'landuse' || changed === 'purpose') {
            var purpose = pSel.value.toUpperCase();
            var rows = RC_RATES.filter(function (r) { return r.land_use === lu; });
            // A purpose's own sizes replace the general ones (as the Resettlement Calculator).
            var own = rows.filter(function (r) { return r.purpose && r.purpose.toUpperCase() === purpose; });
            rows = own.length ? own : rows.filter(function (r) { return !r.purpose; });
            dSel.innerHTML = ''; dSel.appendChild(opt('', !pSel.value ? 'Select purpose first' : (rows.length ? 'Select density…' : 'No SG plot size for this yet')));
            if (pSel.value) rows.forEach(function (r) { var o = opt(r.size, r.density + ' · ' + r.size + ' ha a plot'); dSel.appendChild(o); });
            dSel.disabled = !pSel.value || !rows.length;
        }
        var size = Number(dSel.value) || 0, area = Number(qs('caseArea') ? qs('caseArea').value : 0) || 0;
        var out = qs('calcResult'), use = qs('calcUse');
        calcPlots = null;
        if (!size) { out.textContent = 'Area (Step 1) ÷ plot size for the density, rounded down.'; use.style.display = 'none'; return; }
        if (!area) { out.textContent = 'Enter the area on Step 1 first.'; use.style.display = 'none'; return; }
        // As ResettlementCalculator::effectiveSize: 0.1667 is 1/6 ha, so 3 ha gives 18, not 17; then 2 decimals.
        var n1 = Math.round(1 / size), useSize = n1 >= 1 && Math.abs(size - 1 / n1) < 0.0001 ? 1 / n1 : size;
        var calcExact = Math.round(area / useSize * 100) / 100;
        calcPlots = Math.floor(calcExact + 1e-9);
        out.textContent = area + ' ha ÷ ' + size + ' ha = ' + calcExact.toFixed(2) + ' → ' + calcPlots + ' plot' + (calcPlots === 1 ? '' : 's') + ' (rounded down).';
        qs('calcUseN').textContent = calcPlots;
        use.style.display = '';
    }
    function useDensityPlots() {
        var input = qs('caseNumPlots');
        if (calcPlots === null || !input || input.disabled) return;
        input.value = calcPlots;
        mirrorPlots();
    }

    function applyScheme(next) {
        scheme = next || null;
        var isMonetary = scheme === 'monetary';
        var isLand     = scheme === 'land';
        var calcBox = qs('caseDensityCalc');
        if (calcBox) calcBox.style.display = isLand ? '' : 'none';

        var box = qs('caseSchemeBox');
        if (box) box.style.display = scheme ? '' : 'none';

        var badge = qs('caseSchemeBadge');
        if (badge && scheme) {
            badge.innerHTML = '<span class="status-badge ' + (isMonetary ? 'active' : 'completed') +
                '" style="font-size:14px;padding:6px 16px;"><span class="dot"></span>' +
                (isMonetary ? 'Monetary Compensation' : 'Land to Land') + '</span>' +
                '<span style="font-size:13px;color:var(--gray-600);">Inherited from the project · cannot be changed on the farm assessment</span>';
        }
        var note = qs('caseSchemeNote');
        if (note && scheme) {
            note.innerHTML = '<i class="fas fa-info-circle"></i> ' + (isMonetary
                ? '<strong>Monetary Compensation scheme:</strong> record economic trees and the cash they are worth. No plots are allocated.'
                : '<strong>Land to Land scheme:</strong> plots are split by the project&rsquo;s sharing ratio (Farmer : Government), 50:50 unless the project set another. No cash is paid for trees.');
        }

        // Step 4 — required for monetary, explicitly not applicable for land.
        var na = qs('caseTreesNA'), active = qs('caseTreesActive'), sub = qs('caseTreesSubtitle');
        if (na)     na.style.display = isLand ? '' : 'none';
        if (active) active.style.display = isLand ? 'none' : '';
        if (sub) {
            sub.textContent = isLand
                ? 'Not applicable — this farm assessment is compensated with plots, not cash.'
                : 'List the economic trees on the affected land. Required for a Monetary Compensation assessment: Tree Type × Quantity × Unit Price.';
        }

        // Step 3 — plot count only means something under land-to-land.
        var plots = qs('caseNumPlots'), req = qs('caseNumPlotsReq'), help = qs('caseNumPlotsHelp');
        if (plots) plots.disabled = isMonetary;
        if (req)   req.style.display = isLand ? '' : 'none';
        if (help)  help.textContent = isMonetary
            ? 'Not used — a Monetary Compensation assessment allocates no plots.'
            : 'Drives the farmer / Government split on Step 5.';

        // Step 5 — one panel, never two.
        ['casePanelUnknown', 'casePanelMonetary', 'casePanelLand'].forEach(function (id) {
            var el = qs(id);
            if (el) el.classList.remove('active');
        });
        var panel = qs(isMonetary ? 'casePanelMonetary' : (isLand ? 'casePanelLand' : 'casePanelUnknown'));
        if (panel) panel.classList.add('active');

        var compSub = qs('caseCompSubtitle');
        if (compSub) {
            compSub.textContent = isLand
                ? 'Land to Land: plots split by the project sharing ratio (Farmer : Government). No monetary compensation on this farm assessment.'
                : (isMonetary
                    ? 'Monetary Compensation: economic trees only (Tree × Quantity × Unit Price). No plots on this farm assessment.'
                    : 'The scheme is exclusive — monetary or land-to-land, never both.');
        }
    }

    var projectLoc = null;   // what the last project pick filled, cleared when the project changes

    function onProject() {
        var sel  = qs('caseProject');
        var meta = sel ? PROJECTS[sel.value] : null;

        // Case Property Location from the project (rule 5): empty fields only; the previous pick's values go.
        if (window.fillAddressBuilder) projectLoc = window.fillAddressBuilder('prop_', meta ? meta.location : null, projectLoc);

        var name = qs('caseProjectName');
        if (name) name.value = meta ? meta.name : '';
        var co = qs('caseCoFile'), coNote = qs('caseCoFileNote');
        if (co) co.value = meta ? (meta.co || '') : '';
        if (coNote) coNote.style.display = meta && !meta.co ? '' : 'none';

        var purpose = qs('casePurpose');
        if (meta && purpose && !purpose.value && meta.purpose) purpose.value = meta.purpose;

        applyScheme(meta ? meta.scheme : null);
    }

    /* ------------------------------ rows ------------------------------ */

    function cloneRow(templateId, containerId, prefix, index, values) {
        var tpl = qs(templateId);
        var row = tpl.cloneNode(true);
        row.removeAttribute('id');
        row.style.display = '';

        Array.prototype.forEach.call(row.querySelectorAll('[data-f]'), function (input) {
            var field = input.getAttribute('data-f');
            input.name = prefix + '[' + index + '][' + field + ']';
            // Values are assigned, never interpolated into markup.
            if (Object.prototype.hasOwnProperty.call(values, field)) input.value = values[field];
        });

        qs(containerId).appendChild(row);
        return row;
    }

    function addBen() {
        var name = (qs('newBenName').value || '').trim();
        if (!name) { alert('Enter the farmer name first.'); qs('newBenName').focus(); return; }

        var farmer = qs('newBenFarmer').value, farm = qs('newBenFarm').value;
        var row = cloneRow('caseBenTemplate', 'caseBenRows', 'beneficiaries', benIndex++, {
            full_name: name,
            phone:  (qs('newBenPhone').value || '').trim(),
            nin:    (qs('newBenNin').value || '').trim(),
            status: qs('newBenStatus').value,
            survey_farmer_id: farmer,
            farm_id_no: farmer ? farm : '',
            id: ''
        });
        row.dataset.farmerId = farmerPicked ? farmerPicked.farmer_id_no : '';
        var marker = row.querySelector('[data-role="marker"]');
        if (marker && farmer) marker.textContent = farm ? 'Existing farm' : 'Existing farmer';

        qs('newBenName').value = '';
        qs('newBenPhone').value = '';
        qs('newBenNin').value = '';
        farmerClear();
        qs('caseBenForm').classList.remove('open');
        refreshCounts();
        refreshIdSummary();
    }

    // Existing farmer (CR 03112026): typed details are matched against the farmers on record and offered.
    var farmerTimer = null, farmerSeq = 0, farmerPicked = null, farmerUrl = @json(route('survey-module.compensation.cases.farmer-lookup'));
    function farmerClear() {
        farmerPicked = null;
        qs('newBenFarmer').value = '';
        qs('newBenFarm').innerHTML = '';
        qs('newBenFarmBox').hidden = true;
        qs('newBenMatches').hidden = true;
        qs('newBenMatches').textContent = '';
    }
    function farmerPick(f) {
        farmerPicked = f;
        qs('newBenFarmer').value = f.id;
        qs('newBenName').value = f.full_name;
        if (f.phone) qs('newBenPhone').value = f.phone;
        if (f.nin) qs('newBenNin').value = f.nin;
        var box = qs('newBenMatches');
        box.textContent = '';
        var note = document.createElement('div');
        note.style.cssText = 'padding:8px 12px;font-size:13px;background:var(--primary-50,#f0fdfa);';
        note.textContent = 'Existing farmer ' + f.farmer_id_no + '. Their Farmer ID stays the same.';
        box.appendChild(note);
        var sel = qs('newBenFarm');
        sel.innerHTML = '';
        sel.add(new Option('A different farm (new Farm ID)', ''));
        (f.farms || []).forEach(function (x) { sel.add(new Option(x.label, x.farm_id_no)); });
        qs('newBenFarmBox').hidden = !(f.farms || []).length;
    }
    function farmerSearch() {
        // Changing what was picked drops it, and what it filled.
        if (farmerPicked && (qs('newBenName').value || '').trim() !== farmerPicked.full_name) { farmerClear(); qs('newBenPhone').value = ''; qs('newBenNin').value = ''; }
        if (farmerPicked) return;
        clearTimeout(farmerTimer);
        var name = (qs('newBenName').value || '').trim(), phone = (qs('newBenPhone').value || '').trim(), nin = (qs('newBenNin').value || '').trim();
        var box = qs('newBenMatches');
        if (name.length < 3 && !phone && !nin) { box.hidden = true; box.textContent = ''; return; }
        var mine = ++farmerSeq;
        farmerTimer = setTimeout(function () {
            fetch(farmerUrl + '?name=' + encodeURIComponent(name) + '&phone=' + encodeURIComponent(phone) + '&nin=' + encodeURIComponent(nin), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    if (mine !== farmerSeq || farmerPicked) return;
                    box.textContent = '';
                    box.hidden = !rows.length;
                    if (!rows.length) return;
                    var head = document.createElement('div');
                    head.style.cssText = 'padding:6px 12px;font-size:12px;font-weight:600;background:var(--gray-50,#f9fafb);';
                    head.textContent = 'Is this an existing farmer?';
                    box.appendChild(head);
                    rows.forEach(function (f) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.style.cssText = 'display:block;width:100%;text-align:left;padding:7px 12px;border:0;border-top:1px solid var(--gray-100);background:#fff;cursor:pointer;font-size:13px;';
                        b.textContent = f.farmer_id_no + ' · ' + f.full_name + (f.phone ? ' · ' + f.phone : '') + (f.nin ? ' · NIN ' + f.nin : '');
                        b.addEventListener('click', function () { farmerPick(f); });
                        box.appendChild(b);
                    });
                }).catch(function () {});
        }, 300);
    }

    function addTree() {
        var name = (qs('newTreeName').value || '').trim();
        var qty  = qs('newTreeQty').value;
        var price = qs('newTreePrice').value;
        if (!name || !qty || price === '') { alert('Tree type, quantity and unit price are all needed.'); return; }

        var row = cloneRow('caseTreeTemplate', 'caseTreeRows', 'trees', treeIndex++, {
            tree_type: name, maturity: qs('newTreeMaturity').value || 'Mature', quantity: qty, unit_price: price, id: ''
        });

        lineTotal(row.querySelector('[data-f="quantity"]'));
        qs('newTreeName').value = '';
        qs('newTreeQty').value = '';
        qs('newTreePrice').value = '';
        qs('newTreeMaturity').value = 'Mature';
        autoPrice = null;
        qs('caseTreeForm').classList.remove('open');
        refreshCounts();
    }

    function removeRow(btn) {
        var row = btn.closest('.list-item');
        if (row) row.parentNode.removeChild(row);
        refreshCounts();
        refreshIdSummary();
        refreshTotals();
    }

    /* ----------------------------- totals ----------------------------- */

    function rowLineTotal(row) {
        var q = row.querySelector('[name$="[quantity]"], [data-f="quantity"]');
        var p = row.querySelector('[name$="[unit_price]"], [data-f="unit_price"]');
        return (Number(q && q.value) || 0) * (Number(p && p.value) || 0);
    }

    // Display only — SurveyCaseTree recalculates line_total on save.
    function lineTotal(input) {
        var row = input.closest('.list-item');
        if (!row) return;
        var cell = row.querySelector('[data-role="line"]');
        if (cell) cell.textContent = rowLineTotal(row).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        refreshTotals();
    }

    function treeRows() {
        var c = qs('caseTreeRows');
        return c ? c.querySelectorAll('.list-item') : [];
    }

    function refreshCounts() {
        var bens = qs('caseBenRows') ? qs('caseBenRows').querySelectorAll('.list-item').length : 0;
        var count = qs('caseBenCount');
        if (count) count.textContent = bens;
        var benEmpty = qs('caseBenEmpty');
        if (benEmpty) benEmpty.style.display = bens ? 'none' : '';

        var treeEmpty = qs('caseTreeEmpty');
        if (treeEmpty) treeEmpty.style.display = treeRows().length ? 'none' : '';
    }

    function refreshIdSummary() {
        var rows = qs('caseBenRows') ? Array.prototype.slice.call(qs('caseBenRows').querySelectorAll('.list-item')) : [];
        var farmerNext = Number(String(NEXT_ID_PREVIEW.farmer).replace(/\D/g, '')) || 1;
        var farmNext = Number(String(NEXT_ID_PREVIEW.farm).replace(/\D/g, '')) || 1;
        var farmNoNext = Number(NEXT_ID_PREVIEW.farmNo) || 1;
        [
            ['caseFarmerIdValues', function (row) {
                if (row.dataset.farmerId) return row.dataset.farmerId;
                return 'FMR-' + String(farmerNext++).padStart(6, '0') + ' · preview';
            }, NEXT_ID_PREVIEW.farmer + ' · next'],
            ['caseFarmIdValues', function (row) {
                var input = row.querySelector('[name$="[farm_id_no]"]');
                if (input && input.value) return input.value;
                return 'FRM-' + String(farmNext++).padStart(6, '0') + ' · preview';
            }, NEXT_ID_PREVIEW.farm + ' · next'],
            ['caseFarmNoValues', function (row) {
                if (row.dataset.farmNo) return row.dataset.farmNo;
                return String(farmNoNext++) + ' · preview';
            }, NEXT_ID_PREVIEW.farmNo + ' · next']
        ].forEach(function (item) {
            var container = qs(item[0]);
            if (!container) return;
            container.textContent = '';
            if (!rows.length) {
                var placeholder = document.createElement('span');
                placeholder.className = 'case-id-placeholder';
                placeholder.textContent = item[2];
                container.appendChild(placeholder);
                return;
            }
            rows.forEach(function (row) {
                var value = document.createElement('span');
                value.className = 'case-id-value';
                var name = row.querySelector('[name$="[full_name]"]');
                value.textContent = item[1](row) + (name && name.value.trim() ? ' · ' + name.value.trim() : '');
                container.appendChild(value);
            });
        });
    }

    function refreshTotals() {
        var rows = treeRows(), total = 0;
        var bd = qs('caseCashBreakdown');
        if (bd) bd.textContent = '';

        Array.prototype.forEach.call(rows, function (row) {
            var t = rowLineTotal(row);
            total += t;

            if (!bd) return;
            var nameEl = row.querySelector('[name$="[tree_type]"], [data-f="tree_type"]');
            var qtyEl  = row.querySelector('[name$="[quantity]"], [data-f="quantity"]');

            // Tree names are user text, so the breakdown is built as nodes, not markup.
            var line = document.createElement('div');
            line.setAttribute('style', 'display:flex;justify-content:space-between;padding:4px 0;');
            var left = document.createElement('span');
            left.textContent = (nameEl ? nameEl.value : '') + ' × ' + (qtyEl ? qtyEl.value : 0);
            var right = document.createElement('span');
            right.textContent = money(t);
            line.appendChild(left);
            line.appendChild(right);
            bd.appendChild(line);
        });

        var totalEl = qs('caseTreeTotal');
        if (totalEl) totalEl.textContent = money(total);

        var cash = qs('caseCashAmount');
        if (cash) cash.textContent = money(total);
        var cashLabel = qs('caseCashTotalLabel');
        if (cashLabel) cashLabel.textContent = money(total) + ' cash to beneficiaries';

        return total;
    }

    function mirrorArea() {
        var src = qs('caseArea'), dst = qs('caseAreaMirror');
        if (src && dst) dst.value = src.value;
        if (qs('calcDensity') && qs('calcDensity').value) densityCalc();
    }

    // The split numbers are produced by SurveyCompCase::plotSplit() on the server (project sharing ratio).
    // Changing the plot count here only re-labels the total and flags the split as
    // pending; the page never recomputes the shares itself.
    function mirrorPlots() {
        var input = qs('caseNumPlots');
        var label = qs('caseLandPlots');
        var pending = qs('caseLandPending');
        if (!input) return;

        var typed = Number(input.value) || 0;
        if (label) label.textContent = typed.toLocaleString() + ' Plots Total';

        if (!pending) return;
        var saved = pending.getAttribute('data-saved-plots');
        var stale = saved === '' || Number(saved) !== typed;
        pending.style.display = stale ? '' : 'none';

        if (stale) {
            var f = qs('caseLandFarmer'), g = qs('caseLandGovt'), t = qs('caseLandTotalLabel');
            if (f) f.textContent = '—';
            if (g) g.textContent = '—';
            if (t) t.textContent = 'Split is calculated when the farm assessment is saved';
        }
    }

    /* ----------------------------- preview ---------------------------- */

    function fillPreview() {
        var text = function (id, value) { var el = qs(id); if (el) el.textContent = value; };
        var sel  = qs('caseProject');
        var meta = sel ? PROJECTS[sel.value] : null;
        var preview = document.querySelector('.address-builder[data-prefix="prop_"] .ab-preview');

        text('pvProject', meta ? (meta.code + ' · ' + meta.name) : '—');
        text('pvLocation', (preview && preview.value) || '—');
        text('pvPurpose', qs('casePurpose').value || '—');
        text('pvOfficer', qs('caseOfficer').value || '—');
        // Surveyor and portion, from the picker's selected options.
        var pa = qs('caseAllocation'), pp = qs('casePortionNo'), ps = qs('casePortionSize');
        var paText = pa && pa.value ? pa.options[pa.selectedIndex].text.split(' · ')[0] : '';
        var ppText = pp && pp.value ? pp.options[pp.selectedIndex].text : '';
        text('pvPortion', paText ? paText + (ppText ? ' · ' + ppText : '') + (ps && ps.value ? ' · ' + ps.value + ' Ha' : '') : '—');
        text('pvDate', qs('caseDate').value || '—');
        text('pvArea', (qs('caseArea').value || '0') + ' Ha');
        text('pvDescription', qs('caseDescription').value || '—');
        text('pvBeneficiaries', (qs('caseBenRows') ? qs('caseBenRows').querySelectorAll('.list-item').length : 0) + ' farmer(s)');
        text('pvScheme', meta ? meta.label : 'Choose a project on Step 1');
        text('pvStatus', qs('caseStatus').options[qs('caseStatus').selectedIndex].text);

        var isLand = scheme === 'land';
        var treesItem = qs('pvTreesItem'), cashItem = qs('pvCashItem'), landItem = qs('pvLandItem');
        if (treesItem) treesItem.style.display = isLand ? 'none' : '';
        if (cashItem)  cashItem.style.display  = isLand ? 'none' : '';
        if (landItem)  landItem.style.display  = isLand ? '' : 'none';

        if (isLand) {
            var plots = Number(qs('caseNumPlots') ? qs('caseNumPlots').value : 0) || 0;
            var farmerEl = qs('caseLandFarmer'), govtEl = qs('caseLandGovt');
            var shares = (farmerEl && farmerEl.textContent !== '—')
                ? ' (' + farmerEl.textContent + ' Farmer : ' + govtEl.textContent + ' Government)'
                : ' · split calculated on save (project sharing ratio)';
            text('pvLand', plots + ' plots' + shares);
        } else {
            var rows = treeRows();
            var qty = 0;
            Array.prototype.forEach.call(rows, function (row) {
                var q = row.querySelector('[name$="[quantity]"], [data-f="quantity"]');
                qty += Number(q && q.value) || 0;
            });
            text('pvTrees', rows.length + ' line(s), ' + qty + ' tree(s)');
            text('pvCash', money(refreshTotals()));
        }
    }

    /* ------------------------------ boot ------------------------------ */

    window.caseReg = {
        go: go,
        step: function (d) { go(step + d); },
        onProject: onProject,
        toggle: function (id) { var el = qs(id); if (el) el.classList.toggle('open'); },
        addBen: addBen,
        farmerSearch: farmerSearch,
        addTree: addTree,
        removeRow: removeRow,
        lineTotal: lineTotal,
        mirrorArea: mirrorArea,
        mirrorPlots: mirrorPlots,
        densityCalc: densityCalc,
        useDensityPlots: useDensityPlots,
        // The catalogue rate for the tree and its category; replaces only a price this filled in.
        priceFor: function () {
            var name = (qs('newTreeName').value || '').trim();
            var price = qs('newTreePrice');
            var rates = TREE_PRICES[name] || {};
            var rate = rates[qs('newTreeMaturity').value || 'Mature'];
            var qtyLabel = qs('newTreeQtyLabel');
            if (qtyLabel) qtyLabel.textContent = CASH_CROPS.indexOf(name) >= 0 ? 'Area (m²)' : 'Quantity (stands)';
            if (!price) return;
            if (price.value === '' || price.value === autoPrice) {
                price.value = rate == null ? '' : String(rate);
                autoPrice = price.value;
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        applyScheme(scheme);
        var beneficiaryRows = qs('caseBenRows');
        if (beneficiaryRows) beneficiaryRows.addEventListener('input', refreshIdSummary);
        var officerInput = qs('caseOfficer'), sectionPreviewTimer = null, sectionPreviewRequest = 0;
        if (officerInput && !{{ $case->section_no ? 'true' : 'false' }}) {
            officerInput.addEventListener('input', function () {
                clearTimeout(sectionPreviewTimer);
                var officer = officerInput.value.trim();
                var requestId = ++sectionPreviewRequest;
                var value = qs('caseSectionIdValue');
                if (!officer) { value.textContent = 'Enter survey officer'; return; }
                sectionPreviewTimer = setTimeout(function () {
                    fetch(@json(route('survey-module.compensation.cases.next-section')) + '?officer=' + encodeURIComponent(officer), { headers: { Accept: 'application/json' } })
                        .then(function (response) {
                            if (!response.ok) throw new Error('Section number preview request failed.');
                            return response.json();
                        })
                        .then(function (preview) {
                            if (requestId === sectionPreviewRequest) value.textContent = preview.section_no;
                        })
                        .catch(function (error) {
                            if (requestId === sectionPreviewRequest) value.textContent = 'Preview unavailable';
                            console.error(error);
                        });
                }, 250);
            });
        }
        @unless ($editing)
        // A new case opened with its project already chosen gets that project's location too.
        var pre = qs('caseProject');
        if (pre && pre.value && PROJECTS[pre.value] && window.fillAddressBuilder) projectLoc = window.fillAddressBuilder('prop_', PROJECTS[pre.value].location, null);
        @endunless
        refreshCounts();
        refreshIdSummary();
        refreshTotals();
        mirrorPlots();
        render();
    });
})();
</script>

@include('survey_module.partials._portion_picker_js')
