<form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
  <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
    <option value="">Every bill type</option>
    @foreach ($types as $type)
      <option value="{{ $type->code }}" @selected($filters['type'] === $type->code)>{{ $type->parent_code ? '— ' : '' }}{{ $type->name }}</option>
    @endforeach
  </select>
  <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Receipt no., Bill Ref ID, Transaction ID, file no., payer"
         class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-80">
  <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Search</button>
  @if ($filters['q'] !== '' || $filters['type'] !== '')
    <a href="{{ $indexUrl }}" class="px-3 py-2 text-sm text-gray-600">Clear</a>
  @endif
</form>
