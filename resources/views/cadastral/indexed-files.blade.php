@extends('layouts.app')

@section('page-title')
    {{ __('Cadastral Indexed Files') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-50/60">
        @include('admin.header')

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold text-orange-600 uppercase tracking-widest">Cadastral</p>
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mt-1">Indexed Files</h1>
                        <p class="text-sm text-slate-500 mt-2">
                            Search and review Cadastral indexed file records (corresponding files).
                        </p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-2xl border border-orange-200 shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-orange-600 uppercase tracking-widest">Total Files</p>
                            <h3 class="text-3xl md:text-4xl font-bold text-orange-900 mt-2" id="total-cadastral-files">--</h3>
                            <p class="text-sm text-orange-700 mt-2">Cadastral Indexed Files</p>
                        </div>
                        <div class="bg-orange-200 rounded-full p-3">
                            <i data-lucide="map" class="h-8 w-8 text-orange-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl border border-green-200 shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-green-600 uppercase tracking-widest">Today's Indexing</p>
                            <h3 class="text-3xl md:text-4xl font-bold text-green-900 mt-2" id="daily-cadastral-files">--</h3>
                            <p class="text-sm text-green-700 mt-2">Files Indexed Today</p>
                        </div>
                        <div class="bg-green-200 rounded-full p-3">
                            <i data-lucide="calendar-days" class="h-8 w-8 text-green-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            @include('components.indexed-files-table', ['config' => [
                'isCorrespondingFile' => true,
                'tableVariant' => 'cadastral',
                'columns' => [
                    ['key' => 'sn',                  'sort' => null,           'default' => 'S/N'],
                    ['key' => 'shelf_location',       'sort' => 'shelf_location', 'default' => 'Shelf/Rack'],
                    ['key' => 'file_title',           'sort' => 'file_title',   'default' => 'File Title'],
                    ['key' => 'file_number',          'sort' => 'file_number',  'default' => 'File Number'],
                    ['key' => 'corresponding_fileno', 'sort' => null,           'default' => 'Corresponding FileNo'],
                    ['key' => 'related_fileno_action','sort' => null,           'default' => 'Related FileNo'],
                    ['key' => 'land_use_type',        'sort' => 'land_use_type','default' => 'Land Use'],
                    ['key' => 'plot_number',          'sort' => 'plot_number',  'default' => 'Plot No'],
                    ['key' => 'tp_no',                'sort' => 'tp_no',        'default' => 'TP No'],
                    ['key' => 'lpkn_no',              'sort' => 'lpkn_no',      'default' => 'LPKN No'],
                    ['key' => 'district',             'sort' => 'district',     'default' => 'District'],
                    ['key' => 'lga',                  'sort' => 'lga',          'default' => 'LGA'],
                    ['key' => 'indexed_by',           'sort' => 'created_at',   'default' => 'Indexed By'],
                    ['key' => 'indexed_date',         'sort' => 'created_at',   'default' => 'Indexed Date'],
                    ['key' => 'status',               'sort' => null,           'default' => 'Status'],
                ],
                'hiddenColumns' => [],
                'columnLabels' => [
                    'file_number' => 'File Number',
                    'corresponding_fileno' => 'Corresponding FileNo',
                    'related_fileno_action' => 'Related FileNo',
                ],
                'hideActions' => false,
            ]])
        </div>

        @include('admin.footer')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statsUrl = new URL("{{ route('indexed-files.api.stats') }}", window.location.origin);
            statsUrl.searchParams.set('is_corresponding_file', '1');

            fetch(statsUrl.toString())
                .then(response => response.json())
                .then(data => {
                    const totalElement = document.getElementById('total-cadastral-files');
                    const dailyElement = document.getElementById('daily-cadastral-files');
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
                .catch(error => console.error('Error loading Cadastral stats:', error));
        });
    </script>
@endsection
