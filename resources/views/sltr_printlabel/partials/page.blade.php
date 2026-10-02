<div class="p-6">
    <div class="container mx-auto py-6 space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">SLTR Print File Labels</h1>
                <p class="text-gray-600 mt-1">Generate and print labels for SLTR physical files</p>
            </div>
            <div class="flex gap-2">
                <button
                    id="resetBtn"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    type="button"
                >
                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                </button>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('resetBtn').addEventListener('click', function() {
                        location.reload();
                    });
                });
                </script>
                <button
                    id="printBtn"
                    class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    Print Labels
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg border p-6">
                <div class="pb-2">
                    <h3 class="text-sm font-medium text-gray-700">Selected Files</h3>
                </div>
                <div class="text-2xl font-bold" id="selectedFilesCount">0</div>
                <p class="text-xs text-gray-500 mt-1">Files selected for label printing</p>
            </div>
            <div class="bg-white rounded-lg border p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-semibold">Printer Status</h3>
                        <p class="text-sm text-gray-600">Label printer status</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Online
                    </span>
                </div>
                <div class="text-2xl font-bold flex items-center mt-2">Ready</div>
            </div>
        </div>

        <div class="w-full">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button
                        class="tab-btn active border-b-2 border-blue-500 py-2 px-1 text-sm font-medium text-blue-600"
                        data-tab="files"
                    >
                        Select Files
                    </button>
                    <button
                        class="tab-btn border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        data-tab="generated"
                    >
                        Generated Batches
                    </button>
                    <button
                        class="tab-btn border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        data-tab="settings"
                    >
                        Label Settings
                    </button>
                    <button
                        class="tab-btn border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        data-tab="preview"
                    >
                        Preview & Print
                    </button>
                </nav>
            </div>

            {{-- ====== SELECT FILES TAB ====== --}}
            <div id="files-tab" class="tab-content active mt-6">
                <div class="bg-white rounded-lg border">
                    <div class="p-6 border-b bg-gradient-to-r from-slate-50 to-white">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold">Select SLTR Files for Labels</h3>
                                <p class="text-sm text-gray-600">Select one or more sub prefixes and an optional digit rank to load indexed SLTR files</p>
                            </div>
                            <div class="flex flex-col md:flex-row gap-3 items-center w-full md:w-auto">
                                <div class="relative w-full md:w-64">
                                    <i data-lucide="search" class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400"></i>
                                    <input
                                        type="search"
                                        id="searchInput"
                                        placeholder="Search files..."
                                        class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                            <span class="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1 font-medium text-blue-700">
                                <i data-lucide="gauge" class="h-3.5 w-3.5"></i>
                                Freestyle Capacity
                            </span>
                            <span class="leading-relaxed">Each shelf has unlimited capacity. The counter tracks how many files are currently occupying this label.</span>
                        </div>
                    </div>
                    <div class="p-6">
                        {{-- SLTR Batch Number + Rack/Shelf Selectors --}}
                        <div class="rounded-lg border border-blue-200 bg-blue-50/30 px-4 py-4 mb-6">
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-7">
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Prefix</span>
                                    <input
                                        type="text"
                                        id="sltrPrefixDisplay"
                                        value="SLTR"
                                        readonly
                                        class="mt-1 w-full rounded-md border border-gray-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700"
                                    />
                                    <span class="mt-1 text-xs text-slate-500">Fixed SLTR prefix.</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Sub Prefix</span>
                                    <select
                                        id="sltrSubPrefixSelect"
                                        multiple
                                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 select2"
                                    >
                                    </select>
                                    <span class="mt-1 text-xs text-slate-500">Pick one or more <code>sub_prefix</code> values.</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Digit Rank</span>
                                    <select
                                        id="digitRankSelect"
                                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    >
                                        <option value="">All Ranks</option>
                                        <option value="5">5 Digits</option>
                                        <option value="6">6 Digits</option>
                                    </select>
                                    <span class="mt-1 text-xs text-slate-500">Sort/filter by SLTR serial length.</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Rack</span>
                                    <select
                                        id="rackPrimarySelect"
                                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    >
                                        @foreach(range('A', 'Z') as $letter)
                                            <option value="{{ $letter }}">{{ $letter }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Backup Rack</span>
                                    <select
                                        id="rackSecondarySelect"
                                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    >
                                        <option value="">None</option>
                                        @foreach(range('A', 'Z') as $letter)
                                            <option value="{{ $letter }}">{{ $letter }}</option>
                                        @endforeach
                                    </select>
                                    <span class="mt-1 text-xs text-slate-500">Optional secondary rack.</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Shelf</span>
                                    <select
                                        id="shelfNumberSelect"
                                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    >
                                        @for($i = 1; $i <= 100; $i++)
                                            <option value="{{ $i }}">{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Full Label</span>
                                    <input
                                        type="text"
                                        id="fullLabelInput"
                                        value="A1"
                                        readonly
                                        class="mt-1 w-full rounded-md border border-gray-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700"
                                    />
                                    <span class="mt-1 text-xs text-slate-500">Auto-generated from rack &amp; shelf.</span>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Label Capacity</span>
                                            <p class="text-sm text-slate-600 mt-1">Track how many files are already occupying this label.</p>
                                        </div>
                                        <div class="text-right">
                                            <div id="rackLabelCounterDisplay" class="text-lg font-semibold text-blue-600">0</div>
                                            <span id="rackLabelStatusText" class="text-xs text-slate-500">Awaiting selection</span>
                                        </div>
                                    </div>
                                    <div class="mt-3 text-sm text-slate-700">
                                        <span class="font-semibold">Active Label:</span>
                                        <span id="fullLabelValue" class="ml-1">A1</span>
                                    </div>
                                </div>
                                <label for="excludeAssignedToggle" class="flex cursor-pointer items-start gap-3 rounded-lg border border-dashed border-slate-200 bg-white px-3 py-3 text-xs text-slate-600">
                                    <input
                                        type="checkbox"
                                        id="excludeAssignedToggle"
                                        class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span>
                                        <span class="block font-semibold uppercase tracking-wide text-[11px] text-slate-500">Skip Assigned Shelves</span>
                                        <span>Enable to ignore files that already have a <code>shelf_rack</code> so you only load unassigned files.</span>
                                    </span>
                                </label>
                            </div>

                            {{-- Sub group division --}}
                            <div id="subGroupCard" class="mt-4 rounded-lg border border-amber-200 bg-amber-50/40 p-4">
                                <label for="subGroupToggle" class="flex cursor-pointer items-start gap-3">
                                    <input
                                        type="checkbox"
                                        id="subGroupToggle"
                                        class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-xs text-slate-600">
                                        <span class="block font-semibold uppercase tracking-wide text-[11px] text-slate-500">Sub Group</span>
                                        <span>Divide the files indexed under this group serially into sub groups, then assign a shelf/rack to each one.</span>
                                    </span>
                                </label>
                                <p id="subGroupDisabledNote" class="mt-2 hidden text-xs font-medium text-amber-700">
                                    Serial sub groups are unavailable while shelves are assigned per sub prefix.
                                </p>
                                <div id="subGroupControls" class="mt-3 hidden">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Number of Sub Groups</span>
                                            <input
                                                type="number"
                                                id="subGroupCountInput"
                                                min="2"
                                                step="1"
                                                value="2"
                                                class="mt-1 w-40 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                            />
                                            <span class="mt-1 text-xs text-slate-500">Minimum of 2.</span>
                                        </div>
                                        <p id="subGroupSummary" class="text-xs text-slate-600 pb-1">
                                            Click <strong>Load Records</strong> to count the files and divide them.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="text-xs text-slate-500 sm:text-left">
                                    Select one or more sub prefixes, choose a digit rank if needed, then click <strong>Load Records</strong>.
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <button
                                        id="assignSubPrefixBtn"
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-50"
                                    >
                                        <i data-lucide="layout-grid" class="h-4 w-4"></i>
                                        <span id="assignSubPrefixBtnLabel">Assign Shelves per Sub Prefix</span>
                                    </button>
                                    <button
                                        id="generateBatchBtn"
                                        class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                                    >
                                        Load Records
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Sub group shelf/rack assignment --}}
                        <div id="subGroupPanel" class="hidden mb-6 rounded-md border border-amber-200">
                            <div class="flex flex-col gap-1 border-b border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h4 id="subGroupPanelTitle" class="text-sm font-semibold text-slate-800">Sub Group Shelf/Rack Assignment</h4>
                                    <p id="subGroupPanelSubtitle" class="text-xs text-slate-600">Each sub group holds a serial slice of the loaded files. Assign its shelf/rack below.</p>
                                </div>
                                <span id="subGroupPanelMeta" class="text-xs font-medium text-slate-600"></span>
                            </div>
                            <div id="subGroupList" class="divide-y"></div>
                        </div>

                        {{-- File list --}}
                        <div id="fileList" class="rounded-md border">
                            <div class="p-3 bg-gray-50 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="selectAll"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <label for="selectAll" class="text-sm font-medium">Select All</label>
                                </div>
                                <div class="flex items-center gap-2 text-sm text-gray-500" id="selectionStatusWrapper">
                                    <i data-lucide="list-checks" class="hidden sm:block h-4 w-4 text-gray-400"></i>
                                    <span id="selectionStatus">0 of 0 selected</span>
                                </div>
                            </div>
                            <div class="divide-y max-h-96 overflow-y-auto" id="fileListContent">
                                <div class="p-8 text-center text-gray-500">
                                    <div class="mb-2">
                                        <i data-lucide="file-text" class="h-8 w-8 mx-auto text-gray-400"></i>
                                    </div>
                                    <p>No files loaded yet.</p>
                                    <p class="text-xs text-gray-400 mt-1">Select a sub prefix and click Load Records.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-t flex justify-between">
                        <div></div>
                        <div class="flex gap-2">
                            <button
                                id="duplicateBtn"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 gap-2 inline-flex items-center"
                            >
                                <i data-lucide="copy" class="h-4 w-4"></i>
                                Duplicate
                            </button>
                            <button
                                id="continueToSettingsBtn"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 gap-2 inline-flex items-center"
                            >
                                <i data-lucide="settings" class="h-4 w-4"></i>
                                Continue to Label Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ====== GENERATED BATCHES TAB ====== --}}
            <div id="generated-tab" class="tab-content mt-6">
                <div class="bg-white rounded-lg border">
                    <div class="p-6 border-b">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold">Generated Label Batches</h3>
                                <p class="text-sm text-gray-600">View and manage generated SLTR label batches</p>
                            </div>
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    id="batchSearchInput"
                                    placeholder="Search batch number..."
                                    class="border border-gray-300 rounded-md px-3 py-2 text-sm w-64"
                                />
                                <select id="statusFilter" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <option value="">All Status</option>
                                    <option value="generated">Generated</option>
                                    <option value="printed">Printed</option>
                                    <option value="completed">Completed</option>
                                </select>
                                <button
                                    id="refreshBatchesBtn"
                                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-blue-700">Total Batches</div>
                                <div class="text-2xl font-bold text-blue-900" id="totalBatchesCount">0</div>
                            </div>
                            <div class="bg-green-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-green-700">Generated</div>
                                <div class="text-2xl font-bold text-green-900" id="generatedBatchesCount">0</div>
                            </div>
                            <div class="bg-yellow-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-yellow-700">Printed</div>
                                <div class="text-2xl font-bold text-yellow-900" id="printedBatchesCount">0</div>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-purple-700">Completed</div>
                                <div class="text-2xl font-bold text-purple-900" id="completedBatchesCount">0</div>
                            </div>
                        </div> 

                        <div id="batchList" class="rounded-md border">
                            <div class="p-3 bg-gray-50">
                                <div class="grid grid-cols-10 gap-4 text-sm font-medium">
                                    <div class="truncate min-w-0">Batch Number</div>
                                    <div>Shelf/Rack</div>
                                    <div>Digit Rank</div>
                                    <div>Group</div>
                                    <div>Created Date</div>
                                    <div>Files Count</div>
                                    <div>Format</div>
                                    <div>Status</div>
                                    <div>Created By</div>
                                    <div>Actions</div>
                                </div>
                            </div>
                            <div class="divide-y max-h-96 overflow-y-auto" id="batchListContent">
                                <div class="p-8 text-center text-gray-500">
                                    <div class="mb-2">
                                        <i data-lucide="package" class="h-8 w-8 mx-auto text-gray-400"></i>
                                    </div>
                                    <p>Loading batches...</p>
                                </div>
                            </div>
                        </div>

                        <div id="batchPagination" class="mt-4 flex items-center justify-between">
                            <div class="text-sm text-gray-500" id="batchPaginationInfo">
                                Showing 0 to 0 of 0 results
                            </div>
                            <div class="flex items-center gap-2" id="batchPaginationControls"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ====== LABEL SETTINGS TAB ====== --}}
            <div id="settings-tab" class="tab-content mt-6">
                <div class="bg-white rounded-lg border">
                    <div class="p-6 border-b">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold">Label Settings</h3>
                                <p class="text-sm text-gray-600">Configure label printing options</p>
                            </div>
                            <button
                                id="advancedToggle"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Show Advanced
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="labelTemplate" class="block text-sm font-medium text-gray-700">Label Template</label>
                                    <select
                                        id="labelTemplate"
                                        class="mt-1.5 w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="30-in-1">Labels with File No and Shelf/rack</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="labelSize" class="block text-sm font-medium text-gray-700">Label Size</label>
                                    <select
                                        id="labelSize"
                                        class="mt-1.5 w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="30-in-1">A4 Template (1" x 0.8")</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Label Format</label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div
                                            class="label-format-option border rounded-md p-3 flex flex-col items-center cursor-not-allowed opacity-50 pointer-events-none"
                                            data-format="barcode"
                                        >
                                            <i data-lucide="bar-chart-4" class="h-8 w-8 mb-2"></i>
                                            <span class="text-sm font-medium">Barcode</span>
                                        </div>
                                        <div
                                            class="label-format-option selected border rounded-md p-3 flex flex-col items-center cursor-pointer"
                                            data-format="qrcode"
                                        >
                                            <i data-lucide="qr-code" class="h-8 w-8 mb-2"></i>
                                            <span class="text-sm font-medium">QR Code</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Orientation</label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div
                                            class="orientation-option selected border rounded-md p-3 flex items-center cursor-pointer"
                                            data-orientation="portrait"
                                        >
                                            <input type="radio" name="orientation" value="portrait" class="mr-2" checked />
                                            <label class="cursor-pointer font-medium">Portrait</label>
                                        </div>
                                        <div
                                            class="orientation-option border rounded-md p-3 flex items-center cursor-not-allowed opacity-60 pointer-events-none"
                                            data-orientation="landscape"
                                            data-disabled="true"
                                            aria-disabled="true"
                                            title="Landscape layout is currently unavailable"
                                        >
                                            <input type="radio" name="orientation" value="landscape" class="mr-2" disabled />
                                            <label>Landscape</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="advancedOptions" class="space-y-4" style="display: none">
                                <div>
                                    <label for="margin" class="block text-sm font-medium text-gray-700">Margin (inches)</label>
                                    <input
                                        type="number"
                                        id="margin"
                                        value="0.25"
                                        step="0.01"
                                        class="mt-1.5 w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                <div>
                                    <label for="dpi" class="block text-sm font-medium text-gray-700">DPI</label>
                                    <input
                                        type="number"
                                        id="dpi"
                                        value="300"
                                        class="mt-1.5 w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label for="copies" class="block text-sm font-medium text-gray-700">Number of Copies</label>
                                <input
                                    type="number"
                                    id="copies"
                                    min="1"
                                    value="1"
                                    class="mt-1.5 w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-t flex justify-between">
                        <button
                            id="backToFilesBtn"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Back to File Selection
                        </button>
                        <button
                            id="continueToPreviewBtn"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 gap-2 inline-flex items-center"
                        >
                            <i data-lucide="download" class="h-4 w-4"></i>
                            Continue to Preview
                        </button>
                    </div>
                </div>
            </div>

            {{-- ====== PREVIEW & PRINT TAB ====== --}}
            @include('sltr_printlabel.partials.preview')
        </div>
    </div>
</div>
