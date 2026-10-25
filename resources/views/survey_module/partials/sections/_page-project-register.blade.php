@php
    $editing = $project->exists;
    $locked  = $editing && $project->cases()->exists();
    $scheme  = old('scheme_type', $project->scheme_type ?? 'monetary');
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
            <div class="form-grid">
                @if ($editing)
                    <div class="form-group">
                        <label>Project ID</label>
                        <input type="text" value="{{ $project->project_code }}" readonly
                               style="background:var(--gray-100);color:var(--gray-700);" />
                        <p class="helper-text">Generated automatically; cannot be changed.</p>
                    </div>
                @endif

                <div class="form-group">
                    <label>CO File <span class="required">*</span></label>
                    <select name="survey_co_file_id" required>
                        <option value="">— Choose a CO file —</option>
                        @foreach ($coFiles as $f)
                            <option value="{{ $f->id }}" @selected((string) old('survey_co_file_id', $project->survey_co_file_id) === (string) $f->id)>
                                {{ $f->co_file_no }} · {{ $f->title }}
                            </option>
                        @endforeach
                    </select>
                    <p class="helper-text">
                        A CO file holds many projects.
                        <a href="{{ route('survey-module.compensation.co-files.create') }}">Commission a CO file</a> first if it is not listed.
                    </p>
                </div>

                <div class="form-group">
                    <label>Project Name <span class="required">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $project->name) }}"
                           placeholder="e.g. Kano Housing Corridor Phase 2" required />
                </div>

                <div class="form-group">
                    <label>Purpose <span class="required">*</span></label>
                    <select name="purpose">
                        <option value="">Select purpose…</option>
                        @foreach (['Infrastructure Development','Housing','Commercial','Agricultural','Government Acquisition','Urban Renewal'] as $p)
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
                        'id' => $a->id, 'surveyor_id' => $a->surveyor_id,
                        'portions' => $a->portions, 'portion_size_ha' => (float) $a->portion_size_ha,
                      ])->all()
                    : []);
            @endphp
            <div class="form-grid">
                <div class="form-group full">
                    <label>Surveyors &amp; Portion Allocation</label>
                    <p class="helper-text" style="margin-bottom:8px;">
                        Pick each surveyor from the <a href="{{ route('survey-module.records.surveyors.index') }}">List of Surveyors</a>,
                        then enter how many portions they will survey and the size of each portion.
                        A surveyor's case form offers exactly that many portions.
                    </p>
                    <div class="table-wrapper" style="margin:0;">
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:50px;">S/N</th>
                                        <th>Surveyor</th>
                                        <th style="width:140px;">No. of Portions</th>
                                        <th style="width:170px;">Size per Portion (Ha)</th>
                                        <th style="width:140px;">Subtotal (Ha)</th>
                                        <th style="width:60px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="projSurveyorRows">
                                    @foreach ($svRows as $i => $row)
                                        @php $minPortions = max(1, $usedMax[$row['id'] ?? 0] ?? 0); @endphp
                                        <tr data-row>
                                            <td data-sn>{{ $loop->iteration }}</td>
                                            <td>
                                                <input type="hidden" name="surveyors[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}" />
                                                <select name="surveyors[{{ $i }}][surveyor_id]" required>
                                                    <option value="">— Select surveyor —</option>
                                                    @foreach ($surveyors as $sv)
                                                        <option value="{{ $sv->id }}" @selected((string) ($row['surveyor_id'] ?? '') === (string) $sv->id)>
                                                            {{ $sv->full_name }}{{ $sv->rank ? ' · ' . $sv->rank : '' }}{{ $sv->is_active ? '' : ' (inactive)' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="surveyors[{{ $i }}][portions]" min="{{ $minPortions }}" step="1"
                                                       value="{{ $row['portions'] ?? '' }}" required oninput="projSurveyors.total()"
                                                       @if ($minPortions > 1) title="Portions 1–{{ $minPortions }} already have cases" @endif />
                                            </td>
                                            <td>
                                                <input type="number" name="surveyors[{{ $i }}][portion_size_ha]" min="0.0001" step="0.0001"
                                                       value="{{ $row['portion_size_ha'] ?? '' }}" required oninput="projSurveyors.total()" />
                                            </td>
                                            <td data-subtotal>—</td>
                                            <td>
                                                @if (! empty($row['id']) && ($usedMax[$row['id']] ?? 0) > 0)
                                                    <span title="Has cases; cannot be removed" style="color:var(--gray-400);"><i class="fas fa-lock"></i></span>
                                                @else
                                                    <button type="button" class="btn btn-outline btn-xs" title="Remove" aria-label="Remove surveyor"
                                                            onclick="projSurveyors.remove(this)"><i class="fas fa-times"></i></button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr id="projSurveyorEmpty" @style(['display:none' => count($svRows) > 0])>
                                        <td colspan="6" style="text-align:center;color:var(--gray-500);padding:16px;">
                                            No surveyors assigned yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer" style="flex-wrap:wrap;gap:8px;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="projSurveyors.add()">
                                <i class="fas fa-plus"></i> Add Surveyor
                            </button>
                            <span id="projSurveyorTotal"></span>
                        </div>
                    </div>
                </div>
            </div>

            <template id="projSurveyorTpl">
                <tr data-row>
                    <td data-sn></td>
                    <td>
                        <input type="hidden" name="surveyors[__i__][id]" value="" />
                        <select name="surveyors[__i__][surveyor_id]" required>
                            <option value="">— Select surveyor —</option>
                            @foreach ($surveyors->where('is_active', true) as $sv)
                                <option value="{{ $sv->id }}">{{ $sv->full_name }}{{ $sv->rank ? ' · ' . $sv->rank : '' }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="number" name="surveyors[__i__][portions]" min="1" step="1" required oninput="projSurveyors.total()" /></td>
                    <td><input type="number" name="surveyors[__i__][portion_size_ha]" min="0.0001" step="0.0001" required oninput="projSurveyors.total()" /></td>
                    <td data-subtotal>—</td>
                    <td>
                        <button type="button" class="btn btn-outline btn-xs" title="Remove" aria-label="Remove surveyor"
                                onclick="projSurveyors.remove(this)"><i class="fas fa-times"></i></button>
                    </td>
                </tr>
            </template>

            <div class="form-grid">
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
                            <div class="type-title">Cash for Trees</div>
                            <div class="type-desc">Payment for economic trees only. Tree Type × Quantity × Unit Price. No land allocation.</div>
                            <span class="type-badge">Cash for Trees</span>
                        </label>

                        <label class="comp-type-option {{ $scheme === 'land' ? 'selected' : '' }}"
                               id="newTypeOptLand" onclick="selectNewProjectType('land')">
                            <input type="radio" name="scheme_type" value="land"
                                   @checked($scheme === 'land') @disabled($locked) />
                            <div class="type-icon"><i class="fas fa-map-marked-alt"></i></div>
                            <div class="type-title">Plot for Plot (50:50)</div>
                            <div class="type-desc">Physical plot allocation. Total plots split 50:50 — half to farmers, half to government. No cash component.</div>
                            <span class="type-badge">Plots Only</span>
                        </label>
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
            out.innerHTML = 'Allocated: <strong>' + fmt(sum) + ' Ha</strong>' + (acq ? ' of ' + fmt(acq) + ' Ha acquired' : '') +
                (over ? ' <span style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> exceeds the size of acquisition</span>' : '');
        }

        function add() {
            var html = document.getElementById('projSurveyorTpl').innerHTML.replace(/__i__/g, 'n' + (next++));
            body.insertBefore(document.createRange().createContextualFragment(html), document.getElementById('projSurveyorEmpty'));
            total();
        }

        function remove(btn) {
            btn.closest('tr').remove();
            total();
        }

        document.addEventListener('DOMContentLoaded', total);
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
    }
</script>
