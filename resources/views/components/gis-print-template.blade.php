@props(['gisData'])

<div class="gis-print-container">
    <!-- Print-specific styling -->
    <style>
        @media print {
            /* Reset all elements */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            /* Hide everything by default */
            body * {
                visibility: hidden;
            }
            
            /* Only show our print container and its contents */
            .gis-print-container,
            .gis-print-container * {
                visibility: visible;
            }
            
            /* Position our container at the top */
            .gis-print-container {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
            }
            
            /* Hide any buttons or interactive elements within our container */
            .gis-print-container button,
            .gis-print-container .btn,
            .gis-print-container a[href],
            .gis-print-container .no-print {
                display: none !important;
            }
            
            /* Page setup */
            @page {
                size: A4;
                margin: 1.5cm;
            }
            
            /* Make text black for better printing */
            .gis-print-container h1, 
            .gis-print-container h2, 
            .gis-print-container h3, 
            .gis-print-container h4, 
            .gis-print-container h5, 
            .gis-print-container h6, 
            .gis-print-container p,
            .gis-print-container span,
            .gis-print-container div {
                color: black !important;
            }
            
            /* Style for section headings */
            .gis-section h3 {
                font-size: 16pt;
                margin-bottom: 10px;
                border-bottom: 1px solid #000;
                padding-bottom: 5px;
            }
            
            /* Style for the data grid */
            .gis-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-bottom: 20px;
            }
            
            /* Data item styling */
            .gis-data-item {
                page-break-inside: avoid;
                margin-bottom: 8px;
            }
            
            .gis-label {
                font-weight: bold;
                font-size: 9pt;
                margin-bottom: 2px;
            }
            
            .gis-value {
                font-size: 10pt;
            }
            
            /* Ensure proper page breaks */
            .gis-section {
                page-break-inside: avoid;
                margin-bottom: 20px;
                padding: 15px;
                border: 1px solid #ddd;
                background-color: white !important;
            }
            
            /* Title styling */
            .gis-report-title {
                font-size: 18pt;
                font-weight: bold;
                text-align: center;
                margin-bottom: 20px;
                margin-top: 10px;
            }

            /* Document thumbnails in print */
            .gis-document-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }

            .gis-document-item {
                font-size: 9pt;
                border: 1px solid #eee;
                padding: 5px;
            }
        }
    </style>

    <!-- Report Title -->
    <h1 class="gis-report-title">GIS Data Report</h1>

    <!-- File Information Section -->
    <div class="gis-section">
        <h3>File Information</h3>
        <div class="gis-grid">
            <div class="gis-data-item">
                <p class="gis-label">MLSF Number</p>
                <p class="gis-value">{{ $gisData->mlsfNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">KANGIS File Number</p>
                <p class="gis-value">{{ $gisData->kangisFileNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">New KANGIS File Number</p>
                <p class="gis-value">{{ $gisData->NewKANGISFileno ?? 'N/A' }}</p>
            </div>
        </div>
    </div>

    <!-- Plot Information Section -->
    <div class="gis-section">
        <h3>Plot Information</h3>
        <div class="gis-grid">
            <div class="gis-data-item">
                <p class="gis-label">Plot Number</p>
                <p class="gis-value">{{ $gisData->plotNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Block Number</p>
                <p class="gis-value">{{ $gisData->blockNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Approved Plan Number</p>
                <p class="gis-value">{{ $gisData->approvedPlanNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">TP Plan Number</p>
                <p class="gis-value">{{ $gisData->tpPlanNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Area (in Hectares)</p>
                <p class="gis-value">{{ $gisData->areaInHectares ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Land Use</p>
                <p class="gis-value">{{ $gisData->landUse ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Specific Use</p>
                <p class="gis-value">{{ $gisData->specifically ?? 'N/A' }}</p>
            </div>
        </div>
    </div>

    <!-- Location Information Section -->
    <div class="gis-section">
        <h3>Location Information</h3>
        <div class="gis-grid">
            <div class="gis-data-item">
                <p class="gis-label">Layout Name</p>
                <p class="gis-value">{{ $gisData->layoutName ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">District Name</p>
                <p class="gis-value">{{ $gisData->districtName ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">LGA Name</p>
                <p class="gis-value">{{ $gisData->lgaName ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">State Name</p>
                <p class="gis-value">{{ $gisData->StateName ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Street Name</p>
                <p class="gis-value">{{ $gisData->streetName ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">House Number</p>
                <p class="gis-value">{{ $gisData->houseNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">House Type</p>
                <p class="gis-value">{{ $gisData->houseType ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Tenancy</p>
                <p class="gis-value">{{ $gisData->tenancy ?? 'N/A' }}</p>
            </div>
        </div>
    </div>

    <!-- Title Information Section -->
    <div class="gis-section">
        <h3>Title Information</h3>
        <div class="gis-grid">
            <div class="gis-data-item">
                <p class="gis-label">Old Title Serial No</p>
                <p class="gis-value">{{ $gisData->oldTitleSerialNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Old Title Page No</p>
                <p class="gis-value">{{ $gisData->oldTitlePageNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Old Title Volume No</p>
                <p class="gis-value">{{ $gisData->oldTitleVolumeNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Deeds Date</p>
                <p class="gis-value">{{ $gisData->deedsDate ? date('d M, Y', strtotime($gisData->deedsDate)) : 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Deeds Time</p>
                <p class="gis-value">{{ $gisData->deedsTime ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Certificate Date</p>
                <p class="gis-value">{{ $gisData->certificateDate ? date('d M, Y', strtotime($gisData->certificateDate)) : 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">CofO Serial No</p>
                <p class="gis-value">{{ $gisData->CofOSerialNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Title Issued Year</p>
                <p class="gis-value">{{ $gisData->titleIssuedYear ?? 'N/A' }}</p>
            </div>
        </div>
    </div>

    <!-- Owner Information Section -->
    <div class="gis-section">
        <h3>Owner Information</h3>
        <div class="gis-grid">
            <div class="gis-data-item">
                <p class="gis-label">Original Allottee</p>
                <p class="gis-value">{{ $gisData->originalAllottee ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Address of Original Allottee</p>
                <p class="gis-value">{{ $gisData->addressOfOriginalAllottee ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Change of Ownership</p>
                <p class="gis-value">{{ $gisData->changeOfOwnership ?? 'No' }}</p>
            </div>
            
            @if($gisData->changeOfOwnership == 'Yes')
            <div class="gis-data-item">
                <p class="gis-label">Reason for Change</p>
                <p class="gis-value">{{ $gisData->reasonForChange ?? 'N/A' }}</p>
            </div>
            @endif
            
            <div class="gis-data-item">
                <p class="gis-label">Current Allottee</p>
                <p class="gis-value">{{ $gisData->currentAllottee ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Address of Current Allottee</p>
                <p class="gis-value">{{ $gisData->addressOfCurrentAllottee ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Title of Current Allottee</p>
                <p class="gis-value">{{ $gisData->titleOfCurrentAllottee ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Phone Number</p>
                <p class="gis-value">{{ $gisData->phoneNo ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Email Address</p>
                <p class="gis-value">{{ $gisData->emailAddress ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Occupation</p>
                <p class="gis-value">{{ $gisData->occupation ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Nationality</p>
                <p class="gis-value">{{ $gisData->nationality ?? 'N/A' }}</p>
            </div>
            <div class="gis-data-item">
                <p class="gis-label">Company RC Number</p>
                <p class="gis-value">{{ $gisData->CompanyRCNo ?? 'N/A' }}</p>
            </div>
        </div>
    </div>

    <!-- Document Attachments Section (simplified for printing) -->
    <div class="gis-section">
        <h3>Document Attachments</h3>
        <div class="gis-document-grid">
            @php
                $fileFields = [
                    'transactionDocument' => 'Transaction Document',
                    'passportPhoto' => 'Passport Photo',
                    'nationalId' => 'National ID',
                    'internationalPassport' => 'International Passport',
                    'businessRegCert' => 'Business Registration Certificate',
                    'formCO7AndCO4' => 'Form CO7 & CO4',
                    'certOfIncorporation' => 'Certificate of Incorporation',
                    'memorandumAndArticle' => 'Memorandum & Article',
                    'letterOfAdmin' => 'Letter of Administration',
                    'courtAffidavit' => 'Court Affidavit',
                    'policeReport' => 'Police Report',
                    'newspaperAdvert' => 'Newspaper Advertisement',
                    'picture' => 'Picture',
                    'SurveyPlan' => 'Survey Plan'
                ];
                $hasDocuments = false;
            @endphp
            
            @foreach($fileFields as $field => $label)
                @if(isset($gisData->$field) && $gisData->$field)
                    @php $hasDocuments = true; @endphp
                    <div class="gis-document-item">
                        <p class="gis-label">{{ $label }}</p>
                        <p class="gis-value">Document Available</p>
                    </div>
                @endif
            @endforeach
            
            @if(!$hasDocuments)
                <div style="grid-column: span 3;">
                    <p>No documents attached.</p>
                </div>
            @endif
        </div>
    </div>
</div>
