<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acknowledgement Slip</title>
     <style>
     /* Smaller page bottom margin */
@page {
  size: A4 landscape;
  margin: 8mm 8mm 3mm 8mm;   /* bottom from 8–10mm -> 3mm */
}

/* Pull content closer to the bottom when printing */
@media print {
  .print-container { padding-bottom: 0; }
  .signature-section { margin-bottom: 2mm; }  /* was 6–10mm */
}
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        /* Base font styling - standardized */
        html, body { 
            font-family: Arial, sans-serif; 
            font-size: 13px; 
            line-height: 1.4; 
            color: #000; 
            background: white; 
        }
        
        .print-container { 
            width: 100%; 
            max-width: 100%; 
            padding: 8mm; 
            position: relative; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }
        
        /* Header styling */
        .header { 
            text-align: center; 
            margin-bottom: 12px; 
            border-bottom: 2px solid #000; 
            padding-bottom: 8px; 
            position: relative; 
        }
        .header h1 { 
            font-size: 20px; 
            font-weight: bold; 
            margin-bottom: 4px; 
            font-family: Arial, sans-serif;
        }
        .header h2 { 
            font-size: 18px; 
            font-weight: bold; 
            font-family: Arial, sans-serif;
        }
        
        /* App info styling */
        .app-info { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 14px; 
            padding-bottom: 8px; 
            border-bottom: 1px solid #ccc; 
            font-size: 13px; 
            font-weight: 500;
        }
        
        /* Layout styling */
        .two-column { 
            display: flex; 
            gap: 20px; 
            margin-bottom: 14px; 
        }
        .column { 
            flex: 1; 
        }
        
        /* Section titles */
        .section-title { 
            font-size: 15px; 
            font-weight: bold; 
            margin-bottom: 8px; 
            border-bottom: 1px solid #eee; 
            padding-bottom: 4px; 
            font-family: Arial, sans-serif;
        }
        
        /* Tables */
        .info-table { 
            width: 100%; 
            margin-bottom: 10px; 
        }
        .info-table td { 
            padding: 4px 0; 
            vertical-align: top; 
            font-size: 13px; 
            font-family: Arial, sans-serif;
        }
        .info-table td:first-child { 
            font-weight: 600; 
            width: 40%; 
        }
        
        /* Property and grid sections */
        .property-section { 
            margin-bottom: 12px; 
        }
        .property-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 18px; 
            margin-bottom: 12px; 
        }
        
        /* Status styling */
        .status-paid {
            color: #28a745;
            font-weight: 600;
        }
        .status-unpaid {
            color: #dc3545;
            font-weight: 600;
        }
        .status-submitted {
            color: #28a745;
            font-weight: 600;
        }
        .status-not-submitted {
            color: #dc3545;
            font-weight: 600;
        }
        
        /* Required document asterisk styling */
        .required-doc {
            color: #dc3545;
            font-weight: 700;
        }
    .signature-section { margin-top: auto; margin-bottom: 10mm; padding-top: 30px; border-top: 1px solid #eee; display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; }
        .signature-block { flex: 0 0 45%; text-align: center; }
        .signature-line { display: inline-block; width: 240px; border-bottom: 1px solid #000; margin: 0 auto 5px; }
        .signature-label { font-size: 11px; white-space: nowrap; }
    @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .print-container { page-break-inside: avoid; } }
    @media screen { body { background: #f5f5f5; padding: 20px; } .print-container { background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin: 0 auto; max-width: 297mm; } }

        /* Text Watermark */
    .watermark { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; transform: rotate(-25deg); font-size: 96px; color: rgba(0,0,0,0.06); letter-spacing: 4px; z-index: 0; pointer-events: none; user-select: none; }
    .print-container > *:not(.watermark) { position: relative; z-index: 1; }



        /* Signature section */
        .signature-section {
            margin-bottom: 1mm;
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
        }
        .signature-block {
            flex: 0 0 45%;
            text-align: center;
        }
        .signature-line {
            display: inline-block;
            width: 200px;
            border-bottom: 1px solid #000;
            margin: 0 auto 5px;
        }
        .signature-label {
            font-size: 12px;
            font-family: Arial, sans-serif;
            font-weight: 500;
            white-space: nowrap;
        }
        
        /* Documents section */
        .documents-section {
            margin-bottom: 6px;
        }
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            font-size: 13px;
            font-family: Arial, sans-serif;
            line-height: 1.4;
        }
        .document-item {
            padding: 3px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Payment section */
        .payment-section {
            margin-bottom: 10px;
        }

        /* Footer logos */
        .footer-logos {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 6mm;
            height: 30px;
            z-index: 2;
            pointer-events: none;
        }
        .footer-logos img {
            position: absolute;
            height: 30px;
            object-fit: contain;
        }
        .footer-logos img.left {
            left: 8mm;
        }
        .footer-logos img.right {
            right: 8mm;
        }
    </style>
</head>
<body>
    <div class="print-container">
        @if(!empty($watermarkText))
            <div class="watermark">{{ $watermarkText }}</div>
        @endif
        <!-- Header with two logos side-by-side -->
        <div class="header">
            <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Ministry Logo 1" style="position: absolute; left: 0; top: 0; height: 42px;">
            <div style="text-align: center;">
            <h1>MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
            <h2>SECTIONAL TITLING ACKNOWLEDGEMENT SLIP</h2>
            </div>
            <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Ministry Logo 2" style="position: absolute; right: 0; top: 0; height: 42px;">
        </div>

        <!-- Application Info -->
        <div class="app-info">
            <span><strong>Application ID:</strong> {{ $applicationId }}</span>
            <span><strong>Date:</strong> {{ date('d/m/Y') }}</span>
            {{-- <span><strong>Residence Type:</strong> {{ $residenceType }}</span> --}}
        </div>

        <!-- Two Column Layout -->
        <div class="two-column">
            <div class="column">
                <div class="section-title">Applicant Information</div>
                <table class="info-table">
                    <tr><td>Type:</td><td>{{ $applicantType }}</td></tr>
                    <tr><td>Name:</td><td>{{ $applicantName }}</td></tr>
                    <tr><td>Email:</td><td>{{ $applicantEmail }}</td></tr>
                    <tr><td>Phone:</td><td>{{ $applicantPhone }}</td></tr>
                    <tr><td>Address:</td><td>{{ $applicantAddress }}</td></tr>
                </table>
            </div>
            <div class="column">
                <div class="section-title">Property Details</div>
                <table class="info-table">
                    <tr><td>Units:</td><td>{{ $units }}</td></tr>
                    <tr><td>Blocks:</td><td>{{ $blocks }}</td></tr>
                    <tr><td>Sections:</td><td>{{ $sections }}</td></tr>
                    <tr><td>File Number(s):</td><td><strong>{{ $npFileNumber }}</strong> | <strong>{{ $fileNumber }}</strong></td></tr>
                </table>
            </div>
        </div>

        <!-- Property Address -->
        <div class="property-section">
            <div class="section-title">Property Address:  {{ $propertyFullAddress }}</div>
            {{-- <div class="property-grid">
                <table class="info-table">
                    <tr><td>House No:</td><td>{{ $propertyHouseNo }}</td></tr>
                    <tr><td>Plot No:</td><td>{{ $propertyPlotNo }}</td></tr>
                    <tr><td>Street:</td><td>{{ $propertyStreet }}</td></tr>
                </table>
                <table class="info-table">
                    <tr><td>District:</td><td>{{ $propertyDistrict }}</td></tr>
                    <tr><td>LGA:</td><td>{{ $propertyLGA }}</td></tr>
                    <tr><td>State:</td><td>{{ $propertyState }}</td></tr>
                </table>
            </div>
             --}}
        </div>

        <!-- Payment Information -->
        <div class="payment-section">
            <div class="section-title">Payment Information</div>
            <div class="property-grid">
                <table class="info-table">
                    <tr>
                        <td>Application Fee:</td>
                        <td>
                            @if(isset($paymentStatus['application_fee_paid']) && $paymentStatus['application_fee_paid'])
                                <span class="status-paid">Paid</span>
                            @else
                                <span class="status-unpaid">Unpaid</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>Processing Fee:</td>
                        <td>
                            @if(isset($paymentStatus['processing_fee_paid']) && $paymentStatus['processing_fee_paid'])
                                <span class="status-paid">Paid</span>
                            @else
                                <span class="status-unpaid">Unpaid</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>Site Plan Fee:</td>
                        <td>
                            @if(isset($paymentStatus['site_plan_fee_paid']) && $paymentStatus['site_plan_fee_paid'])
                                <span class="status-paid">Paid</span>
                            @else
                                <span class="status-unpaid">Unpaid</span>
                            @endif
                        </td>
                    </tr>
                </table>
                <table class="info-table">
                    {{-- @if(!empty($totalFee))
                        <tr><td><strong>Total:</strong></td><td><strong>₦{{ $totalFee }}</strong></td></tr>
                    @endif --}}
                    <tr><td>ST Invoice No:</td><td>{{ $receiptNumber ?? '-' }}</td></tr>
                    <tr><td>Payment Date:</td><td>{{ $paymentDate ?? '-' }}</td></tr>
                </table>
            </div>
        </div>

        <!-- Required Documents -->
        @if(isset($documentsWithStatus) && !empty($documentsWithStatus))
        <div class="documents-section">
            <div class="section-title">Required Documents</div>
            <div class="documents-grid">
            @foreach($documentsWithStatus as $documentName => $isSubmitted)
                <div class="document-item" style="display: block;">
                    @if(str_contains($documentName, '*'))
                        <span style="font-weight: 600;">
                            {{ str_replace('*', '', $documentName) }}<span class="required-doc">*</span>:
                        </span>
                    @else
                        <span style="font-weight: 500;">{{ $documentName }}:</span>
                    @endif
                    @if($isSubmitted)
                        <span class="status-submitted" style="margin-left: 8px;">Submitted</span>
                    @else
                        <span class="status-not-submitted" style="margin-left: 8px;">Not Submitted</span>
                    @endif
                </div>
            @endforeach
            </div>
            <div style="margin-top: 8px; font-size: 11px; color: #666; font-style: italic;">
            
            </div>
        </div>
        @endif

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">Applicant's Signature & Date</div>
            </div>
            <div style="flex: 0 0 auto; text-align: center; align-self: center;">
                <div style="font-size: 12px; font-family: Arial, sans-serif; font-weight: 500; white-space: nowrap; color: #666;">
                    Generated by {{ auth()->user()->first_name ?? '' }} {{ auth()->user()->last_name ?? '' }}
                </div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">Director, Sectional Titling</div>
            </div>
        </div>
            <div class="footer-logos">
                <img class="left" src="{{ asset('assets/logo/klaes.png') }}" alt="Logo">
                <img class="right" src="{{ asset('assets/logo/las.jpeg') }}" alt="LAS Logo">
            </div>
    </div>
  
    <script>
        function markPrinted() {
            // Detect if this is a sub or primary by checking presence of a hint in the URL
            const isSub = /\/sectionaltitling\/sub\/acknowledgement\//.test(window.location.pathname);
            const url = isSub
                ? "{{ url('/sectionaltitling/sub/acknowledgement') }}/{{ $applicationId }}/mark-printed"
                : "{{ url('/sectionaltitling/primary/acknowledgement') }}/{{ $applicationId }}/mark-printed";
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({})
            }).catch(() => {});
        }

        window.addEventListener('load', function() { setTimeout(function(){ window.print(); }, 400); });
        window.addEventListener('afterprint', function() {
            try { markPrinted(); } catch(e) {}
            setTimeout(function(){ window.close(); }, 800);
        });
    </script>
</body>
</html>
