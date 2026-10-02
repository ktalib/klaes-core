{{--
    Report on Application - Cadastral Department: the official print (plan §3a).

    A Blade copy of docs/templates/cadastral/Report-on-Application-Cadastral-
    Department.html. Its CSS, A4 page, 15mm margins and layout are kept as they
    are; the only additions are the .fill / .yn-* / .toolbar rules marked below,
    which put the record's values on the form's blank lines and hide the screen
    toolbar when printing.

    YES/NO: the paper form prints "YES/NO" for the officer to delete as
    appropriate, so the option not chosen is struck through and the chosen one
    is bold. An unanswered question keeps the plain "YES/NO".

    Q8 acres are hectares x 2.47, the factor the form itself prints.
    Date and "For:" are the approval step's date and officer, and stay blank
    until the report is approved.
--}}
@php
    $val = fn (string $c) => $report->applicationValue($c);

    // "YES/NO" with the option not chosen struck through.
    $yn = function (?string $answer) {
        if ($answer !== 'Yes' && $answer !== 'No') {
            return 'YES/NO';
        }
        $yes = $answer === 'Yes' ? '<span class="yn-chosen">YES</span>' : '<span class="yn-struck">YES</span>';
        $no  = $answer === 'No'  ? '<span class="yn-chosen">NO</span>'  : '<span class="yn-struck">NO</span>';

        return $yes . '/' . $no;
    };

    // A value after a question's text. Nothing at all when empty, so an
    // unanswered sub-field looks exactly as the paper form does.
    $fill = fn ($v, string $min = '120px') => trim((string) ($v ?? '')) === ''
        ? ''
        : '<span class="fill" style="min-width:' . $min . ';">' . e((string) $v) . '</span>';

    $purpose = $val('q4_purpose');
    $isPurpose = fn (string $p) => $purpose !== null && strcasecmp($purpose, $p) === 0;
    $isOther = $purpose !== null && $purpose !== ''
        && ! in_array(strtolower($purpose), array_map('strtolower', \App\Models\Cadastral\CadastralReport::APPLICATION_PURPOSES), true);
    $circle = fn (string $label, bool $on) => $on ? '<span class="yn-circled">' . e($label) . '</span>' : e($label);

    $fmtNum = function ($n) {
        if ($n === null) return '';
        return rtrim(rtrim(number_format((float) $n, 4, '.', ','), '0'), '.');
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report on Application - Cadastral Department - {{ $report->report_ref }}</title>
    <style>
        /* [ ... NOTE: The vast majority of CSS styles remain exactly as before.
           Only specific logo/QR styles are updated below. ... ] */

        @page {
            size: A4;
            margin: 15mm;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .document-container {
            background-color: #fff;
            max-width: 750px;
            margin: 20px auto;
            padding: 30px 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            page-break-inside: avoid;
            box-sizing: border-box;
            position: relative; /* Container for absolute centering of logo */
        }

        /* Top Header Area with Linked Images */
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 5px;
            height: 90px; /* Force minimum height for alignment */
        }

        /* Updated Image Styling */
        .qr-image {
            width: 70px;  /* Size matched to original image appearance */
            height: 70px;
            border: none; /* Removed placeholder border */
            display: block;
        }

        .coat-of-arms-image {
            width: 80px; /* Size matched to original image appearance */
            height: auto;  /* Maintain ratio */
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            top: 20px; /* Fine-tuned position to match text layout */
            display: block;
        }

        /* [ ... Remainder of CSS styling is unchanged ... ] */

        .ministry-title { background-color: #9cd4a7; border: 2px solid #000; color: #000; text-align: center; font-weight: bold; font-size: 20px; padding: 6px; margin-top: 15px; letter-spacing: 0.5px; box-shadow: 2px 2px 0px #000; }
        .address-sub { text-align: center; font-weight: bold; font-size: 11px; margin: 5px 0 10px 0; letter-spacing: 0.2px; }
        .department-title { background-color: #0000a8; color: #fff; text-align: center; font-weight: bold; font-size: 22px; padding: 4px; width: 80%; margin: 0 auto 10px auto; letter-spacing: 1px; }
        .report-sub { text-align: center; color: #b02a2a; font-weight: bold; font-size: 14px; margin-bottom: 15px; }
        .entry-section { font-size: 14px; line-height: 1.6; margin-bottom: 15px; }
        .entry-row { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .field-group { display: flex; flex-grow: 1; align-items: flex-end; }
        .field-group.half { width: 62%; }
        .field-group.short { width: 35%; margin-left: 3%; }
        .line-fill { border-bottom: 1px solid #000; flex-grow: 1; margin-bottom: 3px; padding-left: 4px; }
        .questionnaire { font-size: 14px; line-height: 1.45; }
        .q-item { margin-bottom: 10px; }
        .q-main { display: flex; justify-content: space-between; align-items: flex-end; font-weight: bold; }
        .q-text { flex-grow: 1; padding-right: 10px; }
        .q-status { font-weight: normal; min-width: 65px; text-align: right; }
        .sub-group { padding-left: 25px; margin-top: 4px; }
        .sub-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 4px; }
        .sub-text { flex-grow: 1; }
        .footer-section { display: flex; justify-content: space-between; margin-top: 40px; font-size: 14px; }
        .footer-left { width: 40%; }
        .footer-right { width: 45%; text-align: right; }
        .signature-line { border-bottom: 1px solid #000; width: 180px; display: inline-block; margin-bottom: 3px; }
        .gp-kano { font-size: 9px; text-align: right; margin-top: 5px; padding-right: 15px; }

        @media print { body { background-color: #fff; } .document-container { box-shadow: none; padding: 10px 20px; max-width: 100%; margin: 0; } }

        /* ---- KLAES additions: filled values and the screen toolbar ---- */
        .fill { display: inline-block; border-bottom: 1px dotted #000; padding: 0 4px; font-weight: normal; vertical-align: bottom; }
        .line-fill, .signature-line { font-weight: normal; text-align: left; }
        .signature-line { padding-left: 4px; box-sizing: border-box; }
        .yn-struck { text-decoration: line-through; text-decoration-thickness: 2px; }
        .yn-chosen { font-weight: bold; }
        .yn-circled { border: 1.5px solid #000; border-radius: 50%; padding: 0 5px; }
        .qr-slot { width: 70px; height: 70px; display: block; }
        .toolbar { max-width: 750px; margin: 12px auto 0; display: flex; gap: 8px; font-family: Arial, sans-serif; font-size: 13px; }
        .toolbar a, .toolbar button { padding: 6px 12px; border: 1px solid #999; background: #fff; border-radius: 4px; color: #000; text-decoration: none; cursor: pointer; font-size: 13px; }
        .toolbar .note { color: #92400e; align-self: center; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print</button>
    <a href="{{ route('cadastral-module.reports.show', $report) }}">Back to {{ $report->report_ref }}</a>
    @unless ($installed)
        <span class="note">The questionnaire columns are pending installation; only the file references and area are filled.</span>
    @endunless
</div>

<div class="document-container">

    <div class="header-top">

        @if ($qr)
            <img src="{{ $qr }}" alt="QR Code" class="qr-image">
        @else
            <span class="qr-slot"></span>
        @endif

        <img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Nigerian Coat of Arms" class="coat-of-arms-image">

        <div></div>
    </div>

    <div class="ministry-title">
        MINISTRY OF LAND AND PHYSICAL PLANNING
    </div>
    <div class="address-sub">
        No. 2 Dr. Bala Mohd. Road, Nassarawa GRA, P.M.B. 3083 KANO-NIGERIA.
    </div>

    <div class="department-title">
        CADASTRAL DEPARTMENT
    </div>
    <div class="report-sub">
        Report on Application for:
    </div>

    <div class="entry-section">
        <div class="entry-row">
            <div class="field-group half">
                <span>Right of Occupancy No. LKN/</span><div class="line-fill">{{ $refs['lkn'] }}</div>
            </div>
            <div class="field-group short">
                <span>SLTR/</span><div class="line-fill">{{ $refs['sltr'] }}</div>
            </div>
        </div>
        <div class="entry-row">
            <div class="field-group half">
                <span>Government Item No. GKN/</span><div class="line-fill">{{ $refs['gkn'] }}</div>
            </div>
            <div class="field-group short">
                <span>SIT/</span><div class="line-fill">{{ $refs['sit'] }}</div>
            </div>
        </div>
    </div>

    <div class="questionnaire">

        <div class="q-item">
            <div class="q-main">
                <span class="q-text">1. Is the applicant's Plan sufficient to Identify the Plot?</span>
                <span class="q-status">{!! $yn($val('q1_plan_sufficient')) !!}</span>
            </div>
        </div>

        <div class="q-item">
            <div class="q-main">
                <span class="q-text">2. Is the ground open? &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; CORRECTLY:</span>
                <span class="q-status">{!! $yn($val('q2_ground_open')) !!}</span>
            </div>
            <div class="sub-group">
                <div class="sub-row">
                    <span class="sub-text">If No, What title of Application lie over the same land? {!! $fill($val('q2_overlapping_title'), '160px') !!}</span>
                </div>
            </div>
        </div>

        <div class="q-item">
            <div class="q-main">
                <span class="q-text">3. Is the Plot COMPLETELY beaconed?</span>
                <span class="q-status">{!! $yn($val('q3_beaconed')) !!}</span>
            </div>

            <div class="sub-group">
                <div class="sub-row">
                    <span class="sub-text">If YES see &nbsp;&nbsp;(a) Tracing No. {!! $fill($val('q3_tracing_no')) !!}</span>
                </div>
                <div class="sub-row" style="margin-left: 45px;">
                    <span class="sub-text">(b) Deposition Plan No. {!! $fill($val('q3_deposition_plan_no')) !!}</span>
                </div>

                <div class="sub-row" style="margin-top: 15px;">
                    <span class="sub-text">If NO &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(a) It lies on unapproved Town Plan No. {!! $fill($val('q3_unapproved_town_plan_no')) !!}</span>
                </div>
                <div class="sub-row" style="margin-left: 45px;">
                    <span class="sub-text">(b) It lies on Lay-Out No. {!! $fill($val('q3_layout_no')) !!}</span>
                </div>
                <div class="sub-row" style="margin-left: 45px;">
                    <span class="sub-text">(c) Is Separate Survey required?</span>
                    <span class="q-status">{!! $yn($val('q3_separate_survey')) !!}</span>
                </div>
            </div>
        </div>

        <div class="q-item">
            <div class="q-main">
                <span class="q-text">4. Does it lies on an area covered by a Town Plan?</span>
                <span class="q-status">{!! $yn($val('q4_town_plan')) !!}</span>
            </div>

            <div class="sub-group">
                <div class="sub-row">
                    <div class="field-group" style="font-weight: normal;">
                        <span>IF YES &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(a) The Town Plan No. is</span><div class="line-fill" style="border-bottom: 1px dotted #000;">{{ $val('q4_town_plan_no') }}</div>
                    </div>
                </div>
                <div class="sub-row" style="margin-left: 45px;">
                    <span class="sub-text">(b) Does the shape of the plot agrees with the Town Plan?</span>
                    <span class="q-status">{!! $yn($val('q4_shape_agrees')) !!}</span>
                </div>
                <div class="sub-row" style="margin-left: 45px;">
                    <span class="sub-text">(c) The Application is for {!! $circle('Residetial', $isPurpose('Residential')) !!}/{!! $circle('Commercial', $isPurpose('Commercial')) !!}</span>
                </div>
                <div class="sub-row" style="margin-left: 63px;">
                    <span class="sub-text">{!! $circle('Industrial', $isPurpose('Industrial')) !!}/{!! $circle('Agricultural', $isPurpose('Agricultural')) !!}/{!! $circle('other', $isOther) !!} purpose{!! $isOther ? ' ' . $fill($purpose, '80px') : '' !!}</span>
                </div>
                <div class="sub-row" style="margin-left: 45px;">
                    <span class="sub-text">(d) Is the Area shown on the Town Plan for the Purpose applied?</span>
                    <span class="q-status">{!! $yn($val('q4_area_for_purpose')) !!}</span>
                </div>
            </div>
        </div>

        <div class="q-item">
            <div class="q-main">
                <span class="q-text">5. Has the Plot been previously hold or applied under statutory title?</span>
                <span class="q-status">{!! $yn($val('q5_previous_title')) !!}</span>
            </div>
            <div class="sub-group">
                <div class="sub-row">
                    <span class="sub-text">IF YES, details: {!! $fill($val('q5_details'), '200px') !!}</span>
                </div>
            </div>
        </div>

        <div class="q-item">
            <div class="q-main">
                <span class="q-text">6. Does a Railway/Siding run alongside/through the Plot?</span>
                <span class="q-status">{!! $yn($val('q6_railway')) !!}</span>
            </div>
        </div>

        <div class="q-item">
            <div class="q-main">
                <span class="q-text">7. Is the Plot alongside a Federal/State Truck Road?</span>
                <span class="q-status">{!! $yn($val('q7_trunk_road')) !!}</span>
            </div>
        </div>

        <div class="q-item">
            <div class="q-main" style="font-weight: normal; margin-top: 5px;">
                @if ($areaHa === null)
                    <span class="q-text">8. Area applied for: (&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; acres) &nbsp;&nbsp; Hectares)</span>
                @else
                    <span class="q-text">8. Area applied for: ({!! $fill($fmtNum($areaAcres), '90px') !!} acres) &nbsp;&nbsp; {!! $fill($fmtNum($areaHa), '70px') !!} Hectares)</span>
                @endif
            </div>
            <div class="sub-group" style="margin-left: 110px;">
                <span>(1 hectares = 2.47 acres)</span>
            </div>
        </div>

    </div>

    <div class="footer-section">
        <div class="footer-left">
            <strong>Date:</strong> <span class="signature-line" style="width: 140px;">{{ optional($approval?->completed_at)->format('d/m/Y') }}</span><br>
            <strong style="margin-left: 65px; display: inline-block; margin-top: 5px;">KCAD</strong>
        </div>
        <div class="footer-right">
            <strong>For:</strong> <span class="signature-line" style="width: 170px;">{{ $approval?->actor_name }}</span><br>
            <strong style="display: inline-block; margin-top: 5px; padding-right: 15px;">Director Cadastral</strong>
        </div>
    </div>


</div>

</body>
</html>
