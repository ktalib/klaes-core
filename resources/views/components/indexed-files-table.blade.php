@php
    $config = $config ?? [];
    $tableId = $tableId ?? 'indexed-files-table';
    $searchId = $searchId ?? 'indexed-files-search';
    $perPageId = $perPageId ?? 'indexed-files-per-page';
    $prevId = $prevId ?? 'indexed-files-prev';
    $nextId = $nextId ?? 'indexed-files-next';
    $pageLabelId = $pageLabelId ?? 'indexed-files-page';
    $summaryId = $summaryId ?? 'indexed-files-summary';
    $tbodyId = $tbodyId ?? 'indexed-files-tbody';

    $defaultConfig = [
        'listUrl' => route('indexed-files.api.list'),
        'statsUrl' => route('indexed-files.api.stats'),
        'viewListUrl' => route('indexed-files.api.view-list'),
        'showUrlTemplate' => route('fileindex.show', ['fileindex' => '__ID__']),
        'editUrlTemplate' => url('fileindexing/__ID__/edit'),
        'deleteUrlTemplate' => route('fileindex.destroy', ['fileindex' => '__ID__']),
        'trackingUrlTemplate' => route('fileindexing.tracking-sheet', ['id' => '__ID__']),
        'updateCoordinatesUrlTemplate' => route('indexed-files.api.update-coordinates', ['id' => '__ID__']),
        'isCorrespondingFile' => false,
    ];

    $mergedConfig = array_merge($defaultConfig, $config);

    /*
     | Which actions this viewer may take. The table renders its action menu in JavaScript,
     | so the answers travel in the config rather than through a blade guard.
     |
     | "Move to Indexing Duplicates" and "Master Delete" stay Super-Admin-only: both erase a
     | record outright, and neither has an archive behind it. They previously tested
     | in_array('Supper Admin', ...) against the raw assign_role string, which missed the
     | 'Super Admin' spelling and every account carrying the grant on users.type instead --
     | isSuperAdmin() handles both. The controller re-checks regardless.
     */
    $tableUser = auth()->user();
    $tableIsSuperAdmin = $tableUser && $tableUser->isSuperAdmin();

    $mergedConfig['canMoveToIndexingDuplicates'] = $tableIsSuperAdmin;
    $mergedConfig['canMasterDelete'] = $tableIsSuperAdmin;

    $mergedConfig['canEdit'] = $tableUser && $tableUser->canDo('Indexing', 'edit');
    $mergedConfig['canDelete'] = $tableUser && $tableUser->canDo('Indexing', 'delete');
    $mergedConfig['canPrint'] = $tableUser && $tableUser->canDo('Indexing', 'print');

    $hiddenColumns = $mergedConfig['hiddenColumns'] ?? [];
    $columnLabels = $mergedConfig['columnLabels'] ?? [];
    $hideActions = $mergedConfig['hideActions'] ?? false;

    // Allow per-variant column definitions (order + set) via config
    $defaultColumns = [
        ['key' => 'shelf_location',          'sort' => 'shelf_location',          'default' => 'Shelf/Rack'],
        ['key' => 'file_number',             'sort' => 'file_number',             'default' => 'Kangis FileNo'],
        ['key' => 'kangis_fileno_placeholder','sort' => 'kangis_fileno_placeholder','default' => 'Kangis Placeholder'],
        ['key' => 'new_kangis_file_no',      'sort' => 'new_kangis_file_no',      'default' => 'Newkangis'],
        ['key' => 'related_file_no',         'sort' => null,                      'default' => 'Mls FileNo'],
        ['key' => 'related_fileno_action',   'sort' => null,                      'default' => 'Related FileNo'],
        ['key' => 'corresponding_fileno',    'sort' => null,                      'default' => 'Corresponding FileNo'],
        ['key' => 'file_title',              'sort' => 'file_title',              'default' => 'File Title'],
        ['key' => 'land_use_type',           'sort' => 'land_use_type',           'default' => 'Land Use'],
        ['key' => 'plot_number',             'sort' => 'plot_number',             'default' => 'Plot No'],
        ['key' => 'tp_no',                   'sort' => 'tp_no',                   'default' => 'TP No'],
        ['key' => 'lpkn_no',                 'sort' => 'lpkn_no',                 'default' => 'LPKN No'],
        ['key' => 'district',                'sort' => 'district',                'default' => 'District'],
        ['key' => 'lga',                     'sort' => 'lga',                     'default' => 'LGA'],
        ['key' => 'indexed_by',              'sort' => 'created_at',              'default' => 'Indexed By'],
        ['key' => 'indexed_date',            'sort' => 'created_at',              'default' => 'Indexed Date'],
        ['key' => 'status',                  'sort' => null,                      'default' => 'Status'],
    ];
    $columnDefs = $mergedConfig['columns'] ?? $defaultColumns;
