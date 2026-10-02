{{--
    Online Legal Search report, emailed to the requester as a PDF once a
    Director / Deputy Director approves the request.

    This is a DomPDF-safe port of the current on-premise template,
    resources/views/legal_search/templates/PAY-PER-SEARCH.html — same columns,
    same Property Details sections, same remarks/signature/footer blocks. Two
    things could not carry over verbatim and are deliberately reworked:

      * PAY-PER-SEARCH lays out Property Details with CSS grid and uses flexbox
        throughout; DomPDF supports neither, so both are rebuilt as tables.
      * Its column hiding, dynamic comment spans and QR are done in JavaScript
        against the fetched payload; DomPDF runs no JS, so the same rules are
        evaluated here in PHP against the same buildPrintReport() payload.

    Keep this file in step with PAY-PER-SEARCH.html when that template changes.
--}}
@php
    use App\Support\QrPng;

    // DomPDF reads local files directly; skip any logo that is not deployed.
    $logo = function (string $relative) {
        $path = public_path($relative);
        return is_file($path) ? $path : null;
    };

    $logoLeft  = $logo('assets/logo/ministry1.jpg');
    // Prefer the 104KB .jpeg over the visually identical 401KB .png that
    // PAY-PER-SEARCH.html loads from the CDN — this one is embedded in an
    // email attachment, so the weight is charged to every recipient.
    $logoRight = $logo('assets/logo/ministry2.jpeg') ?: $logo('assets/logo/ministry2.png');
    // Online Legal Search brand mark. The 480px print variant is preferred over
    // the 1536px original: it renders at ~92px here, and the full-resolution
    // file added ~190KB to an attachment charged to every recipient.
    $logoBrand = $logo('assets/logo/online_ls_print.jpeg')
        ?: $logo('assets/logo/online_ls.jpeg')
        ?: $logo('storage/upload/logo/logo.png');
    // Footer mark. Left_Logo.png replaced las.jpg on 2026-08-26; the old file is still on
    // disk and still used by other modules, so this is not a global swap.
    $logoLas   = $logo('assets/logo/Left_Logo.png') ?: $logo('assets/logo/las.jpg');

    $rows = $report['rows'] ?? [];

    $val = function ($v) {
        $v = is_scalar($v) ? trim((string) $v) : '';
        return $v !== '' ? $v : '-';
    };

    // ── Column visibility, mirroring renderTransactions() in PAY-PER-SEARCH.html ──
    $hasParty3 = collect($rows)->contains(fn ($r) => trim((string) ($r['party_3'] ?? '')) !== ''
        && trim((string) ($r['party_3'] ?? '')) !== '-');
    $hasParty4 = collect($rows)->contains(fn ($r) => trim((string) ($r['party_4'] ?? '')) !== ''
        && trim((string) ($r['party_4'] ?? '')) !== '-');

    // File No is pure repetition unless the history spans more than one file.
    $distinctFileNos = collect($rows)
        ->map(fn ($r) => strtoupper(preg_replace('/[\s\-_=\/]+/', '', (string) ($r['file_no'] ?? ''))))
        ->filter()
        ->unique();
    $showFileNo = $distinctFileNos->count() > 1;

    // Widths from the source template; dropped columns give their share back
    // proportionally so the table always totals 100%.
    $columns = array_values(array_filter([
        ['key' => 'sn',               'label' => 'S/N',                        'w' => 3],
        $showFileNo ? ['key' => 'file_no',          'label' => 'File No',                    'w' => 11] : null,
        ['key' => 'instrument_type',  'label' => 'Instrument/<br>Transaction Type', 'w' => 10],
        ['key' => 'grantor',          'label' => 'Party 1',                    'w' => 11],
        ['key' => 'grantee',          'label' => 'Party 2',                    'w' => 11],
        $hasParty3 ? ['key' => 'party_3',           'label' => 'Party 3',                    'w' => 6]  : null,
        $hasParty4 ? ['key' => 'party_4',           'label' => 'Party 4',                    'w' => 6]  : null,
        ['key' => 'transaction_date', 'label' => 'Transaction Date',           'w' => 9],
        ['key' => 'reg',              'label' => 'Reg. Time/Date',             'w' => 9],
        ['key' => 'reg_no',           'label' => 'Particulars',                'w' => 9],
        ['key' => 'caveat',           'label' => 'Caveat',                     'w' => 5],
        ['key' => 'comments',         'label' => 'Comments',                   'w' => 10],
    ]));

    // Scale the surviving columns back up to exactly 100%: round each in turn
    // and hand the accumulated rounding remainder to the last one, so the row
    // never totals 100.02% and overflows the fixed table.
    $totalWeight = array_sum(array_column($columns, 'w')) ?: 1;
    $used = 0.0;
    $lastIndex = count($columns) - 1;
    foreach ($columns as $i => $col) {
        $width = $i === $lastIndex
            ? round(100 - $used, 2)
            : round($col['w'] / $totalWeight * 100, 2);
        $columns[$i]['width'] = $width;
        $used += $width;
    }

    // Commencement date carries its source in brackets, as the template does.
    $commencement = ($report['commencement_date'] ?? null) && ($report['commencement_source'] ?? null)
        ? $report['commencement_date'] . ' (' . $report['commencement_source'] . ')'
        : ($report['commencement_date'] ?? null);

    // Term falls back to the land-use default, matching termFromLandUse().
    $term = trim((string) ($report['term'] ?? ''));
    if ($term === '') {
        $landUse = strtoupper((string) ($report['land_use'] ?? ''));
        if (str_contains($landUse, 'RESIDENT') || str_starts_with($landUse, 'RES')
            || str_contains($landUse, 'AGRIC') || str_starts_with($landUse, 'AG')) {
            $term = '99 Years';
        } elseif (str_contains($landUse, 'COMMERC') || str_starts_with($landUse, 'COM')
            || str_contains($landUse, 'INDUSTR') || str_starts_with($landUse, 'IND')) {
            $term = '40 Years';
        }
    }

    $titleCase = fn ($v) => trim((string) $v) === '' ? '' : ucwords(strtolower(trim((string) $v)));

    // The paying requester is the client on an online search.
    $clientName    = $titleCase($report['client_name'] ?? '') ?: $searchRequest->requester_email;
    $clientAddress = $titleCase($report['client_address'] ?? '');

    // Remarks: the same spans the template reveals one by one, in its order.
    $remarkParts = [];
    foreach ([
        ['ground_rent',         '#b05c00'],
        ['encumbrance_comment', '#166534'],
        ['litigation_comment',  '#be123c'],
        ['no_cofo_comment',     '#166534'],
        ['wrc_comment',         '#dc2626'],
        ['cofo_comment',        '#166534'],
    ] as [$key, $color]) {
        $text = trim((string) ($report[$key] ?? ''));
        if ($text !== '') {
            $remarkParts[] = ['text' => str_ends_with($text, '.') ? $text : $text . '.', 'color' => $color];
        }
    }

    $caveatNote = trim((string) ($report['caveat_note'] ?? ''));
    if ($caveatNote !== '') {
        $flagged = ($report['under_investigation'] ?? false) || ($report['is_caveated'] ?? false) || ($report['is_flagged'] ?? false);
        $remarkParts[] = ['text' => $caveatNote, 'color' => $flagged ? '#dc2626' : '#166534'];
    }

    $generalComment = trim((string) ($report['general_comment'] ?? ''));

    $qr = QrPng::dataUri($report['qr_data'] ?? null);

    $signDate = optional($searchRequest->reviewed_at)->format('d/m/Y') ?: now()->format('d/m/Y');

    // Issued on approval; a preview of a not-yet-approved request dates from today.
    $issuedAt  = $searchRequest->reviewed_at ?: now();
    $expiresOn = $issuedAt->copy()->addDays(30)->format('F j, Y');

    // Supplied by LegalSearchApprovalService::renderPdf(); absent when the view
    // is rendered directly, in which case the signature line stays blank.
    $signature = $signature ?? null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KANGIS - Official Search Report (Online Copy)</title>
    <style>
        @page { margin: 0.2in; }
        /* Helvetica is a DomPDF core font, so nothing is embedded and the
           attachment stays small; the report is plain Latin text throughout. */
        body { font-family: Helvetica, Arial, sans-serif; color: #000; font-size: 10px; margin: 0; }

        /* ── Header ── */
        .hdr { width: 100%; border-bottom: 2px solid #000; padding-bottom: 2px; margin-bottom: 6px; }
        .hdr td { vertical-align: middle; }
        .hdr .logo { width: 78px; }
        .hdr .logo img { width: 68px; }
        .hdr .qr { width: 52px; text-align: right; }
        .hdr .qr img { width: 44px; height: 44px; }
        .hdr .title { text-align: center; }
        .hdr .title h1 { font-size: 15px; color: #007a33; margin: 0; font-weight: bold; }
        .hdr .title h2 { font-size: 12.5px; margin: 2px 0; font-weight: bold; }
        .hdr .title h3 { font-size: 11px; margin: 0; text-decoration: underline; font-weight: bold; }

        .section-label { border: 1px solid #000; display: inline-block; padding: 1px 6px; font-weight: bold; font-size: 10px; margin: 3px 0; }

        /* ── Property Details ──
           PAY-PER-SEARCH uses a CSS grid with spacer columns carrying a vertical
           rule; DomPDF has no grid, so the same four label+value sections are a
           table whose divider cells keep the border-left. */
        .prop { width: 100%; border-collapse: collapse; font-size: 10.5px; margin-bottom: 5px; table-layout: fixed; }
        .prop td { padding: 2px 0; vertical-align: top; }
        .prop .lbl { font-weight: bold; padding-right: 8px; }
        .prop .val { overflow-wrap: break-word; word-wrap: break-word; padding-right: 8px; }
        .prop .sp { border-left: 0.5px solid #000; width: 12px; }

        /* ── Transactions ── */
        .tx { width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; margin-bottom: 2px; }
        .tx th, .tx td { padding: 2px 4px; border-bottom: 0.5px solid #eee; word-wrap: break-word; line-height: 1.2; }
        .tx th { font-weight: bold; border-bottom: 1.5px solid #000; text-align: left; vertical-align: top; }
        .tx td { vertical-align: middle; }
        .tx tr { page-break-inside: avoid; }
        .tx thead { display: table-header-group; }
        .tx .nowrap { white-space: nowrap; }
        .end-notice { text-align: center; font-size: 9px; font-weight: bold; font-style: italic; margin: 4px 0 6px; page-break-inside: avoid; }

        /* ── Footer blocks ── */
        .footer-wrapper { margin-top: 8px; width: 100%; page-break-inside: avoid; }
        .remarks { width: 100%; border-collapse: collapse; border-top: 1px solid #000; margin-top: 6px; }
        .remarks td { padding-top: 6px; vertical-align: top; }
        .remarks .rl { border: 1px solid #000; padding: 2px 8px; font-weight: bold; font-size: 12px; width: 62px; }
        .remarks .rc { padding-left: 12px; font-size: 12px; font-weight: bold; line-height: 1.4; }
        .remarks .general { margin: 6px 0 0; font-size: 12px; font-weight: bold; color: #1f2937; }

        .sig { width: 100%; margin-top: 4px; border-collapse: collapse; page-break-inside: avoid; }
        .sig td { width: 33.33%; font-size: 10px; line-height: 1.35; padding-right: 20px; vertical-align: top; }
        .sig .line { border-bottom: 1px dotted #000; padding: 0 4px; }
        .sig .row-gap { padding-bottom: 9px; }
        /* The signature sits on the dotted rule rather than replacing it. */
        .sig .sig-img img { height: 26px; max-width: 120px; vertical-align: bottom; }

        .disclaimer { font-size: 9px; font-style: italic; font-weight: bold; text-align: center; margin: 4px 0 2px; color: #333; }
        .validity { font-size: 10px; font-weight: bold; text-align: center; margin: 6px 0 0; color: #b45309; }
        .foot { width: 100%; border-collapse: collapse; border-top: 1px solid #000; padding-top: 4px; margin-top: 4px; }
        .foot td { vertical-align: middle; padding-top: 4px; }
        .foot img { height: 24px; }
        /* Wide 2.7:1 lockup — sized on width so it does not tower over the row. */
        .foot img.brand { height: 30px; width: auto; }
        .foot .mid { text-align: center; font-size: 9px; color: #333; }
        .foot .r { text-align: right; }

        /* ── Page 2: Disclaimer and Terms of Use ── */
        .disclaimer-page { page-break-before: always; }
        .dp-head { text-align: center; border-bottom: 2px solid #166534; padding-bottom: 4px; margin-bottom: 6px; }
        .dp-head h1 { font-size: 13px; margin: 0; letter-spacing: .5px; }
        .dp-sub { font-size: 9px; color: #4b5563; margin: 2px 0 0; }
        .dp-notice { font-size: 9px; font-weight: bold; color: #7f1d1d; letter-spacing: .5px; margin: 3px 0 0; }
        .dp-cols { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .dp-col { width: 50%; vertical-align: top; padding: 0 12px; font-size: 7.1px; line-height: 1.32; text-align: justify; }
        .dp-col h2 { font-size: 7.8px; color: #166534; text-transform: uppercase; letter-spacing: .3px;
                     margin: 6px 0 2px; border-bottom: 0.5px solid #d1d5db; padding-bottom: 1px; }
        .dp-col p { margin: 0 0 3px; }
        .dp-red { color: #7f1d1d; font-weight: bold; }
        .dp-contact { background: #f9fafb; border: 0.5px solid #e5e7eb; padding: 4px 6px; }
        .dp-tagline { text-align: center; color: #166534; font-style: italic; }
    </style>
</head>
<body>

{{-- Header --}}
<style>
    /* A bordered label card rather than loose text: the box is what stops it
       reading as part of the letterhead. Centred above the Ministry name.
       DomPDF handles inline-block + border reliably; the outer div does the
       centring because DomPDF will not centre an inline-block via margin auto. */
    .preview-mark-wrap {
        text-align: center;
        margin: 0 0 3px 0;
    }
    .preview-mark {
        display: inline-block;
        color: #c1121f;
        background: #fff5f5;
        border: 1.5px solid #c1121f;
        border-radius: 3px;
        font-weight: bold;
        font-size: 11px;
        letter-spacing: 2px;
        padding: 2px 10px;
        line-height: 1.2;
    }
</style>
<table class="hdr">
    <tr>
        <td class="logo">@if($logoLeft)<img src="{{ $logoLeft }}" alt="">@endif</td>
        <td class="title">
            {{-- Sits ABOVE the Ministry name so it is read before the document is,
                 and is impossible to mistake for part of the letterhead. Only
                 rendered when the caller asked for a preview: the copy emailed to
                 the requester must never carry it. --}}
            @if ($isPreview ?? false)
                <div class="preview-mark-wrap"><span class="preview-mark">FOR PREVIEW ONLY</span></div>
            @endif
            <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
            <h2>LEGAL SEARCH REPORT</h2>
            <h3>Online Pay-per-Search</h3>
        </td>
        <td class="qr">@if($qr)<img src="{{ $qr }}" alt="">@endif</td>
        <td class="logo" style="text-align:right;">@if($logoRight)<img src="{{ $logoRight }}" alt="">@endif</td>
    </tr>
</table>

{{-- Property Details --}}
<div class="section-label">Property Details</div>
<table class="prop">
    <colgroup>
        <col style="width:14%"><col style="width:14%"><col style="width:1%">
        <col style="width:10%"><col style="width:12%"><col style="width:1%">
        <col style="width:12%"><col style="width:10%"><col style="width:1%">
        <col style="width:11%"><col style="width:14%">
    </colgroup>
    <tr>
        <td class="lbl">File Number:</td>
        <td class="val"><strong>{{ $val($report['file_number'] ?? null) }}</strong></td>
        <td class="sp"></td>
        <td class="lbl">Lon/Lat:</td>
        <td class="val"><strong>{{ $val($report['lon_lat'] ?? null) }}</strong></td>
        <td class="sp"></td>
        <td class="lbl">Term:</td>
        <td class="val"><strong>{{ $val($term) }}</strong></td>
        <td class="sp"></td>
        <td class="lbl">Client Name:</td>
        <td class="val"><strong>{{ $val($clientName) }}</strong></td>
    </tr>
    <tr>
        <td class="lbl">File Title (Current Holder):</td>
        <td class="val"><strong>{{ $val(strtoupper((string) ($report['file_title'] ?? ''))) }}</strong></td>
        <td class="sp"></td>
        <td class="lbl">Plot No:</td>
        <td class="val"><strong>{{ $val($report['plot_no'] ?? null) }}</strong></td>
        <td class="sp"></td>
        <td class="lbl">Commencement Date:</td>
        <td class="val"><strong>{{ $val($commencement) }}</strong></td>
        <td class="sp"></td>
        <td class="lbl">Client Address:</td>
        <td class="val"><strong>{{ $val($clientAddress) }}</strong></td>
    </tr>
    <tr>
        <td class="lbl">Land Use:</td>
        <td class="val"><strong>{{ $val($report['land_use'] ?? null) }}</strong></td>
        <td class="sp"></td>
        <td class="lbl">Size:</td>
        <td class="val"><strong>{{ $val($report['size'] ?? null) }}</strong></td>
        <td class="sp"></td>
        <td class="lbl">Residual Term:</td>
        <td class="val"><strong>{{ $val($report['residual_term'] ?? null) }}</strong></td>
        <td class="sp"></td>
        <td class="val" colspan="2"><strong>{{ $report['date_line'] ?? 'Date: ' . now()->format('F j, Y') }}</strong></td>
    </tr>
    <tr>
        <td class="lbl">Plot Description:</td>
        <td class="val"><strong>{{ $val($report['plot_description'] ?? null) }}</strong></td>
        <td class="sp"></td>
        <td class="lbl">TP No:</td>
        <td class="val"><strong>{{ $val($report['tpno'] ?? null) }}</strong></td>
        <td class="sp"></td>
        <td class="lbl"></td><td class="val"></td>
        <td class="sp"></td>
        {{-- Root of Title, directly beneath the Date line in the same column. Rendered
             only when the file has one on record, rather than printing an empty label. --}}
        @if (!empty($report['root_of_title']))
            {{-- Styled inline: this template has no .root-of-title rule, and the PDF
                 renderer does not inherit the on-screen stylesheet. Bold italic indigo,
                 matching how the HTML reports print it. --}}
            <td class="val" colspan="2"
                style="font-weight:700;font-style:italic;color:#6d28d9;">Root of Title: {{ $report['root_of_title'] }}</td>
        @else
            <td class="lbl"></td><td class="val"></td>
        @endif
    </tr>
</table>

{{-- File History --}}
<div class="section-label">File History</div>
<table class="tx">
    <thead>
        <tr>
            @foreach($columns as $col)
                <th style="width:{{ $col['width'] }}%" @class(['nowrap' => in_array($col['key'], ['file_no', 'instrument_type'], true)])>
                    {!! $col['label'] !!}
                </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            <tr>
                @foreach($columns as $col)
                    @if($col['key'] === 'sn')
                        <td>{{ $row['sn'] ?? $loop->parent->iteration }}</td>
                    @elseif($col['key'] === 'reg')
                        <td>
                            <strong>{{ $val($row['reg_time'] ?? null) }}</strong><br>{{ $val($row['reg_date'] ?? null) }}
                        </td>
                    @elseif($col['key'] === 'reg_no')
                        <td>{{ $row['reg_no'] ?? '0/0/0' }}</td>
                    @elseif($col['key'] === 'file_no')
                        <td class="nowrap">{{ $val($row['file_no'] ?? null) }}</td>
                    @else
                        <td>{{ $val($row[$col['key']] ?? null) }}</td>
                    @endif
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) }}" style="text-align:center;padding:12px;">No transactions on record for this file.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="end-notice">*** END OF TRANSACTION HISTORY ***</div>

{{-- Remarks / signatures / disclaimer --}}
<div class="footer-wrapper">
    <table class="remarks">
        <tr>
            <td class="rl">Remarks</td>
            <td class="rc">
                @forelse($remarkParts as $part)
                    <span style="color:{{ $part['color'] }};">{{ $part['text'] }}</span>
                @empty
                    <span style="color:#0f766e;">Based on our available records, the title is free from encumbrances.</span>
                @endforelse

                @if($generalComment)
                    <p class="general">{{ str_ends_with($generalComment, '.') ? $generalComment : $generalComment . '.' }}</p>
                @endif
            </td>
        </tr>
    </table>

    <table class="sig">
        <tr>
            <td>
                <div class="row-gap">Name: <span class="line">{{ $val($report['full_name'] ?? null) }}</span></div>
                <div class="row-gap">Rank: <span class="line">{{ $val($report['rank'] ?? null) }}</span></div>
                <div>Sign:............................ Date: <span class="line">{{ $signDate }}</span></div>
            </td>
            <td>
                <div class="row-gap">Verified by:.......................................</div>
                <div class="row-gap">Rank:...............................................</div>
                <div>Sign:............................ Date:................</div>
            </td>
            <td>
                <div class="row-gap">
                    Sign:
                    @if($signature)
                        <span class="line sig-img"><img src="{{ $signature }}" alt=""></span>
                    @else
                        <span class="line">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                    @endif
                    &nbsp;Date: <span class="line">{{ $signDate }}</span>
                </div>
                <div style="font-weight:bold">{{ $searchRequest->reviewer_name ?: 'Director Deeds' }}</div>
                <div style="font-size:9px">{{ $searchRequest->reviewer_rank ?: 'Director Deeds' }} (for Permanent Secretary/Hon. Commissioner)</div>
            </td>
        </tr>
    </table>

    <p class="validity">Valid for 30 days from date of issue — expires {{ $expiresOn }}.</p>

    <p class="disclaimer">
        N.B: This search report is deduced based on the available records from the file and does not represent any
        document in possession of any body. This is a single-use paid search report for File No:
        {{ $val($report['file_number'] ?? null) }}, generated as at
        {{ preg_replace('/^Date:\s*/i', '', (string) ($report['date_line'] ?? now()->format('F j, Y'))) }}.
    </p>
</div>

{{-- Footer --}}
<table class="foot">
    <tr>
        <td style="width:100px;">@if($logoBrand)<img class="brand" src="{{ $logoBrand }}" alt="">@endif</td>
        <td class="mid">
            {{ $report['generated_by'] ?? 'Generated via Online Legal Search' }} —
            request {{ $searchRequest->request_no }}, issued to {{ $searchRequest->requester_email }}.
        </td>
        <td class="r" style="width:80px;">@if($logoLas)<img src="{{ $logoLas }}" alt="">@endif</td>
    </tr>
</table>

{{-- Page 2: Disclaimer and Terms of Use, included on every issued report. --}}
@include('online_legal_search.print.disclaimer_page')

</body>
</html>
