@extends('layouts.app')
@section('page-title')
    {{ __('Confirmation Of Instrument Registration') }}
@endsection

@section('content')

@php
    // Get parameters from the URL
    $certificate_generated_date = request()->get('certificate_generated_date'); 
    $serial_no = request()->get('serial_no');
    $reg_page = request()->get('reg_page');
    $reg_volume = request()->get('reg_volume');

    // Process certificate_generated_date for time and date extraction
    $formatted_date = '6TH AUGUST 2025'; // Default
    $hour_part = '6';
    $time_part = 'PM';
    
    // Use the application data passed from the controller
    if (isset($application) && $application) {
        // First, try to use the certificate_generated_date from the application if available
        $dateToUse = $certificate_generated_date ?: $application->certificate_generated_date;
        
        if ($dateToUse) {
            try {
                // Parse the certificate_generated_date (format: 2025-08-11 11:25:46.074)
                $dateTime = new DateTime($dateToUse);
                $formatted_date = strtoupper($dateTime->format('jS F Y'));
                $hour_part = $dateTime->format('g');
                $time_part = $dateTime->format('A');
            } catch (Exception $e) {
                // If parsing fails, use default values
                \Log::error('Date parsing error: ' . $e->getMessage());
            }
        }
        
        // Use application data with URL parameter overrides
        $displayData = (object)[
            'Applicant_Name' => 'KANO STATE GOVERNMENT', // Always KANO STATE GOVERNMENT for recertification
            'instrument_type' => 'CERTIFICATE OF OCCUPANCY',
            'volume_no' => $reg_volume ?: ($application->reg_volume ?? '1'),
            'page_no' => $reg_page ?: ($application->reg_page ?? '1'),
            'serial_no' => $serial_no ?: ($application->cofo_serial_no ?? $application->cofo_number ?? $application->serial_no ?? '1'),
            'formatted_date' => $formatted_date,
            'hour_part' => $hour_part,
            'time_part' => $time_part,
            'STM_Ref' => 'STM-' . date('Y') . '-' . str_pad($application->id ?? 1, 3, '0', STR_PAD_LEFT),
            'MLSFileNo' => $application->mlsfNo,
            'KAGISFileNO' => $application->kangisFileNo,
            'NewKANGISFileNo' => $application->NewKANGISFileno,
            'StFileNo' => $application->file_number,
            'data_source' => 'recertification_application',
            // Additional fields from recertification_applications table
            'application_reference' => $application->application_reference,
            'cofo_number' => $application->cofo_number,
            'reg_no' => $application->reg_no,
            'plot_number' => $application->plot_number,
            'layout_district' => $application->layout_district,
            'lga_name' => $application->lga_name,
            'applicant_full_name' => trim(($application->surname ?? '') . ' ' . ($application->first_name ?? '') . ' ' . ($application->middle_name ?? '')),
            'organisation_name' => $application->organisation_name,
            'applicant_type' => $application->applicant_type,
            'certificate_generated_date_raw' => $dateToUse
        ];
    } else {
        // Fallback data if no application is passed
        if ($certificate_generated_date) {
            try {
                // Parse the certificate_generated_date (format: 2025-08-11 11:25:46.074)
                $dateTime = new DateTime($certificate_generated_date);
                $formatted_date = strtoupper($dateTime->format('jS F Y'));
                $hour_part = $dateTime->format('g');
                $time_part = $dateTime->format('A');
            } catch (Exception $e) {
                // If parsing fails, use default values
                \Log::error('Date parsing error: ' . $e->getMessage());
            }
        }
        
        $year = date('Y');
        $displayData = (object)[
            'Applicant_Name' => 'KANO STATE GOVERNMENT',
            'instrument_type' => 'CERTIFICATE OF OCCUPANCY',
            'volume_no' => $reg_volume ?: '1',
            'page_no' => $reg_page ?: '1',
            'serial_no' => $serial_no ?: '1',
            'formatted_date' => $formatted_date,
            'hour_part' => $hour_part,
            'time_part' => $time_part,
            'STM_Ref' => "STM-{$year}-001",
            'MLSFileNo' => null,
            'KAGISFileNO' => null,
            'NewKANGISFileNo' => null,
            'StFileNo' => null,
            'data_source' => 'fallback',
            'certificate_generated_date_raw' => $certificate_generated_date
        ];
    }
    
    // Override the $data variable
    $data = $displayData;
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
            .print-area, .print-area * {
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
            .print-button, .print-button * {
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
                width: 18px !important;
                height: 18px !important;
            }
            
            .title {
                font-size: 11px !important;
                margin-bottom: 6px !important;
            }
            
            .red-box-compact {
                padding: 6px !important;
                margin-bottom: 6px !important;
                font-size: 8px !important;
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
            max-width: 210mm; /* A4 portrait width */
            max-height: 297mm; /* A4 portrait height */
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
        }
        
        .certificate-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        
        .logo-container {
            width: 20px;
            display: flex;
            justify-content: center;
        }
        
        .logo-container img {
            width: 14px;
            height: 14px;
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
            font-size: 8px;
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
    </style>
    
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        
        <!-- Print button -->
    @if(!request()->has('is_unit'))
    <div class="p-4 flex justify-center">
        <button class="print-button" onclick="window.print()">Print</button>
    </div>
    @endif
        
        <!-- Dashboard Content -->
        <div class="print-area">
            <div class="certificate-container p-4">
                <!-- 2x2 Grid of Certificates -->
                <div class="certificate-grid">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="certificate-item">
                            <!-- Header with logos and registration number -->
                            <div class="certificate-header">
                                <!-- Nigerian Coat of Arms (local logo1.jpg) -->
                                <div class="logo-container">
                                    <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Nigerian Coat of Arms">
                                </div>

                                <!-- Registration Number -->
                                <div class="reg-number">
                                    <p class="font-bold text-[8px]">
                                    @if(isset($data) && isset($data->STM_Ref))
                                        {{ $data->STM_Ref }}
                                    @else
                                        @php
                                            $year = date('Y');
                                            echo "STM-{$year}-001";
                                        @endphp
                                    @endif
                                    </p>
                                </div>

                                <!-- KANGIS Logo (logo2.jpg) -->
                                <div class="logo-container">
                                    <img src="{{ asset('assets/logo/logo2.jpg') }}" alt="KANGIS Logo">
                                </div>

                              
                            </div>

                            <!-- Title -->
                            <div class="title">
                                <h2>CONFIRMATION OF REGISTRATION OF INSTRUMENT</h2>
                            </div>

                            <!-- Red Box 1 -->
                            <div class="red-box-compact">
                                <p>THIS {{ isset($data) && isset($data->instrument_type) ? strtoupper($data->instrument_type) : 'CERTIFICATE OF OCCUPANCY' }} WAS DELIVERED TO ME FOR REGISTRATION BY</p>
                                <p class="font-bold">{{ isset($data) && isset($data->Applicant_Name) ? strtoupper($data->Applicant_Name) : 'KANO STATE GOVERNMENT' }}</p>
                                <p>AT {{ isset($data) && isset($data->hour_part) ? $data->hour_part : '6' }} O'CLOCK IN THE {{ isset($data) && isset($data->time_part) ? $data->time_part : 'PM' }}</p>
                                <p>ON THE {{ isset($data) && isset($data->formatted_date) ? strtoupper($data->formatted_date) : '6TH AUGUST 2025' }}</p>
                                
                                @if(isset($data) && (isset($data->MLSFileNo) || isset($data->KAGISFileNO) || isset($data->NewKANGISFileNo) || isset($data->StFileNo)))
                                    <!-- <div class="mt-1" style="font-size: 7px;">
                                        <p><strong>FILE REFERENCE:</strong></p>
                                        @if(isset($data->MLSFileNo) && !empty($data->MLSFileNo))
                                            <p>MLPP File No: {{ $data->MLSFileNo }}</p>
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
                                
                                <p class="text-center mt-1">REGISTRAR OF DEEDS</p>
                                <div class="mt-1">
                                    <p>Signature: ________________________________</p>
                                    <p style="margin-top: 4px;">Date: ____________________________________</p>
                                </div>

                                <!-- Land Deeds Registry Office -->
                                <div class="text-center mt-2" style="color:black">
                                    <p class="font-bold">DEEDS REGISTRY</p>
                                    <p class="font-bold">DEEDS DEPARTMENT</p>
                                    <p class="font-bold">MINISTRY OF LANDS AND PHYSICAL PLANNING</p>
                                    <p class="font-bold">KANO STATE</p>
                                </div>
                            </div>

                            <!-- Red Box 2 -->
                            <div class="red-box-compact">
                                <p>THIS {{ isset($data) && isset($data->instrument_type) ? strtoupper($data->instrument_type) : 'INSTRUMENT' }} IS REGISTERED AS</p>
                                <p style="margin-top: 2px;">NO <strong>{{ isset($data) && isset($data->serial_no) ? $data->serial_no : '1' }}</strong> AT PAGE <strong>{{ isset($data) && isset($data->page_no) ? $data->page_no : '1' }}</strong> IN VOLUME <strong>{{ isset($data) && isset($data->volume_no) ? $data->volume_no : '1' }}</strong></p>
                                <p style="margin-top: 2px;">OF THE MINISTRY OF LAND AND PHYSICAL PLANNING</p>
                                <p style="margin-top: 2px;">AT KANO STATE</p>
                            </div>

                            <!-- Footer -->
                            <div class="footer-info">
                                <p>Generated by Kano State Land Administration Enterprise 
System (KLAES)</p>
                                <div class="footer-logo">
                                    <img src="http://klas.com.ng/storage/upload/logo/Klase.pngpng" alt="Kano State Logo">
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        @include('admin.footer')
    </div>
@endsection 
