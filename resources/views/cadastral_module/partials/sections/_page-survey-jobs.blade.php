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

@canDo('Cad - Records', 'create')
    @include('cadastral_module.partials._wizard')

    @php
        // Values the file supplies, rendered before the picker locks them.
        $jobValues = ($picked['status'] ?? null) === 'ok' ? $picked['values'] : [];
        $jobModel  = (object) array_merge(['prop_state' => 'Kano'], array_filter(
            \App\Services\Cadastral\CadastralRegistryLookup::lockedInput($jobValues, ['prop_house', 'prop_plot', 'prop_street', 'prop_district', 'prop_lga', 'prop_state']),
            fn ($v) => $v !== null
        ));
    @endphp

    <form method="POST" action="{{ route('cadastral-module.survey-jobs.store') }}" class="form-container" style="margin-bottom:22px;"
          data-wizard data-wizard-errors="{{ json_encode($errors->keys()) }}" novalidate>
        @csrf
        <div class="card-header">
            <strong><i data-lucide="hard-hat" style="width:16px;height:16px;vertical-align:-3px;"></i> Register a Survey Job</strong>
            <span class="helper-text" style="margin:0;">
                The number is allocated now, not at issue — a job with no number cannot be referred to on paper.
            </span>
        </div>

        <div class="form-stepper" data-wizard-header></div>

        <div class="form-body">
            <section class="form-step" data-step data-title="Select File" data-icon="folder-search"
                     data-subtitle="Any indexed file. A job can come before the file reaches Cadastral intake; its number is written onto the file's index card when there is one.">
                <div class="form-grid">
                    @include('cadastral_module.partials._file_picker', [
                        'scope'   => 'indexed',
                        'hidden'  => ['file_indexing_id'],
                        'initial' => $picked ?? null,
                    ])
                </div>
            </section>

            <section class="form-step" data-step data-title="Job Details" data-icon="clipboard-list"
                     data-subtitle="Who is instructed, and to do what.">
                <div class="form-grid">
                    <div class="form-group">
                        <label>File Title</label>
                        <input type="text" name="file_title" value="{{ old('file_title', $jobValues['file_title'] ?? '') }}" maxlength="500" />
                    </div>
                    <div class="form-group">
                        <label>Surveyor</label>
                        <select name="cadastral_surveyor_id">
                            <option value="">— assign later —</option>
                            @foreach ($surveyors as $surveyor)
                                <option value="{{ $surveyor->id }}" @selected(old('cadastral_surveyor_id')==$surveyor->id)>
                                    {{ $surveyor->display_name }}
                                    @unless ($surveyor->canReceiveInstruction()) (licence {{ $surveyor->licence_status }}) @endunless
                                </option>
                            @endforeach
                        </select>
                        @if ($surveyors->isEmpty())
                            <div class="helper-text">
                                The directory is empty.
                                <a href="{{ route('cadastral-module.surveyors.index') }}">Add a surveyor first</a>.
                            </div>
                        @endif
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Job Scope</label>
                        <textarea name="job_scope" rows="3" maxlength="8000"
                                  placeholder="What the surveyor is being instructed to do">{{ old('job_scope') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="form-step" data-step data-title="Job Location" data-icon="map-pin"
                     data-subtitle="Greyed fields come from the file. Complete any it leaves blank.">
                {{-- The job's location is the address builder, not a free-text field. --}}
                @include('cadastral_module.partials._address_builder', [
                    'prefix' => 'prop_',
                    'mode'   => 'property',
                    'model'  => $jobModel,
                    'legend' => 'Job Location',
                ])
            </section>

            <section class="form-step" data-step data-review data-title="Review & Register" data-icon="clipboard-check"
                     data-subtitle="Check the job, then register it. A job number is allocated on save.">
                <div data-wizard-summary></div>
            </section>
        </div>

        <div class="form-actions" data-wizard-nav>
            <button type="button" class="btn btn-outline" data-wizard-back><i data-lucide="arrow-left"></i> Back</button>
            <button type="button" class="btn btn-primary" data-wizard-next>Next <i data-lucide="arrow-right"></i></button>
            <button type="submit" class="btn btn-primary" data-wizard-submit><i data-lucide="plus"></i> Register &amp; Allocate a Number</button>
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
                            {{ $job->file_number }}
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
                        <td>{{ $job->surveyor_name ?: '—' }}</td>
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
