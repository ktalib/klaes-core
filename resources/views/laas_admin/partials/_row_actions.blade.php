{{--
    Row action menu (development standard 4), LAAS copy of cadastral_dept's.
    $items: list of arrays with label, icon, and one of:
      url      a link (GET)
      post     a form POST to this URL (confirm: optional prompt text)
      onclick  a JS call (opens a modal)
    plus optional danger => true. Pass only the actions the user may take.
    Fixed-positioned from the button, so the table wrapper's overflow cannot clip it.
--}}
<div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
  <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
          @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
          class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
    <i data-lucide="more-vertical" class="w-5 h-5"></i>
  </button>
  <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
       :style="'top:' + top + 'px; left:' + left + 'px'"
       class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1 text-left">
    @foreach ($items as $laasItem)
      @php
          $laasItemClass = 'flex w-full items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 '
              . (($laasItem['danger'] ?? false) ? 'text-red-600' : 'text-gray-700');
      @endphp
      @if (!empty($laasItem['post']))
        <form method="POST" action="{{ $laasItem['post'] }}"
              @if (!empty($laasItem['confirm'])) onsubmit="return confirm(@js($laasItem['confirm']))" @endif
              @if (!empty($laasItem['target'])) target="{{ $laasItem['target'] }}" @endif>
          @csrf
          <button type="submit" class="{{ $laasItemClass }}">
            <i data-lucide="{{ $laasItem['icon'] }}" class="w-4 h-4"></i> {{ $laasItem['label'] }}
          </button>
        </form>
      @elseif (!empty($laasItem['onclick']))
        <button type="button" @click="open = false; {{ $laasItem['onclick'] }}" class="{{ $laasItemClass }}">
          <i data-lucide="{{ $laasItem['icon'] }}" class="w-4 h-4"></i> {{ $laasItem['label'] }}
        </button>
      @else
        <a href="{{ $laasItem['url'] }}" class="{{ $laasItemClass }}">
          <i data-lucide="{{ $laasItem['icon'] }}" class="w-4 h-4"></i> {{ $laasItem['label'] }}
        </a>
      @endif
    @endforeach
  </div>
</div>
