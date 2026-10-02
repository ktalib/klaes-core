{{-- Searchable role chooser. Posts roles[<action>][]; the empty value keeps an emptied list saveable. --}}
<div x-data="ceRolePicker(@js(array_values($selected)))" class="cer" @click.outside="open = false">
    <input type="hidden" name="roles[{{ $action }}][]" value="">
    <div class="cer-box" @click="open = true; $nextTick(() => $refs.search.focus())">
        <template x-for="role in selected" :key="role">
            <span class="cer-chip">
                <span x-text="role"></span>
                <button type="button" @click.stop="remove(role)" aria-label="Remove">✕</button>
                <input type="hidden" name="roles[{{ $action }}][]" :value="role">
            </span>
        </template>
        <input type="text" x-ref="search" x-model="query" @focus="open = true" @keydown.enter.prevent="matches[0] && add(matches[0])" class="cer-search" :placeholder="selected.length ? 'Add role…' : 'Only Super Admin — add a role…'">
    </div>
    <div class="cer-list" x-show="open" x-cloak>
        <template x-for="role in matches" :key="role">
            <button type="button" class="cer-option" @click="add(role)" x-text="role"></button>
        </template>
        <div class="cer-empty" x-show="matches.length === 0">No matching role.</div>
    </div>
</div>

@once
    <style>
        .cer { position: relative; }
        .cer-box { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; min-height: 40px; padding: 5px 8px; border: 1px solid #d1d5db; border-radius: 10px; background: #fff; cursor: text; }
        .cer-box:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }
        .cer-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; border-radius: 99px; padding: 2px 4px 2px 9px; }
        .cer-chip button { border: 0; background: transparent; color: #60a5fa; cursor: pointer; font-size: 11px; width: 18px; height: 18px; border-radius: 99px; }
        .cer-chip button:hover { background: #dbeafe; color: #1e40af; }
        .cer-search { flex: 1; min-width: 150px; border: 0; outline: none; font-size: 13px; padding: 4px; background: transparent; }
        .cer-list { position: absolute; z-index: 40; left: 0; right: 0; top: calc(100% + 4px); max-height: 240px; overflow-y: auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 10px 25px rgba(17, 24, 39, .12); padding: 4px; }
        .cer-option { display: block; width: 100%; text-align: left; font-size: 13px; padding: 6px 10px; border-radius: 7px; border: 0; background: transparent; cursor: pointer; color: #374151; }
        .cer-option:hover { background: #f3f4f6; }
        .cer-empty { font-size: 12px; color: #9ca3af; padding: 8px 10px; }
    </style>
@endonce

