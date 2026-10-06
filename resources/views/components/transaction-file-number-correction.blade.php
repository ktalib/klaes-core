{{--
  "Correct File No. (Main / Temp)" dialog: config + script.
  Included by the PRA (/propertycard), File History (/file-index-view) and CofO (/propertycard/cofo)
  pages. Rows add the menu item with txnFileNoCorrectionMenuItem(table, id); a page sets
  window.TxnFileNoCorrection.onChanged to reload its table after a correction.
--}}
@php
    $txnFileNoCanCorrect = auth()->check()
        && auth()->user()->canDo(\App\Http\Controllers\TransactionFileNumberCorrectionController::MODULE, 'edit');
    $txnFileNoJsPath = public_path('js/transaction-file-number-correction.js');
    $txnFileNoJsVersion = is_file($txnFileNoJsPath) ? filemtime($txnFileNoJsPath) : '1';
@endphp
<script>
    window.TxnFileNoCorrection = Object.assign(window.TxnFileNoCorrection || {}, {
        canCorrect: @json($txnFileNoCanCorrect),
        candidatesUrl: @json(route('property-records.file-number-correction.get-candidates')),
        updateUrl: @json(route('property-records.file-number-correction.update')),
    });
</script>
<script src="{{ asset('js/transaction-file-number-correction.js') }}?v={{ $txnFileNoJsVersion }}"></script>
