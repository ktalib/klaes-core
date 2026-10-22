{{-- On-the-fly bill modal (2026-10-21): Revenue Code (one per bill type) → Bill Type, the file
     (global selector, details backfilled) and the items (item, quantity, rate). A unit-rate type
     (Ground Rent) takes the plot size as the quantity.
     Expects $otfTypes (concrete types), $otfTypeCodes (type code => its Revenue Code),
     $otfCycles (type code => cycle label), $otfAllTypes (every type, for parent names) and
     optional $otfFixedType (the type page it is on). Opened by the window event "rev-on-the-fly". --}}
@php
  $otfFixedType = $otfFixedType ?? null;
@endphp
<div data-column-picker="off"
     x-data="revOnTheFly(@js([
        'fileUrl' => route('revenue.bills.on-the-fly.file'),
        'storeUrl' => route('revenue.bills.on-the-fly.store'),
        'codes' => $otfTypeCodes,
        'cycles' => $otfCycles,
        'unitRate' => config('billing.unit_rate_types', []),
        'fixedType' => $otfFixedType,
     ]))"
     @rev-on-the-fly.window="start($event.detail && $event.detail.type, $event.detail && $event.detail.file)">
  <div x-show="open" x-cloak class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="open = false">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden">
      <div class="px-6 py-4 bg-gradient-to-r from-emerald-600 to-emerald-700 text-white flex items-center justify-between">
        <div class="flex items-center gap-2 font-semibold"><i data-lucide="zap" class="w-5 h-5"></i> <span x-text="fixedType ? 'Generate bill' : 'On-the-fly bill'">On-the-fly bill</span></div>
        <button type="button" @click="open = false" class="p-1 rounded hover:bg-white/20" aria-label="Close"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <div class="px-6 py-4 overflow-y-auto flex-1 space-y-4 text-sm">
        {{-- Revenue Code → Bill Type --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Revenue Code</label>
            <div class="flex gap-2">
              <input type="text" x-model="codeEntry" @keydown.enter.prevent="findCode()" @input="if (codeEntry.replace(/\D/g, '').length >= 7) findCode()"
                     :readonly="!!fixedType" inputmode="numeric" maxlength="20" placeholder="Enter the bill type's Revenue Code, e.g. 9170000"
                     class="flex-1 border border-gray-300 rounded-lg px-3 py-2 font-mono bg-white read-only:bg-gray-50">
              <button type="button" @click="findCode()" x-show="!fixedType" class="px-3 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 inline-flex items-center gap-1">
                <i data-lucide="search" class="w-4 h-4"></i> Find
              </button>
            </div>
            <div class="text-xs mt-1" :class="codeOk ? 'text-emerald-700' : 'text-red-600'" x-show="codeMsg" x-text="codeMsg"></div>
          </div>
          <label><span class="block text-xs font-semibold text-gray-600 mb-1">Bill Type <span class="text-red-500">*</span></span>
            <select x-model="type" @change="typeChanged()" :disabled="!!fixedType" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white disabled:bg-gray-50">
              <option value="">Choose the bill type, or enter its Revenue Code</option>
              @foreach ($otfTypes as $t)
                @php $otfCode = $otfTypeCodes[$t->code] ?? null; @endphp
                <option value="{{ $t->code }}">{{ $otfCode ? $otfCode . ' · ' : '' }}{{ $t->parent_code ? optional($otfAllTypes->firstWhere('code', $t->parent_code))->name . ' — ' : '' }}{{ $t->name }}</option>
              @endforeach
            </select>
            <span class="block text-xs text-sky-700 mt-1" x-show="cycleLabel()" x-cloak>
              Recurring: <b x-text="cycleLabel()"></b> payment cycle. The next payment falls due when this period ends.
            </span>
          </label>
        </div>

        {{-- File --}}
        <div><span class="block text-xs font-semibold text-gray-600 mb-1">File No. <span class="text-red-500">*</span></span>
          <div class="flex gap-2 md:w-1/2">
            <input type="text" readonly :value="file ? file.file_number : ''" placeholder="Select with the file number selector"
                   class="flex-1 border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 font-mono">
            <button type="button" @click="pickFile()" class="px-3 py-2 rounded-lg border border-gray-300 font-semibold hover:bg-gray-50 inline-flex items-center gap-1">
              <i data-lucide="search" class="w-4 h-4"></i> <span x-text="file ? 'Change' : 'Select'">Select</span>
            </button>
          </div>
          <span class="block text-xs text-gray-500 mt-1" x-show="loadingFile">Loading the file…</span>
        </div>

        {{-- File details (backfilled), in the standard order --}}
        <template x-if="file">
          <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 grid grid-cols-2 md:grid-cols-5 gap-3">
            <div><div class="text-xs uppercase text-gray-500">Plot No.</div><div x-text="file.plot_no || '—'"></div></div>
            <div><div class="text-xs uppercase text-gray-500">District</div><div x-text="file.district || '—'"></div></div>
            <div><div class="text-xs uppercase text-gray-500">LGA</div><div x-text="file.lga || '—'"></div></div>
            <div><div class="text-xs uppercase text-gray-500">Location</div><div x-text="file.location || '—'"></div></div>
            <div><div class="text-xs uppercase text-gray-500">Plot size</div><div x-text="file.plot_size || '—'"></div></div>
            <div class="col-span-2 md:col-span-5 text-xs text-amber-700" x-show="!file.indexed">This file is not indexed; enter the payer's details below.</div>
          </div>
        </template>

        {{-- Payer --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3" x-show="file" x-cloak>
          <label><span class="block text-xs font-semibold text-gray-600 mb-1">Payer name</span>
            <input type="text" x-model="payer.name" maxlength="255" class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
          <label><span class="block text-xs font-semibold text-gray-600 mb-1">Phone (SMS notices go here)</span>
            <input type="tel" x-model="payer.phone" maxlength="30" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            <span class="block text-xs text-gray-500 mt-1" x-show="phoneSource" x-text="'Found in ' + phoneSource + '. Check it with the payer.'"></span></label>
          <label><span class="block text-xs font-semibold text-gray-600 mb-1">Email (needed for Paystack)</span>
            <input type="email" x-model="payer.email" maxlength="150" class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
          <label><span class="block text-xs font-semibold text-gray-600 mb-1">Address</span>
            <input type="text" x-model="payer.address" maxlength="500" class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
          <label class="md:col-span-2"><span class="block text-xs font-semibold text-gray-600 mb-1">Description (shown on the bill)</span>
            <input type="text" x-model="description" maxlength="500" placeholder="Defaults to the bill type" class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
          <label><span class="block text-xs font-semibold text-gray-600 mb-1">Due date</span>
            <input type="date" x-model="dueDate" class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
          <label x-show="cycleLabel()" x-cloak><span class="block text-xs font-semibold text-gray-600 mb-1">Period starts (default today)</span>
            <input type="date" x-model="periodStart" class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
        </div>

        {{-- Items --}}
        <div x-show="type" x-cloak>
          <div class="flex items-center justify-between mb-2">
            <div class="font-semibold flex items-center gap-2"><i data-lucide="list" class="w-4 h-4 text-emerald-600"></i> Items</div>
            <button type="button" @click="addLine()" class="text-emerald-700 font-semibold inline-flex items-center gap-1"><i data-lucide="plus" class="w-4 h-4"></i> Add item</button>
          </div>
          <div class="rounded-lg border border-gray-200 overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr><th class="px-2 py-2">S/N</th><th class="px-2 py-2">Item</th><th class="px-2 py-2 w-28" x-text="isUnitRate() ? 'Plot size (m²)' : 'Qty'">Qty</th><th class="px-2 py-2 w-32" x-text="isUnitRate() ? 'Rate per m² (₦)' : 'Rate (₦)'">Rate (₦)</th><th class="px-2 py-2 text-right">Amount</th><th class="px-2 py-2"></th></tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <template x-for="(line, i) in lines" :key="i">
                  <tr class="align-top">
                    <td class="px-2 py-2 text-gray-500" x-text="i + 1"></td>
                    <td class="px-2 py-2 min-w-[18rem]"><input type="text" x-model="line.name" maxlength="255" placeholder="e.g. Annual ground rent (residential)" class="w-full border border-gray-300 rounded-lg px-2 py-1.5"></td>
                    <td class="px-2 py-2"><input type="number" x-model="line.quantity" min="0.0001" step="any" class="w-full border border-gray-300 rounded-lg px-2 py-1.5"></td>
                    <td class="px-2 py-2"><input type="number" x-model="line.rate" min="0" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5"></td>
                    <td class="px-2 py-2 text-right whitespace-nowrap" x-text="money(lineAmount(line))"></td>
                    <td class="px-2 py-2 text-right"><button type="button" @click="removeLine(i)" class="p-1 text-gray-400 hover:text-red-600" aria-label="Remove item"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
                  </tr>
                </template>
              </tbody>
              <tfoot class="bg-emerald-50"><tr><td colspan="4" class="px-2 py-2 font-semibold">Total</td><td class="px-2 py-2 text-right font-bold" x-text="money(total())"></td><td></td></tr></tfoot>
            </table>
          </div>
          <div class="text-xs text-gray-500 mt-1" x-show="isUnitRate()">Ground rent is a unit rate: rate per m² × the plot size, filled from the file.</div>
        </div>
      </div>
      <div class="px-6 py-3 border-t border-gray-100 flex justify-end gap-2">
        <button type="button" @click="open = false" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold">Cancel</button>
        <button type="button" @click="submit()" :disabled="busy || !type" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 disabled:opacity-50">
          <i data-lucide="file-check" class="w-4 h-4"></i> <span x-text="busy ? 'Generating…' : 'Generate bill'">Generate bill</span>
        </button>
      </div>
    </div>
  </div>
</div>
