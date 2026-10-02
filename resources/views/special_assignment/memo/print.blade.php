{{--
    Commissioner memo, printable.

    Printable at every stage on purpose: a PENDING memo is the document carried
    to the Commissioner for signature, so it prints with an empty decision block
    to sign; a DECIDED one prints the decision that was recorded, and the block
    becomes the record rather than a form.
--}}
@php
    $app      = $memo->application;
    $decision = $memo->commissioner_decision;
    $decided  = in_array($decision, ['approved', 'rejected'], true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memo – {{ $memo->memo_no }}</title>
    <style>
        @page { size: A4; margin: 0; }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f0f0f0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .a4-page {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            box-sizing: border-box;
            padding: 18mm 20mm 20mm;
            box-shadow: 0 0 15px rgba(0,0,0,.1);
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
        }
        .logo-box img { max-width: 80px; max-height: 80px; object-fit: contain; }
        .gov-title-container { text-align: center; }
        .gov-title-container h1 { font-size: 18px; margin: 0; }
        .gov-title-container h2 { font-size: 14px; margin: 2px 0 0; font-weight: normal; }
        /* Slightly smaller and tracked out, so the department reads as
           subordinate to the Ministry rather than competing with it. */
        .gov-title-container h2.dept-line {
            font-size: 12px; letter-spacing: .04em; text-transform: uppercase;
            margin-top: 3px; color: #333;
        }
        .memo-footer-logo {
            margin-top: 26px; padding-top: 12px;
            border-top: 1px solid #ddd; text-align: center;
        }
        .memo-footer-logo img { max-height: 46px; width: auto; object-fit: contain; }
        .gov-title-container h3 {
            font-size: 15px; margin: 8px 0 0;
            text-transform: uppercase; letter-spacing: 1.5px;
        }

        .memo-head {
            margin-top: 16px;
            font-size: 13px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .memo-head .row { display: flex; padding: 3px 0; }
        .memo-head .label {
            width: 34mm;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11.5px;
            letter-spacing: .04em;
        }

        .subject {
            margin: 16px 0 10px;
            font-size: 13.5px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
        }

        p.body-text { font-size: 13px; line-height: 1.75; text-align: justify; margin: 0 0 10px; }

        table.details {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 4px;
            font-size: 12.5px;
        }
        table.details th, table.details td {
            border: 1px solid #cfcfcf;
            padding: 7px 10px;
            text-align: left;
            vertical-align: top;
        }
        table.details th {
            background: #f4f4f0;
            width: 45mm;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .decision-block {
            margin-top: 16px;
            border: 1px solid #cfcfcf;
            padding: 12px 14px;
            font-size: 12.5px;
        }
        .decision-block h4 {
            margin: 0 0 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .decision-line { min-height: 9mm; border-bottom: 1px dotted #999; margin-bottom: 8px; }
        .stamp {
            display: inline-block;
            padding: 4px 14px;
            border: 2px solid;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 13px;
        }
        .stamp.approved { color: #15803d; border-color: #15803d; }
        .stamp.rejected { color: #b91c1c; border-color: #b91c1c; }

        .sign-row { display: flex; justify-content: space-between; margin-top: 22mm; font-size: 12px; }
        .sign-box { width: 68mm; text-align: center; }
        .sign-line { border-top: 1px solid #333; padding-top: 4px; }

        .toolbar { text-align: center; padding: 12px; }
        .toolbar button {
            padding: 8px 18px; font-size: 13px; border: none; border-radius: 6px;
            cursor: pointer; margin: 0 4px; background: rgb(186,191,12); color: #fff;
        }
        .toolbar .btn-close { background: #6b7280; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .a4-page { margin: 0; box-shadow: none; width: auto; min-height: auto; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button onclick="window.print()">Print Memo</button>
    <button class="btn-close" onclick="window.close()">Close</button>
</div>

<div class="a4-page">

    <div class="header-section">
        <div class="logo-box">
            <img src="{{ url('assets/logo/ministry2.png') }}" alt="Ministry Logo">
        </div>
        <div class="gov-title-container">
            <h1>Kano State Government</h1>
            <h2>Ministry of Land &amp; Physical Planning</h2>
            {{-- The issuing department. A SPAS memo lands on desks across the
                 Ministry, so the heading has to say which department raised
                 it, not only which Ministry. --}}
            <h2 class="dept-line">Department of Special Assignment</h2>
            <h3>Internal Memo</h3>
        </div>
        <div class="logo-box">
            <img src="{{ url('assets/logo/ministry1.jpg') }}" alt="Coat of Arms">
        </div>
    </div>

    <div class="memo-head">
        <div class="row"><span class="label">Memo No:</span><span><strong>{{ $memo->memo_no }}</strong></span></div>
        <div class="row"><span class="label">To:</span><span>{{ $memo->forwarded_to ?? '—' }}</span></div>
        <div class="row"><span class="label">From:</span><span>{{ optional($memo->preparedBy)->name ?? $memo->created_by ?? '—' }}</span></div>
        <div class="row"><span class="label">Date:</span><span>{{ $memo->forwarded_at?->format('d F Y') ?? '—' }}</span></div>
    </div>

    <p class="subject">
        Special Assignment — Change of Land Use: File No {{ $app->file_number ?? '—' }}
    </p>

    <p class="body-text">
        Following the special assignment field exercise, the property described below was
        assessed and its particulars established as recorded. The file is hereby forwarded
        for {{ $memo->forwarded_to ?? 'the Honourable Commissioner' }}'s consideration and approval.
    </p>

    <table class="details">
        <tr><th>File Number</th><td>{{ $app->file_number ?? '—' }}</td></tr>
        <tr><th>Owner</th><td>{{ $app->owner_name ?? '—' }}</td></tr>
        <tr><th>Location</th><td>{{ $app->location ?? '—' }}</td></tr>
        <tr><th>District / LGA</th><td>{{ $app->district ?? '—' }}{{ $app->lga ? ' / '.$app->lga : '' }}</td></tr>
        <tr><th>Approved Land Use</th><td>{{ $app->land_use_type ?? '—' }}</td></tr>
        <tr><th>Prevailing Use</th><td>{{ $app->existing_use ?? '—' }}</td></tr>
        <tr><th>Proposed Use</th><td>{{ $app->proposed_use ?? '—' }}</td></tr>
    </table>

    <div class="decision-block">
        <h4>Commissioner's Decision</h4>

        @if ($decided)
            <p style="margin:0 0 8px;">
                <span class="stamp {{ $decision }}">{{ $decision }}</span>
                <span style="margin-left:10px;">Dated: {{ $memo->decided_at?->format('d F Y') ?? '—' }}</span>
            </p>
            <p style="margin:0;"><strong>Remarks:</strong> {{ $memo->commissioner_notes ?: '—' }}</p>
        @else
            {{-- Pending: this is the copy taken in for signature, so it prints
                 ruled lines to write on rather than an empty box. --}}
            <div class="decision-line"></div>
            <div class="decision-line"></div>
            <p style="margin:0;font-size:11.5px;color:#666;">Approved / Not Approved (delete as appropriate)</p>
        @endif
    </div>

    <div class="sign-row">
        <div class="sign-box">
            <div class="sign-line">
                {{ optional($memo->preparedBy)->name ?? 'Preparing Officer' }}<br>
                <span style="font-size:11px;color:#555;">Prepared By</span>
            </div>
        </div>
        <div class="sign-box">
            <div class="sign-line">
                {{ $memo->forwarded_to ?? 'Honourable Commissioner' }}<br>
                <span style="font-size:11px;color:#555;">Signature &amp; Date</span>
            </div>
        </div>
    </div>

    {{-- Ministry mark at the foot of the printed page. Served from the live
         host rather than asset(), because these documents are printed and
         emailed from environments whose APP_URL is not app.klaes.ng. --}}
    <div class="memo-footer-logo">
        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="">
    </div>

</div>

</body>
</html>
