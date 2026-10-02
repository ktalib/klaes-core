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
            @page { size: A4; margin: 1cm; }
        }
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.5;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #166534;
            padding-bottom: 15px;
        }
        .logo {
            width: 80px;
            height: 80px;
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
            font-size: 18px;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        .title h2 {
            color: #4adc26;
            font-size: 16px;
            font-weight: bold;
            margin: 5px 0 0 0;
        }
        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 20px;
        }
        .detail-group p {
            margin: 5px 0;
            font-size: 14px;
        }
        .detail-label {
            font-weight: bold;
            display: inline-block;
            min-width: 120px;
        }
        .bill-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .bill-table th, .bill-table td {
            border: 1px solid #e5e7eb;
            padding: 12px;
            text-align: left;
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
            padding-top: 20px;
            font-size: 14px;
        }
        .note {
            margin-bottom: 10px;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            opacity: 0.1;
            color: #166534;
            z-index: -1;
            pointer-events: none;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        .signature-box {
            border-top: 1px solid #333;
            width: 200px;
            text-align: center;
            padding-top: 5px;
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
                <h1 style="font-size: 16px; white-space: nowrap;">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                <h1>NO 2 DR BALA MUHAMMAD ROAD KANO</h1>
                <h2>SECTIONAL TITLING DEPARTMENT</h2>
            </div>
            <div class="logo">
                <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Kano State Logo">
               
            </div>
        </div>
        
        <!-- Receipt Details -->
        <p> Dear Sir/Madame,
            I am directed to inform you that the total cost of processing of your application for Sectional Title located at {{ $application->property_house_no }} {{ $application->property_street_name}}  {{ $application->property_lga }} {{ $application->property_state }} with the following particulars;</p>
        <div class="details">
            
            <div class="detail-group">
               
                <p><span class="detail-label"> FormNo:</span>{{ $application->id }}</p> 
                  <p><span class="detail-label">Sectional Title:</span>{{ $application->applicationID }}</p> 
               
                <p><span class="detail-label">File No:</span> {{ $application->fileno }}</p>
                <p><span class="detail-label"> Name of Section Owner:</span> {{ $application->applicant_title }} {{ $application->surname }} {{ $application->first_name }}</p>
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
