{{-- ST Grouped Table: one collapsed row per mother application or SUA --}}

<style>
    .st-group-row {
        background: #f8fafc;
        border-left: 4px solid #3b82f6;
    }
    .st-group-row:hover { background: #eff6ff; }
    .st-group-row.sua-group { border-left-color: #10b981; }

    .st-unit-row {
        background: #fff;
        border-left: 4px solid #e2e8f0;
        display: none;
    }
    .st-unit-row.visible { display: table-row; }
    .st-unit-row td:first-child { padding-left: 2.5rem; }

    .st-toggle-icon { transition: transform 0.2s; display: inline-block; }
    .st-toggle-icon.open { transform: rotate(90deg); }

    .st-progress-bar { height: 6px; background: #e5e7eb; border-radius: 3px; width: 80px; }
    .st-progress-fill { height: 100%; border-radius: 3px; background: #3b82f6; transition: width 0.3s; }
    .st-progress-fill.complete { background: #10b981; }

    .st-status-done    { display:inline-flex; align-items:center; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#dcfce7; color:#166534; }
    .st-status-pending { display:inline-flex; align-items:center; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#fef9c3; color:#854d0e; }
    .st-status-locked  { display:inline-flex; align-items:center; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#f1f5f9; color:#94a3b8; }
    .st-status-frag    { display:inline-flex; align-items:center; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#f3e8ff; color:#6b21a8; }

    .st-unit-action { display:inline-flex; align-items:center; gap:3px; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600; transition:background 0.15s; cursor:pointer; border:none; }
    .st-unit-action.register { background:#2563eb; color:#fff; }
    .st-unit-action.register:hover { background:#1d4ed8; }
    .st-unit-action.rds  { background:#e0e7ff; color:#3730a3; }
    .st-unit-action.rds:hover { background:#c7d2fe; }
    .st-unit-action.cor  { background:#ccfbf1; color:#0f766e; }
    .st-unit-action.cor:hover { background:#99f6e4; }
    .st-unit-action.disabled { background:#f1f5f9; color:#94a3b8; cursor:not-allowed; pointer-events:none; }

    .st-fileno-link { color:#2563eb; font-weight:600; font-family:monospace; background:none; border:none; cursor:pointer; padding:0; text-align:left; }
    .st-fileno-link:hover { text-decoration:underline; }

    .st-col-frag   { min-width: 160px; }
    .st-col-assign { min-width: 180px; }
    .st-col-cofo   { min-width: 180px; }

    .st-group-select-all {
        display:none; align-items:center; gap:4px;
        padding:3px 8px; border-radius:4px; font-size:10px; font-weight:600;
        background:#eef2ff; color:#4338ca; border:1px solid #c7d2fe;
        cursor:pointer; white-space:nowrap; transition:background 0.15s;
    }
    .st-group-select-all:hover { background:#e0e7ff; }
    .st-group-select-all.all-selected { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
</style>

@if($stGroups->isEmpty())
    <div class="text-center py-12 text-gray-400">
        <i class="fas fa-layer-group text-3xl mb-3"></i>
        <p class="text-sm">No approved ST applications found.</p>
    </div>
@else
<div class="table-wrapper" style="max-height: 650px; overflow-y: auto;">
<table class="min-w-full enhanced-table compact-table" id="stGroupedTable">
    <thead class="bg-gray-50 sticky top-0 z-10">
        <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-8"></th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">File No / Units</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applicant</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Captured</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase st-col-frag">
                <i class="fas fa-puzzle-piece mr-1 text-purple-500"></i>Fragmentation
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase st-col-assign">
                <i class="fas fa-exchange-alt mr-1 text-blue-500"></i>Assignment
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase st-col-cofo">
                <i class="fas fa-building mr-1 text-teal-500"></i>C of O
            </th>
        </tr>
    </thead>
    <tbody id="stGroupedTableBody">

        @foreach($stGroups as $group)
            @php
                $groupId       = $group['id'];
                $isMother      = $group['type'] === 'mother_pua';
                $unitCount     = $group['unit_count'];
                $fragStatus    = $group['frag_status'];
                $assignReg     = $group['assign_registered'];
                $assignTotal   = $group['assign_total'];
                $cofoReg       = $group['cofo_registered'];
                $cofoTotal     = $group['cofo_total'];
                $cofoEnabled   = $group['cofo_enabled'];
                $assignPct     = $assignTotal > 0 ? round($assignReg / $assignTotal * 100) : 0;
                $cofoPct       = $cofoTotal   > 0 ? round($cofoReg   / $cofoTotal   * 100) : 0;
                $capturedLabel = !empty($group['captured_date']) ? \Carbon\Carbon::parse($group['captured_date'])->format('M d, Y') : '-';
            @endphp

            {{-- ===== Group header row ===== --}}
            <tr class="st-group-row {{ $isMother ? '' : 'sua-group' }}"
                id="header-{{ $groupId }}"
                data-group-id="{{ $groupId }}"
                data-display-fileno="{{ $group['display_fileno'] }}"
                data-is-mother="{{ $isMother ? '1' : '0' }}"
                data-units="{{ json_encode($group['units']) }}"
                onclick="toggleSTGroup('{{ $groupId }}')">

                {{-- Toggle icon --}}
                <td class="px-4 py-3 text-center">
                    <span class="st-toggle-icon text-gray-400" id="icon-{{ $groupId }}">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </span>
                </td>

                {{-- File No + Unit Count --}}
                <td class="px-4 py-3">
                    <button class="st-fileno-link"
                        onclick="event.stopPropagation(); openUnitsModal('{{ $groupId }}')">
                        {{ $group['display_fileno'] ?? '-' }}
                    </button>
                    <div class="mt-1 flex items-center gap-1 text-gray-400" style="font-size:11px;">
                        <i class="fas fa-home" style="font-size:9px;"></i>
                        <span>{{ $unitCount }} unit{{ $unitCount !== 1 ? 's' : '' }}</span>
                    </div>
                </td>

                {{-- Applicant --}}
                <td class="px-4 py-3 text-sm text-gray-800" style="max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $group['applicant'] }}">
                    {{ $group['applicant'] ?? '-' }}
                </td>

                {{-- Type badge --}}
                <td class="px-4 py-3">
                    @if($isMother)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide border bg-orange-100 text-orange-800 border-orange-200">MOTHER</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide border bg-green-100 text-green-800 border-green-200">SUA</span>
                    @endif
                </td>

                {{-- Captured date --}}
                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                    <i class="fas fa-calendar-plus text-blue-300 mr-1"></i>{{ $capturedLabel }}
                </td>

                {{-- Fragmentation column --}}
                <td class="px-4 py-3 st-col-frag">
                    @if($isMother)
                        @if($fragStatus === 'registered')
                            <div class="flex flex-col gap-1">
                                <span class="st-status-done"><i class="fas fa-check mr-1"></i>{{ $group['frag_serial'] ?? '0/0/0' }}</span>
                                @if($group['frag_deed_reg_id'])
                                    <div class="flex gap-1 mt-0.5">
                                        <button onclick="event.stopPropagation(); openRdsFor('{{ $group['frag_deed_reg_id'] }}')"
                                            class="st-unit-action rds"><i class="fas fa-print" style="font-size:9px;"></i>RDS</button>
                                        <button onclick="event.stopPropagation(); openCoRFor('{{ $group['frag_deed_reg_id'] }}')"
                                            class="st-unit-action cor"><i class="fas fa-certificate" style="font-size:9px;"></i>CoR</button>
                                    </div>
                                @endif
                            </div>
                        @else
                            <span class="st-status-pending"><i class="fas fa-clock mr-1"></i>Pending</span>
                        @endif
                    @else
                        <span style="color:#d1d5db; font-size:11px;">N/A</span>
                    @endif
                </td>

                {{-- Assignment column --}}
                @php $assignPending = $assignTotal - $assignReg; @endphp
                <td class="px-4 py-3 st-col-assign">
                    @if($assignTotal > 0)
                        <div class="flex flex-col gap-1">
                            <span style="font-size:12px; font-weight:600; color:{{ $assignReg === $assignTotal ? '#15803d' : '#374151' }}">
                                {{ $assignReg }}/{{ $assignTotal }}
                                @if($assignReg === $assignTotal)<i class="fas fa-check-circle text-green-500 ml-1"></i>@endif
                            </span>
                            <div class="st-progress-bar">
                                <div class="st-progress-fill {{ $assignReg === $assignTotal ? 'complete' : '' }}" style="width: {{ $assignPct }}%"></div>
                            </div>
                            @if($assignPending > 0)
                            <button class="st-group-select-all" style="display:none;"
                                    data-group-id="{{ $groupId }}" data-reg-type="st_assignment"
                                    data-pending-label="Select {{ $assignPending }} pending"
                                    onclick="event.stopPropagation(); selectAllGroupItems('{{ $groupId }}','st_assignment',this)">
                                <i class="fas fa-check-square" style="font-size:9px;"></i>
                                Select {{ $assignPending }} pending
                            </button>
                            @endif
                        </div>
                    @else
                        <span style="color:#d1d5db;">—</span>
                    @endif
                </td>

                {{-- C of O column --}}
                @php $cofoPending = $cofoTotal - $cofoReg; @endphp
                <td class="px-4 py-3 st-col-cofo">
                    @if(!$cofoEnabled && $cofoTotal > 0)
                        <span class="st-status-locked" title="Register at least one Assignment first">
                            <i class="fas fa-lock mr-1" style="font-size:9px;"></i>Locked
                        </span>
                    @elseif($cofoTotal > 0)
                        <div class="flex flex-col gap-1">
                            <span style="font-size:12px; font-weight:600; color:{{ $cofoReg === $cofoTotal ? '#15803d' : '#374151' }}">
                                {{ $cofoReg }}/{{ $cofoTotal }}
                                @if($cofoReg === $cofoTotal)<i class="fas fa-check-circle text-green-500 ml-1"></i>@endif
                            </span>
                            <div class="st-progress-bar">
                                <div class="st-progress-fill {{ $cofoReg === $cofoTotal ? 'complete' : '' }}" style="width: {{ $cofoPct }}%"></div>
                            </div>
                            @if($cofoPending > 0 && $cofoEnabled)
                            <button class="st-group-select-all" style="display:none;"
                                    data-group-id="{{ $groupId }}" data-reg-type="sectional_cofo"
                                    data-pending-label="Select {{ $cofoPending }} pending"
                                    onclick="event.stopPropagation(); selectAllGroupItems('{{ $groupId }}','sectional_cofo',this)">
                                <i class="fas fa-check-square" style="font-size:9px;"></i>
                                Select {{ $cofoPending }} pending
                            </button>
                            @endif
                        </div>
                    @else
                        <span style="color:#d1d5db;">—</span>
                    @endif
                </td>
            </tr>

            {{-- ===== Unit sub-rows (inline expand, hidden by default) ===== --}}
            @foreach($group['units'] as $unit)
                @php
                    $unitCapturedLabel = !empty($unit['captured_date']) ? \Carbon\Carbon::parse($unit['captured_date'])->format('M d, Y') : '-';
                    $assignStatus    = $unit['assignment_status'];
                    $cofoStatus      = $unit['cofo_status'];
                    $unitCofoEnabled = $unit['cofo_enabled'];
                @endphp
                <tr class="st-unit-row" data-group="{{ $groupId }}">
                    <td class="px-4 py-2.5"><span style="display:inline-block;width:4px;height:16px;border-radius:2px;background:#cbd5e1;"></span></td>
                    <td class="px-4 py-2.5 text-sm"><span class="file-number text-gray-700">{{ $unit['fileno'] ?? '-' }}</span></td>
                    <td class="px-4 py-2.5 text-sm text-gray-700" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $unit['applicant'] }}">{{ $unit['applicant'] ?? '-' }}</td>
                    <td class="px-4 py-2.5">
                        @if($isMother)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border bg-blue-100 text-blue-800 border-blue-200">PUA</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border bg-green-100 text-green-800 border-green-200">SUA</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-xs text-gray-400">{{ $unitCapturedLabel }}</td>
                    {{-- Fragmentation (only for primary-owner units) --}}
                    <td class="px-4 py-2.5 st-col-frag">
                        @if($unit['is_primary_owner'])
                            @if($assignStatus === 'fragmentation' && $unit['assignment_deed_reg_id'])
                                <span class="st-status-frag"><i class="fas fa-puzzle-piece mr-1"></i>Auto</span>
                            @else
                                <span class="st-status-pending">Pending</span>
                            @endif
                        @else
                            <span style="color:#e2e8f0;font-size:11px;">—</span>
                        @endif
                    </td>
                    {{-- Assignment --}}
                    <td class="px-4 py-2.5 st-col-assign">
                        @if($unit['is_primary_owner'])
                            <span style="color:#d1d5db;font-size:11px;">—</span>
                        @elseif($assignStatus === 'registered')
                            <div class="flex flex-col gap-1">
                                <span class="st-status-done"><i class="fas fa-check mr-1"></i>Registered</span>
                                @if($unit['assignment_deed_reg_id'])
                                    <button onclick="openRdsFor('{{ $unit['assignment_deed_reg_id'] }}')" class="st-unit-action rds mt-1"><i class="fas fa-print" style="font-size:9px;"></i>RDS</button>
                                @endif
                            </div>
                        @else
                            <div style="display:flex; align-items:center; gap:6px;">
                                <input type="checkbox"
                                       class="st-batch-checkbox"
                                       data-subapp-id="{{ $unit['subapp_id'] }}"
                                       data-fileno="{{ $unit['fileno'] }}"
                                       data-applicant="{{ e($unit['mother_applicant'] ?? ($group['applicant'] ?? '')) }}"
                                       data-grantee="{{ e($unit['applicant'] ?? '') }}"
                                       data-reg-type="st_assignment"
                                       data-instrument-type="ST Assignment (Transfer of Title)"
                                       style="display:none; width:15px; height:15px; accent-color:#6366f1; cursor:pointer; flex-shrink:0;"
                                       disabled
                                       onchange="handleMainTableCheckboxChange()">
                                <button class="st-unit-action register st-register-btn"
                                        onclick="openSingleRegisterModalWithData('{{ $unit['subapp_id'] }}_st_assignment')">
                                    <i class="fas fa-file-signature" style="font-size:9px;"></i>Register
                                </button>
                            </div>
                        @endif
                    </td>
                    {{-- C of O --}}
                    <td class="px-4 py-2.5 st-col-cofo">
                        @if(!$unitCofoEnabled)
                            <span class="st-status-locked" title="Register Assignment first"><i class="fas fa-lock mr-1" style="font-size:9px;"></i>Locked</span>
                        @elseif($cofoStatus === 'registered')
                            <div class="flex flex-col gap-1">
                                <span class="st-status-done"><i class="fas fa-check mr-1"></i>Registered</span>
                                @if($unit['cofo_deed_reg_id'])
                                    <div class="flex gap-1 mt-1">
                                        <button onclick="openRdsFor('{{ $unit['cofo_deed_reg_id'] }}')" class="st-unit-action rds"><i class="fas fa-print" style="font-size:9px;"></i>RDS</button>
                                        <button onclick="openCoRFor('{{ $unit['cofo_deed_reg_id'] }}')" class="st-unit-action cor"><i class="fas fa-certificate" style="font-size:9px;"></i>CoR</button>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div style="display:flex; align-items:center; gap:6px;">
                                <input type="checkbox"
                                       class="st-batch-checkbox"
                                       data-subapp-id="{{ $unit['subapp_id'] }}"
                                       data-fileno="{{ $unit['fileno'] }}"
                                       data-applicant="Kano State Government"
                                       data-grantee="{{ e($unit['applicant'] ?? '') }}"
                                       data-reg-type="sectional_cofo"
                                       data-instrument-type="Sectional Titling CofO"
                                       style="display:none; width:15px; height:15px; accent-color:#0d9488; cursor:pointer; flex-shrink:0;"
                                       disabled
                                       onchange="handleMainTableCheckboxChange()">
                                <button class="st-unit-action register st-register-btn"
                                        onclick="openSingleRegisterModalWithData('{{ $unit['subapp_id'] }}_sectional_cofo')">
                                    <i class="fas fa-file-signature" style="font-size:9px;"></i>Register
                                </button>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach

        @endforeach

    </tbody>
</table>
</div>

{{-- Group count summary --}}
<div class="px-6 py-3 border-t border-gray-100 text-xs text-gray-500 flex items-center gap-4">
    <span><i class="fas fa-layer-group mr-1 text-blue-400"></i>{{ $stGroups->count() }} group{{ $stGroups->count() !== 1 ? 's' : '' }}</span>
    <span><i class="fas fa-home mr-1 text-blue-300"></i>{{ $stGroups->sum('unit_count') }} total units</span>
    <span style="color:#ea580c;"><i class="fas fa-circle mr-1" style="font-size:7px;"></i>{{ $stGroups->where('type', 'mother_pua')->count() }} mother</span>
    <span style="color:#16a34a;"><i class="fas fa-circle mr-1" style="font-size:7px;"></i>{{ $stGroups->where('type', 'sua')->count() }} SUA</span>
</div>
@endif

{{-- ===== UNITS MODAL (3 tabs) ===== --}}
<style>
.st-modal-tab { padding:10px 18px; font-size:13px; font-weight:600; cursor:pointer; border:none; background:none; color:#6b7280; border-bottom:3px solid transparent; transition:all 0.15s; white-space:nowrap; }
.st-modal-tab:hover { color:#3b82f6; }
.st-modal-tab.active { color:#2563eb; border-bottom-color:#2563eb; }
.st-modal-tab-count { display:inline-flex; align-items:center; justify-content:center; min-width:18px; height:18px; padding:0 5px; border-radius:999px; font-size:10px; font-weight:700; margin-left:5px; background:#e5e7eb; color:#374151; }
.st-modal-tab.active .st-modal-tab-count { background:#dbeafe; color:#1d4ed8; }
.st-reg-input { display:inline-block; background:#f9fafb; border:1px solid #e5e7eb; border-radius:4px; padding:3px 8px; font-family:monospace; font-size:12px; color:#374151; cursor:default; user-select:text; }
.st-status-reg   { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#dcfce7; color:#166534; }
.st-status-pend  { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#fef9c3; color:#854d0e; }
.st-status-na    { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#f1f5f9; color:#94a3b8; }
</style>
<div id="stUnitsModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; width:92%; max-width:980px; max-height:88vh; display:flex; flex-direction:column; box-shadow:0 25px 50px rgba(0,0,0,0.25);">

        {{-- Header --}}
        <div style="padding:18px 24px 14px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:flex-start; flex-shrink:0;">
            <div>
                <h3 id="stUnitsModalTitle" style="font-size:16px; font-weight:700; color:#111827; margin:0;"></h3>
                <p id="stUnitsModalSub" style="font-size:12px; color:#6b7280; margin:4px 0 0;"></p>
            </div>
            <button onclick="closeUnitsModal()" style="background:none; border:none; cursor:pointer; color:#9ca3af; font-size:20px; padding:2px 6px;"><i class="fas fa-times"></i></button>
        </div>

        {{-- Tabs --}}
        <div style="display:flex; border-bottom:1px solid #e5e7eb; padding:0 16px; flex-shrink:0; background:#fff;">
            <button class="st-modal-tab active" id="stTab-frag"   onclick="switchUnitsTab('frag')">
                <i class="fas fa-puzzle-piece mr-1" style="color:#a855f7;font-size:11px;"></i>ST Fragmentation
                <span class="st-modal-tab-count" id="stTabCount-frag">0</span>
            </button>
            <button class="st-modal-tab" id="stTab-assign" onclick="switchUnitsTab('assign')">
                <i class="fas fa-exchange-alt mr-1" style="color:#3b82f6;font-size:11px;"></i>ST Assignment
                <span class="st-modal-tab-count" id="stTabCount-assign">0</span>
            </button>
            <button class="st-modal-tab" id="stTab-cofo"   onclick="switchUnitsTab('cofo')">
                <i class="fas fa-building mr-1" style="color:#14b8a6;font-size:11px;"></i>ST CofO
                <span class="st-modal-tab-count" id="stTabCount-cofo">0</span>
            </button>
        </div>

        {{-- Tab panels --}}
        <div id="stTabPanel-frag"   style="overflow:auto; flex:1;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead><tr style="background:#f9fafb; position:sticky; top:0; z-index:1;">
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;width:40px;">#</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">File No</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;">Applicant</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Status</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Reg Particulars</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Reg Date</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Reg Time</th>
                </tr></thead>
                <tbody id="stTabBody-frag"></tbody>
            </table>
        </div>
        <div id="stTabPanel-assign" style="display:none; overflow:auto; flex:1;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead><tr style="background:#f9fafb; position:sticky; top:0; z-index:1;">
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;width:40px;">#</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">File No</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;">Applicant</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Status</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Reg Particulars</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Reg Date</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Reg Time</th>
                </tr></thead>
                <tbody id="stTabBody-assign"></tbody>
            </table>
        </div>
        <div id="stTabPanel-cofo"   style="display:none; overflow:auto; flex:1;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead><tr style="background:#f9fafb; position:sticky; top:0; z-index:1;">
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;width:40px;">#</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">File No</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;">Applicant</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Status</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Reg Particulars</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Reg Date</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;white-space:nowrap;">Reg Time</th>
                </tr></thead>
                <tbody id="stTabBody-cofo"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleSTGroup(groupId) {
    var icon  = document.getElementById('icon-' + groupId);
    var rows  = document.querySelectorAll('[data-group="' + groupId + '"]');

    var isOpen = icon.classList.contains('open');
    icon.classList.toggle('open', !isOpen);
    rows.forEach(function(row) { row.classList.toggle('visible', !isOpen); });
}

// Select all pending unit checkboxes of a given type for a group.
// Called from the "Select X pending" button on the group header row.
function selectAllGroupItems(groupId, regType, btn) {
    // Ensure the group is expanded so checkboxes are reachable
    var icon = document.getElementById('icon-' + groupId);
    if (icon && !icon.classList.contains('open')) {
        toggleSTGroup(groupId);
    }

    // Check all matching enabled batch checkboxes in this group
    var checkboxes = document.querySelectorAll(
        '[data-group="' + groupId + '"] .st-batch-checkbox[data-reg-type="' + regType + '"]:not(:disabled)'
    );
    var anyUnchecked = Array.from(checkboxes).some(function(cb) { return !cb.checked; });
    checkboxes.forEach(function(cb) { cb.checked = anyUnchecked; });

    // Toggle button appearance: green = all selected, blue = none selected
    if (btn) {
        btn.classList.toggle('all-selected', anyUnchecked);
        var label = regType === 'st_assignment' ? 'Assignment' : 'CofO';
        btn.innerHTML = anyUnchecked
            ? '<i class="fas fa-check-square" style="font-size:9px;"></i> All ' + label + ' selected'
            : '<i class="fas fa-check-square" style="font-size:9px;"></i> ' + btn.getAttribute('data-pending-label');
    }

    if (typeof handleMainTableCheckboxChange === 'function') handleMainTableCheckboxChange();
}

// ===== 3-TAB MODAL =====

function switchUnitsTab(tab) {
    ['frag','assign','cofo'].forEach(function(t) {
        var panel = document.getElementById('stTabPanel-' + t);
        var btn   = document.getElementById('stTab-' + t);
        if (t === tab) {
            panel.style.display = 'block';
            btn.classList.add('active');
        } else {
            panel.style.display = 'none';
            btn.classList.remove('active');
        }
    });
}

function openUnitsModal(groupId) {
    var fileno, isMother, units;

    var headerRow = document.getElementById('header-' + groupId);
    if (headerRow) {
        fileno   = headerRow.getAttribute('data-display-fileno') || groupId;
        isMother = headerRow.getAttribute('data-is-mother') === '1';
        units    = [];
        try { units = JSON.parse(headerRow.getAttribute('data-units') || '[]'); } catch(e) { units = []; }
    } else if (window.stGroupLookup) {
        var mid = groupId.replace(/^grp_m_/, '');
        var groupData = window.stGroupLookup[mid];
        if (!groupData) return;
        fileno   = groupData.display_fileno || groupId;
        isMother = groupData.is_mother !== false;
        units    = groupData.units || [];
    } else {
        return;
    }

    document.getElementById('stUnitsModalTitle').textContent = fileno;
    document.getElementById('stUnitsModalSub').textContent =
        units.length + ' unit' + (units.length !== 1 ? 's' : '') +
        (isMother ? ' — Mother Application' : ' — Standalone Unit (SUA)');

    // Partition units for each tab
    var fragUnits   = units.filter(function(u) { return !!u.is_primary_owner; });
    var assignUnits = units.filter(function(u) { return !u.is_primary_owner; });
    var cofoUnits   = units;

    document.getElementById('stTabCount-frag').textContent   = fragUnits.length;
    document.getElementById('stTabCount-assign').textContent = assignUnits.length;
    document.getElementById('stTabCount-cofo').textContent   = cofoUnits.length;

    var noRows = '<tr><td colspan="7" style="padding:32px;text-align:center;color:#9ca3af;font-size:13px;"><i class="fas fa-info-circle" style="margin-right:6px;"></i>No records found.</td></tr>';

    var fragBody = document.getElementById('stTabBody-frag');
    fragBody.innerHTML = fragUnits.length
        ? fragUnits.map(function(u,i){ return buildFragRow(u,i); }).join('')
        : noRows;

    var assignBody = document.getElementById('stTabBody-assign');
    assignBody.innerHTML = assignUnits.length
        ? assignUnits.map(function(u,i){ return buildAssignRow(u,i); }).join('')
        : noRows;

    var cofoBody = document.getElementById('stTabBody-cofo');
    cofoBody.innerHTML = cofoUnits.length
        ? cofoUnits.map(function(u,i){ return buildCofoRow(u,i); }).join('')
        : noRows;

    switchUnitsTab('frag');

    document.getElementById('stUnitsModal').style.display = 'flex';
}

function closeUnitsModal() {
    document.getElementById('stUnitsModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('stUnitsModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeUnitsModal();
        });
    }
});

// ===== ROW BUILDER HELPERS =====

function _stEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function _stDate(d) {
    if (!d) return '<span style="color:#d1d5db;">—</span>';
    try {
        return '<i class="fas fa-calendar-check" style="color:#34d399;font-size:10px;margin-right:3px;"></i>'
             + new Date(d).toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'});
    } catch(e) { return _stEsc(d); }
}

function _stTime(t) {
    if (!t) return '<span style="color:#d1d5db;">—</span>';
    return '<i class="fas fa-clock" style="color:#9ca3af;font-size:10px;margin-right:3px;"></i>' + _stEsc(t);
}

function _stPart(v) {
    if (!v) return '<span style="color:#d1d5db;">—</span>';
    return '<span class="st-reg-input">' + _stEsc(v) + '</span>';
}

function _stRowBase(unit, i, statusHtml, isReg, particulars, date, time) {
    var bg = i % 2 ? '#fafafa' : '#fff';
    var dash = '<span style="color:#d1d5db;">—</span>';
    return '<tr style="border-bottom:1px solid #f3f4f6;background:' + bg + ';">'
        + '<td style="padding:10px 12px;text-align:center;color:#9ca3af;font-size:11px;">' + (i+1) + '</td>'
        + '<td style="padding:10px 16px;font-family:monospace;font-weight:600;color:#1d4ed8;white-space:nowrap;">' + _stEsc(unit.fileno || '-') + '</td>'
        + '<td style="padding:10px 16px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + _stEsc(unit.applicant) + '">' + _stEsc(unit.applicant || '-') + '</td>'
        + '<td style="padding:10px 16px;white-space:nowrap;">' + statusHtml + '</td>'
        + '<td style="padding:10px 16px;white-space:nowrap;">' + (isReg ? _stPart(particulars) : dash) + '</td>'
        + '<td style="padding:10px 16px;white-space:nowrap;font-size:12px;">' + (isReg ? _stDate(date) : dash) + '</td>'
        + '<td style="padding:10px 16px;white-space:nowrap;font-size:12px;">' + (isReg ? _stTime(time) : dash) + '</td>'
        + '</tr>';
}

function buildFragRow(unit, i) {
    var isReg = !!unit.frag_registered;
    var status = isReg
        ? '<span class="st-status-reg"><i class="fas fa-check" style="margin-right:4px;"></i>Registered</span>'
        : '<span class="st-status-pend"><i class="fas fa-clock" style="margin-right:4px;"></i>Pending</span>';
    return _stRowBase(unit, i, status, isReg, unit.frag_reg_particulars, unit.frag_reg_date, unit.frag_reg_time);
}

function buildAssignRow(unit, i) {
    var isReg = !!unit.assign_registered;
    var status = isReg
        ? '<span class="st-status-reg"><i class="fas fa-check" style="margin-right:4px;"></i>Registered</span>'
        : '<span class="st-status-pend"><i class="fas fa-clock" style="margin-right:4px;"></i>Pending</span>';
    return _stRowBase(unit, i, status, isReg, unit.assign_reg_particulars, unit.assign_reg_date, unit.assign_reg_time);
}

function buildCofoRow(unit, i) {
    var isLocked = !unit.cofo_enabled;
    var isReg    = (unit.cofo_status === 'registered');
    var status;
    if (isLocked) {
        status = '<span class="st-status-na"><i class="fas fa-lock" style="font-size:9px;margin-right:4px;"></i>Locked</span>';
    } else if (isReg) {
        status = '<span class="st-status-reg"><i class="fas fa-check" style="margin-right:4px;"></i>Registered</span>';
    } else {
        status = '<span class="st-status-pend"><i class="fas fa-clock" style="margin-right:4px;"></i>Pending</span>';
    }
    return _stRowBase(unit, i, status, isReg, unit.cofo_reg_particulars, unit.cofo_reg_date, unit.cofo_reg_time);
}

// ===== UTILITY =====

function openRdsFor(registeredId) {
    var url = (window.KlaesConfig && window.KlaesConfig.urls.viewRds)
        ? window.KlaesConfig.urls.viewRds + '/' + registeredId
        : '/instrument_registration/view-rds/' + registeredId;
    window.open(url, '_blank');
}

function openCoRFor(registeredId) {
    var base = (window.KlaesConfig && window.KlaesConfig.urls.corIndex)
        ? window.KlaesConfig.urls.corIndex
        : '/coroi';
    window.open(base + '?id=' + encodeURIComponent(registeredId) + '&is_unit=1', '_blank');
}
</script>
