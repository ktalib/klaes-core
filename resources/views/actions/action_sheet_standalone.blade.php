<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $PageTitle ?? 'Director\'s Action Sheet' }}</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap');
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #f8f9fa;
            font-size: 20px;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        
        .document-container {
            width: 21cm;
            background: white;
            margin: 0 auto;
            padding: 1cm;
            position: relative;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            min-height: 29.7cm;
        }
        
        .header-line {
            height: 2px;
            background: linear-gradient(90deg, #1a56db, #1e3a8a);
            margin: 8px 0 12px 0;
        }
        
        .signature-line {
            border-bottom: 1px solid #cbd5e1;
            display: inline-block;
            width: 180px;
            margin: 0 5px;
        }
        
        .short-line {
            width: 100px;
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 12px;
            margin: 10px 0;
        }
        
        th, td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 600;
        }
        
        .signature-section {
            margin: 15px 0;
        }
        
        .compact-section {
            margin-bottom: 12px;
        }
        
        .status-passed {
            background-color: #dcfce7;
            color: #166534;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .status-declined {
            background-color: #f8f3c2;
            color: #991b1b;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 12px;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .status-paid {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .status-not-paid {
            background-color: #fef3c7;
            color: #92400e;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 12px;
        }

        .header-branding {
            margin-bottom: 12px;
        }

        .header-logos-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .header-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .header-title {
            flex: 1;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #1e3a8a;
            margin: 0;
        }

        .page-index-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }

        .page-index-badge {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }

        .section-heading {
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            margin: 16px 0;
            color: #374151;
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .print-button:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }
        
        /* Hide print button when printing */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
                width: 21cm;
                height: 29.7cm;
            }
            
            .document-container {
                width: 100%;
                height: 100%;
                padding: 1cm;
                margin: 0;
                box-shadow: none;
            }
            
            .print-button {
                display: none !important;
            }
            
            @page {
                size: A4 portrait;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button onclick="window.print()" class="print-button">
        <i data-lucide="printer"></i>
        Print Action Sheet
    </button>

    <!-- Document Container -->
    <div class="document-container">
        <!-- Header -->
        <div class="header-branding">
            <div class="header-logos-row">
                <img src="{{ asset('public/images/ministry-logo-left.jpg') }}" alt="Ministry Logo" class="header-logo" onerror="this.style.display='none'">
                <h1 class="header-title">SECTIONAL TITLE DEPARTMENT</h1>
                <img src="{{ asset('public/images/ministry-logo-right.jpeg') }}" alt="Ministry Logo" class="header-logo" onerror="this.style.display='none'">
            </div>
            <div class="page-index-wrapper">
                <span class="page-index-badge">PAGE INDEX: 1</span>
            </div>
        </div>
        
        

        <h2 class="section-heading">DIRECTOR'S APPROVAL ACTION SHEET</h2>

        @php
            $ownerNameDisplay = $application->computed_owner_name
                ?? trim(collect([$application->applicant_title ?? null, $application->first_name ?? null, $application->surname ?? null])->filter()->implode(' '))
                ?: 'Piece of land';

            $locationDisplay = $application->computed_location
                ?? strtoupper(trim(collect([
                    $application->property_house_no ?? null,
                    $application->property_plot_no ?? null,
                    $application->property_street_name ?? null,
                    $application->property_district ?? null,
                    $application->property_lga ?? null,
                    $application->property_state ?? null,
                ])->filter()->implode(', ')))
                ?: 'Piece of land';

            $plotNumberDisplay = $application->computed_plot_number ?? 'Piece of land';

            $statutoryFileNumberCandidates = collect([
                $application->fileno ?? null,
                $application->np_fileno ?? null,
                $application->new_primary_file_number ?? null,
                $application->mls_fileno ?? null,
            ])->filter(function ($value) {
                return !empty($value) && trim((string) $value) !== '';
            })->map(function ($value) {
                return trim((string) $value);
            })->unique()->values();

            $statutoryFileNumberDisplay = $statutoryFileNumberCandidates->first()
                ?? ($application->fileno ?? $application->np_fileno ?? $application->new_primary_file_number ?? '-');

            $cofoNumberDisplay = $application->computed_cofo_number
                ?? ($application->cofo_number ?? null);

            $cofoRecord = null;

            if ($statutoryFileNumberCandidates->isNotEmpty()) {
                try {
                    $cofoRecord = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                        ->table('Cofo')
                        ->where(function ($query) use ($statutoryFileNumberCandidates) {
                            $query->whereIn('mlsFNo', $statutoryFileNumberCandidates->all());
                            $query->orWhereIn('kangisFileNo', $statutoryFileNumberCandidates->all());
                            $query->orWhereIn('NewKANGISFileno', $statutoryFileNumberCandidates->all());
                        })
                        ->select('regNo')
                        ->first();
                } catch (\Throwable $exception) {
                    $cofoRecord = null;
                }
            }

            $cofoRegNo = null;
            if ($cofoRecord && isset($cofoRecord->regNo)) {
                $cofoRegNo = trim((string) $cofoRecord->regNo);
                if ($cofoRegNo === '') {
                    $cofoRegNo = null;
                }
            }

            $hasCofoRecord = !empty($cofoRegNo) && $cofoRegNo !== '0/0/0';

            if (is_string($cofoNumberDisplay)) {
                $cofoNumberDisplay = trim($cofoNumberDisplay);
                if ($cofoNumberDisplay === '') {
                    $cofoNumberDisplay = null;
                }
            }

            if ($hasCofoRecord && ($cofoNumberDisplay === null || $cofoNumberDisplay === '-')) {
                $cofoNumberDisplay = $cofoRegNo;
            }

            if ($cofoNumberDisplay === null) {
                $cofoNumberDisplay = '-';
            }

            $cofoLabelDisplay = $hasCofoRecord ? 'with CofO no' : 'with the old statutory file no';
            $cofoValueDisplay = $hasCofoRecord ? $cofoNumberDisplay : $statutoryFileNumberDisplay;

            $decisionDisplay = $application->computed_director_decision ?? strtoupper($application->application_status ?? 'APPROVED');
            $decisionIsApproved = $decisionDisplay === 'APPROVED';
            $decisionColor = $decisionIsApproved ? '#059669' : '#dc2626';
        @endphp

        <!-- Application Details -->
        <div style="color: #374151; margin-bottom: 12px;">
            <p style="margin-bottom: 12px;">
                The fragmentation of property with scheme no <span style="font-weight: 600;">{{ $application->scheme_no ?? '______' }}</span>, 
                ST file no <span style="font-weight: 600;">{{ $application->np_fileno ?? $application->fileno ?? '______' }}</span>, located at 
                <span style="font-weight: 600;">{{ $locationDisplay }}</span> under the name of 
                <span style="font-weight: 600;">{{ $ownerNameDisplay }}</span> {{ $cofoLabelDisplay }}
                <span style="font-weight: 600;">{{ $cofoValueDisplay }}</span> and plot no
                <span style="font-weight: 600;">{{ $plotNumberDisplay }}</span> is hereby 
                <span style="font-weight: bold; color: {{ $decisionColor }};">{{ $decisionDisplay }}</span>
                based on the under listed requirements:
            </p>
        </div>

        <!-- Requirements Table -->
        <table class="compact-section">
            <thead>
                <tr>
                    <th>REQUIREMENT</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>a) Application Requirements</td>
                    <td>
                        @if(!empty($application->application_requirements_status))
                            @if($application->application_requirements_status == 'Approved')
                                <span class="status-passed">PASSED</span>
                            @else
                                <span class="status-declined">DECLINED</span>
                            @endif
                        @else
                            <span class="status-passed">PASSED</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>b) Site Plan</td>
                    <td>
                        @if(!empty($application->site_plan_status))
                            @if($application->site_plan_status == 'Approved')
                                <span class="status-passed">PASSED</span>
                            @else
                                <span class="status-declined">DECLINED</span>
                            @endif
                        @else
                            <span class="status-passed">PASSED</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>c) OSS Inspection Report</td>
                    <td>
                        @if(!empty($application->oss_inspection_status))
                            @if($application->oss_inspection_status == 'Approved')
                                <span class="status-passed">PASSED</span>
                            @elseif($application->oss_inspection_status == 'Pending')
                                <span class="status-pending">PENDING</span>
                            @else
                                <span class="status-declined">DECLINED</span>
                            @endif
                        @else
                            <span class="status-pending">PENDING</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>d) Planning Advice</td>
                    <td>
                        @if($application->planning_recommendation_status == 'Approved')
                            <span class="status-passed">PASSED</span>
                        @else
                            <span class="status-declined">DECLINED</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>e) Application and processing Fees</td>
                    <td>
                        @if(!empty($application->application_fee_status))
                            @if($application->application_fee_status == 'Paid')
                                <span class="status-paid">PAID</span>
                            @else
                                <span class="status-not-paid">NOT PAID</span>
                            @endif
                        @else
                            <span class="status-paid">PAID</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>f) Outstanding land use charges</td>
                    <td>
                        @if(!empty($application->land_use_charges_status))
                            @if($application->land_use_charges_status == 'Paid')
                                <span class="status-paid">PAID</span>
                            @else
                                <span class="status-not-paid">NOT PAID</span>
                            @endif
                        @else
                            <span class="status-not-paid">NOT PAID</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Director's Remark -->
        <div class="signature-section">
            <div style="font-weight: bold; font-size: 14px; margin-bottom: 4px;">DIRECTOR'S REMARK:</div>
            <div style="padding: 12px; @if($decisionIsApproved) background-color: #f0fdf4; border-left: 4px solid #22c55e; @else background-color: #fef2f2; border-left: 4px solid #f8f3c2; @endif">
                <p style="color: #374151; font-size: 14px; margin: 0;">
                    @if(!empty($application->comments))
                        {{ $application->comments }}
                    @else
                        @if($decisionIsApproved)
                            The application has been approved and meets all the requirements for sectional title development.
                        @else
                   The application was declined because it lacks the basic requirement to gain planning recommendation for the proposed fragmentation.
                        @endif
                    @endif
                </p>
            </div>
        </div>
<br><br><br><br>
        <!-- Signature Section -->
        <div style="display: flex; justify-content: space-between; margin-top: 32px;">
            <div>
                <p style="font-size: 15px; margin: 0;">Sign: <span class="signature-line"></span></p>
            </div>
            <div>
                <p style="font-size: 15px; margin: 0;">Date: <span class="signature-line short-line"></span></p>
            </div>
        </div>

        <!-- Footer -->
        <div style="position: absolute; bottom: 16px; left: 0; right: 0; text-align: center; font-size: 13px; color: #6b7280;">
            <p style="margin: 0;">Generated by {{ $application->computed_generated_by ?? 'System' }} on {{ $application->computed_generated_at ?? now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>