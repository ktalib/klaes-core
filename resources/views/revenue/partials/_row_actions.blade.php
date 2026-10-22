{{--
    Row action menu (development standard 4): one dropdown per row.
    $items: list of [label, lucide icon, url or null, new tab?, onclick JS?].
    With onclick JS the item runs it (opens a modal); with a url it links; with neither it shows greyed out
    (not built yet, or not available yet: an optional 6th element gives the reason).
    The menu is fixed-positioned from the button, so a table wrapper's overflow cannot clip it.
--}}
<div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
  <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
          @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
          class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
    <i data-lucide="more-vertical" class="w-5 h-5"></i>
  </button>
  <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
       :style="'top:' + top + 'px; left:' + left + 'px'"
       class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
    @foreach ($items as $revItem)
      @if (! empty($revItem[4]))
        <button type="button" onclick="{{ $revItem[4] }}" @click="open = false"
                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
          <i data-lucide="{{ $revItem[1] }}" class="w-4 h-4 text-gray-500"></i> {{ $revItem[0] }}
        </button>
      @elseif (! empty($revItem[2]))
        <a href="{{ $revItem[2] }}" @if ($revItem[3] ?? false) target="_blank" @endif
           class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
          <i data-lucide="{{ $revItem[1] }}" class="w-4 h-4 text-gray-500"></i> {{ $revItem[0] }}
        </a>
      @else
        <span class="flex items-center gap-2 px-3 py-2 text-sm text-gray-400 cursor-not-allowed" title="{{ $revItem[5] ?? 'Coming soon' }}">
          <i data-lucide="{{ $revItem[1] }}" class="w-4 h-4"></i> {{ $revItem[0] }}
        </span>
      @endif
    @endforeach
  </div>
</div>
