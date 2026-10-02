{{--
    The File No cell of the register.

    One file, always - a multi-plot application is still one Right of Occupancy.
    What the chip adds is how many plots that file carries, because that is how
    many file numbers commissioning will issue off it.

    Expects: $record.
--}}
@php
    $plots = max(1, (int) ($record->plot_count ?? 1));
@endphp

<span class="font-mono text-xs text-slate-700">{{ $record->file_no ?: '-' }}</span>

@if($plots > 1)
    <span class="ml-1.5 align-middle px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[9px] font-black uppercase tracking-widest"
          title="This file covers {{ $plots }} plots; commissioning will issue {{ $plots }} new file numbers">
        {{ $plots }} plots
    </span>
@endif
