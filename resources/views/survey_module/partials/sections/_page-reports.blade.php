@include('survey_module.partials._flash')

<form method="GET" class="report-filters">
    <div class="form-group">
        <label>From</label>
        <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}" />
    </div>
    <div class="form-group">
        <label>To</label>
        <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}" />
    </div>
    <div class="form-group">
        <label>&nbsp;</label>
        <button class="btn btn-primary" type="submit"><i class="fas fa-chart-bar"></i> Run Report</button>
    </div>
</form>

<p class="helper-text" style="margin-bottom:16px;">
    Covering {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}.
</p>

<div class="section-cards">
    <div class="section-card">
        <div class="section-icon"><i class="fas fa-coins"></i></div>
        <h4>Compensation</h4>
        <div class="stat">{{ number_format($summary['compensation']['cases']) }}</div>
        <p>{{ number_format($summary['compensation']['beneficiaries']) }} beneficiaries &middot;
           &#8358;{{ number_format($summary['compensation']['cash'], 2) }} in tree valuations</p>
    </div>
    <div class="section-card">
        <div class="section-icon"><i class="fas fa-building"></i></div>
        <h4>GKN</h4>
        <div class="stat">{{ number_format($summary['gkn']['records']) }}</div>
        <p>{{ number_format($summary['gkn']['in_transit']) }} pending survey</p>
    </div>
    <div class="section-card">
        <div class="section-icon"><i class="fas fa-check-double"></i></div>
        <h4>Examination</h4>
        <div class="stat">{{ number_format($summary['examination']['queued']) }}</div>
        <p>{{ number_format($summary['examination']['passed']) }} passed &middot;
           {{ number_format($summary['examination']['returned']) }} returned</p>
    </div>
    <div class="section-card">
        <div class="section-icon"><i class="fas fa-th"></i></div>
        <h4>Plot Allocation</h4>
        <div class="stat">{{ number_format($summary['plots']['rows']) }}</div>
        <p>{{ number_format($summary['plots']['lpkn']) }} layout plans registered</p>
    </div>
    <div class="section-card">
        <div class="section-icon"><i class="fas fa-file-signature"></i></div>
        <h4>Occupancy Permits</h4>
        <div class="stat">{{ number_format($summary['op']['issued']) }}</div>
        <p>{{ number_format($summary['op']['queue']) }} still in the pipeline</p>
    </div>
</div>

<div class="comp-type-note" style="margin-top:20px;">
    <i class="fas fa-info-circle"></i>
    Detailed breakdowns live on the
    <a href="{{ route('survey-module.compensation.reports') }}">Compensation</a> and
    <a href="{{ route('survey-module.gkn.reports') }}">GKN</a> reports.
</div>
