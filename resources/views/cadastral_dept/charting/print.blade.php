{{--
    Charting card, print-on-demand, for the file jacket. Same family as the
    File Movement Card print: standalone, no sidebar, no colour that costs toner.
--}}
@php $yesNo = fn ($v) => $v === null ? '—' : ($v ? 'Yes' : 'No'); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Charting Card {{ $card->charting_no }} · {{ $case->file_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font-family: "Times New Roman", Georgia, serif; font-size: 12px; color: #000; margin: 0; }
        .sheet { max-width: 190mm; margin: 0 auto; }
        .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 10px; }
        .head h1 { font-size: 15px; margin: 0; letter-spacing: .06em; text-transform: uppercase; }
        .head h2 { font-size: 12px; margin: 2px 0 0; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; }
        .fields td { padding: 4px 6px; vertical-align: top; }
        .fields .label { width: 18%; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #333; }
        .fields .value { border-bottom: 1px dotted #666; font-weight: bold; }
        .grid { margin-top: 12px; }
        .grid caption { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; padding-bottom: 3px; }
        .grid th, .grid td { border: 1px solid #000; padding: 3px 5px; font-size: 10px; text-align: left; }
        .grid th { background: #eee; }
        .foot { margin-top: 12px; display: flex; justify-content: space-between; font-size: 9px; color: #444; }
        .no-print { margin: 10px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('cadastral-dept.charting.show', $card->id) }}">Back</a>
    </div>

    <div class="head">
        <h1>Kano State Ministry of Land and Physical Planning</h1>
        <h2>Cadastral Department · Charting Card · {{ $card->stageLabel() }}</h2>
    </div>

    {{-- Property details in the standard order: Plot No., District, LGA, Location. --}}
    <table class="fields">
        <tr><td class="label">Charting No.</td><td class="value">{{ $card->charting_no }} (v{{ $card->version }})</td>
            <td class="label">File Number</td><td class="value">{{ $case->file_number }}</td></tr>
        <tr><td class="label">File Title</td><td class="value" colspan="3">{{ $case->file_title ?: '—' }}</td></tr>
        <tr><td class="label">Plot No.</td><td class="value">{{ $case->plot_no ?: '—' }}</td>
            <td class="label">District</td><td class="value">{{ $case->district ?: '—' }}</td></tr>
        <tr><td class="label">LGA</td><td class="value">{{ $case->lga ?: '—' }}</td>
            <td class="label">Location</td><td class="value">{{ $case->location ?: '—' }}</td></tr>
        @if ($card->stage === 'check')
            <tr><td class="label">Matches record</td><td class="value">{{ $yesNo($card->matches_record) }}</td>
                <td class="label">Note</td><td class="value">{{ $card->match_note ?: '—' }}</td></tr>
        @else
            @if ($card->stage === 'second')
                <tr><td class="label">Agrees with 1st</td><td class="value">{{ $yesNo($card->agrees_with_first) }}</td>
                    <td class="label">Difference</td><td class="value">{{ $card->agree_note ?: '—' }}</td></tr>
            @endif
            <tr><td class="label">Charted</td><td class="value">{{ $yesNo($card->charted) }}</td>
                <td class="label">If not, why</td><td class="value">{{ $card->not_charted_reason ?: '—' }}</td></tr>
            <tr><td class="label">On existing plot</td><td class="value">{{ $yesNo($card->falls_on_existing) }}{{ $card->overlap_extent ? ' (' . $card->overlap_extent . ')' : '' }}</td>
                <td class="label">Existing file(s)</td><td class="value">{{ $card->existing_files ?: '—' }}</td></tr>
        @endif
        <tr><td class="label">Description</td><td class="value" colspan="3">{{ $card->description ?: '—' }}</td></tr>
        <tr><td class="label">Sheet</td><td class="value">{{ $card->sheet_type ? ucfirst($card->sheet_type) : '—' }} {{ $card->sheet_no }}</td>
            <td class="label">Layout</td><td class="value">{{ $card->layout ?: '—' }}</td></tr>
        <tr><td class="label">Plan / TP No.</td><td class="value">{{ $card->plan_no ?: '—' }}</td>
            <td class="label">Area</td><td class="value">{{ $card->area_sqm ? number_format($card->area_sqm, 1) . ' m²' : '—' }}</td></tr>
        <tr><td class="label">Remarks</td><td class="value" colspan="3">{{ $card->remarks ?: '—' }}</td></tr>
        <tr><td class="label">Charted by</td><td class="value" colspan="3">{{ optional($card->officer)->name }} · {{ $card->charted_at->format('d M Y H:i') }}</td></tr>
    </table>

    <table class="grid">
        <caption>Coordinates (UTM Zone 32N, Minna datum)</caption>
        <thead><tr><th style="width:6%">S/N</th><th style="width:16%">Beacon</th><th style="width:22%">Easting</th><th style="width:22%">Northing</th><th>Remarks</th></tr></thead>
        <tbody>
            @foreach ($card->beacons as $b)
                <tr><td>{{ $loop->iteration }}</td><td>{{ $b->beacon }}</td><td>{{ number_format($b->easting, 3) }}</td><td>{{ number_format($b->northing, 3) }}</td><td>{{ $b->remarks }}</td></tr>
            @endforeach
            @for ($i = $card->beacons->count(); $i < 6; $i++)
                <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
    </table>

    <div class="foot">
        <span>{{ $case->case_ref }} · {{ $def->workflows()[$case->workflow]['label'] ?? $case->workflow }}</span>
        <span>Printed {{ now()->format('d M Y H:i') }} · copy {{ $card->print_count }}</span>
    </div>
</div>
</body>
</html>
