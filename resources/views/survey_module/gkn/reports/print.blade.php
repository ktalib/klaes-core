{{--
    Surveyor's Report, printed in the layout of docs/SURVEYOR_s_report-1.docx:
    the form's own wording, with the recorded answers on the dotted lines.
    Standalone, no app chrome.
--}}
@php
    use App\Models\Survey\SurveySurveyorReport as SR;
    $privateReport = $privateReport ?? false;
    $r = $report;
    $d = fn ($v) => $v ? $v->format('d/m/Y') : '';
    $line = fn ($v) => '<span class="fill">' . e($v === null || $v === '' ? ' ' : $v) . '</span>';
    $isNot = fn ($b, $yes, $no) => $b === null ? "{$yes}/ {$no}" : ($b ? $yes : $no);
    $isFinal = $privateReport ? $r->isIssued() : $r->isSubmitted();
    $finalDate = $privateReport ? $r->issued_at : $r->submitted_at;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Surveyor's Report {{ $r->report_ref }}</title>
    <style>
        @page { size: A4; margin: 18mm 20mm; }
        /* Larger, to fill the two pages (user, 2026-11-10: "too much gap below"; 13.5px before). */
        /* Larger, to fill the two pages (user, 2026-11-10: "too much gap below"; 13.5px before). */
        /* Larger, to fill the two pages (user, 2026-11-10: "too much gap below"; 13.5px before). */
        /* Larger, to fill the two pages (user, 2026-11-10: "too much gap below"; 13.5px before). */
        /* Larger, to fill the two pages (user, 2026-11-10: "too much gap below"; 13.5px before). */
        body { font-family: "Times New Roman", Georgia, serif; font-size: 15px; color: #000; margin: 0; line-height: 2; }
        .sheet { max-width: 175mm; margin: 0 auto; }
        .report-header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .report-header td { padding: 0; vertical-align: middle; }
        .report-logo { width: 25mm; }
        .report-logo img { width: 22mm; height: 22mm; object-fit: contain; display: block; }
        .report-logo-right { text-align: right; }
        .report-logo-right img { margin-left: auto; }
        .report-heading { text-align: center; }
        .report-heading h1,
        .report-heading h2,
        .report-heading h3 { margin: 0; font-family: Arial, Helvetica, sans-serif; font-weight: bold; line-height: 1.3; }
        .report-heading h1 { font-size: 18px; }
        .report-heading h2 { font-size: 14px; margin-top: 3px; }
        .report-heading h3 { font-size: 16px; margin-top: 8px; }
        .fill { display: inline-block; min-width: 140px; border-bottom: 1px dotted #444; padding: 0 4px; font-weight: bold; }
        .wide .fill { min-width: 70%; }
        p { margin: 2px 0; }
        .report-lines { margin: 2px 0 0 22px; padding: 0; }
        .report-lines > li { padding-left: 3px; margin: 2px 0; }
        .field-records { margin: 2px 0 0 22px; padding: 0; }
        li.missing { color: #888; text-decoration: line-through; }
        .sig { margin-top: 36px; margin-left: 55%; }
        .refs { font-size: 12px; color: #444; margin-bottom: 10px; display: flex; justify-content: space-between; }
        .no-print { margin: 10px 0; }
        @media print { .no-print { display: none; } }
        /* Page 2 is a column the height of the printable page, so the KLAES logo (bottom-right, last page only;
           user, 2026-11-11) sits at its foot. */
        .page-two { break-before: page; page-break-before: always; display: flex; flex-direction: column; min-height: 255mm; }
        .sheet-footer { margin-top: auto; padding-top: 16px; text-align: right; line-height: 0; }
        .sheet-footer img { height: 11.5mm; width: auto; object-fit: contain; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <a href="{{ $privateReport ? route('survey-module.workflow.change-reports.edit', $r) : route('survey-module.gkn.reports.edit', $r) }}">Back</a>
    </div>

    <table class="report-header">
        <tr>
            <td class="report-logo">
                <img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Kano State coat of arms">
            </td>
            <td class="report-heading">
                <h1>MINISTRY OF LAND &amp; PHYSICAL PLANNING</h1>
                <h2>DEPARTMENT OF SURVEY</h2>
                <h3>SURVEYOR'S REPORT</h3>
            </td>
            <td class="report-logo report-logo-right">
                <img src="{{ asset('assets/logo/ministry2.png') }}" alt="Kano State Ministry of Land and Physical Planning seal">
            </td>
        </tr>
    </table>

    <div class="refs">
        <span>{{ $privateReport ? 'Private · ' . $r->report_ref : $r->report_ref . ' · I to S ' . $r->its_number . ($r->job_ref ? ' · Job ' . $r->job_ref : '') }}</span>
        <span>{{ $isFinal && $finalDate ? ($privateReport ? 'Issued ' : 'Submitted ') . $finalDate->format('d M Y') : '' }}</span>
    </div>
    <ol class="report-lines">
        <li>File Number {!! $line($r->file_number) !!}</li>
        <li>Name Of Grantee {!! $line($r->grantee_name) !!}</li>
        <li>Location {!! $line(trim(($r->plot_no ? 'Plot ' . $r->plot_no . ', ' : '') . ($r->location ?? ''), ', ')) !!}</li>
        <li>District {!! $line($r->district) !!}</li>
        <li>The Survey Is Done In Accordance With Technical Instruction.</li>
        <li>The origin of local grid for the survey is {!! $line($r->grid_origin) !!}</li>
        <li>The three control points {!! $line($r->control_points) !!} are in situ.</li>
        <li>The boundaries of the surveyed plot were pointed out by {!! $line($r->boundaries_pointed_by) !!} on date {!! $line($d($r->boundaries_pointed_on)) !!}</li>
        <li>Survey commenced on date {!! $line($d($r->commenced_on)) !!} and completed on date {!! $line($d($r->completed_on)) !!}</li>
        <li>Distance travelled {!! $line($r->distance_km !== null ? rtrim(rtrim(number_format((float) $r->distance_km, 2), '0'), '.') : null) !!} kms.</li>
        <li>Computation were done on {!! $line($r->computed_on) !!}</li>
        <li>Delay (if any) in completion of survey is due to {!! $line($r->delay_reason) !!}</li>
        <li>
            The plot {{ $isNot($r->on_layout_plan, 'is', 'is not') }} on an approved lay-out plan No. {!! $line($r->layout_plan_no) !!}.
            And the corners of the plot {{ $isNot($r->corners_demarcated, 'were', 'were not') }} demarcated on ground before.
        </li>
        <li>Hard by {!! $line($r->hard_by) !!}</li>
        <li>The plot is surveyed strictly as on approved site plan in file.</li>
        <li>{{ $r->encroachment ? 'Encroachment noticed: ' : 'There are no encroachment noticed.' }}@if ($r->encroachment) {!! $line($r->encroachment_details) !!} @endif</li>
        <li>
            The plot {{ $isNot($r->developed, 'is', 'is not') }} developed with {!! $line($r->developed_with) !!}.
            Survey beacons {!! $line($r->beacons_not_emplaced) !!} were not emplaced on ground due to {!! $line($r->beacons_reason) !!}
        </li>
        <li>The boundaries of previous KN {!! $line($r->previous_kn) !!} {{ $r->previous_kn_coincides === false ? 'do not coincide' : 'coincide' }} with the present survey.</li>
        <li>{{ SR::ROAD_RESERVATIONS }}</li>
        <li>Transport was provided by {{ $r->transport_by ? strtolower(SR::TRANSPORT[$r->transport_by] ?? $r->transport_by) : 'applicant/ ministry' }}.</li>
    </ol>

    {{-- Page 2 starts at 21, Remarks (user, 2026-11-10: the sheet in 2 pages). --}}
    <div class="page-two">
    <ol class="report-lines" start="21">
        <li>Any other remarks:- {!! $line($r->remarks) !!}</li>
        <li>
            In the field jacket, the following field records are numbered and filed in that order:
            <ol class="field-records">
                @foreach (SR::FIELD_RECORDS as $k => $label)
                    <li class="{{ in_array($k, (array) $r->field_jacket_checklist, true) ? '' : 'missing' }}">{{ $label }}.</li>
                @endforeach
            </ol>
        </li>
    </ol>

    <div class="sig">
        <p>Surveyor's signature …………………………</p>
        <p>Name:- <strong>{{ $r->surveyor_name }}</strong></p>
        <p>Date:- <strong>{{ $d($r->report_date) }}</strong></p>
    </div>
    <div class="sheet-footer"><img src="{{ asset('storage/upload/logo/logo.png') }}" alt="KLAES" onerror="this.style.display='none'"></div>
    </div>
</div>
</body>
</html>
