@include('cadastral_module.partials._flash')

<div class="unit-tag"><i class="fas fa-id-card"></i> 4.3 · Cadastral Information</div>

<div class="page-header">
    <div><strong>{{ $card->card_ref }}</strong> · {{ $card->file_number }}</div>
    <div style="display:flex;gap:8px;">
        @canDo('Cad - Records', 'print')
            <a href="{{ route('cadastral-module.index-cards.print', $card) }}" target="_blank" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print the Card
            </a>
        @endcanDo
        <a href="{{ route('cadastral-module.index-cards.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back to the register
        </a>
    </div>
</div>

<div class="section-cards" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

    {{-- The card itself --}}
    <div class="dash-card">
        <div class="card-header"><strong>Card</strong></div>
        <div class="list-header"></div>
        <div class="list-item"><span>File Number</span><strong>{{ $card->file_number }}</strong></div>
        <div class="list-item"><span>File Name</span><strong>{{ $card->file_title ?: '—' }}</strong></div>
        <div class="list-item"><span>Plot</span><strong>{{ $card->plot_no ?: '—' }}</strong></div>
        <div class="list-item"><span>Block</span><strong>{{ $card->block_no ?: '—' }}</strong></div>
        <div class="list-item"><span>Layout</span><strong>{{ $card->layout_name ?: '—' }}</strong></div>
        {{-- District, LGA, State — the plot is shown separately above. --}}
        <div class="list-item"><span>Plot Location</span><strong>{{ $card->property_location ?: '—' }}</strong></div>
        <div class="list-item"><span>Survey Job Number</span><strong>{{ $card->survey_job_number ?: '—' }}</strong></div>
        <div class="list-item">
            <span>File Status</span>
            <strong>
                <span class="status-badge {{ $card->status_badge }}">
                    <span class="dot"></span>{{ $card->file_status_label }}
                </span>
            </strong>
        </div>
        <div class="list-item"><span>Commissioned</span><strong>{{ optional($card->commissioned_at)->format('d M Y H:i') ?: '—' }}</strong></div>
        @if ($receipt)
            <div class="list-item">
                <span>From Intake</span>
                <strong>{{ $receipt->receipt_ref }} · {{ $receipt->source_registry }}</strong>
            </div>
        @endif
        <div class="list-item"><span>Times Printed</span><strong>{{ $card->print_count }}</strong></div>
        @if ($card->chart)
            <div class="list-item">
                <span>Chart</span>
                <strong><a href="{{ route('cadastral-module.charting.edit', $card->chart) }}">{{ $card->chart->chart_ref }}</a></strong>
            </div>
        @endif
    </div>

    {{-- The card's own stage inside Cadastral, and the update form --}}
    <div class="dash-card" id="movement">
        <div class="card-header">
            <strong>Card Movement</strong>
            <span class="helper-text" style="margin:0;">The card's stage inside Cadastral. Each update is kept; none is edited.</span>
        </div>

        @canDo('Cad - Records', 'edit')
            <form method="POST" action="{{ route('cadastral-module.index-cards.movement.save', $card) }}"
                  style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;padding:10px 16px;">
                @csrf
                <select name="stage" required style="padding:6px 8px;border:1px solid var(--gray-300);border-radius:4px;font-size:13px;">
                    @foreach ($stageList as $key => $label)
                        <option value="{{ $key }}" @selected(old('stage') === $key) @disabled(($stages->first()['stage'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="text" name="note" placeholder="Note (optional)" maxlength="1000" value="{{ old('note') }}"
                       style="padding:6px 8px;border:1px solid var(--gray-300);border-radius:4px;font-size:13px;flex:1;min-width:140px;" />
                <button type="submit" class="btn btn-primary btn-xs"><i class="fas fa-right-left"></i> Update Movement</button>
            </form>
        @endcanDo

        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Stage</th><th>From</th><th>By</th><th>When</th><th>Note</th></tr>
                </thead>
                <tbody>
                    @forelse ($stages as $stage)
                        <tr>
                            <td><strong>{{ $stage['label'] }}</strong></td>
                            <td>{{ $stage['from'] ?: '—' }}</td>
                            <td>{{ $stage['by'] ?: '—' }}</td>
                            <td>{{ optional($stage['at'])->format('d M Y H:i') ?: '—' }}</td>
                            <td>{{ Str::limit($stage['note'], 50) ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;padding:18px;color:var(--gray-500);">
                                No stage has been recorded for this card.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- The file's movement record, live from the tracker --}}
<div class="table-wrapper" style="margin-top:18px;">
    <div class="table-toolbar">
        <div class="left">
            <strong>File Tracker</strong>
            <span class="helper-text" style="margin:0 0 0 8px;">Read live from the KLAES file tracker, not stored on the card.</span>
        </div>
        @if ($tracker)
            <a class="btn btn-outline btn-xs" href="{{ route('cadastral-module.registry.movements', ['file_number' => $card->file_number]) }}">
                Full movement history
            </a>
        @endif
    </div>

    @if (! $tracker)
        <div style="padding:22px;text-align:center;color:var(--gray-500);">
            This file is not in the tracker. That means it has never been tracked, not that it does not exist.
        </div>
    @else
        <div style="padding:10px 16px;font-size:13px;">
            Currently with <strong>{{ $tracker->current_office_name ?: '—' }}</strong>
            · Tracking ID {{ $tracker->tracking_id ?: '—' }}
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Office</th><th>In</th><th>Out</th><th>By</th><th>Note</th></tr>
                </thead>
                <tbody>
                    @forelse ($movements as $move)
                        <tr>
                            <td>{{ $move['office_name'] ?? $move['to_office_name'] ?? $move['to'] ?? '—' }}</td>
                            <td>{{ trim(($move['log_in_date'] ?? '') . ' ' . ($move['log_in_time'] ?? '')) ?: ($move['timestamp'] ?? '—') }}</td>
                            <td>{{ trim(($move['log_out_date'] ?? '') . ' ' . ($move['log_out_time'] ?? '')) ?: '—' }}</td>
                            <td>{{ $move['user_name'] ?? '—' }}</td>
                            <td>{{ Str::limit($move['notes'] ?? $move['status_label'] ?? $move['note'] ?? '', 60) ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;padding:18px;color:var(--gray-500);">
                                The movement log is empty.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Status history --}}
<div class="table-wrapper" style="margin-top:18px;">
    <div class="table-toolbar">
        <div class="left"><strong>File status history</strong></div>
        <a class="btn btn-outline btn-xs" href="{{ route('cadastral-module.file-status.index', ['q' => $card->file_number]) }}">
            Change the status
        </a>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>From</th><th>To</th><th>Effective</th><th>Authority</th><th>By</th><th>Remarks</th><th>Documents</th></tr>
            </thead>
            <tbody>
                @forelse ($card->statusEvents as $event)
                    <tr>
                        <td>{{ $event->from_status ? ucfirst(str_replace('_', ' ', $event->from_status)) : '—' }}</td>
                        <td><strong>{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}</strong></td>
                        <td>{{ optional($event->effective_date)->format('d M Y') ?: '—' }}</td>
                        <td>{{ $event->authority_ref ?: '—' }}</td>
                        <td>{{ $event->actor_name ?: '—' }}</td>
                        <td>{{ Str::limit($event->reason, 70) ?: '—' }}</td>
                        <td>@include('cadastral_module.partials._documents', ['docs' => $documents[$event->id] ?? collect()])</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:22px;color:var(--gray-500);">
                            The status has never been changed — the file has been open since it was commissioned.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Survey jobs against this file --}}
<div class="table-wrapper" style="margin-top:18px;">
    <div class="table-toolbar">
        <div class="left"><strong>Survey jobs</strong></div>
        <a class="btn btn-outline btn-xs" href="{{ route('cadastral-module.survey-jobs.index', ['q' => $card->file_number]) }}">Job register</a>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>Job Number</th><th>Surveyor</th><th>Firm</th><th>Instruction</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($card->surveyJobs as $job)
                    <tr>
                        <td>
                            <strong>{{ $job->job_number }}</strong>
                            @if ($job->job_number === $card->survey_job_number)
                                <span class="status-badge active"><span class="dot"></span>On the card</span>
                            @endif
                        </td>
                        <td>{{ $job->surveyor_name ?: '—' }}</td>
                        <td>{{ $job->firm_name ?: '—' }}</td>
                        <td>{{ $job->its_number ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $job->status_badge }}">
                                <span class="dot"></span>{{ $job->status }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;padding:22px;color:var(--gray-500);">
                            No survey job has been linked to this card.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Amend --}}
@canDo('Cad - Records', 'edit')
<form method="POST" action="{{ route('cadastral-module.index-cards.update', $card) }}" class="form-container" style="margin-top:18px;">
    @csrf @method('PUT')
    <div class="card-header"><strong>Amend the Card</strong></div>

    <div class="form-body">
        <div class="form-grid">
            <div class="form-group">
                <label>File Number</label>
                <input type="text" value="{{ $card->file_number }}" readonly style="background:var(--gray-100);" />
                <div class="helper-text">Fixed at commissioning — the card stays linked to its original file.</div>
            </div>
            <div class="form-group">
                <label>File Title</label>
                <input type="text" name="file_title" value="{{ old('file_title', $card->file_title) }}" />
            </div>
            <div class="form-group">
                <label>Plot Number</label>
                <input type="text" name="plot_no" value="{{ old('plot_no', $card->plot_no) }}" />
            </div>
            <div class="form-group">
                <label>Block Number</label>
                <input type="text" name="block_no" value="{{ old('block_no', $card->block_no) }}" />
            </div>
            <div class="form-group">
                <label>Layout</label>
                <input type="text" name="layout_name" value="{{ old('layout_name', $card->layout_name) }}" />
            </div>
            <div class="form-group">
                <label>Survey Job Number</label>
                <select name="survey_job_number">
                    <option value="">— None —</option>
                    @foreach ($jobs as $job)
                        <option value="{{ $job->job_number }}" @selected(old('survey_job_number', $card->survey_job_number) === $job->job_number)>
                            {{ $job->job_number }} ({{ $job->status }})
                        </option>
                    @endforeach
                </select>
                <div class="helper-text">This file's live survey jobs.</div>
            </div>
            <div class="form-group">
                <label>Chart ID</label>
                <input type="number" name="cadastral_chart_id" value="{{ old('cadastral_chart_id', $card->cadastral_chart_id) }}" />
                <div class="helper-text">Checked against the card's file number.</div>
            </div>
            <div class="form-group">
                <label>Scanned Card Folder</label>
                <input type="text" name="image_folder" value="{{ old('image_folder', $card->image_folder) }}" />
            </div>
        </div>

        @include('cadastral_module.partials._address_builder', [
            'prefix' => 'prop_',
            'mode'   => 'property',
            'model'  => $card,
            'legend' => 'Plot Location',
            'plotField' => 'plot_no',
        ])

        <div class="helper-text">
            The file status is changed on the File Status screen, which records who decided it and why.
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Card</button>
    </div>
</form>
@endcanDo
