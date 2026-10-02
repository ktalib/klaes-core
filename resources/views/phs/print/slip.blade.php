<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLAES Official Search Slip</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f3f4f6; color: #111827; font-family: Georgia, "Times New Roman", Times, serif; padding: 32px; }
        .toolbar { margin: 0 auto 16px; max-width: 920px; text-align: right; }
        .toolbar button { background: #14532d; border: 0; border-radius: 6px; color: white; cursor: pointer; font: 700 13px Arial, sans-serif; padding: 10px 14px; }
        .slip { background: white; border: 1px solid #e5e7eb; box-shadow: 0 10px 40px rgba(15, 23, 42, .08); margin: 0 auto; max-width: 920px; position: relative; overflow: hidden; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 26px; font-weight: 900; letter-spacing: 3px; color: rgba(22, 101, 52, .18); white-space: nowrap; text-transform: uppercase; text-align: center; line-height: 1.3; pointer-events: none; z-index: 5; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .bg-watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 55%; max-width: 160px; opacity: .12; pointer-events: none; z-index: 4; mix-blend-mode: multiply; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .header { border-bottom: 3px solid #166534; padding: 20px 30px 16px; }
        .logo-container { align-items: center; display: flex; gap: 20px; justify-content: space-between; }
        .logo-box { align-items: center; border: 1px solid #d1d5db; border-radius: 10px; display: flex; height: 64px; justify-content: center; overflow: hidden; width: 64px; }
        .logo-box img { height: 100%; object-fit: contain; width: 100%; }
        .title-container { flex: 1; text-align: center; }
        .title-container h1 { color: #166534; font-size: 21px; letter-spacing: .5px; line-height: 1.25; }
        .title-container h3 { color: #374151; font-size: 13px; font-weight: normal; margin-top: 6px; }
        .content { padding: 24px 30px; }
        .info-section { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-bottom: 12px; }
        .info-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
        .info-box h4 { border-bottom: 2px solid #166534; color: #166534; display: inline-block; font-size: 12px; margin-bottom: 7px; padding-bottom: 4px; }
        .info-row { display: flex; font-size: 11px; gap: 12px; margin-bottom: 4px; }
        .info-label { color: #6b7280; flex: 0 0 120px; }
        .info-value { color: #111827; flex: 1; font-weight: 600; }
        .section-title { border-bottom: 2px solid #166534; color: #166534; display: inline-block; font-size: 15px; font-weight: bold; margin: 8px 0 14px; padding-bottom: 8px; }
        .timeline { border-left: 2px solid #d1d5db; margin-left: 8px; padding-left: 18px; }
        .timeline-row { margin-bottom: 14px; position: relative; }
        .timeline-row::before { background: #166534; border: 2px solid white; border-radius: 999px; box-shadow: 0 0 0 2px #d1d5db; content: ""; height: 10px; left: -24px; position: absolute; top: 2px; width: 10px; }
        .timeline-head { margin-bottom: 8px; }
        .tl-badge { background: #e8f5e9; border-radius: 20px; color: #166534; display: inline-block; font-size: 12px; font-weight: 600; padding: 2px 12px; }
        .tl-date { color: #9ca3af; font-size: 11px; margin-left: 10px; }
        .timeline-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
        .tl-parties { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; }
        .tl-party { flex: 1; min-width: 130px; }
        .tl-party .lbl { color: #6b7280; font-size: 10px; letter-spacing: .4px; text-transform: uppercase; }
        .tl-party .val { color: #111827; font-size: 13px; font-weight: 600; }
        .tl-arrow { color: #9ca3af; font-size: 16px; padding: 0 12px; }
        .tl-desc { border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 11px; line-height: 1.55; margin-top: 10px; padding-top: 8px; }
        .note { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; color: #78350f; font-size: 12px; line-height: 1.6; margin-top: 22px; padding: 12px; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; color: #6b7280; font-family: Arial, sans-serif; font-size: 11px; line-height: 1.6; padding: 15px 30px; text-align: center; }
        .footer-logos { align-items: center; display: flex; justify-content: space-between; gap: 20px; margin-top: 24px; padding: 12px 30px 24px; width: 100%; }
        .footer-logos .org-brand { align-items: center; display: flex; gap: 16px; flex: 1; }
        .footer-logos .klaes-brand { align-items: center; display: flex; }
        .footer-logos .klaes-brand img { height: 40px; width: auto; }
        .footer-logos .logo-box { border: 0; height: 70px; width: 70px; }
        .footer-logos .org-name { color: #166534; font-size: 12px; font-weight: bold; line-height: 1.2; white-space: nowrap; flex: 1; text-align: center; }
        .header, .content, .footer, .signature, .footer-logos { position: relative; z-index: 1; }
        .signature { display: flex; justify-content: space-between; align-items: flex-end; padding: 26px 30px 30px; }
        .qr-box { text-align: center; }
        .qr-box canvas, .qr-box img { display: block; }
        .qr-box .qr-label { color: #6b7280; font-family: Arial, sans-serif; font-size: 9px; letter-spacing: .4px; margin-top: 4px; }
        .sig-line { text-align: center; width: 250px; }
        .sig-img { display: block; height: 55px; max-width: 230px; margin: 0 auto 6px; object-fit: contain; }
        .sig-line hr { border: 0; border-top: 1px solid #111827; margin-bottom: 8px; }
        .disclaimer-page { margin-top: 32px; }
        .disclaimer-content { padding: 40px 44px 44px; }
        .disclaimer-title { border-bottom: 2px solid #166534; color: #166534; font-size: 24px; font-weight: bold; letter-spacing: .5px; margin-bottom: 30px; padding-bottom: 12px; text-align: center; text-transform: uppercase; }
        .disclaimer-body p { color: #1f2937; font-size: 17px; line-height: 2.05; margin-bottom: 26px; text-align: justify; }
        .disclaimer-body p:last-child { margin-bottom: 0; }
        .disclaimer-body strong { color: #7f1d1d; }
        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            html, body { background: white; padding: 0; margin: 0; }
            .toolbar { display: none; }
            .slip { border: 0; box-shadow: none; max-width: none; }

            /* Compact everything so short results stay on a single page */
            .header { padding: 10px 18px 8px; border-bottom-width: 2px; }
            .logo-box { height: 46px; width: 46px; }
            .title-container h1 { font-size: 16px; }
            .title-container h3 { font-size: 11px; margin-top: 3px; }
            .content { padding: 12px 18px; }
            .info-section { gap: 12px; margin-bottom: 12px; }
            .info-box { padding: 10px; }
            .info-box h4 { font-size: 12px; margin-bottom: 7px; padding-bottom: 4px; }
            .info-row { font-size: 11px; margin-bottom: 4px; }
            .section-title { font-size: 13px; margin: 4px 0 8px; padding-bottom: 5px; }
            .timeline { padding-left: 16px; }
            .timeline-row { margin-bottom: 9px; }
            .timeline-head { margin-bottom: 5px; }
            .tl-badge { font-size: 11px; padding: 1px 10px; }
            .timeline-card { padding: 9px; }
            .tl-party .val { font-size: 12px; }
            .tl-desc { font-size: 10px; margin-top: 7px; padding-top: 6px; }
            .note { font-size: 11px; line-height: 1.45; margin-top: 12px; padding: 9px; }
            .slip { min-height: auto; padding-bottom: 0; }
            .footer-logos { margin-top: 4px; padding: 4px 18px 2px; justify-content: space-between; }
            .footer-logos .logo-box { height: 50px; width: 50px; }
            .footer-logos .klaes-brand img { height: 42px; }
            .footer-logos .org-name { font-size: 12px; white-space: nowrap; }
            .footer { font-size: 10px; line-height: 1.4; padding: 6px 18px; }
            .signature { padding: 4px 18px 2px; }
            .sig-img { height: 46px; }

            /* Keep blocks intact across page breaks */
            .timeline-row, .info-box, .note, .footer, .footer-logos, .signature { break-inside: avoid; page-break-inside: avoid; }

            /* Disclaimer always starts on its own page, attached to the result(s) */
            .disclaimer-page { page-break-before: always; break-before: page; margin-top: 0; }
            .disclaimer-content { padding: 44px 40px; }
            .disclaimer-title { font-size: 24px; margin-bottom: 32px; padding-bottom: 12px; }
            .disclaimer-body p { font-size: 17px; line-height: 2.1; margin-bottom: 28px; }

            /* Watermarks: fixed positioning repeats on every printed page, centered */
            .bg-watermark {
                display: block;
                position: fixed;
                top: 50%; left: 50%;
                transform: translate(-50%, -50%);
                width: 55%; max-width: 400px;
                opacity: .12;
                z-index: 4;
                mix-blend-mode: multiply;
            }
            .watermark {
                position: fixed;
                top: 50%; left: 50%;
                transform: translate(-50%, -50%) rotate(-30deg);
                font-size: 26px;
                color: rgba(22, 101, 52, .18);
                z-index: 5;
            }
        }
        @media screen and (max-width: 760px) {
            body { padding: 12px; }
            .logo-container { align-items: flex-start; }
            .logo-box { height: 48px; width: 48px; }
            .title-container h1 { font-size: 15px; }
            .info-section { grid-template-columns: 1fr; }
            .content, .header, .footer { padding-left: 16px; padding-right: 16px; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print Slip</button></div>

    <article class="slip">
        <img class="bg-watermark" src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" alt="" aria-hidden="true">
        <div class="watermark" aria-hidden="true">PROPERTY HISTORY SEARCH (PHS) <br> &nbsp;&bull;&nbsp;  Not For Sale</div>
        <header class="header">
            <div class="logo-container">
                <div class="logo-box"><img src="http://app.klaes.ng/assets/logo/ministry2.jpeg" alt="Ministry Logo"></div>
                <div class="title-container">
                    <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                    <h3>PROPERTY HISTORY SEARCH (PHS) PORTAL OFFICIAL SEARCH SLIP</h3>
                </div>
                <div class="logo-box"><img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Ministry Logo"></div>
            </div>
        </header>

        <section class="content">
            <div class="info-section">
                <div class="info-box">
                    <h4>PROPERTY INFORMATION</h4>
                    <div class="info-row"><div class="info-label">File Number:</div><div class="info-value">{{ $data['file_number'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">Title Holder:</div><div class="info-value">{{ $data['file_title'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">District/LGA:</div><div class="info-value">{{ $data['district_lga'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">Land Use:</div><div class="info-value">{{ $data['land_use'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">Plot Number:</div><div class="info-value">{{ $data['plot_no'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">TP Number:</div><div class="info-value">{{ $data['tpno'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">Size:</div><div class="info-value">{{ $data['size'] ?? '-' }}</div></div>
                </div>

                <div class="info-box">
                    <h4>SEARCH DETAILS</h4>
                    <div class="info-row"><div class="info-label">Reference:</div><div class="info-value">{{ $reference_no ?: ($data['qr_data'] ?? '-') }}</div></div>
                    <div class="info-row"><div class="info-label">Search Date:</div><div class="info-value">{{ $data['date_line'] ?? now()->format('F j, Y') }}</div></div>
                    <div class="info-row"><div class="info-label">Institution:</div><div class="info-value">{{ $institution->name }}</div></div>
                    <div class="info-row"><div class="info-label">Generated By:</div><div class="info-value">{{ $member->name }}</div></div>
                    <div class="info-row"><div class="info-label">Tokens Consumed:</div><div class="info-value">1</div></div>
                    <div class="info-row"><div class="info-label">Verification:</div><div class="info-value">{{ $data['qr_data'] ?? '-' }}</div></div>
                </div>
            </div>

            <div class="section-title">TRANSACTION(S) TIMELINE ({{ count($data['rows'] ?? []) }})</div>
            @php
                $uniqueFileNumbers = collect($data['rows'] ?? [])
                    ->pluck('file_no')
                    ->filter(fn($fn) => $fn && $fn !== '-')
                    ->unique();
                $showFileNo = $uniqueFileNumbers->count() > 1;
            @endphp
            <div class="timeline">
                @forelse(($data['rows'] ?? []) as $row)
                    @php
                        $type = $row['transaction_type'] ?? $row['instrument_type'] ?? '-';
                        $date = $row['transaction_date'] ?? $row['reg_date'] ?? $row['deeds_date'] ?? '-';
                        $grantor = $row['grantor'] ?? $row['party_1'] ?? '-';
                        $grantee = $row['grantee'] ?? $row['party_2'] ?? '-';
                        $reg = $row['registration_particulars'] ?? $row['reg_no'] ?? null;
                        $comments = ($row['comments'] ?? null) && $row['comments'] !== '-' ? $row['comments'] : null;
                        $descParts = [];
                        if ($reg && $reg !== '-') { $descParts[] = 'Registration Particulars: ' . $reg; }
                        if ($comments) { $descParts[] = $comments; }
                    @endphp
                    <div class="timeline-row">
                        <div class="timeline-head">
                            <span class="tl-badge">{{ $type }}</span>
                            <span class="tl-date">{{ $date }}</span>
                        </div>
                        <div class="timeline-card">
                            <div class="tl-parties">
                                <div class="tl-party">
                                    <div class="lbl">From</div>
                                    @if($showFileNo && !empty($row['file_no']) && $row['file_no'] !== '-')
                                        <div class="tl-file-no" style="font-size: 11px; font-weight: bold; color: #4b5563; margin-bottom: 2px;">{{ $row['file_no'] }}</div>
                                    @endif
                                    <div class="val">{{ $grantor ?: '-' }}</div>
                                </div>
                                <div class="tl-arrow">&rarr;</div>
                                <div class="tl-party">
                                    <div class="lbl">To</div>
                                    <div class="val">{{ $grantee ?: '-' }}</div>
                                </div>
                            </div>
                            @if(count($descParts))
                                <div class="tl-desc">{{ implode(' · ', $descParts) }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="timeline-row"><div class="timeline-card"><div class="tl-party"><div class="val">No timeline rows available.</div></div></div></div>
                @endforelse
            </div>
            
            <div style="text-align: center; font-size: 10px; font-weight: bold; font-style: italic; margin: 16px 0 10px 0; color: #374151; letter-spacing: 0.5px; border-top: 1px dashed #d1d5db; padding-top: 8px;">
                *** END OF TRANSACTION HISTORY ***
            </div>

            @php
                $adverseCaveat = !empty($data['is_caveated']) || !empty($data['under_investigation']) || !empty($data['is_flagged']);
            @endphp
            <div class="note" style="background:#ffffff;border:1px solid #e5e7eb;color:#111827;margin-top:22px;">
                <div style="font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:.04em;margin-bottom:6px;color:#374151;">Remarks</div>
                @if(!empty($data['caveat_note']))<div style="color:{{ $adverseCaveat ? '#b91c1c' : '#166534' }};font-weight:600;">{{ $data['caveat_note'] }}</div>@endif
                @if(!empty($data['wrc_comment']))<div style="color:#b91c1c;font-weight:600;margin-top:4px;">{{ $data['wrc_comment'] }}</div>@endif
                @if(!empty($data['cofo_comment']))<div style="color:#166534;font-weight:600;margin-top:4px;">{{ $data['cofo_comment'] }}</div>@endif
                @if(!empty($data['ground_rent']))<div style="color:#92400e;font-weight:600;margin-top:4px;">{{ $data['ground_rent'] }}</div>@endif
                @if(!empty($data['litigation_comment']))<div style="color:#b91c1c;font-weight:600;margin-top:4px;">{{ $data['litigation_comment'] }}</div>@endif
                @if(!empty($data['no_cofo_comment']))<div style="color:#166534;font-weight:600;margin-top:4px;">{{ $data['no_cofo_comment'] }}</div>@endif
                @if(!empty($data['encumbrance_comment']))<div style="color:#166534;font-weight:600;margin-top:4px;">{{ $data['encumbrance_comment'] }}</div>@endif
            </div>
        </section>

        <footer class="footer">
            <p>This is an electronically generated official search slip. Verification can be made at www.klaes.gov.ng/verify</p>
          
            <p>{{ (!empty($data['full_name']) && !empty($institution->name))
                    ? str_replace($data['full_name'], $data['full_name'] . '/' . $institution->name, $data['generated_by'] ?? '')
                    : ($data['generated_by'] ?? '') }}</p>
        </footer>

        <div class="signature">
            <div class="qr-box">
                <div id="qr-code"></div>
                <div class="qr-label"></div>
            </div>
            <div class="sig-line">
                @if(!empty($authorizedSignatureSrc))
                    <img class="sig-img" src="http://app.klaes.ng/storage/signing_officer_signatures/S3AuwOc91sjQfIYVtLuSlu3aPJ78DdR4JZcWP0Xf.jpg" alt="Authorized Signatory Signature">
                @endif
                <hr>
                {{-- <p style="font-size:11px;" class='date'>{{ $data['generated_date'] ?? '' }}</p> --}}
                <p style="font-size:11px;">Authorized Signatory</p>
            </div>
        </div>
<br><br><br><br><br><br><br><br><br><br><br>
        <div class="footer-logos">
            <div class="org-brand">
                <div class="logo-box">
                    @if(!empty($institution->logo_path))

                      <img src="{{ asset('storage/' . $institution->logo_path) }}" alt="{{ $institution->name }} Logo">

                      
                        {{-- <img src="{{ asset('storage/' . $institution->logo_path) }}" alt="{{ $institution->name }} Logo"> --}}
                    @else
                        <img src="http://app.klaes.ng/storage/upload/logo/Klase.png" alt="Organization Logo">
                    @endif
                </div>
                <div class="org-name" >For {{ $institution->name }} Authorized by Kano State Ministry Of Land and Physical Planning</div>
            </div>
            <div class="klaes-brand">
                <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" alt="PHS Portal">
            </div>
        </div>
    </article>

    <article class="slip disclaimer-page">
        {{-- <img class="bg-watermark" src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" alt="" aria-hidden="true">
        <div class="watermark" aria-hidden="true">PROPERTY HISTORY SEARCH (PHS) <br> &nbsp;&bull;&nbsp;  Not For Sale</div>
        <header class="header">
            <div class="logo-container">
                <div class="logo-box"><img src="http://app.klaes.ng/assets/logo/ministry2.jpeg" alt="Ministry Logo"></div>
                <div class="title-container">
                    <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                    <h3>PROPERTY HISTORY SEARCH (PHS) PORTAL OFFICIAL SEARCH SLIP</h3>
                </div>
                <div class="logo-box"><img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Ministry Logo"></div>
            </div>
        </header> --}}

        <section class="disclaimer-content">
            <div class="disclaimer-title">Disclaimer and Legal Notice</div>
            <div class="disclaimer-body">
                <p>The Property History Search (PHS) Portal and Online Legal Search results provided by the Kano State Ministry of Land and Physical Planning are generated strictly for informational and preliminary due diligence purposes. The Ministry shall not be held liable or responsible for any loss, damage, or legal consequences arising from the reliance on, alteration, forgery, or misrepresentation of any search report or land document generated through this platform. All genuine reports contain unique verification codes and QR codes; it is the sole responsibility of the user to verify the authenticity of any document via the Ministry&rsquo;s official verification portal before making any financial or legal decisions.</p>
                <p>Please be strictly advised that any attempt to <strong>alter, forge, tamper with, or misuse</strong> any search result or land document issued by or accessible through the Ministry is a severe criminal offense. Such acts are punishable under the <strong>Criminal Code Act</strong>, the <strong>Cybercrimes (Prohibition, Prevention, etc.) Act 2015</strong>, and the <strong>Land Use Act of 1978</strong>. The Ministry actively monitors system access and will promptly report any suspected fraudulent activities to law enforcement agencies for full prosecution to the maximum extent of the law.</p>
            </div>
        </section>

        <footer class="footer">
            <p>This is an electronically generated official search slip. Verification can be made at www.klaes.gov.ng/verify</p>
        </footer>
    </article>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        (function () {
            var qrText = @json($data['qr_data'] ?? '');
            var el = document.getElementById('qr-code');
            if (el && qrText && typeof QRCode !== 'undefined') {
                new QRCode(el, {
                    text: qrText,
                    width: 50,
                    height: 50,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        })();
    </script>
</body>
</html>
