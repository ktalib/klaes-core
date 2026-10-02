{{--
    Location Builder — Plot No + District (searchable, with "Other → specify") [+ LGA]
    compose into a single Full Location string. Reused by the OP and ToT cards.

    Usage: @include('lands_one_stop_shop.partials.location-builder', ['prefix' => 'cop', 'withLga' => true])
    Field ids: {prefix}Plot, {prefix}District, {prefix}DistrictOther, {prefix}Location [, {prefix}Lga]
    JS wiring: initLocationBuilder(prefix, modalEl) / composeLocation / resetLocationBuilder.

    withLga (default false) renders the LGA select inside the builder, since LGA is part of
    the composed address. Cards that own their LGA field elsewhere (the ToT card's #utLga)
    leave it off — composeLocation still picks that field up via opLgaFieldId(prefix).
--}}
@php $p = $prefix; $withLga = $withLga ?? false; @endphp
<div class="md:col-span-2 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
    <div class="text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-2">Location Builder</div>
    <div class="grid grid-cols-1 {{ $withLga ? 'md:grid-cols-3' : 'md:grid-cols-2' }} gap-x-4 gap-y-3">
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Plot No</label>
            <input type="text" id="{{ $p }}Plot" placeholder="e.g. 486A"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
        </div>
        {{-- Street Name removed for now (kept out of the compose logic too). --}}
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">District</label>
            <select id="{{ $p }}District" class="w-full text-sm"></select>
        </div>
        @if ($withLga)
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    LGA <span class="cop-batch-req hidden text-rose-500">*</span>
                </label>
                <select id="{{ $p }}Lga" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">Select LGA</option>
                </select>
            </div>
        @endif
        <div id="{{ $p }}OtherWrap" class="hidden md:col-span-3">
            <label class="block text-xs font-semibold text-slate-700 mb-1">Specify District</label>
            <input type="text" id="{{ $p }}DistrictOther" placeholder="Type the district name"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
        </div>
    </div>
    <div class="mt-3">
        <label class="block text-xs font-semibold text-slate-700 mb-1">
            Full Location <span class="text-slate-400 font-normal">— built from the fields above; you can also edit it directly</span>
        </label>
        <input type="text" id="{{ $p }}Location" placeholder="Composed location"
               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
    </div>
</div>
