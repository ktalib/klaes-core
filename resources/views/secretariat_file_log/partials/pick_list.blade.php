{{--
    Searchable face of a dropdown. Goes inside a wrapper with
    x-data="fileLogPickList(() => <model>)", next to the hidden select x-ref="sel" it drives.
    $model:       the bound model (for the red required border), e.g. 'manual.from_office'
    $placeholder: JS expression for the closed, empty text
    $required:    red border while empty
--}}
<div class="relative">
    <input type="text" x-ref="psInput" autocomplete="off"
           :value="psOpen ? psQ : psLabel()"
           :placeholder="psOpen ? (psLabel() || 'Type to search…') : ({{ $placeholder }})"
           @focus="psShow()" @click="psShow()" @blur="psClose()"
           @input="psQ = $event.target.value; psOpen = true; psHi = 0"
           @keydown.arrow-down.prevent="psMove(1)" @keydown.arrow-up.prevent="psMove(-1)"
           @keydown.enter.prevent="psEnter()" @keydown.escape="psClose()"
           class="w-full border rounded-lg text-sm pl-3 pr-8 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-200"
           @if (!empty($required))
           :class="{{ $model }} ? 'border-gray-300' : 'border-red-300'"
           @else
           :class="'border-gray-300'"
           @endif
    >
    <button type="button" tabindex="-1" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400"
            @mousedown.prevent="psToggle()">
        <i data-lucide="chevron-down" class="w-4 h-4"></i>
    </button>
</div>
<div x-ref="psList" style="display:none" :style="{ display: psOpen ? 'block' : 'none' }"
     class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
    <template x-for="row in psRows()" :key="row.key">
        <div>
            <div x-show="row.heading" class="px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-gray-400 bg-gray-50" x-text="row.heading"></div>
            <button type="button" x-show="row.item" x-text="row.item ? row.item.text : ''"
                    class="w-full text-left px-3 py-2 text-sm"
                    :data-ps-hi="row.item && row.index === psHi ? 1 : 0"
                    :class="[
                        row.item && row.index === psHi ? 'bg-indigo-50' : '',
                        row.item && row.item.value === String({{ $model }}) ? 'font-semibold' : '',
                        row.item && row.item.other ? 'border-t border-gray-100 text-amber-800' : 'text-gray-800',
                    ].join(' ')"
                    @mouseenter="psHi = row.index" @mousedown.prevent="psPick(row.item)"></button>
        </div>
    </template>
    <div x-show="psQ.trim() && !psFiltered().some(i => !i.other)" class="px-3 py-3 text-sm text-gray-400">No match.</div>
</div>
