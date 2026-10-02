@include('cadastral_module.partials._flash')

<div class="unit-tag"><i class="fas fa-helmet-safety"></i> 4.3 · Cadastral Information</div>

<div class="page-header">
    <div>
        <strong>{{ $job->job_number }}</strong> · {{ $job->file_number }}
        <span class="status-badge {{ $job->status_badge }}"><span class="dot"></span>{{ $job->status }}</span>
    </div>
    <div style="display:flex;gap:8px;">
        @if ($job->hasInstruction())
            @canDo('Cad - Records', 'print')
                <a href="{{ route('cadastral-module.survey-jobs.its.print', $job) }}" target="_blank" class="btn btn-outline btn-sm">
                    <i class="fas fa-print"></i> Print the Instruction
                </a>
            @endcanDo
        @endif
        <a href="{{ route('cadastral-module.survey-jobs.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back to the register
        </a>
    </div>
</div>

@if ($formatUnconfirmed)
    <div class="caveat">
        <i class="fas fa-triangle-exclamation"></i>
        <div>
            <strong>{{ $job->job_number }} uses the unconfirmed placeholder format.</strong>
            Confirm the SURCON pattern with the Surveyor-General's office before this goes out.
        </div>
    </div>
@endif

<form method="POST" action="{{ route('cadastral-module.survey-jobs.update', $job) }}" class="form-container">
    @csrf @method('PUT')

    <div class="card-header"><strong>Job</strong></div>

    <div class="form-body">
        <div class="form-grid">
            <div class="form-group">
                <label>File Number <span class="required">*</span></label>
                <input type="text" name="file_number" value="{{ old('file_number', $job->file_number) }}" required />
            </div>
            <div class="form-group">
                <label>File Title</label>
                <input type="text" name="file_title" value="{{ old('file_title', $job->file_title) }}" />
            </div>
            <div class="form-group">
                <label>Surveyor</label>
                <select name="cadastral_surveyor_id">
                    <option value="">— unassigned —</option>
                    @foreach ($surveyors as $surveyor)
                        <option value="{{ $surveyor->id }}" @selected(old('cadastral_surveyor_id', $job->cadastral_surveyor_id)==$surveyor->id)>
                            {{ $surveyor->display_name }}
                            @unless ($surveyor->canReceiveInstruction()) (licence {{ $surveyor->licence_status }}) @endunless
                        </option>
                    @endforeach
                </select>
                <div class="helper-text">The name and firm are copied onto the job when you save, so the instruction keeps reading as issued.</div>
            </div>
            <div class="form-group">
                <label>Index Card ID</label>
                <input type="number" name="cadastral_index_card_id" value="{{ old('cadastral_index_card_id', $job->cadastral_index_card_id) }}" />
                <div class="helper-text">
                    @if ($job->indexCard)
                        Linked to <a href="{{ route('cadastral-module.index-cards.show', $job->indexCard) }}">{{ $job->indexCard->card_ref }}</a>{{ $job->indexCard->survey_job_number === $job->job_number ? ', which carries this job number.' : ', which carries ' . ($job->indexCard->survey_job_number ?: 'no job number') . '.' }}
                    @else
                        Left blank, the file's index card is found by file number and the job number is written onto it.
                    @endif
                </div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    @foreach (\App\Models\Cadastral\CadastralSurveyJob::STATUSES as $s)
                        <option value="{{ $s }}" @selected(old('status', $job->status)===$s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label>Job Scope</label>
                <input type="text" name="job_scope" value="{{ old('job_scope', $job->job_scope) }}" />
            </div>
        </div>

        @include('cadastral_module.partials._address_builder', [
            'prefix' => 'prop_',
            'mode'   => 'property',
            'model'  => $job,
            'legend' => 'Job Location',
        ])
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Job</button>
    </div>
</form>

{{-- The Instruction to Surveyor --}}
<div class="form-container" style="margin-top:22px;">
    <div class="card-header">
        <strong>Instruction to Surveyor</strong>
        @if ($job->hasInstruction())
            <span class="helper-text" style="margin:0;">
                {{ $job->its_number }} issued {{ optional($job->its_issued_at)->format('d M Y') }}
                by {{ $job->its_issued_by ?: '—' }} to {{ $job->its_recipient ?: '—' }}.
            </span>
        @endif
    </div>

    @if ($job->hasInstruction())
        <div class="form-body">
            <div class="list-item"><span>Instruction No.</span><strong>{{ $job->its_number }}</strong></div>
            <div class="list-item"><span>Recipient</span><strong>{{ $job->its_recipient ?: '—' }}</strong></div>
            <div class="list-item"><span>Issued By</span><strong>{{ $job->its_issued_by ?: '—' }}</strong></div>
            <div class="list-item">
                <span>Post</span>
                <strong>{{ config('cadastral_module.posts')[$job->its_officer_post] ?? '—' }}</strong>
            </div>
            <div style="padding:12px 0;white-space:pre-wrap;">{{ $job->its_instructions }}</div>
            <div class="helper-text">
                An issued instruction is not edited. If it is wrong, cancel the job and register a new one.
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('cadastral-module.survey-jobs.its.generate', $job) }}">
            @csrf
            <div class="form-body">
                @unless ($job->surveyor)
                    <div class="caveat">
                        <i class="fas fa-circle-info"></i>
                        <div>Assign a surveyor above and save before issuing the instruction.</div>
                    </div>
                @endunless

                <div class="form-grid">
                    <div class="form-group">
                        <label>Recipient</label>
                        <input type="text" name="its_recipient"
                               value="{{ old('its_recipient', $job->surveyor?->display_name) }}"
                               placeholder="Defaults to the assigned surveyor" />
                    </div>
                    <div class="form-group">
                        <label>Issuing Officer's Post</label>
                        <select name="its_officer_post">
                            <option value="">—</option>
                            @foreach (config('cadastral_module.posts') as $code => $label)
                                <option value="{{ $code }}" @selected(old('its_officer_post')===$code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Instruction <span class="required">*</span></label>
                        <textarea name="its_instructions" rows="8" required
                                  placeholder="What the surveyor is instructed to do, the extent of the survey, and what is to be returned.">{{ old('its_instructions') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" @disabled(! $job->surveyor)>
                    <i class="fas fa-file-signature"></i> Issue the Instruction
                </button>
            </div>
        </form>
    @endif
</div>
