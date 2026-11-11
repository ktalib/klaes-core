{{--
    The I to S on the Survey Department's form (user, 2026-11-10: the paper template, two pages), for Public and
    Private alike. Sheet 1 is the instruction, sheet 2 the Additional Instruction and the surveyor's return of the
    field records (swapped and swapped back, 2026-11-11). Header and both logos as on the Surveyor's Report print. Standalone, no app chrome.

    $d (built by SurveyItsController::print / printPrivate): kind, its_number, job_no, file_number, land_file_number (optional), file_title, plot,
    district, lga, surveyor, firm, rank, road, rail, control, detail (item 1's bold words), instructions, signed_by, post, date, signature, back_url;
    optional: return_place, return_date, records (page 2 return slip, column => ticked), blank (the empty template to fill by hand).
--}}
@php
    $line = fn ($v) => '<span class="fill">' . e($v === null || $v === '' ? ' ' : $v) . '</span>';
    $issued = $d['date'] ? \Illuminate\Support\Carbon::parse($d['date']) : null;
    $place = array_filter([$d['district'], $d['lga']]);   // Location = District, LGA; never the plot
    $blank = (bool) ($d['blank'] ?? false);
    $returned = ($d['return_date'] ?? null) ? \Illuminate\Support\Carbon::parse($d['return_date']) : null;
    $records = (array) ($d['records'] ?? []);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Instruction to Survey {{ $blank ? '(blank)' : $d['its_number'] }}</title>
    <style>
        /* Sized to fill both A4 sheets (user, 2026-11-11: no empty space at the bottom of sheet 1, "Signed by" down
           towards the foot). Sheet 1 is a column the height of the printable page; the signature takes the slack. */
        @page { size: A4; margin: 16mm 18mm 14mm; }
        body { font-family: "Times New Roman", Georgia, serif; font-size: 16px; color: #000; margin: 0; line-height: 1.75; }
        .sheet { max-width: 178mm; margin: 0 auto; }
        .page-one { display: flex; flex-direction: column; min-height: 258mm; }
        .report-header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .report-header td { padding: 0; vertical-align: middle; }
        .report-logo { width: 28mm; }
        .report-logo img { width: 25mm; height: 25mm; object-fit: contain; display: block; }
        .report-logo-right { text-align: right; }
        .report-logo-right img { margin-left: auto; }
        .report-heading { text-align: center; }
        .report-heading h1, .report-heading h2, .report-heading h3 { margin: 0; font-family: Arial, Helvetica, sans-serif; font-weight: bold; line-height: 1.3; }
        .report-heading h1 { font-size: 20px; white-space: nowrap; }
        .report-heading h2 { font-size: 16px; margin-top: 4px; }
        .report-heading h3 { font-size: 19px; margin-top: 10px; }
        .refs { font-size: 13px; color: #444; margin-bottom: 12px; display: flex; justify-content: space-between; }
        .fill { display: inline-block; min-width: 150px; border-bottom: 1px dotted #444; padding: 0 4px; font-weight: bold; line-height: 1.4; }
        .u { text-decoration: underline; }
        .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin: 6px 0 14px; }
        .top .right { text-align: left; min-width: 62mm; }
        .title { text-align: center; margin: 14px 0 10px; }
        .title div { text-decoration: underline; }
        .job { width: 100%; border-collapse: collapse; margin: 6px 0 16px; font-size: 16px; }
        .job td { padding: 3px 6px 3px 0; vertical-align: top; }
        .job td.k { width: 30mm; color: #333; }
        .job td.v { font-weight: bold; border-bottom: 1px dotted #888; }
        ol.items { margin: 0; padding-left: 28px; }
        ol.items > li { margin: 7px 0; padding-left: 8px; text-align: justify; }
        ol.sub { list-style: none; margin: 6px 0; padding-left: 0; }
        ol.sub li { display: flex; gap: 20px; margin: 3px 0; }
        .sig { margin-top: auto; margin-left: 50%; padding: 24px 0 4mm; }
        /* Sheet 2 is a full-height column too, so the KLAES logo (bottom-right, last sheet only; user, 2026-11-11) sits at its foot. */
        .page-two { display: flex; flex-direction: column; min-height: 258mm; break-before: page; page-break-before: always; }
        .page-two .sheet-footer { margin-top: auto; }
        .sheet-footer { padding-top: 4mm; }
        .sheet-footer img { height: 11.5mm; width: auto; object-fit: contain; display: block; margin-left: auto; }
        .sig img { max-height: 56px; display: block; margin-bottom: 2px; }
        .extra { white-space: pre-wrap; min-height: 34mm; margin: 0 0 6px; }
        .rule { border: 0; border-top: 3px solid #000; margin: 8px 0 46px; }
        .return { display: flex; justify-content: space-between; align-items: center; gap: 20px; }
        .return .right { width: 80mm; }
        .return .right p { margin: 16px 0; display: flex; align-items: baseline; gap: 4px; }
        .return .right .fill { flex: 1; min-width: 0; }
        .records-title { margin: 60px 0 32px; font-size: 23px; line-height: 1.5; }
        .records-title div { text-decoration: underline; }
        .records-title .fill { font-size: 17px; }
        .submit { font-weight: bold; margin: 0 0 18px; }
        ol.records { margin: 8px 0 0; padding-left: 44px; }
        ol.records li { margin: 16px 0; padding-left: 16px; }
        .box { display: inline-block; width: 17px; height: 17px; border: 1px solid #000; text-align: center; line-height: 17px; font-size: 14px; font-weight: bold; margin-right: 8px; vertical-align: -3px; }
        .no-print { margin: 10px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <a href="{{ $d['back_url'] }}">Back</a>
    </div>

    {{-- Sheet 1: the instruction, from the header down to "Signed by" at the foot. --}}
    <div class="page-one">
    <table class="report-header">
        <tr>
            <td class="report-logo"><img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Kano State coat of arms"></td>
            <td class="report-heading">
                <h1>MINISTRY OF LAND &amp; PHYSICAL PLANNING</h1>
                <h2>DEPARTMENT OF SURVEY</h2>
                <h3>INSTRUCTION TO SURVEY</h3>
            </td>
            <td class="report-logo report-logo-right"><img src="{{ asset('assets/logo/ministry2.png') }}" alt="Kano State Ministry of Land and Physical Planning seal"></td>
        </tr>
    </table>
    @unless ($blank)<div class="refs"><span>{{ $d['kind'] }} · {{ $d['its_number'] }}{{ $d['job_no'] ? ' · Job No. ' . $d['job_no'] : '' }}</span><span>{{ $issued ? $issued->format('d/m/Y') : '' }}</span></div>@endunless

    <div class="top">
        <div class="u">This Instruction to be filed<br>In the Field Jacket.</div>
        <div class="right">Survey Department<br>PMB 3083 Kano<br>{!! $line($issued ? $issued->format('d/m/Y') : '') !!}</div>
    </div>

    <p style="margin:0;">To: The Field Surveyor{{ $d['surveyor'] || $blank ? ',' : '.' }} @if ($d['surveyor'])<strong>{{ $d['surveyor'] }}</strong>{{ $d['firm'] ? ' (' . $d['firm'] . ')' : '' }}.@elseif ($blank) {!! $line('') !!}@endif</p>

    <div class="title">
        <div>Instruction to Survey.</div>
        <div>R. of O./G. Item No. {!! $line($d['file_number']) !!}</div>
    </div>

    {{-- User, 2026-11-11: no property box and no File Title (the paper goes straight from R. of O./G. Item No. to
         item 1); only the Land File, when one was selected on the form. --}}
    @if ($d['land_file_number'] ?? null)
        <table class="job">
            <tr><td class="k">Land File</td><td class="v">{{ $d['land_file_number'] }}</td></tr>
        </table>
    @endif

    <ol class="items">
        <li>You are instructed to demarcate and survey the above job in accordance with Technical Instructions. Particular attention must be paid to making and adequate tie for Cadastral purpose.
            {{-- The bold words are the job's (user, 2026-11-10); the paper form's own when none was chosen. --}}
            <ol class="sub">
                <li><span>1)</span><span>Where Cadastral Control is available a tie must be made to control <strong class="u">{{ ($d['control'] ?? null) ?: \App\Models\Survey\SurveyIts::CONTROL_CONDITIONS[0] }}.</strong></span></li>
                <li><span>2)</span><span>Where a tie cannot be made within 3 days it is sufficient to tie to two points of detail <strong class="u">{{ ($d['detail'] ?? null) ?: \App\Models\Survey\SurveyIts::DETAIL_CONDITIONS[0] }}</strong> on the topographic map. When such ties are made, the position of the survey must be accurately plotted on a new copy of the Tp Sheet and the points of detail to which ties have been made must be neatly ringed on the map sheet.</span></li>
            </ol>
        </li>
        <li>The road reservation is parallel to and {!! $line($d['road']) !!} from the centre Line of the road.</li>
        <li>The rail reservation is parallel to and {!! $line($d['rail']) !!} from the centre Line of the railway.</li>
        <li>The land to be demarcated is as per the attached plan.</li>
    </ol>

    {{-- Sheet 1 stops at item 4, then "Signed by" (user's marked-up sheet, 2026-11-11), pushed down towards the foot;
         the issue date is the dotted line under PMB 3083 Kano. --}}
    <div class="sig">
        @if ($d['signature'])<img src="{{ $d['signature'] }}" alt="">@endif
        <p style="margin:0;">Signed by {!! $line($d['signed_by']) !!}</p>
        @if ($d['post'])<p style="margin:0;">{{ $d['post'] }}</p>@endif
    </div>
    </div>

    {{-- Sheet 2, laid out as the paper: Additional Instruction | GKN Section, a short gap, the heavy rule, the
         surveyor's block on the right with "To: OI/C Cadastral Section." level with it, then Field Records. The
         KLAES logo sits at the foot of this last sheet only (user, 2026-11-11). --}}
    <div class="page-two">
        <div class="top">
            <div class="u">Additional Instruction</div>
            <div class="right">GKN Section<br>For Surveyor-General.</div>
        </div>
        <div class="extra">{{ $d['instructions'] }}</div>

        <hr class="rule">

        <div class="return">
            <div>To: OI/C Cadastral Section.</div>
            <div class="right">
                <p>Field Surveyors,</p>
                <p>{!! $line($d['surveyor']) !!}</p>
                <p>Rank{!! $line($d['rank'] ?? '') !!}</p>
                <p>Place:{!! $line($d['return_place'] ?? '') !!}</p>
                <p>Date:{!! $line($returned ? $returned->format('d/m/Y') : '') !!}</p>
            </div>
        </div>

        <div class="title records-title">
            <div>Field Records:</div>
            <div>R. of O/G. Item No. @if ($d['file_number'])<strong>{{ $d['file_number'] }}</strong>@elseif ($blank){!! $line('') !!}@endif</div>
        </div>

        <p class="submit">I submit the field Jacket above job Containing.</p>
        {{-- A tick box each (CR 10112026 call), ticked from the form; empty on the blank template. --}}
        <ol class="records">
            @foreach (\App\Models\Survey\SurveyIts::FIELD_RECORDS as $col => $recLabel)
                <li><span class="box">{!! ($records[$col] ?? false) ? '&#10003;' : '&nbsp;' !!}</span> {{ $recLabel }}{{ $loop->last ? '.' : '' }}</li>
            @endforeach
        </ol>
        <div class="sheet-footer"><img src="{{ asset('storage/upload/logo/logo.png') }}" alt="KLAES" onerror="this.style.display='none'"></div>
    </div>
</div>
</body>
</html>
