<div class="modal-header">
    <h4 class="modal-title">
        <i data-lucide="file-check" class="w-5 h-5 mr-2"></i>
        SUA Units Overview - Certificate of Occupancy (CoFO)
    </h4>
    <button type="button" class="close" data-dismiss="modal">&times;</button>
</div>

<div class="modal-body">
    <div class="alert alert-info mb-4">
        <i data-lucide="info" class="w-4 h-4 mr-2"></i>
        <strong>SUA Certificate Pipeline:</strong> This overview shows standalone units and their CoFO readiness. 
        Units require completed Memo and RoFO processing before certificate generation.
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card bg-primary text-white">
                <div class="stat-number">{{ $stats['total_units'] }}</div>
                <div class="stat-label">Total SUA Units</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card bg-info text-white">
                <div class="stat-number">{{ $stats['memo_generated'] }}</div>
                <div class="stat-label">Memo Generated</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card bg-warning text-white">
                <div class="stat-number">{{ $stats['rofo_generated'] }}</div>
                <div class="stat-label">RoFO Generated</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card bg-success text-white">
                <div class="stat-number">{{ $stats['ready'] }}</div>
                <div class="stat-label">Ready for CoFO</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card bg-secondary text-white">
                <div class="stat-number">{{ $stats['generated'] }}</div>
                <div class="stat-label">Certificates Issued</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card bg-danger text-white">
                <div class="stat-number">{{ $stats['pending'] }}</div>
                <div class="stat-label">Still Pending</div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="bg-light">
                <tr>
                    <th>ST FileNo</th>
                    <th>Owner Details</th>
                    <th>LGA/Land Use</th>
                    <th>Memo Status</th>
                    <th>RoFO Status</th>
                    <th>Certificate Status</th>
                    <th>CoFO Readiness</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($units as $unit)
                    <tr class="application-row">
                        <td>
                            <div class="file-number">
                                <strong>{{ $unit->fileno ?? 'N/A' }}</strong>
                                @if(!empty($unit->unit_number))
                                    <br><small class="text-muted">Unit: {{ $unit->unit_number }}</small>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="owner-info">
                                <strong>{{ $unit->owner_name ?? 'N/A' }}</strong>
                                @if(!empty($unit->applicant_type))
                                    <br><small class="text-muted">{{ ucfirst($unit->applicant_type) }}</small>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="location-info">
                                <div><strong>LGA:</strong> {{ $unit->property_lga }}</div>
                                @if(!empty($unit->land_use))
                                    <small class="text-muted">{{ $unit->land_use }}</small>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($unit->memo_generated)
                                <span class="badge badge-success">
                                    <i data-lucide="check" class="w-3 h-3 mr-1"></i>
                                    Generated
                                </span>
                                @if(!empty($unit->memo_generated_at))
                                    <br><small class="text-muted">{{ Carbon\Carbon::parse($unit->memo_generated_at)->format('M j, Y') }}</small>
                                @endif
                            @else
                                <span class="badge badge-secondary">
                                    <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                                    Pending
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($unit->rofo_generated)
                                <span class="badge badge-success">
                                    <i data-lucide="check" class="w-3 h-3 mr-1"></i>
                                    Generated
                                </span>
                                @if(!empty($unit->rofo_no))
                                    <br><small class="text-muted">{{ $unit->rofo_no }}</small>
                                @endif
                                @if(!empty($unit->rofo_generated_at))
                                    <br><small class="text-muted">{{ Carbon\Carbon::parse($unit->rofo_generated_at)->format('M j, Y') }}</small>
                                @endif
                            @else
                                <span class="badge badge-secondary">
                                    <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                                    Pending
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($unit->certificate_issued)
                                <span class="badge badge-primary">
                                    <i data-lucide="award" class="w-3 h-3 mr-1"></i>
                                    Issued
                                </span>
                                @if(!empty($unit->certificate_number))
                                    <br><small class="text-muted">{{ $unit->certificate_number }}</small>
                                @endif
                                @if(!empty($unit->certificate_generated_at))
                                    <br><small class="text-muted">{{ Carbon\Carbon::parse($unit->certificate_generated_at)->format('M j, Y') }}</small>
                                @endif
                            @else
                                <span class="badge badge-warning">
                                    <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                                    Not Issued
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $unit->progress_class ?? 'badge-secondary' }}">
                                {{ $unit->progress_status ?? 'Unknown' }}
                            </span>
                            @if(!empty($unit->readiness_message))
                                <br><small class="text-muted">{{ $unit->readiness_message }}</small>
                            @endif
                        </td>
                        <td>
                            <div class="dd">
                                <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                </button>
                                <div class="dd-menu">
                                    <a href="{{ route('sub.show', $unit->id) }}" class="dd-item">
                                        <i data-lucide="eye" class="w-4 h-4 text-sky-500"></i>
                                        <span>View Details</span>
                                    </a>
                                    
                                    @if($unit->ready_for_cofo && !$unit->certificate_issued)
                                        <a href="{{ route('cofo.generate', $unit->id) }}" class="dd-item">
                                            <i data-lucide="file-plus" class="w-4 h-4 text-emerald-500"></i>
                                            <span>Generate CoFO</span>
                                        </a>
                                    @endif
                                    
                                    @if($unit->certificate_issued)
                                        <a href="{{ route('cofo.print', $unit->id) }}" class="dd-item">
                                            <i data-lucide="printer" class="w-4 h-4 text-indigo-500"></i>
                                            <span>View Certificate</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">
        <i data-lucide="x" class="w-4 h-4 mr-1"></i>
        Close
    </button>
    <a href="{{ route('cofo.bulk-generate') }}" class="btn btn-success">
        <i data-lucide="file-check-2" class="w-4 h-4 mr-1"></i>
        Bulk Generate Ready CoFOs
    </a>
</div>

<style>
.stat-card {
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    line-height: 1;
}

.stat-label {
    font-size: 11px;
    opacity: 0.9;
    margin-top: 4px;
}

.application-row:hover {
    background-color: #f8f9fa;
}

.file-number {
    font-family: monospace;
}

.owner-info strong {
    color: #2c3e50;
}

.location-info {
    font-size: 0.9em;
}

.badge-issued {
    background-color: #28a745;
    color: white;
}

.badge-approved {
    background-color: #007bff;
    color: white;
}

.badge-blocked {
    background-color: #dc3545;
    color: white;
}

.btn-group-vertical .btn {
    margin-bottom: 2px;
}
</style>