@endphp

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
    <div class="p-4 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Indexed Files</h2>
            <p class="text-sm text-slate-500">Live list of indexed files across the system.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] w-full lg:w-auto">
            <div class="relative">
                <label for="{{ $searchId }}" class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Global search</label>
                <div class="mt-2">
                    <i data-lucide="search" class="absolute left-4 top-[58%] -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none"></i>
                    <input type="search" id="{{ $searchId }}" placeholder="Search file number, registry, land use..." class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200 bg-slate-50/60 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition" autocomplete="off" spellcheck="false">
                </div>
            </div>
            <div class="bg-slate-50/80 border border-slate-100 rounded-2xl px-4 py-3 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Records per page</p>
                    <p class="text-sm text-slate-500">Choose how many results to load</p>
                </div>
                <select id="{{ $perPageId }}" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm font-medium text-slate-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                    <option value="20">20</option>
                    <option value="40">40</option>
                    <option value="60">60</option>
                    <option value="80">80</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    @include('components.gender-legend')

    <div class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="{{ $tableId }}">
                <thead class="bg-gray-100 border-b border-gray-200">
                    <tr> 
                        @foreach ($columnDefs as $col)
                            @if (!in_array($col['key'], $hiddenColumns))
                                @if ($col['key'] === 'sn')
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide w-12">{{ $columnLabels['sn'] ?? 'S/N' }}</th>
                                @else
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 text-xs uppercase tracking-wide" data-column="{{ $col['key'] }}" @if($col['sort']) data-sort="{{ $col['sort'] }}" @endif>{{ $columnLabels[$col['key']] ?? $col['default'] }}</th>
                                @endif
                            @endif
                        @endforeach
                        @if (!$hideActions)
                            <th class="px-4 py-3 text-center font-semibold text-gray-700 text-xs uppercase tracking-wide">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="{{ $tbodyId }}">
                    <tr class="text-center py-8">
                        <td colspan="{{ count($columnDefs) - count(array_intersect(array_column($columnDefs, 'key'), $hiddenColumns)) + ($hideActions ? 0 : 1) }}" class="text-gray-500 py-8">
                            <i data-lucide="loader" class="h-5 w-5 animate-spin inline-block"></i>
                            Loading...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 py-4 px-4 border-t bg-gradient-to-r from-gray-50 to-white" id="indexed-files-pagination">
            <p class="text-sm text-gray-700 font-medium" id="{{ $summaryId }}">Showing <span class="text-blue-600 font-semibold">--</span> of <span class="text-blue-600 font-semibold">--</span> results</p>
            <div class="flex items-center gap-3">
                <button class="btn btn-outline btn-sm hover:bg-gray-100 transition disabled:opacity-50" id="{{ $prevId }}" disabled>
                    <span class="hidden sm:inline">Prev</span>
                </button>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-900 font-bold bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-200 min-w-12 text-center" id="{{ $pageLabelId }}">--</span>
                </div>
                <button class="btn btn-outline btn-sm hover:bg-gray-100 transition disabled:opacity-50" id="{{ $nextId }}" disabled>
                    <span class="hidden sm:inline">Next</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Related Files Modal (shared) -->
