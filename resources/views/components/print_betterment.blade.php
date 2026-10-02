<!DOCTYPE html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Betterment Bill Receipt</title>
    <style>
        @media print {
            body { margin: 0; padding: 0; }
            @page { size: A4; margin: 0.5cm 0.5cm 0.5cm 0.5cm; } /* Further reduced top/bottom margin */
        }
        body {
            font-family: 'Arial', sans-serif;
            line-height: 2.0; /* Slightly reduced */
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 18px 10px 18px 10px; /* Slightly increased vertical padding */
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px; /* Reduced */
            border-bottom: 2px solid #166534;
            padding-bottom: 8px; /* Reduced */
        }
        .logo {
            width: 60px; /* Reduced */
            height: 60px; /* Reduced */
        }
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .title {
            text-align: center;
        }
        .title h1 {
            color: #166534;
            font-size: 15px; /* Reduced */
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        .title h2 {
            color: #4adc26;
            font-size: 40; /* Reduced */
            font-weight: bold;
            margin: 3px 0 0 0; /* Reduced */
        }
        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 100; /* Further increased gap */
            margin-bottom: 100px; /* Further increased margin */
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px; /* Reduced */
        }
        .detail-group p {
            margin: 8px 0; /* Reduced */
            font-size: 12px; /* Reduced */
        }
        .detail-label {
            font-weight: bold;
            display: inline-block;
            min-width: 100px; /* Reduced */
        }
        .bill-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px; /* Reduced */
        }
        .bill-table th, .bill-table td {
            border: 1px solid #e5e7eb;
            padding: 7px; /* Reduced */
            text-align: left;
            font-size: 12px; /* Reduced */
        }
        .bill-table th {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        .bill-table .amount-column {
            text-align: right;
        }
        .bill-table .total-row {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 10px; /* Reduced */
            font-size: 12px; /* Reduced */
        }
        .note {
            margin-bottom: 6px; /* Reduced */
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 70px; /* Reduced */
            opacity: 0.1;
            color: #166534;
            z-index: -1;
            pointer-events: none;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 20px; /* Reduced */
        }
        .signature-box {
            border-top: 1px solid #333;
            width: 140px; /* Reduced */
            text-align: center;
            padding-top: 3px; /* Reduced */
            font-size: 11px; /* Reduced */
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Watermark -->
        <div class="watermark">OFFICIAL</div>
        
        <!-- Header with logos -->
        <div class="header">
            <div class="logo">
                <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Ministry Logo">
            </div>
            <div class="title">
                <h1 style="font-size: 13px; white-space: nowrap;">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                <h1 style="font-size: 13px;">NO 2 DR BALA MUHAMMAD ROAD KANO</h1>
                <h2>SECTIONAL TITLING DEPARTMENT</h2>
            </div>
            <div class="logo">
                <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Kano State Logo">
               
            </div>
        </div>
        
        <!-- Receipt Details -->
        <p style="font-size:12px; margin-bottom:8px;"> Dear Sir/Madame,
            I am directed to inform you that the total cost of processing of your application for Sectional Title located at {{ $application->property_house_no }} {{ $application->property_street_name}}  {{ $application->property_lga }} {{ $application->property_state }} with the following particulars;</p>
        <div class="details">
            
            <div class="detail-group">
               
                <p><span class="detail-label"> FormNo:</span>{{ $application->id }}</p> 
                  <p><span class="detail-label">Sectional Title:</span>{{ $application->main_id ?? 'N/A' }}</p> 
               
                <p><span class="detail-label">File No:</span> {{ $application->fileno }}</p>
                <p><span class="detail-label">Name of Section Owner:</span> 
                    @if(!empty($application->corporate_name))
                        {{ $application->corporate_name }}
                    @elseif(!empty($application->multiple_owners_names))
                        {{ $application->multiple_owners_names }}
                    @else
                        {{ $application->applicant_title }} {{ $application->first_name }} {{ $application->surname }}
                    @endif
                </p>
                <p><span class="detail-label">Land Use:</span> {{ $application->land_use }}</p>
                <p><span class="detail-label">Location:</span> {{ $application->property_house_no }} {{ $application->property_street_name}}  {{ $application->property_lga }} {{ $application->property_state }} </p>
                <p><span class="detail-label"> Approval:</span> {{ $application->approval_date}}</p>
            </div>
            <div class="detail-group">
                <p><span class="detail-label">Bill Reference:</span> {{ $bill->ref_id }}</p>
                <p><span class="detail-label">Date Generated:</span> {{ date('F j, Y', strtotime($bill->created_at)) }}</p>
                <p><span class="detail-label">Land Size:</span> {{ number_format($application->property_size ?? 0) }} sqm</p>
                <p><span class="detail-label">Number of Units:</span> {{ $application->NoOfUnits ?? 0 }}</p>
            </div>
        </div>
        
        <!-- Bill Table -->
        <table class="bill-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="amount-column">Amount (₦)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <p>Property Value</p>
                        <small style="color: #6b7280;">At {{ $bill->betterment_rate }}% betterment rate</small>
                    </td>
                    <td class="amount-column">₦{{ number_format($bill->property_value, 2) }}</td>
                </tr>
                <tr>
                    <td>Betterment Charge</td>
                    <td class="amount-column">₦{{ number_format($bill->Betterment_Charges, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total Amount</td>
                    <td class="amount-column">₦{{ number_format($bill->Betterment_Charges, 2) }}</td>
                </tr>
            </tbody>
        </table>
        
        <!-- Footer -->
        <div class="footer">
            <p class="note"><strong>Amount in words:</strong> 
                {{ ucfirst((new NumberFormatter('en', NumberFormatter::SPELLOUT))->format($bill->Betterment_Charges)) }} Naira Only
            </p>
            <p class="note">
                You are hereby advised to settle this bill promptly in order to accelerate the processing of your Certificate of Occupancy. Payments can be made at the One-Stop-Shop 
                and all KANGISdesignated banks. Ensure that you obtain a duly acknowledged Revenue Receipt issued at the KANGIS Office after concluding payment of the 
                billed amount.
                 Thank you.
            </p>
 
            <!-- Signatures -->
            <div class="signatures">
                <div class="signature-box">
                    <p> Date & Signature of Revenue Officer
                    </p>
                </div>
                <div class="signature-box">
                    <p> Date & Signature of Revenue Officer
                    </p>
                </div>
            </div>
            
            
        </div>
    </div>
    
    <script>
        // Auto print when loaded
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
