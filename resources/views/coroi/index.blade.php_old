@extends(request()->boolean('embedded') ? 'layouts.bare' : 'layouts.app')
@section('page-title')
    {{ __('Confirmation Of Instrument Registration') }}
@endsection

@section('content')

    @php
        use BaconQrCode\Renderer\ImageRenderer;
        use BaconQrCode\Renderer\Image\SvgImageBackEnd;
        use BaconQrCode\Renderer\RendererStyle\RendererStyle;
        use BaconQrCode\Writer;
        // Get the fileno parameter from the URL
        $fileno = request()->get('fileno');
        $stmRef = request()->get('STM_Ref');

        // If url param is present, try to extract STM_Ref or id from it if not already set
        $urlParam = request()->get('url');
        $idParam = request()->get('id');

        if (!$stmRef && $urlParam) {
            // Try to extract STM_Ref from the url param
            if (preg_match('/STM_Ref=([A-Za-z0-9\-]+)/', $urlParam, $matches)) {
                $stmRef = $matches[1];
            }
            // Try to extract id from the url param
            if (preg_match('/id=([A-Za-z0-9\_]+)/', $urlParam, $matches)) {
                $idParam = $matches[1];
            }
        }

        // Initialize display data
        $displayData = null;

        // 1. First Priority: Use $data if passed from controller and it's not mock
        if (isset($data) && property_exists($data, 'data_source') && $data->data_source === 'database') {
            $displayData = $data;
        }
        // 2. Second Priority: Fetch by id if provided (for both ST and generic instruments)
        elseif (!empty($idParam)) {
            try {
                $realId = $idParam;
                $isDeedReg = false;
                if (strpos($idParam, 'deed_reg_') === 0) {
                    $realId = substr($idParam, 9);
                    $isDeedReg = true;
                }

                if ($isDeedReg) {
                    $dbData = DB::connection('sqlsrv')->table('deed_registrations')->where('id', $realId)->first();
                    if ($dbData) {
                        $dbData->Grantor = $dbData->grantee; // Map grantee to Grantor for template
                    }
                } else {
                    $dbData = DB::connection('sqlsrv')->table('registered_instruments')->where('id', $realId)->first();
                }
            } catch (\Exception $e) {
                \Log::error('ID query error: ' . $e->getMessage());
            }
        }
        // 3. Third Priority: Legacy fetching by fileno
        elseif (!empty($fileno)) {
            try {
                $dbData = DB::connection('sqlsrv')->table('registered_instruments')
                    ->where('MLSFileNo', $fileno)
                    ->orWhere('KAGISFileNO', $fileno)
                    ->orWhere('NewKANGISFileNo', $fileno)
                    ->orWhere('StFileNo', $fileno)
                    ->first();

                if (!$dbData) {
                    $dbData = DB::connection('sqlsrv')->table('registered_instruments')
                        ->where('MLSFileNo', 'LIKE', '%' . $fileno . '%')
                        ->orWhere('KAGISFileNO', 'LIKE', '%' . $fileno . '%')
                        ->orWhere('NewKANGISFileNo', 'LIKE', '%' . $fileno . '%')
                        ->orWhere('StFileNo', 'LIKE', '%' . $fileno . '%')
                        ->first();
                }
            } catch (\Exception $e) {
                \Log::error('Direct DB query error: ' . $e->getMessage());
            }
        }
        // 4. Fourth Priority: Legacy fetching by STM_Ref
        elseif (!empty($stmRef)) {
            try {
                $dbData = DB::connection('sqlsrv')->table('registered_instruments')
                    ->where('STM_Ref', $stmRef)
                    ->first();
            } catch (\Exception $e) {
                \Log::error('STM_Ref query error: ' . $e->getMessage());
            }
        }

        // If we picked up a dbData from legacy fetching, format it
        if (!$displayData && isset($dbData) && $dbData) {
            $formatted_date = ($dbData->deeds_date ?? null) ? date('jS F Y', strtotime($dbData->deeds_date)) :
                (($dbData->instrumentDate ?? null) ? date('jS F Y', strtotime($dbData->instrumentDate)) : date('jS F Y'));

            $time_source = ($dbData->deeds_time ?? null) ?: ($dbData->instrumentDate ?? null ? date('H:i:s', strtotime($dbData->instrumentDate)) : '-');
            $formatted_time = date('g:i A', strtotime($time_source));
            $hour_part = date('g', strtotime($time_source));
            $time_part = date('A', strtotime($time_source));

            $displayData = (object) [
                'Applicant_Name' => ($dbData->Grantor ?? $dbData->grantee ?? 'N/A'),
                'instrument_type' => ($dbData->instrument_type ?? null) ?: 'INSTRUMENT',
                'volume_no' => ($dbData->volume_no ?? null) ?: '-',
                'page_no' => ($dbData->page_no ?? null) ?: '-',
                'serial_no' => ($dbData->serial_no ?? null) ?: '-',
                'formatted_date' => $formatted_date,
                'hour_part' => $hour_part,
                'time_part' => $time_part,
                'STM_Ref' => ($dbData->STM_Ref ?? null),
                'MLSFileNo' => $dbData->MLSFileNo ?? null,
                'KAGISFileNO' => $dbData->KAGISFileNO ?? null,
                'NewKANGISFileNo' => $dbData->NewKANGISFileNo ?? null,
                'StFileNo' => $dbData->StFileNo ?? null,
                'fileno' => $dbData->fileno ?? null,
                'data_source' => 'database'
            ];
        }

        // 5. Final Fallback: Use mock data
        if (!$displayData) {
            $year = date('Y');
            $displayData = (object) [
                'Applicant_Name' => 'DEFAULT APPLICANT',
                'instrument_type' => 'DEFAULT INSTRUMENT',
                'volume_no' => '1',
                'page_no' => '1',
                'serial_no' => '1',
                'formatted_date' => date('jS F Y'),
                'hour_part' => '12',
                'time_part' => 'PM',
                'STM_Ref' => null,
                'MLSFileNo' => $fileno ?: null,
                'KAGISFileNO' => null,
                'NewKANGISFileNo' => null,
                'StFileNo' => null,
                'data_source' => 'mock'
            ];
        }

        $data = $displayData;

        // Generate QR code for verification - use File Number
        $qrFileNo = $data->fileno ?? $data->MLSFileNo ?? $data->KAGISFileNO ?? $data->NewKANGISFileNo ?? $data->StFileNo ?? 'N/A';
        $qrCodeData = $qrFileNo;

        $renderer = new ImageRenderer(
            new RendererStyle(100), // Size
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeData);

        // --- Multi-property Mortgage logic ---
        $copiesToPrint = 1;
        if (isset($data->instrument_type) && stripos((string)$data->instrument_type, 'MORTGAGE') !== false) {
            if (isset($qrFileNo) && $qrFileNo !== 'N/A') {
                $consentApp = DB::connection('sqlsrv')->table('consent_applications')
                    ->where('file_number', $qrFileNo)
                    ->orderBy('id', 'desc')
                    ->first();
                    
                if ($consentApp && !empty($consentApp->additional_properties)) {
                    $additionalProps = json_decode($consentApp->additional_properties, true);
                    if (is_array($additionalProps)) {
                        $copiesToPrint = 1 + count($additionalProps);
                        
                        // Merge all applicant names
                        $titles = [];
                        if (!empty($consentApp->applicant_name)) {
                            $titles[] = strtoupper(trim($consentApp->applicant_name));
                        }
                        foreach ($additionalProps as $prop) {
                            if (!empty($prop['applicant_name'])) {
                                $titles[] = strtoupper(trim($prop['applicant_name']));
                            } elseif (!empty($prop['applicant'])) {
                                $titles[] = strtoupper(trim($prop['applicant']));
                            }
                        }
                        $titles = array_unique(array_filter($titles));
                        if (count($titles) == 1) {
                            $data->Applicant_Name = array_values($titles)[0];
                        } elseif (count($titles) == 2) {
                            $titles = array_values($titles);
                            $data->Applicant_Name = $titles[0] . ' & ' . $titles[1];
                        } elseif (count($titles) > 2) {
                            $titles = array_values($titles);
                            $last = array_pop($titles);
                            $data->Applicant_Name = implode(', ', $titles) . ', & ' . $last;
                        }
                    }
                }
            }
        }
    @endphp
    <style>
        .ck-editor__editable {
            min-height: 200px;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        textarea {
            min-height: 40px;
        }

        @media print {
            body * {
                visibility: hidden !important;
            }

            .print-area,
            .print-area * {
                visibility: visible !important;
            }

            .print-area {
                position: absolute !important;
                left: 0;
                top: 0;
                width: 100vw;
                height: 100vh;
                margin: 0 !important;
                padding: 15px !important;
                box-shadow: none !important;
                background: white !important;
                z-index: 9999;
            }

            .print-button,
            .print-button * {
                display: none !important;
            }

            /* A4 Portrait page optimization */
            @page {
                size: A4 portrait;
                margin: 0.4in;
            }
            .certificate-container {
                max-width: 100% !important;
                height: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

            .certificate-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                grid-template-rows: 1fr 1fr !important;
                gap: 15px !important;
                width: 100% !important;
                height: 100% !important;
                max-height: 100% !important;
            }

            .certificate-item {
                font-size: 9px !important;
                line-height: 1.2 !important;
                padding: 8px !important;
                border: 2px solid #d1d5db !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
                height: 100% !important;
            }

            .certificate-item img {
                width: 16px !important;
                height: 16px !important;
            }

            .logo-container img {
                width: 30px !important;
                height: 30px !important;
            }

            .title {
                font-size: 11px !important;
                margin-bottom: 6px !important;
            }

            .red-box-compact {
                padding: 6px !important;
                margin-bottom: 6px !important;
                font-size: 10px !important;
                line-height: 1.3 !important;
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
        }

        .red-box {
            border: 1px solid #c41e3a;
            color: #c41e3a;
        }

        .print-button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 15px;
        }

        /* Compact layout for screen view - optimized for portrait preview */
        .certificate-container {
            max-width: 210mm;
            /* A4 portrait width */
            max-height: 297mm;
            /* A4 portrait height */
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
            background-image: url();
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            font-size: 9px;
            line-height: 1.3;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        /* Optional: Add a semi-transparent overlay to ensure text readability */
        .certificate-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            z-index: 1;
            pointer-events: none;
        }

        /* Ensure all content appears above the overlay */
        .certificate-item>* {
            position: relative;
            z-index: 2;
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

        .seal-container {
            width: 20px;
            display: flex;
            justify-content: center;
        }

        .seal {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 1px solid #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .seal img {
            width: 12px;
            height: 12px;
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
            font-size: 11px;
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

        .registrar-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 4px;
        }

        .registrar-text {
            flex: 1;
        }

        .qr-code-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
            margin-right: 5px;
        }

        .qr-code-container svg {
            width: 50px;
            height: 50px;
        }

        @media print {
            .qr-code-container svg {
                width: 45px !important;
                height: 45px !important;
            }
        }

        @if(request()->boolean('embedded'))
        /* Batch-embed mode: host owns the @page margins (12mm). Fill the
           printable area so the 2x2 grid stretches across the page. */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            height: 100% !important;
            min-height: 100% !important;
            max-height: 100% !important;
            overflow: hidden !important;
            background: #fff !important;
            box-sizing: border-box !important;
        }
        .certificate-container {
            max-width: 100% !important;
            max-height: 100% !important;
            height: 100% !important;
            width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .certificate-grid {
            height: 100% !important;
            min-height: 0 !important;
        }
        /* The view wraps everything in .flex-1.overflow-auto; it needs an
           explicit height so %-based children resolve. */
        body > .flex-1 {
            height: 100% !important;
            width: 100% !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
        }
        body > .flex-1 > .print-area {
            flex: 1 1 auto !important;
        }
        @media print {
            html, body {
                width: 100% !important;
                height: 100% !important;
                min-height: 100% !important;
                max-height: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }
            body * { visibility: visible !important; }
            .print-area {
                position: static !important;
                width: 100% !important;
                height: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                z-index: auto !important;
                page-break-inside: avoid;
            }
            .certificate-container {
                max-width: 100% !important;
                max-height: 100% !important;
                height: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .certificate-grid {
                height: 100% !important;
            }
            @page {
                size: auto;
                margin: 0;
            }
        }
        @endif
    </style>

    <div class="flex-1 overflow-auto">
        <!-- Header (hidden when embedded as a batch iframe / unit) -->
        @if(!request()->has('is_unit'))
            @include('admin.header')
        @endif

        <!-- Print button -->
        @if(!request()->has('is_unit'))
            <div class="p-4 flex justify-center">
                <button class="print-button" onclick="window.print()">Print</button>
            </div>
        @endif

        <!-- Dashboard Content -->
        <div class="print-area">
            @for ($copy = 0; $copy < $copiesToPrint; $copy++)
            <div class="certificate-container p-4">
                <!-- 2x2 Grid of Certificates -->
                <div class="certificate-grid">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="certificate-item">
                            <!-- Header with logos and registration number -->
                            <div class="certificate-header">
                                <div class='logo-container'>
                                    <img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="KANGIS Logo">
                                </div>

                                <!-- Registration Number -->
                                <div class="reg-number">
                                    <p class="font-bold text-[8px]">
                                        @if(isset($data) && !empty($data->STM_Ref))
                                            {{ $data->STM_Ref }}
                                        @endif
                                    </p>
                                </div>

                                <!-- KANGIS Logo (logo2.jpg) -->
                                <div class="logo-container">
                                    <img src="{{ asset('assets/logo/ministry2.jpeg') }}" alt="KANGIS Logo">
                                </div>


                            </div> 

                            <!-- Title -->
                            <div class="title">
                                <p style="font-size: 9px; font-weight: bold; margin-bottom: 2px; line-height: 1.3;">KANO STATE MINISTRY OF LAND &amp; PHYSICAL PLANNING<br>
                                DEEDS REGISTRY/DEPARTMENT</p>
                                <h2>CONFIRMATION OF REGISTRATION OF INSTRUMENT</h2>
                            </div>

                            <!-- Red Box 1 -->
                            <div class="red-box-compact">
                                <p style="font-size: 14px; line-height: 1.5;">THIS
                                    {{ isset($data) && isset($data->instrument_type) ? strtoupper($data->instrument_type) : 'INSTRUMENT' }}
                                    WAS DELIVERED TO ME FOR REGISTRATION BY

                                    @php
                                        $isDeedOfAssignment = isset($data) && isset($data->instrument_type)
                                            && stripos((string) $data->instrument_type, 'DEED OF ASSIGNMENT') !== false;

                                        $isMortgage = isset($data) && isset($data->instrument_type)
                                            && stripos((string) $data->instrument_type, 'MORTGAGE') !== false;

                                        // Mortgages are delivered by party 1 (the mortgagor)
                                        if ($isDeedOfAssignment) {
                                            $deliveryName = $data->solicitor_name ?? $data->Applicant_Name ?? 'APPLICANT NAME';
                                            $deliveryAddress = $data->solicitor_address ?? $data->party_2_address ?? null;
                                        } elseif ($isMortgage) {
                                            $deliveryName = $data->party_1_name ?? $data->Applicant_Name ?? 'APPLICANT NAME';
                                            $deliveryAddress = $data->party_1_address ?? null;
                                        } else {
                                            $deliveryName = $data->Applicant_Name ?? 'APPLICANT NAME';
                                            $deliveryAddress = $data->party_2_address ?? null;
                                        }
                                    @endphp

                                    <strong class="font-bold">
                                        {{ strtoupper($deliveryName) }}
                                        @if(!empty($deliveryAddress))
                                            OF {{ strtoupper($deliveryAddress) }}
                                        @endif
                                    </strong>
                                </p>
                                <p style="font-size: 14px; line-height: 1.5; font-weight: bold;">AT {{ isset($data) && isset($data->formatted_time) ? $data->formatted_time : '-' }}
                                    IN THE
                                    {{ isset($data) && isset($data->time_part) ? $data->time_part : 'AFTERNOON' }}
                                </p>
                                <p style="font-size: 14px; line-height: 1.5; font-weight: bold;">ON THE
                                    {{ isset($data) && isset($data->deeds_date) ? strtoupper(date('jS \D\A\Y \O\F F Y', strtotime($data->deeds_date))) : strtoupper(date('jS \D\A\Y \O\F F Y')) }}
                                </p>

                                @if(isset($data) && (isset($data->MLSFileNo) || isset($data->KAGISFileNO) || isset($data->NewKANGISFileNo) || isset($data->StFileNo)))
                                    <!-- <div class="mt-1" style="font-size: 7px;">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <p><strong>FILE REFERENCE:</strong></p>
                                                                                                                                                                                                                                                                                                                                                                                                                                                        @if(isset($data->MLSFileNo) && !empty($data->MLSFileNo))
                                                                                                                                                                                                                                                                                                                                                                                                                                                            <p>MLS File No: {{ $data->MLSFileNo }}</p>
                                                                                                                                                                                                                                                                                                                                                                                                                                                        @endif
                                                                                                                                                                                                                                                                                                                                                                                                                                                        @if(isset($data->KAGISFileNO) && !empty($data->KAGISFileNO))
                                                                                                                                                                                                                                                                                                                                                                                                                                                            <p>KAGIS File No: {{ $data->KAGISFileNO }}</p>
                                                                                                                                                                                                                                                                                                                                                                                                                                                        @endif
                                                                                                                                                                                                                                                                                                                                                                                                                                                        @if(isset($data->NewKANGISFileNo) && !empty($data->NewKANGISFileNo))
                                                                                                                                                                                                                                                                                                                                                                                                                                                            <p>New KANGIS File No: {{ $data->NewKANGISFileNo }}</p>
                                                                                                                                                                                                                                                                                                                                                                                                                                                        @endif
                                                                                                                                                                                                                                                                                                                                                                                                                                                        @if(isset($data->StFileNo) && !empty($data->StFileNo))
                                                                                                                                                                                                                                                                                                                                                                                                                                                            <p>ST File No: {{ $data->StFileNo }}</p>
                                                                                                                                                                                                                                                                                                                                                                                                                                                        @endif
                                                                                                                                                                                                                                                                                                                                                                                                                                                    </div> -->
                                @endif

                                <!-- Registrar section with QR code -->
                                <div class="registrar-section">
                                    <div class="registrar-text">
                                        <p class="text-center">REGISTRAR OF DEEDS</p>
                                    </div>
                                    <div class="qr-code-container">
                                        {!! $qrCodeSvg !!}
                                    </div>
                                </div>

                                <div class="mt-1">
                                    <p>Signature: ________________________________</p>
                                    <br>
                                    <p style="margin-top: 4px;">Date: ____________________________________</p>
                                </div>

                                <!--- File number ---->
                                <!--- File number ----> 
                                @php
                                    $baseFileno = isset($data) && isset($data->fileno) ? $data->fileno : '-';
                                    if ($copy > 0 && !empty($additionalProps[$copy - 1]['file_number'])) {
                                        $displayFileno = $additionalProps[$copy - 1]['file_number'];
                                    } else {
                                        $displayFileno = $baseFileno;
                                    }
                                    $isTemp = str_starts_with(strtoupper($displayFileno), 'TEMP');
                                @endphp
                                
                                @if(!$isTemp)
                                <div class="text-center mt-2" style="color:black">
                                    <p class="font-bold">
                                        @if(isset($data) && isset($data->instrument_type) && strtoupper($data->instrument_type) === 'OCCUPANCY PERMIT (OP)')
                                            System Number:
                                        @else
                                            File Number:
                                        @endif
                                        {{ $displayFileno }}
                                    </p>

                                </div>
                                @endif
                            </div>





                            <!-- Red Box 2 -->
                            <div class="red-box-compact" style="display: flex; flex-direction: column; justify-content: center;">
                                <p style="font-size: 14px; line-height: 1.5;">THIS
                                    <strong>{{ isset($data) && isset($data->instrument_type) ? strtoupper($data->instrument_type) : 'INSTRUMENT' }}</strong>
                                    IS REGISTERED AS
                                </p>
                                <p style="margin-top: 4px; font-size: 14px; line-height: 1.5;">NO
                                    <strong>{{ isset($data) && isset($data->serial_no) ? $data->serial_no : '1' }}</strong> AT
                                    PAGE <strong>{{ isset($data) && isset($data->page_no) ? $data->page_no : '1' }}</strong> IN
                                    VOLUME
                                    <strong>{{ isset($data) && isset($data->volume_no) ? $data->volume_no : '1' }}</strong>
                                </p>
                                <p style="margin-top: 4px; font-size: 14px; line-height: 1.5;">OF DEEDS REGISTRY  MINISTRY OF LAND AND PHYSICAL PLANNING</p>
                                <p style="margin-top: 4px; font-size: 14px; line-height: 1.5;">AT KANO STATE</p>
                            </div>

                            <!-- Footer -->
                            <div class="footer-info">
                                <p>Generated by Kano State Land Administration Enterprise
                                    System (KLAES)</p>
                                <div class="footer-logo">
                                    <img src="http://app.klaes.ng/storage/upload/logo/Klase.png" alt="Kano State Logo">
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
            @if ($copy < $copiesToPrint - 1)
                <div style="page-break-after: always;"></div>
            @endif
            @endfor
        </div>

        <!-- Footer (hidden when embedded as a batch iframe / unit) -->
        @if(!request()->has('is_unit'))
            @include('admin.footer')
        @endif
    </div>

    <script>
        const corAutoPrint = @json((bool) request()->query('autoprint'));
        const batchSessionId = @json(request()->query('batch_session_id'));
        const batchScope = @json(request()->query('batch_scope'));
        const batchTotal = Number(@json((int) request()->query('batch_total', 0)));
        const batchDocId = @json(request()->query('batch_doc_id'));
        const batchToken = @json(request()->query('batch_token'));
        const batchCompleteUrl = @json(request()->query('batch_complete_url'));

        function notifyBatchHost(payload) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage(payload, window.location.origin);
            }

            if (window.opener && !window.opener.closed) {
                window.opener.postMessage(payload, window.location.origin);
            }
        }

        async function recordBatchCompletionIfNeeded() {
            if (!batchSessionId || batchScope !== 'cor' || !batchDocId || !batchToken || batchTotal < 1 || !batchCompleteUrl) {
                return;
            }

            const storageKey = ['klaes', 'batch-print', batchScope, batchSessionId, batchToken].join(':');
            let progress = {
                completed: [],
                finalized: false,
                finalizing: false,
            };

            try {
                const stored = window.localStorage.getItem(storageKey);
                if (stored) {
                    progress = Object.assign(progress, JSON.parse(stored));
                }
            } catch (error) {
                console.warn('Unable to read batch print progress from localStorage:', error);
            }

            if (!Array.isArray(progress.completed)) {
                progress.completed = [];
            }

            if (!progress.completed.includes(batchDocId)) {
                progress.completed.push(batchDocId);
            }

            try {
                window.localStorage.setItem(storageKey, JSON.stringify(progress));
            } catch (error) {
                console.warn('Unable to persist batch print progress to localStorage:', error);
            }

            if (progress.completed.length < batchTotal || progress.finalized || progress.finalizing) {
                return;
            }

            progress.finalizing = true;

            try {
                window.localStorage.setItem(storageKey, JSON.stringify(progress));
            } catch (error) {
                console.warn('Unable to update finalizing state in localStorage:', error);
            }

            try {
                const response = await fetch(batchCompleteUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        session_id: batchSessionId,
                    }),
                });

                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || 'Unable to finalize the batch print session.');
                }

                progress.finalizing = false;
                progress.finalized = true;

                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(progress));
                } catch (error) {
                    console.warn('Unable to store finalized batch print state:', error);
                }

                notifyBatchHost({
                    type: 'klaes-batch-print-session-completed',
                    batch_session_id: batchSessionId,
                    batch_scope: batchScope,
                    batch_doc_id: batchDocId,
                    batch_token: batchToken,
                });
            } catch (error) {
                progress.finalizing = false;

                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(progress));
                } catch (storageError) {
                    console.warn('Unable to store failed batch print finalization state:', storageError);
                }

                console.error('Failed to finalize batch print session:', error);
            }
        }

        if (corAutoPrint) {
            window.addEventListener('load', async function () {
                setTimeout(async function () {
                    window.print();

                    notifyBatchHost({
                        type: 'klaes-batch-print-finished',
                        url: window.location.href,
                        batch_session_id: batchSessionId,
                        batch_scope: batchScope,
                        batch_doc_id: batchDocId,
                        batch_token: batchToken,
                    });

                    await recordBatchCompletionIfNeeded();
                }, 500);
            }, { once: true });
        }
    </script>
@endsection