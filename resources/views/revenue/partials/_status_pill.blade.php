@php
  $revPill = [
      'pending' => 'bg-gray-100 text-gray-700 ring-1 ring-gray-300',
      'unpaid' => 'bg-amber-100 text-amber-800',
      'part_paid' => 'bg-blue-100 text-blue-800',
      'paid' => 'bg-green-100 text-green-800',
      'cancelled' => 'bg-gray-100 text-gray-600',
  ][$status] ?? 'bg-gray-100 text-gray-600';
@endphp
<span class="px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap {{ $revPill }}">{{ \App\Http\Controllers\Revenue\BillMasterController::filterStatuses()[$status] ?? ucfirst((string) $status) }}</span>
