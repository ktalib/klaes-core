@extends('layouts.app')

@section('page-title')
    {{ __('SLTR Indexed Files') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-50/60">
        @include('admin.header')

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold text-violet-600 uppercase tracking-widest">SLTR</p>
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mt-1">Indexed Files</h1>
                        <p class="text-sm text-slate-500 mt-2">
                            Search and review SLTR indexed file records only.
                        </p>
                    </div> 
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gradient-to-br from-violet-50 to-violet-100 rounded-2xl border border-violet-200 shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-violet-700 uppercase tracking-widest">Total Files</p>
                            <h3 class="text-3xl md:text-4xl font-bold text-violet-900 mt-2" id="total-sltr-files">--</h3>
                            <p class="text-sm text-violet-700 mt-2">SLTR Indexed Files</p>
                        </div>
                        <div class="bg-violet-200 rounded-full p-3">
                            <i data-lucide="file-search" class="h-8 w-8 text-violet-700"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-2xl border border-emerald-200 shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-emerald-700 uppercase tracking-widest">Today's Indexing</p>
                            <h3 class="text-3xl md:text-4xl font-bold text-emerald-900 mt-2" id="daily-sltr-files">--</h3>
                            <p class="text-sm text-emerald-700 mt-2">Files Indexed Today</p>
                        </div>
                        <div class="bg-emerald-200 rounded-full p-3">
                            <i data-lucide="calendar-days" class="h-8 w-8 text-emerald-700"></i>
                        </div>
                    </div>
                </div>
            </div>

            @include('components.indexed-files-table', ['config' => [
                'registry' => 'SLTR',
                'tableVariant' => 'sltr',
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
                'hiddenColumns' => ['related_file_no', 'related_fileno_action', 'registry', 'corresponding_fileno', 'kangis_fileno_placeholder', 'new_kangis_file_no'],
                'columnLabels' => [
                    'file_number' => 'SLTR FileNo',
                ],
                'hideActions' => true,
            ]])
        </div>

        @include('admin.footer')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statsUrl = new URL("{{ route('indexed-files.api.stats') }}", window.location.origin);
            statsUrl.searchParams.set('registry', 'SLTR');

            fetch(statsUrl.toString())
                .then(response => response.json())
                .then(data => {
                    const totalElement = document.getElementById('total-sltr-files');
                    const dailyElement = document.getElementById('daily-sltr-files');
                    const stats = data && data.data ? data.data : null;

                    if (!stats) {
                        return;
                    }

                    if (totalElement) {
                        totalElement.textContent = Number(stats.total_indexed || 0).toLocaleString();
                    }

                    if (dailyElement) {
                        dailyElement.textContent = Number(stats.indexed_today || 0).toLocaleString();
                    }
                })
                .catch(error => console.error('Error loading SLTR stats:', error));
        });
    </script>
@endsection
