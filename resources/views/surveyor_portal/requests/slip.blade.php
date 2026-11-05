@php
    use App\Models\Survey\SurveyBeaconRegister as Register;

    $numbers = $request->lines->flatMap(fn ($line) => $line->numbers)->map(fn ($n) => Register::display($n->beacon_no))->values();
    $issued = optional($request->lines->first()?->issue_date ?? $request->reviewed_at)->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Beacon Slip {{ $request->request_no }}</title>
    <style>
        @page { size: A4; margin: 16mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; font-size: 13px; margin: 0; }
        .head { text-align: center; border-bottom: 2px solid #0B3A7E; padding-bottom: 10px; margin-bottom: 14px; }
        .head img { height: 70px; width: auto; }
        .head h1 { font-size: 18px; margin: 8px 0 0; letter-spacing: .03em; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #F0F2F5; font-size: 12px; }
        .label { width: 22%; background: #F0F2F5; font-weight: 600; font-size: 12px; }
        .numbers { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px 12px; border: 1px solid #999; padding: 10px; margin-bottom: 18px; font-weight: 600; }
        .bar { text-align: center; margin: 10px 0 18px; }
        .bar button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
        .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 36px; }
        .sign div { border-top: 1px solid #111; padding-top: 4px; font-size: 12px; }
        @media print { .bar { display: none; } }
    </style>
</head>
<body>
    <div class="bar"><button type="button" onclick="window.print()">Print</button></div>
    <div class="head">
        <img src="{{ asset('assets/logo/kssgof-wordmark.png') }}" alt="KSSGOF">
        <h1>BEACON ISSUE SLIP</h1>
    </div>
    <table>
        <tr><td class="label">Request No.</td><td>{{ $request->request_no }}</td><td class="label">Date Issued</td><td>{{ $issued }}</td></tr>
        <tr><td class="label">Type</td><td>{{ $request->type_label }}</td><td class="label">Number of Beacons</td><td>{{ $numbers->count() }}</td></tr>
        <tr><td class="label">Company</td><td>{{ $request->company_name ?: '—' }}</td><td class="label">Surveyor's Name</td><td>{{ $request->surveyor_name }}</td></tr>
        <tr><td class="label">Name of Survey</td><td colspan="3">{{ $request->job_name ?: '—' }}</td></tr>
        <tr><td class="label">Plot No.</td><td>{{ $request->plot_no ?: '—' }}</td><td class="label">District</td><td>{{ $request->district ?: '—' }}</td></tr>
        <tr><td class="label">LGA</td><td>{{ $request->lga ? mb_strtoupper($request->lga) : '—' }}</td><td class="label">Location</td><td>{{ $request->location ?: '—' }}</td></tr>
    </table>
    <strong>Beacon Numbers</strong>
    <div class="numbers" style="margin-top:6px;">
        @foreach ($numbers as $no)<span>{{ $no }}</span>@endforeach
    </div>
    <div class="sign">
        <div>Surveyor General</div>
        <div>Received by</div>
    </div>
</body>
</html>
