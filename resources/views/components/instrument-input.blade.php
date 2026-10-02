@props([
    'id', 
    'label', 
    'type' => 'text', 
    'placeholder' => '', 
    'icon' => 'file-text',
    'value' => '',
    // Opt in to the shared Nigerian phone validator (public/js/nigerian-phone.js).
    // NOT keyed off icon="phone": some of those fields are labelled "Phone/Email"
    // and legitimately hold an address.
    'phone' => false,
    'phoneRequired' => false,
])

<div {{ $attributes->merge(['class' => '']) }}>
    <label id="{{ $id }}-label" for="{{ $id }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i data-lucide="{{ $icon }}" class="h-4 w-4 text-gray-400"></i>
        </div>
        <input id="{{ $id }}" name="{{ $id }}" type="{{ $type }}"
            class="w-full pl-10 px-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm uppercase"
            @if($phone) data-phone-ng @endif
            @if($phoneRequired) data-phone-ng-required required @endif
            @unless($phone) oninput="this.value = this.value.toUpperCase()" @endunless
            placeholder="{{ $placeholder }}" value="{{ $value }}">
    </div>
</div>
