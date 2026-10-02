{{-- Per-column opt-in Apply-to-all for the "never copied" bank. Sits above the
     column label: the buttons then form one band of their own and every label
     still starts on the same line, which they did not when the button pushed
     each label up by a different amount. Ghost styling so it never competes
     with the toolbar Apply button, which still copies the right-hand bank. --}}
<button type="button"
    class="batch-col-apply mb-1.5 w-full flex items-center justify-center gap-1 px-1.5 py-1 rounded border border-amber-300 bg-white/80 text-amber-800 text-[9px] font-bold normal-case tracking-normal hover:bg-amber-100 hover:border-amber-400 disabled:opacity-40 disabled:cursor-not-allowed transition"
    data-f="{{ $f }}"
    data-label="{{ $label }}"
    title="Copy {{ $label }} from the source row into every other row. Optional — nothing is copied unless you press this.">
    <i data-lucide="arrow-down-to-line" class="h-2.5 w-2.5"></i>
    Apply to all
</button>
