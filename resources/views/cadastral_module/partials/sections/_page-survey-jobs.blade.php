@include('cadastral_module.partials._flash')


{{-- Hidden for now: the user accepts the KN/CAD/{year}/{serial} format and will confirm it later (2026-10-12). --}}
@if (false && $formatUnconfirmed)
    <div class="caveat">
        <i class="fas fa-triangle-exclamation"></i>
        <div>
            <strong>The job-number format is unconfirmed.</strong>
            Numbers are issued as <code>{{ app(\App\Services\Cadastral\CadastralSettings::class)->jobNumberFormat()['format'] }}</code>,
            a KLAES-local placeholder. The concept note asks for SURCON compliance and nobody here
            knows the pattern SURCON mandates. Confirm it with the Surveyor-General's office before
            issuing in bulk — every number issued now would otherwise need reissuing.
        </div>
    </div>
@endif

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Jobs Registered</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Instructions Issued</div>
        <div class="kpi-value">{{ number_format($stats['issued']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">In the Field</div>
        <div class="kpi-value">{{ number_format($stats['in_field']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Accepted</div>
        <div class="kpi-value">{{ number_format($stats['accepted']) }}</div>
    </div>
</div>

{{-- A job is its scope and the survey's location (user, 2026-10-12). The surveyor and the file are
     attached at Assign to Surveyor (I to S), where the I to S is issued. --}}
@canDo('Cad - Records', 'create')
    @php
        $jobModel = (object) ['prop_state' => old('prop_state', 'Kano')];
    @endphp
    <form method="POST" action="{{ route('cadastral-module.survey-jobs.store') }}" class="form-container" style="margin-bottom:22px;">
        @csrf
        <div class="card-header">
            <strong><i data-lucide="hard-hat" style="width:16px;height:16px;vertical-align:-3px;"></i> Register a Survey Job</strong>
            <span class="helper-text" style="margin:0;">
                The job number is allocated now. The surveyor and the file are attached at Assign to Surveyor (I to S).
            </span>
        </div>
        <div class="form-body">
            <div class="form-group">
                <label>Job No.</label>
                <input type="text" value="{{ $nextJobNumber ?? '' }}" readonly tabindex="-1"
                       style="font-family:monospace;font-weight:700;background:#f9fafb;max-width:260px;" />
                <span class="helper-text">Preview of the next number. It is confirmed when you save; if another job is registered first, the next number is used.</span>
            </div>
            {{-- Where the survey takes place (not the file's location). --}}
            @include('cadastral_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => $jobModel,
                'legend' => 'Survey Location',
            ])
            <div class="form-group">
                <label>Job Scope</label>
                <textarea name="job_scope" rows="3" maxlength="8000"
                          placeholder="What the survey is for">{{ old('job_scope') }}</textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i data-lucide="plus"></i> Register &amp; Allocate a Number</button>
        </div>
    </form>
@endcanDo

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Job no., file no., ITS no. or surveyor…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:280px;" />
        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach (\App\Models\Cadastral\CadastralSurveyJob::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','status']))
            <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.survey-jobs.index') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Job Number</th>
                    <th>Field Jacket</th>
                    <th>File Number</th>
                    <th>Plot No.</th>
                    <th>Location</th>
                    <th>Surveyor</th>
                    <th>Firm</th>
                    <th>Instruction</th>
                    <th>Issued</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($jobs as $job)
                    <tr>
                        <td>{{ $jobs->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $job->job_number }}</strong></td>
                        <td>
                            @if ($job->field_jacket_no)
                                {{ $job->field_jacket_no }}
                                @if ($job->cadastral_case_id)
                                    <div class="helper-text" style="margin:0;font-size:11px;"><a href="{{ route('cadastral-dept.cases.show', $job->cadastral_case_id) }}">Cadastral Dept file</a></div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($job->file_number)
                                {{ $job->file_number }}
                            @else
                                <span class="status-badge pending"><span class="dot"></span>Not attached yet</span>
                                <div class="helper-text" style="margin:0;font-size:11px;">Attached at Assign to Surveyor (I to S)</div>
                            @endif
                            @if ($job->indexCard)
                                <div class="helper-text" style="margin:0;font-size:11px;">
                                    <a href="{{ route('cadastral-module.index-cards.show', $job->indexCard) }}">{{ $job->indexCard->card_ref }}</a>
                                    @if ($job->indexCard->survey_job_number === $job->job_number)
                                        · on the card
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>{{ $job->prop_plot ?: '—' }}</td>
                        <td>{{ $job->property_location ?: '—' }}</td>
                        <td>{{ $job->surveyor_name ?: ($job->file_number ? '—' : 'At I to S') }}</td>
                        <td>{{ $job->firm_name ?: '—' }}</td>
                        <td>{{ $job->its_number ?: '—' }}</td>
                        <td>{{ optional($job->its_issued_at)->format('d M Y') ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $job->status_badge }}">
                                <span class="dot"></span>{{ $job->status }}
                            </span>
                        </td>
                        <td>
                            @php
                              $sjItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
                            @endphp
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a href="{{ route('cadastral-module.survey-jobs.edit', $job) }}" class="{{ $sjItem }}">
                                        <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Open
                                    </a>
                                    @if ($job->hasInstruction())
                                        @canDo('Cad - Records', 'print')
                                            <a href="{{ route('cadastral-module.survey-jobs.its.print', $job) }}" target="_blank" class="{{ $sjItem }}">
                                                <i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> Print the instruction
                                            </a>
                                        @endcanDo
                                    @endif
                                    @if ($job->cadastral_case_id)
                                        <a href="{{ route('cadastral-dept.cases.show', $job->cadastral_case_id) }}" class="{{ $sjItem }}">
                                            <i data-lucide="folder-open" class="w-4 h-4 text-gray-500"></i> Open Cadastral Dept file
                                        </a>
                                    @endif
                                    @canDo('Cad - Records', 'edit')
                                        @if ($job->status === 'Issued' || $job->status === 'In Field')
                                            <form method="POST" action="{{ route('cadastral-module.survey-jobs.mark-submitted', $job) }}">
                                                @csrf
                                                <button type="submit" class="{{ $sjItem }}"><i data-lucide="inbox" class="w-4 h-4 text-gray-500"></i> Mark submitted</button>
                                            </form>
                                        @elseif ($job->status === 'Submitted')
                                            <form method="POST" action="{{ route('cadastral-module.survey-jobs.mark-accepted', $job) }}">
                                                @csrf
                                                <button type="submit" class="{{ $sjItem }}"><i data-lucide="check" class="w-4 h-4 text-gray-500"></i> Accept</button>
                                            </form>
                                        @endif
                                    @endcanDo
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-helmet-safety" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No survey jobs yet. Register one using the form above.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $jobs->firstItem() ?? 0 }}–{{ $jobs->lastItem() ?? 0 }} of {{ number_format($jobs->total()) }} jobs</span>
        <div class="pagination">{{ $jobs->links() }}</div>
    </div>
</div>
