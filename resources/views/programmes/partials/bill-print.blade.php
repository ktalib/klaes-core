<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @if($type === 'initial')
            Initial Application Bill
        @elseif($type === 'betterment')
            Betterment Bill
        @else
            Final Bill Balance
        @endif
        - {{ $primaryFileNo ?? $unitFileNo ?? 'N/A' }}
    </title>
    <style>
    body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 15px;
            color: #333;
            line-height: 1.4;
            font-size: 13px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            position: relative;
        }
        .logo-left {
            width: 45px;
            height: 45px;
            object-fit: contain;
        }
        .logo-right {
            width: 45px;
            height: 45px;
            object-fit: contain;
        }
        .ministry-center {
            flex: 1;
            text-align: center;
            padding: 0 15px;
        }
        .ministry-title {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.1;
        }
        .ministry-subtitle {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 0;
        }
        .bill-title {
            font-size: 16px;
            font-weight: bold;
            margin: 8px 0 5px 0;
        }
        .bill-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .info-section {
            width: 48%;
        }
        .info-section h3 {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 6px;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 12px;
        }
        .info-label {
            font-weight: 500;
            color: #6b7280;
        }
        .info-value {
            font-weight: 600;
        }
        .bill-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .bill-table th,
        .bill-table td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        .bill-table th {
            background-color: #f9fafb;
            font-weight: bold;
            color: #374151;
        }
        .bill-table .amount {
            text-align: right;
            font-weight: 600;
        }
        .total-row {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 13px;
        }
        .status-section {
            margin-bottom: 15px;
        }
        .status-section h3 {
            font-size: 13px;
            margin-bottom: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 11px;
        }
        .status-paid {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-overdue {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
        @media print {
            @page {
                margin: 0.5in;
                size: A4;
            }
            body {
                margin: 0;
                padding: 10px;
                font-size: 12px;
                line-height: 1.3;
            }
            .header {
                margin-bottom: 10px;
                padding-bottom: 8px;
            }
            .bill-info {
                margin-bottom: 10px;
            }
            .bill-table {
                margin-bottom: 10px;
            }
            .status-section {
                margin-bottom: 10px;
            }
            .footer {
                margin-top: 15px;
                padding-top: 8px;
            }
            .no-print {
                display: none;
            }
        }
</style>
</head>
<body>
    @php
        $primaryFileNo = data_get($bill, 'primary_fileno');
        $unitFileNo = data_get($bill, 'unit_fileno');
        $billId = data_get($bill, 'id');
        $primaryStreet = data_get($bill, 'primary_property_street');
        $unitPropertyLocation = data_get($bill, 'unit_property_location');
        $primaryLga = data_get($bill, 'primary_property_lga');
        $createdAt = data_get($bill, 'created_at');
        $primaryCorporateName = data_get($bill, 'primary_corporate_name');
        $unitCorporateName = data_get($bill, 'unit_corporate_name');
        $primaryFirstName = data_get($bill, 'primary_first_name');
        $unitFirstName = data_get($bill, 'unit_first_name');
        $primarySurname = data_get($bill, 'primary_surname');
        $unitSurname = data_get($bill, 'unit_surname');
        $schemeApplicationFee = data_get($bill, 'Scheme_Application_Fee');
        $sitePlanFee = data_get($bill, 'Site_Plan_Fee');
        $unitApplicationFees = data_get($bill, 'Unit_Application_Fees');
        $bettermentCharges = data_get($bill, 'Betterment_Charges');
        $landUseCharge = data_get($bill, 'Land_Use_Charge');
        $processingFeeInitial = data_get($bill, 'Processing_Fee');
        $processingFee = data_get($bill, 'processing_fee');
        $surveyFee = data_get($bill, 'survey_fee');
        $assignmentFee = data_get($bill, 'assignment_fee');
        $billBalance = data_get($bill, 'bill_balance');
        $totalAmount = data_get($bill, 'total_amount');
        $billStatus = data_get($bill, 'bill_status');
        $paymentStatus = data_get($bill, 'Payment_Status');
        
        // Granular receipts
        $appReceipt = data_get($bill, 'application_fee_receipt_number') ?? data_get($bill, 'application_fee_receipt_no');
        $procReceipt = data_get($bill, 'processing_fee_receipt_number') ?? data_get($bill, 'processing_fee_receipt_no');
        $siteReceipt = data_get($bill, 'site_plan_fee_receipt_number') ?? data_get($bill, 'survey_fee_receipt_no');
    @endphp
    <!-- Header -->
    <div class="header">
        <!-- Header Top with Logos and Ministry Title -->
        <div class="header-top">
            <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Kano State Logo" class="logo-left" onerror="this.style.display='none'">
            
            <div class="ministry-center">
                <div class="ministry-title">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</div>
                <div class="ministry-subtitle">Sectional Title Unit</div>
            </div>
            
            <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Ministry Logo" class="logo-right" onerror="this.style.display='none'">
        </div>
        <!-- Bill Title -->
        <div class="bill-title">
            @if($type === 'initial')
                INITIAL APPLICATION BILL
            @elseif($type === 'betterment')
                BETTERMENT BILL
            @else
                FINAL BILL BALANCE
            @endif
        </div>
        <div>Bill ID: #{{ $billId ?? 'N/A' }}</div>
    </div>

    <!-- Bill Information -->
    <div class="bill-info">
        <div class="info-section">
            <h3>Application Details</h3>
            <div class="info-row">
                <span class="info-label">File Number:</span>
                <span class="info-value">{{ $primaryFileNo ?? $unitFileNo ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Application Type:</span>
                <span class="info-value">{{ $primaryFileNo ? 'Primary Application' : 'Unit Application' }}</span>
            </div>
            @if($primaryStreet || $unitPropertyLocation)
            <div class="info-row">
                <span class="info-label">Property Location:</span>
                <span class="info-value">{{ $primaryStreet ?? $unitPropertyLocation }}</span>
            </div>
            @endif
            @if($primaryLga)
            <div class="info-row">
                <span class="info-label">LGA:</span>
                <span class="info-value">{{ $primaryLga }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-label">Date Generated:</span>
                <span class="info-value">{{ $createdAt ? \Carbon\Carbon::parse($createdAt)->format('M d, Y') : 'N/A' }}</span>
            </div>
        </div>

        <div class="info-section">
            <h3>Applicant Information</h3>
            @php
                $ownerName = '';
                if ($primaryCorporateName) {
                    $ownerName = $primaryCorporateName;
                } elseif ($unitCorporateName) {
                    $ownerName = $unitCorporateName;
                } else {
                    $firstName = $primaryFirstName ?? $unitFirstName ?? '';
                    $surname = $primarySurname ?? $unitSurname ?? '';
                    $ownerName = trim($firstName . ' ' . $surname);
                }
            @endphp
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span class="info-value">{{ $ownerName ?: 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Type:</span>
                <span class="info-value">{{ ($primaryCorporateName || $unitCorporateName) ? 'Corporate' : 'Individual' }}</span>
            </div>
        </div>
    </div>

    <!-- Bill Breakdown Table -->
    <table class="bill-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Amount (₦)</th>
            </tr>
        </thead>
        <tbody>
            @if($type === 'initial')
                @if($schemeApplicationFee)
                <tr>
                    <td>Scheme Application Fee</td>
                    <td class="amount">
                        {{ number_format(floatval($schemeApplicationFee), 2) }}
                        {!! $appReceipt ? '<br><small style="color: #666; font-weight: normal;">Rec: '.$appReceipt.'</small>' : '' !!}
                    </td>
                </tr>
                @endif
                @if($sitePlanFee)
                <tr>
                    <td>Site Plan Fee</td>
                    <td class="amount">
                        {{ number_format(floatval($sitePlanFee), 2) }}
                        {!! $siteReceipt ? '<br><small style="color: #666; font-weight: normal;">Rec: '.$siteReceipt.'</small>' : '' !!}
                    </td>
                </tr>
                @endif
                @if($unitApplicationFees)
                <tr>
                    <td>Application Fee</td>
                    <td class="amount">
                        {{ number_format(floatval($unitApplicationFees), 2) }}
                        {!! $appReceipt ? '<br><small style="color: #666; font-weight: normal;">Rec: '.$appReceipt.'</small>' : '' !!}
                    </td>
                </tr>
                @endif
                @if($processingFeeInitial)
                <tr>
                    <td>Processing Fee</td>
                    <td class="amount">
                        {{ number_format(floatval($processingFeeInitial), 2) }}
                        {!! $procReceipt ? '<br><small style="color: #666; font-weight: normal;">Rec: '.$procReceipt.'</small>' : '' !!}
                    </td>
                </tr>
                @endif
            @elseif($type === 'betterment')
                @if($bettermentCharges)
                <tr>
                    <td>Betterment Charges</td>
                    <td class="amount">{{ number_format(floatval($bettermentCharges), 2) }}</td>
                </tr>
                @endif
                @if($landUseCharge)
                <tr>
                    <td>Land Use Charge</td>
                    <td class="amount">{{ number_format(floatval($landUseCharge), 2) }}</td>
                </tr>
                @endif
                @if($processingFeeInitial)
                <tr>
                    <td>Processing Fee</td>
                    <td class="amount">{{ number_format(floatval($processingFeeInitial), 2) }}</td>
                </tr>
                @endif
            @else
                @if($processingFee)
                <tr>
                    <td>Processing Fee</td>
                    <td class="amount">{{ number_format(floatval($processingFee), 2) }}</td>
                </tr>
                @endif
                @if($surveyFee)
                <tr>
                    <td>Survey Fee</td>
                    <td class="amount">{{ number_format(floatval($surveyFee), 2) }}</td>
                </tr>
                @endif
                @if($assignmentFee)
                <tr>
                    <td>Assignment Fee</td>
                    <td class="amount">{{ number_format(floatval($assignmentFee), 2) }}</td>
                </tr>
                @endif
                @if($billBalance)
                <tr>
                    <td>Bill Balance</td>
                    <td class="amount">{{ number_format(floatval($billBalance), 2) }}</td>
                </tr>
                @endif
            @endif
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td>TOTAL AMOUNT</td>
                <td class="amount">
                    @php
                        $total = 0;
                        if ($type === 'initial') {
                            $total = floatval($schemeApplicationFee ?? 0) + 
                                   floatval($sitePlanFee ?? 0) + 
                                   floatval($unitApplicationFees ?? 0) +
                                   floatval($processingFeeInitial ?? 0);
                        } elseif ($type === 'betterment') {
                            $total = floatval($bettermentCharges ?? 0) + 
                                   floatval($landUseCharge ?? 0) + 
                                   floatval($processingFeeInitial ?? 0);
                        } else {
                            $total = floatval($totalAmount ?? 0);
                            if ($total == 0) {
                                $total = floatval($processingFee ?? 0) + 
                                       floatval($surveyFee ?? 0) + 
                                       floatval($assignmentFee ?? 0) + 
                                       floatval($billBalance ?? 0);
                            }
                        }
                    @endphp
                    ₦{{ number_format($total, 2) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Payment Status -->
    <div class="status-section">
        <h3>Payment Status</h3>
        @php
            $status = $type === 'balance' ? ($billStatus ?? 'Unknown') : ($paymentStatus ?? 'Unknown');
            $statusClass = '';
            $statusText = '';
            
            if ($type === 'balance') {
                switch($status) {
                    case 'paid':
                        $statusClass = 'status-paid';
                        $statusText = 'Paid';
                        break;
                    case 'generated':
                    case 'sent':
                        $statusClass = 'status-pending';
                        $statusText = ucfirst($status);
                        break;
                    default:
                        $statusClass = 'status-overdue';
                        $statusText = $status;
                }
            } else {
                switch($status) {
                    case 'Complete':
                        $statusClass = 'status-paid';
                        $statusText = 'Paid';
                        break;
                    case 'Incomplete':
                        $statusClass = 'status-pending';
                        $statusText = 'Pending';
                        break;
                    default:
                        $statusClass = 'status-overdue';
                        $statusText = $status;
                }
            }
        @endphp
        <span class="status-badge {{ $statusClass }}">{{ $statusText }}</span>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This is a computer-generated bill. For inquiries, please contact the Kano State Ministry of Land and Physical Planning.</p>
        <p>Generated on {{ now()->format('M d, Y \a\t H:i A') }}</p>
    </div>

    <script>
        // Auto-print when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
