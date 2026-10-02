@extends('layouts.app')

@section('page-title')
    {{ __('KANGIS Indexed Files') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-50/60">
        @include('admin.header')

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold text-amber-600 uppercase tracking-widest">KANGIS</p>
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mt-1">Indexed Files</h1>
                        <p class="text-sm text-slate-500 mt-2">
                            Search and review KANGIS indexed file records.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl border border-blue-200 shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-blue-600 uppercase tracking-widest">Total Files</p>
                            <h3 class="text-3xl md:text-4xl font-bold text-blue-900 mt-2" id="total-kangis-files">--</h3>
                            <p class="text-sm text-blue-700 mt-2">KANGIS Indexed Files</p>
                        </div>
                        <div class="bg-blue-200 rounded-full p-3">
                            <i data-lucide="file-text" class="h-8 w-8 text-blue-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl border border-green-200 shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-green-600 uppercase tracking-widest">Today's Indexing</p>
                            <h3 class="text-3xl md:text-4xl font-bold text-green-900 mt-2" id="daily-kangis-files">--</h3>
                            <p class="text-sm text-green-700 mt-2">Files Indexed Today</p>
                        </div>
                        <div class="bg-green-200 rounded-full p-3">
                            <i data-lucide="calendar-today" class="h-8 w-8 text-green-600"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Manage KANGIS Placeholder Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow hidden">
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                            <i data-lucide="edit-3" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white">KANGIS FileNo Placeholder</h3>
                            <p class="text-xs text-purple-100 font-medium">Manage KANGIS FileNo Placeholder</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <p class="text-sm text-slate-500 mb-4">
                        Search for an indexed file by its KANGIS number and update its physical placeholder mapping.
                    </p>
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1 relative">
                            <input type="text" id="quick-placeholder-search" placeholder="Enter KANGIS File No (e.g. MLKN 1)" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 transition outline-none">
                        </div>
                        <button type="button" id="quick-placeholder-btn" class="px-6 py-3 bg-purple-600 text-white font-bold rounded-xl hover:bg-purple-700 transition shadow-lg shadow-purple-200 flex items-center justify-center gap-2">
                            <i data-lucide="search" class="w-4 h-4"></i>
                            Manage
                        </button>
                    </div>
                </div>
            </div>

            @include('components.indexed-files-table', ['config' => [
                'registry' => 'KANGIS',
                'tableVariant' => 'kangis',
                'columns' => [
                    ['key' => 'shelf_location',            'sort' => 'shelf_location',            'default' => 'Shelf/Rack'],
                    ['key' => 'file_number',              'sort' => 'file_number',              'default' => 'Kangis FileNo'],
                    ['key' => 'kangis_fileno_placeholder','sort' => 'kangis_fileno_placeholder','default' => 'Kangis Placeholder'],
                    ['key' => 'new_kangis_file_no',       'sort' => 'new_kangis_file_no',       'default' => 'Newkangis'],
                    ['key' => 'related_file_no',          'sort' => null,                       'default' => 'Mls FileNo'],
                    ['key' => 'related_fileno_action',    'sort' => null,                       'default' => 'Related FileNo'],
                    ['key' => 'corresponding_fileno',     'sort' => null,                       'default' => 'Corresponding FileNo'],
                    ['key' => 'dciv_fileno',              'sort' => null,                       'default' => 'DCIV FileNo'],
                    ['key' => 'file_title',               'sort' => 'file_title',               'default' => 'File Title'],
                    ['key' => 'land_use_type',            'sort' => 'land_use_type',            'default' => 'Land Use'],
                    ['key' => 'plot_number',              'sort' => 'plot_number',              'default' => 'Plot No'],
                    ['key' => 'tp_no',                    'sort' => 'tp_no',                    'default' => 'TP No'],
                    ['key' => 'lpkn_no',                  'sort' => 'lpkn_no',                  'default' => 'LPKN No'],
                    ['key' => 'district',                 'sort' => 'district',                 'default' => 'District'],
                    ['key' => 'lga',                      'sort' => 'lga',                      'default' => 'LGA'],
                    ['key' => 'indexed_by',               'sort' => 'created_at',               'default' => 'Indexed By'],
                    ['key' => 'indexed_date',             'sort' => 'created_at',               'default' => 'Indexed Date'],
                    ['key' => 'dciv_reason',              'sort' => null,                       'default' => 'DCIV Reason'],
                    ['key' => 'lon_value',                'sort' => null,                       'default' => 'Lon'],
                    ['key' => 'lat_value',                'sort' => null,                       'default' => 'Lat'],
                    ['key' => 'latlon',                   'sort' => null,                       'default' => 'Map'],
                    ['key' => 'status',                   'sort' => null,                       'default' => 'Status'],
                ],
                'hiddenColumns' => ['general_registry', 'registry', 'registry_batch_no', 'sys_batch_no', 'batch_no', 'corresponding_fileno', 'new_kangis_file_no'],
                'columnLabels' => [
                    'file_number' => 'Newkangis',
                    'kangis_fileno_placeholder' => 'Kangis FileNo',
                    'related_file_no' => 'Mls FileNo',
                    'related_fileno_action' => 'Related FileNo',
                    'file_title' => 'File Title',
                ],
                'hideActions' => false,
                'trackingUrlTemplate' => route('fileindexing.kangis-tracking-sheet') . '?files=__ID__',
                // KANGIS rows edit through the KANGIS-only update flow, which pins the
                // "_N" file number. Every other registry keeps /fileindexing/{id}/edit.
                'editUrlTemplate' => url('kangis/file-index/__ID__/edit'),
            ]])
        </div>

        @include('admin.footer')
    </div>

    <script>
        // Load KANGIS indexed files stats
        document.addEventListener('DOMContentLoaded', function() {
            const statsUrl = new URL("{{ route('indexed-files.api.stats') }}", window.location.origin);
            statsUrl.searchParams.set('registry', 'KANGIS');

            fetch(statsUrl.toString())
                .then(response => response.json())
                .then(data => {
                    const totalElement = document.getElementById('total-kangis-files');
                    const dailyElement = document.getElementById('daily-kangis-files');
                    
                    if (data.data) {
                        if (totalElement && data.data.total_indexed) {
                            totalElement.textContent = data.data.total_indexed.toLocaleString();
                        }
                        
                        if (dailyElement && data.data.indexed_today) {
                            dailyElement.textContent = data.data.indexed_today.toLocaleString();
                        }
                    }
                })
                .catch(error => console.error('Error loading stats:', error));
        });

        // Quick Manage KANGIS Placeholder logic
        document.getElementById('quick-placeholder-btn')?.addEventListener('click', async () => {
            const btn = document.getElementById('quick-placeholder-btn');
            const fileNo = document.getElementById('quick-placeholder-search').value.trim();
            if (!fileNo) {
                Swal.fire({ icon: 'warning', title: 'Input Required', text: 'Please enter a KANGIS File Number or Placeholder.' });
                return;
            }
            
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i><span>Searching...</span>';
            btn.disabled = true;
            if (window.lucide) window.lucide.createIcons();

            try {
                const response = await fetch("{{ route('indexed-files.find') }}?file_number=" + encodeURIComponent(fileNo), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();
                
                if (result.success && result.data) {
                    // Check if handleUpdatePlaceholder is available (it should be from indexed-files-table component)
                    if (typeof handleUpdatePlaceholder === 'function') {
                        const dummyBtn = document.createElement('button');
                        dummyBtn.setAttribute('data-file-id', result.data.id);
                        dummyBtn.setAttribute('data-placeholder', result.data.kangis_fileno_placeholder || '');
                        handleUpdatePlaceholder(dummyBtn);
                    } else {
                        console.error('handleUpdatePlaceholder function not found. Table component might not be ready.');
                        Swal.fire({ icon: 'error', title: 'Error', text: 'System component not ready. Please refresh.' });
                    }
                } else {
                    Swal.fire({ icon: 'error', title: 'Not Found', text: result.message || 'No indexed file found with that number.' });
                }
            } catch (error) {
                console.error('Error finding file:', error);
                Swal.fire({ icon: 'error', title: 'Error', text: 'An error occurred while searching.' });
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                if (window.lucide) window.lucide.createIcons();
            }
        });
    </script>
@endsection