<div id="related-files-modal" class="fixed inset-0 z-[100] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-200">
            <div class="bg-slate-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-blue-600 p-2 rounded-lg shadow-sm">
                        <i data-lucide="link" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Related File Numbers</h3>
                        <div class="flex items-center gap-2">
                            <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Associated File Records</p>
                            <span id="parent-file-number-container" class="hidden">
                                <span class="text-[10px] text-slate-300 font-bold px-1">|</span>
                                <span class="text-[10px] text-blue-600 font-bold uppercase tracking-tight">Main: </span>
                                <span id="parent-file-number-badge" class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100">---</span>
                            </span>
                        </div>
                    </div>
                </div>
                <button type="button" id="close-related-modal-btn" class="text-gray-400 hover:text-gray-500 transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
         

            <div class="bg-white px-6 py-6">
                <div class="overflow-x-auto rounded-xl border border-gray-100 shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 border-collapse">
                        <thead>
                            <tr class="bg-slate-50 font-semibold text-gray-700">
                                <th scope="col" class="px-4 py-3 text-left text-xs uppercase tracking-wider border-b border-gray-200">#</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs uppercase tracking-wider border-b border-gray-200">Mls FileNo</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs uppercase tracking-wider border-b border-gray-200">File Title</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs uppercase tracking-wider border-b border-gray-200">Location</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs uppercase tracking-wider border-b border-gray-200">Plot/TP/LPKN</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs uppercase tracking-wider border-b border-gray-200">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="related-files-table-body" class="bg-white divide-y divide-gray-100 italic text-gray-500">
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm">Loading related files...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4 sm:flex sm:flex-row-reverse border-t border-gray-100">
                <button type="button" id="close-related-modal-footer-btn" class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Related File Modal (shared) -->
<div id="edit-related-file-modal" class="fixed inset-0 z-[110] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="edit-related-backdrop"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-gray-100">
            <form id="edit-related-file-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="id" id="edit-related-id">

                <div class="bg-indigo-600 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                            <i data-lucide="edit-3" class="w-5 h-5 text-white"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white">Edit Related File Details</h3>
                    </div>
                    <button type="button" id="close-edit-related-modal" class="text-white/70 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <div class="px-8 py-6 space-y-5">
                    <div class="grid grid-cols-2 gap-5">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">File Number</label>
                            <input type="text" name="file_number" id="edit-related-file-number" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">File Title</label>
                            <input type="text" name="file_title" id="edit-related-file-title" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Property Location</label>
                        <textarea name="location" id="edit-related-location" rows="2" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700 resize-none"></textarea>
                    </div>

                    <div class="grid grid-cols-3 gap-5">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Plot Number</label>
                            <input type="text" name="plot_number" id="edit-related-plot-number" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">TP Number</label>
                            <input type="text" name="tp_no" id="edit-related-tp-no" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">LPKN Number</label>
                            <input type="text" name="lpkn_no" id="edit-related-lpkn-no" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none font-medium text-gray-700">
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-8 py-5 flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100">
                    <button type="button" id="cancel-edit-related" class="px-6 py-2.5 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-200 transition-all">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-500/25 transition-all flex items-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
 
