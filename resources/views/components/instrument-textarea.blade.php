@props([
    'id',
    'label',
    'placeholder' => '',
    'icon' => 'file-text',
    'rows' => 3
])

<div {{ $attributes->merge(['class' => '']) }}>
    <label id="{{ $id }}-label" for="{{ $id }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
    <div class="relative">
        <div class="absolute top-3 left-3 pointer-events-none">
            <i data-lucide="{{ $icon }}" class="h-4 w-4 text-gray-400"></i>
        </div>
        <textarea id="{{ $id }}" name="{{ $id }}" rows="{{ $rows }}"
            {{ $attributes->except('class') }}
            class="w-full pl-10 px-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm"
            placeholder="{{ $placeholder }}"></textarea>
    </div>
</div>
