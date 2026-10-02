{{--
    Report on Application questionnaire (rebuild plan §3a), on the report
    detail page.

    Filled on the chain's Report step: editable while that step is current and
    only by whoever may act on it (ReportWorkflow::applicationBlockReason()).
    Otherwise it renders read-only with the reason. Before the 2026_10_02_100000
    migration the columns do not exist and the panel says so; nothing else on
    the page depends on it.

    The sub-fields of each question are shown for the branch chosen; the server
    requires them on that branch and clears them on the other.

    Expects $report and $application (installed, blocked, area, refs).
--}}
@php
    $installed = $application['installed'];
    $blocked   = $application['blocked'];
    $editable  = $installed && ! $blocked;
    $v = fn (string $c) => old($c, $report->applicationValue($c));

    $area = $application['area'];
    $areaValue = old('area_applied_ha', $report->applicationValue('area_applied_ha'));
    $areaPrefilled = ($areaValue === null || $areaValue === '') && $area['ha'] !== null;
    if ($areaPrefilled) {
        $areaValue = $area['ha'];
    }

    $purpose = $v('q4_purpose');
    $purposeIsOther = $purpose !== null && $purpose !== '' && ! in_array($purpose, \App\Models\Cadastral\CadastralReport::APPLICATION_PURPOSES, true) && $purpose !== 'Other';
    $purposeSelect = $purposeIsOther ? 'Other' : $purpose;
    $purposeOther = $purposeIsOther ? $purpose : old('q4_purpose_other');

    $refs = $application['refs'];
@endphp

<style>
    .cadastral-proto .cad-roa .q { padding: 10px 0; border-bottom: 1px dashed var(--gray-200); }
    .cadastral-proto .cad-roa .q:last-child { border-bottom: 0; }
    .cadastral-proto .cad-roa .q-head { display: flex; justify-content: space-between; gap: 12px; align-items: center; font-weight: 600; font-size: 13.5px; color: var(--gray-800); }
    .cadastral-proto .cad-roa .yn { display: inline-flex; gap: 14px; font-weight: 500; white-space: nowrap; }
    .cadastral-proto .cad-roa .yn label { display: inline-flex; gap: 5px; align-items: center; font-weight: 500; margin: 0; }
    .cadastral-proto .cad-roa .sub { margin: 8px 0 0 22px; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
    .cadastral-proto .cad-roa .sub .yn-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 13px; grid-column: 1 / -1; }
    .cadastral-proto .cad-roa .sub label.lbl { display: block; font-size: 12px; color: var(--gray-600); margin-bottom: 3px; font-weight: 500; }
    .cadastral-proto .cad-roa input[type=text], .cadastral-proto .cad-roa input[type=number], .cadastral-proto .cad-roa select, .cadastral-proto .cad-roa textarea {
        width: 100%; padding: 7px 10px; border: 1px solid var(--gray-300); border-radius: 4px; font-size: 13px; font-family: var(--font);
    }
    .cadastral-proto .cad-roa [disabled] { background: var(--gray-100); color: var(--gray-700); }
    .cadastral-proto .cad-roa .branch[hidden] { display: none; }
</style>

