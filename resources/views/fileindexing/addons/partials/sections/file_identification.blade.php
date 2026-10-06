<!-- File Identification Cards (2x2 Grid) -->
<div class="form-grid-2">
    <!-- File Number & Tracking ID Card -->
    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h4 class="mb-6 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
            <i data-lucide="file-text" class="h-4 w-4 text-blue-600"></i>
            File Number & Tracking ID
        </h4>

        <!-- File Number -->
        <div class="form-group">
            <label for="file-number-display" class="block text-sm font-medium text-gray-700 mb-2 required">File Number</label>
            <div class="flex gap-2">
                <input type="text" id="file-number-display"
                    class="flex-grow block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-50"
                    readonly style="color: #6b7280;">
                <input type="hidden" id="fileno" name="fileno" value="">
                <input type="hidden" id="grouping-id" name="grouping_id" value="">
                <div class="flex gap-2">
                    <button type="button" id="select-file-number-btn"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        style="white-space: nowrap;">
                        Select
                    </button>
                    <button type="button" id="clear-file-number-btn"
                        class="inline-flex items-center px-4 py-2 border border-gray-200 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300"
                        style="white-space: nowrap;">
                        Clear
                    </button>
                </div>
            </div>
            @include('fileindexing.partials.year_override')
        </div>

        <div id="kangis-placeholder-feedback" class="hidden mt-2 flex items-center gap-2 text-xs rounded px-3 py-1.5 border">
            <i data-lucide="git-merge" class="h-3.5 w-3.5 shrink-0"></i>
            <span id="kangis-placeholder-feedback-text"></span>
        </div>

        <div id="file-indexed-feedback"
            class="mt-3 hidden rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
            <div class="flex items-start gap-2">
                <i data-lucide="alert-triangle" class="file-indexed-feedback-icon h-4 w-4 mt-0.5 text-amber-500"></i>
                <div>
                    <p id="file-indexed-feedback-title" class="font-semibold"></p>
                    <p id="file-indexed-feedback-body" class="mt-1"></p>
                    <a id="file-indexed-feedback-link" href="#" target="_blank" rel="noopener"
                        class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-amber-800 hover:text-amber-900 underline hidden">
                        View indexed record
                        <i data-lucide="external-link" class="h-3 w-3"></i>
                    </a>
                    <button id="file-indexed-feedback-edit-btn" type="button"
                        class="mt-3 hidden items-center gap-1 rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 transition hover:bg-blue-100">
                        <i data-lucide="edit-3" class="h-4 w-4 text-blue-700"></i>
                        Switch to edit mode
                    </button>
                </div>
            </div>
        </div>

        <div id="edit-mode-banner"
            class="mt-3 hidden rounded-md border border-blue-300 bg-blue-50 px-3 py-2 text-sm text-blue-900" role="status"
            aria-live="assertive">
            <div class="flex items-start gap-2">
                <i data-lucide="info" class="h-4 w-4 mt-0.5 text-blue-500"></i>
                <div>
                    <p class="font-semibold">Edit mode active</p>
                    <p id="edit-mode-banner-body" class="mt-1">
                        Updating existing record <span id="edit-mode-banner-fileno" class="font-semibold"></span>. Review
                        the details and choose Update to save your changes.
                    </p>
                </div>
            </div>
        </div>

        <!-- SLTR Grouping Details -->
        <div id="sltr-grouping-details" class="hidden space-y-4 mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <h5 class="text-xs font-bold text-blue-800 uppercase tracking-wider mb-2 flex items-center gap-2">
                <i data-lucide="info" class="h-3 w-3"></i>
                SLTR Grouping Details
            </h5>
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label for="sub_prefix" class="block text-sm font-medium text-gray-700 mb-1">Sub Prefix</label>
                    <input type="text" id="sub_prefix" name="sub_prefix" disabled
                        class="block w-full px-3 py-2 border border-blue-300 rounded-md shadow-sm bg-white text-blue-900 font-mono sm:text-sm focus:ring-blue-500 focus:border-blue-500"
                        value="{{ isset($record) ? $record->sub_prefix : '' }}">
                </div>
                <div class="form-group">
                    <label for="suffix" class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                    <input type="text" id="suffix" name="suffix" disabled
                        class="block w-full px-3 py-2 border border-blue-300 rounded-md shadow-sm bg-white text-blue-900 font-mono sm:text-sm focus:ring-blue-500 focus:border-blue-500"
                        value="{{ isset($record) ? $record->suffix : '' }}">
                </div>
            </div>
        </div>

        <!-- Tracking ID -->
        <div class="form-group mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Tracking ID</label>
            <div id="grouping-loading-indicator"
                class="hidden flex items-center gap-2 text-sm text-blue-600 mb-2">
                <span class="inline-flex h-4 w-4 items-center justify-center">
                    <span class="loading-spinner"></span>
                </span>
                <span>Fetching grouping record...</span>
            </div>
            <input type="text" id="tracking-id" value=""
                class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 font-mono text-base font-bold text-red-600"
                readonly placeholder="Will be loaded from grouping record">
        </div>




        <!-- Kangis FileNo Placeholder (shown only when KANGIS Registry is selected, hidden in new_kn mode) -->
        <div id="kangis-fileno-placeholder-wrapper" class="form-group mt-6 hidden{{ ($isNewKnMode ?? false) ? ' kn-mode-force-hidden' : '' }}">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                KANGIS FileNo Placeholder <span class="text-red-500">*</span>
                <span class="ml-1 text-xs font-normal text-gray-400">(e.g. KN001, MNKL 01)</span>
            </label>
            {{-- Hidden real field — assembled from prefix + serial by JS --}}
            <input type="hidden" id="kangis-fileno-placeholder" name="kangis_fileno_placeholder">
            <div class="flex gap-2">
                @php
    $prefixes = DB::connection('sqlsrv')
        ->table('kangis_prefixes')
        ->where('status', 1)
        ->orderBy('prefix_code')
        ->get();
