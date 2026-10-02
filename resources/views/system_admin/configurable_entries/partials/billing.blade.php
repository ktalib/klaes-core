@php
    use App\Models\BillingTypeItem;
    $money = fn ($v) => '₦' . number_format((float) $v, 2);
@endphp

<div class="iw-alert info">
    <i data-lucide="sigma" class="h-4 w-4 mt-0.5"></i>
    <div>
        Each bill is worked out by its <strong>formula</strong>: the item variables joined with <span class="iw-mono">+</span>, <span class="iw-mono">-</span> and brackets, e.g.
        <span class="iw-mono">REG_FEE + STAMP_DUTY</span> or <span class="iw-mono">(REG_FEE + STAMP_DUTY) - REBATE</span>.
        These formulas apply to modules connected to the configurable billing engine; existing billing screens are not automatically changed by installing this page. Bills already issued keep their amounts. An empty formula adds every item.
    </div>
</div>

<div class="iw-card">
    <div class="iw-card-head"><div class="iw-card-title">Add bill type</div></div>
    <form method="POST" action="{{ route('configurable-entries.billing-types.store') }}" class="iw-card-body space-y-3">
        @csrf
        <label class="iw-field"><span class="iw-label">Code (lowercase letters, numbers and underscores)</span><input name="code" class="iw-input" required maxlength="60" pattern="[a-z][a-z0-9_]*" placeholder="e.g. legal_search"></label>
        <label class="iw-field"><span class="iw-label">Name</span><input name="name" class="iw-input" required maxlength="200"></label>
        <label class="iw-field"><span class="iw-label">Module</span><input name="module" class="iw-input" maxlength="60"></label>
        <button type="submit" class="iw-btn iw-btn-primary">Add bill type</button>
    </form>
</div>

