{{-- The Special Assignment (SPAS) contravention tariff, moved here from the SPAS Bills page.
     Editing an amount changes what FUTURE bills cost; bills already raised keep their own
     copy of each line in spa_bill_lines, so an old bill still adds up after a change here. --}}
@php
    $rows = $spaBillItems->map(fn ($it) => [
        'id' => $it->id,
        'name' => $it->name,
        'amount' => number_format((float) $it->amount, 2, '.', ''),
        'is_active' => (bool) $it->is_active,
        'is_on_demand' => (bool) ($it->is_on_demand ?? false),
    ])->values();
    $onDemand = $spaBillSupportsOnDemand ?? false;
@endphp

<div class="iw-alert info">
    <i data-lucide="info" class="h-4 w-4 mt-0.5"></i>
    <div>
        A Special Assignment bill is raised once per application, the moment its approved land use
        differs from the use prevailing on the ground. Items set to <strong>0</strong> or switched off are not charged.
        Removing an item switches it off; bills already raised keep it.
        @if($onDemand)
            An item marked <strong>When needed</strong> (a Penalty Fee) is never charged automatically:
            the officer adds it to a particular bill from the Generate Bill form, at the amount set here.
        @endif
        Renaming an item renames it on existing bills, payments and receipts too;
        a new amount applies only to bills raised from now on.
    </div>
</div>

<form method="POST" action="{{ route('configurable-entries.spa-bill-items.save') }}" class="iw-card"
      x-data="spaBillItems(@js($rows))">
    @csrf
    <div class="iw-card-head">
        <div>
            <div class="iw-card-title"><i data-lucide="receipt" class="h-5 w-5" style="color:#4d7c0f"></i> SPAS Bill Items</div>
            <div class="iw-card-sub">Charged automatically when a property is in contravention.</div>
        </div>
    </div>

    <div class="iw-card-body space-y-4">
        <div class="overflow-x-auto">
            <table class="ce-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right" style="width:180px">Amount (&#8358;)</th>
                        <th class="text-center" style="width:90px">Active</th>
                        @if($onDemand)<th class="text-center" style="width:110px" title="Not charged automatically; added to a bill by the officer">When needed</th>@endif
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, i) in rows" :key="row.key">
                        <tr>
                            <td>
                                <input type="hidden" :name="`items[${i}][id]`" :value="row.id ?? ''">
                                <input type="hidden" :name="`items[${i}][sort_order]`" :value="i + 1">
                                <input type="text" class="iw-input" maxlength="150" required
                                       :name="`items[${i}][name]`" x-model="row.name">
                            </td>
                            <td>
                                <input type="number" class="iw-input text-right" min="0" step="0.01" required
                                       :name="`items[${i}][amount]`" x-model="row.amount">
                            </td>
                            <td class="text-center">
                                <input type="hidden" :name="`items[${i}][is_active]`" :value="row.is_active ? 1 : 0">
                                <label class="ce-switch"><input type="checkbox" x-model="row.is_active"><span></span></label>
                            </td>
                            @if($onDemand)
                                <td class="text-center">
                                    <input type="hidden" :name="`items[${i}][is_on_demand]`" :value="row.is_on_demand ? 1 : 0">
                                    <label class="ce-switch"><input type="checkbox" x-model="row.is_on_demand"><span></span></label>
                                </td>
                            @endif
                            <td class="text-center">
                                <button type="button" class="ce-pill red" title="Remove (switches the item off)"
                                        @click="rows.splice(i, 1)">&times;</button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!rows.length">
                        <td colspan="{{ $onDemand ? 5 : 4 }}" class="ce-muted text-center">No items yet. Add one below.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <input type="text" class="iw-input" style="flex:1;min-width:200px" maxlength="150" placeholder="New item name"
                   x-model="newName" @keydown.enter.prevent="add()">
            <input type="number" class="iw-input text-right" style="width:160px" min="0" step="0.01" placeholder="0.00"
                   x-model="newAmount" @keydown.enter.prevent="add()">
            <button type="button" class="iw-btn iw-btn-secondary" @click="add()">
                <i data-lucide="plus" class="h-4 w-4"></i> Add
            </button>
        </div>
    </div>

    <div class="iw-card-foot">
        <span class="text-sm text-gray-600">
            Every contravention will be billed <strong x-text="money(total)"></strong>
        </span>
        <button type="submit" class="iw-btn iw-btn-primary">Save Tariff</button>
    </div>
</form>

<script>
    function spaBillItems(initial) {
        let seq = 0;
        return {
            rows: initial.map(r => ({ ...r, key: ++seq })),
            newName: '', newAmount: '',
            get total() {
                // "When needed" items are not part of every bill, so not of this figure.
                return this.rows.reduce((t, r) => t + (r.is_active && !r.is_on_demand ? (parseFloat(r.amount) || 0) : 0), 0);
            },
            money(n) {
                return '₦' + Number(n || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            add() {
                const name = this.newName.trim();
                if (!name) return;
                this.rows.push({ id: null, name, amount: this.newAmount || '0', is_active: true, is_on_demand: false, key: ++seq });
                this.newName = ''; this.newAmount = '';
            },
        };
    }
</script>
