{{-- One economic-tree line on the mobile register. line_total is recalculated by SurveyCaseTree on save. --}}
<div class="row-card">
    <div class="row-head">
        <strong><span class="num"></span> Tree line</strong>
        <button type="button" class="del" aria-label="Remove tree line"><i class="fas fa-trash"></i></button>
    </div>
    <input type="hidden" name="trees[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="row-grid">
        <div class="field full">
            <label>Tree type <span class="req">*</span></label>
            <input class="input" type="text" list="treeCatalogue" name="trees[{{ $i }}][tree_type]" value="{{ $row['tree_type'] ?? '' }}"
                   maxlength="255" autocomplete="off" placeholder="e.g. Mango">
            <div class="err">Enter the tree type.</div>
        </div>
        <div class="field">
            <label>Quantity <span class="req">*</span></label>
            <input class="input" type="number" inputmode="numeric" min="1" step="1" name="trees[{{ $i }}][quantity]"
                   value="{{ $row['quantity'] ?? '' }}" placeholder="0">
            <div class="err">At least 1.</div>
        </div>
        <div class="field">
            <label>Unit price (₦) <span class="req">*</span></label>
            <input class="input" type="number" inputmode="decimal" min="0" step="0.01" name="trees[{{ $i }}][unit_price]"
                   value="{{ $row['unit_price'] ?? '' }}" placeholder="0.00">
            <div class="err">Enter a price.</div>
        </div>
    </div>
    <div class="line-total"><span>Line total</span><b data-role="line">₦0.00</b></div>
</div>
