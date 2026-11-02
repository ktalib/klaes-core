{{-- Units. File Movement units (CR 30102026 item 34, user 2026-11-01) are kept in file_movement_units,
     saved by SystemAdmin\FileMovementUnitsController and offered, optionally, on File Movement's Send to
     with an Other (specify) for one not listed yet. The department is the File Movement department
     (offices.department). Sub-departments in `departments` (parent_id) are listed below when any exist. --}}
@php
    $fmUnits = $fmUnits ?? collect();
    $fmDepartments = $fmDepartments ?? [];
@endphp
<div class="space-y-5">
    @unless($fmInstalled ?? false)
        <div class="iw-card" style="padding:18px 22px">The File Movement units table has not been created yet.</div>
    @else
    <div class="iw-card"
        x-data="{ editing: null, menu: null, menuTop: 0, menuLeft: 0, dept: '',
            openMenu(id, el) { const r = el.getBoundingClientRect(); this.menuTop = r.bottom + 4; this.menuLeft = Math.max(8, r.right - 210); this.menu = this.menu === id ? null : id; } }"
        @keydown.escape.window="editing = null; menu = null" @scroll.window="menu = null" @resize.window="menu = null">
        <div class="iw-card-head" style="align-items:center">
            <div>
                <div class="iw-card-title"><span class="iw-icon-tile" style="background:#f3e8ff;color:#7e22ce"><i data-lucide="network" class="h-5 w-5"></i></span> File Movement units</div>
                <div class="iw-card-sub">
                    Units inside a department. When a file is sent on File Movement, the unit is optional; one not listed here is typed under Other (specify).
                    A send between two offices of the same department shows as In-Unit; to another department, In Transit.
                </div>
            </div>
            <div class="flex items-center gap-2">
                <select x-model="dept" class="iw-input" style="width:auto;min-width:180px" aria-label="Filter by department">
                    <option value="">All departments</option>
                    @foreach($fmUnits->pluck('department')->unique()->sort() as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
                </select>
                <button type="button" class="iw-btn iw-btn-primary iw-btn-sm" @click="editing = 'new'"><i data-lucide="plus" class="h-4 w-4"></i> Add unit</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="ce-table">
                <thead><tr><th style="width:50px">S/N</th><th>Unit</th><th>Department</th><th>Status</th><th style="width:50px"></th></tr></thead>
                <tbody>
                    @forelse($fmUnits as $u)
                        <tr x-show="!dept || dept === @js($u->department)">
                            {{-- Numbered in the order shown, also when filtered. --}}
                            <td class="ce-muted" x-text="Array.from($el.closest('tbody').querySelectorAll('tr')).filter(t => t.style.display !== 'none').indexOf($el.closest('tr')) + 1">{{ $loop->iteration }}</td>
                            <td class="font-medium text-gray-900">{{ $u->name }}</td>
                            <td><span class="ce-pill" style="background:#fdf4ff;color:#a21caf">{{ $u->department }}</span></td>
                            <td>@if($u->is_active)<span class="ce-pill green">Active</span>@else<span class="ce-pill">Off</span>@endif</td>
                            <td class="text-right">
                                <button type="button" class="iw-btn iw-btn-light iw-btn-sm" aria-label="Actions" aria-haspopup="menu" @click.stop="openMenu({{ $u->id }}, $el)">
                                    <i data-lucide="more-vertical" class="h-4 w-4"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ce-muted text-center" style="padding:20px">No units yet. Use Add unit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Row menus, fixed-positioned so the table's overflow cannot clip them. --}}
        @foreach($fmUnits as $u)
            <div x-show="menu === {{ $u->id }}" x-cloak role="menu" @click.outside="menu = null"
                :style="'position:fixed;z-index:60;top:' + menuTop + 'px;left:' + menuLeft + 'px;width:210px'"
                class="bg-white border border-gray-200 rounded-lg shadow-lg py-1 text-sm">
                <button type="button" role="menuitem" class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50" @click="menu = null; editing = {{ $u->id }}">
                    <i data-lucide="pencil" class="h-4 w-4 text-gray-500"></i> Edit
                </button>
                <form method="POST" action="{{ route('configurable-entries.units.toggle', $u->id) }}">
                    @csrf
                    <button role="menuitem" class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50" @click="menu = null">
                        @if($u->is_active)<i data-lucide="toggle-left" class="h-4 w-4 text-gray-500"></i> Switch off
                        @else<i data-lucide="toggle-right" class="h-4 w-4 text-green-600"></i> Switch on @endif
                    </button>
                </form>
                <form method="POST" action="{{ route('configurable-entries.units.delete', $u->id) }}" onsubmit="return confirm('Delete this unit? It will no longer be offered on File Movement.')">
                    @csrf
                    <button role="menuitem" class="w-full flex items-center gap-2 px-3 py-2 text-left text-red-700 hover:bg-red-50" @click="menu = null">
                        <i data-lucide="trash-2" class="h-4 w-4"></i> Delete
                    </button>
                </form>
            </div>
        @endforeach

        {{-- Add / edit forms, one per unit plus one blank. --}}
        @foreach($fmUnits->concat([null]) as $u)
            <div class="iwr-backdrop" x-show="editing === {{ $u ? $u->id : "'new'" }}" x-cloak x-transition.opacity @click.self="editing = null">
                <form method="POST" action="{{ $u ? route('configurable-entries.units.update', $u->id) : route('configurable-entries.units.store') }}" class="iwr-modal">
                    @csrf
                    <div class="iwr-head"><span class="iw-icon-tile" style="background:#f3e8ff;color:#7e22ce"><i data-lucide="network" class="h-5 w-5"></i></span><div class="flex-1"><div class="iwt-now-label">File Movement units</div><div class="iwr-title">{{ $u ? 'Edit unit' : 'Add unit' }}</div></div><button type="button" class="iwr-close" @click="editing = null">✕</button></div>
                    <div class="iwr-body space-y-4">
                        <div class="iw-field"><label class="iw-label">Department <span class="req">*</span></label>
                            <select name="department" required class="iw-input">
                                <option value="">Select the department…</option>
                                @foreach($fmDepartments as $d)<option value="{{ $d }}" @selected($u && mb_strtolower($u->department) === mb_strtolower($d))>{{ $d }}</option>@endforeach
                            </select>
                            <p class="ce-muted mt-1">The departments File Movement groups its offices by.</p>
                        </div>
                        <div class="iw-field"><label class="iw-label">Unit <span class="req">*</span></label>
                            <input type="text" name="name" value="{{ $u?->name }}" maxlength="150" required class="iw-input" placeholder="e.g. Recommendation">
                        </div>
                    </div>
                    <div class="iwr-foot"><span class="ce-muted"></span><div class="flex gap-2"><button type="button" class="iw-btn iw-btn-light iw-btn-sm" @click="editing = null">Cancel</button><button class="iw-btn iw-btn-success iw-btn-sm">Save</button></div></div>
                </form>
            </div>
        @endforeach
    </div>
    @endunless

    @if($units->isNotEmpty())
        <div class="iw-card">
            <div class="iw-card-head" style="align-items:center">
                <div>
                    <div class="iw-card-title">Sub-departments</div>
                    <div class="iw-card-sub">{{ $units->count() }} sub-departments across {{ $units->pluck('parent_name')->filter()->unique()->count() }} of {{ $departmentCount }} departments, kept under Departments.</div>
                </div>
                <a href="{{ route('departments.index') }}" class="ce-pill">Manage departments</a>
            </div>
            <div class="overflow-x-auto">
                <table class="ce-table">
                    <thead><tr><th style="width:50px">S/N</th><th>Unit</th><th>Department</th><th>Code</th><th>Description</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($units as $unit)
                            <tr>
                                <td class="ce-muted">{{ $loop->iteration }}</td>
                                <td class="font-medium">{{ $unit->name }}</td>
                                <td>
                                    @if($unit->parent_name)
                                        <span class="ce-pill" style="background:#fdf4ff;color:#a21caf">{{ $unit->parent_name }}</span>
                                    @else
                                        <span class="ce-pill red">No department</span>
                                    @endif
                                </td>
                                <td><span class="ce-pill">{{ $unit->code }}</span></td>
                                <td class="ce-muted">{{ Str::limit($unit->description, 80) }}</td>
                                <td>@if($unit->is_active)<span class="ce-pill green">Active</span>@else<span class="ce-pill red">Inactive</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
