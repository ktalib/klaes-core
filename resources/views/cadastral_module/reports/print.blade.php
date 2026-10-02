{{-- Cadastral report — the printed document. Standalone, no app chrome. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>{{ $report->type_label }} Report {{ $report->report_ref }}</title>
    <style>
        @page { size: A4; margin: 18mm; }

        body { font-family: "Times New Roman", Georgia, serif; font-size: 12.5px; color: #000; margin: 0; line-height: 1.5; }
        .sheet { max-width: 180mm; margin: 0 auto; }

        .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
        .head h1 { font-size: 16px; margin: 0; letter-spacing: .06em; text-transform: uppercase; }
        .head h2 { font-size: 12.5px; margin: 3px 0 0; font-weight: normal; }
        .head h3 { font-size: 13.5px; margin: 10px 0 0; text-transform: uppercase; letter-spacing: .1em; }

        .refs { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 11.5px; }

        table.fields { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.fields td { padding: 4px 6px; vertical-align: top; }
        table.fields .label { width: 26%; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; color: #333; }
        table.fields .value { border-bottom: 1px dotted #666; font-weight: bold; }

        h4 { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; margin: 16px 0 5px; border-bottom: 1px solid #999; padding-bottom: 2px; }
        .prose { white-space: pre-wrap; text-align: justify; }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 3px 5px; font-size: 10.5px; text-align: left; }
        table.grid th { background: #eee; }

        .sig { margin-top: 34px; display: flex; justify-content: space-between; }
        .sig .block { width: 45%; }
        .sig .rule { border-top: 1px solid #000; padding-top: 4px; font-size: 10.5px; }

        .foot { margin-top: 22px; border-top: 1px solid #999; padding-top: 6px; font-size: 9.5px; color: #444; }

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
        <a href="{{ route('cadastral-module.reports.show', $report) }}">Back</a>
    </div>

    <div class="head">
        <h1>Kano State Ministry of Land and Physical Planning</h1>
        <h2>Cadastral Department</h2>
        <h3>{{ $report->type_label }} Report</h3>
    </div>

    <div class="refs">
        <span><strong>Report No.:</strong> {{ $report->report_ref }}</span>
        <span><strong>File No.:</strong> {{ $report->file_number }}</span>
        <span><strong>Date:</strong> {{ now()->format('d F Y') }}</span>
    </div>

    <table class="fields">
        <tr>
            <td class="label">File Title</td>
            <td class="value" colspan="3">{{ $report->file_title ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Plot No.</td>
            <td class="value">{{ $report->plot_no ?: '—' }}</td>
            <td class="label">Block No.</td>
            <td class="value">{{ $report->block_no ?: '—' }}</td>
        </tr>
        <tr>
            {{-- District, LGA, State — the plot number is its own row above. --}}
            <td class="label">Location</td>
            <td class="value">{{ $report->property_location ?: '—' }}</td>
            <td class="label">Layout</td>
            <td class="value">{{ $report->layout_name ?: '—' }}</td>
        </tr>
        @if ($report->chart)
            <tr>
                <td class="label">Chart Ref</td>
                <td class="value">{{ $report->chart->chart_ref }} (v{{ $report->chart->version }})</td>
                <td class="label">Approved Plan</td>
                <td class="value">{{ $report->chart->approved_plan_no ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Area</td>
                <td class="value" colspan="3">
                    @if ($report->chart->area_sqm !== null)
                        {{ number_format($report->chart->area_sqm, 2) }} sqm
                        ({{ number_format($report->chart->area_sqm / 10000, 4) }} ha)
                        — {{ $report->chart->area_source }}
                    @else — @endif
                </td>
            </tr>
        @endif
        <tr>
            <td class="label">Survey Necessary</td>
            <td class="value">{{ $report->survey_necessary ?: '—' }}</td>
            <td class="label">Beacon Numbers</td>
            <td class="value">{{ $report->beacon_numbers ?: '—' }}</td>
        </tr>
    </table>

    <h4>Plot Description</h4>
    <div class="prose">{{ $report->plot_description ?: '—' }}</div>

    <h4>Observation — Ground Status</h4>
    <div class="prose">{{ $report->ground_status ?: '—' }}</div>

    <h4>Observation — Chart Status</h4>
    <div class="prose">{{ $report->chart_status ?: '—' }}</div>

    @if ($report->inspections->isNotEmpty())
        <h4>Site Inspection</h4>
        <table class="grid">
            <thead>
                <tr><th>Date</th><th>Officer</th><th>GPS</th><th>Development</th><th>Occupancy</th><th>Encroachment</th></tr>
            </thead>
            <tbody>
                @foreach ($report->inspections as $inspection)
                    <tr>
                        <td>{{ optional($inspection->inspected_on)->format('d M Y') }}</td>
                        <td>{{ $inspection->field_officer_name }}</td>
                        <td>
                            @if ($inspection->hasFix())
                                {{ number_format($inspection->gps_latitude, 6) }},
                                {{ number_format($inspection->gps_longitude, 6) }}
                            @endif
                        </td>
                        <td>{{ $inspection->development_status }}</td>
                        <td>{{ $inspection->occupancy_status }}</td>
                        <td>{{ $inspection->encroachment ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h4>Processing Record</h4>
    <table class="grid">
        <thead>
            <tr><th style="width:36px">#</th><th>Stage</th><th>Desk</th><th>Officer</th><th>Completed</th><th>Note</th></tr>
        </thead>
        <tbody>
            @foreach ($progress['steps'] as $step)
                <tr>
                    <td>{{ $step->step_no }}</td>
                    <td>{{ $step->step_name }}</td>
                    <td>{{ config('cadastral_module.posts')[$step->required_post] ?? '' }}</td>
                    <td>{{ $step->actor_name }}</td>
                    <td>{{ optional($step->completed_at)->format('d M Y') }}</td>
                    <td>{{ $step->note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="sig">
        <div class="block">
            <div class="rule" style="margin-top:40px;">
                Prepared by — Report Officer
            </div>
        </div>
        <div class="block">
            <div class="rule" style="margin-top:40px;">
                Approved — Assistant Director, Cadastral Report
            </div>
        </div>
    </div>

    <div class="foot">
        {{ $report->report_ref }} · {{ $report->type_label }} · status {{ $report->status }} ·
        {{ $progress['done'] }} of {{ $progress['total'] }} stages complete ·
        printed {{ now()->format('d M Y H:i') }}
    </div>
</div>
</body>
</html>
