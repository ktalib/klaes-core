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
            {{-- Existing farmer, as on the web register (2026-11-15): offered while typing, never applied on its own. --}}
            <div class="ben-matches" @if (empty($row['survey_farmer_id'])) hidden @endif
                 style="margin-top:8px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;font-size:13px;">
                @if (! empty($row['survey_farmer_id']))<div style="padding:8px 12px;background:#f0fdfa;">Existing farmer. Their Farmer ID stays the same.</div>@endif
            </div>
            <input type="hidden" name="beneficiaries[{{ $i }}][survey_farmer_id]" value="{{ $row['survey_farmer_id'] ?? '' }}">
            <input type="hidden" name="beneficiaries[{{ $i }}][farm_id_no]" value="{{ $row['farm_id_no'] ?? '' }}">
        </div>
        <div class="field full ben-farm" hidden>
            <label>Farm</label>
            <select class="input ben-farm-sel"></select>
            <div class="hint">Same farm keeps its Farm ID in this section. A different farm gets a new one.</div>
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
