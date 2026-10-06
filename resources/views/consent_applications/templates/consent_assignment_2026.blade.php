{{-- Individual to Individual, Individual to Company, Company to Company and
     Gift. The rates and the stamp duty payee (KIRS / FIRS) come in $fees from
     config/consent_bill.php transaction_types. --}}
@include('consent_applications.templates._consent_letter_2026', [
    'verb' => 'Assign',
    'closing' => 'further action',
])
