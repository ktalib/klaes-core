@php
    $currentYear = (int) date('Y');
    $years = range($currentYear, 1980);
    // "To (Year)" may run ahead of today (prepaid / forward-dated periods) up to 2040.
    $toYears = range(max(2040, $currentYear), 1980);

    // Seed the repeaters. On edit, the file_indexings single columns hold the
    // primary (first) bill of each type; the rest live in file_indexing_bills.
    // If the controller passes $billBalances / $grantRents collections, use them.
    $initialBills = [];
    if (!empty($billBalances)) {
        foreach ($billBalances as $b) {
            $initialBills[] = [
                'total_amount' => $b->amount ?? '',
                'from_year'    => (int) ($b->from_year ?? $currentYear),
                'to_year'      => (int) ($b->to_year ?? $currentYear),
                'receipt_no'   => $b->receipt_no ?? '',
                'receipt_date' => $b->receipt_date ? \Illuminate\Support\Carbon::parse($b->receipt_date)->format('Y-m-d') : '',
            ];
        }
    } elseif (isset($record) && ($record->bill_total_amount !== null || $record->bill_receipt_no)) {
        $initialBills[] = [
            'total_amount' => $record->bill_total_amount ?? '',
            'from_year'    => (int) ($record->bill_from_year ?? $currentYear),
            'to_year'      => (int) ($record->bill_to_year ?? $currentYear),
            'receipt_no'   => $record->bill_receipt_no ?? '',
            'receipt_date' => $record->bill_receipt_date ? \Illuminate\Support\Carbon::parse($record->bill_receipt_date)->format('Y-m-d') : '',
        ];
    }
    if (empty($initialBills)) {
        $initialBills[] = ['total_amount' => '', 'from_year' => $currentYear, 'to_year' => $currentYear, 'receipt_no' => '', 'receipt_date' => ''];
    }

    $initialRents = [];
    if (!empty($grantRents)) {
        foreach ($grantRents as $r) {
            $initialRents[] = [
                'amount'       => $r->amount ?? '',
                'from_year'    => (int) ($r->from_year ?? $currentYear),
                'to_year'      => (int) ($r->to_year ?? $currentYear),
                'receipt_no'   => $r->receipt_no ?? '',
                'receipt_date' => $r->receipt_date ? \Illuminate\Support\Carbon::parse($r->receipt_date)->format('Y-m-d') : '',
            ];
        }
    } elseif (isset($record) && ($record->ground_rent_amount !== null || $record->ground_rent_receipt_no)) {
        $initialRents[] = [
            'amount'       => $record->ground_rent_amount ?? '',
            'from_year'    => (int) ($record->ground_rent_from_year ?? $currentYear),
            'to_year'      => (int) ($record->ground_rent_to_year ?? $currentYear),
            'receipt_no'   => $record->ground_rent_receipt_no ?? '',
            'receipt_date' => $record->ground_rent_receipt_date ? \Illuminate\Support\Carbon::parse($record->ground_rent_receipt_date)->format('Y-m-d') : '',
        ];
    }
    if (empty($initialRents)) {
        $initialRents[] = ['amount' => '', 'from_year' => $currentYear, 'to_year' => $currentYear, 'receipt_no' => '', 'receipt_date' => ''];
    }
