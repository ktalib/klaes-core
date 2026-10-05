{{-- One beneficiary on the mobile register. $i is the row index ('__I__' in the template). --}}
<div class="row-card">
    <div class="row-head">
        <strong><span class="num"></span> Farmer</strong>
        <button type="button" class="del" aria-label="Remove farmer"><i class="fas fa-trash"></i></button>
    </div>
    <input type="hidden" name="beneficiaries[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="row-grid">
        <div class="field full">
            <label>Full name <span class="req">*</span></label>
            <input class="input" type="text" name="beneficiaries[{{ $i }}][full_name]" value="{{ $row['full_name'] ?? '' }}"
                   maxlength="255" autocomplete="off" placeholder="e.g. Musa Ibrahim">
            <div class="err">Enter the farmer's name.</div>
        </div>
        <div class="field">
            <label>Phone</label>
            <input class="input" type="tel" inputmode="tel" name="beneficiaries[{{ $i }}][phone]" value="{{ $row['phone'] ?? '' }}"
                   maxlength="50" placeholder="080…">
        </div>
        <div class="field">
            <label>NIN</label>
            <input class="input" type="text" inputmode="numeric" name="beneficiaries[{{ $i }}][nin]" value="{{ $row['nin'] ?? '' }}"
                   maxlength="20" placeholder="11 digits">
        </div>
        <div class="field full">
            <label>Status</label>
            <select class="input" name="beneficiaries[{{ $i }}][status]">
                @foreach (['Pending', 'Verified', 'Review'] as $s)
                    <option value="{{ $s }}" @selected(($row['status'] ?? 'Pending') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