<!-- Temporary File Number Modal -->
<div id="temp-file-modal" class="fixed inset-0 z-[120] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="temp-file-backdrop"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-gray-100">
            <input type="hidden" id="temp-file-id">

            <div class="bg-teal-600 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                        <i data-lucide="file-key" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Has Temporary File</h3>
                        <p class="text-xs text-teal-100 font-medium">Enter the SFS Temporary File Number</p>
                    </div>
                </div>
                <button type="button" id="close-temp-file-modal" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="px-8 py-6 space-y-5">
                <!-- Read-only fields -->
                <div class="grid grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">File No</label>
                        <input type="text" id="temp-file-file-number" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">File Title</label>
                        <input type="text" id="temp-file-file-title" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Plot No</label>
                        <input type="text" id="temp-file-plot-number" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">District</label>
                        <input type="text" id="temp-file-district" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">LGA</label>
                        <input type="text" id="temp-file-lga" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Location</label>
                    <input type="text" id="temp-file-location" readonly class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-100 text-gray-600 font-medium cursor-not-allowed">
                </div>

                <!-- Editable Temporary File Number -->
                <div class="space-y-1.5 pt-2 border-t border-gray-100">
                    <label class="text-xs font-bold text-teal-600 uppercase tracking-wider block">Temporary File Number (SFS) <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-3">
                        <input type="text" id="temp-file-no-input" readonly class="flex-1 px-4 py-3 rounded-xl border-2 border-teal-200 bg-gray-50 text-gray-800 font-semibold text-lg cursor-pointer" placeholder="Click to select file number...">
                        <button type="button" id="open-fileno-selector-btn" class="px-4 py-3 rounded-xl bg-teal-600 text-white font-bold hover:bg-teal-700 transition-all shadow-sm flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="file-search" class="w-4 h-4"></i>
                            Select
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Use the file number selector to choose the SFS temporary file number.</p>
                </div>
            </div>

            <div class="bg-gray-50 px-8 py-5 flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100">
                <button type="button" id="cancel-temp-file" class="px-6 py-2.5 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-200 transition-all">Cancel</button>
                <button type="button" id="submit-temp-file" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-teal-600 hover:bg-teal-700 shadow-lg shadow-teal-500/25 transition-all flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EDMS Files Viewer Modal -->
<div id="edms-files-modal" class="fixed inset-0 z-[130] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true" id="edms-files-backdrop"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-4xl border border-slate-100">
            <!-- Header -->
            <div class="bg-orange-600 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                        <i data-lucide="folder-open" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">Scanned Documents</h3>
                        <p class="text-xs text-orange-100 font-medium">File No: <span id="edms-files-modal-title" class="font-black text-white">—</span></p>
                    </div>
                </div>
                <button type="button" id="close-edms-files-modal" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <!-- Body -->
            <div class="px-6 py-6 max-h-[70vh] overflow-y-auto" id="edms-files-modal-body">
                <!-- Populated by JS -->
            </div>
            <!-- Footer -->
            <div class="bg-slate-50 px-6 py-4 flex justify-end border-t border-slate-100">
                <button type="button" id="close-edms-files-modal-footer" onclick="document.getElementById('edms-files-modal').classList.add('hidden')" class="px-6 py-2.5 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-200 transition-all">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Update KANGIS Placeholder Modal -->
<div id="update-placeholder-modal" class="hidden fixed inset-0 z-[140] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="update-placeholder-backdrop"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-100">
            <input type="hidden" id="update-placeholder-id">

            <div class="bg-purple-600 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                        <i data-lucide="edit-3" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">KANGIS FileNo Placeholder</h3>
                        <p class="text-xs text-purple-100 font-medium">Manage KANGIS FileNo Placeholder</p>
                    </div>
                </div>
                <button type="button" id="close-update-placeholder-modal" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="px-8 py-6 space-y-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-purple-600 uppercase tracking-wider block">KANGIS FileNo Placeholder <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <div class="w-1/3">
                            <select id="update-placeholder-prefix" class="w-full px-3 py-3 rounded-xl border-2 border-purple-100 focus:border-purple-600 focus:ring-0 text-gray-800 font-semibold text-lg bg-white">
                                <option value="">Select Prefix</option>
                                <option value="KN">KN</option>
                                <option value="KNGP">KNGP</option>
                                <option value="KNML">KNML</option>
                                <option value="MLKN">MLKN</option>
                                <option value="MNKL">MNKL</option>
                                <option value="OTHER">OTHER</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <input type="text" id="update-placeholder-serial" class="w-full px-4 py-3 rounded-xl border-2 border-purple-100 focus:border-purple-600 focus:ring-0 text-gray-800 font-semibold text-lg" placeholder="00000">
                        </div>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">Select the KANGIS prefix and enter the serial digits. Example: MLKN 00035</p>
                </div>
            </div>

            <div class="bg-gray-50 px-8 py-5 flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100">
                <button type="button" id="cancel-update-placeholder" class="px-6 py-2.5 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-200 transition-all">Cancel</button>
                <button type="button" id="submit-update-placeholder" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-purple-600 hover:bg-purple-700 shadow-lg shadow-purple-500/25 transition-all flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Update
                </button>
            </div>
        </div>
    </div>
    @include('fileindexing.partial.property_transaction_modal')
