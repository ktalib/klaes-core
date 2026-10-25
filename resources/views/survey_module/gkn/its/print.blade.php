{{--
    Instruction to Survey, Survey Department: the printed instrument. Copied from
    cadastral_module/information/its_print.blade.php (clone per module).

    Standalone, no app chrome: this is signed and sent out.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Instruction to Survey {{ $its->its_number }}</title>
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
        table.fields .label { width: 28%; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #333; }
        table.fields .value { border-bottom: 1px dotted #666; font-weight: bold; }

        .body-text { white-space: pre-wrap; margin: 16px 0; text-align: justify; }

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
        <button onclick="window.print()">Print</button>
        <a href="{{ route('survey-module.gkn.its.edit', $its) }}">Back</a>
    </div>

    <div class="head">
        <h1>Kano State Ministry of Land and Physical Planning</h1>
        <h2>Survey Department</h2>
        <h3>Instruction to Survey</h3>
    </div>

    <div class="refs">
        <span><strong>Instruction No.:</strong> {{ $its->its_number }}</span>
        <span><strong>Survey Job No.:</strong> {{ $its->job_ref }}</span>
        <span><strong>Date:</strong> {{ optional($its->its_issued_at)->format('d F Y') ?: now()->format('d F Y') }}</span>
    </div>

    <table class="fields">
        <tr>
            <td class="label">To</td>
            <td class="value" colspan="3">{{ $its->surveyor_name ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Rank</td>
            <td class="value">{{ $its->surveyor_rank ?: '—' }}</td>
            <td class="label">Staff ID</td>
            <td class="value">{{ $its->surveyor?->staff_id ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">File Number</td>
            <td class="value">{{ $its->file_number }}</td>
            <td class="label">File Title</td>
            <td class="value">{{ $its->file_title ?: '—' }}</td>
        </tr>
        {{-- Plot No., then District, LGA as the location. --}}
        <tr>
            <td class="label">Plot Number</td>
            <td class="value" colspan="3">{{ $its->plot_no ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Location</td>
            <td class="value" colspan="3">{{ $its->location ? $its->location . ', Kano' : '—' }}</td>
        </tr>
        <tr>
            <td class="label">Scope</td>
            <td class="value" colspan="3">{{ $its->job_scope ?: '—' }}</td>
        </tr>
    </table>

    <p>Sir/Madam,</p>

    <div class="body-text">{{ $its->its_instructions }}</div>

    <p>
        You are required to carry out the survey in accordance with the Survey Co-ordination Act and
        the regulations of the Surveyors Council of Nigeria, and to return the plan, the schedule of
        beacon coordinates and your report to this Department on completion.
    </p>

    <div class="sig">
        <div class="block">
            <div class="rule">
                {{ $its->its_issued_by ?: '' }}<br />
                Officer in Charge<br />
                Survey Department
            </div>
        </div>

        <div class="block">
            <div class="rule" style="margin-top:58px;">
                Received by (surveyor) — name, signature and date
            </div>
        </div>
    </div>

    <div class="foot">
        Survey Job {{ $its->job_ref }} · Instruction {{ $its->its_number }} ·
        Printed {{ now()->format('d M Y H:i') }}
    </div>
</div>
</body>
</html>
