{{--
    File Movement Card, print-on-demand. Copied from the old Cadastral Module's
    index card print, with the QR code and the movement log added.
    Standalone: printed and filed, so no sidebar and no colour that costs toner.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>FMC {{ $fmc->fmc_no }} · {{ $fmc->file_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: "Times New Roman", Georgia, serif; font-size: 12px; color: #000; margin: 0; }
        .sheet { max-width: 190mm; margin: 0 auto; }
        .head { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
        .head .titles { flex: 1; text-align: center; }
        .head h1 { font-size: 15px; margin: 0; letter-spacing: .06em; text-transform: uppercase; }
        .head h2 { font-size: 12px; margin: 2px 0 0; font-weight: normal; }
        .head .qr { text-align: center; font-size: 8px; }
        .head .qr img { width: 30mm; height: 30mm; display: block; }
        table { width: 100%; border-collapse: collapse; }
        .fields td { padding: 4px 6px; vertical-align: top; }
        .fields .label { width: 18%; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #333; }
        .fields .value { border-bottom: 1px dotted #666; font-weight: bold; }
        .log { margin-top: 12px; }
        .log caption { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; padding-bottom: 3px; }
        .log th, .log td { border: 1px solid #000; padding: 3px 5px; font-size: 10px; text-align: left; vertical-align: top; }
        .log th { background: #eee; }
        .foot { margin-top: 12px; display: flex; justify-content: space-between; font-size: 9px; color: #444; }
        .no-print { margin: 10px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('cadastral-dept.cases.fmc.show', $case->id) }}">Back</a>
    </div>

    <div class="head">
        <div class="titles">
            <h1>Kano State Ministry of Land and Physical Planning</h1>
            <h2>Cadastral Department · File Movement Card</h2>
        </div>
        @if ($qr)
            <div class="qr"><img src="{{ $qr }}" alt="QR">Scan to log in / out</div>
        @endif
    </div>

    {{-- Property details in the standard order: Plot No., District, LGA, Location. --}}
    <table class="fields">
        <tr>
            <td class="label">File Number</td><td class="value">{{ $fmc->file_number }}</td>
            <td class="label">FMC No.</td><td class="value">{{ $fmc->fmc_no }}</td>
        </tr>
        <tr>
            <td class="label">File Title</td><td class="value" colspan="3">{{ $fmc->file_title ?: '—' }}</td>
        </tr>
        @if ($fmc->allottee_name && $fmc->allottee_name !== $fmc->file_title)
            <tr><td class="label">Allottee</td><td class="value" colspan="3">{{ $fmc->allottee_name }}</td></tr>
        @endif
        <tr>
            <td class="label">Plot No.</td><td class="value">{{ $fmc->plot_no ?: '—' }}</td>
            <td class="label">District</td><td class="value">{{ $fmc->district ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">LGA</td><td class="value">{{ $fmc->lga ?: '—' }}</td>
            <td class="label">Location</td><td class="value">{{ $fmc->location ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Block No.</td><td class="value">{{ $fmc->block_no ?: '—' }}</td>
            <td class="label">Layout No.</td><td class="value">{{ $fmc->layout_no ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Commissioned</td>
            <td class="value" colspan="3">{{ $fmc->commissioned_at->format('d M Y H:i') }} · {{ optional($fmc->commissioner)->name }}</td>
        </tr>
    </table>

    <table class="log">
        <caption>Movement log</caption>
        <thead>
            <tr><th style="width:5%">S/N</th><th style="width:16%">Date &amp; time</th><th style="width:20%">Unit</th><th style="width:22%">Movement</th><th>Reason</th><th style="width:15%">By</th></tr>
        </thead>
        <tbody>
            @foreach ($timeline as $row)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $row['at']->format('d M Y H:i') }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td>{{ $row['event'] }}</td>
                    <td>{{ $row['reason'] }}</td>
                    <td>{{ $row['by'] }}</td>
                </tr>
            @endforeach
            {{-- Ruled blank lines for movements written by hand. --}}
            @for ($i = 0; $i < max(4, 12 - count($timeline)); $i++)
                <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
    </table>

    <div class="foot">
        <span>{{ $case->case_ref }} · {{ $def->workflows()[$case->workflow]['label'] ?? $case->workflow }}</span>
        <span>Printed {{ now()->format('d M Y H:i') }} · copy {{ $fmc->print_count }}</span>
    </div>
</div>
</body>
</html>
