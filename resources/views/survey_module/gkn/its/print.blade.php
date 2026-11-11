{{--
    Instruction to Survey, Survey Department (government): the printed instrument.
    Two pages, as the Department's own form (photographs itsfront / itsback, user 2026-11-10):
      page 1 - the instruction to the field surveyor, with the job's details card above it;
      page 2 - Additional Instruction, and the Field Records return to the OI/C Cadastral Section.
    The private I to S stays Cadastral's (cadastral_module/information/its_print.blade.php).

    Standalone, no app chrome: this is signed and sent out.
--}}
@php
    $jobNo = $computationFile?->job_no ?: $its->job_ref;
    $issued = optional($its->its_issued_at)->format('d F Y') ?: now()->format('d F Y');
    $blank = '<span class="fill">&nbsp;</span>';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Instruction to Survey {{ $its->its_number }}</title>
    <style>
        @page { size: A4; margin: 16mm 18mm; }

        body { font-family: "Times New Roman", Georgia, serif; font-size: 13.5px; color: #000; margin: 0; line-height: 1.6; }
        .page { max-width: 178mm; margin: 0 auto; position: relative; }
        .page + .page { page-break-before: always; margin-top: 28px; }
        @media screen { .page { border: 1px solid #ddd; padding: 14mm 12mm; margin-bottom: 18px; background: #fff; } body { background: #f2f2f2; } }

        u, .u { text-decoration: underline; }
        .top { display: flex; justify-content: space-between; align-items: flex-start; font-size: 12.5px; margin-bottom: 18px; }
        .top .right { text-align: left; }

        /* The job's details: the "above job" the instruction refers to. */
        table.card { width: 100%; border-collapse: collapse; margin: 0 0 18px; font-size: 12px; border: 1px solid #000; }
        table.card td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        table.card .k { width: 19%; font-size: 10.5px; text-transform: uppercase; letter-spacing: .03em; background: #f4f4f4; }
        table.card .v { font-weight: bold; }

        .to { margin: 8px 0 18px; }
        .title { text-align: center; margin: 0 0 18px; line-height: 1.5; }
        .title div { text-decoration: underline; }

        ol.clauses { margin: 0; padding-left: 26px; }
        ol.clauses > li { margin-bottom: 10px; padding-left: 8px; text-align: justify; }
        ol.sub { list-style: none; margin: 6px 0 0; padding-left: 0; }
        ol.sub li { margin-bottom: 6px; display: flex; gap: 12px; }
        ol.sub li span:first-child { flex: 0 0 22px; }
        .fill { display: inline-block; min-width: 38mm; border-bottom: 1px dotted #000; }

        .signed { margin-top: 46px; margin-left: 55%; }
        .signed .rule { border-top: 1px solid #000; padding-top: 4px; font-size: 12px; }

        .rec-head { border-top: 3px solid #000; margin: 4px 0 18px; }
        .rec { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 28px; }
        .fs { width: 45%; font-size: 13px; }
        .fs div { margin-bottom: 6px; display: flex; gap: 6px; }
        .fs .line { flex: 1; border-bottom: 1px dotted #000; min-height: 18px; font-weight: bold; }
        .addl { white-space: pre-wrap; text-align: justify; min-height: 22mm; margin: 6px 0 14px; }
        ol.items { margin: 6px 0 0; padding-left: 26px; }
        ol.items li { margin-bottom: 4px; padding-left: 10px; }

        .foot { margin-top: 26px; border-top: 1px solid #999; padding-top: 5px; font-size: 10px; color: #444; }
        .no-print { max-width: 178mm; margin: 10px auto; }
        @media print { .no-print { display: none; } body { background: #fff; } }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">Print</button>
    <a href="{{ route('survey-module.gkn.its.edit', $its) }}">Back</a>
</div>

{{-- Page 1: Instruction to Survey. --}}
<div class="page">
    <div class="top">
        <div><u>This Instruction to be filed<br />In the Field Jacket.</u></div>
        <div class="right">Survey Department<br />PMB 3083 Kano</div>
    </div>

    {{-- The job's details (Plot No., District, LGA, Location in that order). --}}
    <table class="card">
        <tr>
            <td class="k">I to S No.</td><td class="v">{{ $its->its_number }}</td>
            <td class="k">Job No.</td><td class="v">{{ $jobNo ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">File No.</td><td class="v">{{ $its->file_number ?: '—' }}</td>
            <td class="k">File Type</td><td class="v">{{ $its->file_type ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">File Title</td><td class="v" colspan="3">{{ $its->file_title ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">Plot No.</td><td class="v">{{ $its->plot_no ?: '—' }}</td>
            <td class="k">District</td><td class="v">{{ $its->district ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">LGA</td><td class="v">{{ $its->lga ?: '—' }}</td>
            <td class="k">Location</td><td class="v">{{ $its->location ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">Computation File</td><td class="v">{{ $computationFile?->computation_no ?: ($its->computation_no ?: '—') }}</td>
            <td class="k">Survey Type</td><td class="v">{{ $its->survey_type ?: '—' }}</td>
        </tr>
        <tr>
            <td class="k">Beacon Nos.</td><td class="v" colspan="3">{{ implode(', ', $beaconList ?? []) ?: '—' }}</td>
        </tr>
    </table>

    <div class="to">To: The Field Surveyor.@if ($its->surveyor_name) <strong>{{ $its->surveyor_name }}</strong>{{ $its->surveyor_rank ? ', ' . $its->surveyor_rank : '' }}@endif</div>

    <div class="title">
        <div>Instruction to Survey.</div>
        <div>R. of O./G. Item No. {!! $blank !!}</div>
    </div>

    <ol class="clauses">
        <li>
            You are instructed to demarcate and survey the above job in accordance with Technical Instructions.
            Particular attention must be paid to making an adequate tie for Cadastral purpose.
            <ol class="sub">
                <li><span>1)</span><span>Where Cadastral Control is available a tie must be made to control <strong><u>prove to be in situ.</u></strong></span></li>
                <li><span>2)</span><span>Where a tie cannot be made within 3 days it is sufficient to tie to two points of detail
                    <u>Identifiable</u> on the topographic map. When such ties are made, the position of the survey must be
                    accurately plotted on a new copy of the Tp Sheet and the points of detail to which ties have been made
                    must be neatly ringed on the map sheet.</span></li>
            </ol>
        </li>
        <li>The road reservation is parallel to and {!! $blank !!} from the centre Line of the road.</li>
        <li>The rail reservation is parallel to and {!! $blank !!} from the centre Line of the railway.</li>
        <li>The land to be demarcated is as per the attached plan.</li>
    </ol>

    <div class="signed">
        <div class="rule">
            Signed by: {{ $its->its_issued_by ?: '' }}<br />
            Survey Department<br />
            {{ $issued }}
        </div>
    </div>

    <div class="foot">Instruction {{ $its->its_number }} · Job {{ $jobNo }} · Page 1 of 2 · Printed {{ now()->format('d M Y H:i') }}</div>
</div>

{{-- Page 2: Additional Instruction and the Field Records return. --}}
<div class="page">
    <div class="top">
        <div><u>Additional Instruction</u></div>
        <div class="right">GKN Section<br />For Surveyor-General.</div>
    </div>
    <div class="addl">{{ $its->its_instructions }}</div>

    <div class="rec-head"></div>

    <div class="rec">
        <div style="padding-bottom:30px;">To: OI/C Cadastral Section.</div>
        <div class="fs">
            <div>Field Surveyors, <span class="line">{{ $its->surveyor_name }}</span></div>
            <div>Rank <span class="line">{{ $its->surveyor_rank }}</span></div>
            <div>Place <span class="line"></span></div>
            <div>Date <span class="line"></span></div>
        </div>
    </div>

    <div class="title" style="font-size:16px;">
        <div>Field Records:</div>
        <div>R. of O/G. Item No. {!! $blank !!}</div>
    </div>

    <p>I submit the field Jacket above job Containing.</p>
    <ol class="items">
        <li>Report</li>
        <li>Survey Diagram</li>
        <li>Field Books</li>
        <li>Computation Sheets</li>
        <li>Surveyor's Plan.</li>
    </ol>

    <div class="foot">Instruction {{ $its->its_number }} · Job {{ $jobNo }} · Page 2 of 2</div>
</div>

</body>
</html>
