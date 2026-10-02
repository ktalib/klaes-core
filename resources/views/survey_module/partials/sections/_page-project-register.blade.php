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
                            <div class="type-title">Monetary (Cash for Trees)</div>
                            <div class="type-desc">Payment for economic trees only. Tree Type × Quantity × Unit Price. No land allocation.</div>
                            <span class="type-badge">Cash for Trees</span>
                        </label>

                        <label class="comp-type-option {{ $scheme === 'land' ? 'selected' : '' }}"
                               id="newTypeOptLand" onclick="selectNewProjectType('land')">
                            <input type="radio" name="scheme_type" value="land"
                                   @checked($scheme === 'land') @disabled($locked) />
                            <div class="type-icon"><i class="fas fa-map-marked-alt"></i></div>
                            <div class="type-title">Land-for-Land (50:50)</div>
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