<div class="form-container cad-roa" id="report-on-application" style="margin-top:18px;">
    <div class="card-header">
        <strong>Report on Application</strong>
        <span style="display:flex;gap:8px;align-items:center;">
            @canDo('Cad - Records', 'print')
                <a href="{{ route('cadastral-module.reports.application-print', $report) }}" target="_blank" class="btn btn-outline btn-sm">
                    <i class="fas fa-print"></i> Print the official form
                </a>
            @endcanDo
        </span>
    </div>

    @if (! $installed)
        <div class="form-body">
            <div class="caveat" style="margin:0;">
                <i class="fas fa-hourglass-half"></i>
                <div>
                    <strong>Pending installation.</strong>
                    The questionnaire is stored in new columns on <code>cadastral_reports</code> that arrive with
                    migration <code>2026_10_02_100000_add_application_report_columns_to_cadastral_reports</code>,
                    which has not been run yet. Everything else on this report works; the official form still prints,
                    with the file references and area filled and the questions left blank.
                </div>
            </div>
        </div>
    @else
        @if ($blocked)
            <div class="form-body" style="padding-bottom:0;">
                <div class="caveat" style="margin:0;">
                    <i class="fas fa-lock"></i>
                    <div>{{ $blocked }} The answers below are read-only.</div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('cadastral-module.reports.application.save', $report) }}">
            @csrf
            <fieldset @disabled(! $editable) style="border:0;margin:0;padding:0;min-width:0;">
                <div class="form-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>R. of O. No. LKN/</label>
                            <input type="text" value="{{ $refs['lkn'] }}" disabled />
                            <div class="helper-text">From the file number.</div>
                        </div>
                        <div class="form-group">
                            <label>SLTR/</label>
                            <input type="text" name="sltr_no" maxlength="100" value="{{ $v('sltr_no') }}" placeholder="{{ $refs['sltr'] }}" />
                            <div class="helper-text">Blank uses the file number when the file came from SLTR.</div>
                        </div>
                        <div class="form-group">
                            <label>Government Item No. GKN/</label>
                            <input type="text" name="govt_item_no" maxlength="100" value="{{ $v('govt_item_no') }}" />
                        </div>
                        <div class="form-group">
                            <label>SIT/</label>
                            <input type="text" name="sit_no" maxlength="100" value="{{ $v('sit_no') }}" placeholder="{{ $refs['sit'] }}" />
                            <div class="helper-text">Blank uses the file number when the file came from ST.</div>
                        </div>
                    </div>

                    @php
                        $yn = function (string $name) use ($v) {
                            $cur = $v($name);
                            return '<span class="yn">'
                                . '<label><input type="radio" name="' . e($name) . '" value="Yes"' . ($cur === 'Yes' ? ' checked' : '') . ' data-roa-toggle="' . e($name) . '"> Yes</label>'
                                . '<label><input type="radio" name="' . e($name) . '" value="No"' . ($cur === 'No' ? ' checked' : '') . ' data-roa-toggle="' . e($name) . '"> No</label>'
                                . '</span>';
                        };
                    @endphp

                    <div class="q">
                        <div class="q-head"><span>{{ \App\Models\Cadastral\CadastralReport::APPLICATION_QUESTIONS['q1_plan_sufficient'] }}</span>{!! $yn('q1_plan_sufficient') !!}</div>
                    </div>

                    <div class="q">
                        <div class="q-head"><span>{{ \App\Models\Cadastral\CadastralReport::APPLICATION_QUESTIONS['q2_ground_open'] }}</span>{!! $yn('q2_ground_open') !!}</div>
                        <div class="sub branch" data-roa-branch="q2_ground_open" data-roa-when="No">
                            <div style="grid-column:1/-1;">
                                <label class="lbl">If No, what title or application lies over the same land? *</label>
                                <input type="text" name="q2_overlapping_title" maxlength="500" value="{{ $v('q2_overlapping_title') }}" />
                            </div>
                        </div>
                    </div>

                    <div class="q">
                        <div class="q-head"><span>{{ \App\Models\Cadastral\CadastralReport::APPLICATION_QUESTIONS['q3_beaconed'] }}</span>{!! $yn('q3_beaconed') !!}</div>
                        <div class="sub branch" data-roa-branch="q3_beaconed" data-roa-when="Yes">
                            <div>
                                <label class="lbl">If Yes: (a) Tracing No. *</label>
                                <input type="text" name="q3_tracing_no" maxlength="100" value="{{ $v('q3_tracing_no') }}" />
                            </div>
                            <div>
                                <label class="lbl">(b) Deposition Plan No.</label>
                                <input type="text" name="q3_deposition_plan_no" maxlength="100" value="{{ $v('q3_deposition_plan_no') }}" />
                            </div>
                        </div>
                        <div class="sub branch" data-roa-branch="q3_beaconed" data-roa-when="No">
                            <div>
                                <label class="lbl">If No: (a) It lies on unapproved Town Plan No.</label>
                                <input type="text" name="q3_unapproved_town_plan_no" maxlength="100" value="{{ $v('q3_unapproved_town_plan_no') }}" />
                            </div>
                            <div>
                                <label class="lbl">(b) It lies on Lay-Out No.</label>
                                <input type="text" name="q3_layout_no" maxlength="100" value="{{ $v('q3_layout_no') }}" />
                            </div>
                            <div class="yn-row"><span>(c) Is a separate survey required? *</span>{!! $yn('q3_separate_survey') !!}</div>
                        </div>
                    </div>

                    <div class="q">
                        <div class="q-head"><span>{{ \App\Models\Cadastral\CadastralReport::APPLICATION_QUESTIONS['q4_town_plan'] }}</span>{!! $yn('q4_town_plan') !!}</div>
                        <div class="sub branch" data-roa-branch="q4_town_plan" data-roa-when="Yes">
                            <div>
                                <label class="lbl">If Yes: (a) The Town Plan No. is *</label>
                                <input type="text" name="q4_town_plan_no" maxlength="100" value="{{ $v('q4_town_plan_no') }}" />
                            </div>
                            <div>
                                <label class="lbl">(c) The application is for *</label>
                                <select name="q4_purpose" data-roa-purpose>
                                    <option value="">—</option>
                                    @foreach (\App\Models\Cadastral\CadastralReport::APPLICATION_PURPOSES as $p)
                                        <option value="{{ $p }}" @selected($purposeSelect === $p)>{{ $p }}</option>
                                    @endforeach
                                    <option value="Other" @selected($purposeSelect === 'Other')>Other purpose</option>
                                </select>
                            </div>
                            <div data-roa-purpose-other @if ($purposeSelect !== 'Other') hidden @endif>
                                <label class="lbl">Other purpose</label>
                                <input type="text" name="q4_purpose_other" maxlength="100" value="{{ $purposeOther }}" />
                            </div>
                            <div class="yn-row"><span>(b) Does the shape of the plot agree with the Town Plan? *</span>{!! $yn('q4_shape_agrees') !!}</div>
                            <div class="yn-row"><span>(d) Is the area shown on the Town Plan for the purpose applied? *</span>{!! $yn('q4_area_for_purpose') !!}</div>
                        </div>
                    </div>

                    <div class="q">
                        <div class="q-head"><span>{{ \App\Models\Cadastral\CadastralReport::APPLICATION_QUESTIONS['q5_previous_title'] }}</span>{!! $yn('q5_previous_title') !!}</div>
                        <div class="sub branch" data-roa-branch="q5_previous_title" data-roa-when="Yes">
                            <div style="grid-column:1/-1;">
                                <label class="lbl">If Yes, details *</label>
                                <textarea name="q5_details" rows="2" maxlength="1000">{{ $v('q5_details') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="q">
                        <div class="q-head"><span>{{ \App\Models\Cadastral\CadastralReport::APPLICATION_QUESTIONS['q6_railway'] }}</span>{!! $yn('q6_railway') !!}</div>
                    </div>

                    <div class="q">
                        <div class="q-head"><span>{{ \App\Models\Cadastral\CadastralReport::APPLICATION_QUESTIONS['q7_trunk_road'] }}</span>{!! $yn('q7_trunk_road') !!}</div>
                    </div>

                    <div class="q">
                        <div class="q-head"><span>8. Area applied for</span></div>
                        <div class="sub">
                            <div>
                                <label class="lbl">Hectares</label>
                                <input type="number" step="0.0001" min="0" name="area_applied_ha" value="{{ $areaValue }}" data-roa-ha />
                                <div class="helper-text">
                                    @if ($areaPrefilled)
                                        Pre-filled from {{ $area['source'] }} — saved only when you save the form.
                                    @elseif ($area['source'] === 'entered')
                                        As entered by the Report Officer.
                                    @else
                                        No plan description or chart area is linked to this report yet.
                                    @endif
                                </div>
                            </div>
                            <div>
                                <label class="lbl">Acres (1 hectare = 2.47 acres, as the form prints)</label>
                                <input type="text" disabled data-roa-acres
                                       value="{{ $areaValue !== null && $areaValue !== '' ? number_format((float) $areaValue * \App\Models\Cadastral\CadastralReport::ACRES_PER_HECTARE_AS_PRINTED, 4) : '' }}" />
                            </div>
                        </div>
                    </div>
                </div>

                @if ($editable)
                    <div class="form-actions">
                        <span class="helper-text" style="margin:0 auto 0 0;">
                            Questions 1–7 must all be answered before the Cadastral Report stage can be advanced.
                        </span>
                        @canDo('Cad - Records', 'edit')
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save the Report on Application</button>
                        @endcanDo
                    </div>
                @endif
            </fieldset>
        </form>

        <script>
            (function () {
                var root = document.getElementById('report-on-application');
                if (!root) return;

                function sync() {
                    root.querySelectorAll('[data-roa-branch]').forEach(function (box) {
                        var name = box.getAttribute('data-roa-branch');
                        var picked = root.querySelector('input[name="' + name + '"]:checked');
                        box.hidden = !picked || picked.value !== box.getAttribute('data-roa-when');
                    });
                    var sel = root.querySelector('[data-roa-purpose]');
                    var other = root.querySelector('[data-roa-purpose-other]');
                    if (sel && other) other.hidden = sel.value !== 'Other';
                }

                root.addEventListener('change', sync);
                sync();

                var ha = root.querySelector('[data-roa-ha]');
                var acres = root.querySelector('[data-roa-acres]');
                if (ha && acres) {
                    ha.addEventListener('input', function () {
                        var n = parseFloat(ha.value);
                        acres.value = isNaN(n) ? '' : (n * {{ \App\Models\Cadastral\CadastralReport::ACRES_PER_HECTARE_AS_PRINTED }}).toFixed(4);
                    });
                }
            })();
        </script>
    @endif
</div>
