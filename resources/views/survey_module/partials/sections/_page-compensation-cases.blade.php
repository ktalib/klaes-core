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
            <a class="btn btn-outline" href="{{ route('survey-module.compensation.op', ['case' => $detail->id]) }}">
                <i class="fas fa-file-signature"></i> Generate OP
            </a>
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
                        {{ $detail->isMonetary() ? 'Monetary (Cash for Trees)' : 'Land-for-Land (50:50)' }}
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
                        <span class="total-label"><i class="fas fa-calculator"></i> Scheme Total (Monetary)</span>
                        <span class="total-amount">₦{{ number_format($treeTotal, 2) }} cash to beneficiaries</span>
                    </div>
                    <div class="comp-type-note" style="margin-top:16px;">
                        <i class="fas fa-info-circle"></i>
                        Monetary compensation only — no plots are allocated on this case.
                    </div>
                </div>
            @else
                <div class="comp-panel active" style="margin-top:20px;">
                    <div class="comp-cards single">
                        <div class="comp-card" style="border-color:var(--primary);background:var(--primary-50);">
                            <div class="card-icon"><i class="fas fa-map-marked-alt"></i></div>
                            <div class="card-label">Land-for-Land Compensation (50:50)</div>
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
                        Land-for-Land only — no cash is paid for trees on this case. Assign plot and OP numbers under
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
                        <div class="tree-list">
                            <div class="list-header">
                                <span>Tree Type</span><span>Quantity</span>
                                <span class="hide-mobile">Unit Price (₦)</span><span>Total (₦)</span><span></span>
                            </div>
                            @forelse ($detail->trees as $t)
                                <div class="list-item">
                                    <span><strong>{{ $t->tree_type }}</strong></span>
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
        <a href="{{ route('survey-module.compensation.cases.register') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Register Case
        </a>
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
        <div class="left">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search case, project, officer…"
                   style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:250px;" />
            <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
                <option value="">All Status</option>
                @foreach (App\Http\Controllers\Survey\CaseController::STATUSES as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ $statusLabel($s) }}</option>
                @endforeach
            </select>
            <select name="project" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
                <option value="">All Projects</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}" @selected((string) request('project') === (string) $p->id)>
                        {{ $p->project_code }} · {{ $p->name }}
                    </option>
                @endforeach
            </select>
            <select name="scheme" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
                <option value="">All Schemes</option>
                <option value="monetary" @selected(request('scheme') === 'monetary')>Monetary</option>
                <option value="land" @selected(request('scheme') === 'land')>Land-for-Land</option>
            </select>
            <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
            @if (request()->hasAny(['q', 'status', 'project', 'scheme']))
                <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.cases') }}">Clear</a>
            @endif
        </div>
    </form>

    <div class="table-wrapper">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Case No</th>
                        <th>Project</th>
                        <th>Location</th>
                        <th>Area (Ha)</th>
                        <th>Beneficiaries</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cases as $c)
                        <tr>
                            <td>
                                <strong>{{ $c->case_ref }}</strong>
                                <div style="font-size:11px;color:var(--gray-500);">
                                    {{ $c->isMonetary() ? 'Monetary' : 'Land-for-Land' }}
                                </div>
                            </td>
                            <td>{{ $c->project?->name ?? '—' }}</td>
                            <td>{{ $c->property_location ?: '—' }}</td>
                            <td>{{ $c->area_ha !== null ? number_format((float) $c->area_ha, 2) : '—' }}</td>
                            <td>{{ number_format($c->beneficiaries_count) }}</td>
                            <td>
                                <span class="status-badge {{ $badgeClass($c->status) }}">
                                    <span class="dot"></span>{{ $statusLabel($c->status) }}
                                </span>
                            </td>
                            <td>{{ optional($c->created_at)->format('Y-m-d') ?? '—' }}</td>
                            <td>
                                <div class="action-icons">
                                    <a href="{{ route('survey-module.compensation.cases.show', $c) }}" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('survey-module.compensation.cases.edit', $c) }}" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="{{ route('survey-module.compensation.op', ['case' => $c->id]) }}" title="Generate OP"><i class="fas fa-file-signature"></i></a>
                                    <form method="POST" action="{{ route('survey-module.compensation.cases.destroy', $c) }}"
                                          style="display:inline" onsubmit="return confirm('Delete {{ $c->case_ref }}? This cannot be undone.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
                                <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                                @if (request()->hasAny(['q', 'status', 'project', 'scheme']))
                                    No cases match these filters.
                                    <a href="{{ route('survey-module.compensation.cases') }}">Clear them</a>.
                                @else
                                    No compensation cases yet.
                                    <a href="{{ route('survey-module.compensation.cases.register') }}">Register the first one</a>.
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
