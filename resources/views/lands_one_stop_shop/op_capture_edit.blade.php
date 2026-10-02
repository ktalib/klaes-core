@extends('layouts.app')

@section('page-title')
    {{ __('Update OP Capture') }}
@endsection

@section('content')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')

        <div class="p-6">
            <div class="max-w-5xl mx-auto">
                {{-- Page header --}}
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400">Applications (Change of Name)</p>
                        <h1 class="mt-1 text-xl font-extrabold text-slate-900">Update OP</h1>
                        <p class="text-slate-500 text-sm mt-1">
                            Occupancy Permit details for
                            <span class="font-semibold text-blue-600 font-mono">{{ $record['mlsfNo'] ?: '—' }}</span>
                        </p>
                    </div>
                    <button onclick="history.back()"
                        class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 transition-all">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to Applications
                    </button>
                </div>

                {{-- Hidden common data --}}
                <input type="hidden" id="opCapRecordId" value="{{ $fileNumberId }}">

                {{-- ══════════════════════════════════════════════════════ --}}
                {{-- CARD 0 — PASSPORT PHOTOGRAPH                          --}}
                {{-- ══════════════════════════════════════════════════════ --}}
                {{-- The photograph belongs to the FILE, not to the OP or the Transfer of
                     Title row, so it sits above both cards and saves on its own. It is the
                     same image the commissioning form captures: filed into the file's EDMS
                     scan folder and pointed at by oss_applications.passport_photo. --}}
                @php
                    $passportUrl = $passport['url'] ?? null;
                @endphp
                <div class="bg-white rounded-2xl border border-amber-200 shadow-sm mb-6" data-card-type="passport">
                    <div class="px-6 pt-5 pb-3 border-b border-amber-100 flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600 flex-shrink-0">
                            <i data-lucide="user-square" class="h-4 w-4"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-extrabold text-amber-700 uppercase tracking-wide">Passport Photograph</h2>
                            <p class="text-[11px] text-slate-400 font-mono">
                                {{ $passportFileNumber ?: 'No file number on this record' }}
                            </p>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-5">
                            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                                <div class="h-40 w-32 flex-shrink-0 overflow-hidden rounded-lg border border-dashed border-slate-300 bg-white flex items-center justify-center">
                                    <img id="passportPreview" src="{{ $passportUrl ?: '' }}" alt="Passport photograph"
                                        class="h-full w-full object-cover {{ $passportUrl ? '' : 'hidden' }}">
                                    {{-- The id sits on the wrapper, not the <i>: lucide swaps the <i> for an <svg>. --}}
                                    <span id="passportPlaceholder" class="{{ $passportUrl ? 'hidden' : 'flex' }} items-center justify-center">
                                        <i data-lucide="user-square" class="h-10 w-10 text-slate-300"></i>
                                    </span>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <p id="passportStatus" class="text-sm font-semibold text-slate-700">
                                        {{ $passportUrl ? 'Passport on record' : 'No passport on record' }}
                                    </p>
                                    @if($passportUrl)
                                        <p class="mt-0.5 text-[11px] text-slate-400">
                                            Filed from {{ ($passport['source'] ?? '') === 'oss_applications' ? 'the OSS application' : 'the scan folder for this file' }}.
                                        </p>
                                    @endif

                                    @if($passportFileNumber)
                                        <input type="file" id="passportInput" accept="image/jpeg,image/jpg,image/png"
                                            class="mt-3 w-full max-w-md rounded-lg border border-slate-300 bg-white p-2 text-xs text-slate-600 file:mr-2 file:rounded file:border-0 file:bg-amber-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-amber-700 hover:file:bg-amber-100">
                                        <p class="mt-1 text-[11px] text-slate-500">
                                            JPG or PNG, max 2MB. Uploading replaces the photograph on record; the previous
                                            scan stays filed as a superseded document.
                                        </p>
                                    @else
                                        <p class="mt-3 text-xs text-rose-600">
                                            This record carries no file number yet, so a photograph cannot be filed against it.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($passportFileNumber)
                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 p-4">
                        <a href="{{ $returnUrl }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                        <button type="button" id="passportSaveBtn" disabled
                            class="rounded-lg bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-slate-300 inline-flex items-center gap-2">
                            <i data-lucide="upload" class="h-4 w-4"></i>
                            Upload Passport
                        </button>
                    </div>
                    @endif
                </div>

                @php
                    // Categorise PRA rows into OP rows and Transfer-of-Title rows
                    // Prioritize records that actually HAVE an op_serial_number for the OP card display.
                    $opPraRows = $allPraRows->filter(fn($r) => 
                        !empty($r->op_serial_number) && $r->op_serial_number != '0'
                    )->values();

                    // If still empty, fall back to records that are NOT transfers
                    if ($opPraRows->isEmpty()) {
                        $opPraRows = $allPraRows->filter(fn($r) =>
                            stripos($r->instrument_type ?? '', 'Transfer of Title') === false &&
                            stripos($r->transaction_type ?? '', 'Transfer of Title') === false
                        )->values();
                    }

                    $totPraRows = $allPraRows->filter(fn($r) =>
                        stripos($r->instrument_type ?? '', 'Transfer of Title') !== false ||
                        stripos($r->transaction_type ?? '', 'Transfer of Title') !== false
                    )->values();

                    // To avoid confusion, if a record is already shown as an OP card, 
                    // only show it as a TOT card if it's the only TOT record.
                    // Actually, usually TOT records should always be shown as TOT cards.
                    // But if it's shown in both, it might be okay.

                    // If OP row is absent in PRA, show a fallback OP card from resolved source data
                    // (instrument_capture/base record) so users can still edit OP details on this page.
                    $hasFallbackOpData =
                        !empty($record['mlsfNo'] ?? null)
                        || !empty($record['op_serial_number'] ?? null)
                        || !empty($record['op_type'] ?? null)
                        || !empty($record['file_name'] ?? null);

                    $fallbackOpRow = null;
                    if ($opPraRows->isEmpty() && $hasFallbackOpData) {
                        $fallbackOpRow = (object) [
                            'id' => null,
                            'mlsFNo' => $record['mlsfNo'] ?? null,
                            'fileno' => $record['mlsfNo'] ?? null,
                            'op_type' => $record['op_type'] ?? null,
                            'op_serial_number' => $record['op_serial_number'] ?? null,
                            'Grantee' => $record['party_2_name'] ?? ($record['file_name'] ?? null),
                            'party_2' => $record['party_2_name'] ?? ($record['file_name'] ?? null),
                            'land_use' => $record['land_use'] ?? null,
                            'purpose' => $record['purpose'] ?? null,
                            'tp_no' => $record['tp_number'] ?? null,
                            'plot_no' => $record['plot_number'] ?? null,
                            'serialNo' => $record['serial_no'] ?? null,
                            'pageNo' => $record['page_no'] ?? null,
                            'volumeNo' => $record['volume_no'] ?? null,
                            'regNo' => $record['registration_number'] ?? null,
                            'registration_number' => $record['registration_number'] ?? null,
                            'deeds_date' => $record['reg_date'] ?? null,
                            'reg_date' => $record['reg_date'] ?? null,
                            'deeds_time' => $record['reg_time'] ?? null,
                            'reg_time' => $record['reg_time'] ?? null,
                            'transaction_date' => $record['transaction_date'] ?? null,
                            'created_at' => $record['instrument_date'] ?? null,
                            'lgsaOrCity' => $record['lga'] ?? null,
                            'location' => $record['location'] ?? null,
                            'property_description' => $record['location'] ?? null,
                        ];
                    }

                    $displayOpRows = $opPraRows->isNotEmpty()
                        ? $opPraRows
                        : ($fallbackOpRow ? collect([$fallbackOpRow]) : collect());
                @endphp

                {{-- ══════════════════════════════════════════════════════ --}}
                {{-- CARD 1 — OCCUPANCY PERMIT (OP) ROW                    --}}
                {{-- ══════════════════════════════════════════════════════ --}}
                @foreach($displayOpRows as $opRow)
                @php $opIdx = $loop->index; @endphp
                <div class="bg-white rounded-2xl border border-blue-200 shadow-sm mb-6" data-card-type="op">
                    <div class="px-6 pt-5 pb-3 border-b border-blue-100 flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600 flex-shrink-0">
                            <i data-lucide="file-badge" class="h-4 w-4"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-extrabold text-blue-700 uppercase tracking-wide">Occupancy Permit (OP)</h2>
                            <p class="text-[11px] text-slate-400 font-mono">{{ !empty($opRow->id) ? 'PRA ID: ' . $opRow->id : 'Source: Instrument Capture' }}</p>
                        </div>
                    </div>

                    <div class="p-6 flex flex-col gap-6">
                        <input type="hidden" class="opRowPraId" value="{{ $opRow->id }}">

                        {{-- ── Section A: File & OP Details ── --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-5">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                                    <i data-lucide="file-text" class="h-4 w-4"></i>
                                </div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600">File &amp; OP Details</h3>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">MLSF Number</label>
                                    <input type="text" readonly value="{{ $opRow->mlsFNo ?: $opRow->fileno ?? $record['mlsfNo'] }}"
                                        class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-mono text-slate-700">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">OP Type</label>
                                    <div class="flex items-center gap-5 mt-1.5">
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                            <input type="radio" name="op_type_{{ $opIdx }}" class="opTypeRadio" value="OP Resettlement"
                                                {{ ($opRow->op_type ?? '') === 'OP Resettlement' ? 'checked' : '' }}
                                                class="h-4 w-4 text-blue-600 border-slate-300">
                                            <span class="text-sm text-slate-700">Resettlement</span>
                                        </label>
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                            <input type="radio" name="op_type_{{ $opIdx }}" class="opTypeRadio" value="OP Direct Allocation"
                                                {{ ($opRow->op_type ?? '') === 'OP Direct Allocation' ? 'checked' : '' }}
                                                class="h-4 w-4 text-blue-600 border-slate-300">
                                            <span class="text-sm text-slate-700">Direct Allocation</span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">OP Serial Number</label>
                                    <input type="text" class="opSerialNum w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        value="{{ $opRow->op_serial_number ?? '' }}" placeholder="Enter OP serial number">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">File Name / Allottee</label>
                                    <input type="text" class="opFileName w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        value="{{ $opRow->Grantee ?? $opRow->party_2 ?? $record['file_name'] ?? '' }}" placeholder="Enter file name">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Customer Type</label>
                                    <select class="opCustomerType w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                                        @foreach(['Individual', 'Corporate', 'Multiple'] as $ct)
                                            <option value="{{ $ct }}" {{ ($record['customer_type'] ?? 'Individual') === $ct ? 'selected' : '' }}>{{ $ct }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Land Use</label>
                                    <select class="opLandUse w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                                        <option value="">Select land use</option>
                                        @foreach($landUses as $lu)
                                            @php
                                                $luName = strtoupper($lu->landuse);
                                                if (str_contains($luName, 'RESIDENTIAL'))      $luCode = 'RES';
                                                elseif (str_contains($luName, 'COMMERCIAL'))   $luCode = 'COM';
                                                elseif (str_contains($luName, 'INDUSTRIAL'))   $luCode = 'IND';
                                                elseif (str_contains($luName, 'AGRICULT'))     $luCode = 'AGR';
                                                elseif (str_contains($luName, 'MIXED'))        $luCode = 'MIX';
                                                elseif (str_contains($luName, 'INSTITUTION'))  $luCode = 'INS';
                                                else                                            $luCode = strtoupper(substr($luName, 0, 3));
                                                $opLandUse = strtoupper($opRow->land_use ?? $record['land_use'] ?? '');
                                            @endphp
                                            <option value="{{ $luCode }}" data-id="{{ $lu->id }}"
                                                {{ $opLandUse === $luCode ? 'selected' : '' }}>{{ $luName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Purpose</label>
                                    <select class="opPurpose w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                                        <option value="">Select purpose</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">TP Number</label>
                                    <input type="text" class="opTpNo w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        value="{{ $opRow->tp_no ?? $record['tp_number'] ?? '' }}" placeholder="Enter TP number">
                                </div>
                            </div>
                        </div>

                        {{-- ── Section B: Registration Particulars (OP only) ── --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-5">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-100 text-violet-600">
                                    <i data-lucide="book-open" class="h-4 w-4"></i>
                                </div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600">Registration Particulars</h3>
                            </div>
                            @php
                                $opSerialNo = $opRow->serialNo ?? $opRow->serial_no ?? $record['serial_no'] ?? '';
                                $opPageNo   = $opRow->pageNo   ?? $opRow->page_no   ?? $record['page_no']   ?? '';
                                $opVolumeNo = $opRow->volumeNo ?? $opRow->volume_no ?? $record['volume_no'] ?? '';
                                $opRegNo    = $opRow->regNo    ?? $opRow->registration_number ?? $record['registration_number'] ?? '';
                                // Strip leading zeros for numeric fields
                                $opSerialNo = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$opSerialNo));
                                $opVolumeNo = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$opVolumeNo));
                                $opRegDate  = $opRow->deeds_date ?? $opRow->reg_date ?? $record['reg_date'] ?? null;
                                $opRegTime  = $opRow->deeds_time ?? $opRow->reg_time ?? $record['reg_time'] ?? null;
                                $opTransDate = $opRow->transaction_date ?? $record['transaction_date'] ?? null;
                                $opInstrDate = $opRow->created_at ?? $record['instrument_date'] ?? null;
                            @endphp
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Serial No</label>
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" class="opRegSerialNo w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        value="{{ $opSerialNo }}" placeholder="Enter serial number">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Page No</label>
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" class="opRegPageNo w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-700"
                                        value="{{ $opPageNo }}" readonly placeholder="Auto from Serial No">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Volume No</label>
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" class="opRegVolumeNo w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        value="{{ $opVolumeNo }}" placeholder="Enter volume number">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Registration Particulars</label>
                                    <input type="text" class="opRegNumber w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm font-mono text-slate-700"
                                        value="{{ $opRegNo }}" readonly placeholder="Auto from Serial/Page/Volume">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Reg Date</label>
                                    <input type="date" class="opRegDate w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        value="{{ !empty($opRegDate) ? \Carbon\Carbon::parse($opRegDate)->format('Y-m-d') : '' }}">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Reg Time</label>
                                    <input type="time" class="opRegTime w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        value="{{ $opRegTime ?? '' }}">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Transaction Date</label>
                                    <input type="date" class="opTransactionDate w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        value="{{ !empty($opTransDate) ? \Carbon\Carbon::parse($opTransDate)->format('Y-m-d') : '' }}">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Entry Date</label>
                                    <input type="date" class="opInstrumentDate w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        value="{{ !empty($opInstrDate) ? \Carbon\Carbon::parse($opInstrDate)->format('Y-m-d') : '' }}">
                                </div>
                            </div>
                        </div>

                        {{-- ── Section C: Location ── --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-5">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 text-amber-600">
                                    <i data-lucide="map-pin" class="h-4 w-4"></i>
                                </div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600">Location</h3>
                            </div>
                            @php
                                $opPlotNo   = $opRow->plot_no ?? $record['plot_number'] ?? '';
                                $opLga      = $opRow->lgsaOrCity ?? $record['lga'] ?? '';
                                $opDistrict = $record['district'] ?? '';
                                $opDistrictCustom = ($districtIsCustom ?? false);
                                $opLocation = $opRow->location ?? $opRow->property_description ?? $record['location'] ?? '';
                            @endphp
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Plot Number</label>
                                    <input type="text" class="opPlotNo w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                        value="{{ $opPlotNo }}" placeholder="Enter plot number">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">District</label>
                                    <select class="opDistrict w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                                        <option value="">Select District</option>
                                        @foreach($districts as $districtItem)
                                            <option value="{{ $districtItem->name }}" {{ !$opDistrictCustom && $opDistrict === $districtItem->name ? 'selected' : '' }}>{{ $districtItem->name }}</option>
                                        @endforeach
                                        <option value="__other__" {{ $opDistrictCustom ? 'selected' : '' }}>Other</option>
                                    </select>
                                    <input type="text" class="opDistrictCustom mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 {{ $opDistrictCustom ? '' : 'hidden' }}"
                                        value="{{ $opDistrictCustom ? $opDistrict : '' }}" placeholder="Enter district name">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">LGA</label>
                                    <select class="opLga w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700">
                                        <option value="">Select LGA</option>
                                        @foreach($lgas as $lgaItem)
                                            <option value="{{ $lgaItem->name }}" {{ $opLga === $lgaItem->name ? 'selected' : '' }}>{{ $lgaItem->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">State</label>
                                    <input type="text" value="Kano" readonly class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-700">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Property Description / Address</label>
                                    <textarea class="opLocation w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-700" rows="2" readonly>{{ $opLocation }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- OP card footer --}}
                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 p-4">
                        <a href="{{ $returnUrl }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                        <button type="button" class="opSaveBtn rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 inline-flex items-center gap-2"
                            data-pra-id="{{ $opRow->id }}" data-row-type="op" data-idx="{{ $opIdx }}">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            Update OP
                        </button>
                    </div>
                </div>
                @endforeach

                @if($displayOpRows->isEmpty())
                <div class="bg-slate-50 rounded-2xl border border-dashed border-slate-300 p-8 text-center mb-6">
                    <i data-lucide="file-question" class="h-10 w-10 text-slate-300 mx-auto mb-2"></i>
                    <p class="text-sm text-slate-400">No Occupancy Permit row found for this property.</p>
                </div>
                @endif

                {{-- ══════════════════════════════════════════════════════ --}}
                {{-- CARD 2 — TRANSFER OF TITLE (OP) ROW                   --}}
                {{-- ══════════════════════════════════════════════════════ --}}
                @foreach($totPraRows as $totRow)
                @php $totIdx = $loop->index; @endphp
                <div class="bg-white rounded-2xl border border-purple-200 shadow-sm mb-6" data-card-type="tot">
                    <div class="px-6 pt-5 pb-3 border-b border-purple-100 flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 text-purple-600 flex-shrink-0">
                            <i data-lucide="arrow-right-left" class="h-4 w-4"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-extrabold text-purple-700 uppercase tracking-wide">Transfer of Title (OP)</h2>
                            <p class="text-[11px] text-slate-400 font-mono">PRA ID: {{ $totRow->id }}</p>
                        </div>
                    </div>

                    <div class="p-6 flex flex-col gap-6">
                        <input type="hidden" class="totRowPraId" value="{{ $totRow->id }}">

                        {{-- ── Party Details ── --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-5">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
                                    <i data-lucide="users" class="h-4 w-4"></i>
                                </div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600">Transfer &amp; Party Details</h3>
                            </div>

                            @php
                                $totTransDate = $totRow->transaction_date ?? $record['transaction_date'] ?? null;
                            @endphp

                            {{-- Transaction Details --}}
                            <div class="mb-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Transaction Date</label>
                                        <input type="date" class="totTransactionDate w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                            value="{{ !empty($totTransDate) ? \Carbon\Carbon::parse($totTransDate)->format('Y-m-d') : '' }}">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border-t border-dashed border-slate-200 my-4"></div>

                            {{-- Party 1 (Grantor) --}}
                            <div class="mb-4">
                                <p class="mb-2 text-[11px] font-black uppercase tracking-widest text-blue-500">Party 1 — Grantor (Previous Owner)</p>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Name</label>
                                        <input type="text" class="totParty1Name w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                            value="{{ $totRow->Grantor ?? $totRow->party_1 ?? '' }}" placeholder="Enter grantor name">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Phone</label>
                                        <input type="text" class="totParty1Phone w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                            value="{{ $record['party_1_phone'] ?? '' }}" placeholder="Enter phone">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Address</label>
                                        <input type="text" class="totParty1Address w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                            value="{{ $record['party_1_address'] ?? '' }}" placeholder="Enter address">
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-dashed border-slate-200 my-4"></div>

                            {{-- Party 2 (Grantee) --}}
                            <div>
                                <p class="mb-2 text-[11px] font-black uppercase tracking-widest text-blue-500">Party 2 — Grantee (New Owner / Applicant)</p>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Name</label>
                                        <input type="text" class="totParty2Name w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                            value="{{ $totRow->Grantee ?? $totRow->party_2 ?? '' }}" placeholder="Enter grantee name">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Phone</label>
                                        <input type="text" class="totParty2Phone w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                            value="{{ $record['party_2_phone'] ?? '' }}" placeholder="Enter phone">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Address</label>
                                        <input type="text" class="totParty2Address w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700"
                                            value="{{ $record['party_2_address'] ?? '' }}" placeholder="Enter address">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 p-4">
                        <a href="{{ $returnUrl }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                        <button type="button" class="totSaveBtn rounded-lg bg-purple-600 px-5 py-2 text-sm font-semibold text-white hover:bg-purple-700 inline-flex items-center gap-2"
                            data-pra-id="{{ $totRow->id }}" data-row-type="transfer_of_title" data-idx="{{ $totIdx }}">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            Update Transfer of Title
                        </button>
                    </div>
                </div>
                @endforeach

                @if($totPraRows->isEmpty())
                <div class="bg-slate-50 rounded-2xl border border-dashed border-slate-300 p-8 text-center mb-6">
                    <i data-lucide="file-question" class="h-10 w-10 text-slate-300 mx-auto mb-2"></i>
                    <p class="text-sm text-slate-400">No Transfer of Title row found for this property.</p>
                </div>
                @endif

            </div>
        </div>
    </div>

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // ── Strip leading zeros helper ──
        function _stripLeadingZeros(val) {
            val = val.replace(/[^0-9]/g, '');
            val = val.replace(/^0+/, '') || '';
            return val;
        }

        function _initOpCard(card) {
            var serialEl  = card.querySelector('.opRegSerialNo');
            var pageEl    = card.querySelector('.opRegPageNo');
            var volEl     = card.querySelector('.opRegVolumeNo');
            var regEl     = card.querySelector('.opRegNumber');
            var plotEl    = card.querySelector('.opPlotNo');
            var distEl    = card.querySelector('.opDistrict');
            var distCustEl = card.querySelector('.opDistrictCustom');
            var lgaEl     = card.querySelector('.opLga');
            var locEl     = card.querySelector('.opLocation');
            var landUseEl = card.querySelector('.opLandUse');
            var purposeEl = card.querySelector('.opPurpose');
            var opSerEl   = card.querySelector('.opSerialNum');

            function stripAndSync(input) {
                input.value = _stripLeadingZeros(input.value);
                syncRegNumber();
            }

            function syncRegNumber() {
                if (!serialEl || !pageEl || !volEl || !regEl) return;
                pageEl.value = serialEl.value;
                var s = serialEl.value, p = pageEl.value, v = volEl.value;
                regEl.value = (s || v) ? ((s || '') + '/' + (p || '') + '/' + (v || '')) : '';
            }

            if (serialEl) {
                serialEl.value = _stripLeadingZeros(serialEl.value || '');
                serialEl.addEventListener('input', function() { stripAndSync(this); });
            }
            if (volEl) {
                volEl.value = _stripLeadingZeros(volEl.value || '');
                volEl.addEventListener('input', function() { stripAndSync(this); });
            }
            if (opSerEl) {
                opSerEl.value = _stripLeadingZeros(opSerEl.value || '');
                opSerEl.addEventListener('input', function() { this.value = _stripLeadingZeros(this.value); });
            }
            syncRegNumber();

            // District "Other" toggle
            if (distEl && distCustEl) {
                distEl.addEventListener('change', function() {
                    if (this.value === '__other__') {
                        distCustEl.classList.remove('hidden');
                        distCustEl.focus();
                    } else {
                        distCustEl.classList.add('hidden');
                        distCustEl.value = '';
                    }
                    buildPropertyDescription();
                });
                distCustEl.addEventListener('input', buildPropertyDescription);
            }

            function getDistrictValue() {
                if (!distEl) return '';
                if (distEl.value === '__other__') return (distCustEl ? distCustEl.value : '').trim();
                return (distEl.value || '').trim();
            }

            function buildPropertyDescription() {
                if (!locEl) return;
                // The plot number is not part of a full property location — it
                // has its own field on this card and was only repeated here.
                var parts = [];
                var dist = getDistrictValue();
                if (dist) parts.push(dist);
                var lga = lgaEl ? (lgaEl.value || '').trim() : '';
                if (lga) parts.push(lga);
                parts.push('Kano State');
                locEl.value = parts.join(', ').toUpperCase();
            }

            if (plotEl) plotEl.addEventListener('input', buildPropertyDescription);
            if (distEl) distEl.addEventListener('change', buildPropertyDescription);
            if (lgaEl) lgaEl.addEventListener('change', buildPropertyDescription);
            buildPropertyDescription();

            // Land-use → Purpose cascade
            function loadPurposes(savedPurpose) {
                if (!purposeEl || !landUseEl) return;
                var selectedOpt = landUseEl.options[landUseEl.selectedIndex];
                var landUseId = selectedOpt ? selectedOpt.getAttribute('data-id') : '';
                purposeEl.innerHTML = '<option value="">Select purpose</option>';
                if (!landUseId) return;
                fetch('/mls-fileno/get-dependent-data?land_use_id=' + encodeURIComponent(landUseId))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        (data.purposes || []).forEach(function(p) {
                            var opt = document.createElement('option');
                            opt.value = p.name;
                            opt.textContent = p.name;
                            purposeEl.appendChild(opt);
                        });
                        if (savedPurpose) {
                            for (var i = 0; i < purposeEl.options.length; i++) {
                                if (purposeEl.options[i].value.toUpperCase() === savedPurpose.toUpperCase()) {
                                    purposeEl.selectedIndex = i;
                                    break;
                                }
                            }
                        }
                    })
                    .catch(function() {});
            }
            if (landUseEl) {
                var savedPurpose = @json($record['purpose'] ?? '');
                landUseEl.addEventListener('change', function() { loadPurposes(''); });
                if (landUseEl.value) loadPurposes(savedPurpose);
            }

            // Save button
            var saveBtn = card.querySelector('.opSaveBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    var praId = this.getAttribute('data-pra-id');
                    var recordId = document.getElementById('opCapRecordId').value;
                    var fileName = (card.querySelector('.opFileName').value || '').trim();
                    var customerType = (card.querySelector('.opCustomerType').value || '').trim();

                    if (!fileName) { Swal.fire('Required', 'File Nameis required.', 'warning'); return; }
                    if (!customerType) { Swal.fire('Required', 'Customer Type is required.', 'warning'); return; }

                    var opTypeEl = card.querySelector('.opTypeRadio:checked');
                    var payload = {
                        pra_id:             parseInt(praId),
                        row_type:           'op',
                        customer_type:      customerType,
                        land_use:           (landUseEl ? landUseEl.value : '') || null,
                        purpose:            (purposeEl ? purposeEl.value : '') || null,
                        instrument_type:    'Occupancy Permit (OP)',
                        file_name:          fileName,
                        plot_number:        plotEl ? (plotEl.value || '').trim() || null : null,
                        tp_number:          card.querySelector('.opTpNo') ? (card.querySelector('.opTpNo').value || '').trim() || null : null,
                        location:           locEl ? (locEl.value || '').trim() || null : null,
                        lga:                lgaEl ? (lgaEl.value || '').trim() || null : null,
                        district:           getDistrictValue() || null,
                        party_1_name:       null,
                        party_2_name:       null,
                        op_type:            opTypeEl ? opTypeEl.value : null,
                        op_serial_number:   opSerEl ? (opSerEl.value || '').trim() || null : null,
                        serial_no:          serialEl ? (serialEl.value || '').trim() || null : null,
                        page_no:            pageEl ? (pageEl.value || '').trim() || null : null,
                        volume_no:          volEl ? (volEl.value || '').trim() || null : null,
                        registration_number: regEl ? (regEl.value || '').trim() || null : null,
                        reg_date:           card.querySelector('.opRegDate') ? (card.querySelector('.opRegDate').value || '').trim() || null : null,
                        reg_time:           card.querySelector('.opRegTime') ? (card.querySelector('.opRegTime').value || '').trim() || null : null,
                        transaction_date:   card.querySelector('.opTransactionDate') ? (card.querySelector('.opTransactionDate').value || '').trim() || null : null,
                        instrument_date:    card.querySelector('.opInstrumentDate') ? (card.querySelector('.opInstrumentDate').value || '').trim() || null : null,
                    };

                    _doSave(saveBtn, recordId, payload, 'Update OP');
                });
            }
        }

        // ── Initialise each Transfer of Title card ──
        function _initTotCards() {
            document.querySelectorAll('[data-card-type="tot"]').forEach(function(card) {
                var btn = card.querySelector('.totSaveBtn');
                if (!btn) return;
                btn.addEventListener('click', function() {
                    var praId = this.getAttribute('data-pra-id');
                    var recordId = document.getElementById('opCapRecordId').value;

                    var party1Name = (card.querySelector('.totParty1Name').value || '').trim();
                    var party2Name = (card.querySelector('.totParty2Name').value || '').trim();
                    var customerType = 'Individual'; // Keep default or pick from OP

                    // ToT uses the grantee/party2 as the "file_name" (new owner)
                    var fileName = party2Name;

                    if (!fileName) { Swal.fire('Required', 'Party 2 (Grantee) Name is required.', 'warning'); return; }

                    var payload = {
                        pra_id:              parseInt(praId),
                        row_type:            'transfer_of_title',
                        customer_type:       customerType,
                        file_name:           fileName,
                        party_1_name:        party1Name || null,
                        party_2_name:        party2Name || null,
                        party_1_phone:       (card.querySelector('.totParty1Phone').value || '').trim() || null,
                        party_1_address:     (card.querySelector('.totParty1Address').value || '').trim() || null,
                        party_2_phone:       (card.querySelector('.totParty2Phone').value || '').trim() || null,
                        party_2_address:     (card.querySelector('.totParty2Address').value || '').trim() || null,
                        transaction_date:    (card.querySelector('.totTransactionDate').value || '').trim() || null,
                    };

                    _doSave(this, recordId, payload, 'Update Transfer of Title');
                });
            });
        }

        function _doSave(btn, recordId, payload, label) {
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i> Saving...';
            if (window.lucide) window.lucide.createIcons();

            fetch('/lands-one-stop-shop/applications/op-resettlement/' + recordId + '/update-details', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify(payload),
            })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(result) {
                if (result.ok && result.data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Record Updated',
                        text: result.data.message || 'Record updated successfully.',
                        timer: 1800,
                        showConfirmButton: false,
                    });
                    _resetBtn(btn, label);
                    return;
                } else {
                    var msg = result.data.message || 'Failed to save.';
                    if (result.data.error) {
                        msg += '\n\n' + result.data.error;
                    }
                    if (result.data.errors) {
                        var errs = Object.values(result.data.errors).flat();
                        msg += '\n' + errs.join('\n');
                    }
                    Swal.fire('Error', msg, 'error');
                    _resetBtn(btn, label);
                }
            })
            .catch(function(err) {
                Swal.fire('Error', 'Network error: ' + err.message, 'error');
                _resetBtn(btn, label);
            });
        }

        // ── Passport photograph ──
        // One slot, two states: the image already filed against the file (rendered by the
        // blade) and a replacement picked here. The replacement is previewed locally and
        // only uploaded when the button is pressed, so opening the page never touches the
        // photo on record.
        function _initPassportCard() {
            var input = document.getElementById('passportInput');
            var btn = document.getElementById('passportSaveBtn');
            if (!input || !btn) return;   // no file number on this record — nothing to file against

            var img = document.getElementById('passportPreview');
            var placeholder = document.getElementById('passportPlaceholder');
            var status = document.getElementById('passportStatus');
            var onRecordSrc = img ? (img.getAttribute('src') || '') : '';

            function showOnRecord() {
                if (img) {
                    if (onRecordSrc) {
                        img.src = onRecordSrc;
                        img.classList.remove('hidden');
                    } else {
                        img.removeAttribute('src');
                        img.classList.add('hidden');
                    }
                }
                if (placeholder) placeholder.classList.toggle('hidden', !!onRecordSrc);
                if (status) status.textContent = onRecordSrc ? 'Passport on record' : 'No passport on record';
            }

            input.addEventListener('change', function() {
                var file = this.files && this.files[0];
                if (!file) {
                    btn.disabled = true;
                    showOnRecord();
                    return;
                }

                // Checked here as well as server-side so an oversized photo is caught before
                // the operator waits on an upload that will be rejected.
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire('Passport too large', 'The passport photograph must be 2MB or smaller.', 'error');
                    this.value = '';
                    btn.disabled = true;
                    showOnRecord();
                    return;
                }

                var reader = new FileReader();
                reader.onload = function(e) {
                    if (img) {
                        img.src = e.target.result;
                        img.classList.remove('hidden');
                    }
                    if (placeholder) placeholder.classList.add('hidden');
                    if (status) status.textContent = file.name + ' — replaces the current photo on upload';
                };
                reader.readAsDataURL(file);
                btn.disabled = false;
            });

            btn.addEventListener('click', function() {
                var file = input.files && input.files[0];
                if (!file) { Swal.fire('Required', 'Choose a passport photograph first.', 'warning'); return; }

                var body = new FormData();
                body.append('passport', file);

                btn.disabled = true;
                btn.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i> Uploading...';
                if (window.lucide) window.lucide.createIcons();

                fetch('{{ route('lands-one-stop-shop.applications.update-passport', ['id' => $fileNumberId]) }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: body,
                })
                .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
                .then(function(result) {
                    if (result.ok && result.data.success) {
                        // The uploaded image is now the one on record, so a later "clear the
                        // picker" must fall back to it rather than to the previous photo.
                        onRecordSrc = result.data.url || onRecordSrc;
                        input.value = '';
                        showOnRecord();
                        Swal.fire({
                            icon: 'success',
                            title: 'Passport Updated',
                            text: result.data.message || 'Passport photograph filed.',
                            timer: 2200,
                            showConfirmButton: false,
                        });
                        _resetPassportBtn(btn);
                        return;
                    }

                    var msg = result.data.message || 'Failed to upload the passport photograph.';
                    if (result.data.errors) {
                        msg += '
' + Object.values(result.data.errors).flat().join('
');
                    }
                    Swal.fire('Error', msg, 'error');
                    _resetPassportBtn(btn);
                })
                .catch(function(err) {
                    Swal.fire('Error', 'Network error: ' + err.message, 'error');
                    _resetPassportBtn(btn);
                });
            });
        }

        function _resetPassportBtn(btn) {
            btn.disabled = true;   // re-enabled only once a new file is picked
            btn.innerHTML = '<i data-lucide="upload" class="h-4 w-4"></i> Upload Passport';
            if (window.lucide) window.lucide.createIcons();
        }

        function _resetBtn(btn, label) {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="save" class="h-4 w-4"></i> ' + label;
            if (window.lucide) window.lucide.createIcons();
        }

        // Init Lucide icons and wire up all cards
        document.addEventListener('DOMContentLoaded', function() {
            // Force uppercase on all text inputs and textareas
            document.querySelectorAll('input[type="text"], textarea').forEach(function(input) {
                if (!input.readOnly && !input.classList.contains('opRegNumber')) {
                    input.addEventListener('input', function() {
                        this.value = this.value.toUpperCase();
                    });
                    input.style.textTransform = 'uppercase';
                }
            });

            document.querySelectorAll('[data-card-type="op"]').forEach(function(card) {
                _initOpCard(card);
            });
            _initTotCards();
            _initPassportCard();
            if (window.lucide) window.lucide.createIcons();
        });
    </script>
@endsection
