{{-- Reconciliation pill for a successful payment: $recon = unreconciled / reconciled / disputed. --}}
@php
  $reconTone = [
      'reconciled' => 'bg-green-100 text-green-800 ring-1 ring-green-200',
      'disputed' => 'bg-red-100 text-red-700 ring-1 ring-red-200',
  ][$recon] ?? 'bg-amber-50 text-amber-800 ring-1 ring-amber-200';
@endphp
<span class="px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap {{ $reconTone }}">{{ config('billing.reconciliation_statuses.' . $recon, ucfirst($recon)) }}</span>
