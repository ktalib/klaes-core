@php
    $inputName = $name ?? 'entity_id';
    $inputId = $inputId ?? ('entity-select-' . uniqid());
    $placeholder = $placeholder ?? '-- Select Existing Entity --';
    $entitiesCollection = collect($entities ?? [])->take(20);
    $selectedValue = old($inputName, $selectedEntityId ?? optional($selectedEntityModel ?? null)->id);
    $selectedModel = $selectedEntityModel ?? null;
    $selectedLabel = $selectedEntityLabel ?? optional($selectedModel)->entity_name;
    $selectedType = $selectedEntityType ?? optional($selectedModel)->entity_type;
    $selectClass = isset($selectClass) ? trim($selectClass) : 'mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0';
    $labelText = $labelText ?? ($label ?? null);
    $isRequired = (bool)($required ?? false);
    $helpCopy = $helpText ?? null;

    if ($selectedValue && !$selectedLabel) {
        try {
            $lookupEntity = \App\Models\Entity::find($selectedValue);
            if ($lookupEntity) {
                $selectedModel = $lookupEntity;
                $selectedLabel = $lookupEntity->entity_name;
                $selectedType = $lookupEntity->entity_type;
            }
        } catch (\Throwable $e) {
            // Swallow lookup errors silently to avoid breaking the form render.
        }
    }
@endphp

<div class="entity-select">
    @if(!empty($labelText))
        <label for="{{ $inputId }}" class="block text-sm font-medium text-gray-700">
            {{ $labelText }}
            @if($isRequired)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif
    <select name="{{ $inputName }}" id="{{ $inputId }}" class="{{ $selectClass }}" data-entity-select="true" data-placeholder="{{ $placeholder }}">
        <option value="">{{ $placeholder }}</option>
        @foreach($entitiesCollection as $entityOption)
            <option value="{{ $entityOption->id }}" data-type="{{ $entityOption->entity_type }}" @selected((string) $selectedValue === (string) $entityOption->id)>
                {{ $entityOption->entity_name }} ({{ $entityOption->entity_type }})
            </option>
        @endforeach
        @if($selectedValue && $selectedLabel && !$entitiesCollection->firstWhere('id', $selectedValue))
            <option value="{{ $selectedValue }}" data-type="{{ $selectedType }}" selected>
                {{ $selectedLabel }}@if($selectedType) ({{ $selectedType }})@endif
            </option>
        @endif
    </select>
    @if(!empty($helpCopy))
        <p class="mt-1 text-xs text-gray-500">{{ $helpCopy }}</p>
    @endif
</div>

@once
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            padding-left: 0.75rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
            color: #111827;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
            right: 0.75rem;
        }
        .select2-container--default .select2-selection--single:focus,
        .select2-container--default .select2-selection--single.select2-selection--focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
        }
        .select2-container {
            width: 100% !important;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endonce

<script>
    (function() {
        const selectId = '#{{ $inputId }}';
        if (typeof window.jQuery === 'undefined') {
            console.warn('Select2 requires jQuery to be loaded.');
            return;
        }
        const initEntitySelect = function() {
            const $ = window.jQuery;
            const $select = $(selectId);
            if (!$select.length || $select.hasClass('entity-select--enhanced')) {
                return;
            }
            $select.addClass('entity-select--enhanced');
            const placeholder = $select.data('placeholder') || '{{ $placeholder }}';
            $select.select2({
                width: '100%',
                placeholder: placeholder,
                allowClear: true,
                minimumInputLength: 1,
                ajax: {
                    url: '{{ route('api.entity-customer.search') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { q: params.term };
                    },
                    processResults: function(data) {
                        const results = (data && data.data ? data.data : []).slice(0, 20).map(function(item) {
                            const suffix = item.type ? ' (' + item.type + ')' : '';
                            return {
                                id: item.id,
                                text: item.name + suffix
                            };
                        });
                        return { results: results };
                    },
                    cache: true
                }
            });

            $select.on('select2:open', function() {
                const searchInput = document.querySelector('.select2-container--open .select2-search__field');
                if (searchInput) {
                    searchInput.focus();
                }
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initEntitySelect);
        } else {
            initEntitySelect();
        }
    })();
</script>
