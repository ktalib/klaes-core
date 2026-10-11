{{-- Row checkbox for the batch KAMMA / Approve-Reject toolbar (batch_decisions).
     Each flag is the row's OWN menu condition, so a batch never does to a file what its
     menu would refuse. land_value / knupda_fee travel with the row because every
     module's KAMMA save writes both and would blank them if they were not sent back.
     Pass flags as null to have them read from a batch_flags marker later in the row. --}}
@php
    $batchFile = $file ?? ($record->file_no ?? '');
@endphp
<input type="checkbox" class="pu-batch-check h-3.5 w-3.5 mr-1.5 align-middle rounded border-slate-300 text-violet-600 focus:ring-violet-500"
       value="{{ $record->id }}"
       data-file="{{ $batchFile }}"
       @isset($canKnupda) data-can-knupda="{{ $canKnupda ? 1 : 0 }}" @endisset
       @isset($canApprove) data-can-approve="{{ $canApprove ? 1 : 0 }}" @endisset
       @isset($canReject) data-can-reject="{{ $canReject ? 1 : 0 }}" @endisset
       data-land-value="{{ $record->land_value ?? '' }}"
       data-knupda-fee="{{ $record->knupda_fee ?? '' }}"
       aria-label="Select {{ $batchFile }}">
