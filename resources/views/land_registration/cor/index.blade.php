@extends(request()->boolean('embedded') ? 'layouts.bare' : 'layouts.app')
@section('page-title')
    {{ __('Confirmation Of Instrument Registration') }}
@endsection

@section('content')

    {{--
        Confirmation of Registration of Instrument — Land Registry edition.

        Same slip layout as the Deeds certificate (four to an A4 sheet, QR code
        carrying the file number), differing in what the registry says about
        itself:

          - the header reads LAND REGISTRY, not DEEDS REGISTRY/DEPARTMENT
          - the red box cites the Land Registry
          - the bottom carries the registry's own stamp — DEEDS OF SALE
            REGISTERED ... DIRECTOR LAND — in place of REGISTRAR OF DEEDS

        The stamp is set as text rather than a scan of the rubber stamp, so the
        particulars print filled in and stay sharp at slip size. Its wording
        lives in config('land_registration.stamp').

        Deliberately absent: the multi-property mortgage lookup the Deeds
        certificate does against consent_applications. This registry has no
        consents, and a purchase is always one property.
    --}}

    @php
        use BaconQrCode\Renderer\ImageRenderer;
        use BaconQrCode\Renderer\Image\SvgImageBackEnd;
        use BaconQrCode\Renderer\RendererStyle\RendererStyle;
        use BaconQrCode\Writer;

        $authority = config('land_registration.authority');
        $stamp = config('land_registration.stamp');

        $fileno = request()->get('fileno');
        $idParam = request()->get('id');
        $urlParam = request()->get('url');

        if (!$idParam && $urlParam && preg_match('/id=([A-Za-z0-9\_]+)/', $urlParam, $matches)) {
            $idParam = $matches[1];
        }

        /*
         * This certificate is built from deed_registrations directly rather than
         * from whatever $data the shared controller happens to hand over.
         *
         * CoroiController resolves records through several paths, and the shape
         * it produces varies: the deed_registrations path SELECTs a narrow set of
         * columns (no grantor, no addresses, no capture link) and stamps
         * data_source='database' on it, so a template that trusted that object
         * would read undefined properties the moment it wanted the vendor or the
         * solicitor. Resolving here keeps the certificate independent of which
         * branch upstream ran.
         */
        $row = null;
        try {
            if (!empty($idParam)) {
                $realId = str_starts_with((string) $idParam, 'deed_reg_') ? substr($idParam, 9) : $idParam;

                if (is_numeric($realId)) {
                    $row = DB::connection('sqlsrv')->table('deed_registrations')
                        ->where('id', $realId)
                        ->where('instrument_type', config('land_registration.instrument_type'))
                        ->first();
                }
            }

            if (!$row && !empty($fileno)) {
                $row = DB::connection('sqlsrv')->table('deed_registrations')
                    ->where('instrument_type', config('land_registration.instrument_type'))
                    ->where('fileno', $fileno)
                    ->orderBy('id', 'desc')
                    ->first();
            }
        } catch (\Exception $e) {
            \Log::error('Land CoR lookup failed: ' . $e->getMessage());
        }

        // Addresses and the solicitor live on the capture, not the registration.
        $capture = null;
        if ($row && !empty($row->instrument_capture_id)) {
            $capture = DB::connection('sqlsrv')->table('instrument_capture')
                ->where('id', $row->instrument_capture_id)
                ->first();
        }

        // Anything the upstream controller resolved is used only as a fallback,
        // and every read of it is null-safe.
        $upstream = (isset($data) && is_object($data)) ? $data : null;

        $dateSource = $row->deeds_date ?? $row->instrument_date ?? $upstream->deeds_date ?? null;
        $timeSource = $row->deeds_time ?? null;
        if (!$timeSource && $dateSource) {
            $timeSource = date('H:i:s', strtotime($dateSource));
        }

        /*
         * One normalised object with EVERY field the template below reads, so a
         * missing column can never surface as an undefined-property error on a
         * document being printed at the counter. An unknown value prints blank.
         */
        $cor = (object) [
            'instrument_type' => $row->instrument_type
                ?? $upstream->instrument_type
                ?? config('land_registration.instrument_type'),
            'vendor' => $row->grantor ?? $upstream->Grantor ?? '',
            'purchaser' => $row->grantee ?? $upstream->Grantee ?? $upstream->Applicant_Name ?? '',
            'vendor_address' => $capture->party_1_address ?? '',
            'purchaser_address' => $capture->party_2_address ?? '',
            'solicitor_name' => $capture->solicitor_name ?? '',
            'solicitor_address' => $capture->solicitor_address ?? '',
            'serial_no' => $row->serial_no ?? $upstream->serial_no ?? '-',
            'page_no' => $row->page_no ?? $upstream->page_no ?? '-',
            'volume_no' => $row->volume_no ?? $upstream->volume_no ?? '-',
            'deeds_date' => $dateSource,
            'formatted_time' => $timeSource ? date('g:i A', strtotime($timeSource)) : '-',
            'time_part' => $timeSource ? date('A', strtotime($timeSource)) : 'AFTERNOON',
            'fileno' => $row->fileno ?? $upstream->fileno ?? $fileno ?? '',
            'stm_ref' => $row->STM_Ref ?? $upstream->STM_Ref ?? '',
        ];

        // The QR carries the file number, which is what a verifier looks up.
        $renderer = new ImageRenderer(new RendererStyle(100), new SvgImageBackEnd());
        $qrCodeSvg = (new Writer($renderer))->writeString($cor->fileno ?: 'N/A');

        /*
         * Who delivered the deed for registration: PARTY 2, the purchaser.
         *
         * This used to prefer the solicitor and fall back to the purchaser,
         * copied from the Deeds Assignment rule. That is not what this registry
         * wants — the certificate names the purchaser, and a deed prepared by a
         * firm was printing the firm's name and address instead.
         */
        $deliveryName = $cor->purchaser;
        $deliveryAddress = $cor->purchaser_address;

        // A TEMP number is an internal placeholder and is never printed on a
        // document that leaves the registry.
        $isTempFileNo = str_starts_with(strtoupper((string) $cor->fileno), 'TEMP');
    @endphp

    <style>
        @media print {
            body * {
                visibility: hidden !important;
            }

            .print-area,
            .print-area * {
                visibility: visible !important;
            }

            /* The @page margin already provides the sheet border; padding here
               would be subtracted from the space the 2x2 grid has to fit into. */
            .print-area {
                position: absolute !important;
                left: 0;
                top: 0;
                width: 100%;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                background: white !important;
                z-index: 9999;
            }

            .print-button,
            .print-button * {
                display: none !important;
            }

            @page {
                size: A4 portrait;
                margin: 0.4in;
            }

            /* 100vh is exactly the printable area of one sheet, so the 2x2 grid
               can never spill onto a second page. */
            .certificate-container {
                max-width: 100% !important;
                width: 100% !important;
                height: 100vh !important;
                max-height: 100vh !important;
                overflow: hidden !important;
                padding: 0 !important;
                margin: 0 !important;
                display: block !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .certificate-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                grid-template-rows: 1fr 1fr !important;
                gap: 12px !important;
                width: 100% !important;
                height: 100% !important;
                max-height: 100% !important;
                min-height: 0 !important;
            }

            /* min-height/overflow keep a long purchaser name from stretching its
               row past the 1fr the page can afford. */
            .certificate-item {
                font-size: 9px !important;
                line-height: 1.2 !important;
                padding: 8px !important;
                border: 2px solid #d1d5db !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
                height: 100% !important;
                min-height: 0 !important;
                overflow: hidden !important;
            }

            .logo-container img {
                width: 30px !important;
                height: 30px !important;
            }

            .title {
                font-size: 13px !important;
                margin-bottom: 4px !important;
            }

            .red-box-compact {
                padding: 6px !important;
                margin-bottom: 6px !important;
                font-size: 10px !important;
                line-height: 1.3 !important;
            }

            /* The certificate body carries inline font-size:14px/line-height:1.5,
               which is what would push four slips past one A4 sheet. Only an
               !important rule can override an inline style. */
            .red-box-compact p {
                font-size: 12px !important;
                line-height: 1.35 !important;
            }

            /* The stamp has four lines plus a rule to sign on, in roughly 4cm of
               slip. It is deliberately smaller than the body text: it is a stamp
               impression, not prose. */
            .stamp-block {
                font-size: 13px !important;
                line-height: 1.3 !important;
            }

            /* The type shrinks for print but the signing gap must not: it is
               physical writing room, not typography. */
            .stamp-signoff {
                row-gap: 25px !important;
                margin-top: 19px !important;
            }

            .footer-info {
                font-size: 7px !important;
                margin-top: 4px !important;
            }

            .footer-logo img {
                width: 14px !important;
                height: 14px !important;
            }

            .reg-number p {
                font-size: 10px !important;
            }

            .qr-code-container svg {
                width: 45px !important;
                height: 45px !important;
            }
        }

        .print-button {
            background-color: #ea580c;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .certificate-container {
            max-width: 210mm;
            max-height: 297mm;
            margin: 0 auto;
            padding: 20px;
        }

        .certificate-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 15px;
            height: 100%;
            min-height: 500px;
        }

        .certificate-item {
            border: 2px solid #d1d5db;
            padding: 10px;
            background: white;
            font-size: 9px;
            line-height: 1.3;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .certificate-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .logo-container {
            width: 35px;
            display: flex;
            justify-content: center;
        }

        .logo-container img {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }

        .reg-number {
            text-align: center;
            flex: 1;
        }

        .title {
            text-align: center;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 13px;
        }

        .red-box-compact {
            border: 1px solid #c41e3a;
            color: #c41e3a;
            padding: 6px;
            margin-bottom: 6px;
            font-size: 10px;
            line-height: 1.3;
            flex-grow: 1;
        }

        /* The registry stamp. No border of its own -- it sits inside the red box,
           and a second rectangle there read as a box within a box. Letter-spaced
           and set in black so it still reads as an impression rather than as more
           of the certificate's body text. */
        .stamp-block {
            padding: 2px 2px;
            font-size: 13px;
            line-height: 1.3;
            color: #111;
            letter-spacing: 0.02em;
            /* The whole impression is bold, not just its heading and values —
               a rubber stamp does not have light and heavy strokes. */
            font-weight: 700;
            /* The wording sits at the top and the sign-off is pushed to the
               bottom by margin-top:auto below, so the space between them is
               room to sign in rather than dead centring. */
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .stamp-heading {
            font-weight: 700;
            text-transform: uppercase;
        }

        /* The particulars are printed values, not blanks to write in, so they
           carry no rule underneath them. */
        .stamp-fill {
            font-weight: 700;
            padding: 0 4px;
            display: inline-block;
            text-align: center;
        }

        /* Signed and dated by hand. Both lines are label + rule in the same
           two-column grid, so the two rules start and end together instead of
           sitting at different lengths. */
        .stamp-signoff {
            display: grid;
            grid-template-columns: max-content 1fr;
            align-items: end;
            column-gap: 4px;
            /* The signing room belongs BETWEEN the two rules, not above them:
               the Director signs on the DIRECTOR LAND rule and that signature
               needs vertical room before the DATE line. So the block follows the
               wording closely (small margin-top) and the space is row-gap. */
            row-gap: 26px;
            margin-top: 20px;
        }

        .stamp-signoff-label {
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .stamp-signoff-rule {
            border-bottom: 1px solid #111;
            height: 12px;
            width: 100%;
        }

        /* The QR now sits alone in the delivery box. */
        .stamp-row {
            display: flex;
            justify-content: flex-end;
            align-items: flex-end;
            gap: 8px;
        }

        .qr-code-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 8px;
        }

        .qr-code-container svg {
            width: 50px;
            height: 50px;
        }

        .footer-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 7px;
            margin-top: 4px;
        }

        .footer-logo {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #b91c1c;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .footer-logo img {
            width: 12px;
            height: 12px;
            object-fit: cover;
            border-radius: 50%;
        }

        @if(request()->boolean('embedded'))
        /* Batch-embed mode: the host owns the @page margins. Fill the printable
           area so the 2x2 grid stretches across the page. */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            height: 100% !important;
        }
        @endif
    </style>

    <div class="flex-1 overflow-auto">
        @if(!request()->has('is_unit'))
            @include('admin.header')

            <div class="p-4 flex justify-center">
                <button class="print-button" onclick="window.print()">Print</button>
            </div>
        @endif

        <div class="print-area">
            <div class="certificate-container p-4">
                <div class="certificate-grid">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="certificate-item">

                            <div class="certificate-header">
                                <div class="logo-container">
                                    <img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Ministry Logo">
                                </div>
                                <div class="reg-number">
                                    <p class="font-bold text-[8px]">{{ $cor->stm_ref }}</p>
                                </div>
                                <div class="logo-container">
                                    <img src="{{ asset('assets/logo/ministry2.jpeg') }}" alt="Ministry Logo">
                                </div>
                            </div>

                            <div class="title">
                                <p style="font-size: 9px; font-weight: bold; margin-bottom: 2px; line-height: 1.3;">
                                    {{ $authority['ministry'] }}<br>
                                    {{ $authority['registry'] }}<br>
                                    {{ $authority['department'] }}
                                </p>
                                <h2>CONFIRMATION OF REGISTRATION OF INSTRUMENT</h2>
                            </div>

                            <!-- Red Box 1: delivery -->
                            <div class="red-box-compact">
                                <p style="font-size: 14px; line-height: 1.5;">THIS
                                    <strong>{{ strtoupper((string) $cor->instrument_type) }}</strong>
                                    WAS DELIVERED TO ME FOR REGISTRATION BY
                                    <strong class="font-bold">
                                        {{ strtoupper((string) $deliveryName) }}
                                        @if(!empty($deliveryAddress))
                                            OF {{ strtoupper((string) $deliveryAddress) }}
                                        @endif
                                    </strong>
                                </p>
                                <p style="font-size: 14px; line-height: 1.5; font-weight: bold;">
                                    AT {{ $cor->formatted_time }} IN THE {{ $cor->time_part }}
                                </p>
                                <p style="font-size: 14px; line-height: 1.5; font-weight: bold;">
                                    ON THE
                                    {{ $cor->deeds_date
                                        ? strtoupper(date('jS \D\A\Y \O\F F Y', strtotime($cor->deeds_date)))
                                        : strtoupper(date('jS \D\A\Y \O\F F Y')) }}
                                </p>

                                {{-- The stamp used to be repeated here as well. It
                                     now appears once, in the box below, so this
                                     box carries only the delivery narrative and
                                     the QR. --}}
                                <div class="stamp-row">
                                    <div class="qr-code-container">
                                        {!! $qrCodeSvg !!}
                                    </div>
                                </div>

                                {{-- A TEMP number is an internal placeholder and is
                                     never printed on a document that leaves the
                                     registry. --}}
                                @if(!$isTempFileNo && !empty($cor->fileno))
                                    <div class="text-center mt-2" style="color:black">
                                        <p class="font-bold">File Number: {{ $cor->fileno }}</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Red Box 2: the registry's stamp. This box used to
                                 restate the particulars in prose ("THIS ... IS
                                 REGISTERED AS NO x AT PAGE y ..."); it now carries
                                 the stamp itself, which says the same thing in the
                                 registry's own wording. No inner border here — the
                                 red box already is the box. --}}
                            <div class="red-box-compact" style="display: flex; flex-direction: column; justify-content: flex-start;">
                                @if($stamp['enabled'])
                                    <div class="stamp-block">
                                        <div class="stamp-heading">{{ $stamp['heading'] }}</div>
                                        <div>
                                            AS NO
                                            <span class="stamp-fill">{{ $stamp['fill_particulars'] ? ($cor->serial_no) : '' }}</span>
                                            AT PAGE
                                            <span class="stamp-fill">{{ $stamp['fill_particulars'] ? ($cor->page_no) : '' }}</span>
                                        </div>
                                        <div>
                                            IN VOL
                                            <span class="stamp-fill">{{ $stamp['fill_particulars'] ? ($cor->volume_no) : '' }}</span>
                                        </div>
                                        <div>{{ $stamp['registry_line'] }}</div>

                                        {{-- Both left blank: the Director signs and
                                             dates by hand, as on the physical
                                             stamp. Laid out as one grid so the two
                                             rules line up rather than ending at
                                             different points. --}}
                                        <div class="stamp-signoff">
                                            <span class="stamp-signoff-label">{{ $stamp['signatory'] }}:</span>
                                            <span class="stamp-signoff-rule"></span>
                                            <span class="stamp-signoff-label">DATE:</span>
                                            <span class="stamp-signoff-rule"></span>
                                        </div>
                                    </div>
                                @else
                                    <div class="text-center font-bold">{{ $authority['signatory'] }}</div>
                                @endif
                            </div>

                            <div class="footer-info">
                                <p>Generated by Kano State Land Administration Enterprise System (KLAES)</p>
                                <div class="footer-logo">
                                    <img src="http://app.klaes.ng/storage/upload/logo/Klase.png" alt="Kano State Logo">
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        @if(!request()->has('is_unit'))
            @include('admin.footer')
        @endif
    </div>
@endsection
