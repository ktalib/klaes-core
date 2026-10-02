{{--
    Online Legal Search — Identify your Customer (IYC), read-only.

    The post-payment counterpart of partials/identification-card.blade.php. It shows
    what the applicant submitted and the verification result, with no inputs at all:
    the identification was checked BEFORE payment and is part of the record the
    Director reviews, so it must not be editable once money has changed hands.

    The uploaded document itself is deliberately NOT rendered here. It lives on the
    private disk and its only read path is the approver-gated route; putting it on a
    page reachable with a payment reference would undo that.

    Expects: $verification (App\Models\LegalSearchOnlineVerification)
--}}
@php
    $statusStyles = [
        'verified' => ['#dcfce7', '#166534', 'Verified'],
        'review'   => ['#fef3c7', '#92400e', 'Needs review'],
        'failed'   => ['#fee2e2', '#991b1b', 'Not verified'],
        'pending'  => ['#e2e8f0', '#334155', 'Pending'],
    ];
    $vStatus = $statusStyles[$verification->id_verification_status] ?? $statusStyles['pending'];

    // Only meaningful for a lawyer; an individual has no bar number to report on.
    $barLabels = [
        'matched'     => ['#dcfce7', '#166534', 'Confirmed'],
        'unconfirmed' => ['#e2e8f0', '#334155', 'To be confirmed by the approving officer'],
        'rejected'    => ['#fee2e2', '#991b1b', 'Could not be confirmed'],
    ];
    $barBadge = $barLabels[$verification->bar_number_status] ?? null;
@endphp

<div class="pay-wrap">
    <div class="pay-head">
        <h1>Identify your Customer (IYC)</h1>
        <p>Submitted with this request. These details can no longer be changed.</p>
    </div>
    <div class="pay-body">

        <div class="pay-row">
            <span class="lbl">Name check</span>
            <span class="val" style="background:{{ $vStatus[0] }};color:{{ $vStatus[1] }};padding:4px 10px;border-radius:999px;font-size:12px;">
                {{ $vStatus[2] }}
            </span>
        </div>

        <div class="pay-row">
            <span class="lbl">Customer type</span>
            <span class="val">{{ $verification->customerTypeLabel() }}</span>
        </div>

        @if($verification->isLawyer() && $verification->call_to_bar_number)
            <div class="pay-row">
                <span class="lbl">Call-to-Bar number</span>
                <span class="val">{{ $verification->call_to_bar_number }}</span>
            </div>
            @if($barBadge)
                <div class="pay-row">
                    <span class="lbl">Call-to-Bar check</span>
                    <span class="val" style="background:{{ $barBadge[0] }};color:{{ $barBadge[1] }};padding:4px 10px;border-radius:999px;font-size:11px;">
                        {{ $barBadge[2] }}
                    </span>
                </div>
            @endif
        @endif

        <div class="pay-row">
            <span class="lbl">Full name</span>
            <span class="val">{{ $verification->applicant_full_name }}</span>
        </div>

        <div class="pay-row">
            <span class="lbl">Phone number</span>
            <span class="val">{{ $verification->applicant_phone }}</span>
        </div>

        <div class="pay-row" style="align-items:flex-start;">
            <span class="lbl">Address</span>
            <span class="val" style="text-align:right;max-width:60%;">{{ $verification->applicant_address }}</span>
        </div>

        <div class="pay-row">
            <span class="lbl">Means of identification</span>
            <span class="val" style="text-align:right;max-width:60%;">{{ $verification->identificationLabel() }}</span>
        </div>

        <p class="pay-note" style="text-align:left;">
            Your identification was checked against the name on your document before payment
            and forms part of the record the approving officer reviews. Your uploaded document
            is stored privately and is not shown here.
        </p>
    </div>
</div>
