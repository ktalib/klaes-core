@php
    use App\Http\Controllers\Survey\GknController;

    $editing   = $gkn->exists;
    $suggested = $suggested ?? '';
@endphp

@include('survey_module.partials._flash')

<form method="POST"
      action="{{ $editing ? route('survey-module.gkn.update', $gkn) : route('survey-module.gkn.register.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="form-container">
        <div class="form-body">
            @if ($editing)
                <div class="comp-type-note" style="margin-bottom:16px;">
                    <i class="fas fa-pen"></i>
                    Editing <strong>{{ $gkn->gkn_number }}</strong>, registered
                    {{ optional($gkn->created_at)->format('Y-m-d') ?: 'previously' }}.
                </div>
            @endif

            <div class="form-grid">
                <div class="form-group">
                    <label>GKN Number</label>
                    <input type="text" name="gkn_number" value="{{ old('gkn_number', $gkn->gkn_number ?: $suggested) }}"
                           placeholder="Auto or manual e.g. {{ $suggested ?: 'GKN-2026-001' }}" maxlength="50" />
                    <p class="helper-text">
                        Leave as suggested to keep the series, or type a legacy number — it must be unique.
                    </p>
                </div>

                <div class="form-group">
                    <label>Title / Description <span class="required">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $gkn->title) }}"
                           placeholder="Parcel name" maxlength="255" required />
                </div>

                <div class="form-group">
                    <label>Area (Ha)</label>
                    <input type="number" step="0.01" min="0" name="area_ha"
                           value="{{ old('area_ha', $gkn->area_ha) }}" placeholder="e.g. 2.40" />
                </div>

                <div class="form-group">
                    <label>Land Use <span class="required">*</span></label>
                    <select name="land_use">
                        @foreach (GknController::LAND_USES as $lu)
                            <option value="{{ $lu }}" @selected(old('land_use', $gkn->land_use ?? 'Residential') === $lu)>{{ $lu }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Survey Officer</label>
                    <input type="text" name="survey_officer" value="{{ old('survey_officer', $gkn->survey_officer) }}"
                           placeholder="Officer who surveyed the parcel" maxlength="255" />
                </div>

                <div class="form-group">
                    <label>Coordinates</label>
                    <input type="text" name="coordinates" value="{{ old('coordinates', $gkn->coordinates) }}"
                           placeholder="Lat, Long" maxlength="255" />
                </div>

                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="record_date"
                           value="{{ old('record_date', optional($gkn->record_date)->format('Y-m-d')) }}" />
                </div>

                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status">
                        @foreach (GknController::STATUSES as $s)
                            <option value="{{ $s }}" @selected(old('status', $gkn->status ?? 'Active') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group full">
                    <label>Remarks</label>
                    <textarea name="remarks" placeholder="Notes, boundaries, references…">{{ old('remarks', $gkn->remarks) }}</textarea>
                </div>
            </div>

            {{-- Parcel location: District, LGA, State — the plot number stays in its own field. --}}
            @include('survey_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => $gkn,
                'legend' => 'Parcel Location',
            ])

            <div class="form-actions">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ route('survey-module.gkn.lands') }}">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
                <div class="right">
                    <button class="btn btn-success" type="submit">
                        <i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Register' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
