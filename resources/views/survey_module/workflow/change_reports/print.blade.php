{{-- Surveyors' Report (Changes), printed. Standalone, no app chrome. --}}
@php
    $r = $report;
    $changes = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $r->required_changes))));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Surveyors' Report (Changes) {{ $r->report_ref }}</title>
    <style>
        @page { size: A4; margin: 20mm; }
        body { font-family: "Times New Roman", Georgia, serif; font-size: 13.5px; color: #000; margin: 0; line-height: 1.6; }
        .sheet { max-width: 175mm; margin: 0 auto; }
        .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
        .head h1 { font-size: 16px; margin: 0; text-transform: uppercase; letter-spacing: .06em; }
        .head h2 { font-size: 13px; margin: 3px 0 0; font-weight: normal; }
        .head h3 { font-size: 14px; margin: 10px 0 0; text-transform: uppercase; letter-spacing: .08em; }
        .refs { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 12px; }
        table.f { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.f td { padding: 4px 6px; vertical-align: top; }
        table.f .l { width: 26%; font-size: 11px; text-transform: uppercase; color: #333; }
        table.f .v { border-bottom: 1px dotted #666; font-weight: bold; }
        h4 { margin: 14px 0 4px; font-size: 13px; text-transform: uppercase; }
        .body { white-space: pre-wrap; text-align: justify; }
        .sig { margin-top: 40px; width: 45%; border-top: 1px solid #000; padding-top: 4px; font-size: 11px; }
        .draft { text-align: center; color: #b91c1c; font-weight: bold; letter-spacing: .2em; }
        .no-print { margin: 10px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('survey-module.workflow.change-reports.edit', $r) }}">Back</a>
    </div>
    @unless ($r->isIssued())<p class="draft">DRAFT</p>@endunless

    <div class="head">
        <h1>Kano State Ministry of Land and Physical Planning</h1>
        <h2>Survey Department · Examination / Verification Unit</h2>
        <h3>Surveyors' Report (Changes)</h3>
    </div>

    <div class="refs">
        <span><strong>Report No.:</strong> {{ $r->report_ref }}</span>
        <span><strong>Register File:</strong> {{ $r->registerFile?->register_ref ?: '—' }}</span>
        <span><strong>Date:</strong> {{ optional($r->report_date)->format('d F Y') ?: '' }}</span>
    </div>

    <table class="f">
        <tr><td class="l">File Number</td><td class="v">{{ $r->file_number }}</td><td class="l">Plan No.</td><td class="v">{{ $r->plan_no ?: '—' }}</td></tr>
        <tr><td class="l">File Title</td><td class="v" colspan="3">{{ $r->file_title ?: '—' }}</td></tr>
        <tr><td class="l">Plot No.</td><td class="v">{{ $r->plot_no ?: '—' }}</td><td class="l">District</td><td class="v">{{ $r->district ?: '—' }}</td></tr>
        <tr><td class="l">LGA</td><td class="v">{{ $r->lga ?: '—' }}</td><td class="l">Surveyor</td><td class="v">{{ $r->surveyor_name ?: '—' }}</td></tr>
    </table>

    <p>On examination, the submitted survey plan was found not to agree with the existing records, as follows.</p>

    <h4>Discrepancies</h4>
    <div class="body">{{ $r->discrepancies }}</div>

    <h4>Changes required</h4>
    <ol>@foreach ($changes as $c)<li>{{ $c }}</li>@endforeach</ol>

    <p>The plan is to be corrected accordingly and resubmitted for examination.</p>

    <div class="sig">{{ $r->examined_by ?: '' }}<br />Examination / Verification Unit, Survey Department</div>
</div>
</body>
</html>