@endphp
<div class="form-section" id="billing-ground-rent-section"
    x-data="{
        open: true,
        tab: 'bill',
        years: @js($years),
        toYears: @js($toYears),
        currentYear: {{ $currentYear }},
        bills: @js($initialBills),
        rents: @js($initialRents),
        addBill() {
            this.bills.push({ total_amount: '', from_year: this.currentYear, to_year: this.currentYear, receipt_no: '', receipt_date: '' });
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },
        removeBill(i) { this.bills.splice(i, 1); if (!this.bills.length) this.addBill(); },
        addRent() {
            this.rents.push({ amount: '', from_year: this.currentYear, to_year: this.currentYear, receipt_no: '', receipt_date: '' });
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },
        removeRent(i) { this.rents.splice(i, 1); if (!this.rents.length) this.addRent(); }
    }">
    <button type="button" @click="open = !open"
        class="flex w-full items-center justify-between gap-2 mb-4 text-left">
        <div class="flex items-center gap-2">
            <i data-lucide="receipt" class="h-5 w-5 text-emerald-600"></i>
            <h3 class="form-section-title" style="margin-bottom: 0;">Bill Balance &amp; Ground Rent</h3>
        </div>
        <i data-lucide="chevron-down" class="h-5 w-5 text-gray-500 transition-transform"
            :class="{ '-rotate-180': open }"></i>
    </button>

    <div x-show="open" x-collapse>
        <hr class="mb-6 border-t border-gray-200">

        {{-- Tabs --}}
        <div class="flex gap-1 border-b border-gray-200 mb-6">
            <button type="button" @click="tab = 'bill'"
                class="inline-flex items-center gap-2 px-4 py-2 -mb-px text-sm font-medium border-b-2 transition"
                :class="tab === 'bill' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700'">
                <i data-lucide="wallet" class="h-4 w-4"></i>
                Bill Balance
                <span class="ml-1 rounded-full bg-amber-100 text-amber-700 text-xs px-2 py-0.5" x-text="bills.length"></span>
            </button>
            <button type="button" @click="tab = 'rent'"
                class="inline-flex items-center gap-2 px-4 py-2 -mb-px text-sm font-medium border-b-2 transition"
                :class="tab === 'rent' ? 'border-emerald-500 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700'">
                <i data-lucide="banknote" class="h-4 w-4"></i>
                Ground Rent
                <span class="ml-1 rounded-full bg-emerald-100 text-emerald-700 text-xs px-2 py-0.5" x-text="rents.length"></span>
            </button>
        </div>

        {{-- Bill Balance tab --}}
        <div x-show="tab === 'bill'">
            <template x-for="(row, i) in bills" :key="i">
                <div class="relative rounded-lg border border-amber-200 bg-amber-50/40 p-5 mb-4">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-semibold uppercase tracking-wide text-amber-700">
                            Bill Balance <span x-text="i + 1"></span>
                        </span>
                        <button type="button" x-show="bills.length > 1" @click="removeBill(i)"
                            class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-800">
                            <i data-lucide="trash-2" class="h-4 w-4"></i> Remove
                        </button>
                    </div>

                    <div class="form-group w-full md:w-1/2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Total Amount (₦)</label>
                        <input type="number" min="0" step="0.01" name="bill_total_amount[]" x-model="row.total_amount"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                            placeholder="e.g. 250000.00">
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">From (Year)</label>
                            <select name="bill_from_year[]" x-model.number="row.from_year"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                                <template x-for="y in years" :key="y"><option :value="y" x-text="y"></option></template>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">To (Year)</label>
                            <select name="bill_to_year[]" x-model.number="row.to_year"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                                <template x-for="y in toYears" :key="y"><option :value="y" x-text="y"></option></template>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Receipt Number (CRC No)</label>
                            <input type="text" name="bill_receipt_no[]" x-model="row.receipt_no"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm"
                                placeholder="e.g. CRC/2026/00123">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Receipt Date</label>
                            <input type="date" name="bill_receipt_date[]" x-model="row.receipt_date"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-amber-500 focus:border-amber-500 sm:text-sm">
                        </div>
                    </div>
                </div>
            </template>

            <button type="button" @click="addBill()"
                class="inline-flex items-center gap-2 rounded-md border border-dashed border-amber-400 px-4 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50 transition">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Add Bill Balance
            </button>
        </div>

        {{-- Ground  Rent tab --}}
        <div x-show="tab === 'rent'" x-cloak>
            <template x-for="(row, i) in rents" :key="i">
                <div class="relative rounded-lg border border-emerald-200 bg-emerald-50/40 p-5 mb-4">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-semibold uppercase tracking-wide text-emerald-700">
                            Ground  Rent <span x-text="i + 1"></span>
                        </span>
                        <button type="button" x-show="rents.length > 1" @click="removeRent(i)"
                            class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-800">
                            <i data-lucide="trash-2" class="h-4 w-4"></i> Remove
                        </button>
                    </div>

                    <div class="form-group w-full md:w-1/2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Amount (₦)</label>
                        <input type="number" min="0" step="0.01" name="ground_rent_amount[]" x-model="row.amount"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"
                            placeholder="e.g. 50000.00">
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">From (Year)</label>
                            <select name="ground_rent_from_year[]" x-model.number="row.from_year"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                                <template x-for="y in years" :key="y"><option :value="y" x-text="y"></option></template>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">To (Year)</label>
                            <select name="ground_rent_to_year[]" x-model.number="row.to_year"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                                <template x-for="y in toYears" :key="y"><option :value="y" x-text="y"></option></template>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Receipt No</label>
                            <input type="text" name="ground_rent_receipt_no[]" x-model="row.receipt_no"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"
                                placeholder="e.g. RCT/2026/00123">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Receipt Date</label>
                            <input type="date" name="ground_rent_receipt_date[]" x-model="row.receipt_date"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                        </div>
                    </div>
                </div>
            </template>

            <button type="button" @click="addRent()"
                class="inline-flex items-center gap-2 rounded-md border border-dashed border-emerald-400 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 transition">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Add Ground  Rent
            </button>
        </div>
    </div>
</div>
