<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $PageTitle ?? 'Director\'s Action Sheet - Unit Application' }}</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap');
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #f8f9fa;
            font-size: 18px;
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
            background: linear-gradient(90deg, #8b5cf6, #7c3aed);
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
            font-size: 11px;
            margin: 10px 0;
        }
        
        th, td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
        }
        
        th {
            background-color: #f3f4f6;
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
            font-size: 11px;
        }
        
        .status-declined {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 11px;
        }
        
        .status-paid {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 11px;
        }
        
        .status-not-paid {
            background-color: #fef3c7;
            color: #92400e;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 11px;
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #8b5cf6;
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
            background: #7c3aed;
            transform: translateY(-1px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }

        .page-index-wrapper {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 4px;
            margin-bottom: 8px;
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


               .header-title {
            flex: 1;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #1e3a8a;
            margin: 0;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button onclick="window.print()" class="print-button">
        <i data-lucide="printer"></i>
        Print Unit Action Sheet
    </button>

    <!-- Document Container -->
    <div class="document-container">
        <!-- Header with left and right logos -->
      <div class="header-branding">
            <div class="header-logos-row">
                <img src="{{ asset('public/images/ministry-logo-left.jpg') }}" alt="Ministry Logo" class="header-logo" onerror="this.style.display='none'">
                <h1 class="header-title">SECTIONAL TITLE DEPARTMENT</h1>
                <img src="{{ asset('public/images/ministry-logo-right.jpeg') }}" alt="Ministry Logo" class="header-logo" onerror="this.style.display='none'">
            </div>
            <br>
            <div class="page-index-wrapper">
                <span class="page-index-badge">PAGE INDEX: 1</span>
            </div>
        </div>
        
        
        
        
        <h2 style="font-size: 18px; font-weight: bold; text-align: center; margin: 16px 0; color: #374151;">DIRECTOR'S APPROVAL ACTION SHEET </h2>

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
            $decisionDisplay = $application->computed_director_decision ?? strtoupper($application->application_status ?? 'APPROVED');
            $decisionIsApproved = $decisionDisplay === 'APPROVED';
            $decisionColor = $decisionIsApproved ? '#059669' : '#dc2626';
        @endphp

        <!-- Application Details (updated phrasing for unit applications) -->
        <div style="color: #374151; margin-bottom: 12px;">
            <p style="margin-bottom: 12px;">
                The application for the unit  with scheme no <span style="font-weight: 600;">{{ $application->scheme_no ?? '______' }}</span> and ST file no <span style="font-weight: 600;">{{ $application->fileno ?? $application->mls_fileno ?? '______' }}</span>, located at <span style="font-weight: 600;">{{ $locationDisplay ?? '______' }}</span>, under the name of
                <span style="font-weight: 600;">
                    @if(isset($application->applicant_type) && $application->applicant_type === 'Corporate')
                        {{ $application->corporate_name ?? $ownerNameDisplay ?? '______' }}
                    @elseif(isset($application->applicant_type) && $application->applicant_type === 'Multiple')
                        {{ $application->computed_multiple_owners_names ?? $application->multiple_owners_names ?? $ownerNameDisplay ?? '______' }}
                    @else
                        {{-- Default to Individual formatting --}}
                        {{ trim(implode(' ', array_filter([ $application->applicant_title ?? '', $application->first_name ?? '', $application->middle_name ?? '', $application->surname ?? '']))) ?: ($ownerNameDisplay ?? '______') }}
                    @endif
                </span>,
                with unit no <span style="font-weight: 600;">{{ $application->unit_no ?? $application->unit_number ?? '______' }}</span>, is hereby <span style="font-weight: bold; color: {{ $decisionColor }};">{{ strtoupper(($decisionDisplay ?? '') === 'DECLINED' ? 'DECLINED' : ($decisionDisplay ?? 'DECLINED')) }}</span> based on the under listed requirements:
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
                        @if($application->primary_application_status == 'Approved')
                            <span class="status-passed">PASSED</span>
                        @else
                            <span class="status-declined">PENDING</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>c) OSS Inspection Report</td>
                    <td>
                        @if(!empty($application->unit_plan_status))
                            @if($application->unit_plan_status == 'Approved')
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
                        @php
                            // Fetch outstanding land use charge for the unit application
                            $landUseCharge = DB::connection('sqlsrv')
                                ->table('billing')
                                ->where('sub_application_id', $application->id ?? $application->subapplication_id ?? null)
                                ->value('Land_Use_Charge');
                        @endphp
                        @if(empty($landUseCharge) || floatval($landUseCharge) <= 0)
                            <span class="status-not-paid">NOT PAID</span>
                        @else
                            <span class="status-paid">PAID</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Director's Remark -->
        <div class="signature-section">
            <div style="font-weight: bold; font-size: 14px; margin-bottom: 4px;">DIRECTOR'S REMARK:</div>
            <div style="padding: 12px; @if($decisionIsApproved) background-color: #f0fdf4; border-left: 4px solid #22c55e; @else background-color: #fef2f2; border-left: 4px solid #ef4444; @endif">
                <p style="color: #374151; font-size: 14px; margin: 0;">
                    @if(!empty($application->comments))
                        {{ $application->comments }}
                    @else
                        @if($decisionIsApproved)
                            The unit application has been approved and meets all the requirements for sectional title unit development.
                        @else
                            The unit application was declined due to missing or inadequate requirements for the proposed unit development.
                        @endif
                    @endif
                </p>
            </div>
        </div>

       <br><br><br><br>
        <!-- Signature Section -->
        <div style="display: flex; justify-content: space-between; margin-top: 32px;">
            <div>
                <p style="font-size: 14px; margin: 0;">Sign: <span class="signature-line"></span></p>
            </div>
            <div>
                <p style="font-size: 14px; margin: 0;">Date: <span class="signature-line short-line"> </span></p>
            </div>
        </div>

        <!-- Footer -->
        <div style="position: absolute; bottom: 16px; left: 0; right: 0; text-align: center; font-size: 12px; color: #6b7280;">
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