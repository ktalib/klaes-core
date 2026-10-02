<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('page-title') - {{ config('app.name', 'KANO STATE LAND REGISTRY') }}</title>
    
    <!-- Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        /* Optimized print styles for single A4 landscape page */
        @media print {
            @page {
                size: A4 landscape;
                margin: 0.25in;
            }
            
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-size: 10px !important;
                line-height: 1.2 !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                height: 100% !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .print-container {
                max-width: none !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                height: 100vh !important;
                max-height: 7.8in !important;
                display: flex !important;
                flex-direction: column !important;
                box-sizing: border-box !important;
                background: white !important;
                box-shadow: none !important;
            }
            
            /* Make content use full width */
            .max-w-5xl {
                max-width: none !important;
            }
            
            .mx-auto {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            
            /* Header section - compact */
            .header-section {
                margin-bottom: 0.75rem !important;
                padding-bottom: 0.5rem !important;
                flex-shrink: 0 !important;
                width: 100% !important;
            }
            
            .print-header {
                font-size: 18px !important;
                margin-bottom: 0.25rem !important;
            }
            
            .print-subheader {
                font-size: 14px !important;
            }
            
            /* File details section - compact and full width */
            .file-details-section {
                margin-bottom: 1rem !important;
                flex-shrink: 0 !important;
                width: 100% !important;
            }
            
            .file-details-table th,
            .file-details-table td {
                padding: 0.5rem 0.75rem !important;
                font-size: 10px !important;
                line-height: 1.2 !important;
            }
            
            /* Signing section - optimized to fit remaining space and full width */
            .signing-section {
                flex-grow: 1 !important;
                margin-bottom: 0.75rem !important;
                display: flex !important;
                flex-direction: column !important;
                min-height: 0 !important;
                width: 100% !important;
            }
            
            .signing-section h2 {
                margin-bottom: 0.5rem !important;
                font-size: 14px !important;
                flex-shrink: 0 !important;
            }
            
            .signing-table {
                flex-grow: 1 !important;
                height: 100% !important;
                min-height: 120px !important;
                max-height: 180px !important;
                width: 100% !important;
            }
            
            .signing-table th {
                padding: 0.5rem 0.25rem !important;
                font-size: 10px !important;
                font-weight: bold !important;
            }
            
            .signing-table td {
                padding: 1.25rem 0.25rem !important;
                font-size: 8px !important;
                height: auto !important;
                min-height: 40px !important;
                vertical-align: top !important;
            }
            
            /* QR code section - smaller */
            .qr-section {
                padding: 0.75rem !important;
            }
            
            .qr-code {
                width: 4rem !important;
                height: 4rem !important;
            }
            
            .qr-section h3 {
                font-size: 12px !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Government seal - compact */
            .govt-seal-container {
                width: 3rem !important;
                height: 3rem !important;
            }
            
            .government-seal {
                width: 2.5rem !important;
                height: 2.5rem !important;
            }
            
            .government-seal .text-center div {
                font-size: 7px !important;
                line-height: 1 !important;
            }
            
            /* Tracking ID section - compact */
            .tracking-section {
                padding: 0.75rem !important;
            }
            
            .tracking-section p {
                font-size: 10px !important;
                margin-bottom: 0.25rem !important;
            }
            
            /* Authorization section - compact and full width */
            .auth-section {
                padding: 0.75rem !important;
                margin-bottom: 0.5rem !important;
                flex-shrink: 0 !important;
                width: 100% !important;
            }
            
            .auth-section p {
                font-size: 10px !important;
            }
            
            /* Footer section - minimal and full width */
            .footer-section {
                margin-top: 0.5rem !important;
                padding-top: 0.5rem !important;
                flex-shrink: 0 !important;
                width: 100% !important;
            }
            
            .footer-section p {
                font-size: 11px !important;
                margin-bottom: 0.25rem !important;
            }
            
            /* Section headers - smaller */
            h2 {
                font-size: 13px !important;
                margin-bottom: 0.5rem !important;
                font-weight: bold !important;
            }
            
            h3 {
                font-size: 11px !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Form inputs - compact */
            .form-input {
                min-width: 4rem !important;
                border-bottom: 1px solid #374151 !important;
                font-size: 10px !important;
                padding: 0.125rem !important;
            }
            
            /* Remove rounded corners and shadows for print */
            .rounded-lg {
                border-radius: 0 !important;
            }
            
            .shadow-xl, .shadow-md, .shadow-lg {
                box-shadow: none !important;
            }
            
            /* Ensure borders are visible */
            .border-2 {
                border-width: 1px !important;
            }
            
            .border {
                border-width: 1px !important;
            }
            
            /* Force table to use full width */
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            
            /* Flex adjustments for print */
            .flex {
                display: flex !important;
            }
            
            .justify-between {
                justify-content: space-between !important;
            }
            
            .items-center {
                align-items: center !important;
            }
            
            .gap-8 {
                gap: 1.5rem !important;
            }
            
            /* Reduce all margins globally */
            .mb-10, .mb-8, .mb-6 {
                margin-bottom: 0.75rem !important;
            }
            
            .mt-12 {
                margin-top: 0.5rem !important;
            }
            
            .pt-6 {
                padding-top: 0.5rem !important;
            }
            
            .pb-6 {
                padding-bottom: 0.5rem !important;
            }
            
            /* Force single page */
            .print-container, .print-container * {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            
            /* Prevent overflow */
            * {
                overflow: visible !important;
            }
            
            /* Remove all centering classes */
            .text-center {
                text-align: left !important;
            }
            
            /* Only keep text-center for specific elements that should be centered */
            .footer-section .text-center,
            .signing-section h2.text-center,
            .qr-section .text-center {
                text-align: center !important;
            }
        }
        
        @media screen {
            body {
                background-color: #f5f5f5;
                padding: 20px;
            }
            
            .print-container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                min-height: 800px;
            }
        }
        
        /* Custom styles for better visual hierarchy */
        .government-seal {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
    </style>
    
    @yield('additional-styles')
</head>
<body class="bg-gray-50 p-4 font-serif">
    <!-- Added print button with improved styling -->
    <div class="no-print mb-6 text-center">
        <button 
            onclick="window.print()" 
            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition-colors duration-200 flex items-center gap-2 mx-auto"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print Document
        </button>
    </div>

    <div class="print-container">
        @yield('content')
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-print when page loads
            setTimeout(function() {
                window.print();
            }, 500);
            
            // Optional: Close window after printing
            window.addEventListener('afterprint', function() {
                // Uncomment below to close window after printing
                // window.close();
            });
        });
    </script>
    
    @yield('additional-scripts')
</body>
</html>