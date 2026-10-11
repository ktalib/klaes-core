{{--
    Allottee's Response Form to Notice of Contravention of Land Use (A4, two pages).

    Layout and wording are the Ministry template supplied on 2026-10-09
    (docs/templates/SPAS_Notice_Response.html). $filled = a recorded response;
    otherwise a blank form carrying the notice's own particulars, to hand to
    the allottee.
--}}
@php
    use App\Models\SpaNoticeResponse;

    $fmt  = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '';
    $opt  = $filled ? ($r->response_option ?? null) : null;
    $dec  = $filled ? ($r->director_decision ?? null) : null;
    $atts = $filled ? (array) ($r->attachments ?? []) : [];
    $box  = fn ($on) => $on ? '☑' : '☐';
    $v    = fn ($k) => trim((string) ($r->{$k} ?? ''));
    $official = $filled; // Section E is the Ministry's own; left blank on a hand-out form.
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Allottee’s Response Form - {{ $notice->file_number }}</title>
<style>
    @page { size: A4; margin: 18mm 18mm 17mm; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #f2f2f2; color: #111; font-family: "Times New Roman", Times, serif; font-size: 11pt; line-height: 1.28; }
    .page { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 18mm; background: #fff; box-shadow: 0 1px 8px #bbb; }
    .letterhead { text-align: center; line-height: 1.25; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .letterhead .letterhead-text { flex: 1; }
    /* Same two marks as the SPAS receipt and certificate: Coat of Arms left, Ministry right. */
    .letterhead .logo { width: 72px; height: 72px; object-fit: contain; flex-shrink: 0; }
    .letterhead .ministry { font-weight: bold; font-size: 13pt; text-transform: uppercase; }
    .letterhead .office { font-weight: bold; }
    h1 { font-size: 12pt; text-align: center; margin: 20px 0 16px; text-transform: uppercase; }
    .field { margin: 8px 0; display: flex; gap: 6px; align-items: baseline; }
    .field .lbl { white-space: nowrap; }
    .fill { flex: 1; border-bottom: 1px dotted #777; min-height: 16px; padding: 0 4px; font-weight: bold; }
    .pair { display: flex; gap: 18px; }
    .pair > .field { flex: 1; }
    .section-title { font-weight: bold; margin: 15px 0 8px; padding: 4px 0; border-bottom: 1px solid #777; }
    .option { margin: 9px 0 12px; }
    .option-title { font-weight: bold; margin-bottom: 3px; }
    .write-lines { min-height: 28px; border-bottom: 1px dotted #777; margin: 7px 0; font-weight: bold; white-space: pre-wrap; }
    .remarks-area { min-height: 45px; border-bottom: 1px dotted #777; margin: 5px 0 10px; font-weight: bold; white-space: pre-wrap; }
    .signature-row { display: flex; gap: 20px; margin: 13px 0; }
    .signature-row > div { flex: 1; }
    .checklist { list-style: none; padding-left: 0; margin-top: 6px; }
    .checklist li { margin: 5px 0; }
    .checkbox { font-family: "Segoe UI Symbol", Arial, sans-serif; }
    .page-break { break-before: page; page-break-before: always; }
    .page-number { text-align: right; font-size: 9pt; margin-top: 18px; }
    .no-print { text-align: center; padding: 12px; }
    .no-print button { padding: 9px 22px; font-size: 13px; font-weight: bold; cursor: pointer; background: rgb(186,191,12); color: #fff; border: 0; border-radius: 8px; }
    @media print {
        body { background: #fff; font-size: 10.5pt; }
        .page { margin: 0; padding: 0; width: auto; min-height: 0; box-shadow: none; }
        .no-print { display: none; }
        /* Page 2 holds Sections C–E and the checklist; keep it to one sheet. */
        .page-break .field { margin: 5px 0; }
        .page-break p { margin: 6px 0; }
        .page-break .option { margin: 6px 0 8px; }
        .page-break .section-title { margin: 10px 0 6px; }
        .page-break .signature-row { margin: 8px 0; }
        .page-break .write-lines { min-height: 22px; margin: 5px 0; }
        .page-break .remarks-area { min-height: 34px; }
        .checklist li { margin: 3px 0; }
    }
</style>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Print</button></div>

<div class="page">
    <header class="letterhead">
        <img class="logo" src="{{ url('assets/logo/ministry1.jpg') }}" alt="Coat of Arms">
        <div class="letterhead-text">
            <div class="ministry">MINISTRY OF LAND AND PHYSICAL PLANNING</div>
            <div class="office">Office of the Director, Special Assignment</div>
            <div>No 2 Bala Mohd Road, Nassarawa Kano State</div>
        </div>
        <img class="logo" src="{{ url('assets/logo/ministry2.png') }}" alt="Ministry Logo">
    </header>

    <h1>Allottee’s Response Form to Notice of Contravention of Land Use</h1>

    <div class="field"><span class="lbl">Reference No:</span><span class="fill">{{ $v('reference_no') }}</span></div>
    <div class="field"><span class="lbl">Date of Original Notice:</span><span class="fill">{{ $fmt($r->original_notice_date ?? null) }}</span></div>
    <div class="field"><span class="lbl">Date of This Response:</span><span class="fill">{{ $fmt($r->response_date ?? null) }}</span></div>

    <div class="section-title">SECTION A: ALLOTTEE DETAILS</div>
    <div class="field"><span class="lbl">Full Name of Allottee / Company Name:</span><span class="fill">{{ $v('allottee_name') }}</span></div>
    <div class="field"><span class="lbl">Address of Allottee:</span><span class="fill">{{ $v('allottee_address') }}</span></div>
    <div class="pair">
        <div class="field"><span class="lbl">Phone Number:</span><span class="fill">{{ $v('phone') }}</span></div>
        <div class="field"><span class="lbl">Email:</span><span class="fill">{{ $v('email') }}</span></div>
    </div>
    <div class="field"><span class="lbl">Representative Name (if Company/Organization):</span><span class="fill">{{ $v('representative_name') }}</span></div>

    <div class="section-title">SECTION B: PROPERTY DETAILS</div>
    <div class="pair">
        <div class="field"><span class="lbl">SPAS ID:</span><span class="fill">{{ optional($notice->application)->spa_id }}</span></div>
        <div class="field"><span class="lbl">File Number:</span><span class="fill">{{ $notice->application ? $notice->application->file_number : $notice->file_number }}</span></div>
    </div>
    <div class="field"><span class="lbl">Plot Number:</span><span class="fill">{{ $v('plot_number') }}</span></div>
    <div class="field"><span class="lbl">Location / Layout Name:</span><span class="fill">{{ $v('location') }}</span></div>
    <div class="field"><span class="lbl">Approved Land Use:</span><span class="fill">{{ $v('approved_land_use') }}</span></div>
    <div class="field"><span class="lbl">Current Land Use (as observed by Ministry):</span><span class="fill">{{ $v('current_land_use') }}</span></div>

    <div class="section-title">SECTION C: ACKNOWLEDGEMENT &amp; RESPONSE</div>
    <p>Please tick [✓] the appropriate box that reflects your response to the Notice of Contravention of Land Use.</p>

    @foreach(['formalize', 'change_of_use'] as $key)
        <div class="option">
            <div class="option-title"><span class="checkbox">{{ $box($opt === $key) }}</span> {{ SpaNoticeResponse::OPTIONS[$key][0] }}</div>
            {{ SpaNoticeResponse::OPTIONS[$key][1] }}
        </div>
    @endforeach
    <div class="page-number">1 | Page</div>
</div>

<div class="page page-break">
    <div style="font-weight:bold; margin-bottom:18px;">Allottee’s Response Form to Notice of Contravention of Land Use</div>

    <div class="option">
        <div class="option-title"><span class="checkbox">{{ $box($opt === 'appeal') }}</span> {{ SpaNoticeResponse::OPTIONS['appeal'][0] }}</div>
        {{ SpaNoticeResponse::OPTIONS['appeal'][1] }}
        @if($opt === 'appeal' && $v('appeal_grounds') !== '')
            <div class="write-lines">{{ $v('appeal_grounds') }}</div>
        @else
            <div class="write-lines"></div>
            <div class="write-lines"></div>
        @endif
    </div>
    <div class="option">
        <div class="option-title"><span class="checkbox">{{ $box($opt === 'surrender') }}</span> {{ SpaNoticeResponse::OPTIONS['surrender'][0] }}</div>
        {{ SpaNoticeResponse::OPTIONS['surrender'][1] }}
    </div>
    <div class="option">
        <div class="option-title"><span class="checkbox">{{ $box($opt === 'other') }}</span> {{ SpaNoticeResponse::OPTIONS['other'][0] }}</div>
        <div class="write-lines">{{ $opt === 'other' ? $v('other_details') : '' }}</div>
    </div>

    <div class="section-title">SECTION D: STATEMENT OF FACTS &amp; UNDERTAKING</div>
    <p>I, the undersigned, hereby declare that the information provided in this form is true and correct to the best of my knowledge. I understand that failure to respond or provide false information may lead to the invocation of the Ministry’s powers under Section 9(4) or 28(5) of the Land Use Act, which may include revocation of the Right of Occupancy.</p>
    <p>I undertake to comply with all directives issued by the Ministry regarding the regularization, conversion, or surrender of the aforementioned property within the stipulated timeframe.</p>

    <div class="signature-row">
        <div class="field"><span class="lbl">Allottee’s Signature:</span><span class="fill">{{ $filled && ($r->declaration_signed ?? false) ? 'Signed' : '' }}</span></div>
        <div class="field"><span class="lbl">Date:</span><span class="fill">{{ $fmt($r->allottee_signed_date ?? null) }}</span></div>
    </div>

    <div class="section-title">SECTION E: FOR OFFICIAL USE ONLY (MINISTRY)</div>
    <div class="field"><span class="lbl">Received by (Name):</span><span class="fill">{{ $official ? $v('received_by') : '' }}</span></div>
    <div class="field"><span class="lbl">Designation:</span><span class="fill">{{ $official ? $v('receiver_designation') : '' }}</span></div>
    <div class="signature-row">
        <div class="field"><span class="lbl">Date Received:</span><span class="fill">{{ $official ? $fmt($r->date_received ?? null) : '' }}</span></div>
        <div class="field"><span class="lbl">Time:</span><span class="fill">{{ $official ? $v('time_received') : '' }}</span></div>
    </div>
    <div class="field"><span class="lbl">Signature &amp; Stamp:</span></div>
    <div class="write-lines"></div>
    <div class="field"><span class="lbl">Action Taken / Remarks:</span></div>
    <div class="remarks-area">{{ $official ? $v('action_remarks') : '' }}</div>
    <div class="field" style="display:block">Director’s Approval:
        <span class="checkbox">{{ $box($dec === 'noted') }}</span> Noted &nbsp;
        <span class="checkbox">{{ $box($dec === 'referred') }}</span> Referred to{{ $dec === 'referred' && $v('referred_to') !== '' ? ': '.$v('referred_to') : '' }} &nbsp;
        <span class="checkbox">{{ $box($dec === 'closed') }}</span> File Closed
    </div>
    <div class="signature-row">
        <div class="field"><span class="lbl">Signature:</span><span class="fill"></span></div>
        <div class="field"><span class="lbl">Date:</span><span class="fill">{{ $official ? $fmt($r->director_date ?? null) : '' }}</span></div>
    </div>

    <div class="section-title">Checklist of Attachments (Please ensure these are included):</div>
    <ul class="checklist">
        @foreach(SpaNoticeResponse::ATTACHMENTS as $key => $text)
            <li><span class="checkbox">{{ $box(in_array($key, $atts, true)) }}</span> {{ $text }}</li>
        @endforeach
    </ul>
</div>
</body>
</html>
