@include('survey_module.partials._flash')

<div class="quick-win">
    <div>
        <span class="badge"><i class="fas fa-bolt"></i> Quick Win</span>
        <h3>Automated Occupancy Permit Workflow</h3>
        <p>Survey &rarr; GIS/KANGIS &rarr; Commissioner &rarr; Deeds &rarr; Land/OSS</p>
    </div>
    <a class="btn btn-success" href="{{ route('survey-module.workflow.occupancy') }}">
        <i class="fas fa-arrow-right"></i> View Workflow
    </a>
</div>

<div class="kpi-grid">
    @foreach ([
        ['fa-folder-open',     'Total Compensation Cases', $kpis['cases']],
        ['fa-hourglass-half',  'Pending Verification',     $kpis['pending']],
        ['fa-file-signature',  'Occupancy Permits Issued', $kpis['ops_issued']],
        ['fa-landmark',        'Government Land Records',  $kpis['gkn']],
        ['fa-check-circle',    'Completed Cases',          $kpis['completed']],
        ['fa-clock',           'Awaiting GIS Certification', $kpis['awaiting_gis']],
    ] as [$icon, $label, $value])
        <div class="kpi-card">
            <i class="fas {{ $icon }} kpi-icon"></i>
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value">{{ number_format($value) }}</div>
        </div>
    @endforeach
</div>

<div class="dash-grid">
    <div class="dash-card">
        <div class="card-header">
            <h3><i class="fas fa-project-diagram" style="color:var(--primary);margin-right:8px;"></i>OP Workflow Status</h3>
            <a href="{{ route('survey-module.workflow.occupancy') }}">View All &rarr;</a>
        </div>

        @if ($op && $op->steps->isNotEmpty())
            <p class="helper-text" style="margin-bottom:10px;">
                {{ $op->op_number }}@if ($op->case) &middot; {{ $op->case->case_ref }} @endif
            </p>
            <div class="workflow-timeline">
                @foreach ($op->steps as $step)
                    <div class="workflow-step">
                        <div class="step-icon {{ $step->status === 'done' ? 'done' : ($step->status === 'active' ? 'pending' : 'waiting') }}">
                            <i class="fas {{ $step->status === 'done' ? 'fa-check' : ($step->status === 'active' ? 'fa-spinner fa-pulse' : 'fa-clock') }}"></i>
                        </div>
                        <div class="step-info">
                            <div class="step-name">{{ $step->step_no }}. {{ $step->step_name }}</div>
                            <div class="step-status">{{ $step->actor ?: '—' }}</div>
                        </div>
                        <div class="step-time">
                            {{ $step->completed_at ? $step->completed_at->diffForHumans() : '—' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div style="text-align:center;padding:28px;color:var(--gray-500);">
                <i class="fas fa-project-diagram" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                No occupancy permits yet.
                <a href="{{ route('survey-module.compensation.op') }}">Generate one</a>.
            </div>
        @endif
    </div>

    <div class="dash-card">
        <div class="card-header">
            <h3><i class="fas fa-bolt" style="color:var(--accent);margin-right:8px;"></i>Recent Activity</h3>
        </div>
        <div class="activity-feed">
            @forelse ($activities as $a)
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas {{ $a['icon'] }}" @if ($a['colour']) style="color:{{ $a['colour'] }};" @endif></i>
                    </div>
                    <div class="activity-content">
                        <div class="text">{!! $a['text'] !!}</div>
                        <div class="time">{{ $a['when'] ? $a['when']->diffForHumans() : '—' }}</div>
                    </div>
                </div>
            @empty
                <div style="text-align:center;padding:28px;color:var(--gray-500);">
                    Nothing recorded yet.
                </div>
            @endforelse
        </div>
    </div>
</div>

<div class="section-cards">
    @foreach ([
        ['survey-module.compensation.dashboard', 'fa-coins',        'Compensation', 'Cases needing attention', $sections['compensation'], 'pending'],
        ['survey-module.gkn.dashboard',          'fa-building',     'GKN',          'Government land records', $sections['gkn'], 'indexed'],
        ['survey-module.records.lpkn',           'fa-layer-group',  'LPKN',         'Layout plan registrations', $sections['lpkn'], 'not yet approved'],
        ['survey-module.workflow.examination',   'fa-check-double', 'Examination',  'QA/QC verification queue', $sections['examination'], 'awaiting review'],
    ] as [$route, $icon, $title, $blurb, $stat, $unit])
        <a class="section-card" href="{{ route($route) }}" style="cursor:pointer;text-decoration:none;color:inherit;">
            <div class="section-icon"><i class="fas {{ $icon }}"></i></div>
            <h4>{{ $title }}</h4>
            <p>{{ $blurb }}</p>
            <div class="stat">{{ number_format($stat) }}
                <span style="font-size:14px;font-weight:400;color:var(--gray-500);">{{ $unit }}</span>
            </div>
        </a>
    @endforeach
</div>
