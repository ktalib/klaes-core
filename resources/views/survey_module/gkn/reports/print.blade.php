{{--
    Surveyor's Report, printed in the layout of docs/SURVEYOR_s_report-1.docx:
    the form's own wording, with the recorded answers on the dotted lines.
    Standalone, no app chrome.
--}}
@php
    use App\Models\Survey\SurveySurveyorReport as SR;
    $r = $report;
    $d = fn ($v) => $v ? $v->format('d/m/Y') : '';
    $line = fn ($v) => '<span class="fill">' . e($v === null || $v === '' ? ' ' : $v) . '</span>';
    $isNot = fn ($b, $yes, $no) => $b === null ? "{$yes}/ {$no}" : ($b ? $yes : $no);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Surveyor's Report {{ $r->report_ref }}</title>
    <style>
        @page { size: A4; margin: 18mm 20mm; }
        body { font-family: "Times New Roman", Georgia, serif; font-size: 13.5px; color: #000; margin: 0; line-height: 1.9; }
        .sheet { max-width: 175mm; margin: 0 auto; }
        h1 { text-align: center; font-size: 17px; letter-spacing: .08em; margin: 0 0 14px; text-decoration: underline; }
        .fill { display: inline-block; min-width: 140px; border-bottom: 1px dotted #444; padding: 0 4px; font-weight: bold; }
        .wide .fill { min-width: 70%; }
        p { margin: 2px 0; }
        ol { margin: 4px 0 0 22px; padding: 0; }
        li.missing { color: #888; text-decoration: line-through; }
        .sig { margin-top: 36px; margin-left: 55%; }
        .refs { font-size: 11px; color: #444; margin-bottom: 10px; display: flex; justify-content: space-between; }
        .draft { text-align: center; color: #b91c1c; font-weight: bold; letter-spacing: .2em; }
        .no-print { margin: 10px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('survey-module.gkn.reports.edit', $r) }}">Back</a>
    </div>

    <div class="refs">
        <span>{{ $r->report_ref }} · I to S {{ $r->its_number }}{{ $r->job_ref ? ' · Job ' . $r->job_ref : '' }}</span>
        <span>{{ $r->isSubmitted() ? 'Submitted ' . $r->submitted_at->format('d M Y') : '' }}</span>
    </div>
    @unless ($r->isSubmitted())<p class="draft">DRAFT</p>@endunless

    <h1>SURVEYOR'S REPORT</h1>

    <p class="wide">File Number {!! $line($r->file_number) !!}</p>
    <p class="wide">Name Of Grantee {!! $line($r->grantee_name) !!}</p>
    <p class="wide">Location {!! $line(trim(($r->plot_no ? 'Plot ' . $r->plot_no . ', ' : '') . ($r->location ?? ''), ', ')) !!}</p>
    <p class="wide">District {!! $line($r->district) !!}</p>

    <p>The Survey Is Done In Accordance With Technical Instruction.</p>
    <p>The origin of local grid for the survey is {!! $line($r->grid_origin) !!}</p>
    <p>The three control points {!! $line($r->control_points) !!} are in situ.</p>
    <p>The boundaries of the surveyed plot were pointed out by {!! $line($r->boundaries_pointed_by) !!} on date {!! $line($d($r->boundaries_pointed_on)) !!}</p>
    <p>Survey commenced on date {!! $line($d($r->commenced_on)) !!} and completed on date {!! $line($d($r->completed_on)) !!}</p>
    <p>Distance travelled {!! $line($r->distance_km !== null ? rtrim(rtrim(number_format((float) $r->distance_km, 2), '0'), '.') : null) !!} kms.</p>
    <p>Computation were done on {!! $line($r->computed_on) !!}</p>
    <p class="wide">Delay (if any) in completion of survey is due to {!! $line($r->delay_reason) !!}</p>
    <p>The plot {{ $isNot($r->on_layout_plan, 'is', 'is not') }} on an approved lay-out plan No. {!! $line($r->layout_plan_no) !!}</p>
    <p>And the corners of the plot {{ $isNot($r->corners_demarcated, 'were', 'were not') }} demarcated on ground before.</p>
    <p class="wide">Hard by {!! $line($r->hard_by) !!}</p>
    <p>The plot is surveyed strictly as on approved site plan in file.</p>
    <p>{{ $r->encroachment ? 'Encroachment noticed: ' : 'There are no encroachment noticed.' }}@if ($r->encroachment) {!! $line($r->encroachment_details) !!} @endif</p>
    <p>The plot {{ $isNot($r->developed, 'is', 'is not') }} developed with {!! $line($r->developed_with) !!}</p>
    <p>Survey beacons {!! $line($r->beacons_not_emplaced) !!} were not emplaced on ground due to {!! $line($r->beacons_reason) !!}</p>
    <p>The boundaries of previous KN {!! $line($r->previous_kn) !!} {{ $r->previous_kn_coincides === false ? 'do not coincide' : 'coincide' }} with the present survey.</p>
    <p>{{ SR::ROAD_RESERVATIONS }}</p>
    <p>Transport was provided by {{ $r->transport_by ? strtolower(SR::TRANSPORT[$r->transport_by] ?? $r->transport_by) : 'applicant/ ministry' }}.</p>
    <p class="wide">Any other remarks:- {!! $line($r->remarks) !!}</p>

    <p style="margin-top:10px;">In the field jacket, the following field records are numbered and filed in that order:</p>
    <ol>
        @foreach (SR::FIELD_RECORDS as $k => $label)
            <li class="{{ in_array($k, (array) $r->field_jacket_checklist, true) ? '' : 'missing' }}">{{ $label }}.</li>
        @endforeach
    </ol>

    <div class="sig">
        <p>Surveyor's signature …………………………</p>
        <p>Name:- <strong>{{ $r->surveyor_name }}</strong></p>
        <p>Date:- <strong>{{ $d($r->report_date) }}</strong></p>
    </div>
</div>
</body>
</html>