@forelse($billingTypes as $type)
    @php
        $variables = $type->items->pluck('variable')->all();
        $known = $type->items->mapWithKeys(fn ($item) => [$item->variable => match ($item->source) {
            BillingTypeItem::SOURCE_REVENUE_ITEM => $item->revenueItem ? (float) $item->revenueItem->base_rate : null,
            BillingTypeItem::SOURCE_AMOUNT => (float) $item->amount,
            default => null,
        }])->all();
    @endphp
    <div class="iw-card" x-data="billFormula(@js((string) $type->formula), @js($variables), @js($known))" @keydown.escape.window="editing = null">
        <div class="iw-card-head" style="align-items:center;flex-wrap:wrap">
            <div>
                <div class="iw-card-title">
                    <span class="iw-icon-tile {{ $type->module === 'Legal Search' ? 'blue' : 'green' }}"><i data-lucide="{{ $type->module === 'Legal Search' ? 'search' : 'file-badge' }}" class="h-5 w-5"></i></span>
                    {{ $type->name }}
                    @if($type->isEffective())<span class="ce-pill green">Active</span>@else<span class="ce-pill red">Off</span>@endif
                </div>
                <div class="iw-card-sub">{{ $type->description }}</div>
            </div>
            <span class="ce-muted iw-mono">{{ $type->code }}</span>
        </div>

        <form method="POST" action="{{ route('configurable-entries.billing-types.update', $type->id) }}" class="iw-card-body space-y-4">
            @csrf
            <div class="iw-field">
                <label class="iw-label">Formula</label>
                <div class="bf-editor">
                    <input type="text" name="formula" x-model="formula" x-ref="input" class="iw-input iw-mono bf-input" placeholder="Leave empty to add every item" autocomplete="off" spellcheck="false">
                    <div class="bf-keys">
                        @foreach(['+', '-', '(', ')'] as $operator)
                            <button type="button" class="bf-key op" @click="insert(@js(' ' . $operator . ' '))">{{ $operator }}</button>
                        @endforeach
                        <span class="bf-sep"></span>
                        @foreach($variables as $variable)
                            <button type="button" class="bf-key" @click="insert(@js($variable))">{{ $variable }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="bf-check" :class="problem ? 'bad' : 'ok'">
                    <template x-if="problem"><span x-text="problem"></span></template>
                    <template x-if="!problem"><span x-text="preview()"></span></template>
                </div>
            </div>

            <div class="iw-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                <div class="iw-field"><label class="iw-label">Bill name</label><input type="text" name="name" value="{{ $type->name }}" class="iw-input" required></div>
                <div class="iw-field"><label class="iw-label">Effective from</label><input type="date" name="effective_from" value="{{ optional($type->effective_from)->toDateString() }}" class="iw-input"></div>
                <div class="iw-field"><label class="iw-label">Effective to</label><input type="date" name="effective_to" value="{{ optional($type->effective_to)->toDateString() }}" class="iw-input"></div>
                <div class="iw-field"><label class="iw-label">Status</label>
                    <label class="flex items-center gap-3 text-sm" style="height:42px"><input type="hidden" name="is_active" value="0"><span class="ce-switch"><input type="checkbox" name="is_active" value="1" @checked($type->is_active)><span></span></span> Bills can be generated</label></div>
                <input type="hidden" name="description" value="{{ $type->description }}">
            </div>

            <div class="flex items-center justify-between flex-wrap gap-2">
                <span class="ce-muted">{{ $type->updated_by_name ? 'Last change by ' . $type->updated_by_name . ' · ' . optional($type->updated_at)->format('d M Y, H:i') : '' }}</span>
                <button class="iw-btn iw-btn-success iw-btn-sm" :disabled="!!problem"><i data-lucide="save" class="h-4 w-4"></i> Save formula</button>
            </div>
        </form>

        <div class="overflow-x-auto" style="border-top:1px solid #f3f4f6">
            <table class="ce-table">
                <thead><tr><th style="width:60px">#</th><th>Variable</th><th>Item</th><th>Amount from</th><th class="text-right">Amount</th><th>Status</th><th class="text-right"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = 'new'"><i data-lucide="plus" class="h-3.5 w-3.5"></i> Add item</button></th></tr></thead>
                <tbody>
                    @foreach($type->items as $item)
                        <tr>
                            <td class="ce-muted">{{ $item->sequence }}</td>
                            <td class="iw-mono font-semibold text-gray-900">{{ $item->variable }}</td>
                            <td>{{ $item->label }}@if($item->effective_from || $item->effective_to)<div class="ce-muted">{{ optional($item->effective_from)->format('d M Y') ?: '…' }} – {{ optional($item->effective_to)->format('d M Y') ?: '…' }}</div>@endif</td>
                            <td class="ce-muted">
                                {{ BillingTypeItem::SOURCE_LABELS[$item->source] ?? $item->source }}
                                @if($item->source === BillingTypeItem::SOURCE_REVENUE_ITEM)<div class="iw-mono">{{ $item->revenueItem?->revenue_code ?? 'not set' }}</div>@endif
                            </td>
                            <td class="text-right font-semibold whitespace-nowrap">
                                @if($item->source === BillingTypeItem::SOURCE_REVENUE_ITEM){{ $item->revenueItem ? $money($item->revenueItem->base_rate) : '—' }}
                                @elseif($item->source === BillingTypeItem::SOURCE_AMOUNT){{ $money($item->amount) }}
                                @else<span class="ce-muted">per bill</span>@endif
                            </td>
                            <td>@if($item->isEffective())<span class="ce-pill green">Active</span>@else<span class="ce-pill">Off</span>@endif</td>
                            <td class="text-right"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = {{ $item->id }}"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @foreach($type->items->concat([null]) as $item)
            <div class="iwr-backdrop" x-show="editing === {{ $item ? $item->id : "'new'" }}" x-cloak x-transition.opacity @click.self="editing = null">
                <form method="POST" action="{{ $item ? route('configurable-entries.billing-items.update', [$type->id, $item->id]) : route('configurable-entries.billing-items.store', $type->id) }}" class="iwr-modal tone-green" style="max-width:600px" x-data="{ source: @js($item?->source ?? BillingTypeItem::SOURCE_REVENUE_ITEM) }">
                    @csrf
                    <div class="iwr-head">
                        <span class="iw-icon-tile green"><i data-lucide="sigma" class="h-5 w-5"></i></span>
                        <div class="flex-1"><div class="iwt-now-label">{{ $type->name }}</div><div class="iwr-title">{{ $item ? 'Edit ' . $item->variable : 'Add bill item' }}</div></div>
                        <button type="button" class="iwr-close" @click="editing = null">✕</button>
                    </div>
                    <div class="iwr-body space-y-4">
                        <div class="iw-grid" style="gap:12px">
                            <div class="iw-field"><label class="iw-label">Variable <span class="req">*</span></label><input type="text" name="variable" value="{{ $item?->variable }}" required class="iw-input iw-mono" placeholder="e.g. REBATE" style="text-transform:uppercase"><span class="iw-help">The name used in the formula.</span></div>
                            <div class="iw-field"><label class="iw-label">Order</label><input type="number" min="0" name="sequence" value="{{ $item?->sequence }}" class="iw-input"></div>
                            <div class="iw-field full"><label class="iw-label">Item name <span class="req">*</span></label><input type="text" name="label" value="{{ $item?->label }}" required class="iw-input"></div>
                            <div class="iw-field full"><label class="iw-label">Amount from <span class="req">*</span></label>
                                <select name="source" class="iw-select" x-model="source">
                                    @foreach(BillingTypeItem::SOURCE_LABELS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="iw-field full" x-show="source === 'revenue_item'"><label class="iw-label">Revenue item <span class="req">*</span></label>
                                <select name="revenue_item_id" class="iw-select" :disabled="source !== 'revenue_item'">
                                    <option value="">Select…</option>
                                    @foreach($billingRevenueItems as $revenueItem)<option value="{{ $revenueItem->id }}" @selected($item?->revenue_item_id === $revenueItem->id)>{{ $revenueItem->optionLabel() }}</option>@endforeach
                                </select>
                                <span class="iw-help">The item's base rate (Revenue Items) is the amount; its Revenue Code prints on the bill.</span>
                            </div>
                            <div class="iw-field full" x-show="source === 'amount'" x-cloak><label class="iw-label">Amount (₦) <span class="req">*</span></label><input type="number" step="0.01" min="0" name="amount" value="{{ $item && $item->amount !== null ? (float) $item->amount : '' }}" class="iw-input" :disabled="source !== 'amount'"></div>
                            <div class="iw-field full" x-show="source === 'workflow'" x-cloak><div class="iw-alert info"><i data-lucide="info" class="h-4 w-4 mt-0.5"></i><div>The module works this amount out for each bill (for example Stamp Duty on the consideration).</div></div></div>
                            <div class="iw-field"><label class="iw-label">Effective from</label><input type="date" name="effective_from" value="{{ optional($item?->effective_from)->toDateString() }}" class="iw-input"></div>
                            <div class="iw-field"><label class="iw-label">Effective to</label><input type="date" name="effective_to" value="{{ optional($item?->effective_to)->toDateString() }}" class="iw-input"></div>
                        </div>
                        @if($item)<label class="flex items-center gap-3 text-sm"><input type="hidden" name="is_active" value="0"><span class="ce-switch"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)><span></span></span> Active</label>@endif
                    </div>
                    <div class="iwr-foot"><span class="ce-muted">An inactive item counts as ₦0 in the formula.</span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = null">Cancel</button><button class="iw-btn iw-btn-success iw-btn-sm">Save</button></div></div>
                </form>
            </div>
        @endforeach
    </div>
@empty
    <div class="iw-card"><div class="iw-card-body"><div class="iw-empty">No bill formulas yet. Add a bill type above, then configure its items and formula. No dev rates have been imported.</div></div></div>
@endforelse

<script>
    // Mirrors App\Services\Billing\BillFormula for instant feedback; the server checks again on save.
    function billFormula(initial, variables, amounts) {
        return {
            formula: initial, variables: variables.map(v => v.toUpperCase()), amounts, editing: null,
            get problem() { return this.run(this.formula).error; },
            insert(text) {
                const input = this.$refs.input;
                const start = input.selectionStart ?? this.formula.length, end = input.selectionEnd ?? start;
                this.formula = (this.formula.slice(0, start) + text + this.formula.slice(end)).replace(/\s{2,}/g, ' ');
                this.$nextTick(() => { input.focus(); const at = start + text.length; input.setSelectionRange(at, at); });
            },
            /** Parses the formula like BillFormula: {error} or {value} (a variable with no amount counts as 0). */
            run(formula) {
                const text = formula.trim();
                if (!text) return { error: '', value: null };
                const tokens = [];
                for (let i = 0; i < text.length;) {
                    const ch = text[i];
                    if (/\s/.test(ch)) { i++; continue; }
                    if ('+-()'.includes(ch)) { tokens.push(ch); i++; continue; }
                    const m = /^[A-Za-z][A-Za-z0-9_]*/.exec(text.slice(i));
                    if (m) { tokens.push(m[0].toUpperCase()); i += m[0].length; continue; }
                    return { error: `“${ch}” is not allowed. Use item variables with +, - and brackets only.` };
                }
                const unknown = [...new Set(tokens.filter(t => /^[A-Z]/.test(t) && !this.variables.includes(t)))];
                if (unknown.length) return { error: `Unknown variable: ${unknown.join(', ')}. Use: ${this.variables.join(', ')}.` };
                let pos = 0;
                const term = () => {
                    const t = tokens[pos];
                    if (t === undefined) throw 'The formula ends with an operator; add an item after it.';
                    if (t === '+' || t === '-') { pos++; const v = term(); return t === '-' ? -v : v; }
                    if (t === '(') { pos++; const v = expr(); if (tokens[pos] !== ')') throw 'A bracket is not closed.'; pos++; return v; }
                    if (t === ')') throw 'Empty brackets, or a bracket closed straight after an operator.';
                    pos++;
                    return Number(this.amounts[t] ?? 0);
                };
                const expr = () => {
                    let total = term();
                    while (tokens[pos] === '+' || tokens[pos] === '-') { const op = tokens[pos++]; const v = term(); total = op === '+' ? total + v : total - v; }
                    return total;
                };
                try {
                    const value = expr();
                    if (pos < tokens.length) return { error: tokens[pos] === ')' ? 'There is a closing bracket without an opening one.' : `Expected + or - before “${tokens[pos]}”.` };
                    return { error: '', value };
                } catch (e) { return { error: e }; }
            },
            preview() {
                const text = this.formula.trim();
                if (!text) return 'No formula: the bill adds every active item.';
                const money = v => '₦' + Number(v).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const shown = text.toUpperCase().replace(/[A-Z][A-Z0-9_]*/g, v => this.amounts[v] != null ? money(this.amounts[v]) : v);
                const priced = Object.values(this.amounts).every(v => v != null);
                return priced ? '✓ ' + shown + ' = ' + money(this.run(text).value) : '✓ Valid formula · ' + shown;
            },
        };
    }
</script>
<style>
    .bf-editor { border: 1px solid #d1d5db; border-radius: 12px; overflow: hidden; background: #fff; }
    .bf-editor:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }
    .bf-input { border: 0 !important; box-shadow: none !important; font-size: 17px !important; font-weight: 600; letter-spacing: .02em; padding: 12px 14px !important; }
    .bf-keys { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 10px; background: #f9fafb; border-top: 1px solid #f3f4f6; }
    .bf-key { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; font-weight: 700; padding: 5px 10px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff; color: #1f2937; cursor: pointer; }
    .bf-key:hover { border-color: #3b82f6; color: #1d4ed8; }
    .bf-key.op { min-width: 34px; color: #1d4ed8; background: #eff6ff; border-color: #bfdbfe; font-size: 14px; }
    .bf-sep { width: 1px; background: #e5e7eb; margin: 0 4px; }
    .bf-check { font-size: 13px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; border-radius: 10px; padding: 8px 12px; }
    .bf-check.ok { background: #f0fdf4; color: #166534; }
    .bf-check.bad { background: #fef2f2; color: #991b1b; font-family: inherit; }
</style>
