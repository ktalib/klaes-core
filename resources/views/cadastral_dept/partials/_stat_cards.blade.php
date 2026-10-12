{{-- Summary cards at the top of a listing, in the workflow home page's style.
     Expects $cdCards: list of ['label' => , 'value' => , 'hint' => ?, 'colour' => ?, 'url' => ?, 'active' => ?].
     A card with a url is a link (usually the listing filtered to it); colour is a Tailwind v2 palette name. --}}
@if (! empty($cdCards))
  <div class="grid gap-4 grid-cols-2 lg:grid-cols-{{ min(count($cdCards), 5) }} mb-6">
    @foreach ($cdCards as $cdCard)
      @php
        $cdColour = $cdCard['colour'] ?? 'gray';
        $cdTag = ! empty($cdCard['url']) ? 'a' : 'div';
      @endphp
      <{{ $cdTag }} @if ($cdTag === 'a') href="{{ $cdCard['url'] }}" @endif
         class="rounded-xl border bg-white p-4 {{ ! empty($cdCard['active']) ? 'border-' . $cdColour . '-400 ring-2 ring-' . $cdColour . '-100' : 'border-' . $cdColour . '-200' }} {{ $cdTag === 'a' ? 'hover:shadow' : '' }}">
        <div class="text-xs font-semibold uppercase tracking-wide text-{{ $cdColour }}-700">{{ $cdCard['label'] }}</div>
        <div class="text-2xl font-black text-gray-800 mt-1">{{ $cdCard['value'] }}</div>
        @if (! empty($cdCard['hint']))<div class="text-xs text-gray-500">{{ $cdCard['hint'] }}</div>@endif
      </{{ $cdTag }}>
    @endforeach
  </div>
@endif
