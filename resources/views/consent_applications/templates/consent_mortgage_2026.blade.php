{{-- Mortgage: 2% registration, 0.375% stamp duty to KIRS. The rates come in
     $fees from config/consent_bill.php transaction_types. --}}
@include('consent_applications.templates._consent_letter_2026', [
    'verb' => 'Mortgage',
    'closing' => 'further consideration',
])
