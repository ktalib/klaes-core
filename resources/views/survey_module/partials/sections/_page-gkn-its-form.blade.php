@php
    $editing = $its->exists;
    $issued  = $its->isIssued();
    $locked  = $issued || $its->isClosed();
    $ro      = 'background:var(--gray-100);color:var(--gray-700);';
@endphp

@include('survey_module.partials._flash')

{{-- The template form (user, 2026-11-10: the Private I to S form replaces Public's, keeping Computation File, Survey
     Type, Beacon Numbers and the staff surveyor). Full capitals as saved; a detail filled from the Computation File is
     locked and greyed (still sent); Job Scope, Remarks and the Additional Instruction keep their case. --}}
<style>
    #itsForm input[type="text"], #itsForm .its-caps .select2-selection__rendered, #itsReturnForm .its-caps .select2-selection__rendered { text-transform: uppercase; }
    #itsForm input::placeholder, #itsForm .select2-selection__placeholder { text-transform: none; }
    #itsForm .its-sec, #itsReturnForm .its-sec { border: 1px solid var(--gray-200, #e5e7eb); border-radius: 8px; padding: 10px 16px 2px; margin: 0 0 14px; }
    #itsForm .its-sec > legend, #itsReturnForm .its-sec > legend { padding: 0 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--primary, #0f766e); }
    #itsForm .its-fixed { padding: 8px 12px; border: 1px dashed var(--gray-300, #d1d5db); border-radius: 6px; background: var(--gray-50, #f9fafb); font-size: 13px; line-height: 1.55; color: var(--gray-700, #374151); }
    #itsForm .its-fixed em { display: block; margin-top: 4px; font-size: 12px; color: var(--gray-500, #6b7280); }
    #itsForm .its-plan-pick { margin-top: 8px; border: 1px solid var(--gray-200, #e5e7eb); border-radius: 8px; overflow: hidden; }
    #itsForm .its-plan-pick-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 6px 10px; background: var(--gray-50, #f9fafb); font-size: 12px; }
    #itsForm .its-plan-pick-body img { display: block; width: 100%; max-height: 240px; object-fit: contain; background: #fff; }
    #itsForm .its-plan-pick-body iframe { display: block; width: 100%; height: 240px; border: 0; }
    .its-plan-modal { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 23, 42, .65); }
    .its-plan-modal-card { display: flex; flex-direction: column; width: min(1000px, 100%); height: min(88vh, 900px); background: #fff; border-radius: 10px; overflow: hidden; }
    .its-plan-modal-head { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid var(--gray-200, #e5e7eb); font-weight: 600; }
    .its-plan-modal-body { flex: 1; min-height: 0; background: var(--gray-100, #f3f4f6); display: flex; align-items: center; justify-content: center; }
    .its-plan-modal-body img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .its-plan-modal-body iframe { width: 100%; height: 100%; border: 0; background: #fff; }
    #itsForm .its-grid-4, #itsReturnForm .its-grid-4 { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0 16px; }
    @media (max-width: 900px) { #itsForm .its-grid-4, #itsReturnForm .its-grid-4 { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 600px) { #itsForm .its-grid-4, #itsReturnForm .its-grid-4 { grid-template-columns: 1fr; } }
    #itsForm input.its-locked { background: var(--gray-100, #f3f4f6) !important; color: var(--gray-600, #4b5563) !important; cursor: not-allowed; }
    #itsForm .select2.its-locked { pointer-events: none; }
    #itsForm .select2.its-locked .select2-selection { background: var(--gray-100, #f3f4f6) !important; cursor: not-allowed; }
    #itsForm .select2.its-locked .select2-selection__clear { display: none; }
    /* Land File selector in a quarter of the row: the number on its own line, the buttons under it. */
    #itsLandPicker > div { flex-wrap: wrap !important; }
    #itsLandPicker > div > input { flex: 1 0 100% !important; }
</style>

@if ($issued)
    <div class="comp-type-note" style="margin-bottom:16px;">
        <i class="fas fa-lock"></i>
        Instruction <strong>{{ $its->its_number }}</strong> was issued on {{ optional($its->its_issued_at)->format('d M Y') }}
        by {{ $its->its_issued_by ?: '—' }}; it can no longer be edited.
        <a href="{{ route('survey-module.gkn.its.print', $its) }}" target="_blank">Print it</a>.
    </div>
@endif

<form method="POST" action="{{ $editing ? route('survey-module.gkn.its.update', $its) : route('survey-module.gkn.its.store') }}" id="itsForm" enctype="multipart/form-data">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="form-container">
        <div class="form-body">
            {{-- Grouped as the template reads (user, 2026-11-10: "group the form layout properly"); Job Scope is
                 not on the template and is no longer asked (a job keeps any it already has). --}}
                {{-- Select the Computation File first; it fills the read-only File Number below. --}}
                {{-- Also LPKN files already in File Indexing (user, 2026-11-03), posted as idx:<file number>. --}}
                @php
                    $cfPick = (string) old('survey_computation_file_id', $its->survey_computation_file_id
                        ?: ($its->computation_no ? 'idx:' . $its->computation_no : ''));
                    $cfIdx = collect($indexedLpkn ?? []);
                @endphp
            {{-- Survey type + beacons (CR 03112026 item 11). Searchable with Other, as on the Computation File (user, 2026-11-06). --}}
            @php
                $stNames = collect($surveyTypes ?? [])->pluck('name');
                $stSaved = $its->survey_type;
                $stPick = old('survey_type', $stSaved === null ? '' : ($stNames->contains($stSaved) ? $stSaved : 'Other'));
                $stOther = old('survey_type_other', $stPick === 'Other' ? $stSaved : '');
                $bPicks = collect(session()->hasOldInput() ? old('beacon_picks', []) : ($beaconList ?? []));
                $bOtherOn = $bPicks->contains('Other');
            @endphp
            <fieldset class="its-sec">
                <legend>File</legend>
                {{-- Computation File No. | File Number | Land File | File Title on one row (user, 2026-11-11). --}}
                <div class="form-grid its-grid-4">
                    <div class="form-group">
                        <label>Computation File No.</label>
                        <select name="survey_computation_file_id" id="itsComputation" class="sv-searchable" data-placeholder="Search computation files…" @disabled($locked)>
                            <option value="">— None —</option>
                            @foreach ($computationFiles ?? [] as $cf)
                                <option value="{{ $cf->id }}" @selected($cfPick === (string) $cf->id)
                                        data-file-number="{{ $cf->file_number }}"
                                        data-title="{{ $cf->file_title }}" data-plot="{{ $cf->plot_no }}" data-district="{{ $cf->district }}"
                                        data-lga="{{ $cf->lga }}" data-surveyor="{{ $cf->surveyors_list_id }}" data-type="{{ $cf->survey_type }}" data-beacons="{{ $cf->beacon_list }}">
                                    {{ $cf->computation_no }}{{ $cf->file_number ? ' · ' . $cf->file_number : '' }}{{ $cf->file_title ? ' · ' . \Illuminate\Support\Str::limit($cf->file_title, 40) : '' }}
                                </option>
                            @endforeach
                            @if ($cfIdx->isNotEmpty())
                                <optgroup label="Existing LPKN files (File Indexing)">
                                    @foreach ($cfIdx as $fi)
                                        <option value="idx:{{ $fi->file_number }}" @selected($cfPick === 'idx:' . $fi->file_number)
                                                data-file-number="{{ $fi->file_number }}"
                                                data-title="{{ $fi->file_title }}" data-plot="{{ $fi->plot_number }}" data-district="{{ $fi->district }}"
                                                data-lga="{{ $fi->lga }}" data-surveyor="">
                                            {{ $fi->file_number }}{{ $fi->file_title ? ' · ' . \Illuminate\Support\Str::limit($fi->file_title, 40) : '' }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                            @if ($its->computation_no && $its->survey_computation_file_id && ! collect($computationFiles ?? [])->contains('id', $its->survey_computation_file_id))
                                <option value="{{ $its->survey_computation_file_id }}" selected>{{ $its->computation_no }}</option>
                            @elseif ($its->computation_no && ! $its->survey_computation_file_id && ! $cfIdx->contains('file_number', $its->computation_no))
                                <option value="idx:{{ $its->computation_no }}" selected>{{ $its->computation_no }}</option>
                            @endif
                        </select>
                        @error('survey_computation_file_id')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                        <p class="helper-text">Optional. LPKN Computation Files commissioned by Survey, and existing LPKN files from File Indexing.</p>
                    </div>
                    <div class="form-group">
                        <label>File Number</label>
                        <input type="text" name="file_number" id="itsFileNumber" readonly
                               value="{{ old('file_number', $its->file_number) }}"
                               placeholder="{{ $editing ? '' : 'Select a Computation File' }}"
                               style="max-width:100%;font-weight:700;{{ $ro }}cursor:not-allowed;" />
                        <p class="helper-text" id="itsFileNumberStatus">
                            {{ $editing && $its->file_number ? 'A survey job keeps its file.' : 'Filled from the selected Computation File.' }}
                        </p>
                    </div>
                    {{-- The Land File, a fourth field on the row (CR 10112026 call: "where is the attached file number? ... it
                         has to be optional ... a land file"; user 2026-11-11: "name it land file", with the Select File
                         Number button). Its details fill the form; the plan in item 4 is filed into its EDMS folder. --}}
                    @include('survey_module.partials._file_picker', [
                        'id' => 'itsLand', 'name' => 'land_file_number', 'value' => $its->land_file_number, 'label' => 'Land File',
                        'fill' => ['title' => 'itsTitle', 'plot' => 'itsPlot', 'district' => 'itsDistrict', 'lga' => 'itsLga'],
                        'locked' => $locked,
                    ])
                    {{-- File Title beside the File Number (user, 2026-11-10). --}}
                    <div class="form-group">
                        <label>File Title</label>
                        <input type="text" name="file_title" id="itsTitle" maxlength="500" value="{{ old('file_title', $its->file_title) }}" @disabled($locked) />
                    </div>
                    @if ($editing)
                        <div class="form-group">
                            <label>Survey Job No.</label>
                            <input type="text" value="{{ $its->jobNo() ?: $its->job_ref }}" readonly style="{{ $ro }}" />
                        </div>
                    @endif
                </div>
            </fieldset>

            <fieldset class="its-sec">
                <legend>Property</legend>
                {{-- Plot No., District, LGA, Location. --}}
                <div class="form-grid its-grid-4">
                    <div class="form-group">
                        <label>Plot No.</label>
                        <input type="text" name="plot_no" id="itsPlot" maxlength="100" value="{{ old('plot_no', $its->plot_no) }}" @disabled($locked) />
                    </div>
                    {{-- Dropdowns searched from the lookups, with Other (specify), as on the template form. --}}
                    @foreach (['district' => ['itsDistrict', 'District', 'Search district…'], 'lga' => ['itsLga', 'LGA', 'Search LGA…']] as $f => [$fid, $flabel, $fph])
                        @php $fv = old($f, $its->{$f}); @endphp
                        <div class="form-group its-caps">
                            <label>{{ $flabel }}</label>
                            <select name="{{ $f }}" id="{{ $fid }}" data-no-search data-placeholder="{{ $fph }}" style="width:100%;" @disabled($locked)>
                                <option value=""></option>
                                @if ($fv)<option value="{{ $fv }}" selected>{{ mb_strtoupper($fv) }}</option>@endif
                            </select>
                            <input type="text" name="{{ $f }}_other" id="{{ $fid }}Other" maxlength="255" value="{{ old($f . '_other') }}" placeholder="Specify the {{ $f === 'lga' ? 'LGA' : 'district' }} *"
                                   style="margin-top:6px;{{ $fv === 'Other' ? '' : 'display:none;' }}" @disabled($fv !== 'Other' || $locked) />
                            @error($f . '_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div class="form-group">
                        <label>Location (auto)</label>
                        <input type="text" id="itsLocation" readonly tabindex="-1" style="{{ $ro }}" placeholder="District, LGA" value="{{ mb_strtoupper((string) $its->location) }}" />
                    </div>
                </div>
            </fieldset>

            <fieldset class="its-sec">
                <legend>To: The Field Surveyor</legend>
                <div class="form-grid cols-3">
                    <div class="form-group">
                        <label>Surveyor <span class="required">*</span></label>
                        @php
                            // A surveyor typed under "Other (specify)" has a name but no List of Surveyors id.
                            $svPick = (string) old('surveyor_id', $its->surveyor_id ?: ($its->surveyor_name ? 'other' : ''));
                            $svOther = old('surveyor_other', $its->surveyor_id ? '' : $its->surveyor_name);
                        @endphp
                        <select name="surveyor_id" id="itsSurveyor" @disabled($locked)>
                            <option value="">— Select from the List of Surveyors —</option>
                            @foreach ($surveyors as $s)
                                <option value="{{ $s->id }}" @selected($svPick === (string) $s->id) data-rank="{{ $s->rank }}">
                                    {{ $s->full_name }}{{ $s->rank ? ' · ' . $s->rank : '' }}{{ $s->is_active ? '' : ' (inactive)' }}
                                </option>
                            @endforeach
                            <option value="other" @selected($svPick === 'other')>Other (specify)</option>
                        </select>
                        <input type="text" name="surveyor_other" id="itsSurveyorOther" maxlength="255" value="{{ $svOther }}"
                               placeholder="Specify surveyor's full name *" aria-label="Specify surveyor" style="margin-top:6px;{{ $svPick === 'other' ? '' : 'display:none;' }}"
                               class="@error('surveyor_other') is-invalid @enderror"
                               @if ($svPick === 'other' && ! $locked) required @else disabled @endif />
                        @error('surveyor_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                        <p class="helper-text">Survey Department staff, or Other (specify). Needed before the instruction is issued.</p>
                    </div>
                    {{-- Rank (CR 10112026 call: "where would they select the rank? It has to show on the front end"): the
                         ranks on the List of Surveyors, with Other; filled from the surveyor picked, editable. Prints on page 2. --}}
                    @php
                        $rankList = collect($ranks ?? []);
                        $rkSaved = (string) $its->surveyor_rank;
                        $rkPick = old('surveyor_rank', $rkSaved === '' ? '' : ($rankList->contains($rkSaved) ? $rkSaved : 'Other'));
                        $rkOther = old('surveyor_rank_other', $rkPick === 'Other' ? $rkSaved : '');
                    @endphp
                    <div class="form-group">
                        <label>Rank</label>
                        <select name="surveyor_rank" id="itsRank" @disabled($locked)>
                            <option value="">— Select rank —</option>
                            @foreach ($rankList as $rk)<option value="{{ $rk }}" @selected($rkPick === $rk)>{{ $rk }}</option>@endforeach
                            <option value="Other" @selected($rkPick === 'Other')>Other (specify)</option>
                        </select>
                        <input type="text" name="surveyor_rank_other" id="itsRankOther" maxlength="150" value="{{ $rkOther }}" placeholder="Specify the rank *" aria-label="Specify the rank"
                               style="margin-top:6px;text-transform:none;{{ $rkPick === 'Other' ? '' : 'display:none;' }}" @if ($rkPick === 'Other' && ! $locked) required @else disabled @endif />
                        @error('surveyor_rank_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                        <p class="helper-text">Filled from the List of Surveyors when the surveyor is picked.</p>
                    </div>
                </div>
            </fieldset>

            <fieldset class="its-sec">
                <legend>Instruction to Survey</legend>
                <div class="form-grid cols-3">
                    {{-- Items 1 to 4 as the template numbers them (user, 2026-11-10: "you missed some fields, e.g. number 4"). --}}
                    <div class="form-group full its-item">
                        <label>1. Technical Instructions</label>
                        <div class="its-fixed">You are instructed to demarcate and survey the above job in accordance with Technical Instructions. Particular attention must be paid to making an adequate tie for Cadastral purpose.</div>
                    </div>
                    {{-- Both sub-items print; their bold words are the data (user, 2026-11-10): a dropdown each, the paper's word
                         first, with Other (specify). --}}
                    @foreach ([
                        'its_control_condition' => ['1) Where Cadastral Control is available a tie must be made to control …', \App\Models\Survey\SurveyIts::CONTROL_CONDITIONS],
                        'its_detail_condition' => ['2) Where a tie cannot be made within 3 days it is sufficient to tie to two points of detail … on the topographic map', \App\Models\Survey\SurveyIts::DETAIL_CONDITIONS],
                    ] as $tf => [$tlabel, $topts])
                        @php
                            $tSaved = $its->{$tf};
                            $tPick = old($tf, $tSaved === null || $tSaved === '' ? $topts[0] : (in_array($tSaved, $topts, true) ? $tSaved : 'Other'));
                            $tOther = old($tf . '_other', $tPick === 'Other' ? $tSaved : '');
                        @endphp
                        <div class="form-group {{ $loop->first ? '' : 'span-2' }}">
                            <label>{{ $tlabel }}</label>
                            <select name="{{ $tf }}" id="{{ $tf }}" data-no-search @disabled($locked)
                                    onchange="var o=document.getElementById('{{ $tf }}_other'),on=this.value==='Other';o.style.display=on?'':'none';o.disabled=!on;o.required=on;if(on)o.focus();">
                                @foreach ($topts as $opt)<option value="{{ $opt }}" @selected($tPick === $opt)>{{ $opt }}</option>@endforeach
                                <option value="Other" @selected($tPick === 'Other')>Other (specify)</option>
                            </select>
                            <input type="text" name="{{ $tf }}_other" id="{{ $tf }}_other" maxlength="150" value="{{ $tOther }}" placeholder="Specify *" style="margin-top:6px;text-transform:none;{{ $tPick === 'Other' ? '' : 'display:none;' }}"
                                   @if ($tPick === 'Other' && ! $locked) required @else disabled @endif />
                            @error($tf . '_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div class="form-group">
                        <label>2. Road reservation: parallel to and … from the centre line of the road</label>
                        <input type="text" name="its_road_reservation" maxlength="100" value="{{ old('its_road_reservation', $its->its_road_reservation) }}" placeholder="e.g. 50 feet" @disabled($locked) />
                    </div>
                    <div class="form-group">
                        <label>3. Rail reservation: parallel to and … from the centre line of the railway</label>
                        <input type="text" name="its_rail_reservation" maxlength="100" value="{{ old('its_rail_reservation', $its->its_rail_reservation) }}" placeholder="e.g. 50 feet" @disabled($locked) />
                    </div>
                    {{-- Item 4: the plan it names, filed into the file's EDMS folder (module uploads go through EDMS). --}}
                    <div class="form-group">
                        <label>4. The land to be demarcated is as per the attached plan</label>
                        @php $plan = $attachedPlan ?? null; @endphp
                        {{-- Preview (user, 2026-11-10): the plan already attached opens in a pop-up; a file chosen shows below
                             before it is saved. --}}
                        @if ($plan && $plan->url)
                            <p class="helper-text" style="margin:0 0 6px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <a href="{{ $plan->url }}" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> {{ $plan->original_name }}</a>
                                <button type="button" class="btn btn-outline btn-sm" data-plan-preview="{{ $plan->url }}" data-plan-kind="{{ str_starts_with((string) $plan->mime, 'image/') ? 'image' : 'pdf' }}" data-plan-name="{{ $plan->original_name }}">
                                    <i class="fas fa-eye"></i> Preview</button>
                            </p>
                        @endif
                        @unless ($locked)
                            <input type="file" name="attached_plan" id="itsPlanFile" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" />
                            <p class="helper-text" id="itsPlanHelp">{{ $plan ? 'Attach a new plan to replace it. ' : 'Optional. ' }}PDF, JPG or PNG; filed into the Land File's EDMS folder (or the File Number's when there is no Land File), so one must be selected.</p>
                            <div id="itsPlanPick" class="its-plan-pick" hidden>
                                <div class="its-plan-pick-head">
                                    <span id="itsPlanPickName"></span>
                                    <span style="display:flex;gap:6px;">
                                        <button type="button" class="btn btn-outline btn-sm" id="itsPlanPickOpen"><i class="fas fa-expand"></i> Larger</button>
                                        <button type="button" class="btn btn-outline btn-sm" id="itsPlanPickClear"><i class="fas fa-times"></i> Remove</button>
                                    </span>
                                </div>
                                <div id="itsPlanPickBody" class="its-plan-pick-body"></div>
                            </div>
                        @endunless
                        @if ($locked && ! $plan)<p class="helper-text">No plan attached.</p>@endif
                        @error('attached_plan')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label>Survey Type</label>
                        <select name="survey_type" id="itsSurveyType" class="sv-searchable" @disabled($locked)
                                onchange="var o=document.getElementById('itsSurveyTypeOther'),on=this.value==='Other';o.style.display=on?'':'none';o.disabled=!on;o.required=on;">
                            <option value="">Select survey type…</option>
                            @foreach ($surveyTypes ?? [] as $st)
                                <option value="{{ $st->name }}" @selected($stPick === $st->name)>{{ $st->name }}</option>
                            @endforeach
                            <option value="Other" @selected($stPick === 'Other')>Other (specify)</option>
                        </select>
                        <input type="text" name="survey_type_other" id="itsSurveyTypeOther" maxlength="100" value="{{ $stOther }}" placeholder="Specify the survey type *" aria-label="Specify the survey type"
                               style="margin-top:6px;{{ $stPick === 'Other' ? '' : 'display:none;' }}" class="@error('survey_type_other') is-invalid @enderror"
                               @if ($stPick === 'Other' && ! $locked) required @else disabled @endif />
                        @error('survey_type_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group span-2">
                        <label>Beacon Numbers</label>
                        <style>#itsForm .select2-selection--multiple .select2-selection__choice { padding-left: 24px !important; }</style>
                        <select name="beacon_picks[]" id="itsBeacons" multiple data-no-search aria-label="Beacon Numbers" style="width:100%;" @disabled($locked)>
                            @foreach ($bPicks as $b)
                                <option value="{{ $b }}" selected>{{ $b === 'Other' ? 'Other (specify)' : $b }}</option>
                            @endforeach
                        </select>
                        <textarea name="beacon_other" id="itsBeaconOther" rows="2" maxlength="8000" placeholder="Type the numbers *, e.g. KDJ 0001 to KDJ 0004, A1404B" aria-label="Other beacon numbers"
                                  style="margin-top:6px;{{ $bOtherOn ? '' : 'display:none;' }}" @if ($bOtherOn && ! $locked) required @else disabled @endif>{{ old('beacon_other') }}</textarea>
                        @error('beacon_picks')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                        @error('beacon_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                        <p class="helper-text" id="itsCfBeacons" style="display:none;"></p>
                        <p class="helper-text">Search the numbers free for this surveyor, or choose Other to type numbers and ranges. A number on another job, or issued to another surveyor, is refused.</p>
                    </div>
                    <div class="form-group full">
                        <label>Remarks</label>
                        <textarea name="remarks" rows="3" maxlength="2000" @disabled($locked)>{{ old('remarks', $its->remarks) }}</textarea>
                    </div>
                </div>
            </fieldset>

            @unless ($locked)
                @include('survey_module.partials.sections._page-gkn-its-return')
            @endunless

            <div class="form-actions">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ route('survey-module.gkn.its.index') }}"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                <div class="right">
                    @unless ($locked)
                        <button class="btn btn-success" type="submit">
                            <i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Issue I to S' }}
                        </button>
                    @endunless
                </div>
            </div>
        </div>
    </div>
</form>

{{-- The surveyor returns the field jacket after the instruction is locked: the return slip stays open (not cancelled). --}}
@if ($editing && $locked)
    @php $retOff = $its->status === \App\Models\Survey\SurveyIts::CANCELLED; @endphp
    <form method="POST" action="{{ route('survey-module.gkn.its.return', $its) }}" id="itsReturnForm" style="margin-top:20px;">
        @csrf
        <div class="form-container">
            <div class="form-body">
                @include('survey_module.partials.sections._page-gkn-its-return', ['retOff' => $retOff])
                @unless ($retOff)
                    <div class="form-actions">
                        <div class="left"></div>
                        <div class="right"><button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Return</button></div>
                    </div>
                @endunless
            </div>
        </div>
    </form>
@endif

{{-- Issue: after the job is registered. The instruction text is offered and can be edited first. --}}
@if ($editing && ! $issued && ! $its->isClosed())
    <form method="POST" action="{{ route('survey-module.gkn.its.issue', $its) }}" style="margin-top:20px;"
          onsubmit="return confirm('Issue the instruction? It can no longer be edited afterwards.');">
        @csrf
        <div class="form-container">
            <div class="form-body">
                <h3 style="margin:0 0 10px;font-size:16px;font-weight:700;"><i class="fas fa-file-signature"></i> Signed by</h3>
                <p class="helper-text" style="margin:0 0 10px;">{{ mb_strtoupper((string) (auth()->user()->name ?? '')) }} · {{ now()->format('d/m/Y') }}. Issued to {{ $its->surveyor_name ?: '(no surveyor yet — choose one above and save)' }}; the I to S number is given on issue.</p>
                <div class="form-group full">
                    <label>Additional Instruction</label>
                    <textarea name="its_instructions" rows="4" maxlength="8000" placeholder="Printed on page 2 under Additional Instruction">{{ old('its_instructions', $its->its_instructions) }}</textarea>
                </div>
                <div class="form-actions">
                    <div class="left"></div>
                    <div class="right">
                        <button class="btn btn-primary" type="submit" @disabled(! $its->surveyor_name)><i class="fas fa-paper-plane"></i> Issue Instruction</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endif

@push('scripts')
<script>
// Surveyor: searchable (Select2, as elsewhere in the module) with "Other (specify)",
// which reveals a required name box; any other choice hides, clears and disables it.
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('itsSurveyor');
    var box = document.getElementById('itsSurveyorOther');
    if (!sel || !box) return;
    function sync() {
        var on = sel.value === 'other';
        box.style.display = on ? '' : 'none';
        box.disabled = !on || sel.disabled;
        box.required = on && !sel.disabled;
        if (!on) box.value = '';
    }
    // Rank follows the surveyor picked (their List of Surveyors rank); Other opens its box.
    var rank = document.getElementById('itsRank'), rankOther = document.getElementById('itsRankOther');
    function syncRankBox() {
        if (!rank || !rankOther) return;
        var on = rank.value === 'Other';
        rankOther.style.display = on ? '' : 'none'; rankOther.disabled = !on || rank.disabled; rankOther.required = on && !rank.disabled;
        if (!on) rankOther.value = '';
    }
    function rankFromSurveyor() {
        if (!rank || rank.disabled) return;
        var o = sel.options[sel.selectedIndex], want = o ? (o.getAttribute('data-rank') || '') : '';
        if (!want) return;
        var listed = Array.prototype.some.call(rank.options, function (x) { return x.value === want; });
        rank.value = listed ? want : 'Other';
        syncRankBox();
        if (!listed && rankOther) rankOther.value = want;
    }
    if (rank) rank.addEventListener('change', syncRankBox);
    var $ = window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 ? window.jQuery : null;
    if ($) {
        // No dropdownParent: the default (<body>) keeps the panel clear of the scroll containers.
        $(sel).select2({ width: '100%', placeholder: '— Select from the List of Surveyors —' })
              .on('change', function () { sync(); rankFromSurveyor(); if (sel.value === 'other') box.focus(); });
    } else {
        sel.addEventListener('change', function () { sync(); rankFromSurveyor(); });
    }
    sync();
});
</script>
@endpush

<div id="itsPlanModal" class="its-plan-modal" role="dialog" aria-modal="true" aria-labelledby="itsPlanModalTitle" style="display:none;">
    <div class="its-plan-modal-card">
        <div class="its-plan-modal-head">
            <span id="itsPlanModalTitle">Attached plan</span>
            <button type="button" class="btn btn-secondary btn-sm" id="itsPlanModalClose"><i class="fas fa-times"></i> Close</button>
        </div>
        <div class="its-plan-modal-body" id="itsPlanModalBody"></div>
    </div>
</div>

@push('scripts')
<script>
// Attached plan preview (user, 2026-11-10): a PDF in a frame, a picture as an image; the chosen file before Save.
(function () {
    var modal = document.getElementById('itsPlanModal'), body = document.getElementById('itsPlanModalBody'), title = document.getElementById('itsPlanModalTitle');
    function view(holder, url, kind) {
        holder.textContent = '';
        var el = document.createElement(kind === 'image' ? 'img' : 'iframe');
        el.src = url;
        if (kind === 'image') el.alt = 'Attached plan'; else el.title = 'Attached plan';
        holder.appendChild(el);
    }
    function openModal(url, kind, name) { title.textContent = name || 'Attached plan'; view(body, url, kind); modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
    function closeModal() { modal.style.display = 'none'; body.textContent = ''; document.body.style.overflow = ''; }
    document.getElementById('itsPlanModalClose').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.style.display !== 'none') closeModal(); });
    document.querySelectorAll('[data-plan-preview]').forEach(function (b) {
        b.addEventListener('click', function () { openModal(b.dataset.planPreview, b.dataset.planKind, b.dataset.planName); });
    });

    var input = document.getElementById('itsPlanFile');
    if (!input) return;
    var pick = document.getElementById('itsPlanPick'), pickBody = document.getElementById('itsPlanPickBody'), pickName = document.getElementById('itsPlanPickName'), objectUrl = null, kind = null;
    function clearPick() {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null; kind = null; pick.hidden = true; pickBody.textContent = ''; pickName.textContent = '';
    }
    input.addEventListener('change', function () {
        clearPick();
        var f = input.files && input.files[0];
        if (!f) return;
        // The plan is filed into the file's EDMS folder (user, 2026-11-11: "file number must be selected").
        // Filed into the Land File, or the File Number when there is no Land File.
        var has = function (id) { var el = document.getElementById(id); return !!(el && String(el.value).trim()); };
        if (!has('itsLandFileNumber') && !has('itsFileNumber')) {
            input.value = '';
            var help = document.getElementById('itsPlanHelp');
            if (help) { help.textContent = 'Select the Land File (or a Computation File) above before attaching the plan: it is filed into that file\'s EDMS folder.'; help.style.color = 'var(--danger)'; }
            return;
        }
        var isImage = /^image\/(jpeg|png)$/.test(f.type), isPdf = f.type === 'application/pdf' || /\.pdf$/i.test(f.name);
        pick.hidden = false;
        pickName.textContent = f.name + ' · ' + (f.size >= 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB');
        if (!isImage && !isPdf) { pickBody.innerHTML = '<p class="helper-text" style="padding:10px;margin:0;color:var(--danger);">Only PDF, JPG and PNG can be attached; this file will be refused.</p>'; return; }
        objectUrl = URL.createObjectURL(f); kind = isImage ? 'image' : 'pdf';
        view(pickBody, objectUrl, kind);
    });
    document.getElementById('itsPlanPickOpen').addEventListener('click', function () { if (objectUrl) openModal(objectUrl, kind, pickName.textContent); });
    document.getElementById('itsPlanPickClear').addEventListener('click', function () { input.value = ''; clearPick(); });
})();

// District / LGA dropdowns (2026-11-10), the template form's: searched from the lookups, Other opens its box, Location follows.
var itsFilled = {};
function itsSet(el, value) {
    if (el.tagName !== 'SELECT') { el.value = value; return; }
    if (value && !Array.prototype.some.call(el.options, function (o) { return o.value === value; })) el.add(new Option(String(value).toUpperCase(), value, true, true));
    el.value = value;
    if (window.jQuery && jQuery(el).data('select2')) jQuery(el).trigger('change'); else el.dispatchEvent(new Event('change'));
}
// Locked, not disabled: a disabled field would not be sent.
function itsLock(el, on) {
    if (el.tagName === 'SELECT') {
        var box = el.nextElementSibling && el.nextElementSibling.classList.contains('select2') ? el.nextElementSibling : null;
        if (box) box.classList.toggle('its-locked', on);
    } else { el.readOnly = on; el.classList.toggle('its-locked', on); }
    el.tabIndex = on ? -1 : 0;
}
function itsUnfill() {
    Object.keys(itsFilled).forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        itsLock(el, false);
        if (el.value === itsFilled[id]) itsSet(el, '');
    });
    itsFilled = {};
}
function itsSyncPlace() {
    var named = function (id) { var s = document.getElementById(id), o = document.getElementById(id + 'Other'); return s ? (s.value === 'Other' ? (o ? o.value : '') : s.value) : ''; };
    ['itsDistrict', 'itsLga', 'itsRetDistrict', 'itsRetLga'].forEach(function (id) {
        var s = document.getElementById(id), o = document.getElementById(id + 'Other');
        if (!s || !o) return;
        var on = s.value === 'Other';
        o.style.display = on ? '' : 'none'; o.disabled = !on || s.disabled; o.required = on && !s.disabled;
    });
    var loc = document.getElementById('itsLocation');
    if (loc) loc.value = [named('itsDistrict'), named('itsLga')].map(function (v) { return String(v || '').trim().toUpperCase(); }).filter(Boolean).join(', ');
}
window.addEventListener('load', function () {
    // Page 2's Place uses the same District / LGA lookups (CR 10112026 call).
    [['itsDistrict', @json(route('survey-module.lookup.districts'))], ['itsLga', @json(route('survey-module.lookup.lgas'))],
     ['itsRetDistrict', @json(route('survey-module.lookup.districts'))], ['itsRetLga', @json(route('survey-module.lookup.lgas'))]].forEach(function (p) {
        var el = document.getElementById(p[0]);
        if (!el) return;
        ['input'].forEach(function (ev) { var o = document.getElementById(p[0] + 'Other'); if (o) o.addEventListener(ev, itsSyncPlace); });
        if (!(window.jQuery && jQuery.fn && jQuery.fn.select2)) { el.addEventListener('change', itsSyncPlace); return; }
        var up = function (o) { return o.text ? String(o.text).toUpperCase() : o.text; };
        jQuery(el).select2({
            width: '100%', allowClear: true, placeholder: jQuery(el).data('placeholder'),
            ajax: { url: p[1], dataType: 'json', delay: 250, cache: true, data: function (q) { return { q: q.term || '', page: q.page || 1 }; }, processResults: function (d) { return d; } },
            templateResult: up, templateSelection: up
        }).on('change', itsSyncPlace);
    });
    itsSyncPlace();
});

// Computation File No. (user, 2026-11-02): picking one fills the file number, title, plot, district, LGA and surveyor
// where they are still empty; nothing typed is overwritten.
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('itsComputation');
    if (!sel) return;
    function fill() {
        itsUnfill();
        var o = sel.options[sel.selectedIndex];
        var fileNumber = document.getElementById('itsFileNumber');
        var status = document.getElementById('itsFileNumberStatus');
        // A job that already has its file keeps it.
        var kept = @json($editing && trim((string) $its->file_number) !== '');
        if (!o || !o.value) {
            if (fileNumber && !kept) fileNumber.value = '';
            if (status && !kept) status.textContent = 'Select a Computation File.';
            return;
        }
        if (fileNumber && !kept) {
            fileNumber.value = o.dataset.fileNumber || '';
            if (status) {
                status.textContent = o.dataset.fileNumber ? 'File number filled from ' + o.textContent.trim().split(' · ')[0] + '.' : 'This Computation File has no file number.';
                status.style.color = '';
            }
        }
        [['itsTitle', 'title'], ['itsPlot', 'plot'], ['itsDistrict', 'district'], ['itsLga', 'lga']].forEach(function (p) {
            var el = document.getElementById(p[0]);
            if (el && !el.disabled && !String(el.value).trim() && o.dataset[p[1]]) { itsSet(el, o.dataset[p[1]]); itsFilled[p[0]] = o.dataset[p[1]]; itsLock(el, true); }
        });
        var sv = document.getElementById('itsSurveyor');
        if (sv && !sv.disabled && !sv.value && o.dataset.surveyor && sv.querySelector('option[value="' + o.dataset.surveyor + '"]')) {
            sv.value = o.dataset.surveyor;
            if (window.jQuery) window.jQuery(sv).trigger('change');
        }
    }
    if (window.jQuery) window.jQuery(sel).on('change', fill); else sel.addEventListener('change', fill);
});
</script>
@endpush

@push('scripts')
<script>
// Beacon Numbers: search the register, or Other for typed numbers and ranges. select2 loads after this script.
window.addEventListener('load', function () {
    var $b = window.jQuery && jQuery.fn.select2 ? jQuery('#itsBeacons') : null;
    if (!$b || !$b.length) return;
    var other = document.getElementById('itsBeaconOther'), surveyor = document.getElementById('itsSurveyor'),
        surveyorOther = document.getElementById('itsSurveyorOther');
    $b.select2({
        width: '100%', placeholder: 'Search beacon numbers…', closeOnSelect: false,
        ajax: {
            url: @json(route('survey-module.gkn.its.beacon-search')), delay: 250,
            data: function (p) { return { q: p.term || '', surveyor: surveyor.value, surveyor_other: surveyorOther.value, its: @json($its->id) }; },
            processResults: function (d) {
                return { results: (d.numbers || []).map(function (n) { return { id: n, text: n }; }).concat([{ id: 'Other', text: 'Other (specify)' }]) };
            }
        }
    }).on('change', function () {
        var on = ($b.val() || []).indexOf('Other') !== -1;
        other.style.display = on ? '' : 'none';
        other.disabled = !on;
        other.required = on;
    });
});

// Picking a Computation File fills its Survey Type and shows its beacons, which stay on that file. A new pick clears what the last one filled.
document.addEventListener('DOMContentLoaded', function () {
    var cf = document.getElementById('itsComputation'), type = document.getElementById('itsSurveyType'),
        typeOther = document.getElementById('itsSurveyTypeOther'), note = document.getElementById('itsCfBeacons');
    if (!cf || !type) return;
    var filled = '';
    function show() {
        var o = cf.options[cf.selectedIndex], beacons = o ? (o.getAttribute('data-beacons') || '') : '';
        note.textContent = beacons ? 'Already on this Computation File: ' + beacons + '.' : '';
        note.style.display = beacons ? '' : 'none';
    }
    function setType(value) {
        var listed = Array.prototype.some.call(type.options, function (x) { return x.value === value; });
        type.value = !value ? '' : (listed ? value : 'Other');
        typeOther.value = value && !listed ? value : '';
        type.dispatchEvent(new Event('change', { bubbles: true }));
    }
    cf.addEventListener('change', function () {
        var o = cf.options[cf.selectedIndex], want = o ? (o.getAttribute('data-type') || '') : '';
        var current = type.value === 'Other' ? typeOther.value : type.value;
        if (!type.disabled && (current === '' || current === filled)) { setType(want); filled = want; }
        show();
    });
    show();
});
</script>
@endpush
