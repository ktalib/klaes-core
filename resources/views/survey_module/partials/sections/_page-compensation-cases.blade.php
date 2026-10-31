@php
    /**
     * Compensation cases. Two jobs in one partial:
     *   $detail set  -> the read-only drill-down for CaseController::show()
     *   otherwise    -> the searchable, filterable, paginated list
     *
     * "Review" is stored on the row; the register calls that state Pending Review,
     * so that is what the badge says.
     */
    $detail = $detail ?? null;

    $badgeClass = fn ($s) => match ($s) {
        'Active'    => 'active',
        'Completed' => 'completed',
        'Review'    => 'review',
        'Rejected'  => 'rejected',
        default     => 'pending',
    };
    $statusLabel = fn ($s) => $s === 'Review' ? 'Pending Review' : $s;
@endphp

@include('survey_module.partials._flash')
@include('survey_module.partials._searchable_selects')

@if ($detail)
    @php
        $treeTotal = $detail->tree_total;
    @endphp

    <div class="page-header">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn btn-secondary" href="{{ route('survey-module.compensation.cases') }}">
                <i class="fas fa-arrow-left"></i> Back to Cases
            </a>
            <a class="btn btn-primary" href="{{ route('survey-module.compensation.cases.edit', $detail) }}">
                <i class="fas fa-edit"></i> Edit Case
            </a>
            @if ($detail->status !== 'Review')
                <form method="POST" action="{{ route('survey-module.compensation.cases.submit', $detail) }}"
                      onsubmit="return confirm('Submit {{ $detail->case_ref }} for review?');">
                    @csrf
                    <button class="btn btn-success" type="submit">
                        <i class="fas fa-paper-plane"></i> Submit for Review
                    </button>
                </form>
            @endif
            {{-- No Generate OP on existing records, imported or captured from paper (CR 30102026 item 32). --}}
            @unless ($detail->isExistingRecord())
            <a class="btn btn-outline" href="{{ route('survey-module.compensation.op', ['case' => $detail->id]) }}">
                <i class="fas fa-file-signature"></i> Generate OP
            </a>
            @endunless
        </div>
    </div>

    <div class="form-container">
        <div class="form-body">
            <div class="preview-grid">
                <div class="preview-item">
                    <span class="label">Case No</span>
                    <span class="value">{{ $detail->case_ref }}</span>
                </div>
                <div class="preview-item">
                    <span class="label">Project</span>
                    <span class="value">
                        {{ $detail->project?->name ?? '—' }}
                        @if ($detail->project)
                            <span style="color:var(--gray-500);font-weight:500;">({{ $detail->project->project_code }})</span>
                        @endif
                    </span>
                </div>
                <div class="preview-item">
                    <span class="label">Location</span>
                    <span class="value">{{ $detail->property_location ?: '—' }}</span>
                </div>
                <div class="preview-item">
                    <span class="label">Purpose</span>
                    <span class="value">{{ $detail->purpose ?: '—' }}</span>
                </div>
                <div class="preview-item">
                    <span class="label">Survey Officer</span>
                    <span class="value">{{ $detail->survey_officer ?: '—' }}</span>
                </div>
                <div class="preview-item">
                    <span class="label">Surveyor / Portion</span>
                    <span class="value">@if ($detail->allocation)
    {{ $detail->allocation->surveyor_name }}
    <div style="font-size:11px;color:var(--gray-500);">Portion {{ $detail->portion_no }} of {{ $detail->allocation->portions }} · {{ rtrim(rtrim(number_format((float) $detail->portion_size_ha, 4), '0'), '.') }} Ha</div>
@else
    <span style="color:var(--gray-500);">—</span>
