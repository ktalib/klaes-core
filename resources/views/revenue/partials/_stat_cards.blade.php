<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  @foreach ([
      ['Bills', number_format($stats['count']), 'receipt', 'text-emerald-600 bg-emerald-50'],
      ['Billed', '₦' . number_format($stats['billed'], 2), 'banknote', 'text-blue-600 bg-blue-50'],
      ['Paid', '₦' . number_format($stats['paid'], 2), 'badge-check', 'text-green-600 bg-green-50'],
      ['Outstanding', '₦' . number_format($stats['outstanding'], 2), 'alert-circle', 'text-amber-600 bg-amber-50'],
  ] as [$label, $value, $icon, $tone])
    <div class="rounded-xl border border-gray-200 bg-white p-4 flex items-center gap-3">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg {{ $tone }}"><i data-lucide="{{ $icon }}" class="w-5 h-5"></i></span>
      <div class="min-w-0">
        <div class="text-xs uppercase text-gray-500">{{ $label }}</div>
        <div class="text-lg font-bold text-gray-800 truncate">{{ $value }}</div>
      </div>
    </div>
  @endforeach
</div>
