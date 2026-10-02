@php
    $statusMeta = [
        'DRAFT' => ['ghost', 'Draft'],
        'PENDING_VERIFICATION' => ['yellow', 'Pending verification'],
        'PENDING_BILL' => ['yellow', 'Pending bill'],
        'PENDING_PAYMENT' => ['yellow', 'Pending payment'],
        'COMMISSIONED' => ['blue', 'Commissioned'],
        'RECOMMENDED' => ['blue', 'Recommended'],
        'ROFO_ISSUED' => ['blue', 'RofO issued'],
        'COFO_REGISTERED' => ['purple', 'CofO registered'],
        // ST-only: the front page exists but KANGIS has not filed the back page yet. A
        // normal waiting state, not a fault, so it is amber rather than red.
        'PENDING_TDP' => ['yellow', 'Awaiting TDP'],
        // ST-only: CofO data is captured but the certificate has no registration
        // particulars, so no front page can be issued. Waiting on Deeds, not on GIS.
        'PENDING_REGISTRATION' => ['ghost', 'Awaiting registration'],
        // SLTR: registered with Deeds, front page not generated yet.
        'PENDING_FRONT_PAGE' => ['blue', 'Awaiting front page'],
        'GENERATING_DOCUMENTS' => ['purple', 'Generating documents'],
        'COMPLETED' => ['green', 'Completed'],
        'CANCELLED' => ['red', 'Cancelled'],
    ];
    [$statusTone, $statusLabel] = $statusMeta[(string) ($status ?? '')] ?? ['', (string) ($status ?? 'Draft')];
@endphp
<span class="cw-pill {{ $statusTone }}"><span class="cw-dot"></span>{{ $statusLabel }}</span>
