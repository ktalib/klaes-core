<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sub-Application Slip</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.2;
            color: #000;
            background: white;
        }
        
        .print-container {
            width: 100%;
            max-width: 100%;
            padding: 8mm;
        }
        
        .header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
        }
        
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .header h2 {
            font-size: 14px;
            font-weight: bold;
        }
        
        .app-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ccc;
            font-size: 11px;
        }
        
        .two-column {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .column {
            flex: 1;
        }
        
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
            border-bottom: 1px solid #eee;
            padding-bottom: 2px;
        }
        
        .info-table {
            width: 100%;
            margin-bottom: 8px;
        }
        
        .info-table td {
            padding: 1px 0;
            vertical-align: top;
            font-size: 9px;
        }
        
        .info-table td:first-child {
            font-weight: bold;
            width: 40%;
        }
        
        .property-section {
            margin-bottom: 10px;
        }
        
        .property-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 8px;
        }
        
        .payment-section {
            margin-bottom: 10px;
        }
        
        .documents-section {
            margin-bottom: 12px;
        }
        
        .documents-list {
            font-size: 9px;
            line-height: 1.3;
        }
        
        .document-item {
            margin-bottom: 2px;
        }
        
        .signature-section {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
            text-align: center;
        }
        
        .signature-line {
            display: inline-block;
            width: 200px;
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
        }
        
        .signature-label {
            font-size: 9px;
        }
        
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .print-container {
                page-break-inside: avoid;
            }
        }
        
        @media screen {
            body {
                background: #f5f5f5;
                padding: 20px;
            }
            
            .print-container {
                background: white;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                margin: 0 auto;
                max-width: 297mm;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- Header -->
        <div class="header">
            <h1>MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
            <h2>
                @if($isSUA ?? false)
                    STANDALONE UNIT APPLICATION (SUA) SLIP
                @else
                    SECTIONAL TITLING - UNIT APPLICATION SLIP
                @endif
            </h2>
        </div>
        
        <!-- Application Info -->
        <div class="app-info">
            <span><strong>Application ID:</strong> {{ $applicationId ?? 'SUB-' . rand(10000, 99999) }}</span>
            <span><strong>Date:</strong> {{ date('d/m/Y') }}</span>
            <span><strong>Land Use:</strong> {{ $landUse ?? 'Residential' }}</span>
        </div>
        
        <!-- Two Column Layout -->
        <div class="two-column">
            <div class="column">
                <div class="section-title">Applicant Information</div>
                <table class="info-table">
                    <tr>
                        <td>Type:</td>
                        <td>{{ $applicantType ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Name:</td>
                        <td>{{ $applicantName ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Email:</td>
                        <td>{{ $applicantEmail ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Phone:</td>
                        <td>{{ $applicantPhone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Address:</td>
                        <td>{{ $applicantAddress ?? '-' }}</td>
                    </tr>
                </table>
            </div>
            
            <div class="column">
                <div class="section-title">Unit Details</div>
                <table class="info-table">
                    <tr>
                        <td>Unit Type:</td>
                        <td>{{ $unitType ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Unit Number:</td>
                        <td>{{ $units ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Block Number:</td>
                        <td>{{ $blocks ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Section (Floor):</td>
                        <td>{{ $sections ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Unit Size:</td>
                        <td>{{ $unitSize ?? '-' }}</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Property Information -->
        <div class="property-section">
            <div class="section-title">File Information</div>
            <div class="property-grid">
                <table class="info-table">
                    @if($isSUA ?? false)
                        <tr>
                            <td>Primary FileNo:</td>
                            <td>{{ $primaryFileNo ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>SUA FileNo:</td>
                            <td>{{ $fileNumber ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>MLPP FileNo:</td>
                            <td>{{ $mlsFileNo ?? '-' }}</td>
                        </tr>
                    @else
                        <tr>
                            <td>STFileNo:</td>
                            <td>{{ $fileNumber ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>Main Application ID:</td>
                            <td>{{ $mainApplicationId ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>Scheme No:</td>
                            <td>{{ $schemeNo ?? '-' }}</td>
                        </tr>
                    @endif
                </table>
                <table class="info-table">
                    @if($isSUA ?? false)
                        <tr>
                            <td>Property Location:</td>
                            <td>{{ $propertyLocation ?? '-' }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="payment-section">
            <div class="section-title">Payment Information</div>
            <div class="property-grid">
                <table class="info-table">
                    <tr>
                        <td>Application Fee:</td>
                        <td>{{ $applicationFee ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Processing Fee:</td>
                        <td>{{ $processingFee ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Survey Fee:</td>
                        <td>{{ $surveyFee ?? '-' }}</td>
                    </tr>
                </table>
                <table class="info-table">
                    <tr>
                        <td><strong>Total:</strong></td>
                        <td><strong>{{ $totalFee ?? '-' }}</strong></td>
                    </tr>
                    <tr>
                        <td>Receipt No:</td>
                        <td>{{ $receiptNumber ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td>Payment Date:</td>
                        <td>{{ $paymentDate ?? '-' }}</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Documents -->
        <div class="documents-section">
            <div class="section-title">Uploaded Documents</div>
            <div class="documents-list">
                @if(isset($documents) && is_array($documents))
                    @foreach($documents as $document)
                        <div class="document-item">
                            <span style="display: inline-block; width: 20px; text-align: center;">✓</span>
                            {{ $document }}
                        </div>
                    @endforeach
                @else
                    <div class="document-item">No documents listed</div>
                @endif
            </div>
        </div>
        
        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-line"></div>
            <div class="signature-label">Receiving Officer's Signature & Date</div>
        </div>
    </div>
    
    <script>
        // Auto-print when page loads
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });
        
        // Close window after printing
        window.addEventListener('afterprint', function() {
            setTimeout(function() {
                window.close();
            }, 1000);
        });
    </script>
</body>
</html>