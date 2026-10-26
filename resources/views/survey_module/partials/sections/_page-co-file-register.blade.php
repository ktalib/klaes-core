@php
    use App\Models\Survey\SurveyCoFile;

    $editing = $coFile->exists;
    $capture = $coFile->source === SurveyCoFile::SOURCE_CAPTURED;
    $readonlyStyle = 'background:var(--gray-100);color:var(--gray-700);';
@endphp

@include('survey_module.partials._flash')

<form method="POST"
      action="{{ $editing
                  ? route('survey-module.compensation.co-files.update', $coFile)
                  : route('survey-module.compensation.co-files.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    @unless ($editing)
        <input type="hidden" name="source" value="{{ $coFile->source }}" />
    @endunless

    <div class="form-container">
        <div class="form-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>CO File No @if ($capture)<span class="required">*</span>@endif</label>
                    @if ($capture)
                        <input type="text" name="co_file_no" value="{{ old('co_file_no', $coFile->co_file_no) }}"
                               maxlength="50" placeholder="As written on the paper file" required />
                        <p class="helper-text">The existing file's own number, kept exactly as written.</p>
                    @else
                        <input type="text" value="{{ $editing ? $coFile->co_file_no : $nextNo }}" readonly style="{{ $readonlyStyle }}" />
                        <p class="helper-text">
                            {{ $editing ? 'Generated when commissioned; cannot be changed.' : 'Next number in the series; confirmed when you save.' }}
                        </p>
                    @endif
                </div>

                <div class="form-group">
                    <label>Commission Date @unless ($capture)<span class="required">*</span>@endunless</label>
                    <input type="date" name="commission_date"
                           value="{{ old('commission_date', optional($coFile->commission_date)->format('Y-m-d')) }}"
                           @unless ($capture) required @endunless />
                </div>
            </div>

            {{-- Title and Status share a row (user, 2026-10-26) --}}
            <div class="form-grid cols-3">
                <div class="form-group span-2">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $coFile->title) }}" maxlength="255"
                           placeholder="e.g. Tundun Wada Government Acquisition" required />
                </div>

                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status">
                        @foreach (SurveyCoFile::STATUSES as $s)
                            <option value="{{ $s }}" @selected(old('status', $coFile->status) === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group full">
                    <label>Remarks</label>
                    <textarea name="remarks" maxlength="4000" placeholder="Optional notes…">{{ old('remarks', $coFile->remarks) }}</textarea>
                </div>
            </div>

            @include('survey_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => $coFile,
                'legend' => 'Acquisition Location',
            ])

            <div class="form-actions">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ route('survey-module.compensation.co-files') }}">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
                <div class="right">
                    <button class="btn btn-success" type="submit">
                        <i class="fas fa-check"></i>
                        {{ $editing ? 'Save Changes' : ($capture ? 'Capture CO File' : 'Commission CO File') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
