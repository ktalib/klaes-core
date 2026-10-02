<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sectional Titling Memo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    
    </style>
</head>
<body class="py-4">
    <div class="no-print flex justify-center mb-4">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg shadow-md transition duration-200 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m4 4h6a2 2 0 002-2v-4a2 2 0 00-2-2h-6a2 2 0 00-2 2v4a2 2 0 002 2z" />
            </svg>
            Print Document
        </button>
    </div>

    <!-- Memo Container -->
    <div class="memo-container">
        <!-- Header with Logos -->
    <div class="logo-section">
            <!-- Left Ministry Logo -->
            <img src="{{ asset('public/images/ministry-logo-left.jpg') }}" alt="Ministry Logo" 
                 style="position: absolute; left: 0; top: -10px; width: 50px; height: 50px; object-fit: contain;" 
                 onerror="this.style.display='none'">
            
            <!-- Right Ministry Logo -->
            <img src="{{ asset('public/images/ministry-logo-right.jpeg') }}" alt="Ministry Logo" 
                 style="position: absolute; right: 0; top: -10px; width: 50px; height: 50px; object-fit: contain;" 
                 onerror="this.style.display='none'">
            
           
        </div>
        

             <div class="memo-header-wrap">
          
            
            <!-- Header Content (centered) -->
            <div class="memo-header">
                <br>
                <p class="font-bold text-left"><span class="data-value">{{ $memo->memo_no ?? '-' }}</span></p>
                <p class="font-bold text-left">PERMANENT SECRETARY,</p>
            </div>
        </div>
        
      

        <!-- Main Content -->
        @php
            $propertyLocationText = null;

            if (!empty($memo->property_location)) {
                $propertyLocationText = trim($memo->property_location);
            } elseif (!empty($memo->property_street_name) || !empty($memo->property_district) || !empty($memo->property_lga)) {
                $propertyLocationText = trim(preg_replace('/\s+/', ' ', trim(($memo->property_street_name ?? '') . ', ' . ($memo->property_district ?? '') . ', ' . ($memo->property_lga ?? ''), ', ')));
            }

            if (empty($propertyLocationText)) {
                $propertyLocationText = $memo->property_lga ?? ($suaApplication->unit_lga ?? $suaApplication->mother_lga ?? 'Nasarawa Heights, Fagge, Fagge');
            }

            $ownerNamesList = isset($ownerNamesList) && is_array($ownerNamesList)
                ? array_values(array_filter(array_map('trim', $ownerNamesList)))
                : [];

            $applicantDisplayName = trim($memo->memo_applicant_name ?? $memo->owner_name ?? '');
            if ($applicantDisplayName === '' && count($ownerNamesList) > 0) {
                $applicantDisplayName = $ownerNamesList[0];
            }
            if ($applicantDisplayName === '') {
                $applicantDisplayName = 'Applicant Name Not Provided';
            }

            $landUseRaw = $suaApplication->land_use
                ?? $memo->land_use
                ?? $suaApplication->unit_land_use
                ?? $suaApplication->land_use_type
                ?? null;

            if (is_string($landUseRaw)) {
                $landUseDisplay = ucwords(strtolower(trim($landUseRaw)));
            } else {
                $landUseDisplay = 'Land Use Not Provided';
            }

            $allocationEntityRaw = $suaApplication->allocation_entity
                ?? $memo->allocation_entity
                ?? $suaApplication->allocation_source
                ?? null;

            if (is_string($allocationEntityRaw) && trim($allocationEntityRaw) !== '') {
                $allocationEntityDisplay = trim($allocationEntityRaw);
            } else {
                $allocationEntityDisplay = 'Issuing Institution Not Provided';
            }

            $allocationRefNoDisplay = 'N/A';
            if (isset($memo->allocation_ref_no) && is_string($memo->allocation_ref_no) && trim($memo->allocation_ref_no) !== '') {
                $allocationRefNoDisplay = trim($memo->allocation_ref_no);
            }
        @endphp

        <div class="text-gray-700 mb-6">
            <p class="mb-4">Kindly find page <span class="data-value">{{ $memo->page_no ?? ($landAdmin->page_no ?? '-') }}</span> is an application for sectional titling in respect of a property (plaza) covered by Certificate of Occupancy No. <br><span class="data-value" style="word-break: break-all;">{{ $suaApplication->fileno ?? $memo->certificate_number ?? $memo->fileno ?? '-' }}</span> situated at <span class="data-value">{{ ucwords(strtolower($propertyLocationText)) }}</span> in the name of <span class="data-value">{{ ucwords(strtolower($applicantDisplayName)) }}</span>.</p>
            
            <p class="mb-4">As well as change of name to various shop owners as per attached on the application.</p>
            
            <p class="mb-4"><span class="data-value">{{ $memo->planner_recommendation ?? 'The application was referred to One stop shop for planning, engineering as well as architectural views. The planners recommended the application because it is feasible, and the shops meet the minimum requirements for commercial titles. Moreover, the proposal is accessible and conforms with the existing commercial development in the area.' }}</span></p>
            
            <p class="mb-4">However, this recommendation is based on recommended site plan at page <span class="data-value">{{ $memo->page_no ?? ($landAdmin->page_no ?? '66') }}</span> and architectural design at page <span class="data-value">{{ $memo->arc_design_page_no ?? ($landAdmin->arc_design_page_no ?? '33') }}</span> with measurements of approved dimensions contained Therein respectively.</p>
            
            <p class="mb-4">Meanwhile, the title was granted for commercial purposes for a term of <span class="data-value">{{ $memo->term_years ?? ($totalYears ?? '40') }}</span> years commencing from <span class="data-value">{{ $memo->commencement_date ? date('d/m/Y', strtotime($memo->commencement_date)) : ($memo->approval_date ? date('d/m/Y', strtotime($memo->approval_date)) : '21/04/2025') }}</span> and has a residual term of <span class="data-value">{{ $memo->residual_years ?? ($residualYears ?? '40') }}</span> years to expire.</p>
            
            <p class="mb-4 font-semibold">In view of the above, you may kindly wish to recommend the following for approval of the Honourable Commissioner.</p>
            
            <ol class="list-decimal pl-5 mb-4">
                <li class="mb-2">Consider and approve the application for Sectional Titling over a standalone <span class="data-value">{{ ucwords(strtolower($landUseDisplay)) }}</span> unit situated at <span class="data-value">{{ ucwords(strtolower($propertyLocationText)) }}</span> with allocation reference No. <span class="data-value">{{ $allocationRefNoDisplay }}</span> from <span class="data-value">{{ $allocationEntityDisplay  }}</span> in favour of <span class="data-value">{{ ucwords(strtolower($applicantDisplayName)) }}</span>.</li>

                <li class="mb-2">Consider and approve the change of name to the new owner (applicant) of the stand-alone unit.</li>

                <li class="mb-2">CONSIDER AND APPROVE THE CANCELLATION OF THE ALLOCATION SLIP FROM <span class="data-value">{{ strtoupper($allocationEntityDisplay) }}</span> TO PAVE WAY FOR A NEW UNIT(S) OF STAND-ALONE SECTIONAL TITLE TO THE APPLICANT.</li>
            </ol>
 

            <div class="overflow-x-auto mb-6">
                <table>
                    <thead>
                        <tr>
                            <th>SN</th>
                            <th>UNIT OWNER</th>
                            <th>UNIT NUMBER</th>
                            <th>MEASUREMENT (M²)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($buyersList) && count($buyersList) > 0)
                            @foreach($buyersList as $index => $buyer)
                                @php
                                    $unitOwnerName = trim(implode(' ', array_filter([
                                        $buyer->buyer_title ?? null,
                                        $buyer->buyer_name ?? null,
                                    ])));

                                    if ($unitOwnerName === '' && !empty($buyer->owner_name)) {
                                        $unitOwnerName = trim($buyer->owner_name);
                                    }

                                    if ($unitOwnerName === '' && !empty($memo->memo_applicant_name)) {
                                        $unitOwnerName = trim($memo->memo_applicant_name);
                                    }

                                    if ($unitOwnerName === '' && !empty($memo->owner_name)) {
                                        $unitOwnerName = trim($memo->owner_name);
                                    }

                                    if ($unitOwnerName === '' && isset($ownerNamesList[$index])) {
                                        $unitOwnerName = $ownerNamesList[$index];
                                    }

                                    if ($unitOwnerName === '' && count($ownerNamesList) > 0) {
                                        $unitOwnerName = $ownerNamesList[0];
                                    }

                                    if ($unitOwnerName === '') {
                                        $unitOwnerName = 'Applicant Name Not Provided';
                                    }
                                @endphp
                                <tr>
                                    <td class="data-value">{{ $index + 1 }}.</td>
                                    <td class="data-value">{{ $unitOwnerName }}</td>
                                    <td class="data-value">{{ $buyer->unit_no ?? 'N/A' }}</td>
                                    <td class="data-value">{{ $buyer->measurement ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        @elseif(isset($memo->buyers) && is_array($memo->buyers) && count($memo->buyers) > 0)
                            @foreach($memo->buyers as $index => $buyer)
                                @php
                                    $unitOwnerName = '';

                                    if (isset($buyer['buyer_title']) || isset($buyer['buyer_name'])) {
                                        $unitOwnerName = trim(implode(' ', array_filter([
                                            $buyer['buyer_title'] ?? null,
                                            $buyer['buyer_name'] ?? null,
                                        ])));
                                    }

                                    if ($unitOwnerName === '' && !empty($buyer['unit_owner'])) {
                                        $unitOwnerName = trim($buyer['unit_owner']);
                                    }

                                    if ($unitOwnerName === '' && !empty($memo->memo_applicant_name)) {
                                        $unitOwnerName = trim($memo->memo_applicant_name);
                                    }

                                    if ($unitOwnerName === '' && !empty($memo->owner_name)) {
                                        $unitOwnerName = trim($memo->owner_name);
                                    }

                                    if ($unitOwnerName === '' && isset($ownerNamesList[$index])) {
                                        $unitOwnerName = $ownerNamesList[$index];
                                    }

                                    if ($unitOwnerName === '' && count($ownerNamesList) > 0) {
                                        $unitOwnerName = $ownerNamesList[0];
                                    }

                                    if ($unitOwnerName === '') {
                                        $unitOwnerName = 'Applicant Name Not Provided';
                                    }
                                @endphp
                                <tr>
                                    <td class="data-value">{{ $index + 1 }}.</td>
                                    <td class="data-value">{{ $unitOwnerName }}</td>
                                    <td class="data-value">{{ $buyer['unit_number'] ?? 'N/A' }}</td>
                                    <td class="data-value">{{ $buyer['measurement'] ?? $buyer['plot_size'] ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        @elseif(count($ownerNamesList) > 0)
                            @foreach($ownerNamesList as $index => $owner)
                                <tr>
                                    <td class="data-value">{{ $index + 1 }}.</td>
                                    <td class="data-value">{{ $owner }}</td>
                                    <td class="data-value">{{ $suaApplication->unit_number ?? 'N/A' }}</td>
                                    <td class="data-value">{{ $suaApplication->unit_size ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td class="data-value">1.</td>
                                <td class="data-value">{{ $applicantDisplayName }}</td>
                                <td class="data-value">{{ $suaApplication->unit_number ?? 'N/A' }}</td>
                                <td class="data-value">{{ $suaApplication->unit_size ?? 'N/A' }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Corrected Signatures Section -->
        <div class="signature-grid">
            <div>
                <div class="signature-item">
                    <p>Name: - <span class="signature-line"></span></p>
                </div>
                <div class="signature-item">
                    <p>Rank: <span class="signature-line"></span></p>
                </div>
                <div class="signature-item">
                    <p>Sign: <span class="signature-line"></span></p>
                </div>
                <div class="signature-item">
                    <p>Date: <span class="signature-line short-line"></span></p>
                </div>
            </div>
            <div>
                <div class="signature-item">
                    <p>Counter Sign: - <span class="signature-line"></span></p>
                </div>
                <div class="signature-item">
                    <p><span class="data-value">{{ $memo->director_rank ?? 'Director Sectional Titling' }}</span></p>
                </div>
                <div class="signature-item">
                    <p>Date: <span class="signature-line short-line"></span></p>
                </div>
            </div>
        </div>

        <!-- Permanent Secretary Section - Corrected -->
        <div class="approval-section">
            <div class="title">PERMANENT SECRETARY</div>
            <div class="permanent-secretary-line">
                <p>The application is hereby recommended for your kind approval, please. </p>
                <span class="signature-line"></span>
            </div>
            <div class="permanent-secretary-line">
                <p>Date: <span class="signature-line short-line"></span> 2025.</p>

                <span class="font-semibold">Permanent Secretary</span>
            </div>
        </div>
        
        <!-- Honourable Commissioner Section  -->
        <div class="approval-section">
            <div class="title">HONOURABLE COMMISSIONER</div>
            <div class="permanent-secretary-line">
                <p>The application is hereby APPROVED/NOT APPROVED. </p>
                <span class="signature-line"></span>
            </div>
            <div class="permanent-secretary-line">
                <p>Date: <span class="signature-line short-line"></span> 2025.</p>

                <span class="font-semibold">Honourable Commissioner</span>
            </div>
        </div>
        <br><br>
        
  <div class="absolute inset-x-0 text-center text-xs text-gray-500" style="bottom: 1cm; padding: 0 1cm; z-index: 15;">
            <p>Generated on: {{ now()->format('d/m/Y') }} by {{ auth()->user()->name ?? 'System' }}</p>
        </div>
    </div>
    
</body>
</html>