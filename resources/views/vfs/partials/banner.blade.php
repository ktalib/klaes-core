{{--
    The legal status banner.

    Colour is never the only signal: every state carries an icon and states its
    verdict and lock in words. Colours come from the Tailwind 2.2.19 DEFAULT palette
    because that is what the CDN build ships — yellow-500 under white text fails
    WCAG AA at ~2.2:1, so withdrawal uses yellow-800.
--}}

@if($status)
    <div role="alert" class="{{ $status['banner'] }} text-white px-6 py-3 flex items-center gap-3">
        <i data-lucide="{{ $status['icon'] }}" class="h-5 w-5 flex-shrink-0"></i>

        <div class="flex-1">
            <div class="text-sm font-bold">{{ $status['headline'] }}</div>
            @if($status['detail'])
                <div class="text-xs" style="opacity: 0.94;">{{ $status['detail'] }}</div>
            @endif
            @if($status['reason'])
                <div class="text-xs" style="opacity: 0.88;">{{ \Illuminate\Support\Str::limit($status['reason'], 160) }}</div>
            @endif
        </div>

        @if($status['read_only'])
            <span class="text-xs font-bold px-2 py-1 rounded border border-white" style="border-color: rgba(255,255,255,0.6);">READ-ONLY</span>
        @endif

        @if($status['cascade'] ?? false)
            <span class="text-xs font-bold px-2 py-1 rounded bg-red-900">CASCADE ALERT</span>
        @endif
    </div>

    {{-- A summary badge must never hide a second active hold: when more than one
         restriction is on record, say so on its own line rather than folding it
         into the banner above. --}}
    @if(($status['other_active'] ?? 0) > 0)
        <div role="alert" class="bg-gray-800 text-white px-6 py-2 flex items-center gap-3">
            <i data-lucide="layers" class="h-4 w-4 flex-shrink-0"></i>
            <div class="text-xs">
                <strong>{{ $status['other_active'] }} further status {{ \Illuminate\Support\Str::plural('event', $status['other_active']) }} on record.</strong>
                Resolving one restriction does not lift the others — see the Status Timeline.
            </div>
        </div>
    @endif

    {{-- Honesty about what this release actually does. The guard that blocks
         SPU/APU and RofO on a frozen folio is backend work; until it lands the
         banner describes the legal position without enforcing it. --}}
    @if($status['enforcement_pending'] ?? false)
        <div class="bg-gray-100 border-b border-gray-300 px-6 py-2 flex items-center gap-2">
            <i data-lucide="alert-circle" class="h-3 w-3 text-gray-600 flex-shrink-0"></i>
            <span class="text-xs text-gray-700">
                Advisory only in this release: the status is shown here but is not yet enforced against transactions elsewhere in KLAES.
                @unless($status['reversal_tracked'] ?? false)
                    Reversals (for example a resolved litigation) are not tracked yet, so check the timeline before acting.
                @endunless
            </span>
        </div>
    @endif
@endif