</div>

<!-- Location Map (Coordinates) Modal -->
<div id="location-map-modal" class="fixed inset-0 z-[150] hidden overflow-y-auto">
    <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
    <div class="relative mx-auto my-8 w-full max-w-5xl px-4">
        <div class="overflow-hidden rounded-[32px] bg-white shadow-2xl ring-1 ring-slate-200">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-8 py-5">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Map Coordinates</p>
                    <h2 id="location-map-modal-title" class="text-lg font-bold text-slate-900">Update Lat / Lon</h2>
                </div>
                <button type="button" class="location-map-close-btn inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="space-y-6 p-8">
                <div class="grid gap-6 sm:grid-cols-3">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">File Number</label>
                        <p id="location-map-file-number" class="mt-2 text-sm font-semibold text-slate-900"></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Location</label>
                        <p id="location-map-file-title" class="mt-2 text-sm text-slate-700"></p>
                    </div>
                </div>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="location-map-latitude" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Latitude</label>
                        <input id="location-map-latitude" type="text" inputmode="decimal" step="any"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 font-mono" />
                    </div>
                    <div>
                        <label for="location-map-longitude" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Longitude</label>
                        <input id="location-map-longitude" type="text" inputmode="decimal" step="any"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 font-mono" />
                    </div>
                </div>
                <div class="rounded-3xl border border-slate-200 overflow-hidden bg-slate-50">
                    <div class="flex items-center justify-between p-4 border-b border-slate-200 bg-white">
                        <p class="text-sm font-semibold text-slate-700">📍 Map Preview</p>
                        <span class="text-[10px] text-slate-400 font-medium">Drag the marker to adjust coordinates</span>
                    </div>
                    <div id="location-map-preview" class="min-h-[28rem] bg-slate-100 relative z-10"></div>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p id="location-map-helper" class="text-sm text-slate-500">Drag the marker or type coordinates to pinpoint the location.</p>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="location-map-close-btn inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Close
                        </button>
                        <button id="location-map-save-btn" type="button" class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Save Coordinates
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    {{-- Shared cross-module Property Timeline card, opened by the action menu's
         "View File Transactions" item. --}}
    <script src="{{ asset('js/property-timeline-modal.js') }}"></script>
    {{-- Two-step typed confirmation shared with the Recommendation / RofO master
         deletes, used by the action menu's "Master Delete" item. --}}
    <script src="{{ asset('js/master-delete.js') }}"></script>
    {{-- The File Snapshot card, reused by the action menu's "View Details" item so
         it shows the same card the indexing form shows after a save. --}}
    <script src="{{ asset('js/fileindexing/file-snapshot-card.js') }}?v={{ @filemtime(public_path('js/fileindexing/file-snapshot-card.js')) }}"></script>
    <script>
        window.indexedFilesConfig = Object.assign({}, @json($mergedConfig));
    </script>
    <script type="module" src="{{ asset('js/indexed-files/index.js') }}?v={{ filemtime(public_path('js/indexed-files/index.js')) }}"></script>
@endpush
 