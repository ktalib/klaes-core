@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('KLAES') }}
@endsection

@section('content')
    <style>
        /* Minimal, clean table styles */
        .wrap {
            padding: 16px;
        }

        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .controls-main {
            padding: 14px 18px;
            background: #f3f4f6;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }

        .controls-sub {
            padding: 10px 14px;
            background: #f9fafb;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }

        .tabs {
            display: flex;
            gap: 10px;
            position: relative;
        }

        .tab-btn {
            position: relative;
            padding: 10px 16px;
            border: none;
            background: #fff;
            color: #374151;
            border-radius: 9999px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: inset 0 0 0 1px #d1d5db;
            transition: all 0.2s ease;
        }

        .tab-btn:hover {
            box-shadow: inset 0 0 0 1px #2563eb;
            color: #1d4ed8;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            box-shadow: none;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -8px;
            transform: translateX(-50%);
            width: 36px;
            height: 3px;
            background: #1d4ed8;
            border-radius: 9999px;
        }

        .search {
            max-width: 320px;
            flex: 1;
        }

        .search input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .card-header {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 700;
            color: #111827;
        }

        .table-container {
            overflow-x: auto;
        }

        table.simple {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead th {
            background: #f9fafb;
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            white-space: nowrap;
        }

        tr:hover td {
            background: #fafafa;
        }

        .actions a {
            color: #2563eb;
            text-decoration: none;
            margin-right: 10px;
        }

        .actions a:last-child {
            margin-right: 0;
        }

        .muted {
            color: #6b7280;
        }

        /* Simple dropdown */
        .dd {
            position: relative;
            display: inline-block;
        }

        .dd-btn {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            border-radius: 6px;
            padding: 4px 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s;
            min-width: 85px;
        }

        .dd-btn:hover {
            background: #f3f4f6;
        }

        .dd-menu {
            display: none;
            position: fixed;
            min-width: 180px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
            z-index: 9999;
            overflow: hidden;
        }

        .dd-menu.show {
            display: block;
        }

        .dd-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            color: #4b5563;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .dd-item i, .dd-item svg {
            flex-shrink: 0;
        }

        .dd-item:hover {
            background: #f3f4f6;
        }

        .dd-item.disabled {
            color: #9ca3af;
            pointer-events: none;
        }

        @media (max-width: 640px) {
            .search {
                max-width: none;
                width: 100%;
            }
        }
    </style>

    <div class="flex-1 overflow-auto">
        @include($headerPartial ?? 'admin.header')

        <div class="wrap">
            <div class="card">
                <div class="card-header">RoFO Applications</div>
                <div class="controls controls-main">
                    <div class="tabs">
                        <button id="btn-main-pua" class="tab-btn active" onclick="setMainTab('pua')">PuA</button>
                        <button id="btn-main-sua" class="tab-btn" onclick="setMainTab('sua')">SuA</button>
                    </div>
                    <div class="search">
                        <input id="search" type="text" placeholder="Search..." oninput="applySearch()" />
                    </div>
                </div>

                <div id="main-pua" class="main-section">
                    <div class="controls controls-sub">
                        <div class="tabs">
                            <button id="btn-pua-not" class="tab-btn active" onclick="setSubTab('pua', 'not')">Not
                                Generated</button>
                            <button id="btn-pua-gen" class="tab-btn" onclick="setSubTab('pua', 'gen')">Generated</button>
                        </div>
                    </div>
                    @include('programmes.partials.rofo_pua_tables', ['primaries' => $puaPrimaries])
                </div>

                <div id="main-sua" class="main-section" style="display:none;">
                    <div class="controls controls-sub">
                        <div class="tabs">
                            <button id="btn-sua-not" class="tab-btn active" onclick="setSubTab('sua', 'not')">Not
                                Generated</button>
                            <button id="btn-sua-gen" class="tab-btn" onclick="setSubTab('sua', 'gen')">Generated</button>
                        </div>
                    </div>
                    @include('programmes.partials.rofo_sua_tables', ['applications' => $suaSubapplications])
                </div>
            </div>
        </div>

        @include($footerPartial ?? 'admin.footer')
    </div>

    <script src="{{ asset('js/master-delete.js') }}"></script>
    <script>
        /**
         * Master Delete for an ST RofO.
         *
         * scope 'unit'    — one SUA row, its own RofO.
         * scope 'primary' — one PUA row, the RofOs of every unit under it, which is
         *                   what "Generate Batch RoFO" produced in a single pass and
         *                   what the row stands for.
         *
         * Deletes `rofo` rows only. The applications, their memos and their file
         * numbers survive; erasing an ST FILE is the master delete on the ST file
         * number screen.
         */
        function masterDeleteStRofo(scope, id, fileNo, unitCount) {
            const isPrimary = scope === 'primary';

            MasterDelete.confirm({
                url: '{{ route('programmes.rofo.master-destroy') }}',
                body: { scope: scope, id: id },
                reference: fileNo,
                title: 'Master Delete RofO',
                lead: isPrimary
                    ? 'This permanently deletes the RofOs of <b>all ' + (unitCount || 0) + ' unit(s)</b> under <b>' + fileNo + '</b>. It cannot be undone.'
                    : 'This permanently deletes the RofO for <b>' + fileNo + '</b>. It cannot be undone.',
                targets: [
                    'The RofO record(s) and their RofO numbers',
                    'Their PRA transactions',
                    'Their security paper codes (released, or retired if already printed)',
                    'Their print history, white copies included'
                ],
                keeps: 'The application, its ST memo and its file number are untouched.'
            });
        }
    </script>

    <script>
        let currentMainTab = 'pua';
        const subTabState = { pua: 'not', sua: 'not' };

        function setMainTab(tab) {
            if (!['pua', 'sua'].includes(tab)) {
                return;
            }

            currentMainTab = tab;
            document.getElementById('btn-main-pua').classList.toggle('active', tab === 'pua');
            document.getElementById('btn-main-sua').classList.toggle('active', tab === 'sua');
            document.getElementById('main-pua').style.display = tab === 'pua' ? 'block' : 'none';
            document.getElementById('main-sua').style.display = tab === 'sua' ? 'block' : 'none';
            applySearch();
            closeAllDD();
        }

        function setSubTab(group, tab) {
            if (!['pua', 'sua'].includes(group) || !['not', 'gen'].includes(tab)) {
                return;
            }

            subTabState[group] = tab;

            const notButton = document.getElementById(`btn-${group}-not`);
            const genButton = document.getElementById(`btn-${group}-gen`);
            const notWrap = document.getElementById(`wrap-${group}-not`);
            const genWrap = document.getElementById(`wrap-${group}-gen`);

            if (notButton && genButton) {
                notButton.classList.toggle('active', tab === 'not');
                genButton.classList.toggle('active', tab === 'gen');
            }

            if (notWrap && genWrap) {
                notWrap.style.display = tab === 'not' ? 'block' : 'none';
                genWrap.style.display = tab === 'gen' ? 'block' : 'none';
            }

            if (group === currentMainTab) {
                applySearch();
            }

            closeAllDD();
        }

        function getActiveTableId() {
            const activeGroup = currentMainTab;
            const activeTab = subTabState[activeGroup] || 'not';
            return `table-${activeGroup}-${activeTab}`;
        }

        function applySearch() {
            const q = (document.getElementById('search').value || '').toLowerCase();
            const tableId = getActiveTableId();
            const table = document.getElementById(tableId);
            if (!table) {
                return;
            }

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(q) ? '' : 'none';
            });
        }

        setSubTab('pua', 'not');
        setSubTab('sua', 'not');
        setMainTab('pua');

        function showOwners(owners) {
            alert('Owners:\n\n' + owners.join('\n'));
        }

        function closeAllDD() {
            document.querySelectorAll('.dd-menu.show').forEach(m => m.classList.remove('show'));
        }

        function toggleDD(btn) {
            const menu = btn.nextElementSibling;
            if (!menu) return;

            const wasOpen = menu.classList.contains('show');
            closeAllDD();
            if (wasOpen) return;

            // Temporarily show menu to measure width/height accurately
            menu.style.visibility = 'hidden';
            menu.classList.add('show');
            const menuWidth = menu.offsetWidth || 180;
            const menuHeight = menu.offsetHeight || 150;
            menu.classList.remove('show');
            menu.style.visibility = '';

            const rect = btn.getBoundingClientRect();
            const modalContent = btn.closest('#modal-content');
            
            let topOffset = 0;
            let leftOffset = 0;
            
            if (modalContent) {
                const modalRect = modalContent.getBoundingClientRect();
                topOffset = modalRect.top;
                leftOffset = modalRect.left;
            }

            // Calculate Left
            let left = rect.right - menuWidth - leftOffset;
            if (left < 8) {
                left = (rect.left - leftOffset);
            }
            const maxLeft = (window.innerWidth - menuWidth - 8) - leftOffset;
            if (left > maxLeft) {
                left = maxLeft;
            }

            // Calculate Top (Smart positioning: open upward if not enough space below)
            let top = (rect.bottom + 6) - topOffset;
            const spaceBelow = window.innerHeight - rect.bottom;
            if (spaceBelow < menuHeight + 10) {
                top = (rect.top - menuHeight - 6) - topOffset;
            }

            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
            menu.classList.add('show');
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.dd')) closeAllDD();
        });
        window.addEventListener('scroll', closeAllDD, true);
        window.addEventListener('resize', closeAllDD);

        document.querySelectorAll('[data-batch-rofo="1"]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                closeAllDD();

                var url = this.getAttribute('data-url');
                if (!url) {
                    return;
                }

                var totalUnits = parseInt(this.getAttribute('data-total-units') || '0', 10);
                var primaryId = this.getAttribute('data-primary');
                var promptMessage = totalUnits > 1
                    ? 'Generate RoFO documents for ' + totalUnits + ' units under this primary application?'
                    : 'Generate RoFO for this unit?';

                if (primaryId) {
                    promptMessage += '\n\nPrimary ID: ' + primaryId;
                }

                if (window.Swal && typeof window.Swal.fire === 'function') {
                    window.Swal.fire({
                        title: 'Generate RoFO documents?',
                        text: promptMessage,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, proceed',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true,
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            window.location.href = url;
                        }
                    });
                } else if (confirm(promptMessage)) {
                    window.location.href = url;
                }
            });
        });
        
        // Bridge to global security paper modal component
        function openSecurityPaperModal(id, fileno, currentCode) {
            openAssignSecurityPaperModal(
                id, fileno, currentCode,
                '{{ url("programmes/rofo/assign-security-paper") }}/' + id,
                { codesApiUrl: '{{ url("security-paper-codes/available") }}', fieldName: 'security_paper_code' }
            );
        }

        // Bridge to the global reset modal. A successful reset chains into the
        // picker so the correct code can be keyed in before the page reloads.
        function resetSecurityPaperModal(id, fileno, currentCode) {
            openResetSecurityPaperModal(
                id, fileno, currentCode,
                '{{ url("programmes/rofo/reset-security-paper") }}/' + id,
                {
                    assignEndpoint: '{{ url("programmes/rofo/assign-security-paper") }}/' + id,
                    codesApiUrl: '{{ url("security-paper-codes/available") }}',
                    fieldName: 'security_paper_code'
                }
            );
        }
        // goet setTab has_idapplications ? 'gen' : 'not';
        // if(getISnone) sys.name = 'not';
        // {
        //     public function p []
        // }
    </script>
@endsection