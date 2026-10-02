{{--
    Instruction to Surveyor — the printed instrument.

    Standalone, no app chrome: this is signed and sent out.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Instruction to Surveyor {{ $job->its_number }}</title>
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
        .sig img { max-height: 54px; display: block; margin-bottom: 4px; }

        .foot { margin-top: 26px; border-top: 1px solid #999; padding-top: 6px; font-size: 10px; color: #444; }
        .caveat-print { margin-top: 10px; font-size: 10px; color: #7a4a00; }

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
        <a href="{{ route('cadastral-module.survey-jobs.edit', $job) }}">Back</a>
    </div>

    <div class="head">
        <h1>Kano State Ministry of Land and Physical Planning</h1>
        <h2>Cadastral Department</h2>
        <h3>Instruction to Surveyor</h3>
    </div>

    <div class="refs">
        <span><strong>Instruction No.:</strong> {{ $job->its_number }}</span>
        <span><strong>Survey Job No.:</strong> {{ $job->job_number }}</span>
        <span><strong>Date:</strong> {{ optional($job->its_issued_at)->format('d F Y') ?: now()->format('d F Y') }}</span>
    </div>

    <table class="fields">
        <tr>
            <td class="label">To</td>
            <td class="value" colspan="3">{{ $job->its_recipient ?: $job->surveyor_name }}</td>
        </tr>
        <tr>
            <td class="label">Surveyor</td>
            <td class="value">{{ $job->surveyor_name ?: '—' }}</td>
            <td class="label">SURCON No.</td>
            <td class="value">{{ $job->surveyor?->surcon_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Firm</td>
            <td class="value">{{ $job->firm_name ?: '—' }}</td>
            <td class="label">RC No.</td>
            <td class="value">{{ $job->surveyor?->firm_rc_no ?: '—' }}</td>
        </tr>
        @if ($job->surveyor)
            <tr>
                {{-- Street, plot, district, LGA, state. --}}
                <td class="label">Address</td>
                <td class="value" colspan="3">{{ $job->surveyor->person_address ?: '—' }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">File Number</td>
            <td class="value">{{ $job->file_number }}</td>
            <td class="label">File Title</td>
            <td class="value">{{ $job->file_title ?: '—' }}</td>
        </tr>
        <tr>
            {{-- District, LGA, State. --}}
            <td class="label">Job Location</td>
            <td class="value" colspan="3">{{ $job->property_location ?: '—' }}</td>
        </tr>
        @if ($job->prop_plot)
            <tr>
                <td class="label">Plot Number</td>
                <td class="value" colspan="3">{{ $job->prop_plot }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Scope</td>
            <td class="value" colspan="3">{{ $job->job_scope ?: '—' }}</td>
        </tr>
    </table>

    <p>Sir/Madam,</p>

    <div class="body-text">{{ $job->its_instructions }}</div>

    <p>
        You are required to carry out the survey in accordance with the Survey Co-ordination Act and
        the regulations of the Surveyors Council of Nigeria, and to return the plan, the schedule of
        beacon coordinates and your report to this Department on completion.
    </p>

    <div class="sig">
        <div class="block">
            @if ($job->its_signature_path)
                <img src="{{ $job->its_signature_path }}" alt="" />
            @endif
            <div class="rule">
                {{ $job->its_issued_by ?: '' }}<br />
                {{ $postName ?: 'Officer in Charge' }}<br />
                Cadastral Department
                @if ($job->its_signature_verified_at)
                    <br /><em>Signature verified {{ $job->its_signature_verified_at->format('d M Y H:i') }}</em>
                @endif
            </div>
        </div>

        <div class="block">
            <div class="rule" style="margin-top:58px;">
                Received by (surveyor) — name, signature and date
            </div>
        </div>
    </div>

    <div class="foot">
        Survey Job {{ $job->job_number }} · Instruction {{ $job->its_number }} ·
        Printed {{ now()->format('d M Y H:i') }}

        @if (app(\App\Services\Cadastral\CadastralSettings::class)->jobNumberFormat()['is_placeholder'])
            <div class="caveat-print">
                Note for internal use: the job-number format is a KLAES placeholder pending
                confirmation of the SURCON pattern.
            </div>
        @endif
    </div>
</div>
</body>
</html>
