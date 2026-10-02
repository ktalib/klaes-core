{{--
  One Cadastral setting as a form field, used by partials/cadastral.
  Expects $field: a CadastralSettings::DEFINITIONS row plus key, name, value,
  text, source, updated_by, updated_at (ConfigurableEntriesController::cadastralData).
  Every input is settings[name], where name is the key with dots as double underscores.
--}}
@php
    $inputName = 'settings[' . $field['name'] . ']';
    $savedBy = $field['updated_by'] ? ($cadUserNames[$field['updated_by']] ?? 'User #' . $field['updated_by']) : null;
    $savedAt = $field['updated_at'] ? \Illuminate\Support\Carbon::parse($field['updated_at'])->format('d M Y, H:i') : null;
    $sourceNote = $field['source'] === 'database'
        ? 'Saved' . ($savedBy ? ' by ' . $savedBy : '') . ($savedAt ? ' · ' . $savedAt : '')
        : 'Shipped default (config/cadastral_module.php)';
@endphp
<div class="iw-field">
    @if($field['type'] === 'bool')
        <label class="flex items-center gap-3 text-sm" style="min-height:38px">
            <input type="hidden" name="{{ $inputName }}" value="0">
            <span class="ce-switch"><input type="checkbox" name="{{ $inputName }}" value="1" @checked($field['value'])><span></span></span>
            <span class="text-gray-800">{{ $field['label'] }}</span>
        </label>
    @else
        <label class="iw-label">{{ $field['label'] }}</label>
        @if($field['type'] === 'money')
            <input type="number" step="0.01" min="0" name="{{ $inputName }}" value="{{ number_format((float) $field['value'], 2, '.', '') }}" required class="iw-input">
        @elseif($field['type'] === 'decimal')
            <input type="number" step="0.01" min="0.01" name="{{ $inputName }}" value="{{ (float) $field['value'] }}" required class="iw-input">
        @elseif($field['type'] === 'int')
            <input type="number" step="1" min="1" max="12" name="{{ $inputName }}" value="{{ (int) $field['value'] }}" required class="iw-input">
        @elseif($field['type'] === 'choice')
            <select name="{{ $inputName }}" class="ce-select" required>
                @foreach($field['options'] ?? [] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $field['value'] === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @elseif($field['type'] === 'list')
            <textarea name="{{ $inputName }}" rows="2" class="iw-textarea iw-input iw-mono" placeholder="Comma-separated">{{ $field['text'] }}</textarea>
        @else
            <input type="text" name="{{ $inputName }}" value="{{ $field['value'] }}" required maxlength="100" class="iw-input iw-mono">
        @endif
    @endif
    <div class="ce-muted" style="margin-top:3px">{{ $sourceNote }}</div>
</div>
