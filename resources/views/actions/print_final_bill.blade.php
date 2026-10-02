<!-- filepath: c:\wamp64\www\gisedms\resources\views\sub_actions\print_final_bill.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sectional Title Final Bill</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #fff;
        }
        .bill-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 30px;
            position: relative;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .logo {
            width: 80px;
            height: 80px;
        }
        .title {
            text-align: center;
            flex-grow: 1;
        }
        .title h1 {
            font-size: 18px;
            color: #166534;
            margin: 0;
        }
        .title h2 {
            font-size: 16px;
            color: #dc2626;
            margin: 5px 0 0;
        }
        .date {
            text-align: right;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .bill-ref {
            text-align: right;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .bill-ref span {
            color: #166534;
            font-weight: bold;
        }
        .intro {
            margin-bottom: 20px;
            font-size: 12px;
        }
        .property-details {
            margin-bottom: 20px;
            font-size: 12px;
        }
        .property-details p {
            margin: 5px 0;
        }
        .property-details .label {
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 12px;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f9fafb;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
        }
        .amount-words {
            font-style: italic;
            margin-bottom: 10px;
        }
        .note {
            margin-top: 10px;
            font-size: 12px;
        }
        @media print {
            body {
                padding: 0;
                background-color: #fff;
            }
            .bill-container {
                border: none;
                padding: 20px;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="bill-container">
        <!-- Header with two logos and title -->
        <div class="header">
            <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Kano State Logo" class="logo">
            <div class="title">
                <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                <h2>SECTIONAL TITLE FINAL BILL</h2>
            </div>
            <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Ministry Logo" class="logo">
        </div>
        
        <!-- Date and Reference ID -->
        <div class="date">
            {{ $current_date }}
        </div>
        <div class="bill-ref">
            <strong>Bill Reference ID:</strong> <span>ST-BILL-{{ $application->id }}-{{ date('Ymd') }}-{{ rand(1000, 9999) }}</span>
        </div>
        
        <!-- Introduction -->
        <div class="intro">
            <p>Dear Sir/Madam,</p>
            <p>
                I am directed to inform you that the total cost of processing of your application for sectional 
                title located at <strong>{{ $application->property_house_no ?? '' }} {{ $application->property_plot_no ?? '' }}, {{ $application->property_street_name ?? '' }}, {{ $application->property_district ?? '' }}, {{ $application->property_lga ?? '' }}</strong> with the following particulars.
            </p>
        </div>
        
        <!-- Property Details -->
        <div class="property-details">
            <p><span class="label">Form No:</span> {{$application->id}}</p>
            <p><span class="label">File No:</span> {{$application->fileno}}</p>
            <p><span class="label">Name of Section Owner:</span> {{ $application->applicant_title}} {{ $application->surname}} {{ $application->first_name}}</p>
            <p><span class="label">Plot Size:</span> {{ $application->plot_size}}</p>
            <p><span class="label">Land Use:</span> {{ $application->land_use}}</p>
            <p><span class="label">Location:</span> {{ $application->property_house_no ?? '' }} {{ $application->property_plot_no ?? '' }}, {{ $application->property_street_name ?? '' }}, {{ $application->property_district ?? '' }}, {{ $application->property_lga ?? '' }}</p>
            <p><span class="label">Approval Date:</span> {{ $application->approval_date ?? 'Pending' }}</p>
        </div>
        
        <!-- Fee Table -->
        <table>
            <thead>
                <tr>
                    <th width="40%">Land Use</th>
                    <th width="30%">Survey / Processing Fees</th>
                    <th width="30%">Dev. Charges ₦</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        @php
                            $landUse = strtolower($application->land_use ?? 'residential');
                        @endphp
                        
                        @if($landUse == 'residential')
                            <p><strong>a.Residential Fees</strong></p>
                            <p style="padding-left: 15px;">i.Processing Fee</p>
                            <p><strong>b.Survey Fees</strong></p>
                            <p style="padding-left: 15px;">i.Block of Flats</p>
                            <p style="padding-left: 15px;">ii.Apartment</p>
                            <p><strong>c.Assignment Fees</strong></p>
                            <p><strong>d.Bill Balance</strong></p>
                        @else
                            <p><strong>e.Commercial Fees</strong></p>
                            <p style="padding-left: 15px;">i.Processing Fee</p>
                            <p style="padding-left: 15px;">ii.Survey Fees</p>
                            <p style="padding-left: 15px;">iii.Assignment Fees</p>
                            <p style="padding-left: 15px;">iv.Bill Balance</p>
                        @endif
                    </td>
                    <td>
                        <p>N {{ number_format($bill->processing_fee, 2) }}</p>
                        <p>N {{ number_format($bill->survey_fee, 2) }}</p>
                        <p>N {{ number_format($bill->assignment_fee, 2) }}</p>
                        <p>N {{ number_format($bill->bill_balance, 2) }}</p>
                    </td>
                    <td>
                        <p>N {{ number_format($bill->dev_charges, 2) }}</p>
                    </td>
                </tr>
                <tr>
                    <td>One year Ground Rent</td>
                    <td>N {{ number_format($bill->recertification_fee, 2) }}</td>
                    <td>N __________________</td>
                </tr>
                <tr>
                    <td colspan="3">
                        <p><strong>TOTAL: ₦ {{ number_format($bill->total_amount, 2) }}</strong> ({{ $total_in_words }})</p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <!-- Footer Text -->
        <div class="footer">
            <p>
                You are hereby directed to settle this bill promptly in order to accelerate the processing of your 
                application.
            </p>
            <p class="note">
                <strong>Note:</strong> Documentary Payments can be made at the Checkout-Point and KANGIS 
                Cashier's Office.
            </p>
            <p class="note">
                <strong>Note:</strong> Ensure that you obtain a duly acknowledged Revenue Receipt issued at the KANGIS 
                Office.
            </p>
            <p>Thank you.</p>
        </div>
        
        <!-- Print Button (not displayed when printing) -->
        <div class="no-print" style="text-align: center; margin-top: 30px;">
            <button onclick="window.print()" style="padding: 8px 16px; background-color: #4b5563; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Print Bill
            </button>
            <button onclick="window.close()" style="padding: 8px 16px; margin-left: 10px; background-color: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Close
            </button>
        </div>
    </div>
    
    <script>
        // Auto-print when the page loads
        window.onload = function() {
            // Uncomment the line below to auto-print when the page loads
            // window.print();
        }
    </script>
</body>
</html>