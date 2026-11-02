@php
    $editing = $project->exists;
    $locked  = $editing && $project->cases()->exists();
    $scheme  = old('scheme_type', $project->scheme_type ?? 'monetary');
    // Sharing ratio (CR 30102026 item 11): picked here from Configurable Entities > Survey; 50:50 unless set.
    $ratios  = \App\Services\Survey\SurveySettings::sharingRatios();
    $farmerPct = (string) old('farmer_share_pct', $project->farmer_share_pct ?? \App\Services\Survey\SurveySettings::defaultFarmerPct());
@endphp

@include('survey_module.partials._flash')

<form method="POST"
      action="{{ $editing
                  ? route('survey-module.compensation.projects.update', $project)
                  : route('survey-module.compensation.projects.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="form-container">
        <div class="form-body">
            {{-- Scheme type comes first (user, 2026-10-28). --}}
            <div class="form-grid" style="margin-bottom:22px;">
                <div class="form-group full">
                    <label>Compensation Scheme Type <span class="required">*</span></label>

                    @if ($locked)
                        <div class="comp-type-note" style="margin-bottom:10px;">
                            <i class="fas fa-lock"></i>
                            This project already has <strong>{{ $project->cases()->count() }}</strong> case(s), which
                            inherited <strong>{{ $project->scheme_label }}</strong>. The scheme can no longer be changed.
                        </div>
                        <input type="hidden" name="scheme_type" value="{{ $project->scheme_type }}" />
                    @else
                        <p class="helper-text" style="margin-bottom:10px;">
                            This choice is permanent once cases are registered. All cases under this project use the
                            selected scheme exclusively.
                        </p>
                    @endif

                    <div class="comp-type-grid">
                        <label class="comp-type-option {{ $scheme === 'monetary' ? 'selected' : '' }}"
                               id="newTypeOptMonetary" onclick="selectNewProjectType('monetary')">
                            <input type="radio" name="scheme_type" value="monetary"
                                   @checked($scheme === 'monetary') @disabled($locked) />
                            <div class="type-icon"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="type-title">Monetary Compensation</div>
                            <div class="type-desc">Payment for economic trees only. Tree Type × Quantity × Unit Price. No land allocation.</div>
                            <span class="type-badge">Monetary Compensation</span>
                        </label>

                        <label class="comp-type-option {{ $scheme === 'land' ? 'selected' : '' }}"
                               id="newTypeOptLand" onclick="selectNewProjectType('land')">
                            <input type="radio" name="scheme_type" value="land"
                                   @checked($scheme === 'land') @disabled($locked) />
                            <div class="type-icon"><i class="fas fa-map-marked-alt"></i></div>
                            <div class="type-title">Land to Land</div>
                            <div class="type-desc">Physical plot allocation. Total plots split between farmers and government by the sharing ratio (50:50 unless set below). No cash component.</div>
                            <span class="type-badge">Plots Only</span>
                        </label>
                    </div>

                    {{-- The project's sharing ratio; only Land to Land uses it. Locked with the scheme once cases exist. --}}
                    <div id="projRatioBox" class="form-group" style="margin-top:14px;max-width:420px;{{ $scheme === 'land' ? '' : 'display:none;' }}">
                        <label>Sharing ratio (farmer : Government) <span class="required">*</span></label>
                        @if ($locked)
                            <input type="text" value="{{ $project->share_label }}" disabled />
                        @else
                            <select name="farmer_share_pct" @disabled($scheme !== 'land')>
                                @foreach ($ratios as $ratio)
                                    <option value="{{ $ratio->farmer_pct }}" @selected($farmerPct === (string) $ratio->farmer_pct)>{{ $ratio->label }}{{ $ratio->is_default ? ' (general)' : '' }}</option>
                                @endforeach
                            </select>
                            <p class="helper-text">The general ratio is 50:50. Pick another only for a project agreed on different terms. The ratios are set in Configurable Entities &gt; Survey.</p>
                        @endif
                    </div>

                    @unless ($locked)
                        <div class="comp-type-note">
                            <i class="fas fa-info-circle"></i>
                            <strong>Critical:</strong> once cases are registered under this project the scheme type
                            is locked, because those cases inherited it.
                        </div>
                    @endunless
                </div>
            </div>

            <div class="form-grid">
                @if ($editing)
                    <div class="form-group">
                        <label>Project ID</label>
                        <input type="text" value="{{ $project->project_code }}" readonly
                               style="background:var(--gray-100);color:var(--gray-700);" />
                        <p class="helper-text">Generated automatically; cannot be changed.</p>
                    </div>
                @endif

                {{-- CO file from the global file number selector (user, 2026-10-30); one CO file holds many projects. --}}
                @include('survey_module.partials._co_file_picker', [
                    'coCurrent'  => $project->survey_co_file_id ? \App\Models\Survey\SurveyCoFile::find($project->survey_co_file_id) : null,
                    'coRequired' => true,
                    'coAllowNew' => false,
                ])

                <div class="form-group">
                    <label>Project Name <span class="required">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $project->name) }}"
                           placeholder="e.g. Kano Housing Corridor Phase 2" required />
                </div>

                <div class="form-group">
                    <label>TP No.</label>
                    <input type="text" name="plan_no" value="{{ old('plan_no', $project->plan_no) }}" maxlength="100"
                           placeholder="e.g. TP/KNUPDA/UC/GZ/02" />
                    <p class="helper-text">The layout plan number, as written on the plan.</p>
                </div>

                <div class="form-group">
                    <label>Purpose <span class="required">*</span></label>
                    <select name="purpose">
                        <option value="">Select purpose…</option>
                        {{-- A purpose not on the list (e.g. from the imported lists) stays selectable, so an edit never blanks it. --}}
                        @foreach (array_values(array_unique(array_filter(array_merge(['Infrastructure Development','Housing','Commercial','Agricultural','Government Acquisition','Urban Renewal'], [old('purpose', $project->purpose)])))) as $p)
                            <option value="{{ $p }}" @selected(old('purpose', $project->purpose) === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date"
                           value="{{ old('start_date', optional($project->start_date)->format('Y-m-d')) }}" />
                </div>

                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status">
                        @foreach (['Active','Draft','Closed'] as $s)
                            <option value="{{ $s }}" @selected(old('status', $project->status ?? 'Active') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Size of Acquisition (Ha) <span class="required">*</span></label>
                    <input type="number" step="0.0001" min="0.0001" name="acquisition_size_ha" id="projAcqSize"
                           value="{{ old('acquisition_size_ha', $project->acquisition_size_ha !== null ? (float) $project->acquisition_size_ha : '') }}"
                           placeholder="e.g. 120.5" required oninput="projSurveyors.total()" />
                </div>

                <div class="form-group full">
                    <label>Description</label>
                    <textarea name="description" placeholder="Brief project description…">{{ old('description', $project->description) }}</textarea>
                </div>
            </div>

            {{-- Property location: District, LGA, State --}}
            @include('survey_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => $project,
                'legend' => 'Project Location',
            ])

            {{-- Surveyors working the project: each gets a number of portions of one size. Each portion is surveyed on its own case form. --}}
            @php
                $svRows = old('surveyors', $project->exists
                    ? $project->surveyors->map(fn ($a) => [
                        // A surveyor typed under "Other (specify)" has a name but no List of Surveyors id.
                        'id' => $a->id, 'surveyor_id' => $a->surveyor_id ?: 'other',
                        'surveyor_other' => $a->surveyor_id ? '' : $a->surveyor_name,
                        'portions' => $a->portions, 'portion_size_ha' => (float) $a->portion_size_ha,
                      ])->all()
                    : []);
            @endphp
            {{-- Restyled 2026-10-26 (user: "the css is really bad"). --}}
            <style>
                .survey-proto .pa-section { margin-top: 22px; }
                .survey-proto .pa-head { display: flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; letter-spacing: .06em;
                    text-transform: uppercase; color: var(--primary, #2563eb); margin-bottom: 6px; }
                .survey-proto .pa-head::after { content: ''; flex: 1; height: 1px; background: var(--gray-200, #e5e7eb); }
                .survey-proto .pa-help { font-size: 12.5px; line-height: 1.5; color: var(--gray-500, #6b7280); margin: 0 0 12px; }
                .survey-proto .pa-help a { color: var(--primary, #2563eb); text-decoration: underline; }
                .survey-proto .pa-box { border: 1px solid var(--gray-200, #e5e7eb); border-radius: 10px; overflow: hidden; background: #fff; }
                .survey-proto .pa-scroll { overflow-x: auto; }
                .survey-proto table.pa-table { width: 100%; min-width: 720px; border-collapse: collapse; margin: 0; }
                .survey-proto table.pa-table th { background: var(--gray-50, #f9fafb); font-size: 11.5px; font-weight: 600; letter-spacing: .04em;
                    text-transform: uppercase; color: var(--gray-600, #4b5563); padding: 10px 12px; text-align: left;
                    border-bottom: 1px solid var(--gray-200, #e5e7eb); white-space: nowrap; }
                .survey-proto table.pa-table td { padding: 8px 12px; border-bottom: 1px solid var(--gray-100, #f3f4f6); vertical-align: middle; font-size: 14px; }
                .survey-proto table.pa-table tr:last-child td { border-bottom: 0; }
                .survey-proto table.pa-table .num { text-align: right; }
                .survey-proto table.pa-table .sn { width: 56px; color: var(--gray-500, #6b7280); font-weight: 600; }
                .survey-proto table.pa-table select,
                .survey-proto table.pa-table input[type=number] { width: 100%; height: 38px; padding: 6px 10px; border: 1.5px solid var(--gray-300, #d1d5db);
                    border-radius: var(--radius-sm, 6px); font-family: var(--font); font-size: 14px; background: #fff; }
                .survey-proto table.pa-table input[type=number] { text-align: right; }
                .survey-proto table.pa-table input.pa-other { width: 100%; height: 38px; margin-top: 6px; padding: 6px 10px; border: 1.5px solid var(--gray-300, #d1d5db);
                    border-radius: var(--radius-sm, 6px); font-family: var(--font); font-size: 14px; background: #fff; }
                .survey-proto table.pa-table .select2-container .select2-selection--single { height: 38px; }
                .survey-proto table.pa-table select:focus,
                .survey-proto table.pa-table input:focus { outline: 0; border-color: var(--primary, #2563eb); box-shadow: 0 0 0 3px var(--primary-100, #dbeafe); }
                .survey-proto table.pa-table [data-subtotal] { font-weight: 600; color: var(--gray-900, #111827); white-space: nowrap; }
                .survey-proto .pa-x { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;
                    border: 1px solid var(--gray-200, #e5e7eb); background: #fff; color: var(--gray-500, #6b7280); cursor: pointer; }
                .survey-proto .pa-x:hover { border-color: #fecaca; background: #fef2f2; color: var(--danger, #dc2626); }
                .survey-proto .pa-lock { color: var(--gray-400, #9ca3af); display: inline-flex; width: 32px; justify-content: center; }
                .survey-proto .pa-empty td { text-align: center; color: var(--gray-500, #6b7280); padding: 22px 12px; font-size: 13.5px; }
                .survey-proto .pa-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
                    padding: 10px 12px; background: var(--gray-50, #f9fafb); border-top: 1px solid var(--gray-200, #e5e7eb); }
                .survey-proto .pa-total { font-size: 13.5px; color: var(--gray-700, #374151); }
                .survey-proto .pa-total strong { color: var(--gray-900, #111827); }
                .survey-proto .pa-total .over { color: var(--danger, #dc2626); margin-left: 6px; }
            </style>
            <div class="pa-section">
                <div class="pa-head"><i class="fas fa-user-friends"></i><span>Surveyors &amp; Portion Allocation</span></div>
                <p class="pa-help">
                    Pick each surveyor from the <a href="{{ route('survey-module.records.surveyors.index') }}">List of Surveyors</a>,
                    then enter how many portions they will survey and the size of each portion.
                    A surveyor's case form offers exactly that many portions.
                </p>
                <div class="pa-box">
                    <div class="pa-scroll">
                        <table class="pa-table" data-column-picker="off">
                            <thead>
                                <tr>
                                    <th class="sn">S/N</th>
                                    <th>Surveyor</th>
                                    <th class="num" style="width:150px;">No. of Portions</th>
                                    <th class="num" style="width:180px;">Size per Portion (Ha)</th>
                                    <th class="num" style="width:140px;">Subtotal (Ha)</th>
                                    <th style="width:56px;"></th>
                                </tr>
                            </thead>
                            <tbody id="projSurveyorRows">
                                @foreach ($svRows as $i => $row)
                                    @php $minPortions = max(1, $usedMax[$row['id'] ?? 0] ?? 0); @endphp
                                    <tr data-row>
                                        <td class="sn" data-sn>{{ $loop->iteration }}</td>
                                        <td>
                                            <input type="hidden" name="surveyors[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}" />
                                            @php $isOther = (string) ($row['surveyor_id'] ?? '') === 'other'; @endphp
                                            <select name="surveyors[{{ $i }}][surveyor_id]" class="pa-surveyor" required aria-label="Surveyor">
                                                <option value="">— Select surveyor —</option>
                                                @foreach ($surveyors as $sv)
                                                    <option value="{{ $sv->id }}" @selected((string) ($row['surveyor_id'] ?? '') === (string) $sv->id)>
                                                        {{ $sv->full_name }}{{ $sv->rank ? ' · ' . $sv->rank : '' }}{{ $sv->is_active ? '' : ' (inactive)' }}
                                                    </option>
                                                @endforeach
                                                <option value="other" @selected($isOther)>Other (specify)</option>
                                            </select>
                                            <input type="text" name="surveyors[{{ $i }}][surveyor_other]" class="pa-other" maxlength="255"
                                                   placeholder="Specify surveyor's full name *" aria-label="Specify surveyor"
                                                   value="{{ $row['surveyor_other'] ?? '' }}"
                                                   @style(['display:none' => ! $isOther]) @if ($isOther) required @else disabled @endif />
                                        </td>
                                        <td>
                                            <input type="number" name="surveyors[{{ $i }}][portions]" min="{{ $minPortions }}" step="1" aria-label="No. of portions"
                                                   value="{{ $row['portions'] ?? '' }}" required oninput="projSurveyors.total()"
                                                   @if ($minPortions > 1) title="Portions 1–{{ $minPortions }} already have cases" @endif />
                                        </td>
                                        <td>
                                            <input type="number" name="surveyors[{{ $i }}][portion_size_ha]" min="0.0001" step="0.0001" aria-label="Size per portion (Ha)"
                                                   value="{{ $row['portion_size_ha'] ?? '' }}" required oninput="projSurveyors.total()" />
                                        </td>
                                        <td class="num" data-subtotal>—</td>
                                        <td>
                                            @if (! empty($row['id']) && ($usedMax[$row['id']] ?? 0) > 0)
                                                <span class="pa-lock" title="Has cases; cannot be removed"><i class="fas fa-lock"></i></span>
                                            @else
                                                <button type="button" class="pa-x" title="Remove" aria-label="Remove surveyor"
                                                        onclick="projSurveyors.remove(this)"><i class="fas fa-times"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                <tr id="projSurveyorEmpty" class="pa-empty" @style(['display:none' => count($svRows) > 0])>
                                    <td colspan="6"><i class="fas fa-user-plus"></i> No surveyors assigned yet. Use <strong>Add Surveyor</strong> below.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pa-foot">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="projSurveyors.add()">
                            <i class="fas fa-plus"></i> Add Surveyor
                        </button>
                        <span class="pa-total" id="projSurveyorTotal"></span>
                    </div>
                </div>
            </div>

            <template id="projSurveyorTpl">
                <tr data-row>
                    <td class="sn" data-sn></td>
                    <td>
                        <input type="hidden" name="surveyors[__i__][id]" value="" />
                        <select name="surveyors[__i__][surveyor_id]" class="pa-surveyor" required aria-label="Surveyor">
                            <option value="">— Select surveyor —</option>
                            @foreach ($surveyors->where('is_active', true) as $sv)
                                <option value="{{ $sv->id }}">{{ $sv->full_name }}{{ $sv->rank ? ' · ' . $sv->rank : '' }}</option>
                            @endforeach
                            <option value="other">Other (specify)</option>
                        </select>
                        <input type="text" name="surveyors[__i__][surveyor_other]" class="pa-other" maxlength="255"
                               placeholder="Specify surveyor's full name *" aria-label="Specify surveyor"
                               style="display:none" disabled />
                    </td>
                    <td><input type="number" name="surveyors[__i__][portions]" min="1" step="1" required aria-label="No. of portions" oninput="projSurveyors.total()" /></td>
                    <td><input type="number" name="surveyors[__i__][portion_size_ha]" min="0.0001" step="0.0001" required aria-label="Size per portion (Ha)" oninput="projSurveyors.total()" /></td>
                    <td class="num" data-subtotal>—</td>
                    <td>
                        <button type="button" class="pa-x" title="Remove" aria-label="Remove surveyor"
                                onclick="projSurveyors.remove(this)"><i class="fas fa-times"></i></button>
                    </td>
                </tr>
            </template>

            {{-- Cash for economic trees at project level (user, 2026-11-02): Monetary Compensation only.
                 Each line is a tree of the Kano schedule in one Category; the rate per stand comes from
                 the schedule and can be changed; amount = stands x rate. --}}
            @php
                $treeRows = old('trees', $project->exists
                    ? $project->trees->map(fn ($t) => ['name' => $t->tree_name, 'maturity' => $t->maturity, 'stands' => $t->stands, 'rate' => (float) $t->rate])->all()
                    : []);
                $treeRows = array_values(array_filter((array) $treeRows, fn ($t) => is_array($t)));
                $maturities = \App\Models\Survey\SurveyTreeType::MATURITIES;
            @endphp
            <div class="pa-section" id="projTreeSection" @style(['display:none' => $scheme !== 'monetary'])>
                <div class="pa-head"><i class="fas fa-tree"></i><span>Economic Trees (Monetary Compensation)</span></div>
                <p class="pa-help">
                    The cash for economic trees on this project. Pick the tree and its Category; the rate per stand comes from the
                    <a href="{{ route('survey-module.compensation.trees') }}">Farm Assessment</a> schedule (change it if needed).
                    Amount = stands × rate.
                </p>
                <div class="pa-box">
                    <div class="pa-scroll">
                        <table class="pa-table" data-column-picker="off">
                            <thead>
                                <tr>
                                    <th class="sn">S/N</th>
                                    <th>Economic Tree</th>
                                    <th style="width:150px;">Category</th>
                                    <th class="num" style="width:130px;">Stands</th>
                                    <th class="num" style="width:170px;">Rate per Stand (₦)</th>
                                    <th class="num" style="width:170px;">Amount (₦)</th>
                                    <th style="width:56px;"></th>
                                </tr>
                            </thead>
                            <tbody id="projTreeRows">
                                @foreach ($treeRows as $i => $row)
                                    <tr data-row>
                                        <td class="sn" data-sn>{{ $loop->iteration }}</td>
                                        <td>
                                            <select name="trees[{{ $i }}][name]" class="pt-name" data-no-search aria-label="Economic tree">
                                                <option value="">— Select tree —</option>
                                                @foreach ($treeRates as $name => $rates)<option value="{{ $name }}" @selected(($row['name'] ?? '') === $name)>{{ $name }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select name="trees[{{ $i }}][maturity]" class="pt-mat" aria-label="Category">
                                                @foreach ($maturities as $m)<option value="{{ $m }}" @selected(($row['maturity'] ?? 'Mature') === $m)>{{ $m }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td><input type="number" name="trees[{{ $i }}][stands]" class="pt-stands" min="1" step="1" value="{{ $row['stands'] ?? '' }}" aria-label="Stands" /></td>
                                        <td><input type="number" name="trees[{{ $i }}][rate]" class="pt-rate" min="0" step="0.01" value="{{ $row['rate'] ?? '' }}" aria-label="Rate per stand" /></td>
                                        <td class="num" data-amount>—</td>
                                        <td><button type="button" class="pa-x" title="Remove" aria-label="Remove tree" onclick="projTrees.remove(this)"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                @endforeach
                                <tr id="projTreeEmpty" class="pa-empty" @style(['display:none' => count($treeRows) > 0])>
                                    <td colspan="7"><i class="fas fa-tree"></i> No economic trees added. Use <strong>Add Tree</strong> below.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pa-foot">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="projTrees.add()">
                            <i class="fas fa-plus"></i> Add Tree
                        </button>
                        <span class="pa-total" id="projTreeTotal"></span>
                    </div>
                </div>
            </div>

            <template id="projTreeTpl">
                <tr data-row>
                    <td class="sn" data-sn></td>
                    <td>
                        <select name="trees[__i__][name]" class="pt-name" data-no-search aria-label="Economic tree">
                            <option value="">— Select tree —</option>
                            @foreach ($treeRates as $name => $rates)<option value="{{ $name }}">{{ $name }}</option>@endforeach
                        </select>
                    </td>
                    <td>
                        <select name="trees[__i__][maturity]" class="pt-mat" aria-label="Category">
                            @foreach ($maturities as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                        </select>
                    </td>
                    <td><input type="number" name="trees[__i__][stands]" class="pt-stands" min="1" step="1" aria-label="Stands" /></td>
                    <td><input type="number" name="trees[__i__][rate]" class="pt-rate" min="0" step="0.01" aria-label="Rate per stand" /></td>
                    <td class="num" data-amount>—</td>
                    <td><button type="button" class="pa-x" title="Remove" aria-label="Remove tree" onclick="projTrees.remove(this)"><i class="fas fa-times"></i></button></td>
                </tr>
            </template>

            <div class="form-actions">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ route('survey-module.compensation.projects') }}">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
                <div class="right">
                    <button class="btn btn-success" type="submit">
                        <i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Create Project' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    // Surveyor rows: add/remove, renumber S/N, and total the portions against the acquisition.
    var projSurveyors = (function () {
        var body = document.getElementById('projSurveyorRows');
        var next = {{ count($svRows) }};

        function fmt(n) { return (Math.round(n * 10000) / 10000).toLocaleString(undefined, { maximumFractionDigits: 4 }); }

        function total() {
            var sum = 0, rows = body.querySelectorAll('tr[data-row]');
            rows.forEach(function (tr, i) {
                tr.querySelector('[data-sn]').textContent = i + 1;
                var p = parseFloat(tr.querySelector('input[name$="[portions]"]').value) || 0;
                var s = parseFloat(tr.querySelector('input[name$="[portion_size_ha]"]').value) || 0;
                tr.querySelector('[data-subtotal]').textContent = p && s ? fmt(p * s) : '—';
                sum += p * s;
            });
            document.getElementById('projSurveyorEmpty').style.display = rows.length ? 'none' : '';

            var acq = parseFloat(document.getElementById('projAcqSize').value) || 0;
            var out = document.getElementById('projSurveyorTotal');
            if (!rows.length) { out.textContent = ''; return; }
            var over = acq > 0 && sum - acq > 0.00005;
            out.innerHTML = 'Allocated: <strong>' + fmt(sum) + ' Ha</strong>' + (acq ? ' of <strong>' + fmt(acq) + ' Ha</strong> acquired' : '') +
                (over ? '<span class="over"><i class="fas fa-exclamation-triangle"></i> exceeds the size of acquisition</span>' : '');
        }

        function add() {
            var html = document.getElementById('projSurveyorTpl').innerHTML.replace(/__i__/g, 'n' + (next++));
            // Parsed inside a tbody: outside a table the browser drops the tr/td tags and the inputs pile up.
            var tmp = document.createElement('tbody');
            tmp.innerHTML = html.trim();
            var tr = tmp.querySelector('tr');
            body.insertBefore(tr, document.getElementById('projSurveyorEmpty'));
            var sel = tr.querySelector('select.pa-surveyor');
            if (sel) {
                wire(sel);
                var $ = $j();
                if ($) $(sel).select2('open'); else sel.focus();
            }
            total();
        }

        function remove(btn) {
            var $ = $j(), sel = btn.closest('tr').querySelector('select.pa-surveyor');
            if ($ && sel && $(sel).data('select2')) $(sel).select2('destroy');
            btn.closest('tr').remove();
            total();
        }

        function $j() { return window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 ? window.jQuery : null; }

        // "Other (specify)" reveals a required name box; any other choice hides, clears and disables it.
        function syncOther(sel) {
            var box = sel.closest('td').querySelector('input.pa-other');
            if (!box) return;
            var on = sel.value === 'other';
            box.style.display = on ? '' : 'none';
            box.disabled = !on;
            box.required = on;
            if (on) { if (!box.value) box.focus(); } else box.value = '';
        }

        // Searchable surveyor select (Select2, as elsewhere in the module); plain select if it failed to load.
        function wire(sel) {
            var $ = $j();
            if ($) {
                // No dropdownParent: the default (<body>) keeps the panel clear of the scroll containers.
                $(sel).select2({ width: '100%', placeholder: '— Select surveyor —' })
                      .on('change', function () { syncOther(sel); });
            } else {
                sel.addEventListener('change', function () { syncOther(sel); });
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            body.querySelectorAll('select.pa-surveyor').forEach(wire);
            total();
        });
        return { add: add, remove: remove, total: total };
    })();

    // Economic trees (user, 2026-11-02): rate per stand from the schedule by tree + Category,
    // unless typed over; amount per line and the total.
    var projTrees = (function () {
        var RATES = @json($treeRates);
        var body = document.getElementById('projTreeRows');
        var next = {{ count($treeRows) }};
        function money(n) { return '₦' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function scheduleRate(tr) {
            var n = tr.querySelector('.pt-name').value, m = tr.querySelector('.pt-mat').value;
            return n && RATES[n] && RATES[n][m] != null ? RATES[n][m] : null;
        }
        // The rate follows the schedule while it still shows the schedule's figure (or is empty).
        function fillRate(tr) {
            var rate = tr.querySelector('.pt-rate'), auto = scheduleRate(tr);
            if (rate.value === '' || rate.dataset.auto === rate.value) {
                rate.value = auto == null ? '' : auto;
                rate.dataset.auto = rate.value;
            }
            rate.placeholder = auto == null && tr.querySelector('.pt-name').value ? 'No rate: type it' : '';
            total();
        }
        function total() {
            var sum = 0, rows = body.querySelectorAll('tr[data-row]');
            rows.forEach(function (tr, i) {
                tr.querySelector('[data-sn]').textContent = i + 1;
                var s = parseFloat(tr.querySelector('.pt-stands').value) || 0, r = parseFloat(tr.querySelector('.pt-rate').value);
                var amt = s && !isNaN(r) ? s * r : null;
                tr.querySelector('[data-amount]').textContent = amt == null ? '—' : money(amt);
                sum += amt || 0;
            });
            document.getElementById('projTreeEmpty').style.display = rows.length ? 'none' : '';
            document.getElementById('projTreeTotal').innerHTML = rows.length ? 'Total for economic trees: <strong>' + money(sum) + '</strong>' : '';
        }
        function $j() { return window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 ? window.jQuery : null; }
        function wire(tr) {
            var name = tr.querySelector('.pt-name'), mat = tr.querySelector('.pt-mat'), $ = $j();
            if ($) $(name).select2({ width: '100%', placeholder: '— Select tree —' }).on('change', function () { fillRate(tr); });
            else name.addEventListener('change', function () { fillRate(tr); });
            mat.addEventListener('change', function () { fillRate(tr); });
            tr.querySelector('.pt-stands').addEventListener('input', total);
            tr.querySelector('.pt-rate').addEventListener('input', total);
            var rate = tr.querySelector('.pt-rate'), auto = scheduleRate(tr);
            if (auto != null && rate.value !== '' && parseFloat(rate.value) === auto) rate.dataset.auto = rate.value;
        }
        function add() {
            var tmp = document.createElement('tbody');
            tmp.innerHTML = document.getElementById('projTreeTpl').innerHTML.replace(/__i__/g, 'n' + (next++)).trim();
            var tr = tmp.querySelector('tr');
            body.insertBefore(tr, document.getElementById('projTreeEmpty'));
            wire(tr);
            var $ = $j();
            if ($) $(tr.querySelector('.pt-name')).select2('open'); else tr.querySelector('.pt-name').focus();
            total();
        }
        function remove(btn) {
            var tr = btn.closest('tr'), $ = $j(), sel = tr.querySelector('.pt-name');
            if ($ && $(sel).data('select2')) $(sel).select2('destroy');
            tr.remove();
            total();
        }
        document.addEventListener('DOMContentLoaded', function () {
            body.querySelectorAll('tr[data-row]').forEach(wire);
            total();
        });
        return { add: add, remove: remove, total: total };
    })();

    // Scheme tiles behave like the radio group they wrap.
    function selectNewProjectType(type) {
        var mon = document.getElementById('newTypeOptMonetary');
        var land = document.getElementById('newTypeOptLand');
        var radio = document.querySelector('input[name="scheme_type"][value="' + type + '"]');
        if (!radio || radio.disabled) return;
        radio.checked = true;
        if (mon) mon.classList.toggle('selected', type === 'monetary');
        if (land) land.classList.toggle('selected', type === 'land');
        // The sharing ratio belongs to Land to Land only.
        var box = document.getElementById('projRatioBox'), sel = box ? box.querySelector('select') : null;
        if (box) box.style.display = type === 'land' ? '' : 'none';
        if (sel) sel.disabled = type !== 'land';
        // Economic trees belong to Monetary Compensation only; their lines are dropped on save otherwise.
        var trees = document.getElementById('projTreeSection');
        if (trees) trees.style.display = type === 'monetary' ? '' : 'none';
        if (trees) trees.querySelectorAll('select, input').forEach(function (el) { el.disabled = type !== 'monetary'; });
    }
</script>
