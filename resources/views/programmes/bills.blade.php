@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Bills Management') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include($headerPartial ?? 'admin.header')

        <!-- Main Content -->
        <div class="p-6">
            <div class="bg-white rounded-lg shadow-sm" style="width: 60%; margin: 0 auto;">
                <!-- Page Header -->
                <div class="border-b border-gray-200 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">{{ $PageTitle }}</h1>
                            <p class="text-sm text-gray-600 mt-1">{{ $PageDescription }}</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button id="exportAllBills"
                                class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                                <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                Export All Bills
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Search Section -->
                <div class="px-6 py-6 bg-gray-50 border-b border-gray-200">
                    <div class="max-w-md mx-auto">
                        <label for="filenoSelect" class="block text-sm font-medium text-gray-700 mb-3 text-center">
                            <i data-lucide="search" class="w-4 h-4 inline mr-2"></i>
                            @if(request()->get('source') === 'pp_conversion')
                                Search by Conversion File Number
                            @elseif(request()->get('url') === 'physical_planning')
                                Search by Primary File Number
                            @else
                                Search by File Number
                            @endif
                        </label>
                        @if(request()->get('source') === 'pp_conversion')
                            <p class="text-xs text-blue-600 text-center mb-3">
                                <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                                Showing conversion application file numbers from JSI reports
                            </p>
                        @elseif(request()->get('url') === 'physical_planning')
                            <p class="text-xs text-blue-600 text-center mb-3">
                                <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                                Showing primary file numbers only (Physical Plan view)
                            </p>
                        @endif
                        <select id="filenoSelect" class="w-full" style="width: 100%;">
                            <option value="">
                                @if(request()->get('source') === 'pp_conversion')
                                    Select a conversion file number to view bills...
                                @elseif(request()->get('url') === 'physical_planning')
                                    Select a primary file number to view bills...
                                @else
                                    Select a file number to view bills...
                                @endif
                            </option>
                            @foreach($allFiles as $file)
                                <option value="{{ $file['id'] }}" data-type="{{ $file['type'] }}"
                                    data-fileno="{{ $file['fileno'] }}" data-owner="{{ $file['owner_name'] }}"
                                    data-application-id="{{ $file['applicationID'] ?? '' }}"
                                    data-unit-type="{{ $file['unit_type'] ?? '' }}"
                                    data-existing-site-area="{{ $file['existing_site_area'] ?? '' }}"
                                    data-existing-structure-total="{{ $file['existing_structure_total'] ?? '' }}">
                                    {{ strtoupper($file['fileno']) }} - {{ strtoupper($file['owner_name']) }} ({{ ucfirst($file['type']) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Bills Content -->
                <div class="px-6 py-6">
                    <!-- Default State - No File Selected -->
                    <div id="no-selection" class="text-center py-12">
                        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i data-lucide="file-search" class="w-12 h-12 text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Select a File Number</h3>
                        <p class="text-gray-500 max-w-md mx-auto">Choose a file number from the dropdown above to view and
                            manage bills for that application.</p>
                    </div>

                    <!-- Bills Display Area -->
                    <div id="bills-container" class="hidden">
                        <!-- Application Info Header -->
                        <div id="application-info"
                            class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 mb-6 border border-blue-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900" id="app-owner-name">-</h3>
                                    <p class="text-sm text-gray-600">
                                        Application ID: <span class="font-medium" id="app-id">-</span> |
                                        File No: <span class="font-medium" id="app-fileno">-</span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800"
                                        id="app-type">
                                        <i data-lucide="file-text" class="w-4 h-4 mr-1"></i>
                                        -
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs Navigation -->
                        <div class="mb-6">
                            <nav class="flex space-x-8" aria-label="Tabs">
                                <button
                                    class="tab-button active py-3 px-1 border-b-2 font-medium text-sm focus:outline-none"
                                    data-tab="initial">
                                    <div class="flex items-center">
                                        <i data-lucide="banknote" class="w-4 h-4 mr-2"></i>
                                        INITIAL BILL
                                    </div>
                                </button>
                                <button class="tab-button py-3 px-1 border-b-2 font-medium text-sm focus:outline-none"
                                    data-tab="betterment" id="betterment-tab-btn">
                                    <div class="flex items-center">
                                        <i data-lucide="calculator" class="w-4 h-4 mr-2"></i>
                                        BETTERMENT BILL
                                    </div>
                                </button>
                                <button class="tab-button py-3 px-1 border-b-2 font-medium text-sm focus:outline-none"
                                    data-tab="balance" id="balance-tab-btn">
                                    <div class="flex items-center">
                                        <i data-lucide="file-check" class="w-4 h-4 mr-2"></i>
                                        BILL BALANCE
                                    </div>
                                </button>
                            </nav>
                        </div>

                        <!-- Tab Content -->
                        <div class="tab-content-container">
                            <!-- Initial Bills Tab -->
                            <div id="initial-tab" class="tab-content">
                                <div id="initial-bills-content">
                                    <!-- Bills will be loaded here -->
                                </div>
                            </div>

                            <!-- Betterment Bills Tab -->
                            <div id="betterment-tab" class="tab-content hidden">
                                <div id="betterment-bills-content">
                                    <!-- Bills will be loaded here -->
                                </div>
                            </div>

                            <!-- Bill Balance Tab -->
                            <div id="balance-tab" class="tab-content hidden">
                                <div id="balance-bills-content">
                                    <!-- Bills will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Footer -->
            @include($footerPartial ?? 'admin.footer')
        </div>
    </div>

    <!-- Include Select2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Include SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .tab-button {
            color: #6b7280;
            border-bottom-color: transparent;
            transition: all 0.2s;
        }

        .tab-button.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }

        .tab-button:hover:not(.disabled) {
            color: #4b5563;
        }

        .tab-button.disabled {
            color: #9ca3af;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .tab-button.disabled:hover {
            color: #9ca3af;
        }

        .tab-content {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Select2 Custom Styling */
        .select2-container--default .select2-selection--single {
            height: 48px;
            border: 2px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 30px;
            color: #374151;
            font-size: 0.875rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
        }

        .select2-dropdown {
            border: 2px solid #d1d5db;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #3b82f6;
        }

        /* Bill Card Styles */
        .bill-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
        }

        .bill-card:hover {
            box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-paid {
            background-color: #22c55e;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            padding: 0.5rem 1rem;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-overdue {
            background-color: #22c55e;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            padding: 0.5rem 1rem;
        }

        .bill-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            background-color: #eef2ff;
            color: #4338ca;
            letter-spacing: 0.05em;
        }

        /* Betterment, Balance, and Conversion Tab Button Styles */
        .betterment-tab-button,
        .balance-tab-button,
        .conv-bill-tab-button {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: background-color 0.2s;
            border: 1px solid #d1d5db;
            background-color: white;
            color: #6b7280;
        }

        /* Ensure hidden class always wins over display properties */
        .betterment-tab-button.bb-hidden,
        .balance-tab-button.bb-hidden,
        .conv-bill-tab-button.bb-hidden,
        .betterment-tab-content.bb-hidden,
        .balance-tab-content.bb-hidden,
        .conv-bill-tab-content.bb-hidden {
            display: none !important;
        }

        .betterment-tab-button.active,
        .balance-tab-button.active,
        .conv-bill-tab-button.active {
            background-color: #f3f4f6;
            font-weight: 500;
            color: #374151;
            border-color: #9ca3af;
        }

        .betterment-tab-button:hover:not(.active),
        .balance-tab-button:hover:not(.active),
        .conv-bill-tab-button:hover:not(.active) {
            background-color: #f9fafb;
        }

        /* Tab Content Styles */
        .betterment-tab-content,
        .balance-tab-content,
        .conv-bill-tab-content {
            display: none;
        }

        .betterment-tab-content.active:not(.bb-hidden),
        .balance-tab-content.active:not(.bb-hidden),
        .conv-bill-tab-content.active:not(.bb-hidden) {
            display: block;
        }

        /* Screen styles for print-area preview */
        .print-area {
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
            font-family: 'Times New Roman', serif;
            color: #333;
            line-height: 1.5;
        }

        .print-header {
            margin-bottom: 20px;
        }

        .print-logos {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 15px;
        }

        .print-logo-left {
            display: table-cell;
            width: 80px;
            vertical-align: middle;
            text-align: left;
        }

        .print-logo-right {
            display: table-cell;
            width: 80px;
            vertical-align: middle;
            text-align: right;
        }

        .print-title {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            padding: 0 15px;
        }

        .print-logo {
            width: 60px;
            height: 60px;
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
        }

        .print-title h1 {
            font-size: 14px;
            font-weight: bold;
            margin: 0;
            color: #1a5276;
            line-height: 1.3;
        }

        .print-title h2 {
            font-size: 12px;
            font-weight: bold;
            margin: 4px 0 0 0;
            color: #c0392b;
        }

        .print-content {
            font-size: 13px;
            line-height: 1.5;
        }

        .print-content p {
            margin: 6px 0;
        }

        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 13px;
        }

        .print-table th,
        .print-table td {
            border: 1px solid #999;
            padding: 8px;
            text-align: left;
        }

        .print-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .print-table .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .print-date-ref {
            text-align: right;
            margin-bottom: 15px;
        }

        .print-date-ref .ref-highlight {
            font-weight: bold;
            color: #2c3e50;
        }

        .print-footer {
            margin-top: 20px;
            font-size: 12px;
        }

        /* Enhanced Print Styles */
        @media print {
            @page {
                margin: 0.5in;
                size: A4;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                font-family: 'Times New Roman', serif !important;
                font-size: 12pt !important;
                line-height: 1.4 !important;
                color: black !important;
            }

            body * {
                visibility: hidden !important;
            }

            .print-area,
            .print-area * {
                visibility: visible !important;
            }

            .print-area {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                background: white !important;
                padding: 20px !important;
                margin: 0 !important;
                font-family: 'Times New Roman', serif !important;
                color: black !important;
            }

            .print-header {
                width: 100% !important;
                margin-bottom: 30px !important;
                page-break-inside: avoid !important;
            }

            .print-logos {
                display: table !important;
                width: 100% !important;
                margin-bottom: 20px !important;
                border-collapse: separate !important;
                table-layout: fixed !important;
            }

            .print-logo-left {
                display: table-cell !important;
                width: 100px !important;
                vertical-align: middle !important;
                text-align: left !important;
            }

            .print-logo-right {
                display: table-cell !important;
                width: 100px !important;
                vertical-align: middle !important;
                text-align: right !important;
            }

            .print-title {
                display: table-cell !important;
                vertical-align: middle !important;
                text-align: center !important;
                padding: 0 20px !important;
            }

            .print-logo {
                width: 80px !important;
                height: 80px !important;
                max-width: 80px !important;
                max-height: 80px !important;
                display: block !important;
            }

            .print-title h1 {
                font-size: 16pt !important;
                font-weight: bold !important;
                color: black !important;
                margin: 0 !important;
                line-height: 1.2 !important;
                text-align: center !important;
            }

            .print-title h2 {
                font-size: 14pt !important;
                font-weight: bold !important;
                color: black !important;
                margin: 5px 0 0 0 !important;
                text-align: center !important;
            }

            .print-content {
                font-size: 12pt !important;
                line-height: 1.4 !important;
                color: black !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .print-content p {
                margin: 8px 0 !important;
                color: black !important;
            }

            .print-content strong,
            .print-content b {
                color: black !important;
                font-weight: bold !important;
            }

            .print-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin: 20px 0 !important;
                font-size: 11pt !important;
                color: black !important;
            }

            .print-table th,
            .print-table td {
                border: 1px solid black !important;
                padding: 8px !important;
                text-align: left !important;
                color: black !important;
                background: white !important;
            }

            .print-table th {
                background-color: #f5f5f5 !important;
                font-weight: bold !important;
                color: black !important;
            }

            .print-table .total-row {
                background-color: #f0f0f0 !important;
                font-weight: bold !important;
                color: black !important;
            }

            .print-table .total-row td {
                background-color: #f0f0f0 !important;
                color: black !important;
                font-weight: bold !important;
            }

            .print-footer {
                margin-top: 30px !important;
                font-size: 11pt !important;
                color: black !important;
            }

            .print-footer p {
                color: black !important;
                margin: 8px 0 !important;
            }

            .no-print {
                display: none !important;
                visibility: hidden !important;
            }

            /* Date and reference styling */
            .print-date-ref {
                text-align: right !important;
                margin-bottom: 20px !important;
                color: black !important;
            }

            .print-date-ref p {
                color: black !important;
                margin: 4px 0 !important;
            }

            .print-date-ref .ref-highlight {
                color: black !important;
                font-weight: bold !important;
            }
        }
    </style>

    <script>
        // Function to normalize names to Title Case
        function normalizeName(name) {
            if (!name) return '';
            return name.toLowerCase().split(' ').map(function (word) {
                return (word.charAt(0).toUpperCase() + word.slice(1));
            }).join(' ');
        }

        // Store all bills data
        let allBillsData = {
            initial: @json($initialBills),
            betterment: @json($bettermentBills),
            balance: @json($finalBills)
        };

        let conversionFileMetrics = @json(
            collect($allFiles ?? [])->mapWithKeys(function ($file) {
                $id = (string) ($file['id'] ?? '');
                return [
                    $id => [
                        'existing_site_area' => $file['existing_site_area'] ?? null,
                        'existing_structure_total' => $file['existing_structure_total'] ?? 0,
                    ]
                ];
            })
        );

        // Store current application data for export
        let currentApplication = {
            fileId: null,
            fileType: null,
            fileno: null,
            owner: null,
            unitType: null,
            existingSiteArea: null,
            existingStructureTotal: 0
        };

        // Store generated bills for persistence
        let generatedBills = {
            betterment: null,
            balance: null
        };

        // Function to hide betterment bill actions if NOT in physical plan mode
        function hideBettermentBillActions() {
            // In conversion mode, the conversion form handles its own UI — skip betterment hiding
            if (window.isConversionMode) {
                return;
            }

            // Show generate UI only when the URL has ?url=physical_planning
            if (!window.isPhysicalPlanMode) {
                // Hide only the "GENERATE BETTERMENT BILL" sub-tab when neither physical_planning nor SUA
                $('.betterment-tab-button[data-tab="generate"]').addClass('bb-hidden');
                $('#betterment-generate-tab').addClass('bb-hidden').removeClass('active').removeAttr('style');

                // Hide calculate and generate buttons when not allowed
                $('#calculate-betterment-btn, #generate-betterment-btn').addClass('bb-hidden');

                // Hide print bill buttons that call printBettermentBill
                $('button[onclick*="printBettermentBill"]').addClass('bb-hidden');

                // Switch to receipt sub-tab
                $('.betterment-tab-button').removeClass('active');
                $('.betterment-tab-button[data-tab="receipt"]').addClass('active');
                $('.betterment-tab-content').removeClass('active').addClass('bb-hidden');
                $('#betterment-receipt-tab').removeClass('bb-hidden').removeClass('hidden').addClass('active');
            } else {
                // Show the "GENERATE BETTERMENT BILL" sub-tab when URL has ?url=physical_planning or SUA selected
                $('.betterment-tab-button[data-tab="generate"]').removeClass('bb-hidden');
                $('#betterment-generate-tab').removeClass('bb-hidden').removeAttr('style');
                // Show generate buttons as well
                $('#calculate-betterment-btn, #generate-betterment-btn').removeClass('bb-hidden');
                $('button[onclick*="printBettermentBill"]').removeClass('bb-hidden');
                // Disable BILL BALANCE tab if in physical planning mode
                $('#balance-tab-btn').addClass('disabled');
            }
        }

        $(document).ready(function () {
            // Check URL parameter to conditionally hide betterment bill actions
            const urlParams = new URLSearchParams(window.location.search);
            const isPhysicalPlan = urlParams.get('url') === 'physical_planning';
            const isConversion = urlParams.get('source') === 'pp_conversion';

            // Store this globally for use in other functions
            window.isPhysicalPlanMode = isPhysicalPlan;
            window.isConversionMode = isConversion;

            // In conversion mode: hide Initial Bill and Bill Balance tabs, activate Betterment
            if (isConversion) {
                $('[data-tab="initial"]').addClass('disabled').hide();
                $('#balance-tab-btn').addClass('disabled').hide();
                $('#betterment-tab-btn').addClass('active').trigger('click');
                $('[data-tab="initial"]').removeClass('active');
                // Show initial tab content hidden, switch to betterment
                $('#initial-tab').addClass('hidden');
                $('#betterment-tab').removeClass('hidden');
            }

            // Apply hiding on page load
            hideBettermentBillActions();

            // Watch for changes in betterment content areas
            const observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (mutation.type === 'childList') {
                        // Use setTimeout to ensure DOM is fully updated
                        setTimeout(() => {
                            hideBettermentBillActions();
                        }, 50);
                    }
                });
            });

            // Start observing the betterment receipt container
            const receiptContainer = document.getElementById('betterment-receipt-container');
            if (receiptContainer) {
                observer.observe(receiptContainer, { childList: true, subtree: true });
            }

            // Also observe the main betterment bills content area
            const bettermentContent = document.getElementById('betterment-bills-content');
            if (bettermentContent) {
                observer.observe(bettermentContent, { childList: true, subtree: true });
            }

            // Initialize Select2
            $('#filenoSelect').select2({
                placeholder: 'Search by file number or owner name...',
                allowClear: true,
                width: '100%',
                templateResult: function (option) {
                    if (!option.id) return option.text;

                    var parts = option.text.split(' - ');
                    var fileno = parts[0];
                    var ownerInfo = parts[1];
                    var type = $(option.element).data('type');
                    var unitType = ($(option.element).data('unitType') || '').toString().toUpperCase();
                    var typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
                    if (type === 'unit' && unitType) {
                        typeLabel = unitType === 'SUA' ? 'SuA' : 'PuA';
                    } else if (type === 'unit') {
                        typeLabel = 'PuA';
                    }
                    // Ensure SUA is always uppercase
                    if (type === 'sua' || unitType === 'SUA') {
                        typeLabel = 'SuA';
                    }

                    var $option = $(
                        '<div class="flex items-center justify-between p-2">' +
                        '<div>' +
                        '<div class="font-medium text-gray-900">' + fileno + '</div>' +
                        '<div class="text-sm text-gray-500">' + ownerInfo + '</div>' +
                        '</div>' +
                        '<span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full font-medium">' + typeLabel + '</span>' +
                        '</div>'
                    );
                    return $option;
                },
                templateSelection: function (option) {
                    if (!option.id) return option.text;
                    var unitType = ($(option.element).data('unitType') || '').toString().toUpperCase();
                    var label = $(option.element).data('fileno') + ' - ' + $(option.element).data('owner');
                    if (unitType) {
                        label += unitType === 'SUA' ? ' (SuA)' : ' (' + (unitType === 'PUA' ? 'PuA' : unitType) + ')';
                    }
                    return label;
                }
            });

            // File number selection handler
            $('#filenoSelect').on('select2:select', function (e) {
                var data = e.params.data;
                var element = $(data.element);
                var fileId = data.id;
                var fileType = element.data('type');
                var fileno = element.data('fileno');
                var owner = element.data('owner');
                var applicationId = element.data('application-id');
                var unitType = element.data('unit-type');
                var existingSiteArea = element.data('existing-site-area');
                var existingStructureTotal = element.data('existing-structure-total');

                loadBillsForFile(fileId, fileType, fileno, owner, unitType, existingSiteArea, existingStructureTotal, applicationId);
            });

            // Clear selection handler
            $('#filenoSelect').on('select2:clear', function (e) {
                showNoSelection();
            });

            // Tab functionality
            $('.tab-button').click(function () {
                // Check if tab is disabled
                if ($(this).hasClass('disabled')) {
                    return false;
                }

                var tabId = $(this).data('tab');

                // Update active tab button
                $('.tab-button').removeClass('active');
                $(this).addClass('active');

                // Show corresponding tab content
                $('.tab-content').addClass('hidden');
                $('#' + tabId + '-tab').removeClass('hidden');
            });

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        function showNoSelection() {
            $('#no-selection').removeClass('hidden');
            $('#bills-container').addClass('hidden');

            currentApplication = {
                fileId: null,
                fileType: null,
                fileno: null,
                owner: null,
                unitType: null,
                existingSiteArea: null,
                existingStructureTotal: 0
            };
        }

        function loadBillsForFile(fileId, fileType, fileno, owner, unitType = null, existingSiteArea = null, existingStructureTotal = null, applicationId = null) {
            $('#no-selection').addClass('hidden');
            $('#bills-container').removeClass('hidden');

            // Parse and format metrics
            var parsedExistingSiteArea = parseFloat(String(existingSiteArea ?? '').replace(/,/g, ''));
            parsedExistingSiteArea = Number.isFinite(parsedExistingSiteArea) && parsedExistingSiteArea > 0 ? parsedExistingSiteArea : null;

            var parsedExistingStructureTotal = parseFloat(String(existingStructureTotal ?? '').replace(/,/g, ''));
            parsedExistingStructureTotal = Number.isFinite(parsedExistingStructureTotal) && parsedExistingStructureTotal > 0 ? parsedExistingStructureTotal : 0;

            // Store current application data
            currentApplication = {
                fileId: fileId,
                fileType: fileType,
                fileno: fileno,
                owner: owner ? owner.toUpperCase() : '',
                applicationId: applicationId,
                unitType: unitType,
                existingSiteArea: parsedExistingSiteArea,
                existingStructureTotal: parsedExistingStructureTotal
            };

            // Update application info
            $('#app-owner-name').text(currentApplication.owner);
            
            // Format Application ID
            var displayAppId = applicationId;
            if (!displayAppId || displayAppId === 'null') {
                displayAppId = (fileType === 'primary' ? 'ST-2025-' : 'STM-2025-') + String(fileId).padStart(4, '0');
            } else if (!isNaN(displayAppId)) {
                // If it's just a number (id), format it
                displayAppId = 'STM-2025-' + String(displayAppId).padStart(4, '0');
            }
            
            $('#app-id').text(displayAppId.toUpperCase());
            $('#app-fileno').text(fileno.toUpperCase());
            $('#app-type').html('<i data-lucide="file-text" class="w-4 h-4 mr-1"></i>' + formatApplicationTypeLabel(fileType, unitType));

            // Filter bills for this file
            var initialBills = filterBillsByFile(allBillsData.initial, fileId, fileType);
            var bettermentBills = filterBillsByFile(allBillsData.betterment, fileId, fileType);
            var balanceBills = filterBillsByFile(allBillsData.balance, fileId, fileType);

            // Conditionally disable tabs based on application type
            manageTabsBasedOnApplicationType(fileType);

            // Load bill cards
            loadInitialBills(initialBills);
            loadBettermentBills(bettermentBills);
            loadBalanceBills(balanceBills);

            // Reinitialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function formatApplicationTypeLabel(fileType, unitType) {
            if (fileType === 'conversion') {
                return 'Conversion Application';
            }
            if (fileType === 'unit') {
                const normalized = (unitType || '').toString().toUpperCase();
                if (normalized === 'SUA') {
                    return 'SuA Application';
                }
                return 'PuA Application';
            }
            if (fileType === 'sua') {
                return 'SuA Application';
            }
            const label = fileType ? fileType.charAt(0).toUpperCase() + fileType.slice(1) : 'Application';
            return label + ' Application';
        }

        function filterBillsByFile(bills, fileId, fileType) {
            return bills.filter(function (bill) {
                if (fileType === 'conversion') {
                    // Conversion bills matched by source_id (= JSI report id)
                    return bill.application_id == fileId || bill.source_id == fileId;
                } else if (fileType === 'primary') {
                    // Strictly match primary application bill (no sub_application_id)
                    return bill.application_id == fileId && (!bill.sub_application_id || bill.sub_application_id == 'null');
                } else {
                    // Match specific unit/sua application bill
                    return bill.sub_application_id == fileId;
                }
            });
        }

        function manageTabsBasedOnApplicationType(fileType) {
            // Reset all tabs to enabled state first
            $('.tab-button').removeClass('disabled');
            const normalized = (fileType || '').toString().toLowerCase();
            const isSUA = normalized === 'sua' || (currentApplication && (currentApplication.unitType || '').toString().toUpperCase() === 'SUA');

            // If in conversion mode, only Betterment tab is relevant
            if (window.isConversionMode || normalized === 'conversion') {
                $('[data-tab="initial"]').addClass('disabled').hide();
                $('#balance-tab-btn').addClass('disabled').hide();
                // Ensure betterment tab is active
                $('.tab-button').removeClass('active');
                $('#betterment-tab-btn').addClass('active');
                $('.tab-content').addClass('hidden');
                $('#betterment-tab').removeClass('hidden');
                return;
            }

            // If in physical planning mode, ensure BILL BALANCE is disabled
            if (window.isPhysicalPlanMode) {
                $('#balance-tab-btn').addClass('disabled');
            }

            if (normalized === 'primary') {
                // For primary applications: disable BILL BALANCE tab
                $('#balance-tab-btn').addClass('disabled');

                // If the currently active tab is the disabled one, switch to initial
                if ($('#balance-tab-btn').hasClass('active')) {
                    $('#balance-tab-btn').removeClass('active');
                    $('[data-tab="initial"]').addClass('active');
                    $('.tab-content').addClass('hidden');
                    $('#initial-tab').removeClass('hidden');
                }
            } else if ((normalized === 'unit' || normalized === 'secondary') && !isSUA) {
                // For unit applications (excluding SUA): disable BETTERMENT BILL tab only
                $('#betterment-tab-btn').addClass('disabled');

                // If the currently active tab is the disabled one, switch to initial
                if ($('#betterment-tab-btn').hasClass('active')) {
                    $('.tab-button').removeClass('active');
                    $('[data-tab="initial"]').addClass('active');
                    $('.tab-content').addClass('hidden');
                    $('#initial-tab').removeClass('hidden');
                }
            }
        }

        function loadInitialBills(bills) {
            var container = $('#initial-bills-content');

            if (bills.length === 0) {
                container.html(getEmptyState('initial', 'No initial bills found for this application.'));
                return;
            }

            var html = '';
            bills.forEach(function (bill) {
                // Check for primary or unit application fields
                var applicationFee, processingFee, sitePlanFee, paymentDate, receiptNumber;
                var appReceipt, procReceipt, siteReceipt;

                if (bill.primary_application_fee !== null && bill.primary_application_fee !== undefined) {
                    // Primary application
                    applicationFee = parseFloat(bill.primary_application_fee || 0);
                    processingFee = parseFloat(bill.primary_processing_fee || 0);
                    sitePlanFee = parseFloat(bill.primary_site_plan_fee || 0);
                    paymentDate = bill.primary_payment_date;
                    receiptNumber = bill.primary_receipt_number;
                    
                    appReceipt = bill.application_fee_receipt_number;
                    procReceipt = bill.processing_fee_receipt_number;
                    siteReceipt = bill.site_plan_fee_receipt_number;
                } else if (bill.unit_application_fee !== null && bill.unit_application_fee !== undefined) {
                    // Unit application
                    applicationFee = parseFloat(bill.unit_application_fee || 0);
                    processingFee = parseFloat(bill.unit_processing_fee || 0);
                    sitePlanFee = parseFloat(bill.unit_site_plan_fee || 0);
                    paymentDate = bill.unit_payment_date;
                    receiptNumber = bill.unit_receipt_number;

                    appReceipt = bill.application_fee_receipt_no;
                    procReceipt = bill.processing_fee_receipt_no;
                    siteReceipt = bill.survey_fee_receipt_no;
                } else {
                    // Fallback to original field names
                    applicationFee = parseFloat(bill.Scheme_Application_Fee || bill.Unit_Application_Fees || 0);
                    processingFee = parseFloat(bill.Processing_Fee || 0);
                    sitePlanFee = parseFloat(bill.Site_Plan_Fee || 0);
                    paymentDate = bill.payment_date;
                    receiptNumber = bill.receipt_number;

                    appReceipt = bill.application_fee_receipt_number || bill.application_fee_receipt_no;
                    procReceipt = bill.processing_fee_receipt_number || bill.processing_fee_receipt_no;
                    siteReceipt = bill.site_plan_fee_receipt_number || bill.survey_fee_receipt_no;
                }

                var total = applicationFee + processingFee + sitePlanFee;

                var billId = bill.id || bill.billing_id || bill.bill_id || bill.application_id || bill.sub_application_id || 'unknown';
                var rawUnitType = (bill.unit_type || currentApplication.unitType || '').toString().trim().toUpperCase();
                var unitTypeLabel = rawUnitType === 'SUA' ? 'SuA' : (rawUnitType === 'PUA' ? 'PuA' : '');

                html += createBillCard({
                    id: billId,
                    title: 'Initial Application Bill',
                    subtitle: 'Application Fee, Processing Fee, Site Plan Fee',
                    items: [
                        { label: 'Application Fee', amount: applicationFee, receipt: appReceipt },
                        { label: 'Processing Fee', amount: processingFee, receipt: procReceipt },
                        { label: 'Site Plan Fee', amount: sitePlanFee, receipt: siteReceipt }
                    ],
                    total: total,
                    status: bill.Payment_Status,
                    date: paymentDate || bill.created_at,
                    receipt_number: receiptNumber,
                    type: 'initial',
                    unitType: unitTypeLabel
                });
            });

            container.html(html);
        }

        function loadBettermentBills(bills) {
            var container = $('#betterment-bills-content');

            // Conversion applications — show existing bill or generation form
            if (currentApplication.fileType === 'conversion') {
                if (bills.length > 0) {
                    // Show existing conversion bill cards
                    var html = '';
                    bills.forEach(function (bill) {
                        var totalFee = parseFloat(bill.Betterment_Charges || 0);
                        var area = parseFloat(bill.property_value || 0);
                        var rate = parseFloat(bill.betterment_rate || 5);
                        var billId = bill.ID || bill.id || bill.bill_id || 'unknown';

                        html += createBillCard({
                            id: billId,
                            title: 'Conversion Betterment Bill',
                            subtitle: 'Ref: ' + (bill.ref_id || 'N/A') + ' — ' + (bill.jsi_land_use || bill.applied_land_use || 'Conversion'),
                            items: [
                                { label: 'Total Area', amount: area.toFixed(2) + ' m²' },
                                { label: 'Rate per extra m²', amount: '₦' + rate.toFixed(2) },
                                { label: 'Betterment Charges', amount: totalFee }
                            ],
                            total: totalFee,
                            status: bill.Payment_Status || 'Pending',
                            date: bill.receipt_date || bill.created_at,
                            receipt_number: bill.Betterment_receipt || '',
                            type: 'betterment',
                            unitType: ''
                        });
                    });
                    container.html(html);
                } else {
                    // No bill yet — show conversion generation form
                    container.html(getConversionBettermentBillForm());
                }

                // Reinitialize icons
                if (typeof lucide !== 'undefined') lucide.createIcons();
                return;
            }

            // Always show the betterment bill generation form for primary applications
            if (currentApplication.fileType === 'primary' || currentApplication.fileType === 'sua') {
                container.html(getBettermentBillForm());

                // Apply button hiding logic after form is generated
                hideBettermentBillActions();

                return;
            }

            // For non-primary applications, show empty state
            container.html(getEmptyState('betterment', 'Betterment bills are only available for primary applications.'));
        }

        function loadBalanceBills(bills) {
            var container = $('#balance-bills-content');

            // Show the balance bill generation form for unit/secondary and SUA applications
            const normalized = (currentApplication.fileType || '').toString().toLowerCase();
            const isSUA = (currentApplication && (currentApplication.unitType || '').toString().toUpperCase() === 'SUA') || normalized === 'sua';
            const eligibleForBalance = normalized === 'unit' || normalized === 'secondary' || normalized === 'sua' || isSUA;

            if (eligibleForBalance) {
                container.html(getBalanceBillForm());
                // Fields render blank at 0.00, so recalculate immediately —
                // otherwise the total keeps whatever markup getBalanceBillForm() shipped.
                calculateBalanceTotal();
                backfillRecordedBillBalance();
                return;
            }

            // For non-eligible applications, show empty state
            container.html(getEmptyState('balance', 'Bill balance is only available for PuA and SuA applications.'));
        }

        /**
         * Pull the Bill Balance already recorded for this unit (File Indexing
         * capture, or the Deeds Bill Balance certificate) so staff do not
         * re-key it. ST unit numbers are not present in those stores, so the
         * server falls back to the parent scheme's land file number.
         *
         * Purely a read — it never creates a billing row.
         */
        function backfillRecordedBillBalance() {
            if (!currentApplication || !currentApplication.fileId) {
                return;
            }

            var $note = $('#balance-source-note');
            $note.text('Checking for a recorded bill balance…');

            fetch(`${window.location.origin}/programmes/bills/st-balance-source/${currentApplication.fileId}`)
                .then(response => response.json())
                .then(payload => {
                    var d = payload && payload.data;

                    if (!payload.success || !d || !d.found) {
                        $note.text('No bill balance on record for this file — enter it manually.');
                        return;
                    }

                    // Do not clobber a figure the user has already typed.
                    var current = readBalanceMoney('#balance-bill-balance');
                    if (current === 0) {
                        $('#balance-bill-balance').val(parseFloat(d.amount || 0).toFixed(2));
                        calculateBalanceTotal();
                    }

                    $('#balance-source-billing-id').val(d.billing_id || '');

                    var via = d.matched_on === 'parent'
                        ? ` via parent file ${d.file_number}`
                        : ` on ${d.file_number}`;
                    $note.text(`Backfilled from ${d.source_label}${via}.`);
                })
                .catch(error => {
                    console.error('Bill balance backfill failed:', error);
                    $note.text('Could not check for a recorded bill balance.');
                });
        }

        function createBillCard(data) {
            var statusClass = getStatusClass(data.status, data.type);
            var statusText = getStatusText(data.status, data.type);
            var formattedDate = data.date ? new Date(data.date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }) : 'N/A';
            var unitTypeBadge = data.unitType ? `<span class="bill-type-badge">${data.unitType}</span>` : '';

            var itemsHtml = '';
            data.items.forEach(function (item) {
                var formattedAmount = !isNaN(parseFloat(item.amount)) 
                    ? `₦${parseFloat(item.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
                    : (item.amount || '—');
                    
                itemsHtml += `
                                                    <div class="flex justify-between text-sm py-1 border-b border-gray-50 last:border-0">
                                                        <span class="text-gray-600">${item.label}</span>
                                                        <div class="text-right flex flex-col items-end">
                                                            <span class="font-bold text-gray-900">${formattedAmount}</span>
                                                            ${item.receipt ? `<span class="inline-block mt-0.5 px-1.5 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-bold rounded border border-blue-100 uppercase">Rec: ${item.receipt}</span>` : ''}
                                                        </div>
                                                    </div>
                                                `;
            });

            return `
                                                <div class="bill-card p-4 mb-3">
                                                    <div class="flex items-start justify-between mb-3">
                                                        <div>
                                                            <div class="flex items-center gap-2">
                                                                <h3 class="text-base font-semibold text-gray-900">${data.title}</h3>
                                                                ${unitTypeBadge}
                                                            </div>
                                                            <p class="text-xs text-gray-500">${data.subtitle}</p>
                                                        </div>
                                                        <span class="status-badge ${statusClass}">
                                                            <i data-lucide="${getStatusIcon(data.status, data.type)}" class="w-3 h-3 mr-1"></i>
                                                            ${statusText}
                                                        </span>
                                                    </div>

                                                    <div class="space-y-1 mb-3">
                                                        ${itemsHtml}
                                                    </div>

                                                    <hr class="my-3">

                                                    <div class="flex justify-between items-center mb-3">
                                                        <div>
                                                            <p class="text-xs text-gray-500">Total Amount</p>
                                                            <p class="text-lg font-bold text-gray-900">₦${parseFloat(data.total).toLocaleString('en-US', { minimumFractionDigits: 2 })}</p>
                                                        </div>
                                                        <div class="text-right">
                                                            <p class="text-xs text-gray-500">Date</p>
                                                            <p class="text-xs font-medium">${formattedDate}</p>
                                                        </div>
                                                    </div>

                                                    <div class="flex gap-2">
                                                        <button onclick="printBill('${data.type}', '${data.id}')" class="flex items-center px-2 py-1 text-xs border border-gray-300 rounded bg-white hover:bg-gray-50 transition-colors">
                                                            <i data-lucide="printer" class="w-3 h-3 mr-1"></i>
                                                            Print
                                                        </button>
                                                    </div>
                                                </div>
                                            `;
        }

        function getEmptyState(type, message) {
            var icons = {
                initial: 'banknote',
                betterment: 'calculator',
                balance: 'file-check'
            };

            return `
                                                <div class="text-center py-12">
                                                    <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                        <i data-lucide="${icons[type]}" class="w-8 h-8 text-gray-400"></i>
                                                    </div>
                                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Bills Found</h3>
                                                    <p class="text-gray-500">${message}</p>
                                                </div>
                                            `;
        }

        function getStatusClass(status, type) {
            if (type === 'balance') {
                switch (status) {
                    case 'paid': return 'status-paid';
                    case 'generated':
                    case 'sent': return 'status-pending';
                    default: return 'status-overdue';
                }
            } else {
                switch (status) {
                    case 'Complete': return 'status-paid';
                    case 'Incomplete': return 'status-pending';
                    default: return 'status-overdue';
                }
            }
        }

        function getStatusText(status, type) {
            if (type === 'balance') {
                switch (status) {
                    case 'paid': return 'Paid';
                    case 'generated': return 'Generated';
                    case 'sent': return 'Sent';
                    case 'cancelled': return 'Cancelled';
                    default: return status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown';
                }
            } else {
                switch (status) {
                    case 'Complete': return 'Paid';
                    case 'Incomplete': return 'Pending';
                    default: return status || 'Paid';
                }
            }
        }

        function getStatusIcon(status, type) {
            if (type === 'balance') {
                switch (status) {
                    case 'paid': return 'check-circle';
                    case 'generated':
                    case 'sent': return 'clock';
                    case 'cancelled': return 'x-circle';
                    default: return 'help-circle';
                }
            } else {
                switch (status) {
                    case 'Complete': return 'check-circle';
                    case 'Incomplete': return 'clock';
                    default: return 'alert-circle';
                }
            }
        }

        function printBill(billType, billId) {
            if (!billId || billId === 'undefined') {
                alert('Bill ID is missing. Cannot print bill.');
                return;
            }

            console.log('Printing bill:', billType, billId);

            // Open print view in new window
            const baseUrl = window.location.origin;
            const printUrl = `${baseUrl}/programmes/bill/print/${billType}/${billId}`;
            window.open(printUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
        }

        // Generate Betterment Bill Form
        function getBettermentBillForm() {
            // Get application data for the current file
            var landSize = '1,200'; // Default value
            var unitsCount = '12'; // Default value
            var landUse = 'Residential'; // Default value

            // Try to get actual values from the current application
            if (currentApplication && currentApplication.fileId) {
                // Fetch application details to get plot_size, NoOfUnits, and land_use
                fetchApplicationDetails(currentApplication.fileId, currentApplication.fileType);
            }

            return `
                                                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                                    <div class="p-4 border-b">
                                                        <h3 class="text-sm font-medium">Generate Betterment Bill</h3>
                                                        <p class="text-xs text-gray-500">Generate and manage betterment charges for primary applications</p>
                                                    </div>

                                                    <!-- Tabs Navigation -->
                                                    <div class="p-4">
                                                        <div class="grid grid-cols-2 gap-2 mb-4">
                                                            <button class="betterment-tab-button active" data-tab="generate">
                                                                <i data-lucide="calculator" class="w-3.5 h-3.5 mr-1.5"></i>
                                                                GENERATE BETTERMENT BILL
                                                            </button>
                                                            <button class="betterment-tab-button" data-tab="receipt">
                                                                <i data-lucide="file-text" class="w-3.5 h-3.5 mr-1.5"></i>
                                                                 Betterment Bill Invoice
                                                            </button>
                                                        </div>

                                                        <!-- Generate Tab -->
                                                        <div id="betterment-generate-tab" class="betterment-tab-content active">
                                                            <div class="p-4 border border-gray-200 rounded-md">
                                                                <h4 class="text-sm font-medium mb-3">Calculate Betterment Charges</h4>
                                                                <form id="betterment-form" class="space-y-4">
                                                                    <div class="grid grid-cols-2 gap-4">
                                                                        <div class="space-y-2">
                                                                            <label for="betterment-land-use" class="text-xs font-medium block">
                                                                                Land Use
                                                                            </label>
                                                                            <input id="betterment-land-use" name="land_use" type="text" value="${landUse}"
                                                                                class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-50" readonly>
                                                                        </div>
                                                                        <div class="space-y-2">
                                                                            <label for="betterment-units-count" class="text-xs font-medium block">
                                                                                Number of Units
                                                                            </label>
                                                                            <input id="betterment-units-count" name="units_count" type="text" value="${unitsCount}"
                                                                                class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-50" readonly>
                                                                        </div>
                                                                    </div>

                                                                    <div class="grid grid-cols-2 gap-4">
                                                                        <div class="space-y-2">
                                                                            <label for="betterment-land-size" class="text-xs font-medium block">
                                                                                Total Land Size (m²)
                                                                            </label>
                                                                            <input id="betterment-land-size" name="land_size" type="text" value="${landSize}"
                                                                                class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-50" readonly>
                                                                        </div>
                                                                        <div class="space-y-2">
                                                                            <label for="betterment-area-per-unit" class="text-xs font-medium block">
                                                                                Area per Unit (m²)
                                                                            </label>
                                                                            <input id="betterment-area-per-unit" name="area_per_unit" type="text" value="100"
                                                                                class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-50" readonly>
                                                                        </div>
                                                                    </div>

                                                                    <div class="bg-gray-50 p-3 rounded-md">
                                                                        <h4 class="text-xs font-medium">New Calculation Formula</h4>
                                                                        <p class="text-xs text-gray-500">Total Fee = Number of Units × (Base Fee + (Area per Unit - Threshold) × Rate)</p>
                                                                        <p class="text-xs text-gray-400 mt-1">Rates vary by land use: Industrial/Commercial (₦35,000 base, ₦5/m²), Residential (₦15,000 base, ₦1/m²), etc.</p>
                                                                    </div>

                                                                    <div class="flex justify-between items-center">
                                                                        <div>
                                                                            <p class="text-xs text-gray-500">Estimated Amount</p>
                                                                            <p class="text-lg font-bold" id="betterment-amount">₦0.00</p>
                                                                        </div>
                                                                        <div class="flex gap-2" id="betterment-action-buttons">
                                                                            <button type="button" id="calculate-betterment-btn" class="px-3 py-1 text-xs bg-gray-600 text-white rounded-md hover:bg-gray-700">
                                                                                <i data-lucide="calculator" class="w-3.5 h-3.5 mr-1.5 inline-block"></i>
                                                                                Calculate
                                                                            </button>
                                                                            <button type="button" id="generate-betterment-btn" class="px-3 py-1 text-xs bg-green-600 text-white rounded-md hover:bg-green-700">
                                                                                <i data-lucide="save" class="w-3.5 h-3.5 mr-1.5 inline-block"></i>
                                                                                Generate Bill
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>

                                                        <!-- Receipt Tab -->
                                                        <div id="betterment-receipt-tab" class="betterment-tab-content bb-hidden">
                                                            <div class="p-4 border border-gray-200 rounded-md">
                                                                <h4 class="text-sm font-medium mb-3"> Betterment Bill Invoice</h4>
                                                                <div id="betterment-receipt-container">
                                                                    <div class="text-center p-8">
                                                                        <p class="text-sm text-gray-500">No betterment bill has been generated yet.</p>
                                                                        <p class="text-xs text-gray-400 mt-2">Please generate a bill first.</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            `;
        }

        // Conversion Betterment Bill Form — uses pp_betterment_rates via AJAX
        function getConversionBettermentBillForm() {
            // Build category options from server-side $rates (only available in conversion mode)
            var categoryOptions = '';
            @if(isset($rates) && count($rates ?? []))
                @foreach($rates as $rate)
                    categoryOptions += '<option value="{{ $rate->category }}" data-base-fee="{{ $rate->base_fee }}" data-base-area="{{ $rate->base_area }}" data-rate="{{ $rate->rate_per_extra_sqm }}">{{ $rate->category }} (Base: ₦{{ number_format($rate->base_fee) }} / {{ number_format($rate->base_area) }} m²)</option>';
                @endforeach
            @endif

            // Resolve metrics by file id at render time to avoid UI state drift
            var metricKey = String(currentApplication.fileId || '');
            var metrics = (conversionFileMetrics && conversionFileMetrics[metricKey])
                ? conversionFileMetrics[metricKey]
                : null;

            var selectedOption = $('#filenoSelect').find('option:selected');
            var optionArea = selectedOption.attr('data-existing-site-area') || selectedOption.data('existingSiteArea');
            var optionStructure = selectedOption.attr('data-existing-structure-total') || selectedOption.data('existingStructureTotal');

            var resolvedExistingSiteArea = parseFloat(String(
                (metrics && metrics.existing_site_area !== undefined && metrics.existing_site_area !== null && metrics.existing_site_area !== ''
                    ? metrics.existing_site_area
                    : (currentApplication.existingSiteArea ?? optionArea ?? ''))
            ).replace(/,/g, ''));
            resolvedExistingSiteArea = Number.isFinite(resolvedExistingSiteArea) && resolvedExistingSiteArea > 0
                ? resolvedExistingSiteArea
                : null;

            var resolvedExistingStructureTotal = parseFloat(String(
                (metrics && metrics.existing_structure_total !== undefined && metrics.existing_structure_total !== null && metrics.existing_structure_total !== ''
                    ? metrics.existing_structure_total
                    : (currentApplication.existingStructureTotal ?? optionStructure ?? '0'))
            ).replace(/,/g, ''));
            resolvedExistingStructureTotal = Number.isFinite(resolvedExistingStructureTotal) && resolvedExistingStructureTotal > 0
                ? resolvedExistingStructureTotal
                : 0;

            currentApplication.existingSiteArea = resolvedExistingSiteArea;
            currentApplication.existingStructureTotal = resolvedExistingStructureTotal;

            return `
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b">
                        <h3 class="text-sm font-medium">Generate Conversion Betterment Bill</h3>
                        <p class="text-xs text-gray-500">Calculate and generate betterment charges based on land-use category and total area</p>
                    </div>

                    <!-- Tabs Navigation -->
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <button class="conv-bill-tab-button active" data-tab="generate">
                                <i data-lucide="calculator" class="w-3.5 h-3.5 mr-1.5"></i>
                                GENERATE BETTERMENT BILL
                            </button>
                            <button class="conv-bill-tab-button" data-tab="receipt">
                                <i data-lucide="file-text" class="w-3.5 h-3.5 mr-1.5"></i>
                                Betterment Bill Invoice
                            </button>
                        </div>

                        <!-- Generate Tab -->
                        <div id="conv-bill-generate-tab" class="conv-bill-tab-content active">
                            <div class="p-4 border border-gray-200 rounded-md">
                                <h4 class="text-sm font-medium mb-3">Calculate Betterment Charges</h4>
                                <form id="conv-betterment-form" class="space-y-4">
                                    <!-- File Details (readonly) -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <label class="text-xs font-medium block">File Number</label>
                                            <input type="text" value="${currentApplication.fileno}" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-50" readonly>
                                        </div>
                                        <div class="space-y-2">
                                            <label class="text-xs font-medium block">Applicant Name</label>
                                            <input type="text" value="${currentApplication.owner}" class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-50" readonly>
                                        </div>
                                    </div>

                                    <!-- Category and Area Inputs -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <label for="conv-existing-structure-total" class="text-xs font-medium block">
                                                Existing Structure Total (NGN)
                                            </label>
                                            <input id="conv-existing-structure-total" type="text"
                                                value="₦${Number(resolvedExistingStructureTotal || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}"
                                                class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-50" readonly>
                                        </div>
                                        <div class="space-y-2">
                                            <label for="conv-category" class="text-xs font-medium block">
                                                Land-Use Category <span class="text-red-500">*</span>
                                            </label>
                                            <select id="conv-category" name="category" class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                                                <option value="">Select category...</option>
                                                ${categoryOptions}
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4">
                                        <div class="space-y-2">
                                            <label for="conv-area" class="text-xs font-medium block">
                                                Existing Site Measurement Area Covered (m²) <span class="text-red-500">*</span>
                                            </label>
                                            <input id="conv-area" name="area" type="number" min="0" step="0.01"
                                                value="${resolvedExistingSiteArea || ''}"
                                                placeholder="Area is auto-filled from JSI"
                                                class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-50" readonly required>
                                        </div>
                                    </div>

                                    <!-- Rate info panel -->
                                    <div id="conv-rate-info" class="bg-gray-50 p-3 rounded-md hidden">
                                        <h4 class="text-xs font-medium mb-1">Rate Details</h4>
                                        <p class="text-xs text-gray-600">Existing Structure Total: <strong id="conv-info-existing-structure">₦${Number(resolvedExistingStructureTotal || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong></p>
                                        <p class="text-xs text-gray-600">Base Fee: <strong id="conv-info-base-fee">—</strong></p>
                                        <p class="text-xs text-gray-600">Base Area: <strong id="conv-info-base-area">—</strong></p>
                                        <p class="text-xs text-gray-600">Rate per extra m²: <strong id="conv-info-rate">₦5.00</strong></p>
                                        <p class="text-xs text-gray-400 mt-1">Formula: Total Fee = Base Fee + max(0, (Total Area − Base Area) × Rate)</p>
                                    </div>

                                    <!-- Calculation result & action buttons -->
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-xs text-gray-500">Estimated Amount</p>
                                            <p class="text-lg font-bold" id="conv-betterment-amount">₦0.00</p>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="button" id="calculate-conv-btn" class="px-3 py-1 text-xs bg-gray-600 text-white rounded-md hover:bg-gray-700">
                                                <i data-lucide="calculator" class="w-3.5 h-3.5 mr-1.5 inline-block"></i>
                                                Calculate
                                            </button>
                                            <button type="button" id="generate-conv-btn" class="px-3 py-1 text-xs bg-green-600 text-white rounded-md hover:bg-green-700" disabled>
                                                <i data-lucide="save" class="w-3.5 h-3.5 mr-1.5 inline-block"></i>
                                                Generate Bill
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Receipt Tab -->
                        <div id="conv-bill-receipt-tab" class="conv-bill-tab-content bb-hidden">
                            <div class="p-4 border border-gray-200 rounded-md">
                                <h4 class="text-sm font-medium mb-3">Betterment Bill Invoice</h4>
                                <div id="conv-receipt-container">
                                    <div class="text-center p-8">
                                        <p class="text-sm text-gray-500">No betterment bill has been generated yet.</p>
                                        <p class="text-xs text-gray-400 mt-2">Please generate a bill first.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Generate Balance Bill Form
        function getBalanceBillForm() {
            const isSUA = (currentApplication && (currentApplication.unitType || '').toString().toUpperCase() === 'SUA') || (currentApplication.fileType === 'sua');
            const label = isSUA ? 'SuA' : 'PuA';
            return `
                                                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                                                    <div class="p-4 border-b">
                                                        <h3 class="text-sm font-medium">${label} Application Final Bill Balance</h3>
                                                        <p class="text-xs text-gray-500">Calculate, generate and preview final bill balance for ${label} applications</p>
                                                    </div>

                                                    <!-- Tabs Navigation -->
                                                    <div class="p-4">
                                                        <div class="grid grid-cols-2 gap-2 mb-4">
                                                            <button class="balance-tab-button active" data-tab="calculate">
                                                                <i data-lucide="calculator" class="w-3.5 h-3.5 mr-1.5"></i>
                                                                CALCULATE & GENERATE BILL
                                                            </button>
                                                            <button class="balance-tab-button" data-tab="preview">
                                                                <i data-lucide="eye" class="w-3.5 h-3.5 mr-1.5"></i>
                                                                PREVIEW BILL
                                                            </button>
                                                        </div>

                                                        <!-- Calculate Tab -->
                                                        <div id="balance-calculate-tab" class="balance-tab-content active">
                                                            <div class="p-4 border border-gray-200 rounded-md">
                                                                <h4 class="text-sm font-medium mb-3">Generate Bill Balance</h4>
                                                                <form id="balance-form" class="space-y-4">
                                                                    <!-- Owner Details (Disabled) -->
                                                                    <div class="mb-3">
                                                                        <div class="space-y-2">
                                                                            <label for="balance-bill-ref-id" class="text-xs font-medium text-green-600">Bill Reference ID</label>
                                                                            <input id="balance-bill-ref-id" name="bill_ref_id" type="text" 
                                                                                value="${stBillReference(currentApplication.fileId, new Date().toISOString().slice(0, 10))}"
                                                                                class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly>
                                                                        </div>
                                                                        <br>
                                                                        <h5 class="text-xs font-semibold mb-2">Owner Details</h5>
                                                                        <div class="grid grid-cols-2 gap-4">
                                                                            <div class="space-y-2">
                                                                                <label for="balance-file-no" class="text-xs font-medium">File Number</label>
                                                                                <input id="balance-file-no" type="text" value="${currentApplication.fileno}" 
                                                                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" disabled>
                                                                            </div>
                                                                            <div class="space-y-2">
                                                                                <label for="balance-owner-name" class="text-xs font-medium">Owner Name</label>
                                                                                <input id="balance-owner-name" type="text" value="${currentApplication.owner}" 
                                                                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" disabled>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Fee Customization -->
                                                                    <div class="mb-3">
                                                                        <h5 class="text-xs font-semibold mb-2">Charges & Fees</h5>
                                                                        <div class="grid grid-cols-2 gap-4">
                                                                            <div class="space-y-2">
                                                                                <label for="balance-assignment-fee" class="text-xs font-medium">Assignment Fee (₦)</label>
                                                                                <input id="balance-assignment-fee" name="assignment_fee" type="text" value="0.00" 
                                                                                    class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                                                            </div>
                                                                            <div class="space-y-2">
                                                                                <label for="balance-bill-balance" class="text-xs font-medium">Bill Balance (₦)</label>
                                                                                <input id="balance-bill-balance" name="bill_balance" type="text" value="0.00"
                                                                                    class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                                                                <input id="balance-source-billing-id" type="hidden" value="">
                                                                                <p id="balance-source-note" class="text-xs text-gray-400"></p>
                                                                            </div>
                                                                            <div class="space-y-2">
                                                                                <label for="balance-recertification-fee" class="text-xs font-medium">Recertification Fee (₦)</label>
                                                                                <input id="balance-recertification-fee" name="recertification_fee" type="text" value="0.00" 
                                                                                    class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                                                            </div>
                                                                            <div class="space-y-2">
                                                                                <label for="balance-bill-date" class="text-xs font-medium">Bill Date</label>
                                                                                <input id="balance-bill-date" name="bill_date" type="date" value="${new Date().toISOString().slice(0, 10)}" 
                                                                                    class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Development Charges -->
                                                                    <div class="space-y-2">
                                                                        <label for="balance-dev-charges" class="text-xs font-medium">Development Charges (₦)</label>
                                                                        <input id="balance-dev-charges" name="dev_charges" type="text" value="0.00" 
                                                                            class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                                                    </div>

                                                                    <!-- Total Amount (Calculated) -->
                                                                    <div class="mt-4 p-3 bg-gray-100 rounded-md">
                                                                        <div class="flex justify-between items-center">
                                                                            <div>
                                                                                <p class="text-xs text-gray-600">Total Amount:</p>
                                                                                <p class="text-lg font-bold" id="balance-calculated-total">₦0.00</p>
                                                                            </div>
                                                                            <div class="flex gap-2">
                                                                                <button type="button" id="calculate-balance-total-btn" class="px-3 py-1 text-xs bg-gray-600 text-white rounded-md hover:bg-gray-700">
                                                                                    <i data-lucide="calculator" class="w-3.5 h-3.5 mr-1.5 inline-block"></i>
                                                                                    Calculate
                                                                                </button>
                                                                                <button type="button" id="save-balance-bill-btn" class="px-3 py-1 text-xs bg-green-600 text-white rounded-md hover:bg-green-700">
                                                                                    <i data-lucide="save" class="w-3.5 h-3.5 mr-1.5 inline-block"></i>
                                                                                    Generate Bill
                                                                                </button>
                                                                                <button type="button" id="preview-balance-bill-btn" class="px-3 py-1 text-xs bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                                                                    <i data-lucide="eye" class="w-3.5 h-3.5 mr-1.5 inline-block"></i>
                                                                                    Preview Bill
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>

                                                        <!-- Preview Tab -->
                                                        <div id="balance-preview-tab" class="balance-tab-content bb-hidden">
                                                            <div id="balance-preview-container">
                                                                <div class="text-center p-8">
                                                                    <p class="text-sm text-gray-500">No bill preview available yet.</p>
                                                                    <p class="text-xs text-gray-400 mt-2">Please calculate and generate a bill first.</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            `;
        }

        // Event delegation for dynamically created elements
        $(document).on('click', '.betterment-tab-button', function () {
            var tab = $(this).data('tab');

            // Update active tab button
            $('.betterment-tab-button').removeClass('active');
            $(this).addClass('active');

            // Hide all tab contents
            $('.betterment-tab-content').removeClass('active').addClass('bb-hidden').removeAttr('style');

            // Show selected tab
            $('#betterment-' + tab + '-tab').removeClass('bb-hidden').removeClass('hidden').addClass('active');

            // Apply button hiding logic when switching tabs
            setTimeout(() => {
                hideBettermentBillActions();
            }, 100);

            // Reinitialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        $(document).on('click', '.balance-tab-button', function () {
            var tab = $(this).data('tab');

            // Update active tab button
            $('.balance-tab-button').removeClass('active');
            $(this).addClass('active');

            // Hide all tab contents
            $('.balance-tab-content').removeClass('active').addClass('bb-hidden').removeAttr('style');

            // Show selected tab
            $('#balance-' + tab + '-tab').removeClass('bb-hidden').removeClass('hidden').addClass('active');

            // Reinitialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        // Betterment bill calculation using new formula
        $(document).on('click', '#calculate-betterment-btn', function () {
            var landUse = $('#betterment-land-use').val() || 'Residential';
            var unitsCount = parseInt($('#betterment-units-count').val()) || 1;
            var totalLandSize = parseFloat($('#betterment-land-size').val().replace(/,/g, '')) || 1200;
            var areaPerUnit = parseFloat($('#betterment-area-per-unit').val()) || (totalLandSize / unitsCount);

            // Show loading
            Swal.fire({
                title: 'Calculating...',
                text: 'Please wait while we calculate the betterment charges using the new formula.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Simulate calculation delay
            setTimeout(() => {
                // Get land use parameters
                var landUseParams = getLandUseParameters(landUse);
                var baseFee = landUseParams.baseFee;
                var threshold = landUseParams.threshold;
                var rate = landUseParams.rate;

                // Calculate using new formula
                var bettermentAmount = 0;

                if (areaPerUnit <= threshold) {
                    // If Area per Unit ≤ Threshold, then: Total Fee = Number of Units × Base Fee
                    bettermentAmount = unitsCount * baseFee;
                } else {
                    // Total Fee = Number of Units × (Base Fee + (Area per Unit - Threshold) × Rate)
                    bettermentAmount = unitsCount * (baseFee + ((areaPerUnit - threshold) * rate));
                }

                $('#betterment-amount').text('₦' + bettermentAmount.toLocaleString('en-US', { minimumFractionDigits: 2 }));

                // Store calculation details for bill generation
                $('#betterment-form').data('calculation', {
                    landUse: landUse,
                    unitsCount: unitsCount,
                    totalLandSize: totalLandSize,
                    areaPerUnit: areaPerUnit,
                    baseFee: baseFee,
                    threshold: threshold,
                    rate: rate,
                    bettermentAmount: bettermentAmount
                });

                Swal.fire({
                    icon: 'success',
                    title: 'Calculation Complete!',
                    html: `
                                                        <div class="text-left">
                                                            <p><strong>Land Use:</strong> ${landUse}</p>
                                                            <p><strong>Number of Units:</strong> ${unitsCount}</p>
                                                            <p><strong>Area per Unit:</strong> ${areaPerUnit.toFixed(2)} m²</p>
                                                            <p><strong>Base Fee:</strong> ₦${baseFee.toLocaleString()}</p>
                                                            <p><strong>Threshold:</strong> ${threshold} m²</p>
                                                            <p><strong>Rate:</strong> ₦${rate}/m²</p>
                                                            <hr class="my-2">
                                                            <p><strong>Total Betterment Charges:</strong> ₦${bettermentAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</p>
                                                        </div>
                                                    `,
                    confirmButtonColor: '#10b981'
                });
            }, 1500);
        });

        // Function to get land use parameters
        function getLandUseParameters(landUse) {
            var params = {
                baseFee: 15000,
                threshold: 100,
                rate: 1
            };

            switch (landUse.toLowerCase()) {
                case 'industrial':
                    params = { baseFee: 35000, threshold: 100, rate: 5 };
                    break;
                case 'commercial':
                    params = { baseFee: 35000, threshold: 100, rate: 5 };
                    break;
                case 'commercial (petrol station)':
                    params = { baseFee: 50000, threshold: 100, rate: 5 };
                    break;
                case 'agricultural':
                    params = { baseFee: 25000, threshold: 5000, rate: 1 };
                    break;
                case 'residential':
                default:
                    params = { baseFee: 15000, threshold: 100, rate: 1 };
                    break;
            }

            return params;
        }

        // Generate betterment bill with saving functionality using new formula
        $(document).on('click', '#generate-betterment-btn', function () {
            // Get calculation data from the form
            var calculationData = $('#betterment-form').data('calculation');

            if (!calculationData || !calculationData.bettermentAmount) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Calculation',
                    text: 'Please calculate the betterment amount first using the new formula.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            // Show loading
            Swal.fire({
                title: 'Generating Bill...',
                text: 'Please wait while we generate your betterment bill.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Prepare data for backend
            var billData = {
                application_id: currentApplication.fileId,
                betterment_charges: calculationData.bettermentAmount,
                ref_id: `BB-${currentApplication.fileId}-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}`,
                Sectional_Title_File_No: currentApplication.fileno,
                _token: $('meta[name="csrf-token"]').attr('content')
            };

            // Save to backend via AJAX
            fetch(`${window.location.origin}/betterment-bill/store`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(billData)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Store in localStorage for persistence
                        generatedBills.betterment = {
                            ...billData,
                            land_use: calculationData.landUse,
                            units_count: calculationData.unitsCount,
                            total_land_size: calculationData.totalLandSize,
                            area_per_unit: calculationData.areaPerUnit,
                            base_fee: calculationData.baseFee,
                            threshold: calculationData.threshold,
                            rate: calculationData.rate,
                            generated_date: new Date().toISOString().slice(0, 10),
                            owner_name: currentApplication.owner
                        };
                        localStorage.setItem('betterment_bill_' + currentApplication.fileId, JSON.stringify(generatedBills.betterment));

                        // Update receipt tab with generated bill using new data
                        var receiptHtml = getBettermentReceiptHtmlNew(calculationData);

                        $('#betterment-receipt-container').html(receiptHtml);
                        $('.betterment-tab-button[data-tab="receipt"]').click();

                        // Apply button hiding logic after receipt content is added
                        hideBettermentBillActions();

                        // Reinitialize Lucide icons
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Bill Generated & Saved Successfully!',
                            text: `Betterment bill generated for ₦${calculationData.bettermentAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}`,
                            confirmButtonColor: '#10b981'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error Saving Bill',
                            text: data.message || 'Failed to save betterment bill. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error saving betterment bill:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to save betterment bill. Please try again.',
                        confirmButtonColor: '#ef4444'
                    });
                });
        });

        // Function to generate betterment receipt HTML with proper print structure
        function getBettermentReceiptHtml(propertyValue, bettermentRate, bettermentAmount) {
            return `
                                                <div class="print-area">
                                                    <!-- Header with logos -->
                                                    <div class="print-header">
                                                        <div class="print-logos">
                                                            <div class="print-logo-left">
                                                                <img src="/assets/logo/logo1.jpg" alt="Kano State Logo" class="print-logo">
                                                            </div>
                                                            <div class="print-title">
                                                                <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                                                                <h2>BETTERMENT CHARGES BILL</h2>
                                                            </div>
                                                            <div class="print-logo-right">
                                                                <img src="/assets/logo/logo3.jpeg" alt="Ministry Logo" class="print-logo">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="print-content">
                                                        <!-- Date and Reference -->
                                                        <div class="print-date-ref">
                                                            <p><strong>Date:</strong> ${new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                                                            <p><strong>Bill Reference:</strong> <span class="ref-highlight">BB-${currentApplication.fileId}-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}</span></p>
                                                        </div>

                                                        <!-- Introduction -->
                                                        <div style="margin-bottom: 20px;">
                                                            <p>Dear Sir/Madam,</p>
                                                            <p>I am directed to inform you that the betterment charges for your primary application with the following particulars:</p>
                                                        </div>

                                                        <!-- Property Details -->
                                                        <div style="margin-bottom: 20px;">
                                                            <p><strong>File No:</strong> ${currentApplication.fileno}</p>
                                                            <p><strong>Name of Applicant:</strong> ${currentApplication.owner}</p>
                                                        </div>

                                                        <!-- Calculation Table -->
                                                        <table class="print-table">
                                                            <thead>
                                                                <tr>
                                                                    <th>Description</th>
                                                                    <th style="text-align: right;">Amount (₦)</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr>
                                                                    <td>Property Value</td>
                                                                    <td style="text-align: right;">${propertyValue.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Betterment Rate</td>
                                                                    <td style="text-align: right;">${bettermentRate}%</td>
                                                                </tr>
                                                                <tr class="total-row">
                                                                    <td><strong>Total Betterment Charges</strong></td>
                                                                    <td style="text-align: right;"><strong>${bettermentAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</strong></td>
                                                                </tr>
                                                            </tbody>
                                                        </table>

                                                        <!-- Footer Text -->
                                                        <div class="print-footer">
                                                            <p>You are hereby directed to settle this bill promptly in order to accelerate the processing of your application.</p>
                                                            <p><strong>Note:</strong> Documentary Payments can be made at the Checkout-Point and KANGIS Cashier's Office.</p>
                                                            <p>Thank you.</p>
                                                        </div>
                                                    </div>

                                                    <!-- Action Buttons (no-print) -->
                                                    <div class="no-print mt-6 flex gap-2">
                                                        <button onclick="printBettermentBill()" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                                            <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
                                                            Print Bill
                                                        </button>
                                                    </div>
                                                </div>
                                            `;
        }

        // Function to generate betterment receipt HTML with new formula structure
        function getBettermentReceiptHtmlNew(calculationData) {
            return `
                                                <div class="print-area">
                                                    <!-- Header with logos -->
                                                    <div class="print-header">
                                                        <div class="print-logos">
                                                            <div class="print-logo-left">
                                                                <img src="/assets/logo/logo1.jpg" alt="Kano State Logo" class="print-logo">
                                                            </div>
                                                            <div class="print-title">
                                                                <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                                                                <h2>BETTERMENT CHARGES BILL</h2>
                                                            </div>
                                                            <div class="print-logo-right">
                                                                <img src="/assets/logo/logo3.jpeg" alt="Ministry Logo" class="print-logo">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="print-content">
                                                        <!-- Date and Reference -->
                                                        <div class="print-date-ref">
                                                            <p><strong>Date:</strong> ${new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                                                            <p><strong>Bill Reference:</strong> <span class="ref-highlight">BB-${currentApplication.fileId}-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}</span></p>
                                                        </div>

                                                        <!-- Introduction -->
                                                        <div style="margin-bottom: 20px;">
                                                            <p>Dear Sir/Madam,</p>
                                                            <p>I am directed to inform you that the betterment charges for your primary application with the following particulars:</p>
                                                        </div>

                                                        <!-- Property Details -->
                                                        <div style="margin-bottom: 20px;">
                                                            <p><strong>File No:</strong> ${currentApplication.fileno}</p>
                                                            <p><strong>Name of Applicant:</strong> ${currentApplication.owner}</p>
                                                            <p><strong>Land Use:</strong> ${calculationData.landUse}</p>
                                                            <p><strong>Number of Units:</strong> ${calculationData.unitsCount}</p>
                                                        </div>

                                                        <!-- Calculation Table -->
                                                        <table class="print-table">
                                                            <thead>
                                                                <tr>
                                                                    <th>Description</th>
                                                                    <th style="text-align: right;">Value</th>
                                                                    <th style="text-align: right;">Amount (₦)</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr>
                                                                    <td>Base Fee (${calculationData.landUse})</td>
                                                                    <td style="text-align: right;">₦${calculationData.baseFee.toLocaleString()}</td>
                                                                    <td style="text-align: right;">-</td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Area per Unit</td>
                                                                    <td style="text-align: right;">${calculationData.areaPerUnit.toFixed(2)} m²</td>
                                                                    <td style="text-align: right;">-</td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Threshold</td>
                                                                    <td style="text-align: right;">${calculationData.threshold} m²</td>
                                                                    <td style="text-align: right;">-</td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Rate (per m² above threshold)</td>
                                                                    <td style="text-align: right;">₦${calculationData.rate}/m²</td>
                                                                    <td style="text-align: right;">-</td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Number of Units</td>
                                                                    <td style="text-align: right;">${calculationData.unitsCount}</td>
                                                                    <td style="text-align: right;">-</td>
                                                                </tr>
                                                                <tr class="total-row">
                                                                    <td><strong>Total Betterment Charges</strong></td>
                                                                    <td style="text-align: right;"><strong>-</strong></td>
                                                                    <td style="text-align: right;"><strong>${calculationData.bettermentAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</strong></td>
                                                                </tr>
                                                            </tbody>
                                                        </table>

                                                        <!-- Formula Explanation -->
                                                        <div style="margin: 20px 0;">
                                                            <p><strong>Calculation Formula:</strong></p>
                                                            <p style="font-style: italic;">Total Fee = Number of Units × (Base Fee + (Area per Unit - Threshold) × Rate)</p>
                                                            ${calculationData.areaPerUnit <= calculationData.threshold ?
                    `<p style="color: #059669;"><strong>Note:</strong> Since Area per Unit (${calculationData.areaPerUnit.toFixed(2)} m²) ≤ Threshold (${calculationData.threshold} m²), only the base fee applies.</p>` :
                    `<p style="color: #dc2626;"><strong>Note:</strong> Area per Unit (${calculationData.areaPerUnit.toFixed(2)} m²) exceeds Threshold (${calculationData.threshold} m²), additional charges apply.</p>`
                }
                                                        </div>

                                                        <!-- Footer Text -->
                                                        <div class="print-footer">
                                                            <p>You are hereby directed to settle this bill promptly in order to accelerate the processing of your application.</p>
                                                            <p><strong>Note:</strong> Documentary Payments can be made at the Checkout-Point and KANGIS Cashier's Office.</p>
                                                            <p>Thank you.</p>
                                                        </div>
                                                    </div>

                                                    <!-- Action Buttons (no-print) -->
                                                    <div class="no-print mt-6 flex gap-2">
                                                        <button onclick="printBettermentBill()" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                                            <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
                                                            Print Bill
                                                        </button>
                                                    </div>
                                                </div>
                                            `;
        }

        // Balance bill calculation
        $(document).on('click', '#calculate-balance-total-btn', function () {
            calculateBalanceTotal();
        });

        // Real-time calculation for balance bill
        $(document).on('input', '#balance-assignment-fee, #balance-bill-balance, #balance-recertification-fee, #balance-dev-charges', function () {
            calculateBalanceTotal();
        });

        /**
         * The ST bill's reference id.
         *
         * Deliberately derived only from the unit id and the bill date, with no
         * random component: the Deeds Bill Balance form recomputes this exact
         * string from final_bills so the two modules agree on one reference.
         * (It used to end in Math.random(), which produced a different id on
         * every render of the same bill and could never be matched.)
         */
        function stBillReference(fileId, isoDate) {
            var compact = (isoDate || new Date().toISOString().slice(0, 10)).replace(/-/g, '');
            return `ST-BILL-${fileId}-${compact}`;
        }

        // Keep the reference in step with the bill date it encodes.
        $(document).on('change', '#balance-bill-date', function () {
            if (currentApplication && currentApplication.fileId) {
                $('#balance-bill-ref-id').val(stBillReference(currentApplication.fileId, $(this).val()));
            }
        });

        /**
         * Read a balance field as a number.
         *
         * bill-form-populator.js writes these back with toLocaleString(), so a
         * saved bill reloads as "1,234.50" — and parseFloat("1,234.50") is 1234.
         * Strip the grouping separators and currency mark before parsing.
         */
        function readBalanceMoney(selector) {
            var raw = ($(selector).val() || '').toString().replace(/[₦,\s]/g, '');
            return parseFloat(raw) || 0;
        }

        function calculateBalanceTotal() {
            var assignmentFee = readBalanceMoney('#balance-assignment-fee');
            var billBalance = readBalanceMoney('#balance-bill-balance');
            var recertificationFee = readBalanceMoney('#balance-recertification-fee');
            var devCharges = readBalanceMoney('#balance-dev-charges');

            var totalAmount = assignmentFee + billBalance + recertificationFee + devCharges;

            $('#balance-calculated-total').text('₦' + totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2 }));
        }

        // Generate balance bill with saving functionality
        $(document).on('click', '#save-balance-bill-btn', function () {
            var assignmentFee = readBalanceMoney('#balance-assignment-fee');
            var billBalance = readBalanceMoney('#balance-bill-balance');
            var recertificationFee = readBalanceMoney('#balance-recertification-fee');
            var devCharges = readBalanceMoney('#balance-dev-charges');
            var totalAmount = assignmentFee + billBalance + recertificationFee + devCharges;
            var billDate = $('#balance-bill-date').val() || new Date().toISOString().slice(0, 10);

            if (totalAmount === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Amounts',
                    text: 'Please enter valid amounts for the fees.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            // Show loading
            Swal.fire({
                title: 'Generating Balance Bill...',
                text: 'Please wait while we generate your balance bill.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Persist to final_bills — this is the table bill-form-populator.js reads back
            // via /sub-final-bill/show/{id}, so the form can repopulate on the next visit.
            var billData = {
                sub_application_id: currentApplication.fileId,
                assignment_fee: assignmentFee,
                bill_balance: billBalance,
                recertification_fee: recertificationFee,
                dev_charges: devCharges,
                bill_date: billDate,
                bill_status: 'generated',
                _token: $('meta[name="csrf-token"]').attr('content')
            };

            fetch(`${window.location.origin}/sub-final-bill/save`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(billData)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Keep the local copy for the preview tab
                        generatedBills.balance = $.extend({}, billData, {
                            application_id: currentApplication.fileId,
                            total_amount: totalAmount,
                            bill_reference: $('#balance-bill-ref-id').val(),
                            generated_date: new Date().toISOString().slice(0, 10),
                            file_no: currentApplication.fileno,
                            owner_name: currentApplication.owner
                        });
                        localStorage.setItem('balance_bill_' + currentApplication.fileId, JSON.stringify(generatedBills.balance));

                        // Reflect the saved state the same way the populator would
                        if (typeof window.populateAllForms === 'function') {
                            window.populateAllForms();
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Balance Bill Generated & Saved!',
                            text: `Balance bill saved successfully! Total Amount: ₦${totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}`,
                            confirmButtonColor: '#10b981'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error Saving Bill',
                            text: data.message || 'Could not save the balance bill.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error saving balance bill:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error Saving Bill',
                        text: 'Could not reach the server. The bill was not saved.',
                        confirmButtonColor: '#ef4444'
                    });
                });
        });

        // Preview balance bill with SweetAlert
        $(document).on('click', '#preview-balance-bill-btn', function () {
            var assignmentFee = readBalanceMoney('#balance-assignment-fee');
            var billBalance = readBalanceMoney('#balance-bill-balance');
            var recertificationFee = readBalanceMoney('#balance-recertification-fee');
            var devCharges = readBalanceMoney('#balance-dev-charges');
            var totalAmount = assignmentFee + billBalance + recertificationFee + devCharges;
            var billDate = $('#balance-bill-date').val() || new Date().toISOString().slice(0, 10);

            if (totalAmount === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Calculation Found',
                    text: 'Please calculate the bill first.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            // Show loading
            Swal.fire({
                title: 'Generating Preview...',
                text: 'Please wait while we prepare your bill preview.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Simulate preview generation delay
            setTimeout(() => {
                // Generate preview HTML
                var previewHtml = getBalancePreviewHtml(assignmentFee, billBalance, recertificationFee, devCharges, totalAmount, billDate);

                $('#balance-preview-container').html(previewHtml);
                $('.balance-tab-button[data-tab="preview"]').click();

                // Reinitialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Preview Ready!',
                    text: 'Your bill preview has been generated successfully.',
                    confirmButtonColor: '#10b981'
                });
            }, 1500);
        });

        // Function to generate balance preview HTML with proper print structure
        function getBalancePreviewHtml(assignmentFee, billBalance, recertificationFee, devCharges, totalAmount, billDate) {
            return `
                                                <div class="print-area">
                                                    <!-- Header with logos -->
                                                    <div class="print-header">
                                                        <div class="print-logos">
                                                            <div class="print-logo-left">
                                                                <img src="/assets/logo/logo1.jpg" alt="Kano State Logo" class="print-logo">
                                                            </div>
                                                            <div class="print-title">
                                                                <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                                                                <h2>SECTIONAL TITLE BILL BALANCE</h2>
                                                            </div>
                                                            <div class="print-logo-right">
                                                                <img src="/assets/logo/logo3.jpeg" alt="Ministry Logo" class="print-logo">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="print-content">
                                                        <!-- Date and Reference -->
                                                        <div class="print-date-ref">
                                                            <p><strong>Date:</strong> ${new Date(billDate).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                                                            <p><strong>Bill Reference ID:</strong> <span class="ref-highlight">${$('#balance-bill-ref-id').val()}</span></p>
                                                        </div>

                                                        <!-- Introduction -->
                                                        <div style="margin-bottom: 20px;">
                                                            <p>Dear Sir/Madam,</p>
                                                            <p>I am directed to inform you that the total cost of processing of your application for sectional title with the following particulars:</p>
                                                        </div>

                                                        <!-- Property Details -->
                                                        <div style="margin-bottom: 20px;">
                                                            <p><strong>File No:</strong> ${currentApplication.fileno}</p>
                                                            <p><strong>Name of Section Owner:</strong> ${normalizeName(currentApplication.owner)}</p>
                                                        </div>

                                                        <!-- Fee Table -->
                                                        <table class="print-table">
                                                            <thead>
                                                                <tr>
                                                                    <th>Description</th>
                                                                    <th style="text-align: right;">Amount (₦)</th>
                                                                    <th style="text-align: right;">Dev. Charges (₦)</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr>
                                                                    <td>Assignment Fee</td>
                                                                    <td style="text-align: right;">${assignmentFee.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                                                                    <td style="text-align: right;">${devCharges.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Bill Balance</td>
                                                                    <td style="text-align: right;">${billBalance.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                                                                    <td style="text-align: right;">-</td>
                                                                </tr>
                                                                <tr>
                                                                    <td>Recertification Fee</td>
                                                                    <td style="text-align: right;">${recertificationFee.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                                                                    <td style="text-align: right;">-</td>
                                                                </tr>
                                                                <tr class="total-row">
                                                                    <td><strong>TOTAL</strong></td>
                                                                    <td style="text-align: right;"><strong>${totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}</strong></td>
                                                                    <td style="text-align: right;"><strong>-</strong></td>
                                                                </tr>
                                                            </tbody>
                                                        </table>

                                                        <!-- Footer Text -->
                                                        <div class="print-footer">
                                                            <p>You are hereby directed to settle this bill promptly in order to accelerate the processing of your application.</p>
                                                            <p><strong>Note:</strong> Documentary Payments can be made at the Checkout-Point and KANGIS Cashier's Office.</p>
                                                            <p>Thank you.</p>
                                                        </div>
                                                    </div>

                                                    <!-- Action Buttons (no-print) -->
                                                    <div class="no-print mt-6 flex gap-2">
                                                        <button onclick="printBalanceBill()" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                                            <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
                                                            Print Bill
                                                        </button>
                                                    </div>
                                                </div>
                                            `;
            }

            // Print functions
            function printBettermentBill() {
                var printContents = document.getElementById('betterment-receipt-container');
                if (!printContents || !printContents.innerHTML.trim() || printContents.querySelector('.text-center.p-8')) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Bill to Print',
                        text: 'Please generate a betterment bill first.',
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }

                var printWindow = window.open('', '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
                printWindow.document.write('<html><head><title>Betterment Bill</title>');
                printWindow.document.write('<style>');
                printWindow.document.write('body { font-family: "Times New Roman", serif; margin: 0.5in; color: black; }');
                printWindow.document.write('.print-area { width: 100%; }');
                printWindow.document.write('.print-logos { display: table; width: 100%; margin-bottom: 20px; table-layout: fixed; }');
                printWindow.document.write('.print-logo-left { display: table-cell; width: 100px; vertical-align: middle; text-align: left; }');
                printWindow.document.write('.print-logo-right { display: table-cell; width: 100px; vertical-align: middle; text-align: right; }');
                printWindow.document.write('.print-title { display: table-cell; vertical-align: middle; text-align: center; padding: 0 20px; }');
                printWindow.document.write('.print-logo { width: 80px; height: 80px; }');
                printWindow.document.write('.print-title h1 { font-size: 16pt; font-weight: bold; margin: 0; }');
                printWindow.document.write('.print-title h2 { font-size: 14pt; font-weight: bold; margin: 5px 0 0 0; }');
                printWindow.document.write('.print-table { width: 100%; border-collapse: collapse; margin: 20px 0; }');
                printWindow.document.write('.print-table th, .print-table td { border: 1px solid black; padding: 8px; text-align: left; }');
                printWindow.document.write('.print-table th { background-color: #f5f5f5; font-weight: bold; }');
                printWindow.document.write('.print-table .total-row { background-color: #f0f0f0; font-weight: bold; }');
                printWindow.document.write('.print-date-ref { text-align: right; margin-bottom: 20px; }');
                printWindow.document.write('.print-footer { margin-top: 30px; }');
                printWindow.document.write('.no-print { display: none !important; }');
                printWindow.document.write('</style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write(printContents.innerHTML);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.focus();
                setTimeout(function() { printWindow.print(); }, 500);
            }

            function printBalanceBill() {
                var printContents = document.getElementById('balance-preview-container');
                if (!printContents || !printContents.innerHTML.trim() || printContents.querySelector('.text-center.p-8')) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Bill to Print',
                        text: 'Please generate a balance bill first.',
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }

                var printWindow = window.open('', '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
                printWindow.document.write('<html><head><title>Balance Bill</title>');
                printWindow.document.write('<style>');
                printWindow.document.write('body { font-family: "Times New Roman", serif; margin: 0.5in; color: black; }');
                printWindow.document.write('.print-area { width: 100%; }');
                printWindow.document.write('.print-logos { display: table; width: 100%; margin-bottom: 20px; table-layout: fixed; }');
                printWindow.document.write('.print-logo-left { display: table-cell; width: 100px; vertical-align: middle; text-align: left; }');
                printWindow.document.write('.print-logo-right { display: table-cell; width: 100px; vertical-align: middle; text-align: right; }');
                printWindow.document.write('.print-title { display: table-cell; vertical-align: middle; text-align: center; padding: 0 20px; }');
                printWindow.document.write('.print-logo { width: 80px; height: 80px; }');
                printWindow.document.write('.print-title h1 { font-size: 16pt; font-weight: bold; margin: 0; }');
                printWindow.document.write('.print-title h2 { font-size: 14pt; font-weight: bold; margin: 5px 0 0 0; }');
                printWindow.document.write('.print-table { width: 100%; border-collapse: collapse; margin: 20px 0; }');
                printWindow.document.write('.print-table th, .print-table td { border: 1px solid black; padding: 8px; text-align: left; }');
                printWindow.document.write('.print-table th { background-color: #f5f5f5; font-weight: bold; }');
                printWindow.document.write('.print-table .total-row { background-color: #f0f0f0; font-weight: bold; }');
                printWindow.document.write('.print-date-ref { text-align: right; margin-bottom: 20px; }');
                printWindow.document.write('.print-footer { margin-top: 30px; }');
                printWindow.document.write('.no-print { display: none !important; }');
                printWindow.document.write('</style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write(printContents.innerHTML);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.focus();
                setTimeout(function() { printWindow.print(); }, 500);
            }

            /* ================================================================== */
            /* Conversion Betterment Bill — Tab switching, Calculate & Generate   */
            /* ================================================================== */

            // Conversion bill tab switching
            $(document).on('click', '.conv-bill-tab-button', function () {
                var tab = $(this).data('tab');
                $('.conv-bill-tab-button').removeClass('active');
                $(this).addClass('active');
                $('.conv-bill-tab-content').removeClass('active').addClass('bb-hidden').removeAttr('style');
                $('#conv-bill-' + tab + '-tab').removeClass('bb-hidden').removeClass('hidden').addClass('active');
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });

            // Show rate info when category is selected
            $(document).on('change', '#conv-category', function () {
                var $opt = $(this).find(':selected');
                if (!$opt.val()) {
                    $('#conv-rate-info').addClass('hidden');
                    return;
                }
                var structureTotal = Number(currentApplication.existingStructureTotal || 0);
                $('#conv-info-existing-structure').text('₦' + structureTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#conv-info-base-fee').text('₦' + parseFloat($opt.data('base-fee')).toLocaleString());
                $('#conv-info-base-area').text(parseFloat($opt.data('base-area')).toLocaleString() + ' m²');
                $('#conv-info-rate').text('₦' + parseFloat($opt.data('rate')).toFixed(2));
                $('#conv-rate-info').removeClass('hidden');
            });

            // Calculate conversion betterment fee via AJAX
            $(document).on('click', '#calculate-conv-btn', function () {
                var category = $('#conv-category').val();
                var area = parseFloat($('#conv-area').val());

                if (!category || !area || area <= 0) {
                    Swal.fire({ icon: 'warning', title: 'Missing Input', text: 'Please select a category. Area is taken from Existing Site Measurement Area Covered in JSI and must be greater than 0.', confirmButtonColor: '#3b82f6' });
                    return;
                }

                Swal.fire({ title: 'Calculating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                $.ajax({
                    url: '{{ route("physical-planning.conversion.bills.calculate") }}',
                    method: 'POST',
                    data: { category: category, area: area, _token: $('meta[name="csrf-token"]').attr('content') },
                    dataType: 'json',
                    success: function (resp) {
                        if (resp.success) {
                            var d = resp.data;
                            var fmt = function(v) { return parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2 }); };
                            $('#conv-betterment-amount').text('₦' + fmt(d.total_fee));
                            // Store calculation data on the form
                            $('#conv-betterment-form').data('calc', d);
                            $('#generate-conv-btn').prop('disabled', false);

                            Swal.fire({
                                icon: 'success',
                                title: 'Calculation Complete!',
                                html: '<div class="text-left">' +
                                    '<p><strong>Category:</strong> ' + d.category + '</p>' +
                                    '<p><strong>Base Fee:</strong> ₦' + fmt(d.base_fee) + '</p>' +
                                    '<p><strong>Base Area:</strong> ' + fmt(d.base_area) + ' m²</p>' +
                                    '<p><strong>Excess Area:</strong> ' + fmt(d.excess_area) + ' m²</p>' +
                                    '<p><strong>Excess Charge:</strong> ₦' + fmt(d.excess_charge) + '</p>' +
                                    '<hr class="my-2"><p><strong>Total Betterment Charges:</strong> ₦' + fmt(d.total_fee) + '</p>' +
                                    '</div>',
                                confirmButtonColor: '#10b981'
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: resp.message || 'Calculation failed.', confirmButtonColor: '#ef4444' });
                        }
                    },
                    error: function () {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.', confirmButtonColor: '#ef4444' });
                    }
                });
            });

            // Generate conversion betterment bill via AJAX
            $(document).on('click', '#generate-conv-btn', function () {
                var calc = $('#conv-betterment-form').data('calc');
                if (!calc || !calc.total_fee) {
                    Swal.fire({ icon: 'warning', title: 'Calculate First', text: 'Please calculate the betterment amount first.', confirmButtonColor: '#3b82f6' });
                    return;
                }

                Swal.fire({ title: 'Generating Bill...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                $.ajax({
                    url: '{{ route("physical-planning.conversion.bills.generate") }}',
                    method: 'POST',
                    data: {
                        jsi_report_id: currentApplication.fileId,
                        file_number: currentApplication.fileno,
                        category: $('#conv-category').val(),
                        area: $('#conv-area').val(),
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: 'json',
                    success: function (resp) {
                        if (resp.success) {
                            var bill = resp.bill;
                            var c = resp.calculation;
                            var fmt = function(v) { return parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2 }); };

                            // Build receipt HTML
                            var receiptHtml = getConversionReceiptHtml(bill, c);
                            $('#conv-receipt-container').html(receiptHtml);
                            // Switch to receipt tab
                            $('.conv-bill-tab-button[data-tab="receipt"]').click();

                            if (typeof lucide !== 'undefined') lucide.createIcons();

                            Swal.fire({
                                icon: 'success',
                                title: 'Bill Generated & Saved!',
                                text: 'Total: ₦' + fmt(c.total_fee) + ' — Ref: ' + (bill.ref_id || 'N/A'),
                                confirmButtonColor: '#10b981'
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: resp.message || 'Failed to generate bill.', confirmButtonColor: '#ef4444' });
                        }
                    },
                    error: function () {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.', confirmButtonColor: '#ef4444' });
                    }
                });
            });

            // Conversion betterment bill receipt HTML
            function getConversionReceiptHtml(bill, calc) {
                var fmt = function(v) { return parseFloat(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }); };
                var existingStructureRow = '';
                var structureDimensions = (calc && calc.existing_structure_dimensions)
                    ? String(calc.existing_structure_dimensions)
                    : '';

                var computeStructureTotalFromDimensions = function(raw) {
                    if (!raw) {
                        return null;
                    }

                    var parts = String(raw)
                        .split(/[xX×]/)
                        .map(function(part) { return parseFloat(String(part).trim()); })
                        .filter(function(num) { return Number.isFinite(num) && num >= 0; });

                    if (!parts.length) {
                        return null;
                    }

                    return parts.reduce(function(acc, val) { return acc * val; }, 1);
                };

                var structureTotal = computeStructureTotalFromDimensions(structureDimensions);
                if (!Number.isFinite(structureTotal) || structureTotal < 0) {
                    structureTotal = null;
                }

                if (structureDimensions || structureTotal !== null) {
                    var structureLabel = structureDimensions || 'N/A';
                    var structureValue = structureTotal !== null ? fmt(structureTotal) : 'N/A';
                    existingStructureRow = `
                                    <tr>
                                        <td>Existing Structure Dimensions${structureLabel !== 'N/A' ? ' (' + structureLabel + ')' : ''}</td>
                                        <td style="text-align:right;">${structureValue}</td>
                                        <td style="text-align:right;">—</td>
                                    </tr>
                    `;
                }

                return `
                    <div class="print-area">
                        <div class="print-header">
                            <div class="print-logos">
                                <div class="print-logo-left">
                                    <img src="/assets/logo/logo1.jpg" alt="Kano State Logo" class="print-logo">
                                </div>
                                <div class="print-title">
                                    <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                                    <h2>CONVERSION BETTERMENT CHARGES BILL</h2>
                                </div>
                                <div class="print-logo-right">
                                    <img src="/assets/logo/logo3.jpeg" alt="Ministry Logo" class="print-logo">
                                </div>
                            </div>
                        </div>
                        <div class="print-content">
                            <div class="print-date-ref">
                                <p><strong>Date:</strong> ${new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                                <p><strong>Bill Reference:</strong> <span class="ref-highlight">${bill.ref_id || 'N/A'}</span></p>
                            </div>
                            <div style="margin-bottom: 20px;">
                                <p>Dear Sir/Madam,</p>
                                <p>I am directed to inform you that the betterment charges for the conversion of your property with the following particulars:</p>
                            </div>
                            <div style="margin-bottom: 20px;">
                                <p><strong>File No:</strong> ${currentApplication.fileno}</p>
                                <p><strong>Name of Applicant:</strong> ${currentApplication.owner}</p>
                                <p><strong>Land Use Category:</strong> ${calc.category}</p>
                            </div>
                            <table class="print-table">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th style="text-align:right;">Value</th>
                                        <th style="text-align:right;">Amount (₦)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${existingStructureRow}
                                    <tr>
                                        <td>Base Fee (${calc.category})</td>
                                        <td style="text-align:right;">₦${fmt(calc.base_fee)}</td>
                                        <td style="text-align:right;">₦${fmt(calc.base_fee)}</td>
                                    </tr>
                                    <tr>
                                        <td>Base Area</td>
                                        <td style="text-align:right;">${fmt(calc.base_area)} m²</td>
                                        <td style="text-align:right;">—</td>
                                    </tr>
                                    <tr>
                                        <td>Total Area</td>
                                        <td style="text-align:right;">${fmt(bill.property_value || 0)} m²</td>
                                        <td style="text-align:right;">—</td>
                                    </tr>
                                    <tr>
                                        <td>Excess Area (${fmt(calc.excess_area)} m² × ₦${fmt(calc.rate_per_extra_sqm)}/m²)</td>
                                        <td style="text-align:right;">${fmt(calc.excess_area)} m²</td>
                                        <td style="text-align:right;">₦${fmt(calc.excess_charge)}</td>
                                    </tr>
                                    <tr class="total-row">
                                        <td><strong>Total Betterment Charges</strong></td>
                                        <td style="text-align:right;"><strong>—</strong></td>
                                        <td style="text-align:right;"><strong>₦${fmt(calc.total_fee)}</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div style="margin: 20px 0;">
                                <p><strong>Calculation Formula:</strong></p>
                                <p style="font-style:italic;">Total Fee = Base Fee + max(0, (Total Area − Base Area) × Rate per extra m²)</p>
                            </div>
                            <div class="print-footer">
                                <p>You are hereby directed to settle this bill promptly in order to accelerate the processing of your application.</p>
                                <p><strong>Note:</strong> Documentary Payments can be made at the Checkout-Point and KANGIS Cashier's Office.</p>
                                <p>Thank you.</p>
                            </div>
                        </div>
                        <div class="no-print mt-6 flex gap-2">
                            <button onclick="printConversionBill()" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <i data-lucide="printer" class="w-4 h-4 mr-2"></i>
                                Print Bill
                            </button>
                        </div>
                    </div>
                `;
            }

            // Print conversion bill
            function printConversionBill() {
                var printContents = document.getElementById('conv-receipt-container').innerHTML;
                var printWindow = window.open('', '_blank', 'width=800,height=600');
                printWindow.document.write('<html><head><title>Conversion Betterment Bill</title>');
                printWindow.document.write('<style>body{font-family:Arial,sans-serif;margin:20px}.print-header{text-align:center;margin-bottom:20px}.print-logos{display:flex;justify-content:space-between;align-items:center}.print-logo{max-height:60px}.print-title h1{font-size:14px;margin:5px 0}.print-title h2{font-size:12px;margin:5px 0}.print-content{margin:20px 0}.print-table{width:100%;border-collapse:collapse;margin:15px 0}.print-table th,.print-table td{border:1px solid #333;padding:8px;font-size:12px}.print-table th{background-color:#f0f0f0}.total-row{font-weight:bold;background-color:#f9f9f9}.print-footer{margin-top:20px;font-size:11px}.print-date-ref{margin-bottom:15px}.ref-highlight{font-weight:bold;color:#1a56db}.no-print{display:none}</style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write(printContents);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.focus();
                setTimeout(function() { printWindow.print(); printWindow.close(); }, 500);
            }

            // Currency input formatting functionality
            function initializeCurrencyInputs() {
                // Target all bill amount inputs
                const currencyInputs = [
                    '#betterment-property-value',
                    '#balance-assignment-fee',
                    '#balance-bill-balance',
                    '#balance-recertification-fee',
                    '#balance-dev-charges'
                ];

                currencyInputs.forEach(selector => {
                    $(document).off('focus input keydown', selector);

                    // Initialize with 0.00 if empty
                    $(document).on('focus', selector, function () {
                        const $input = $(this);
                        let value = $input.val().replace(/[^\d]/g, '');

                        if (!value || value === '0') {
                            $input.val('0.00');
                            $input.data('raw-value', '0');
                        } else {
                            // Store the raw numeric value
                            $input.data('raw-value', value);
                        }

                        // Select all text for easy replacement
                        this.select();
                    });

                    // Handle input formatting
                    $(document).on('input', selector, function (e) {
                        const $input = $(this);
                        let value = $input.val().replace(/[^\d]/g, '');

                        // If empty, set to 0
                        if (!value) {
                            value = '0';
                        }

                        // Store raw value
                        $input.data('raw-value', value);

                        // Format as currency (divide by 100 to get decimal places)
                        const numericValue = parseInt(value) / 100;
                        const formattedValue = numericValue.toFixed(2);

                        $input.val(formattedValue);
                    });

                    // Handle keydown for special keys
                    $(document).on('keydown', selector, function (e) {
                        const $input = $(this);

                        // Allow: backspace, delete, tab, escape, enter
                        if ($.inArray(e.keyCode, [46, 8, 9, 27, 13]) !== -1 ||
                            // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                            (e.keyCode === 65 && e.ctrlKey === true) ||
                            (e.keyCode === 67 && e.ctrlKey === true) ||
                            (e.keyCode === 86 && e.ctrlKey === true) ||
                            (e.keyCode === 88 && e.ctrlKey === true)) {
                            return;
                        }

                        // Ensure that it is a number and stop the keypress
                        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                            e.preventDefault();
                        }
                    });

                    // Handle blur to ensure proper formatting
                    $(document).on('blur', selector, function () {
                        const $input = $(this);
                        let value = $input.val().replace(/[^\d.]/g, '');

                        if (!value || value === '0' || value === '0.00') {
                            $input.val('0.00');
                            $input.data('raw-value', '0');
                        } else {
                            // Ensure proper decimal formatting
                            const numericValue = parseFloat(value);
                            if (!isNaN(numericValue)) {
                                $input.val(numericValue.toFixed(2));
                                $input.data('raw-value', (numericValue * 100).toString());
                            }
                        }
                    });
                });
            }

            // Initialize currency inputs when document is ready
            $(document).ready(function () {
                initializeCurrencyInputs();
            });

            // Re-initialize currency inputs when new content is loaded
            $(document).on('DOMNodeInserted', function () {
                setTimeout(initializeCurrencyInputs, 100);
            });
        </script>
        <script src="{{ asset('js/application-details-fetcher.js') }}"></script>
        <script src="{{ asset('js/bill-form-populator.js') }}"></script>
        <script src="{{ asset('js/test-routes.js') }}"></script>
@endsection
{{--
// Additional function to load saved bills when application is selected
function loadSavedBillsForApplication() {
if (typeof checkForSavedBettermentBill === 'function') {
checkForSavedBettermentBill();
}
if (typeof checkForSavedBalanceBill === 'function') {
checkForSavedBalanceBill();
}
} --}}