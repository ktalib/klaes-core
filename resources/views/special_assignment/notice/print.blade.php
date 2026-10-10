{{--
    SPAS contravention notice, printed (A4, browser print).

    Layout and the Second Serve (REMINDER) letter are the Ministry template
    supplied on 2026-10-08 (spas_notice.html). A First Serve uses the same
    letter with the heading NOTICE and the Ministry's First Serve wording
    (App\Models\SpaNotice::FIRST_SERVE_SMS), unchanged.
--}}
@php
    use App\Models\SpaNotice;

    $app      = $notice->application;
    $isSecond = $notice->notice_type === 'second';
    $served   = $notice->served_date ?? $notice->created_at;
    $district = $app->district ?? null;
    $lga      = $app->lga ?? null;
    $address  = array_values(array_filter([
        $notice->recipient_name ?: ($app->owner_name ?? null),
        trim(collect([$district, $lga])->filter()->implode(', ')),
        $app->location ?? null,
    ], fn ($line) => $line !== null && $line !== ''));
    // Location repeats District, LGA when it was built from them.
    $address = array_values(array_unique($address));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isSecond ? 'Reminder' : 'Notice' }} - Contravention of Landuse - {{ $notice->file_number }}</title>
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #f2f2f2; font-family: "Times New Roman", Times, serif; color: #000; }
        .page { width: 210mm; min-height: 297mm; margin: 0 auto; background: #fff; padding: 25mm; position: relative; }

        .header { position: relative; height: 68mm; }
        .address { position: absolute; top: 35mm; left: 0; width: 95mm; }
        .address-line { min-height: 7mm; border-bottom: 1px dotted #555; font-size: 12pt; line-height: 7mm; padding-left: 1mm;
                        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-transform: uppercase; }
        .date { position: absolute; top: 8mm; right: 5mm; font-size: 12pt; white-space: nowrap; }
        .date-line { display: inline-block; min-width: 43mm; border-bottom: 1px dotted #555; margin-left: 3mm; vertical-align: middle; text-align: center; }
        .ref { position: absolute; top: 16mm; right: 5mm; font-size: 10pt; white-space: nowrap; }

        .salutation { font-size: 12pt; margin-top: 1mm; margin-bottom: 8mm; }

        .main-heading { text-align: center; margin-bottom: 7mm; }
        .main-heading .reminder { display: block; font-weight: bold; text-decoration: underline; font-size: 14pt; margin-bottom: 7mm; }
        .main-heading .subject { display: block; font-weight: bold; text-decoration: underline; font-size: 13pt; text-transform: uppercase; }

        .content { font-size: 12pt; line-height: 1.55; text-align: left; }
        .content p { margin: 0 0 8mm 0; }

        .closing { margin-top: 25mm; font-size: 12pt; line-height: 1.45; }
        .signature { margin-top: 13mm; font-size: 12pt; line-height: 1.3; }
        .signature .name { font-weight: bold; }
        .signature .for { font-weight: bold; }

        .no-print { text-align: center; padding: 12px; }
        .no-print button { padding: 9px 22px; font-size: 13px; font-weight: bold; cursor: pointer; background: rgb(186,191,12); color: #fff; border: 0; border-radius: 8px; }

        @media print {
            body { background: #fff; }
            .page { margin: 0; width: 210mm; min-height: 297mm; box-shadow: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print"><button onclick="window.print()">Print this notice</button></div>

<div class="page">

    <div class="header">
        <div class="date">
            Date:
            <span class="date-line">{{ $served ? \Carbon\Carbon::parse($served)->format('jS F, Y') : '' }}</span>
        </div>
        <div class="ref">File No: <strong>{{ $notice->file_number }}</strong></div>

        <div class="address">
            @for ($i = 0; $i < 3; $i++)
                <div class="address-line">{{ $address[$i] ?? '' }}</div>
            @endfor
        </div>
    </div>

    <div class="salutation">Sir/Ma,</div>

    <div class="main-heading">
        <span class="reminder">{{ $isSecond ? 'REMINDER' : 'NOTICE' }}</span>
        <span class="subject">RE: CONTRAVENTION OF LANDUSE</span>
    </div>

    <div class="content">
        @if ($isSecond)
            <p>
                Refer above and note that the Ministry has served you with a notice to come
                forward and formalize your title but there was no response. This served as a
                reminder to you that your failure will leave the ministry with no option other
                than to invoke its power under either section 9 (4) or 28 (5) of the Land use Act.
            </p>
            <p>Your earlier response is awaited.</p>
        @else
            {{-- The Ministry's First Serve wording, as sent by SMS. --}}
            <p>{{ SpaNotice::FIRST_SERVE_SMS }}</p>
        @endif
    </div>

    <div class="closing">Best Regards,</div>

    <div class="signature">
        <div class="name">GAMBO SULEIMAN GARKO,</div>
        <div class="title">Director Special Assignment,</div>
        <div class="for">For: Honorable Commissioner</div>
    </div>

</div>

</body>
</html>