@endphp

{{-- Prefix dropdown --}}
<select id="kangis-fileno-prefix"
    name="kangis_fileno_prefix"
    class="w-36 shrink-0 px-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono bg-white">

    <option value="">Select Prefix</option>

    @foreach ($prefixes as $prefix)
        <option value="{{ $prefix->prefix_code }}">
            {{ $prefix->prefix_code }}
        </option>
    @endforeach
</select>
                {{-- Serial / number part --}}
                <input type="text" id="kangis-fileno-serial"
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm font-mono uppercase"
                    placeholder="e.g. 01, 001, 1234"
                    autocomplete="off"
                    inputmode="numeric"
                    oninput="this.value = this.value.replace(/[^0-9]/g,'')" required>
            </div>
            {{-- Inline validation error --}}
            <p id="kangis-placeholder-error" class="hidden mt-1.5 text-xs text-red-600 flex items-center gap-1">
                <i data-lucide="alert-circle" class="h-3.5 w-3.5 shrink-0"></i>
                <span id="kangis-placeholder-error-text">Please select a prefix and enter the serial number.</span>
            </p>
            {{-- Temporary checkbox --}}
            <div class="mt-2 flex items-center gap-2">
                <input type="checkbox" id="kangis-fileno-is-temp"
                    class="h-4 w-4 rounded border-gray-300 text-orange-500 focus:ring-orange-400">
                <label for="kangis-fileno-is-temp" class="text-sm font-medium text-gray-700 cursor-pointer">
                    Temporary <span class="text-gray-400 font-normal">(T)</span>
                </label>
            </div>
            {{-- Assembled preview --}}
            <p id="kangis-placeholder-preview" class="mt-1 text-xs text-gray-400 hidden">
                Value: <strong id="kangis-placeholder-preview-value" class="font-mono text-gray-600"></strong>
            </p>

            {{-- Has New KANGIS FileNo — distinct from the legacy KANGIS FileNo above.
                 A New KANGIS FileNo is the KN-series number (prefix fixed to "KN").
                 When ticked, the KN-series number below is required. Rendered only in
                 normal mode (new_kn mode has its own dedicated KN section below). --}}
            @unless($isNewKnMode ?? false)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="has-new-kangis-fileno" name="has_new_kangis_fileno" value="1"
                        class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                    <label for="has-new-kangis-fileno" class="text-sm font-medium text-gray-700 cursor-pointer">
                        Has New KANGIS FileNo <span class="text-gray-400 font-normal">(KN Series)</span>
                    </label>
                </div>

                <div id="has-new-kangis-fields" class="hidden mt-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        New KANGIS File No <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="text" id="new_kangis_file_no_display" readonly
                            class="flex-grow block w-full px-4 py-3 border border-purple-400 rounded-md shadow-sm placeholder-gray-400 focus:outline-none sm:text-sm font-mono font-bold text-purple-900 bg-purple-50 uppercase cursor-pointer"
                            placeholder="Click Select to choose a New KANGIS file">
                        <button type="button" id="new_kangis_file_no_select_btn"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
                            style="white-space: nowrap;">
                            Select
                        </button>
                        <button type="button" id="new_kangis_file_no_clear_btn"
                            class="inline-flex items-center px-4 py-2 border border-gray-200 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300"
                            style="white-space: nowrap;">
                            Clear
                        </button>
                    </div>
                    <input type="hidden" name="new_kangis_file_no" id="new_kangis_file_no_hidden" value="">
                    <p id="has-new-kangis-error" class="hidden mt-1.5 text-xs text-red-600 flex items-center gap-1">
                        <i data-lucide="alert-circle" class="h-3.5 w-3.5 shrink-0"></i>
                        <span>Please select the New KANGIS File No.</span>
                    </p>
                    <p id="kn-fileno-status" class="mt-1 text-xs text-gray-500"></p>
                </div>
            </div>

            {{-- Has Transaction (New KANGIS) — repeatable transaction rows; each row is saved
                 to the pra table on submit, sharing one prop_id for the file. --}}
            <div class="mt-4 pt-4 border-t border-gray-100">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="has-new-kangis-transaction" name="has_new_kangis_transaction" value="1"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <label for="has-new-kangis-transaction" class="text-sm font-medium text-gray-700 cursor-pointer">
                        Has Transaction
                    </label>
                </div>

                <div id="nk-transaction-card" class="hidden mt-3">
                    <div id="nk-transaction-rows" class="space-y-4">
                        {{-- Template / first transaction row --}}
                        <div class="nk-transaction-row rounded-lg border border-blue-200 bg-blue-50/50 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="text-sm font-semibold text-blue-900 flex items-center gap-2">
                                    <i data-lucide="receipt" class="h-4 w-4"></i>
                                    Transaction <span class="nk-txn-index">1</span> <span class="nk-txn-fileno font-mono text-purple-700"></span>
                                </h5>
                                <button type="button" class="nk-txn-remove hidden inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-800">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Remove
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Instrument Type</label>
                                    <select class="nk-txn-instrument block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="">Select Instrument Type</option>
                                        <option value="Certificate of Occupancy">Certificate of Occupancy</option>
                                        <option value="ST Certificate of Occupancy">ST Certificate of Occupancy</option>
                                        <option value="SLTR Certificate of Occupancy">SLTR Certificate of Occupancy</option>
                                        <option value="Customary Right of Occupancy">Customary Right of Occupancy</option>
                                        <option value="Deed of Transfer">Deed of Transfer</option>
                                        <option value="Deed of Assignment">Deed of Assignment</option>
                                        <option value="ST Assignment">ST Assignment</option>
                                        <option value="Deed of Mortgage">Deed of Mortgage</option>
                                        <option value="Tripartite Mortgage">Tripartite Mortgage</option>
                                        <option value="Deed of Sub Lease">Deed of Sub Lease</option>
                                        <option value="Deed of Sub Under Lease">Deed of Sub Under Lease</option>
                                        <option value="Power of Attorney">Power of Attorney</option>
                                        <option value="Irrevocable Power of Attorney">Irrevocable Power of Attorney</option>
                                        <option value="Conveyance">Conveyance</option>
                                        <option value="Deed of Gift">Deed of Gift</option>
                                        <option value="Court Affidavit">Court Affidavit</option>
                                        <option value="Consent Judgment">Consent Judgment</option>
                                        <option value="Right of Occupancy">Right of Occupancy</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Transaction Date</label>
                                    <input type="date" class="nk-txn-date block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4 mt-2">
                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Serial No</label>
                                    <input type="text" class="nk-txn-serial block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Page No</label>
                                    <input type="text" class="nk-txn-page block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Vol No</label>
                                    <input type="text" class="nk-txn-vol block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mt-2">
                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Registration Date</label>
                                    <input type="date" class="nk-txn-reg-date block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Registration Time</label>
                                    <input type="time" class="nk-txn-reg-time block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mt-2">
                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Grantor</label>
                                    <input type="text" class="nk-txn-grantor block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <div class="form-group">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Grantee</label>
                                    <input type="text" class="nk-txn-grantee block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" style="text-transform: uppercase;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="add-nk-transaction-btn"
                        class="mt-3 inline-flex items-center gap-1.5 px-3 py-2 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        Add Transaction
                    </button>
                </div>
            </div>
            @endunless
        </div>

        <!-- New KANGIS File Section — visible ONLY when arriving from Track New File (?url=new_kn) -->
        @if($isNewKnMode ?? false)
        <div id="new-kangis-wrapper" class="form-group mt-6">
            {{-- In new_kn mode: checkbox is hidden but auto-checked via JS, show a label instead --}}
            <input type="checkbox" id="is-new-kangis-file" class="sr-only" checked>
            <p class="text-sm font-semibold text-green-700 mb-3">
                <i data-lucide="leaf" class="inline h-4 w-4 text-green-600"></i>
                New KANGIS File (KN Series) — enter the KN file number below
            </p>

            <div id="new-kangis-fields" class="space-y-4 pl-2 border-l-2 border-green-200">
                {{-- Hidden form fields submitted to backend --}}
                <input type="hidden" name="kangis_file_type" id="kangis_file_type" value="">
                <input type="hidden" name="mls_file_no" id="mls_file_no_hidden" value="">
                <input type="hidden" name="kangis_file_no" id="kangis_file_no_hidden" value="">
                <input type="hidden" name="new_kangis_file_no" id="new_kangis_file_no_hidden" value="">

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div @if($isNewKnMode ?? false) class="hidden" aria-hidden="true" @endif>
                        <label class="block text-sm font-medium text-gray-700 mb-1">MLPP File No <span class="text-gray-400 font-normal">(optional)</span></label>
                        <div class="flex gap-2">
                            <input type="text" id="mls_file_no_input"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm font-mono uppercase"
                                placeholder="e.g. CON-RES-2019-456"
                                oninput="document.getElementById('mls_file_no_hidden').value = this.value">
                            @if($isNewKnMode ?? false)
                            <button type="button" onclick="KnModeFileSelector.open('mls')"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50" title="Select MLPP File No">
                                <i data-lucide="search" class="h-4 w-4"></i>
                            </button>
                            @endif
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">KANGIS File No <span class="text-gray-400 font-normal">(optional)</span></label>
                        <div class="flex gap-2">
                            <input type="text" id="kangis_file_no_input"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm font-mono uppercase"
                                placeholder="e.g. KNML 0123"
                                oninput="document.getElementById('kangis_file_no_hidden').value = this.value">
                            @if($isNewKnMode ?? false)
                            <button type="button" onclick="KnModeFileSelector.open('kangis')"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-gray-600 hover:bg-gray-50" title="Select KANGIS File No">
                                <i data-lucide="search" class="h-4 w-4"></i>
                            </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- KN Series input: visible in BOTH non-new_kn mode AND new_kn mode.
                     In new_kn mode this is the primary input for the new KN file number; it bidirectionally syncs with the main File Number field. --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        New KANGIS File No (KN Series) <span class="text-red-500">*</span>
                        @if($isNewKnMode ?? false)
                        <span class="ml-1 text-xs font-normal text-green-700">(this becomes the active file number)</span>
                        @endif
                    </label>
                    <div class="flex gap-2">
                        <div class="relative flex-grow">
                            <input type="text" id="new_kangis_file_no_input" autocomplete="off"
                                class="block w-full px-3 py-2 border border-green-400 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm font-mono font-bold text-green-800 bg-green-50"
                                placeholder="Type to search e.g. KN1, KN100…">
                            <div id="kn-autocomplete-list" class="absolute z-50 w-full bg-white border border-gray-200 rounded-md shadow-lg mt-1 hidden max-h-48 overflow-y-auto"></div>
                        </div>
                        @if($isNewKnMode ?? false)
                        <button type="button" onclick="KnModeFileSelector.open('new_kangis')"
                            class="inline-flex items-center px-3 py-2 border border-green-300 rounded-md text-green-700 bg-green-50 hover:bg-green-100" title="Select New KANGIS (KN) File No">
                            <i data-lucide="search" class="h-4 w-4"></i>
                            <span class="ml-1 text-xs font-medium">Select</span>
                        </button>
                        @endif
                    </div>
                    <p id="kn-fileno-status" class="mt-1 text-xs text-gray-500"></p>
                </div>


            </div>
        </div>
        @endif
    </div>{{-- end: File Number & Tracking ID Card --}}

    <!-- Customer Type & File Title Card -->
    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h4 class="mb-6 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
            <i data-lucide="user" class="h-4 w-4 text-green-600"></i>
            Customer Type & File Title
        </h4>
  @php
    use Illuminate\Support\Facades\DB;

    $customerTypes = DB::connection('sqlsrv')
        ->table('customer_type')
        ->orderBy('customer_type_name')
        ->get();
     @endphp

        <!-- Customer Type -->
 <div class="form-group">
    <label class="block text-sm font-medium text-gray-700 mb-2">
        Customer Type <span class="text-red-500">*</span>
    </label>

    {{-- Dropdown --}}
    <select id="file_type_select"
            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm
                   focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            onchange="handleFileTypeChange(this)"
            required>

        <option value="">Select Customer Type</option>

        @foreach ($customerTypes as $type)
            <option value="{{ $type->customer_type_name }}">
                {{ $type->customer_type_name }}
            </option>
        @endforeach
    </select>

    {{-- Actual value submitted --}}
    <input type="hidden" name="file_type" id="file_type">

    {{-- Shown only when "Other" is selected --}}
    <input type="text"
           id="file_type_other"
           class="mt-2 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm
                  focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm hidden"
           placeholder="Please specify">
