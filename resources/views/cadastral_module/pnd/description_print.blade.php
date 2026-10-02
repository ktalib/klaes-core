{{--
    Description of Right of Occupancy — the saved land description, printed.

    Standalone, no app chrome. There is no official template for this document
    (docs/templates/cadastral holds the fee sheet and the report on application
    only), so it follows the module's other prints: the bill and the
    Instruction to Surveyor.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Description of Right of Occupancy {{ $record->file_number }}</title>
    <style>
        @page { size: A4; margin: 20mm; }

        body { font-family: "Times New Roman", Georgia, serif; font-size: 13px; color: #000; margin: 0; line-height: 1.55; }
        .sheet { max-width: 180mm; margin: 0 auto; }

        .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 16px; }
        .head h1 { font-size: 16px; margin: 0; letter-spacing: .06em; text-transform: uppercase; }
        .head h2 { font-size: 13px; margin: 3px 0 0; font-weight: normal; }
        .head h3 { font-size: 14px; margin: 10px 0 0; text-transform: uppercase; letter-spacing: .1em; }

        .refs { display: flex; justify-content: space-between; margin-bottom: 14px; font-size: 12px; }

        table.fields { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.fields td { padding: 5px 6px; vertical-align: top; }
        table.fields .label { width: 22%; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #333; }
        table.fields .value { border-bottom: 1px dotted #666; font-weight: bold; }

        .body-text { white-space: pre-wrap; margin: 16px 0; text-align: justify; }

        table.pillars { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.pillars th, table.pillars td { border: 1px solid #000; padding: 4px 6px; font-size: 11px; }
        table.pillars th { background: #eee; text-align: left; }
        table.pillars .num { text-align: right; font-variant-numeric: tabular-nums; }
        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; margin-top: 14px; }

        .sig { margin-top: 42px; display: flex; justify-content: space-between; }
        .sig .block { width: 45%; }
        .sig .rule { border-top: 1px solid #000; padding-top: 4px; font-size: 11px; }

        .foot { margin-top: 26px; border-top: 1px solid #999; padding-top: 6px; font-size: 10px; color: #444; }

        .no-print { margin: 10px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="sheet">

    <div class="no-print">
        @canDo('Cad - Records', 'print')
            <button onclick="window.print()">Print</button>
        @endcanDo
        <a href="{{ route('cadastral-module.plan-description.descriptions', ['record' => $record->id]) }}">Back</a>
    </div>

    <div class="head">
        <h1>Kano State Ministry of Land and Physical Planning</h1>
        <h2>Cadastral Department — Plan and Description Unit</h2>
        <h3>Description of Right of Occupancy</h3>
    </div>

    <div class="refs">
        <span><strong>File No.:</strong> {{ $record->file_number }}</span>
        <span><strong>Ref.:</strong> {{ $record->pd_ref }}</span>
        <span><strong>Date:</strong> {{ optional($record->updated_at)->format('d F Y') ?: now()->format('d F Y') }}</span>
    </div>

    <table class="fields">
        <tr>
            <td class="label">Holder</td>
            <td class="value" colspan="3">{{ $record->file_title ?: '—' }}</td>
        </tr>
        <tr>
            {{-- District, LGA, State. The plot number has its own cell. --}}
            <td class="label">Location</td>
            <td class="value">{{ $record->property_location ?: '—' }}</td>
            <td class="label">Plot No.</td>
            <td class="value">{{ $record->chart?->plot_no ?: $record->prop_plot ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Land Use</td>
            <td class="value">{{ $record->land_use ?: '—' }}</td>
            <td class="label">Plan No.</td>
            <td class="value">{{ $record->chart?->approved_plan_no ?: $record->chart?->tp_plan_no ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Area</td>
            <td class="value" colspan="3">
                {{ $areas['sqm'] !== null ? number_format($areas['sqm'], 2) . ' m²' : '—' }}
                @if ($areas['hectares'] !== null)
                    · {{ number_format($areas['hectares'], 4) }} ha
                    · {{ number_format($areas['acres'], 2) }} acres
                @endif
            </td>
        </tr>
    </table>

    <div class="body-text">{{ $record->description_body }}</div>

    @if ($pillars->isNotEmpty())
        <div class="section-title">Schedule of Pillars</div>
        <table class="pillars">
            <thead>
                <tr>
                    <th>#</th><th>Pillar No.</th><th>Ownership</th><th>Type</th>
                    <th class="num">Easting</th><th class="num">Northing</th><th>Condition</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pillars as $i => $p)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $p->pillar_number ?: '—' }}</td>
                        <td>{{ ucfirst($p->ownership) }}</td>
                        <td>{{ $p->pillar_type ?: '—' }}</td>
                        <td class="num">{{ $p->easting !== null ? number_format($p->easting, 3) : '—' }}</td>
                        <td class="num">{{ $p->northing !== null ? number_format($p->northing, 3) : '—' }}</td>
                        <td>{{ $p->condition ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="sig">
        <div class="block">
            <div class="rule" style="margin-top:40px;">
                Prepared — Plan and Description Unit
            </div>
        </div>
        <div class="block">
            <div class="rule" style="margin-top:40px;">
                Checked — Assistant Director, Plans and Descriptions
            </div>
        </div>
    </div>

    <div class="foot">
        {{ $record->pd_ref }} · {{ $template ?: 'hand-written' }} ·
        chart check {{ $record->validation_status }} ·
        printed {{ now()->format('d M Y H:i') }}
    </div>
</div>
</body>
</html>
