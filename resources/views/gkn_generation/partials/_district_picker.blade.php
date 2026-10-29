{{-- Searchable District picker (user, 2026-10-28). $ctx is the Alpine object holding
     district_id / district_other: "current" (generate modal) or "formData" (edit modal).
     "Other" is the one districts row of that name; it reveals a required "specify" box.
     The list is position:fixed so the modal's scroll container cannot clip it. --}}
<div x-data="{ open: false, q: '', top: 0, left: 0, width: 0, up: false }"
     x-init="document.addEventListener('scroll', e => { if (open && !$el.contains(e.target)) open = false }, true)"
     @keydown.escape.stop="open = false" @click.outside="open = false">
    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase text-left">District</label>
    <button type="button" x-ref="trigger" :disabled="viewMode"
            @click="const r = $refs.trigger.getBoundingClientRect();
                    up = window.innerHeight - r.bottom < 300 && r.top > 300;
                    top = up ? r.top - 4 : r.bottom + 4; left = r.left; width = r.width;
                    open = !open; q = '';
                    if (open) $nextTick(() => $refs.search.focus())"
            class="w-full flex items-center justify-between gap-2 bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 text-left focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-500">
        <span class="truncate" :class="{{ $ctx }}.district_id ? '' : 'text-slate-400'"
              x-text="{{ $ctx }}.district_id ? (isOtherDistrict({{ $ctx }}.district_id) ? 'Other' : (districts.find(i => i.id == {{ $ctx }}.district_id) || {}).name) : 'Select District'"></span>
        <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 flex-shrink-0"></i>
    </button>

    <div x-show="open" x-cloak
         :style="'position:fixed; left:' + left + 'px; width:' + Math.max(width, 240) + 'px; ' + (up ? 'bottom:' + (window.innerHeight - top) + 'px;' : 'top:' + top + 'px;')"
         class="z-[9999] bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden">
        <div class="p-2 border-b border-slate-100">
            <input type="text" x-ref="search" x-model="q" placeholder="Search district…" aria-label="Search district"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
        </div>
        <ul class="max-h-60 overflow-y-auto py-1 text-sm" role="listbox">
            <template x-for="d in searchDistricts(q).list" :key="d.id">
                <li role="option" @click="pickDistrict({{ $ctx }}, d); open = false"
                    :class="d.id == {{ $ctx }}.district_id ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-700'"
                    class="px-3 py-2 cursor-pointer hover:bg-slate-100" x-text="d.name"></li>
            </template>
            <li x-show="searchDistricts(q).list.length === 0" class="px-3 py-2 text-slate-400 italic">No district matches.</li>
            <li x-show="searchDistricts(q).more > 0" class="px-3 py-1.5 text-[11px] text-slate-400"
                x-text="'+ ' + searchDistricts(q).more + ' more, type to narrow'"></li>
            <template x-for="d in searchDistricts(q).other" :key="'o' + d.id">
                <li role="option" @click="pickDistrict({{ $ctx }}, d); open = false; $nextTick(() => $refs.other && $refs.other.focus())"
                    :class="d.id == {{ $ctx }}.district_id ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-700'"
                    class="px-3 py-2 cursor-pointer hover:bg-slate-100 border-t border-slate-100">Other (specify)</li>
            </template>
        </ul>
    </div>

    <input type="text" x-ref="other" x-show="isOtherDistrict({{ $ctx }}.district_id)" x-cloak
           x-model="{{ $ctx }}.district_other" :disabled="viewMode" :required="isOtherDistrict({{ $ctx }}.district_id)"
           @input="if (batchMode && applyLocationToAll) syncToAll('district_other')"
           maxlength="255" placeholder="Specify district *" aria-label="Specify district"
           class="mt-2 w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 shadow-sm disabled:bg-slate-50">
</div>