</div>



        <script>
    function handleFileTypeChange(select) {
        const hidden = document.getElementById('file_type');
        const other  = document.getElementById('file_type_other');

        if (select.value === 'Other') {
            other.classList.remove('hidden');
            other.required = true;
            hidden.value = '';
        } else {
            other.classList.add('hidden');
            other.required = false;
            hidden.value = select.value;
            other.value = '';
        }

        other.oninput = () => {
            hidden.value = other.value;
        };
    }
</script>



        <!-- Group Title (Block Indexing Only) -->
        <div id="group-title-container" class="hidden mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Block</label>
            <input type="text" value="Group Title" disabled class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-500 sm:text-sm">
        </div>

        <!-- File Title -->
        <div class="form-group mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2 required">File Title</label>
            <template x-for="(param, index) in fileParams" :key="param.id">
                <div class="flex gap-2 mb-2">
                    <input type="text" name="file_title[]" x-model="param.title" 
                        :id="index === 0 ? 'file-title' : 'file-title-' + index"
                        placeholder="Enter file title"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                    
                    <button type="button" @click="addFileParam()" x-show="index === fileParams.length - 1 && indexing_type === 'Block'"
                        class="inline-flex items-center px-3 py-2 border border-blue-600 text-blue-600 rounded-md hover:bg-blue-50" title="Add another file title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    </button>
                    
                    <button type="button" @click="removeFileParam(index)" x-show="fileParams.length > 1"
                         class="inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50" title="Remove this file title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- Holders Card -->
    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h4 class="mb-6 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
            <i data-lucide="users" class="h-4 w-4 text-orange-600"></i>
            Holders
        </h4>

        <div class="space-y-4">
            {{-- Root of Title — the instrument/holder the title springs from, i.e. what
                 happened BEFORE the file was commissioned. It sits above the two holder
                 lines because that is the order the title reads in (Root -> Original ->
                 Current, client spec 2026-08-20 SS12). Required: the indexer is looking at
                 the physical file and is the only one who can state it.

                 Shown, and required, ONLY for the two registry combinations that have a
                 root to state: SLTR (either registry field), and Land Registry with
                 Physical Registry "Registry 3 - Land". syncRootOfTitleVisibility() in
                 create-indexing-dialog.js owns the toggle and the `required` attribute;
                 it starts hidden here so the field never flashes on an unrelated
                 registry before that runs. FileIndexingController applies the same test
                 server-side. --}}
            <div class="form-group hidden" id="root-of-title-group">
                <label for="root-of-title" class="block text-sm font-medium text-gray-700 mb-2">
                    Root of Title <span class="text-red-600">*</span>
                </label>
                <input type="text" name="root_of_title" id="root-of-title"
                    value="{{ isset($record) ? ($record->root_of_title ?? '') : '' }}"
                    placeholder="e.g. Customary Right of Occupancy - ALIYU MUSA"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div class="form-group">
                <label for="original-holder" class="block text-sm font-medium text-gray-700 mb-2">Original Holder</label>
                <div id="original-holders-wrapper" class="space-y-2">
                    @php
                        $raw_o_holders = isset($record) ? $record->original_holder : '';
                        $o_holders = [''];
                        if (is_array($raw_o_holders)) {
                            $o_holders = $raw_o_holders;
                        } elseif (is_string($raw_o_holders) && str_starts_with(trim($raw_o_holders), '[')) {
                            $decoded = json_decode($raw_o_holders, true);
                            $o_holders = is_array($decoded) ? $decoded : [$raw_o_holders];
                        } elseif (!empty($raw_o_holders)) {
                            $o_holders = [$raw_o_holders];
                        }
                    @endphp
                    @foreach($o_holders as $index => $holder)
                        <div class="original-holder-row flex gap-2">
                            <input type="text" name="original_holder[]" value="{{ $holder }}"
                                class="original-holder-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                {{ $index === 0 ? 'id=original-holder' : '' }}>
                            @if($index === 0)
                                <button type="button" id="add-original-holder-btn" 
                                    class="inline-flex items-center px-3 py-2 border border-blue-600 text-blue-600 rounded-md hover:bg-blue-50"
                                    style="display: none;">
                                    <i data-lucide="plus" class="h-4 w-4 text-blue-600"></i>
                                </button>
                            @else
                                <button type="button" class="remove-original-holder-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50">
                                     <i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="form-group">
                <label for="current-holder" class="block text-sm font-medium text-gray-700 mb-2">Current Holder</label>
                <div id="current-holders-wrapper" class="space-y-2">
                    @php
                        $raw_c_holders = isset($record) ? $record->current_holder : '';
                        $holders = [''];
                        if (is_array($raw_c_holders)) {
                            $holders = $raw_c_holders;
                        } elseif (is_string($raw_c_holders) && str_starts_with(trim($raw_c_holders), '[')) {
                            $decoded = json_decode($raw_c_holders, true);
                            $holders = is_array($decoded) ? $decoded : [$raw_c_holders];
                        } elseif (!empty($raw_c_holders)) {
                            $holders = [$raw_c_holders];
                        }
                    @endphp
                    @foreach($holders as $index => $holder)
                        <div class="current-holder-row flex gap-2">
                            <input type="text" name="current_holder[]" value="{{ $holder }}"
                                class="current-holder-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                {{ $index === 0 ? 'id=current-holder' : '' }}>
                            @if($index === 0)
                                <button type="button" id="add-holder-btn" 
                                    class="inline-flex items-center px-3 py-2 border border-blue-600 text-blue-600 rounded-md hover:bg-blue-50"
                                    style="display: none;">
                                    <i data-lucide="plus" class="h-4 w-4 text-blue-600"></i>
                                </button>
                            @else
                                <button type="button" class="remove-holder-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50">
                                     <i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Related File Number Details Card -->
    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <div class="flex justify-between items-center mb-6">
            <h4 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                <i data-lucide="link" class="h-4 w-4 text-amber-600"></i>
                Related File Number
            </h4>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="has-related-file" name="has_related_file" 
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    {{ (isset($record) && $record->has_related_file) ? 'checked' : '' }}>
                <label for="has-related-file" class="text-sm font-medium text-gray-700">Has Related File?</label>
            </div>
        </div>

        <div id="related-files-wrapper" class="{{ (isset($record) && $record->has_related_file) ? '' : 'hidden' }} space-y-4">
            @php
                $relatedFiles = (isset($record) && is_array($record->related_fileno)) ? $record->related_fileno : [isset($record) ? $record->related_fileno : ''];
                if(empty($relatedFiles)) $relatedFiles = [''];
            @endphp
            
            @foreach($relatedFiles as $index => $relFileno)
                <div class="related-file-row flex gap-2">
                    <div class="flex-grow">
                        <input type="text" name="related_fileno[]" value="{{ $relFileno }}"
                            class="related-file-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-50"
                            placeholder="Select related file number" readonly>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="select-related-btn inline-flex items-center px-3 py-2 border border-indigo-600 text-indigo-600 rounded-md hover:bg-indigo-50" title="Select File Number">
                            <i data-lucide="search" class="h-4 w-4 text-indigo-600"></i>
                        </button>
                        <button type="button" class="clear-related-btn inline-flex items-center px-3 py-2 border border-red-600 text-red-600 rounded-md hover:bg-red-50" title="Remove/Clear">
                            <i data-lucide="{{ $index === 0 ? 'eraser' : 'trash-2' }}" class="h-4 w-4 text-red-600"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex justify-between items-center">
            <button type="button" id="add-related-file-btn" class="{{ (isset($record) && $record->has_related_file) ? '' : 'hidden' }} inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i data-lucide="plus" class="h-4 w-4 mr-2 text-gray-700"></i>
                Add Another
            </button>
            
            <button type="button" id="open-related-details-btn" class="inline-flex items-center px-3 py-2 border border-blue-600 text-blue-600 font-medium rounded-md hover:bg-blue-50 transition-colors">
                <i data-lucide="list-plus" class="h-4 w-4 mr-2 text-blue-600"></i>
                Enter Related Details
            </button>
        </div>
    </div>
</div>
