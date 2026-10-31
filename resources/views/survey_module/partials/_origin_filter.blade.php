{{-- ?origin= filter for listings whose model uses HasImportOrigin. --}}
<select name="origin" title="Where the record came from"
        style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
    <option value="">All Records</option>
    @foreach (\App\Models\Survey\SurveyProject::origins() as $k => $label)
        <option value="{{ $k }}" @selected(request('origin') === $k)>{{ $label }}</option>
    @endforeach
</select>
