{{--
    In-Unit: the Department, fixed to the user's own, then that department's units with Other (specify).
    $state: the form's state, e.g. 'send'
    $key:   prefix for the list keys
    $when:  optional JS condition on top of In-Unit being on
--}}
@php
    $when = $when ?? 'true';
    $field = 'w-full border rounded-lg text-sm px-2 py-2 bg-white';
@endphp
<div x-show="{{ $state }}.in_unit && ({{ $when }})">
    <label class="block text-xs font-semibold text-gray-600 mb-1">Department</label>
    <select x-model="{{ $state }}.to_department" disabled aria-label="Department" class="{{ $field }} border-gray-300 bg-gray-50 text-gray-700">
        <option value="">— Your office has no department —</option>
        <template x-for="d in departments()" :key="'{{ $key }}d-' + d">
            <option :value="d" x-text="d" :selected="d === {{ $state }}.to_department"></option>
        </template>
    </select>
</div>
<div x-show="{{ $state }}.in_unit && ({{ $when }})">
    <label class="block text-xs font-semibold text-gray-600 mb-1">Unit *</label>
    <select x-model="{{ $state }}.unit_id" :disabled="!{{ $state }}.to_department" aria-label="Unit"
            class="{{ $field }} disabled:bg-gray-50 disabled:text-gray-400" :class="{{ $state }}.unit_id ? 'border-gray-300' : 'border-red-300'">
        <option value="">— Select unit —</option>
        <template x-for="u in unitsInDept({{ $state }}.to_department)" :key="'{{ $key }}u-' + u.id">
            <option :value="String(u.id)" x-text="u.name" :selected="String(u.id) === String({{ $state }}.unit_id)"></option>
        </template>
        <option value="other" :selected="{{ $state }}.unit_id === 'other'">Other (specify)</option>
    </select>
    <input type="text" x-show="{{ $state }}.unit_id === 'other'" x-model="{{ $state }}.unit_other" maxlength="150" placeholder="Specify the unit *"
           class="mt-1 {{ $field }}" :class="String({{ $state }}.unit_other || '').trim() ? 'border-gray-300' : 'border-red-300'">
</div>
