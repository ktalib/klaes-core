@extends('layouts.app')

@section('page-title')
    {{ isset($report) ? __('Edit Valuation Report') : __('Generate Valuation Report') }}
@endsection

@section('content')

    <style>
        /* Screen Styles (unchanged mostly, just for viewing) */
        .document-body {
            width: 100%;
            max-width: 900px;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            padding: 50px 60px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        /* PRINT STYLES - EXACT MATCH TO HTML TEMPLATE */
        @media print {
            @page {
                margin: 10mm 8mm;
                size: A4 portrait;
            }

            body {
                margin: 0;
                padding: 0;
                font-size: 12pt !important;
                line-height: 1.35 !important;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
            }

            .no-print {
                display: none !important;
            }

            /* Reset container */
            .page-container,
            .main-content,
            .w-full,
            .h-full,
            .overflow-y-auto,
            .max-w-\[210mm\] {
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
                position: static !important;
                background: white !important;
            }

            .document-body {
                width: auto !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }

            /* Typography Overrides */
            h1 {
                font-size: 24pt !important;
                margin: 10px 0 20px !important;
                text-align: center !important;
                font-weight: 700 !important;
                text-decoration: underline !important;
            }

            .section-title {
                font-size: 10pt !important;
                font-weight: bold !important;
                margin: 14px 0 6px !important;
                border-bottom: 2.5px solid #000 !important;
                padding-bottom: 4px !important;
                text-transform: uppercase !important;
            }

            .label {
                width: 160px !important;
                display: inline-block !important;
                vertical-align: top !important;
                font-weight: 700 !important;
                font-size: 10pt !important;
            }

            /* Input Styles */
            input,
            select,
            textarea {
                border: none !important;
                border-bottom: 2px solid #000 !important;
                background: transparent !important;
                font-size: 14pt !important;
                padding: 3px 0 !important;
                margin: 0 !important;
                font-weight: 500 !important;
                width: 100% !important;
                outline: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                appearance: none !important;
            }

            textarea {
                height: 60px !important;
                resize: none !important;
                min-height: 50px !important;
            }

            /* Specific Adjustments */
            .field-row {
                margin: 6px 0 !important;
            }

            /* Hide placeholders in print */
            ::placeholder {
                color: transparent !important;
            }

            /* Hide empty signature lines or helpers */
            .print\:hidden {
                display: none !important;
            }

            .print\:block {
                display: block !important;
            }

            .print\:grid {
                display: grid !important;
            }

            .print\:inline-block {
                display: inline-block !important;
            }

            .sign-block div {
                margin-top: 25px !important;
            }

            .sign-line {
                border-bottom: 2px solid #000 !important;
                height: 20px !important;
            }
        }
    </style>


    <!-- Main Scroll Container -->
    <div class="w-full h-full bg-slate-100 py-8 overflow-y-auto">
        <div class="max-w-[210mm] mx-auto px-4">

            <!-- EXCLUDED FROM PRINT: Header & Control Area -->
            <div class="no-print mb-8 space-y-4">
                <div class="flex justify-between items-center">
                    <a href="{{ route('valuation-reports.index') }}"
                        class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-blue-600 transition group">
                        <i data-lucide="chevron-left" class="h-4 w-4 transform group-hover:-translate-x-1 transition"></i>
                        Back
                    </a>
                    <!-- <div class="flex items-center gap-2 bg-white px-3 py-1.5 rounded-full border border-slate-200">
                                                                            <div class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                                                                            <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Drafting
                                                                                Document</span>
                                                                        </div> -->
                </div>
                <h1 class="text-3xl font-black text-center underline mb-8 uppercase">VALUATION REPORT</h1>
                <!-- FILE NUMBER PICKER: Top level, excluded from report body -->
                <div class="max-w-md mx-auto">
                    <div class="bg-white border border-slate-200 rounded-xl p-1 shadow-sm flex items-center gap-2">
                        <div
                            class="w-8 h-8 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-600">
                            <i data-lucide="search" class="h-4 w-4"></i>
                        </div>
                        <div class="flex-1 flex gap-2 items-center">
                            <div class="flex-1">
                                <input type="text" name="file_number_display" id="file_number" readonly required
                                    value="{{ old('file_number', $report->file_number ?? '') }}"
                                    placeholder="NO FILE SELECTED"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-slate-900 font-bold font-mono placeholder:text-slate-500 text-sm outline-none focus:border-slate-400 transition">
                            </div>
                            <button type="button" id="select-fileno-btn"
                                class="px-4 py-1.5 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition flex items-center gap-2 shadow-sm text-xs">
                                <i data-lucide="search" class="h-3.5 w-3.5"></i>
                                Select File Number
                            </button>
                        </div>
                    </div>

                </div>
            </div>
            <!-- THE DOCUMENT BODY -->
            <form id="valuation-form" class="document-body">
                @csrf
                @if(isset($report))
                    @method('PUT')
                    <input type="hidden" name="id" id="report_id" value="{{ $report->id }}">
                @else
                    <input type="hidden" name="id" id="report_id" value="">
                @endif

                <!-- Hidden real file_number input for form data -->
                <input type="hidden" name="file_number" id="file_number_hidden"
                    value="{{ old('file_number', $report->file_number ?? '') }}">



                <div class="space-y-6 text-sm leading-relaxed font-medium print:space-y-0">
                    <!-- Dates & Purpose -->
                    <div class="space-y-4 print:space-y-0">
                        <div class="flex items-end gap-2 field-row">
                            <span class="label font-bold whitespace-nowrap w-64 flex-shrink-0">1. INSPECTION DATE:</span>
                            <div class="flex-1 print:inline-block print:w-[calc(100%-270px)]">
                                <input type="date" name="inspection_date" id="inspection_date" required
                                    value="{{ old('inspection_date', isset($report) && $report->inspection_date ? \Carbon\Carbon::parse($report->inspection_date)->format('Y-m-d') : date('Y-m-d')) }}"
                                    class="w-full bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 py-0.5 font-bold transition placeholder-transparent">
                            </div>
                        </div>
                        <div class="flex items-end gap-2 field-row">
                            <span class="label font-bold whitespace-nowrap w-64 flex-shrink-0">2. PURPOSE OF
                                INSPECTION:</span>
                            <div class="flex-1 print:inline-block print:w-[calc(100%-270px)]">
                                <input type="text" name="valuation_purpose" id="valuation_purpose"
                                    value="{{ old('valuation_purpose', $report->valuation_purpose ?? '') }}"
                                    class="w-full bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 py-0.5 font-bold   transition">
                            </div>
                        </div>
                    </div>

                    <!-- 3. OWNER & PROPERTY DETAILS -->
                    <div>
                        <div class="section-title font-bold border-b-2 border-black pb-1 mb-4 uppercase text-sm">3. OWNER & PROPERTY DETAILS
                        </div>
                        <div class="space-y-4 print:space-y-0 print:block">
                            <!-- Use Print specific structure which is cleaner -->

                            <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                <span class="label font-bold w-64 flex-shrink-0">a) Full Name</span>
                                <input type="text" name="full_name" id="full_name" required
                                    value="{{ old('full_name', $report->full_name ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 font-bold transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                            <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                <span class="label font-bold w-64 flex-shrink-0">b) Address</span>
                                <input type="text" name="address" id="address" required
                                    value="{{ old('address', $report->address ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 font-bold transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                            <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                <span class="label font-bold w-64 flex-shrink-0">i) Phone Number</span>
                                <input type="tel" name="phone" id="phone" required inputmode="tel" maxlength="30"
                                    value="{{ old('phone', $report->phone ?? '') }}"
                                    placeholder="e.g. 08031234567"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 font-bold transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>

                            <!-- Grid for screen, block lines for print -->
                            <div class="grid grid-cols-2 gap-x-8 gap-y-4 print:block print:gap-0 print:space-y-0">
                                <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                    <span class="label w-32 flex-shrink-0">c) Plot/Prop No.</span>
                                    <input type="text" name="property_no" id="property_no"
                                        value="{{ old('property_no', $report->property_no ?? '') }}"
                                        class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-170px)] print:inline-block">
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                    <span class="label w-32 flex-shrink-0">d) Street Name</span>
                                    <select name="street_name" id="street_name" required
                                        class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-170px)] print:inline-block">
                                        <option value="">Select Street Name</option>
                                        @foreach($streetNames as $street)
                                            <option value="{{ $street->name }}" {{ (old('street_name', $report->street_name ?? '') == $street->name) ? 'selected' : '' }}>{{ $street->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                    <span class="label w-32 flex-shrink-0">e) Estate/Quarters</span>
                                    <input type="text" name="estate_quarters" id="estate_quarters"
                                        value="{{ old('estate_quarters', $report->estate_quarters ?? '') }}"
                                        class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-170px)] print:inline-block">
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                    <span class="label w-32 flex-shrink-0">f) Town / City</span>
                                    <input type="text" name="town_city" id="town_city" required
                                        value="{{ old('town_city', $report->town_city ?? '') }}"
                                        class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-170px)] print:inline-block">
                                </div>
                                    <div class="flex items-end gap-2 col-span-2 print:col-span-1 print:block print:mb-[2px]">
                                        <span class="label w-32 flex-shrink-0 font-bold">g) Local Govt Area</span>
                                        <div class="flex-1 print:inline-block print:w-[calc(100%-170px)]">
                                            <select name="lga" id="lga" required
                                                class="w-full bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                                <option value="">SELECT LGA</option>
                                                @foreach($lgas as $lga)
                                                    <option value="{{ $lga->LGAName }}" {{ (old('lga', $report->lga ?? '') == $lga->LGAName) ? 'selected' : '' }}>{{ $lga->LGAName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                        </div>
                    </div>

                    <!-- 4. PROPERTY DETAILS -->
                    <div>
                        <div class="section-title font-bold border-b-2 border-black pb-1 mb-4 uppercase text-sm">4. PROPERTY
                            DETAILS
                        </div>
                        <div class="space-y-4 print:space-y-0 print:block mb-4">
                            <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                <span class="label w-48 flex-shrink-0">Property</span>
                                <input type="text" name="property_desc" id="property_desc"
                                    value="{{ old('property_desc', $report->property_desc ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 font-bold transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-8 gap-y-4 print:block print:gap-0 print:mb-2">
                            <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                <span class="label w-48 flex-shrink-0">a) Property Type</span>
                                <input type="text" name="property_type" id="property_type" required
                                    value="{{ old('property_type', $report->property_type ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 uppercase transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                            <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                <span class="label w-48 flex-shrink-0">b) Total Number of Blocks</span>
                                <input type="text" name="num_blocks" id="num_blocks"
                                    value="{{ old('num_blocks', $report->num_blocks ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                            <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                <span class="label w-48 flex-shrink-0">c) Storey Height</span>
                                <input type="text" name="storey_height" id="storey_height"
                                    value="{{ old('storey_height', $report->storey_height ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                            <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                <span class="label w-48 flex-shrink-0">d) Units per Block</span>
                                <input type="text" name="units_per_block" id="units_per_block"
                                    value="{{ old('units_per_block', $report->units_per_block ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                            <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                <span class="label w-48 flex-shrink-0">e) Total Bedroom Units</span>
                                <input type="text" name="bedroom_units" id="bedroom_units"
                                    value="{{ old('bedroom_units', $report->bedroom_units ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                            <div class="flex items-end gap-2 col-span-2 print:block print:mb-[2px]">
                                <span class="label w-48 flex-shrink-0">f) Ancillary Accommodation</span>
                                <input type="text" name="ancillary_bldgs" id="ancillary_bldgs"
                                    value="{{ old('ancillary_bldgs', $report->ancillary_bldgs ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 font-bold transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                            <div class="flex items-end gap-2 col-span-2 print:block print:mb-[2px]">
                                <span class="label w-48 flex-shrink-0">g) Use</span>
                                <input type="text" name="use" id="use"
                                    value="{{ old('use', $report->use ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 font-bold transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                            <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                <span class="label w-48 flex-shrink-0">h) Current Use</span>
                                <input type="text" name="current_use" id="current_use" required
                                    value="{{ old('current_use', $report->current_use ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                            <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                <span class="label w-48 flex-shrink-0">h) Land Use Purposes</span>
                                <input type="text" name="land_use_purpose" id="land_use_purpose"
                                    value="{{ old('land_use_purpose', $report->land_use_purpose ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION B - STRUCTURAL DETAILS -->
                    <div>
                        <div class="section-title font-bold border-b-2 border-black pb-1 mb-4 uppercase text-sm">SECTION B - STRUCTURAL DETAILS
                        </div>
                        <div class="grid grid-cols-2 gap-x-12 print:gap-x-8 print:gap-y-4">
                            <!-- Column 1 -->
                            <div class="space-y-4 print:space-y-0">
                                <div>
                                    <div
                                        class="font-bold text-slate-700 text-xs mb-1 print:text-[10pt] print:text-black print:mb-2 uppercase underline">
                                        6. FOUNDATION</div>
                                    <div class="flex gap-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mb-1">
                                        <span class="font-bold print:font-bold">a) Type:</span>
                                        <input type="text" name="foundation_type"
                                            value="{{ old('foundation_type', $report->foundation_type ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                    <div
                                        class="flex gap-2 mt-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mt-0">
                                        <span class="font-bold print:font-bold">b) Stage:</span>
                                        <input type="text" name="foundation_stage"
                                            value="{{ old('foundation_stage', $report->foundation_stage ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                </div>
                                <div class="print:mt-4">
                                    <div
                                        class="font-bold text-slate-700 text-xs mb-1 print:text-[10pt] print:text-black print:mb-2 uppercase underline">
                                        7. FLOOR</div>
                                    <div class="flex gap-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mb-1">
                                        <span class="font-bold print:font-bold">a) Type:</span>
                                        <input type="text" name="floor_type"
                                            value="{{ old('floor_type', $report->floor_type ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                    <div
                                        class="flex gap-2 mt-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mt-0">
                                        <span class="font-bold print:font-bold">b) Finishing:</span>
                                        <input type="text" name="floor_finish"
                                            value="{{ old('floor_finish', $report->floor_finish ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                </div>
                                <div class="print:mt-4">
                                    <div
                                        class="font-bold text-slate-700 text-xs mb-1 print:text-[10pt] print:text-black print:mb-2 uppercase underline">
                                        8. MAIN WALLS</div>
                                    <div class="flex gap-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mb-1">
                                        <span class="font-bold print:font-bold">a) Type:</span>
                                        <input type="text" name="walls_type"
                                            value="{{ old('walls_type', $report->walls_type ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                    <div
                                        class="flex gap-2 mt-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mt-0">
                                        <span class="font-bold print:font-bold">b) Stage:</span>
                                        <input type="text" name="walls_stage"
                                            value="{{ old('walls_stage', $report->walls_stage ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                    <div
                                        class="flex gap-2 mt-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mt-0">
                                        <span class="font-bold print:font-bold">c) Finishing:</span>
                                        <input type="text" name="walls_finish"
                                            value="{{ old('walls_finish', $report->walls_finish ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                </div>
                                <div class="print:mt-4">
                                    <div
                                        class="font-bold text-slate-700 text-xs mb-1 print:text-[10pt] print:text-black print:mb-2 uppercase underline">
                                        9. BOUNDARY WALLS</div>
                                    <div class="flex gap-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mb-1">
                                        <span class="font-bold print:font-bold">a) Type:</span>
                                        <input type="text" name="boundary_wall_type"
                                            value="{{ old('boundary_wall_type', $report->boundary_wall_type ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                    <div
                                        class="flex gap-2 mt-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mt-0">
                                        <span class="font-bold print:font-bold">b) Height:</span>
                                        <input type="text" name="boundary_wall_height"
                                            value="{{ old('boundary_wall_height', $report->boundary_wall_height ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                    <div
                                        class="flex gap-2 mt-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mt-0">
                                        <span class="font-bold print:font-bold">c) Finishing:</span>
                                        <input type="text" name="boundary_wall_finish"
                                            value="{{ old('boundary_wall_finish', $report->boundary_wall_finish ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                </div>
                            </div>
                            <!-- Column 2 -->
                            <div class="space-y-4 print:space-y-0">
                                <div>
                                    <div
                                        class="font-bold text-slate-700 text-xs mb-1 print:text-[10pt] print:text-black print:mb-2 uppercase underline">
                                        10. ROOF</div>
                                    <div class="flex gap-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mb-1">
                                        <span class="font-bold print:font-bold">a) Type:</span>
                                        <input type="text" name="roof_type"
                                            value="{{ old('roof_type', $report->roof_type ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                    <div
                                        class="flex gap-2 mt-2 print:grid print:grid-cols-[100px_1fr] print:gap-2 print:mt-0">
                                        <span class="font-bold print:font-bold">b) Material:</span>
                                        <input type="text" name="roof_material"
                                            value="{{ old('roof_material', $report->roof_material ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                    </div>
                                </div>
                                <div class="print:mt-4">
                                    <div
                                        class="font-bold text-slate-700 text-xs mb-1 print:text-[10pt] print:text-black print:mb-2 uppercase underline">
                                        11. CEILING</div>
                                    <input type="text" name="ceiling_detail"
                                        value="{{ old('ceiling_detail', $report->ceiling_detail ?? '') }}"
                                        class="w-full bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:mt-2">
                                </div>
                                <div class="print:mt-4">
                                    <div
                                        class="font-bold text-slate-700 text-xs mb-1 print:text-[10pt] print:text-black print:mb-2 uppercase underline">
                                        12. DOORS</div>
                                    <div class="flex gap-2 print:grid print:grid-cols-[120px_1fr] print:gap-2 print:mb-1">
                                        <span class="font-bold print:font-bold">a) Gate:</span>
                                        <input type="text" name="gate_type"
                                            value="{{ old('gate_type', $report->gate_type ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 text-xs transition">
                                    </div>
                                    <div class="flex gap-2 print:grid print:grid-cols-[120px_1fr] print:gap-2 print:mb-1">
                                        <span class="font-bold print:font-bold">b) External:</span>
                                        <input type="text" name="ext_doors"
                                            value="{{ old('ext_doors', $report->ext_doors ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 text-xs transition">
                                    </div>
                                    <div class="flex gap-2 print:grid print:grid-cols-[120px_1fr] print:gap-2 print:mb-1">
                                        <span class="font-bold print:font-bold">c) Internal:</span>
                                        <input type="text" name="int_doors"
                                            value="{{ old('int_doors', $report->int_doors ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 text-xs transition">
                                    </div>
                                    <div class="flex gap-2 print:grid print:grid-cols-[120px_1fr] print:gap-2 print:mb-1">
                                        <span class="font-bold print:font-bold">d) Proof:</span>
                                        <input type="text" name="door_security_proof"
                                            value="{{ old('door_security_proof', $report->door_security_proof ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 text-xs transition">
                                    </div>
                                </div>
                                <div class="print:mt-4">
                                    <div
                                        class="font-bold text-slate-700 text-xs mb-1 print:text-[10pt] print:text-black print:mb-2 uppercase underline">
                                        13. WINDOWS</div>
                                    <div class="flex gap-2 print:grid print:grid-cols-[120px_1fr] print:gap-2 print:mb-1">
                                        <span class="font-bold print:font-bold">a) Type:</span>
                                        <input type="text" name="window_type"
                                            value="{{ old('window_type', $report->window_type ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 text-xs transition">
                                    </div>
                                    <div
                                        class="flex gap-2 mt-2 print:grid print:grid-cols-[120px_1fr] print:gap-2 print:mt-0">
                                        <span class="font-bold print:font-bold">b) Proof:</span>
                                        <input type="text" name="burglary_proof"
                                            value="{{ old('burglary_proof', $report->burglary_proof ?? '') }}"
                                            class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 text-xs transition">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION C - SERVICES & UTILITIES -->
                    <div>
                        <div class="section-title font-bold border-b-2 border-black pb-1 mb-4 uppercase text-sm">SECTION C - SERVICES
                            & UTILITIES
                        </div>
                        <div class="grid grid-cols-2 gap-x-12 gap-y-2 print:gap-x-3 print:gap-y-1">
                            <div>
                                <div class="font-bold mb-2 text-[10pt] uppercase">14. Toilet: Bathroom Facility</div>
                                <div class="flex gap-2 print:block">
                                    <input type="text" name="toilet_bath"
                                        value="{{ old('toilet_bath', $report->toilet_bath ?? '') }}"
                                        class="flex-1 w-full bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                </div>
                            </div>
                            <div>
                                <div class="font-bold mb-2 text-[10pt] uppercase">15. Pipe Borne Water</div>
                                <div class="flex gap-2 print:block">
                                    <input type="text" name="water_supply"
                                        value="{{ old('water_supply', $report->water_supply ?? '') }}"
                                        class="flex-1 w-full bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                </div>
                            </div>
                            <div>
                                <div class="font-bold mb-2 text-[10pt] uppercase">16. Electricity</div>
                                <div class="flex gap-2 print:block">
                                    <input type="text" name="electricity"
                                        value="{{ old('electricity', $report->electricity ?? '') }}"
                                        class="flex-1 w-full bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                </div>
                            </div>
                            <div>
                                <div class="font-bold mb-2 text-[10pt] uppercase">17. Drainage System</div>
                                <div class="flex gap-2 print:block">
                                    <input type="text" name="drainage"
                                        value="{{ old('drainage', $report->drainage ?? '') }}"
                                        class="flex-1 w-full bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION D - FITTINGS & FIXTURES -->
                    <div>
                        <div class="section-title font-bold border-b-2 border-black pb-1 mb-2 uppercase text-sm">SECTION D - FITTINGS & FIXTURES</div>
                        <div>
                            <div class="font-bold mb-2 text-[10pt]">18. FITTING & FIXTURE</div>
                            <div class="grid grid-cols-2 gap-x-8 gap-y-2 print:block print:gap-0">
                                <div class="flex items-end gap-2 print:block print:mb-[1px]">
                                    <span class="label w-40 flex-shrink-0">a) Sitting Room</span>
                                    <input type="text" name="fittings_sitting" value="{{ old('fittings_sitting', $report->fittings_sitting ?? '') }}" class="flex-1 bg-transparent border-b border-black rounded-none outline-none px-2 transition print:w-[calc(100%-200px)] print:inline-block">
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[1px]">
                                    <span class="label w-40 flex-shrink-0">b) Dining</span>
                                    <input type="text" name="fittings_dining" value="{{ old('fittings_dining', $report->fittings_dining ?? '') }}" class="flex-1 bg-transparent border-b border-black rounded-none outline-none px-2 transition print:w-[calc(100%-200px)] print:inline-block">
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[1px]">
                                    <span class="label w-40 flex-shrink-0">c) Bed</span>
                                    <input type="text" name="fittings_bedroom" value="{{ old('fittings_bedroom', $report->fittings_bedroom ?? '') }}" class="flex-1 bg-transparent border-b border-black rounded-none outline-none px-2 transition print:w-[calc(100%-200px)] print:inline-block">
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[1px]">
                                    <span class="label w-40 flex-shrink-0">d) Toilet</span>
                                    <input type="text" name="fittings_toilet" value="{{ old('fittings_toilet', $report->fittings_toilet ?? '') }}" class="flex-1 bg-transparent border-b border-black rounded-none outline-none px-2 transition print:w-[calc(100%-200px)] print:inline-block">
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[1px]">
                                    <span class="label w-40 flex-shrink-0">e) Bath</span>
                                    <input type="text" name="fittings_bath" value="{{ old('fittings_bath', $report->fittings_bath ?? '') }}" class="flex-1 bg-transparent border-b border-black rounded-none outline-none px-2 transition print:w-[calc(100%-200px)] print:inline-block">
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[1px]">
                                    <span class="label w-40 flex-shrink-0">f) Kitchen</span>
                                    <input type="text" name="fittings_kitchen" value="{{ old('fittings_kitchen', $report->fittings_kitchen ?? '') }}" class="flex-1 bg-transparent border-b border-black rounded-none outline-none px-2 transition print:w-[calc(100%-200px)] print:inline-block">
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[1px]">
                                    <span class="label w-40 flex-shrink-0">g) Store</span>
                                    <input type="text" name="fittings_store" value="{{ old('fittings_store', $report->fittings_store ?? '') }}" class="flex-1 bg-transparent border-b border-black rounded-none outline-none px-2 transition print:w-[calc(100%-200px)] print:inline-block">
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[1px]">
                                    <span class="label w-40 flex-shrink-0">h) B/Q</span>
                                    <input type="text" name="fittings_bq" value="{{ old('fittings_bq', $report->fittings_bq ?? '') }}" class="flex-1 bg-transparent border-b border-black rounded-none outline-none px-2 transition print:w-[calc(100%-200px)] print:inline-block">
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="font-bold mb-2 text-[10pt]">19. OTHER RELATED INFORMATION / REMARKS</div>
                            <textarea name="other_info" rows="2" placeholder="..."
                                class="w-full bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 resize-none transition tracking-wide text-[12pt]">{{ old('other_info', $report->other_info ?? ($report->remarks ?? '')) }}</textarea>
                        </div>
                    </div>

                    <!-- SECTION E - VALUATION DETAILS -->
                    <div>
                        <div class="section-title font-bold border-b-2 border-black pb-1 mb-4 uppercase text-sm print:mt-6">
                            SECTION E - VALUATION DETAILS
                        </div>
                        <div class="space-y-4 print:space-y-0 print:block">
                            <div class="flex items-end gap-2 text-xs print:block print:mb-[2px]">
                                <span class="label w-64 shrink-0 font-bold">20. Basis of Valuation:</span>
                                <input type="text" name="valuation_basis"
                                    value="{{ old('valuation_basis', $report->valuation_basis ?? '') }}"
                                    class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-270px)] print:inline-block">
                            </div>
                            <div class="space-y-2 print:space-y-0 print:block">
                                <div class="font-bold uppercase text-[10pt] mb-2">21. Opinion/Recommendation</div>
                                <div class="flex items-end gap-2 print:block print:mb-[2px] ml-4">
                                    <span class="label w-60 shrink-0 font-bold">a) Amount in Words (Naira)</span>
                                    <input type="text" name="value_words" id="value_words" required
                                        value="{{ old('value_words', $report->value_words ?? '') }}"
                                        class="flex-1 bg-transparent border-b-2 border-black rounded-none focus:border-blue-600 outline-none px-2 font-black uppercase transition text-base print:w-[calc(100%-270px)] print:inline-block">
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[2px] ml-4 mt-2">
                                    <span class="label w-60 shrink-0 font-bold">b) N;</span>
                                    <div class="flex-1 flex items-end border-b-2 border-black print:inline-block print:w-[calc(100%-270px)]">
                                        <input type="text" name="value_figures" id="value_figures" required
                                            value="{{ old('value_figures', $report->value_figures ?? '') }}" placeholder="0.00"
                                            class="flex-1 bg-transparent outline-none px-1 font-black text-blue-700 transition text-2xl tracking-tighter w-full">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION F - CERTIFICATION -->
                    <div class="grid grid-cols-2 gap-12 pt-4 print:block print:pt-0">
                        <div class="space-y-4 print:space-y-0 col-span-2">
                            <div class="section-title print:border-b-[2.5px] print:border-black print:pb-[4px] print:mb-0 print:mt-6 print:font-bold">
                                22. SURVEYOR
                            </div>
                            <div class="grid grid-cols-1 gap-4 ml-4">
                                <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                    <span class="label w-48 font-bold">a) Name:</span>
                                    <select name="surveyor_name" id="surveyor_name" required
                                        class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 font-bold transition print:w-[calc(100%-250px)] print:inline-block">
                                        <option value="">Select Surveyor</option>
                                        @foreach($valuationOfficers as $officer)
                                            <option value="{{ $officer->name }}" data-rank="{{ $officer->rank }}"
                                                @selected(old('surveyor_name', $report->surveyor_name ?? '') === $officer->name)>
                                                {{ $officer->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                    <span class="label w-48 font-bold">b) Rank/Reg:</span>
                                    <input type="text" name="surveyor_rank" id="surveyor_rank"
                                        value="{{ old('surveyor_rank', $report->surveyor_rank ?? '') }}"
                                        class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 transition print:w-[calc(100%-250px)] print:inline-block">
                                </div>
                                <div class="flex items-end gap-2 print:block print:mb-[2px]">
                                    <span class="label w-48 font-bold">c) Chief Valuer:</span>
                                    <select name="chief_valuer" id="chief_valuer"
                                        class="flex-1 bg-transparent border-b border-black rounded-none focus:border-blue-600 outline-none px-2 font-bold transition print:w-[calc(100%-250px)] print:inline-block">
                                        <option value="">Select O/C Valuation</option>
                                        @foreach($valuationOfficers as $officer)
                                            <option value="{{ $officer->name }}"
                                                @selected(old('chief_valuer', $report->chief_valuer ?? '') === $officer->name)>
                                                {{ $officer->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Signature Block (Print Only) -->
                    <div class="hidden print:grid print:grid-cols-2 print:gap-8 print:mt-10 sign-block">
                        <div>
                            <div class="mb-3 font-bold text-base print:invisible">.</div>
                        </div>
                        <div class="text-right">
                            <div class="mb-3 font-bold text-base">SURVEYOR'S SIGNATURE & DATE</div>
                            <div class="sign-line w-72 inline-block"></div>
                        </div>
                    </div>

                    <!-- SUBMIT BUTTONS (Excluded from print) -->
                    <div class="no-print pt-20 flex justify-end gap-5">
                        <button type="button" onclick="window.history.back()"
                            class="px-10 py-4 bg-white text-slate-400 font-bold rounded-2xl border border-slate-200 hover:bg-slate-50 hover:text-slate-600 transition shadow-sm">
                            Cancel
                        </button>
                        <button type="submit" id="submit-btn"
                            class="px-16 py-4 bg-blue-600 text-white font-black rounded-2xl shadow-2xl shadow-blue-200 hover:bg-black transition transform active:scale-[0.98] flex items-center gap-3">
                            <i data-lucide="check-circle" class="h-6 w-6"></i>
                            GENERATE REPORT
                        </button>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const surveyorSelect = document.getElementById('surveyor_name');
                        const rankInput = document.getElementById('surveyor_rank');

                        if (!surveyorSelect || !rankInput) return;

                        const applyRank = () => {
                            const selectedOption = surveyorSelect.options[surveyorSelect.selectedIndex];
                            if (!selectedOption) return;
                            rankInput.value = selectedOption.getAttribute('data-rank') || '';
                        };

                        surveyorSelect.addEventListener('change', applyRank);
                        applyRank();
                    });
                </script>

                <!-- Watermark -->
                <div
                    class="no-print absolute top-[45%] left-1/2 -rotate-[35deg] -translate-x-1/2 -translate-y-1/2 text-slate-50 text-[100px] font-black pointer-events-none z-0 opacity-10 select-none uppercase tracking-[0.3em]">
                    Draft
                </div>
            </form>
        </div>
    </div>

    @include('components.global-fileno-modal')

    @push('scripts')
        <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
        <script src="{{ asset('js/valuation_reports.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.lucide) window.lucide.createIcons();
                if (window.GlobalFileNoModal) {
                    window.GlobalFileNoModal.init();

                    const fileInput = document.getElementById('file_number');
                    const hiddenInput = document.getElementById('file_number_hidden');
                    const pickBtn = document.getElementById('select-fileno-btn');

                    if (pickBtn) {
                        pickBtn.addEventListener('click', () => {
                            window.GlobalFileNoModal.open({
                                targetFields: ['#file_number', '#file_number_hidden'], // Auto-populate these fields
                                callback: (data) => {
                                    console.log('File selected:', data);
                                    if (data && data.record) {
                                        const r = data.record;
                                        
                                        if (r.file_name && !document.getElementById('full_name').value) {
                                            document.getElementById('full_name').value = r.file_name;
                                        }
                                        
                                        if (document.getElementById('address') && !document.getElementById('address').value) {
                                            const addr = r.address || r.location || '';
                                            if (addr) document.getElementById('address').value = addr;
                                        }
                                        
                                        if (r.plot_no && document.getElementById('property_no') && !document.getElementById('property_no').value) {
                                            document.getElementById('property_no').value = r.plot_no;
                                        }
                                        
                                        if (r.location && document.getElementById('town_city') && !document.getElementById('town_city').value) {
                                            document.getElementById('town_city').value = r.location;
                                        }
                                        
                                        if (r.lga && document.getElementById('lga')) {
                                            const lgaSelect = document.getElementById('lga');
                                            if (!lgaSelect.value) {
                                                Array.from(lgaSelect.options).forEach(opt => {
                                                    if (opt.text.trim().toUpperCase() === r.lga.trim().toUpperCase() || opt.value.trim().toUpperCase() === r.lga.trim().toUpperCase()) {
                                                        lgaSelect.value = opt.value;
                                                    }
                                                });
                                            }
                                        }
                                    }
                                }
                            });
                        });
                    }

                    // Form submit handler
                    const form = document.getElementById('valuation-form');
                    if (form) {
                        form.addEventListener('submit', () => {
                            hiddenInput.value = fileInput.value;
                        });
                    }
                }
            });
        </script>
    @endpush
@endsection
