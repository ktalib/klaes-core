{{--
    Cadastral Verification Report Sheet (client DOCX "Cadastral Verification Report Sheet",
    2026-10-15). Same sections, tables and tick boxes as the sheet; the values come from
    the case's cadastral_verification_reports row, else the records (VerificationReports::values).
    A ticked box prints as a filled square; signatures are left for the officers' hands.
--}}
@php
    $R = \App\Models\CadastralDept\CadastralVerificationReport::class;
    $box = fn (bool $on) => $on ? '&#9745;' : '&#9744;';
    $ticks = function (array $opts, ?string $value) use ($box) {
        return implode('&nbsp;&nbsp; ', array_map(fn ($o) => $box($value === $o) . ' ' . e($o), $opts));
    };
    $t = fn (?string $s) => e(trim((string) $s));
    $nature = $v['current_transaction_nature'] ?? null;
    $natureOther = $nature && ! in_array($nature, $R::NATURES, true) ? trim(preg_replace('/^Other:\s*/i', '', $nature)) : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastral Verification Report Sheet - {{ $case->case_ref }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; color: #000; font-size: 12px;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sheet { background: #fff; max-width: 760px; margin: 16px auto; padding: 26px 34px; box-shadow: 0 0 10px rgba(0,0,0,.1); box-sizing: border-box; position: relative; }
        .head { text-align: center; }
        .head .title-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .head .logo { height: 20mm; width: auto; display: block; flex: none; }
        .head .meta { display: flex; justify-content: flex-end; margin-top: 4px; }
        .head .qr { width: 64px; height: 64px; }
        .head h1 { font-size: 15px; margin: 2px 0; letter-spacing: .3px; }
        .head h2 { font-size: 13px; margin: 2px 0; }
        .head h3 { font-size: 14px; margin: 4px 0 0; letter-spacing: .5px; text-decoration: underline; }
        .instr { border: 1px solid #999; background: #f7f7f7; padding: 6px 8px; margin: 10px 0; font-size: 11px; }
        .sec { font-weight: bold; font-size: 12.5px; margin: 14px 0 4px; padding: 4px 6px; background: #dfe9f7; border-left: 4px solid #0b3d91; }
        .note { font-size: 11px; font-style: italic; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 5px 6px; vertical-align: top; text-align: left; }
        th { background: #eee; font-size: 11px; }
        td.sn, th.sn { width: 28px; text-align: center; }
        .v { font-weight: bold; }
        .verdict p { margin: 4px 0; }
        .lines td { height: 18px; }
        .sign td { height: 34px; }
        .muted { color: #555; font-weight: normal; }
        .toolbar { max-width: 760px; margin: 12px auto 0; display: flex; gap: 8px; font-size: 13px; }
        .toolbar a, .toolbar button { padding: 6px 12px; border: 1px solid #999; background: #fff; border-radius: 4px; color: #000; text-decoration: none; cursor: pointer; font-size: 13px; }
        .toolbar .note2 { color: #92400e; align-self: center; }
        @media print { body { background: #fff; } .sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; } .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">Print</button>
    <a href="{{ route('cadastral-dept.cases.verification-report.show', $case->id) }}">Back to the sheet</a>
    @if (! $report)
        <span class="note2">Not saved yet: sections 2 to 6 show what the records suggest.</span>
    @elseif (! $report->isComplete())
        <span class="note2">No verdict yet.</span>
    @endif
</div>

<div class="sheet">
    <div class="head">
        {{-- The two ministry logos either side of the heading, as the FMC print (user, 2026-10-15). --}}
        <div class="title-row">
            <img class="logo" src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Kano State">
            <div>
                <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                <h2>CADASTRAL DEPARTMENT</h2>
                <h3>CADASTRAL VERIFICATION REPORT SHEET</h3>
            </div>
            <img class="logo" src="{{ asset('assets/logo/ministry2.png') }}" alt="Ministry of Land and Physical Planning">
        </div>
        <div class="meta"><img src="{{ $qr }}" alt="QR Code" class="qr"></div>
    </div>

    <div class="instr"><strong>Instructions for Verifying Officer:</strong> This form must be completed during the Verification Workflow.
        Sections marked with [Auto] will be automatically populated by the KLAES system from the File Movement Card (FMC) and Registry Database.
        All other sections require manual verification and input.</div>

    <div class="sec">SECTION 1: GENERAL FILE INFORMATION (Main Section) <span class="muted">[Auto]</span></div>
    <table>
        <tr><th class="sn">No</th><th>Field</th><th>Details</th><th>Field</th><th>Details</th></tr>
        <tr><td class="sn">1</td><td>File Reference No</td><td class="v">{{ $case->file_number }}</td><td>Date of Application</td><td class="v">{{ $t($v['application_date']) }}</td></tr>
        <tr><td class="sn">2</td><td>Allottee Name</td><td class="v">{{ $t($v['allottee_name']) }}</td><td>Application Type</td><td class="v">{{ $t($v['application_type']) }}</td></tr>
        <tr><td class="sn">3</td><td>Plot Number</td><td class="v">{{ $t($v['plot_no']) }}</td><td>Layout Name/No</td><td class="v">{{ $t($v['layout_no']) }}</td></tr>
        <tr><td class="sn">4</td><td>District/Location</td><td class="v">{{ $t($v['district_location']) }}</td><td>FMC Commission Date</td><td class="v">{{ $t($v['fmc_commission_date']) }}</td></tr>
    </table>

    <div class="sec">SECTION 2: TRANSACTION HISTORY</div>
    <p class="note">To be completed by verifying the historical records in the Registry and Cadastral Information Unit.</p>
    <table>
        <tr><th class="sn">SN</th><th>Holder Details</th><th>Original Holder (First Allottee)</th><th>Current Holder (Applicant)</th></tr>
        <tr><td class="sn">1</td><td>Full Name:</td><td class="v">{{ $t($v['original_holder_name']) }}</td><td class="v">{{ $t($v['current_holder_name']) }}</td></tr>
        <tr><td class="sn">2</td><td>File / Plot Reference:</td><td class="v">{{ $t($v['original_holder_ref']) }}</td><td class="v">{{ $t($v['current_holder_ref']) }}</td></tr>
        <tr><td class="sn">3</td><td>Date of Allocation/Transaction:</td><td class="v">{{ $t($v['original_holder_date']) }}</td><td class="v">{{ $t($v['current_holder_date']) }}</td></tr>
        <tr><td class="sn">4</td><td>Nature of Transaction:</td><td>Original Allocation</td>
            <td>{!! $ticks($R::NATURES, $nature) !!}&nbsp;&nbsp; {!! $box($natureOther !== '') !!} Other: <span class="v">{{ $natureOther }}</span></td></tr>
    </table>

    <div class="sec">SECTION 3: ENCUMBRANCE CHECK</div>
    <p class="note">Verify the legal status of the parcel against the Deeds and Registry database. Check the appropriate box.</p>
    <table>
        <tr><th class="sn">SN</th><th>Encumbrance Type</th><th>Status</th><th>Details / Reference No. (If applicable)</th></tr>
        <tr><td class="sn">1</td><td>Caveat</td><td>{!! $ticks($R::ENCUMBRANCE, $v['caveat_status']) !!}</td><td class="v">{{ $t($v['caveat_ref']) }}</td></tr>
        <tr><td class="sn">2</td><td>Mortgage</td><td>{!! $ticks($R::ENCUMBRANCE, $v['mortgage_status']) !!}</td><td class="v">{{ $t($v['mortgage_ref']) }}</td></tr>
        <tr><td class="sn">3</td><td>Surrender &amp; Release</td><td>{!! $ticks($R::RELEASE, $v['release_status']) !!}</td><td class="v">{{ $t($v['release_ref']) }}</td></tr>
    </table>

    <div class="sec">SECTION 4: DCIV INVESTIGATION CHECK</div>
    <p class="note">To be completed in conjunction with the DCIV (Department of Control and Investigation) to verify the genuineness of the transaction and check for fraud or irregularities.</p>
    <table>
        <tr><th class="sn">SN</th><th>Investigation Parameter</th><th>Findings / Status</th></tr>
        <tr><td class="sn">1</td><td>DCIV Investigation Status:</td><td>{!! $ticks($R::DCIV_STATUS, $v['dciv_status']) !!}</td></tr>
        <tr><td class="sn">2</td><td>Investigating Officer Name:</td><td class="v">{{ $t($v['dciv_officer']) }}</td></tr>
        <tr><td class="sn">3</td><td>Genuineness of Documents:</td><td>{!! $ticks($R::GENUINENESS, $v['dciv_genuineness']) !!}</td></tr>
        <tr><td class="sn">4</td><td>DCIV Findings / Remarks:</td><td class="v">{{ $t($v['dciv_remarks']) }}</td></tr>
    </table>

    <div class="sec">SECTION 5: TITLE STATUS &amp; PARCEL UPDATE CHECK</div>
    <p class="note">To be completed by the Cadastral Information and Report Units to verify the physical and spatial status of the land.</p>
    <table>
        <tr><th class="sn">SN</th><th>Check Parameter</th><th>Status / Details</th></tr>
        <tr><td class="sn">1</td><td>Current Title Status:</td><td>{!! $ticks($R::TITLE_STATUS, $v['title_status']) !!}</td></tr>
        <tr><td class="sn">2</td><td>Title Document Reference:</td><td class="v">{{ $t($v['title_ref']) }}</td></tr>
        <tr><td class="sn">3</td><td>Parcel Update Status (APU/SPU):</td><td>{!! $ticks($R::PARCEL_UPDATE, $v['parcel_update_status']) !!}</td></tr>
        <tr><td class="sn">4</td><td>Survey Plan Status:</td><td>{!! $ticks($R::SURVEY_PLAN, $v['survey_plan_status']) !!}</td></tr>
        <tr><td class="sn">5</td><td>Spatial/Charting Verification:</td><td>{!! $ticks($R::CHARTING, $v['charting_status']) !!}</td></tr>
    </table>

    <div class="sec">SECTION 6: VERIFICATION CONCLUSION &amp; RECOMMENDATION</div>
    <div class="verdict">
        <p><strong>Final Verification Verdict:</strong></p>
        @foreach ($R::VERDICTS as $key => [$title, $hint])
            <p>{!! $box(($v['verdict'] ?? null) === $key) !!} <strong>{{ $title }}</strong> ({{ $hint }})</p>
        @endforeach
        <p><strong>Detailed Remarks / Conditions:</strong></p>
    </div>
    @php $remarkLines = array_values(array_filter(preg_split('/\r?\n/', (string) ($v['remarks'] ?? '')), fn ($l) => trim($l) !== '')); @endphp
    <table class="lines">
        @for ($i = 0; $i < max(5, count($remarkLines)); $i++)
            <tr><td class="v">{{ $remarkLines[$i] ?? '' }}</td></tr>
        @endfor
    </table>

    <div class="sec">SECTION 7: AUTHORIZATION &amp; SIGN-OFF</div>
    <table class="sign">
        <tr><th class="sn">SN</th><th>Role</th><th>Name &amp; Signature</th><th style="width:110px">Date</th></tr>
        @foreach (array_values($signOff) as $i => $s)
            <tr><td class="sn">{{ $i + 1 }}</td><td>{{ $s['role'] }}</td><td class="v">{{ $s['name'] }}</td><td class="v">{{ $s['date'] }}</td></tr>
        @endforeach
    </table>
</div>
</body>
</html>