@endif</span>
                </div>
                <div class="preview-item">
                    <span class="label">Date</span>
                    <span class="value">{{ optional($detail->case_date)->format('Y-m-d') ?: '—' }}</span>
                </div>
                <div class="preview-item">
                    <span class="label">Area</span>
                    <span class="value">{{ number_format((float) $detail->area_ha, 2) }} Ha</span>
                </div>
                <div class="preview-item">
                    <span class="label">Coordinates</span>
                    <span class="value">{{ $detail->coordinates ?: '—' }}</span>
                </div>
                <div class="preview-item">
                    <span class="label">GPS Reading</span>
                    <span class="value">{{ $detail->gps_reading ?: '—' }}</span>
                </div>
                <div class="preview-item">
                    <span class="label">Compensation Scheme (from Project)</span>
                    <span class="value" style="color:var(--primary);font-weight:700;">
                        {{ $detail->isMonetary() ? 'Monetary Compensation' : 'Land to Land (50:50)' }}
                    </span>
                </div>
                <div class="preview-item">
                    <span class="label">Status</span>
                    <span class="value">
                        <span class="status-badge {{ $badgeClass($detail->status) }}">
                            <span class="dot"></span>{{ $statusLabel($detail->status) }}
                        </span>
                    </span>
                </div>
                <div class="preview-item full">
                    <span class="label">Description</span>
                    <span class="value">{{ $detail->description ?: '—' }}</span>
                </div>
            </div>

            {{-- The compensation summary is exclusive: one scheme, one panel. --}}
            @if ($detail->isMonetary())
                <div class="comp-panel active" style="margin-top:20px;">
                    <div class="comp-total">
                        <span class="total-label"><i class="fas fa-calculator"></i> Scheme Total (Monetary Compensation)</span>
                        <span class="total-amount">₦{{ number_format($treeTotal, 2) }} cash to beneficiaries</span>
                    </div>
                    <div class="comp-type-note" style="margin-top:16px;">
                        <i class="fas fa-info-circle"></i>
                        Monetary Compensation only — no plots are allocated on this case.
                    </div>
                </div>
            @else
                <div class="comp-panel active" style="margin-top:20px;">
                    <div class="comp-cards single">
                        <div class="comp-card" style="border-color:var(--primary);background:var(--primary-50);">
                            <div class="card-icon"><i class="fas fa-map-marked-alt"></i></div>
                            <div class="card-label">Land to Land Compensation (50:50)</div>
                            <div style="font-size:20px;font-weight:600;color:var(--primary);margin:8px 0;">
                                {{ number_format($split['total'] ?? 0) }} Plots Total
                            </div>
                            <div class="card-detail">Physical land allocation · No cash component</div>
                            <div class="land-split">
                                <div class="split-item">
                                    <div class="num">{{ $split['farmer'] ?? 0 }}</div>
                                    <div class="label"><i class="fas fa-user" style="color:var(--secondary-dark);"></i> Farmer</div>
                                </div>
                                <div style="font-size:32px;color:var(--gray-300);">:</div>
                                <div class="split-item">
                                    <div class="num">{{ $split['govt'] ?? 0 }}</div>
                                    <div class="label"><i class="fas fa-building" style="color:var(--info);"></i> Government</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="comp-type-note" style="margin-top:16px;">
                        <i class="fas fa-info-circle"></i>
                        Land to Land only — no cash is paid for trees on this case. Assign plot and OP numbers under
                        <a href="{{ route('survey-module.tools.plot-allocation', ['case' => $detail->id]) }}">Plot Allocation</a>.
                    </div>
                </div>
            @endif

            <div class="form-grid" style="margin-top:8px;">
                <div class="form-group full">
                    <label>Beneficiaries ({{ $detail->beneficiaries->count() }})</label>
                    <div class="beneficiary-list">
                        <div class="list-header">
                            <span>Name</span><span>Phone</span><span class="hide-mobile">NIN</span>
                            <span>Status</span><span class="hide-mobile">Address</span><span>Actions</span>
                        </div>
                        @forelse ($detail->beneficiaries as $b)
                            <div class="list-item">
                                <span><strong>{{ $b->full_name }}</strong></span>
                                <span>{{ $b->phone ?: '—' }}</span>
                                <span class="hide-mobile">{{ $b->nin ?: '—' }}</span>
                                <span>
                                    <span class="status-badge {{ $badgeClass($b->status) }}">
                                        <span class="dot"></span>{{ $b->status }}
                                    </span>
                                </span>
                                <span class="hide-mobile">{{ $b->person_address ?: '—' }}</span>
                                <span>
                                    <a class="btn btn-secondary btn-xs"
                                       href="{{ route('survey-module.compensation.beneficiaries.edit', $b) }}">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </span>
                            </div>
                        @empty
                            <div class="list-item" style="color:var(--gray-500);">
                                <span>No beneficiaries recorded on this case yet.</span>
                            </div>
                        @endforelse
                    </div>
                </div>

                @if ($detail->isMonetary())
                    <div class="form-group full">
                        <label>Economic Trees ({{ $detail->trees->count() }})</label>
                        <style>
                            /* Category column (Mature / Early, 2026-10-30), as on the Register Land wizard. */
                            @media (min-width: 769px) { .survey-proto .tree-list.tl-m .list-header, .survey-proto .tree-list.tl-m .list-item { grid-template-columns: 2fr 1fr 1fr 1.5fr 1.5fr auto; } }
                        </style>
                        <div class="tree-list tl-m">
                            <div class="list-header">
                                <span>Tree Type</span><span>Category</span><span>Quantity</span>
                                <span class="hide-mobile">Unit Price (₦)</span><span>Total (₦)</span><span></span>
                            </div>
                            @forelse ($detail->trees as $t)
                                <div class="list-item">
                                    <span><strong>{{ $t->tree_type }}</strong></span>
                                    <span>{{ $t->maturity ?: 'Mature' }}</span>
                                    <span>{{ number_format($t->quantity) }}</span>
                                    <span class="hide-mobile">{{ number_format((float) $t->unit_price, 2) }}</span>
                                    <span class="total">{{ number_format((float) $t->line_total, 2) }}</span>
                                    <span></span>
                                </div>
                            @empty
                                <div class="list-item" style="color:var(--gray-500);">
                                    <span>No tree lines recorded — this monetary case has nothing to value yet.</span>
                                </div>
                            @endforelse
                            <div class="list-total">
                                Total Trees Value: <span>₦{{ number_format($treeTotal, 2) }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="form-group full">
                    <label>Field Photos ({{ $detail->photos->count() }})</label>
                    @include('survey_module.partials._case_photos', ['case' => $detail])
                </div>

                <div class="form-group full">
                    <label>Occupancy Permits ({{ $detail->opRecords->count() }})</label>
                    <p class="helper-text">
                        @if ($detail->opRecords->isEmpty())
                            None generated yet.
                            <a href="{{ route('survey-module.compensation.op', ['case' => $detail->id]) }}">Generate an OP</a>.
                        @else
                            {{ $detail->opRecords->pluck('op_number')->filter()->implode(', ') ?: 'Draft records exist.' }}
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="page-header">
        <div></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('survey-module.compensation.capture-list') }}" class="btn btn-secondary">
                <i class="fas fa-clipboard-list"></i> Capture Compensation List
            </a>
            <a href="{{ route('survey-module.compensation.cases.register') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Register
            </a>
        </div>
    </div>

    {{-- Imported / Captured tabs (CR 30102026 item 23); they replace the All Records dropdown. --}}
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;" role="tablist">
        @foreach (['imported' => ['Imported', 'fa-file-import'], 'entered' => ['Captured', 'fa-keyboard']] as $k => [$label, $icon])
            <a href="{{ route('survey-module.compensation.cases', ['origin' => $k]) }}" role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}"
               class="btn {{ $tab === $k ? 'btn-primary' : 'btn-outline' }} btn-sm">
                <i class="fas {{ $icon }}"></i> {{ $label }} <span style="opacity:.75;">({{ number_format($tabCounts[$k]) }})</span>
            </a>
        @endforeach
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Cases</div>
            <div class="kpi-value">{{ number_format($stats['total']) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Active</div>
            <div class="kpi-value">{{ number_format($stats['active']) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Pending Review</div>
            <div class="kpi-value">{{ number_format($stats['review']) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Completed</div>
            <div class="kpi-value">{{ number_format($stats['completed']) }}</div>
        </div>
    </div>

    <form method="GET" class="table-toolbar">
        <input type="hidden" name="origin" value="{{ $tab }}" />
        <div class="left">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search case, project, officer…"
                   style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:250px;" />
            <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
                <option value="">All Status</option>
                @foreach (App\Http\Controllers\Survey\CaseController::STATUSES as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ $statusLabel($s) }}</option>
                @endforeach
            </select>
            <select name="project" class="sv-searchable" data-width="300px" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
                <option value="">All Projects</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}" @selected((string) request('project') === (string) $p->id)>
                        {{ $p->project_code }} · {{ $p->name }}
                    </option>
                @endforeach
            </select>
            <select name="scheme" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
                <option value="">All Schemes</option>
                <option value="monetary" @selected(request('scheme') === 'monetary')>Monetary Compensation</option>
                <option value="land" @selected(request('scheme') === 'land')>Land to Land</option>
            </select>
            <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
            @if (request()->hasAny(['q', 'status', 'project', 'scheme']))
                <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.cases', ['origin' => $tab]) }}">Clear</a>
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
                        <th>Project</th>
                        <th>TP No.</th>
                        <th>Scheme</th>
                        <th>Surveyor / Portion</th>
                        <th>Location</th>
                        <th class="num">Area (Ha)</th>
                        <th class="ctr">Beneficiaries</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Date Created</th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cases as $c)
                        <tr>
                            <td class="muted">{{ $cases->firstItem() + $loop->index }}</td>
                            {{-- No case number column (user, 2026-10-30): the scheme has its own column instead. --}}
                            {{-- Project code dropped; the TP No. has its own column (user, 2026-10-30). --}}
                            <td class="wide strong">{{ $c->project?->display_name ?? '—' }}</td>
                            <td class="nowrap">{!! $c->project?->plan_no ? '<span class="tp">' . e($c->project->plan_no) . '</span>' : '<span class="muted">—</span>' !!}</td>
                            <td>
                                @if ($c->isMonetary())
                                    <span class="status-badge active"><span class="dot"></span>Monetary Compensation</span>
                                @else
                                    <span class="status-badge completed"><span class="dot"></span>Land to Land</span>
                                @endif
                            </td>
                            <td class="mid">@if ($c->allocation)
    {{ $c->allocation->surveyor_name }}
    <div style="font-size:11px;color:var(--gray-500);">Portion {{ $c->portion_no }} of {{ $c->allocation->portions }} · {{ rtrim(rtrim(number_format((float) $c->portion_size_ha, 4), '0'), '.') }} Ha</div>
@else
    <span style="color:var(--gray-500);">—</span>
@endif</td>
                            <td class="mid">{{ $c->property_location ?: '—' }}</td>
                            <td class="num">@if ($c->area_ha !== null){{ number_format((float) $c->area_ha, 4) }}@else<span class="muted">—</span>@endif</td>
                            <td class="ctr">{{ number_format($c->beneficiaries_count) }}</td>
                            <td>
                                <span class="status-badge {{ $badgeClass($c->status) }}">
                                    <span class="dot"></span>{{ $statusLabel($c->status) }}
                                </span>
                            </td>
                            @include('survey_module.partials._created_cells', ['row' => $c])
                            <td class="col-actions">
                                @php $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50'; @endphp
                                <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                    <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                            @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                        <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                    </button>
                                    <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                         :style="'top:' + top + 'px; left:' + left + 'px'"
                                         class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                        <a href="{{ route('survey-module.compensation.cases.show', $c) }}" class="{{ $svItem }}"><i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View</a>
                                        <a href="{{ route('survey-module.compensation.cases.edit', $c) }}" class="{{ $svItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                                        @unless ($c->isExistingRecord())
                                        <a href="{{ route('survey-module.compensation.op', ['case' => $c->id]) }}" class="{{ $svItem }}"><i data-lucide="file-signature" class="w-4 h-4 text-gray-500"></i> Generate OP</a>
                                        @endunless
                                        <form method="POST" action="{{ route('survey-module.compensation.cases.destroy', $c) }}"
                                              onsubmit="return confirm('Delete {{ $c->case_ref }}? This cannot be undone.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" style="text-align:center;padding:28px;color:var(--gray-500);">
                                <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                                @if (request()->hasAny(['q', 'status', 'project', 'scheme']))
                                    No lands match these filters.
                                    <a href="{{ route('survey-module.compensation.cases', ['origin' => $tab]) }}">Clear them</a>.
                                @elseif ($tab === 'entered')
                                    No captured lands yet.
                                    <a href="{{ route('survey-module.compensation.cases.register') }}">Register one</a> or
                                    <a href="{{ route('survey-module.compensation.capture-list') }}">capture a compensation list</a>.
                                @else
                                    No imported lands.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>
                Showing {{ $cases->firstItem() ?? 0 }}–{{ $cases->lastItem() ?? 0 }}
                of {{ number_format($cases->total()) }} cases · each case keeps the scheme of its project
            </span>
            <div class="pagination">{{ $cases->links() }}</div>
        </div>
    </div>
@endif
