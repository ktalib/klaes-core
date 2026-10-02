<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Slip Print</title>
    <style>
        /* Reset CSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.5;
            background: #fff;
        }
        
        /* Print Optimization */
        @page {
            size: A4 landscape;
            margin: 5mm;
        }
        
        @media print {
            html, body {
                width: 100%;
                height: 100%;
                overflow: hidden;
                background: #fff;
                font-size: 12pt;
            }
            
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
        }
        
        /* Layout */
        .print-container {
            width: 100%;
            margin: 0 auto;
            padding: 5mm;
            background: #fff;
        }
        
        .print-header {
            margin-bottom: 10mm;
            text-align: center;
        }
        
        .header-with-logos {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        
        .logo-left, .logo-right {
            width: 70px;
        }
        
        .header-text {
            flex: 1;
            text-align: center;
        }
        
        .header-text h1 {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .header-text h2 {
            font-size: 16pt;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .logo-image {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        
        /* Content */
        .print-body {
            font-size: 12pt;
            line-height: 1.5;
        }
        
        .print-body > div {
            margin-bottom: 5mm;
        }
        
        .print-body h3 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 3mm;
        }
        
        .print-body table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
        }
        
        .print-body table th {
            font-weight: bold;
            text-align: left;
            padding: 2mm;
            border-bottom: 1px solid #ccc;
        }
        
        .print-body table td {
            padding: 2mm;
            border-bottom: 1px solid #eee;
        }
        
        .print-body .section {
            margin-bottom: 5mm;
        }
        
        /* Footer */
        .print-footer {
            margin-top: 10mm;
            text-align: center;
            font-size: 10pt;
            color: #666;
        }
        
        /* Buttons */
        .print-buttons {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        
        .print-button {
            background: #1a56db;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 10px;
        }
        
        .print-button:hover {
            background: #1e429f;
        }
        
        /* Grid layout */
        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 5mm;
        }
        
        .grid-col-1 {
            grid-column: span 1;
        }
        
        .grid-col-2 {
            grid-column: span 2;
        }
        
        /* Info boxes */
        .info-box {
            padding: 3mm;
            border: 1px solid #eee;
            border-radius: 2mm;
            background-color: #f9f9f9;
        }
        
        .info-box h4 {
            font-weight: bold;
            margin-bottom: 2mm;
            font-size: 13pt;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 1mm;
        }
        
        .info-label {
            width: 120px;
            font-weight: bold;
        }
        
        .info-value {
            flex: 1;
        }
    </style>
</head>
<body>
    <!-- Print Control Buttons (hidden during print) -->
    <div class="print-buttons no-print">
        <button class="print-button" onclick="window.print()">Print Document</button>
        <button class="print-button" onclick="window.close()">Close</button>
        
        <!-- Debug Info (hidden during print) -->
        <div style="margin-top: 20px; padding: 10px; border: 1px solid #ccc; background: #f9f9f9;">
            <h3>Debug Information:</h3>
            <p><strong>Application ID:</strong> {{ $application->id ?? 'Not Available' }}</p>
            <p><strong>Application Type:</strong> {{ isset($isSUA) && $isSUA ? 'Standalone Unit Application (SUA)' : 'Sub Application' }}</p>
            <p><strong>Main Application ID:</strong> {{ $application->main_application_id ?? 'N/A' }}</p>
            <p><strong>Total Units:</strong> {{ $totalUnitsInMotherApp ?? 'N/A' }}</p>
            <p><strong>Total Sub Applications:</strong> {{ $totalSubApplications ?? 'N/A' }}</p>
        </div>
    </div>

    <!-- Print Container -->
    <div class="print-container">
        <!-- Header -->
        <div class="print-header">
            <div class="header-with-logos">
                <div class="logo-left">
                    <img src="{{ asset('assets/images/app-logo.png') }}" alt="Logo" class="logo-image">
                </div>
                <div class="header-text">
                    <h1>MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                    <h2>SECTIONAL TITLE UNIT APPLICATION</h2>
                    <p>{{ $isSUA ? 'Standalone Unit Application (SUA)' : 'Sub-Application (Secondary)' }}</p>
                </div>
                <div class="logo-right">
                    <img src="{{ asset('assets/images/kangis-logo.png') }}" alt="KANGIS Logo" class="logo-image">
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="print-body">
            <!-- Application Reference Information -->
            <div class="section">
                <h3>APPLICATION REFERENCE</h3>
                <div class="info-box">
                    <div class="grid">
                        @if(!$isSUA)
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">Main App ID:</div>
                                <div class="info-value"><strong>{{ $motherApplication->applicationID ?? 'N/A' }}</strong></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Applicant:</div>
                                <div class="info-value">
                                    {{ $motherApplication->applicant_title ?? '' }} 
                                    {{ $motherApplication->first_name ?? '' }} 
                                    {{ $motherApplication->surname ?? '' }}
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Form ID:</div>
                                <div class="info-value">{{ $motherApplication->id ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">File Number:</div>
                                <div class="info-value">{{ $motherApplication->fileno ?? 'N/A' }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Land Use:</div>
                                <div class="info-value">{{ $motherApplication->land_use ?? 'N/A' }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Total Units:</div>
                                <div class="info-value">{{ $totalUnitsInMotherApp ?? 'N/A' }}</div>
                            </div>
                        </div>
                        @else
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">Land Use:</div>
                                <div class="info-value"><strong>{{ isset($sua) ? $sua->land_use : ($selectedLandUse ?? 'N/A') }}</strong></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Allocation Source:</div>
                                <div class="info-value">{{ isset($sua) ? $sua->allocation_source : 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">Application Type:</div>
                                <div class="info-value">Standalone Unit Application (SUA)</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Date:</div>
                                <div class="info-value">{{ date('Y-m-d') }}</div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- File Numbers -->
            <div class="section">
                <h3>FILE NUMBERS</h3>
                <div class="info-box">
                    <div class="grid">
                        @if($isSUA)
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">Primary FileNo:</div>
                                <div class="info-value"><strong>{{ isset($primaryFileNo) ? $primaryFileNo : (isset($sua) ? $sua->np_fileno : 'Auto-generated') }}</strong></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">MLPP FileNo:</div>
                                <div class="info-value">{{ isset($primaryFileNo) ? $primaryFileNo : (isset($sua) ? $sua->np_fileno : 'Auto-generated') }}</div>
                            </div>
                        </div>
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">SUA FileNo:</div>
                                <div class="info-value"><strong>{{ isset($suaFileNo) ? $suaFileNo : (isset($sua) ? $sua->fileno : 'Auto-generated') }}</strong></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Scheme No:</div>
                                <div class="info-value">{{ isset($sua) ? $sua->scheme_no : 'N/A' }}</div>
                            </div>
                        </div>
                        @else
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">NP FileNo:</div>
                                <div class="info-value"><strong>{{ $npFileNo ?? 'N/A' }}</strong></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Scheme No:</div>
                                <div class="info-value">{{ isset($application) ? $application->scheme_no : 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">Unit FileNo:</div>
                                <div class="info-value"><strong>{{ $unitFileNo ?? 'N/A' }}</strong></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Application Date:</div>
                                <div class="info-value">{{ isset($application) ? $application->date_captured : date('Y-m-d') }}</div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Applicant Information -->
            <div class="section">
                <h3>APPLICANT INFORMATION</h3>
                <div class="info-box">
                    <div class="grid">
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">Applicant Type:</div>
                                <div class="info-value"><strong>{{ isset($application) ? $application->applicant_type : 'N/A' }}</strong></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Name:</div>
                                <div class="info-value">
                                    @if(isset($application))
                                        {{ $application->title ?? '' }} {{ $application->first_name ?? '' }} {{ $application->surname ?? '' }}
                                    @else
                                        N/A
                                    @endif
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Phone:</div>
                                <div class="info-value">
                                    @if(isset($application) && isset($application->phone_number))
                                        @php
                                            $phoneData = is_string($application->phone_number) ? json_decode($application->phone_number, true) : $application->phone_number;
                                        @endphp
                                        @if(is_array($phoneData))
                                            {{ implode(', ', $phoneData) }}
                                        @else
                                            {{ $application->phone_number }}
                                        @endif
                                    @else
                                        N/A
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">Email:</div>
                                <div class="info-value">{{ isset($application) ? $application->email : 'N/A' }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Address:</div>
                                <div class="info-value">{{ isset($application) ? $application->address : 'N/A' }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">ID Type:</div>
                                <div class="info-value">{{ isset($application) ? ($application->identification_type ?? 'N/A') : 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Unit Details -->
            <div class="section">
                <h3>UNIT DETAILS</h3>
                <div class="info-box">
                    <div class="grid">
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">Unit Type:</div>
                                <div class="info-value">{{ isset($application) ? ($application->unit_type ?? 'N/A') : 'N/A' }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Block No:</div>
                                <div class="info-value">{{ isset($application) ? ($application->block_number ?? 'N/A') : 'N/A' }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Floor No:</div>
                                <div class="info-value">{{ isset($application) ? ($application->floor_number ?? 'N/A') : 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="grid-col-1">
                            <div class="info-row">
                                <div class="info-label">Unit No:</div>
                                <div class="info-value">{{ isset($application) ? ($application->unit_number ?? 'N/A') : 'N/A' }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Unit Size:</div>
                                <div class="info-value">{{ isset($application) ? ($application->unit_size ?? 'N/A') : 'N/A' }}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Date:</div>
                                <div class="info-value">{{ isset($application) ? ($application->date_captured ?? date('Y-m-d')) : date('Y-m-d') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payments -->
            <div class="section">
                <h3>PAYMENT INFORMATION</h3>
                <div class="info-box">
                    <div class="grid">
                        <div class="grid-col-2">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Fee Type</th>
                                        <th>Amount</th>
                                        <th>Receipt No</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Application Fee</td>
                                        <td>
                                            @if(isset($application) && isset($application->application_fee))
                                                ₦{{ number_format($application->application_fee, 2) }}
                                            @else
                                                ₦0.00
                                            @endif
                                        </td>
                                        <td>{{ isset($application) ? ($application->receipt_number ?? 'N/A') : 'N/A' }}</td>
                                        <td>{{ isset($application) ? ($application->payment_date ?? 'N/A') : 'N/A' }}</td>
                                    </tr>
                                    @if(isset($application) && isset($application->processing_fee) && $application->processing_fee > 0)
                                    <tr>
                                        <td>Processing Fee</td>
                                        <td>₦{{ number_format($application->processing_fee, 2) }}</td>
                                        <td>{{ $application->receipt_number ?? 'N/A' }}</td>
                                        <td>{{ $application->payment_date ?? 'N/A' }}</td>
                                    </tr>
                                    @endif
                                    @if(isset($application) && isset($application->survey_fee) && $application->survey_fee > 0)
                                    <tr>
                                        <td>Survey Fee</td>
                                        <td>₦{{ number_format($application->survey_fee, 2) }}</td>
                                        <td>{{ $application->receipt_number ?? 'N/A' }}</td>
                                        <td>{{ $application->payment_date ?? 'N/A' }}</td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="print-footer">
            <p>This is an official application receipt. Please keep for your records.</p>
            <p>Application submitted on: {{ date('Y-m-d H:i:s') }}</p>
        </div>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            console.log('Page loaded, preparing to print...');
            
            // Check if the document has content before printing
            if (document.querySelector('.print-body') && 
                document.querySelector('.print-header')) {
                console.log('Content found, printing document...');
                
                // Allow time for resources to load
                setTimeout(function() {
                    window.print();
                    console.log('Print dialog triggered');
                }, 1000);
            } else {
                console.error('Document content not fully loaded');
                alert('Document content is not fully loaded. Please try printing manually using the Print button.');
            }
        };
    </script>
</body>
</html>