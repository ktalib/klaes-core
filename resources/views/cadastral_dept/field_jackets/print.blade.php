{{--
    Computation File (Field Jacket) cover, for the front of the small survey file. Same family as
    the FMC and charting card prints: standalone, no sidebar, black on white.
    The QR code opens the file's page in the Cadastral Department workflow.
--}}
@php
  $job = $jacket->job ?? (new \App\Models\Cadastral\CadastralSurveyJob())->forceFill(['status' => 'No job yet']);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Computation File (Field Jacket) {{ $jacket->field_jacket_no }} · {{ $jacket->file_number }}</title>
    <style>
        @page { size: A5 landscape; margin: 10mm; }
        body { font-family: "Times New Roman", Georgia, serif; font-size: 12px; color: #000; margin: 0; }
        .sheet { max-width: 190mm; margin: 0 auto; border: 2px solid #000; padding: 8mm; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
        .head h1 { font-size: 15px; margin: 0; letter-spacing: .06em; text-transform: uppercase; }
        .head h2 { font-size: 12px; margin: 2px 0 0; font-weight: normal; }
        .head h3 { font-size: 18px; margin: 8px 0 0; letter-spacing: .12em; text-transform: uppercase; }
        .head img { width: 30mm; height: 30mm; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; vertical-align: top; }
        .label { width: 22%; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #333; }
        .value { border-bottom: 1px dotted #666; font-weight: bold; }
        .big { font-size: 16px; font-family: "Courier New", monospace; }
        .foot { margin-top: 10px; display: flex; justify-content: space-between; font-size: 9px; color: #444; }
        .no-print { margin: 10px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">Print</button>
    <a href="{{ route('cadastral-dept.field-jackets.show', $jacket->id) }}">Back</a>
</div>
<div class="sheet">
    <div class="head">
        <div>
            <h1>Kano State Ministry of Land and Physical Planning</h1>
            <h2>Cadastral Department · Plans and Descriptions</h2>
            <h3>Computation File (Field Jacket)</h3>
        </div>
        @if ($qr)<img src="{{ $qr }}" alt="QR code to the file's page" />@endif
    </div>

    <table>
        <tr>
            <td class="label">Computation File (Field Jacket) No.</td><td class="value big">{{ $jacket->field_jacket_no }}</td>
            <td class="label">Survey Job No.</td><td class="value big">{{ $job->job_number }}</td>
        </tr>
        <tr>
            <td class="label">File No.</td><td class="value">{{ $jacket->file_number }}</td>
            <td class="label">I to S No.</td><td class="value">{{ $job->its_number ?: '—' }}</td>
        </tr>
        <tr><td class="label">File Title</td><td class="value" colspan="3">{{ $jacket->file_title ?: '—' }}</td></tr>
        {{-- Attached to the correspondence file when commissioned (change request 26-10-2026). --}}
        <tr><td class="label">Correspondence File</td><td class="value" colspan="3">{{ trim(($jacket->correspondence_fileno ?? '') . (($jacket->shadow_fileno ?? null) ? ' · ' . $jacket->shadow_fileno : '')) ?: 'Not attached' }}</td></tr>
        <tr>
            <td class="label">Plot No.</td><td class="value">{{ $jacket->plot_no ?: '—' }}</td>
            <td class="label">District</td><td class="value">{{ $jacket->district ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">LGA</td><td class="value">{{ $jacket->lga ?: '—' }}</td>
            <td class="label">Location</td><td class="value">{{ $jacket->location ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Surveyor</td><td class="value">{{ $job->surveyor_name ?: '—' }}</td>
            <td class="label">Firm</td><td class="value">{{ $job->firm_name ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Commissioned</td><td class="value">{{ $jacket->commissioned_at->format('d M Y') }}</td>
            <td class="label">Case</td><td class="value">{{ $jacket->case->case_ref }}</td>
        </tr>
    </table>

    <div class="foot">
        <span>Printed {{ now()->format('d M Y H:i') }}</span>
        <span>{{ $jacket->field_jacket_no }} · {{ $job->job_number }}</span>
    </div>
</div>
</body>
</html>
