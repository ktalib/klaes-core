@php
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $ha = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
@endphp

@include('survey_module.partials._flash')
@include('survey_module.partials._searchable_selects')

<div class="page-header">
    <div>@include('survey_module.partials._records_export', ['endpoint' => route('survey-module.compensation.projects.export'), 'title' => 'Projects', 'statuses' => ['Active', 'Draft', 'Closed']])</div>
    <a href="{{ route('survey-module.compensation.projects.create', array_filter(['co_file' => request('co_file')])) }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Create Project
    </a>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total Projects</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Monetary Compensation Schemes</div>
        <div class="kpi-value">{{ number_format($stats['monetary']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Land to Land Schemes</div>
        <div class="kpi-value">{{ number_format($stats['land']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Active</div>
        <div class="kpi-value">{{ number_format($stats['active']) }}</div>
    </div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or code…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:250px;" />
        <select name="co_file" class="sv-searchable" data-width="260px" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;max-width:260px;">
            <option value="">All CO Files</option>
            @foreach ($coFiles as $f)
                <option value="{{ $f->id }}" @selected((string) request('co_file') === (string) $f->id)>{{ $f->co_file_no }} · {{ $f->title }}</option>
            @endforeach
        </select>
        <select name="scheme" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Schemes</option>
            <option value="monetary" @selected(request('scheme')==='monetary')>Monetary Compensation</option>
            <option value="land" @selected(request('scheme')==='land')>Land to Land</option>
        </select>
        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach (['Active','Draft','Closed'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
            @endforeach
        </select>
        @include('survey_module.partials._origin_filter')
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','scheme','status','co_file','origin']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.projects') }}">Clear</a>
        @endif
    </div>
</form>

@include('survey_module.partials._table_ui')

<div class="table-wrapper">
    <div class="table-scroll">
        <table class="sv-dt">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Project ID</th>
                    <th>CO File</th>
                    <th>Project Name</th>
                    <th>TP No.</th>
                    <th>Location</th>
                    <th class="num" title="Size of Acquisition (hectares)">Acquisition (Ha)</th>
                    <th title="Number beside each name = portions (blocks) that surveyor covers">Surveyors</th>
                    <th class="ctr">Portions</th>
                    <th class="ctr" title="Farmers' lands registered under the project; click the number to list them">Lands</th>
                    <th>Scheme Type</th>
                    <th class="num" title="Cash for economic trees entered on the project (Monetary Compensation)">Tree Cash (₦)</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Date Created</th>
                    <th class="col-actions"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $p)
                    @php
                        $portions = $p->surveyors->sum('portions');
                    @endphp
                    <tr>
                        <td class="muted">{{ $projects->firstItem() + $loop->index }}</td>
                        <td class="code">{{ $p->project_code }}</td>
                        <td>@if ($p->coFile)<span class="nowrap">{{ $p->coFile->co_file_no }}</span>@else<span class="muted">—</span>@endif</td>
                        <td class="wide strong">{{ $p->display_name }}</td>
                        <td>@if ($p->plan_no)<span class="tp">{{ $p->plan_no }}</span>@else<span class="muted">—</span>@endif</td>
                        <td class="mid">{{ $p->property_location ?: '—' }}</td>
                        <td class="num">@if ($p->acquisition_size_ha !== null){{ $ha($p->acquisition_size_ha) }}@else<span class="muted">—</span>@endif</td>
                        <td>
                            @if ($p->surveyors->isNotEmpty())
                                <div class="chips">
                                    @foreach ($p->surveyors as $a)
                                        <span class="chip" title="{{ $a->surveyor_name }}: {{ $a->portions }} {{ $a->portions === 1 ? 'portion' : 'portions' }}">
                                            {{ $a->surveyor_name }} <b>{{ $a->portions }}</b>
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="ctr">@if ($portions){{ number_format($portions) }}@else<span class="muted">—</span>@endif</td>
                        <td class="ctr">
                            @if ($p->cases_count)
                                <button type="button" class="pj-lands-btn" onclick="pjShowLands({{ $p->id }})"
                                        title="Show this project's lands">{{ number_format($p->cases_count) }} <i class="fas fa-external-link-alt"></i></button>
                            @else
                                <span class="muted">0</span>
                            @endif
                        </td>
                        <td>
                            @if ($p->isMonetary())
                                <span class="status-badge active"><span class="dot"></span>Monetary Compensation</span>
                            @else
                                <span class="status-badge completed"><span class="dot"></span>Land to Land</span>
                            @endif
                        </td>
                        <td class="num">@if ($p->isMonetary() && $p->trees->isNotEmpty()){{ number_format($p->trees->sum('amount'), 2) }}@else<span class="muted">—</span>@endif</td>
                        <td class="wide">{{ $p->purpose ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $p->status === 'Active' ? 'active' : ($p->status === 'Closed' ? 'rejected' : 'pending') }}">
                                <span class="dot"></span>{{ $p->status }}
                            </span>
                        </td>
                        @include('survey_module.partials._created_cells', ['row' => $p])
                        <td class="col-actions">
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    {{-- Menu wording from the user's notes (2026-11-03): Capture Farmer, Update Farmers List, View Lands, Update Project. --}}
                                    <a href="{{ route('survey-module.compensation.cases.register', ['project' => $p->id]) }}" class="{{ $svItem }}">
                                        <i data-lucide="user-plus" class="w-4 h-4 text-gray-500"></i> Capture Farm
                                    </a>
                                    <a href="{{ route('survey-module.compensation.capture-list', ['project' => $p->id]) }}" class="{{ $svItem }}">
                                        <i data-lucide="clipboard-list" class="w-4 h-4 text-gray-500"></i> Update Farmers List
                                    </a>
                                    @if ($p->cases_count)
                                        {{-- The project's lands, in a modal (user, 2026-11-03). --}}
                                        <button type="button" class="{{ $svItem }}" @click="open = false; pjShowLands({{ $p->id }})">
                                            <i data-lucide="list" class="w-4 h-4 text-gray-500"></i> View Lands
                                        </button>
                                    @endif
                                    <a href="{{ route('survey-module.compensation.projects.edit', $p) }}" class="{{ $svItem }}">
                                        <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Update Project
                                    </a>
                                    @if ($p->cases_count === 0)
                                        <form method="POST" action="{{ route('survey-module.compensation.projects.destroy', $p) }}"
                                              onsubmit="return confirm('Delete {{ $p->project_code }}? This cannot be undone.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $svItem }} text-red-600">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @if ($p->cases->isNotEmpty())
                        {{-- The project's lands (user, 2026-11-02), never shown in the table: View Lands copies them into the
                             modal (user, 2026-11-03). Property order: Plot No., District, LGA, Location. --}}
                        <tr class="pj-lands-row" data-parent="{{ $p->id }}" data-title="{{ $p->project_code }} · {{ $p->display_name }}" data-count="{{ $p->cases_count }}" hidden>
                            <td colspan="17">
                                <div class="pj-lands" data-column-picker="off">
                                    <table>
                                        <thead><tr><th>S/N</th><th>Land Ref</th><th>Surveyor</th><th class="ctr">Portion</th><th class="num">Area (Ha)</th>
                                            <th class="ctr">Farmers</th><th class="ctr">Plots</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Status</th></tr></thead>
                                        <tbody>
                                            @foreach ($p->cases as $land)
                                                @php
                                                    $lDistrict = \App\Support\AddressBuilder::resolve($land->prop_district, $land->prop_district_other);
                                                    $lLga = \App\Support\AddressBuilder::resolve($land->prop_lga);
                                                @endphp
                                                <tr>
                                                    <td class="muted">{{ $loop->iteration }}</td>
                                                    <td class="code">{{ $land->case_ref }}</td>
                                                    <td>{{ $land->survey_officer ?: '—' }}</td>
                                                    <td class="ctr">{{ $land->portion_no ?: '—' }}</td>
                                                    <td class="num">{{ $ha($land->area_ha) }}</td>
                                                    <td class="ctr">{{ number_format($land->beneficiaries_count) }}</td>
                                                    <td class="ctr">{{ $land->num_plots !== null ? number_format($land->num_plots) : '—' }}</td>
                                                    <td>{{ $land->prop_plot ?: '—' }}</td>
                                                    <td>{{ $lDistrict ?: '—' }}</td>
                                                    <td>{{ $lLga ? mb_strtoupper($lLga) : '—' }}</td>
                                                    <td>{{ \App\Support\AddressBuilder::propertyLocation(['district' => $land->prop_district, 'district_other' => $land->prop_district_other, 'lga' => $land->prop_lga, 'state' => $land->prop_state]) ?: '—' }}</td>
                                                    <td>{{ $land->status ?: '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="17" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No projects yet.
                            <a href="{{ route('survey-module.compensation.projects.create') }}">Create the first one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $projects->firstItem() ?? 0 }}–{{ $projects->lastItem() ?? 0 }}
            of {{ number_format($projects->total()) }} projects · the number beside a surveyor is how many portions they surveyed · click a Lands number to open them
        </span>
        <div class="pagination">{{ $projects->links() }}</div>
    </div>
</div>

<style>
    .survey-proto .pj-lands-btn { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; border: 1px solid var(--primary-100, #dbeafe);
        background: var(--primary-50, #eff6ff); color: var(--primary, #2563eb); font-weight: 600; font-size: 13px; cursor: pointer; }
    .survey-proto .pj-lands-btn i { font-size: 10px; }
    .survey-proto .pj-lands { border: 1px solid var(--gray-200, #e5e7eb); border-radius: 8px; overflow: auto; background: #fff; }
    .pj-modal { position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, .45); }
    .pj-modal[hidden] { display: none; }
    .pj-modal-box { background: #fff; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,.25); width: min(1100px, 100%); max-height: 88vh; display: flex; flex-direction: column; }
    .pj-modal-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #e5e7eb; }
    .pj-modal-head h3 { margin: 0; font-size: 16px; font-weight: 700; color: #111827; }
    .pj-modal-head .sub { font-size: 12px; color: #6b7280; margin-top: 2px; }
    .pj-modal-close { border: 0; background: transparent; width: 32px; height: 32px; border-radius: 999px; font-size: 16px; color: #4b5563; cursor: pointer; }
    .pj-modal-close:hover { background: #f3f4f6; }
    .pj-modal-body { padding: 14px 18px 18px; overflow: auto; }
    .survey-proto .pj-lands table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .survey-proto .pj-lands th { text-align: left; padding: 7px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-600, #4b5563); background: var(--gray-50, #f9fafb); border-bottom: 1px solid var(--gray-200, #e5e7eb); white-space: nowrap; }
    .survey-proto .pj-lands td { padding: 6px 10px; border-bottom: 1px solid var(--gray-100, #f3f4f6); }
    .survey-proto .pj-lands .num { text-align: right; }
    .survey-proto .pj-lands .ctr { text-align: center; }
</style>
<div class="pj-modal" id="pjLandsModal" data-column-picker="off" role="dialog" aria-modal="true" aria-labelledby="pjLandsTitle" hidden>
    <div class="pj-modal-box">
        <div class="pj-modal-head">
            <div><h3 id="pjLandsTitle">Lands</h3><div class="sub" id="pjLandsSub"></div></div>
            <button type="button" class="pj-modal-close" onclick="pjCloseLands()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="pj-modal-body survey-proto" id="pjLandsBody"></div>
    </div>
</div>
<script>
    // View Lands: a project's lands in a modal (user, 2026-11-03), copied from its hidden row.
    function pjShowLands(id) {
        var row = document.querySelector('tr.pj-lands-row[data-parent="' + id + '"]');
        var modal = document.getElementById('pjLandsModal');
        if (!row || !modal) return;
        var n = parseInt(row.dataset.count || '0', 10);
        document.getElementById('pjLandsTitle').textContent = row.dataset.title || 'Lands';
        document.getElementById('pjLandsSub').textContent = n + (n === 1 ? ' land' : ' lands');
        document.getElementById('pjLandsBody').innerHTML = row.querySelector('.pj-lands').outerHTML;
        if (modal.parentNode !== document.body) document.body.appendChild(modal);   // above the layout's stacking
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        modal.querySelector('.pj-modal-close').focus();
    }
    function pjCloseLands() {
        var modal = document.getElementById('pjLandsModal');
        if (!modal || modal.hidden) return;
        modal.hidden = true;
        document.body.style.overflow = '';
        document.getElementById('pjLandsBody').innerHTML = '';
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') pjCloseLands(); });
    document.addEventListener('click', function (e) { if (e.target && e.target.id === 'pjLandsModal') pjCloseLands(); });
</script>
