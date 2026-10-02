@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('KLAES') }}
@endsection

@section('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
@endsection

@section('content')
@php
    $totalUnits = $unitApplications->count();
    $primaryMemoReady = $unitApplications->where('has_st_memo', true)->count();
    $uploadedMemos = $unitApplications->where('has_unit_memo_upload', true)->count();
@endphp

<style>
    .action-toggle {
        border: none;
        background: none;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 9999px;
        transition: background 0.15s ease;
    }

    .action-toggle:hover {
        background-color: #f3f4f6;
    }

    .action-menu {
        position: absolute;
        right: 0;
        margin-top: 0.5rem;
        min-width: 12rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        box-shadow: 0 10px 25px -10px rgba(30, 41, 59, 0.15);
        padding: 0.5rem 0;
        display: none;
        z-index: 2000;
    }

    .action-menu.show { display: block; }

    .action-menu--floating {
        position: fixed;
        right: auto;
        left: 0;
        margin-top: 0;
        z-index: 3000;
    }

    .action-item {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        color: #374151;
        background: none;
        border: none;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }

    .action-item:hover {
        background-color: #f9fafb;
        color: #111827;
    }

    .action-item.disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }

    .stat-card {
        background: linear-gradient(135deg, #f9fafb 0%, #eef2ff 100%);
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1.25rem;
        min-width: 12rem;
    }

    #uploadMemoModal.hidden { display: none; }

    #unitSchemeTable td {
        white-space: nowrap;
        font-size: 0.875rem;
        vertical-align: top;
    }

    #unitSchemeTable th {
        white-space: nowrap;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.15rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.2;
    }

    .badge--primary {
        background-color: #eef2ff;
        color: #3730a3;
    }

    .badge--secondary {
        background-color: #f1f5f9;
        color: #0f172a;
    }

    .badge--success {
        background-color: #dcfce7;
        color: #047857;
    }

    .badge--warning {
        background-color: #fef3c7;
        color: #92400e;
    }

    .badge--danger {
        background-color: #fee2e2;
        color: #b91c1c;
    }

    .badge--info {
        background-color: #e0f2fe;
        color: #0369a1;
    }

    .badge--muted {
        background-color: #f3f4f6;
        color: #4b5563;
    }

    .badge--residential {
        background-color: #e0f2f1;
        color: #0f766e;
    }

    .badge--commercial {
        background-color: #ede9fe;
        color: #5b21b6;
    }

    .badge--industrial {
        background-color: #fef2f2;
        color: #b91c1c;
    }

    .badge--mixed {
        background-color: #e5e7eb;
        color: #1f2937;
    }
</style>

