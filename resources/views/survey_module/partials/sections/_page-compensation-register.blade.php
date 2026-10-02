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

    $projectMeta = $projects->mapWithKeys(fn ($p) => [$p->id => [
        'name'    => $p->name,
        'code'    => $p->project_code,
        'scheme'  => $p->scheme_type,
        'purpose' => $p->purpose,
        'label'   => $p->scheme_label,
    ]])->all();

    $treePrices = $treeTypes->pluck('default_unit_price', 'name')->all();

    // Rows already on the case, or whatever bounced back from validation.
    $benRows = old('beneficiaries', $editing
        ? $case->beneficiaries->map(fn ($b) => [
            'id' => $b->id, 'full_name' => $b->full_name, 'phone' => $b->phone,
            'nin' => $b->nin, 'status' => $b->status,
        ])->all()
        : []);

    $treeRows = old('trees', $editing
        ? $case->trees->map(fn ($t) => [
            'id' => $t->id, 'tree_type' => $t->tree_type,
            'quantity' => $t->quantity, 'unit_price' => $t->unit_price,
        ])->all()
        : []);

    $statusLabel = fn ($s) => $s === 'Review' ? 'Pending Review' : $s;
@endphp

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
                    Select a Project. The compensation scheme (Monetary or Land-for-Land 50:50) is defined at
                    Project level and cannot be changed on the case.
                </div>

                <div class="form-grid">
                    <div class="form-group full">
                        <label>Select Project <span class="required">*</span></label>
                        <select name="survey_project_id" id="caseProject" onchange="caseReg.onProject()">
                            <option value="">— Choose a registered project —</option>
                            @foreach ($projects as $p)
                                @php $mismatch = $editing && $p->scheme_type !== $case->scheme_type; @endphp
                                <option value="{{ $p->id }}"
                                        @selected((string) old('survey_project_id', $case->survey_project_id) === (string) $p->id)
                                        @disabled($mismatch)>
                                    {{ $p->project_code }} · {{ $p->name }}
                                    ({{ $p->isMonetary() ? 'Monetary' : 'Land-for-Land' }}){{ $mismatch ? ' — different scheme' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="helper-text">
                            Active projects appear here. Create new ones under
                            <a href="{{ route('survey-module.compensation.projects') }}">Project Management</a>.
                            @if ($editing)
                                A case keeps the scheme it was registered under, so projects on the other scheme are not selectable.
                            @endif
                        </p>
                    </div>

                    {{-- Scheme inherited from the project — read-only, and never posted. --}}
                    <div class="form-group full" id="caseSchemeBox" @style(['display:none' => ! $scheme])>
                        <label>Compensation Scheme (from Project)</label>
                        <div id="caseSchemeBadge" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:4px;">
                            @if ($scheme)
                                <span class="status-badge {{ $isMonetary ? 'active' : 'completed' }}" style="font-size:14px;padding:6px 16px;">
                                    <span class="dot"></span>
                                    {{ $isMonetary ? 'Monetary (Cash for Trees)' : 'Land-for-Land (50:50)' }}
                                </span>
                                <span style="font-size:13px;color:var(--gray-600);">Inherited from the project · cannot be changed on the case</span>
                            @endif
                        </div>
                        <div class="comp-type-note" style="margin-top:10px;" id="caseSchemeNote">
                            @if ($scheme)
                                <i class="fas fa-info-circle"></i>
                                @if ($isMonetary)
                                    <strong>Monetary scheme:</strong> record economic trees and the cash they are worth. No plots are allocated.
                                @else
                                    <strong>Land-for-Land scheme:</strong> plots are split 50:50 (Farmer : Government). No cash is paid for trees.
                                @endif
                            @endif
                        </div>
                    </div>

                    @if ($editing)
                        <div class="form-group">
                            <label>Case No</label>
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
                    'legend' => 'Case Property Location',
                ])
            </div>

            <!-- STEP 2: BENEFICIARIES -->
            <div class="form-step" data-step="1">
                <div class="step-title">Beneficiaries</div>
                <div class="step-subtitle">Farmers and landowners affected by this case. They also appear in the Beneficiaries register.</div>

                <button type="button" class="btn btn-primary btn-sm" onclick="caseReg.toggle('caseBenForm')">
                    <i class="fas fa-user-plus"></i> Add Farmer
                </button>

                <div class="beneficiary-form" id="caseBenForm">
                    <div class="form-row">
                        <div class="full">
                            <label>Farmer Name <span class="required">*</span></label>
                            <input type="text" id="newBenName" placeholder="e.g. Chidi Okafor" />
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Phone</label>
                            <input type="text" id="newBenPhone" placeholder="e.g. 080-1234-5678" />
                        </div>
                        <div>
                            <label>NIN</label>
                            <input type="text" id="newBenNin" placeholder="e.g. 12345678901" />
                        </div>
                        <div>
                            <label>Status</label>
                            <select id="newBenStatus">
                                <option value="Pending">Pending</option>
                                <option value="Verified">Verified</option>
                                <option value="Review">Review</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:12px;">
                        <button type="button" class="btn btn-success btn-sm" onclick="caseReg.addBen()">
                            <i class="fas fa-check"></i> Add Farmer
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="caseReg.toggle('caseBenForm')">Cancel</button>
                    </div>
                    <p class="helper-text">
                        Bank details and the full address are captured in the
                        <a href="{{ route('survey-module.compensation.beneficiaries') }}">Beneficiaries register</a>.
                    </p>
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
                        <span><input type="text" data-f="full_name" /><input type="hidden" data-f="id" /></span>
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
                            <div class="list-item">
                                <span>
                                    <input type="text" name="beneficiaries[{{ $i }}][full_name]" value="{{ $row['full_name'] ?? '' }}" />
                                    <input type="hidden" name="beneficiaries[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}" />
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
                                <span class="hide-mobile">{{ !empty($row['id']) ? 'Saved' : 'New' }}</span>
                                <span>
                                    <button type="button" class="btn btn-danger btn-xs" onclick="caseReg.removeRow(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div id="caseBenEmpty" style="padding:14px 0;color:var(--gray-500);@if(count($benRows)) display:none; @endif">
                        No beneficiaries added yet. A case can be saved as a draft without them, but it cannot be submitted.
                    </div>
                </div>

                <div style="margin-top:12px;font-size:13px;color:var(--gray-500);">
                    <i class="fas fa-info-circle"></i> Total Beneficiaries: <span id="caseBenCount">{{ count($benRows) }}</span>
                    · removing a row here detaches the person from the case; it does not delete their record.
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
                                Not used — a monetary case allocates no plots.
                            @else
                                Drives the 50:50 split on Step 5.
                            @endif
                        </p>
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
                        Not applicable — this case is compensated with plots, not cash.
                    @else
                        List the economic trees on the affected land. Required for a Monetary case: Tree Type × Quantity × Unit Price.
                    @endif
                </div>

                {{-- Land-for-Land: the whole step is not applicable and records nothing. --}}
                <div class="comp-type-note" id="caseTreesNA"
                     style="border-left:4px solid var(--gray-400);@if(! $isLand) display:none; @endif">
                    <i class="fas fa-ban"></i>
                    <strong>Not applicable to this case.</strong>
                    Land-for-Land compensation pays no cash for economic trees, so nothing is recorded on this step.
                    Compensation is the 50:50 plot allocation on Step 5. Any tree line entered here is discarded on save.
                </div>

                <div id="caseTreesActive" @style(['display:none' => $isLand])>
                    <button type="button" class="btn btn-primary btn-sm" onclick="caseReg.toggle('caseTreeForm')">
                        <i class="fas fa-tree"></i> Add Tree
                    </button>

                    <div class="tree-form" id="caseTreeForm">
                        <div class="form-row">
                            <div>
                                <label>Tree Type <span class="required">*</span></label>
                                <input type="text" id="newTreeName" list="caseTreeCatalogue" placeholder="e.g. Palm Oil"
                                       oninput="caseReg.priceFor()" />
                                <datalist id="caseTreeCatalogue">
                                    @foreach ($treeTypes as $t)
                                        <option value="{{ $t->name }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div>
                                <label>Quantity <span class="required">*</span></label>
                                <input type="number" min="1" step="1" id="newTreeQty" placeholder="e.g. 45" />
                            </div>
                            <div>
                                <label>Unit Price (₦) <span class="required">*</span></label>
                                <input type="number" min="0" step="0.01" id="newTreePrice" placeholder="e.g. 15000" />
                            </div>
                            <div>
                                <label>&nbsp;</label>
                                <p class="helper-text" style="margin:0;">Line total is recalculated on save.</p>
                            </div>
                            <div style="display:flex;align-items:end;gap:6px;">
                                <button type="button" class="btn btn-success btn-sm" onclick="caseReg.addTree()"><i class="fas fa-check"></i></button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="caseReg.toggle('caseTreeForm')"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        @if ($treeTypes->isEmpty())
                            <p class="helper-text">
                                The tree catalogue is empty, so type the name and price by hand. Maintain it under
                                <a href="{{ route('survey-module.compensation.trees') }}">Economic Trees</a>.
                            </p>
                        @endif
                    </div>

                    <div class="tree-list">
                        <div class="list-header">
                            <span>Tree Type</span>
                            <span>Quantity</span>
                            <span class="hide-mobile">Unit Price (₦)</span>
                            <span>Total (₦)</span>
                            <span>Actions</span>
                        </div>

                        {{-- Hidden prototype row, cloned by addTree(). --}}
                        <div class="list-item" id="caseTreeTemplate" style="display:none;">
                            <span><input type="text" data-f="tree_type" /><input type="hidden" data-f="id" /></span>
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
                            No tree lines yet. A monetary case needs at least one before it can be saved.
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
                    The scheme is exclusive — monetary or land-for-land, never both.
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
                            <span class="total-label"><i class="fas fa-calculator"></i> Scheme Total (Monetary)</span>
                            <span class="total-amount" id="caseCashTotalLabel">₦0.00 cash to beneficiaries</span>
                        </div>
                        <div class="comp-type-note" style="margin-top:16px;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Monetary compensation only.</strong> No plots are allocated on this case.
                            Line totals shown here are a preview; the stored values are recalculated on save.
                        </div>
                    </div>
                @endif

                @if ($showLandPanel)
                    <div class="comp-panel {{ $isLand ? 'active' : '' }}" id="casePanelLand">
                        <div class="comp-cards single">
                            <div class="comp-card" style="border-color:var(--primary);background:var(--primary-50);">
                                <div class="card-icon"><i class="fas fa-map-marked-alt"></i></div>
                                <div class="card-label">Land-for-Land Compensation (50:50)</div>
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
                            <span class="total-label"><i class="fas fa-calculator"></i> Scheme Total (Land-for-Land)</span>
                            <span class="total-amount" id="caseLandTotalLabel">
                                @if ($split)
                                    {{ $split['total'] }} plots · {{ $split['farmer'] }} Farmer : {{ $split['govt'] }} Government
                                @else
                                    Split is calculated when the case is saved
                                @endif
                            </span>
                        </div>
                        {{-- The split itself is produced by SurveyCompCase::plotSplit(); the page never
                             re-derives it, so a changed plot count is shown as pending until saved. --}}
                        <div class="comp-type-note" id="caseLandPending"
                             data-saved-plots="{{ $split['total'] ?? '' }}"
                             style="margin-top:16px;display:none;">
                            <i class="fas fa-sync"></i>
                            The farmer / government figures come from the server. Save the case to recalculate them for the
                            plot count entered on Step 3.
                        </div>
                        <div class="comp-type-note" style="margin-top:16px;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Land-for-Land only.</strong> No cash is paid for trees on this case. Individual plot and
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
                                Submitting routes the case through Survey → GIS → Commissioner → Deeds → Land/OSS.
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
                            <i class="fas fa-paper-plane"></i> Submit Case
                        </button>
                    @else
                        <button class="btn btn-success" type="submit" name="submit_now" value="1">
                            <i class="fas fa-paper-plane"></i> Save &amp; Submit Case
                        </button>
                    @endif
                </div>

                <div style="margin-top:12px;font-size:13px;color:var(--gray-500);">
                    <i class="fas fa-info-circle"></i>
                    A case needs at least one beneficiary before it can be submitted; a monetary case also needs at least one tree line.
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

    function applyScheme(next) {
        scheme = next || null;
        var isMonetary = scheme === 'monetary';
        var isLand     = scheme === 'land';

        var box = qs('caseSchemeBox');
        if (box) box.style.display = scheme ? '' : 'none';

        var badge = qs('caseSchemeBadge');
        if (badge && scheme) {
            badge.innerHTML = '<span class="status-badge ' + (isMonetary ? 'active' : 'completed') +
                '" style="font-size:14px;padding:6px 16px;"><span class="dot"></span>' +
                (isMonetary ? 'Monetary (Cash for Trees)' : 'Land-for-Land (50:50)') + '</span>' +
                '<span style="font-size:13px;color:var(--gray-600);">Inherited from the project · cannot be changed on the case</span>';
        }
        var note = qs('caseSchemeNote');
        if (note && scheme) {
            note.innerHTML = '<i class="fas fa-info-circle"></i> ' + (isMonetary
                ? '<strong>Monetary scheme:</strong> record economic trees and the cash they are worth. No plots are allocated.'
                : '<strong>Land-for-Land scheme:</strong> plots are split 50:50 (Farmer : Government). No cash is paid for trees.');
        }

        // Step 4 — required for monetary, explicitly not applicable for land.
        var na = qs('caseTreesNA'), active = qs('caseTreesActive'), sub = qs('caseTreesSubtitle');
        if (na)     na.style.display = isLand ? '' : 'none';
        if (active) active.style.display = isLand ? 'none' : '';
        if (sub) {
            sub.textContent = isLand
                ? 'Not applicable — this case is compensated with plots, not cash.'
                : 'List the economic trees on the affected land. Required for a Monetary case: Tree Type × Quantity × Unit Price.';
        }

        // Step 3 — plot count only means something under land-for-land.
        var plots = qs('caseNumPlots'), req = qs('caseNumPlotsReq'), help = qs('caseNumPlotsHelp');
        if (plots) plots.disabled = isMonetary;
        if (req)   req.style.display = isLand ? '' : 'none';
        if (help)  help.textContent = isMonetary
            ? 'Not used — a monetary case allocates no plots.'
            : 'Drives the 50:50 split on Step 5.';

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
                ? 'Land-for-Land: plots split 50:50 (Farmer : Government). No cash for trees on this case.'
                : (isMonetary
                    ? 'Monetary: cash for economic trees only (Tree × Quantity × Unit Price). No plots on this case.'
                    : 'The scheme is exclusive — monetary or land-for-land, never both.');
        }
    }

    function onProject() {
        var sel  = qs('caseProject');
        var meta = sel ? PROJECTS[sel.value] : null;

        var name = qs('caseProjectName');
        if (name) name.value = meta ? meta.name : '';

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

        cloneRow('caseBenTemplate', 'caseBenRows', 'beneficiaries', benIndex++, {
            full_name: name,
            phone:  (qs('newBenPhone').value || '').trim(),
            nin:    (qs('newBenNin').value || '').trim(),
            status: qs('newBenStatus').value,
            id: ''
        });

        qs('newBenName').value = '';
        qs('newBenPhone').value = '';
        qs('newBenNin').value = '';
        qs('caseBenForm').classList.remove('open');
        refreshCounts();
    }

    function addTree() {
        var name = (qs('newTreeName').value || '').trim();
        var qty  = qs('newTreeQty').value;
        var price = qs('newTreePrice').value;
        if (!name || !qty || price === '') { alert('Tree type, quantity and unit price are all needed.'); return; }

        var row = cloneRow('caseTreeTemplate', 'caseTreeRows', 'trees', treeIndex++, {
            tree_type: name, quantity: qty, unit_price: price, id: ''
        });

        lineTotal(row.querySelector('[data-f="quantity"]'));
        qs('newTreeName').value = '';
        qs('newTreeQty').value = '';
        qs('newTreePrice').value = '';
        qs('caseTreeForm').classList.remove('open');
        refreshCounts();
    }

    function removeRow(btn) {
        var row = btn.closest('.list-item');
        if (row) row.parentNode.removeChild(row);
        refreshCounts();
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
    }

    // The 50:50 numbers are produced by SurveyCompCase::plotSplit() on the server.
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
            if (t) t.textContent = 'Split is calculated when the case is saved';
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
                : ' · 50:50 split calculated on save';
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
        addTree: addTree,
        removeRow: removeRow,
        lineTotal: lineTotal,
        mirrorArea: mirrorArea,
        mirrorPlots: mirrorPlots,
        priceFor: function () {
            var name = (qs('newTreeName').value || '').trim();
            var price = qs('newTreePrice');
            if (price && !price.value && TREE_PRICES[name]) price.value = TREE_PRICES[name];
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        applyScheme(scheme);
        refreshCounts();
        refreshTotals();
        mirrorPlots();
        render();
    });
})();
</script>
