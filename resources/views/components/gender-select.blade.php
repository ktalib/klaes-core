{{--
    The single gender dropdown for every capture form.

    Options come from the `genders` lookup table (App\Models\Gender::options(),
    cached, seeded by GenderSeeder) instead of being hard-coded per page — add or
    retire a value there and every form follows.

    THE SUBMITTED VALUE IS THE NAME, NOT THE ID. file_indexings.gender,
    mls_file_no.gender and st_file_numbers.gender are varchars validated against
    App\Services\GenderNormalizer::CANON, so an id would break every existing row,
    the row tint in components/gender-legend.blade.php, and the gender reports.

    Props
      name       — submitted field name (default "gender"). Skipped when the caller
                   binds it with Alpine (`:name="'gender[' + i + ']'"`).
      id         — element id (defaults to `name`). Skipped when bound with `:id`.
      selected   — current value; matched case-insensitively through
                   GenderNormalizer, so legacy lowercase "male" still selects Male.
      placeholder / includePlaceholder — the leading empty option.
      options    — override the list (array of names). Rarely needed.

    Alpine callers keep using x-model as before; it is forwarded with the rest of
    the attribute bag.

    Usage:
      <x-gender-select class="input" required />
      <x-gender-select name="gender" x-model="gender" class="..." required />
      <x-gender-select :selected="$record->gender" class="..." />
      <x-gender-select :name="null" ::name="'gender[' + index + ']'" ... />
--}}
@props([
    'name' => 'gender',
    'id' => null,
    'selected' => null,
    'placeholder' => 'Select Gender',
    'includePlaceholder' => true,
    'options' => null,
])

@php
    $genderOptions = $options ?? \App\Models\Gender::options();

    // Alpine-bound name/id win over the static props, so the component never emits
    // a duplicate attribute on the per-file selects that build their own.
    $hasBoundName = $attributes->has(':name') || $attributes->has('x-bind:name');
    $hasBoundId = $attributes->has(':id') || $attributes->has('x-bind:id');

    $normalizer = app(\App\Services\GenderNormalizer::class);
    $currentGender = $normalizer->normalize($selected);

    $elementId = $id ?? $name;
@endphp

<select
    @if ($name !== null && !$hasBoundName) name="{{ $name }}" @endif
    @if ($elementId !== null && !$hasBoundId) id="{{ $elementId }}" @endif
    {{ $attributes }}>
    @if ($includePlaceholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($genderOptions as $gender)
        <option value="{{ $gender }}" @selected($currentGender === $gender)>{{ $gender }}</option>
    @endforeach
</select>