<div class="flex-1 overflow-auto">
    @include($headerPartial ?? 'admin.header')

    <div class="p-6 bg-gray-50 min-h-screen">
        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            {{-- <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $PageTitle ?? 'Unit (Scheme) ST Memo Management' }}</h1>
                <p class="text-gray-600">{{ $PageDescription ?? 'Manage memo readiness, uploads, and records for sectional titling units.' }}</p>
            </div> --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="stat-card ">
                    <p class="text-sm text-gray-500">Total Units</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ number_format($totalUnits) }}</p>
                </div>
                <div class="stat-card hidden">
                    <p class="text-sm text-gray-500">Primary Memo Ready</p>
                    <p class="text-2xl font-semibold text-emerald-600">{{ number_format($primaryMemoReady) }}</p>
                </div>
                <div class="stat-card ">
                    <p class="text-sm text-gray-500">Total uploads</p>
                    <p class="text-2xl font-semibold text-indigo-600">{{ number_format($uploadedMemos) }}</p>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3">
                <strong class="font-semibold">Success:</strong> {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3">
                <strong class="font-semibold">Error:</strong> {{ session('error') }}
            </div>
        @endif

        @if (isset($uploadTableExists) && !$uploadTableExists)
            <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3">
                Unit memo uploads storage hasn’t been initialized yet. Run the latest migrations (<code>php artisan migrate --database=sqlsrv</code>) to enable uploads and status tracking.
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
            <div class="px-6 py-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Scheme Units</h2>
                    <p class="text-sm text-gray-500">Search, export, and manage ST memo copies for each unit.</p>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table id="unitSchemeTable" class="min-w-full">
                        <thead>
                            <tr>
                                <th>NP Fileno</th>
                                <th>ST Fileno</th>
                                <th>Unit Owner</th>
                                <th>Unit Details</th>
                                <th>Land Use</th>
                                <th>LGA</th>
                                <th>ST Memo</th>
                                <th>Unit Upload Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($unitApplications as $application)
                                <tr>
                                    <td class="text-sm text-gray-900">
                                        @php
                                            $npFileno = $application->primary_np_fileno ?? null;
                                            $npBadgeClass = $npFileno ? 'badge--primary' : 'badge--muted';
                                        @endphp
                                        <span class="badge {{ $npBadgeClass }}">{{ $npFileno ?? 'N/A' }}</span>
                                    </td>
                                    <td class="text-sm text-gray-900">
                                        @php
                                            $unitFileno = $application->fileno ?? null;
                                            $unitBadgeClass = $unitFileno ? 'badge--secondary' : 'badge--muted';
                                        @endphp
                                        <div class="font-semibold">
                                            <span class="badge {{ $unitBadgeClass }}">{{ $unitFileno ?? 'N/A' }}</span>
                                        </div>
                                    </td>
                                    <td class="text-sm text-gray-900">
                                        <div class="font-semibold">{{ $application->owner_name ?? 'N/A' }}</div>
                                        @php
                                            $ownerList = $application->owner_names_list ?? null;
                                        @endphp
                                        @if (is_array($ownerList) && count($ownerList) > 1)
                                            <div class="text-xs text-emerald-600">{{ count($ownerList) }} owners listed</div>
                                        @endif
                                    </td>
                                    <td class="text-sm text-gray-900">
                                        @php
                                            $unitDetails = array_filter([
                                                $application->block_number ? 'Block ' . $application->block_number : null,
                                                $application->floor_number ? 'Floor ' . $application->floor_number : null,
                                                $application->unit_number ? 'Unit ' . $application->unit_number : null,
                                            ]);
                                        @endphp
                                        <div>{{ $unitDetails ? implode(' • ', $unitDetails) : '—' }}</div>
                                    </td>
                                    <td class="text-sm text-gray-900">
                                        @php
                                            $landUseRaw = $application->land_use ?? null;
                                            $landUseDisplay = $landUseRaw ? ucwords(strtolower($landUseRaw)) : 'N/A';
                                            $landUseKey = $landUseRaw ? strtoupper(trim($landUseRaw)) : null;
                                            $landUseClassMap = [
                                                'RESIDENTIAL' => 'badge--residential',
                                                'COMMERCIAL' => 'badge--commercial',
                                                'INDUSTRIAL' => 'badge--industrial',
                                                'MIXED' => 'badge--mixed',
                                                'MIXED USE' => 'badge--mixed',
                                            ];
                                            $landUseBadgeClass = $landUseClassMap[$landUseKey] ?? ($landUseDisplay !== 'N/A' ? 'badge--secondary' : 'badge--muted');
                                        @endphp
                                        <span class="badge {{ $landUseBadgeClass }}">{{ $landUseDisplay }}</span>
                                    </td>
                                    <td class="text-sm text-gray-900">
                                        {{ $application->property_lga ?? 'N/A' }}
                                    </td>
                                    <td class="text-sm text-gray-900">
                                        @php
                                            $memoStatusClass = $application->has_st_memo ? 'badge--success' : 'badge--warning';
                                            $memoStatusLabel = $application->has_st_memo ? 'Generated' : 'Not Generated';
                                        @endphp
                                        <span class="badge {{ $memoStatusClass }}">{{ $memoStatusLabel }}</span>
                                    </td>
                                    <td class="text-sm text-gray-900">
                                        @php
                                            $uploadStatusClass = $application->has_unit_memo_upload ? 'badge--info' : 'badge--danger';
                                            $uploadStatusLabel = $application->has_unit_memo_upload ? 'Uploaded' : 'Not Uploaded';
                                        @endphp
                                        <span class="badge {{ $uploadStatusClass }}">{{ $uploadStatusLabel }}</span>
                                    </td>
                                    <td class="text-sm text-gray-900">
                                        <div class="action-dropdown relative">
                                            <button class="action-toggle" onclick="toggleActionMenu(this)" type="button">
                                                <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                            </button>
                                            <div class="action-menu">
                                                @if ($application->has_st_memo)
                                                    <a href="{{ route('programmes.view_memo_primary', $application->main_application_id) }}" target="_blank" class="action-item">
                                                        <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i>
                                                        View Parent ST Memo
                                                    </a>
                                                    <a href="{{ route('programmes.view_memo_new', $application->main_application_id) }}" target="_blank" class="action-item">
                                                        <i data-lucide="printer" class="w-4 h-4 text-amber-500"></i>
                                                        Print Parent ST Memo
                                                    </a>
                                                    @if ($application->has_unit_memo_upload)
                                                        <span class="action-item disabled" title="Parent ST memo already uploaded.">
                                                            <i data-lucide="upload" class="w-4 h-4"></i>
                                                            Upload Parent ST Memo
                                                        </span>
                                                    @else
                                                        <a href="{{ route('programmes.unit_scheme_memo.upload', $application->id) }}" class="action-item">
                                                            <i data-lucide="upload" class="w-4 h-4 text-emerald-500"></i>
                                                            Upload Parent ST Memo
                                                        </a>
                                                    @endif
                                                @else
                                                    <span class="action-item disabled" title="Primary memo must be generated first.">
                                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                                        View Parent ST Memo
                                                    </span>
                                                    <span class="action-item disabled" title="Primary memo must be generated first.">
                                                        <i data-lucide="printer" class="w-4 h-4"></i>
                                                        Print Parent ST Memo
                                                    </span>
                                                    <span class="action-item disabled" title="Primary memo must be generated first.">
                                                        <i data-lucide="upload" class="w-4 h-4"></i>
                                                        Upload Parent ST Memo
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-10 text-gray-500">
                                        <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 text-gray-300"></i>
                                        <p class="text-lg font-medium">No unit applications found</p>
                                        <p class="text-sm text-gray-400">Unit ST memo data will appear here once applications are available.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

<script>
    const ACTION_MENU_SHOW_CLASS = 'show';
    const ACTION_MENU_FLOAT_CLASS = 'action-menu--floating';
    const ACTION_MENU_GAP = 16;
    const ACTION_MENU_VERTICAL_GAP = 8;

    function closeAllActionMenus() {
        document.querySelectorAll('.action-menu').forEach(menu => {
            menu.classList.remove(ACTION_MENU_SHOW_CLASS);
            menu.classList.remove(ACTION_MENU_FLOAT_CLASS);
            menu.style.left = '';
            menu.style.top = '';
            menu.style.right = '';
            menu.style.visibility = '';
        });
    }

    function toggleActionMenu(button) {
        const menu = button?.nextElementSibling;
        if (!menu || !menu.classList.contains('action-menu')) {
            return;
        }

        const isOpen = menu.classList.contains(ACTION_MENU_SHOW_CLASS);

        if (isOpen) {
            closeAllActionMenus();
            return;
        }

        closeAllActionMenus();

        menu.classList.add(ACTION_MENU_SHOW_CLASS);
        // Ensure measurements are correct before floating the menu
        menu.classList.remove(ACTION_MENU_FLOAT_CLASS);
        menu.style.left = '';
        menu.style.top = '';
        menu.style.right = '';
    menu.style.visibility = 'hidden';

        const buttonRect = button.getBoundingClientRect();
        const menuWidth = menu.offsetWidth;
        const menuHeight = menu.offsetHeight;

        menu.classList.add(ACTION_MENU_FLOAT_CLASS);

        let left = buttonRect.right - menuWidth;
        if (left < ACTION_MENU_GAP) {
            left = ACTION_MENU_GAP;
        }
        const maxLeft = window.innerWidth - menuWidth - ACTION_MENU_GAP;
        if (left > maxLeft) {
            left = maxLeft;
        }

        let top = buttonRect.bottom + ACTION_MENU_VERTICAL_GAP;
        const maxBottom = window.innerHeight - ACTION_MENU_GAP;
        if (top + menuHeight > maxBottom) {
            top = buttonRect.top - menuHeight - ACTION_MENU_VERTICAL_GAP;
            if (top < ACTION_MENU_GAP) {
                top = Math.max(ACTION_MENU_GAP, maxBottom - menuHeight);
            }
        }

        menu.style.left = `${left}px`;
        menu.style.top = `${top}px`;
        menu.style.visibility = '';
    }

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.action-dropdown')) {
            closeAllActionMenus();
        }
    });

    window.addEventListener('resize', closeAllActionMenus);
    window.addEventListener('scroll', closeAllActionMenus, true);

    $(document).ready(function () {
        const $table = $('#unitSchemeTable');
        const hasData = $table.find('tbody tr').filter(function () {
            return !$(this).find('td[colspan]').length;
        }).length > 0;

        if (!hasData) {
            return;
        }

        $table.DataTable({
            responsive: true,
            pageLength: 25,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: 'Export Excel',
                    className: 'bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm'
                },
                {
                    extend: 'pdf',
                    text: 'Export PDF',
                    className: 'bg-rose-500 hover:bg-rose-600 text-white px-4 py-2 rounded-lg text-sm'
                }
            ],
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            language: {
                search: 'Search units:',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ units',
                infoEmpty: 'Showing 0 to 0 of 0 units',
                infoFiltered: '(filtered from _MAX_ total units)',
                emptyTable: 'No unit applications available',
                paginate: {
                    next: 'Next',
                    previous: 'Previous'
                }
            }
        });
    });

</script>
@endsection
