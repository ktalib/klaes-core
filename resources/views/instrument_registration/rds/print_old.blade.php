<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Document Sheet (RDS) - {{ $rds->rds_reference }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            @page {
                size: A4 portrait;
                margin: 0.5in;
            }
            /* Remove browser headers and footers */
            @page { margin: 0; }
            body { margin: 1.6cm; }
        }
        
        .underline-field {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 150px;
            padding: 0 4px;
        }
        
        .form-line {
            border-bottom: 1px solid #000;
            min-height: 24px;
        }
        
        .document-title {
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        
        .document-title {
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        
        .header-box {
            border: 2px solid #000;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .label {
            font-weight: 600;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(255, 0, 0, 0.1);
            z-index: 1000;
            pointer-events: none;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gray-50 p-4">
    <!-- Watermark for COPY prints -->
    @if($watermark === 'COPY')
    <div class="watermark">{{ $watermark }}</div>
    @endif

    <!-- Print Button -->
    <button onclick="window.print()" class="no-print fixed top-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg hover:bg-blue-700 transition-colors flex items-center gap-2 z-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
        </svg>
        Print RDS
    </button>

    <!-- Close Button -->
    <button onclick="window.close()" class="no-print fixed top-4 right-36 bg-gray-600 text-white px-4 py-2 rounded-lg shadow-lg hover:bg-gray-700 transition-colors flex items-center gap-2 z-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
        Close
    </button>

    <!-- Document Container -->
    <div class="max-w-4xl mx-auto bg-white shadow-lg p-12 relative">
        <!-- Header Section -->
        <div class="header-box">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <img src="{{ asset('public/images/logo.png') }}" alt="Logo" class="h-16 mb-2">
                    <h2 class="text-lg font-bold">KANO STATE GEOGRAPHIC</h2>
                    <h2 class="text-lg font-bold">INFORMATION SYSTEM</h2>
                </div>
                <div class="text-right">
                    <h1 class="text-2xl font-bold tracking-wider document-title">REGISTERED DOCUMENT SHEET</h1>
                    <p class="text-sm mt-2 text-gray-600">RDS No: <span class="font-bold">{{ $rds->rds_reference }}</span></p>
                </div>
            </div>
        </div>

        <!-- Document Type Section -->
        <div class="text-center mb-6 py-3 bg-gray-100 border-t-2 border-b-2 border-gray-400">
            <h2 class="text-xl font-bold uppercase">
                {{ $instrument->instrument_type ?? 'INSTRUMENT REGISTRATION' }}
            </h2>
            <p class="text-sm text-gray-600 mt-1">STM Reference: {{ $instrument->STM_Ref }}</p>
        </div>

        <!-- Form Content -->
        <div class="space-y-4 text-sm leading-relaxed">
            <!-- Registration Details -->
            <div class="border-b-2 border-gray-300 pb-4 mb-4">
                <h3 class="font-bold text-lg mb-3 text-gray-700">REGISTRATION DETAILS</h3>
                
                <div class="info-grid">
                    <span class="label">Registration Date:</span>
                    <span class="underline-field">{{ $details['registration_date'] }}</span>
                </div>
                
                <div class="info-grid">
                    <span class="label">Registration Time:</span>
                    <span class="underline-field">{{ $details['registration_time'] }}</span>
                </div>
                
                <div class="info-grid">
                    <span class="label">File Number:</span>
                    <span class="underline-field">{{ $instrument->fileno ?? 'N/A' }}</span>
                </div>
                
                <div class="info-grid">
                    <span class="label">Instrument Date:</span>
                    <span class="underline-field">{{ $details['instrument_date'] }}</span>
                </div>
            </div>

            <!-- Parties Information -->
            <div class="border-b-2 border-gray-300 pb-4 mb-4">
                <h3 class="font-bold text-lg mb-3 text-gray-700">PARTIES INFORMATION</h3>
                
                <div class="mb-3">
                    <div class="label mb-1">GRANTOR (FROM):</div>
                    <div class="form-line">{{ $instrument->Grantor ?? 'N/A' }}</div>
                </div>
                
                <div>
                    <div class="label mb-1">GRANTEE (TO):</div>
                    <div class="form-line">{{ $instrument->Grantee ?? 'N/A' }}</div>
                </div>
            </div>

            <!-- Property Details -->
            <div class="border-b-2 border-gray-300 pb-4 mb-4">
                <h3 class="font-bold text-lg mb-3 text-gray-700">PROPERTY DETAILS</h3>
                
                <div class="info-grid">
                    <span class="label">Right of Occupancy No:</span>
                    <span class="underline-field">{{ $details['root_registration_number'] }}</span>
                </div>
                
                <div class="info-grid">
                    <span class="label">Plot Number:</span>
                    <span class="underline-field">{{ $details['plot_number'] }}</span>
                </div>
                
                <div class="info-grid">
                    <span class="label">Plot Size:</span>
                    <span class="underline-field">{{ $details['plot_size'] }}</span>
                </div>
                
                <div class="info-grid">
                    <span class="label">LGA:</span>
                    <span class="underline-field">{{ $details['lga'] }}</span>
                </div>
                
                <div class="info-grid">
                    <span class="label">District:</span>
                    <span class="underline-field">{{ $details['district'] }}</span>
                </div>
                
                <div class="info-grid">
                    <span class="label">Land Use:</span>
                    <span class="underline-field">{{ $details['land_use_type'] }}</span>
                </div>
                
                @if($details['duration'])
                <div class="info-grid">
                    <span class="label">Duration:</span>
                    <span class="underline-field">{{ $details['duration'] }} Years</span>
                </div>
                @endif
                
                @if($details['plot_description'])
                <div class="mt-2">
                    <div class="label mb-1">Property Description:</div>
                    <div class="form-line">{{ $details['plot_description'] }}</div>
                </div>
                @endif
            </div>

            <!-- Financial Details -->
            <div class="border-b-2 border-gray-300 pb-4 mb-4">
                <h3 class="font-bold text-lg mb-3 text-gray-700">FINANCIAL DETAILS</h3>
                
                <div class="info-grid">
                    <span class="label">CONSIDERATION:</span>
                    <span class="underline-field">₦ {{ $details['consideration'] }}</span>
                </div>
                
                <div class="info-grid">
                    <span class="label">STAMP DUTY:</span>
                    <span class="underline-field">₦ {{ $details['stamp_duty'] }}</span>
                </div>
                
                <div class="info-grid">
                    <span class="label">REGISTRATION FEE:</span>
                    <span class="underline-field">₦ {{ $details['registration_fee'] }}</span>
                </div>
            </div>

            <!-- Solicitor Information -->
            @if($details['solicitor_name'])
            <div class="border-b-2 border-gray-300 pb-4 mb-4">
                <h3 class="font-bold text-lg mb-3 text-gray-700">LEGAL REPRESENTATIVE</h3>
                
                <div class="info-grid">
                    <span class="label">Solicitor Name:</span>
                    <span class="underline-field">{{ $details['solicitor_name'] }}</span>
                </div>
                
                @if($details['solicitor_address'])
                <div class="mt-2">
                    <div class="label mb-1">Solicitor Address:</div>
                    <div class="form-line">{{ $details['solicitor_address'] }}</div>
                </div>
                @endif
            </div>
            @endif

            <!-- Footer Section -->
            <div class="mt-8 pt-6 border-t-2 border-gray-400">
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <div class="mb-2">
                            <div class="label">GENERATED BY:</div>
                            <div class="form-line text-center">_________________________</div>
                        </div>
                        <p class="text-xs text-center text-gray-600">Generated on: {{ \Carbon\Carbon::parse($rds->generated_at)->format('d/m/Y H:i:s') }}</p>
                    </div>
                    
                    <div>
                        <div class="mb-2">
                            <div class="label">VERIFIED BY:</div>
                            <div class="form-line text-center">_________________________</div>
                        </div>
                        <p class="text-xs text-center text-gray-600">Date: _________________</p>
                    </div>
                </div>
                
                <div class="text-center mt-6 text-xs text-gray-500">
                    <p class="font-semibold">OFFICIAL DOCUMENT - KANO STATE GEOGRAPHIC INFORMATION SYSTEM</p>
                    <p class="mt-1">This document is generated from the official registry database</p>
                    @if($printCount > 1)
                    <p class="mt-2 text-red-600 font-semibold">Print #{{ $printCount }} - COPY</p>
                    @else
                    <p class="mt-2 text-green-600 font-semibold">Print #{{ $printCount }} - ORIGINAL</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-print on load (optional)
        // window.onload = function() { window.print(); }
        
        // Print count warning
        @if($printCount > 1)
        console.warn('This is a COPY print (#{{ $printCount }})');
        @endif
    </script>
</body>
</html>
