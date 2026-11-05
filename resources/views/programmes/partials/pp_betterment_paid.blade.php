{{-- What was paid against a betterment bill, and how it differs from the bill. --}}
@if($amountPaid !== null && $amountPaid !== '')
    @php
        $ppPaid = floatval($amountPaid);
        $ppDifference = round($ppPaid - floatval($billAmount ?? 0), 2);
    @endphp
    <span class="block mt-1 text-xs text-gray-700">Paid: ₦{{ number_format($ppPaid, 2) }}</span>
    @if($ppDifference > 0)
        <span class="inline-block mt-1 px-1.5 py-0.5 bg-blue-50 text-blue-700 text-xs font-bold rounded border border-blue-200 w-fit">Overpaid ₦{{ number_format($ppDifference, 2) }}</span>
    @elseif($ppDifference < 0)
        <span class="inline-block mt-1 px-1.5 py-0.5 bg-red-50 text-red-700 text-xs font-bold rounded border border-red-200 w-fit">Underpaid ₦{{ number_format(abs($ppDifference), 2) }}</span>
    @else
        <span class="inline-block mt-1 px-1.5 py-0.5 bg-green-50 text-green-700 text-xs font-bold rounded border border-green-200 w-fit">Paid in full</span>
    @endif
@endif
