@include('cadastral_module.partials._flash')


<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Cards Commissioned</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Open Files</div>
        <div class="kpi-value">{{ number_format($stats['open']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Revoked</div>
        <div class="kpi-value">{{ number_format($stats['revoked']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Carrying a Job Number</div>
        <div class="kpi-value">{{ number_format($stats['with_job']) }}</div>
    </div>
</div>

{{-- Commission: one live card per file, picked from registered intake files. --}}
@canDo('Cad - Records', 'create')
    @include('cadastral_module.partials._wizard')

    @php
        // Values the receipt supplies, rendered before the picker locks them.
        $cardValues = ($picked['status'] ?? null) === 'ok' ? $picked['values'] : [];
        $cardModel  = (object) array_merge(['prop_state' => 'Kano'], array_filter(
            \App\Services\Cadastral\CadastralRegistryLookup::lockedInput($cardValues, ['prop_house', 'prop_street', 'prop_district', 'prop_lga', 'prop_state']),
            fn ($v) => $v !== null
        ));
    @endphp

    <form method="POST" action="{{ route('cadastral-module.index-cards.store') }}" class="form-container" style="margin-bottom:22px;"
          id="commission-card" data-wizard data-wizard-errors="{{ json_encode($errors->keys()) }}" novalidate>
        @csrf
        <div class="card-header">
            <strong><i data-lucide="id-card" style="width:16px;height:16px;vertical-align:-3px;"></i> Commission Index Card</strong>
            <span class="helper-text" style="margin:0;">
                One live card per file. Owner and location come from the intake record; only what it leaves blank is filled here.
            </span>
        </div>

        <div class="form-stepper" data-wizard-header></div>

        <div class="form-body">
            <section class="form-step" data-step data-title="Select File" data-icon="folder-search"
                     data-subtitle="A file received and registered at intake. A file that already has a card is refused.">
                <div class="form-grid">
                    @include('cadastral_module.partials._file_picker', [
                        'scope'   => 'receipt',
                        'purpose' => 'commission',
                        'hidden'  => ['cadastral_file_receipt_id'],
                        'initial' => $picked,
                    ])
                </div>
            </section>

            <section class="form-step" data-step data-title="Card Details" data-icon="id-card"
                     data-subtitle="Greyed fields come from the intake record. Complete any it leaves blank.">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name / Owner</label>
                        <input type="text" name="file_title" value="{{ old('file_title', $cardValues['file_title'] ?? '') }}" maxlength="500" />
                    </div>
                    <div class="form-group">
                        <label>Plot Number</label>
                        <input type="text" name="plot_no" value="{{ old('plot_no', $cardValues['plot_no'] ?? '') }}" maxlength="50" />
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <input type="text" data-fp-value="type" class="cad-locked" disabled value="{{ $picked['file']['type'] ?? '' }}" />
                    </div>
                    <div class="form-group">
                        <label>Block Number</label>
                        <input type="text" name="block_no" value="{{ old('block_no') }}" maxlength="50" />
                    </div>
                    <div class="form-group">
                        <label>Layout</label>
                        <input type="text" name="layout_name" value="{{ old('layout_name') }}" maxlength="255" />
                    </div>
                    <div class="form-group">
                        <label>Scanned Card Folder</label>
                        <input type="text" name="image_folder" value="{{ old('image_folder') }}" maxlength="255"
                               placeholder="Joins this row to the scanned card" />
                    </div>
                </div>

                @include('cadastral_module.partials._address_builder', [
                    'prefix' => 'prop_',
                    'mode'   => 'property',
                    'model'  => $cardModel,
                    'legend' => 'Property Location',
                    'plotField' => 'plot_no',
                ])
            </section>

            <section class="form-step" data-step data-title="Job & Movement" data-icon="route"
                     data-subtitle="The file's survey job, if it has one, and where the card starts.">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Survey Job No</label>
                        <select name="cadastral_survey_job_id" id="card-job">
                            <option value="">— None yet —</option>
                            @foreach ($picked['records']['survey_jobs'] ?? [] as $j)
                                <option value="{{ $j['id'] }}" @selected((string) old('cadastral_survey_job_id') === (string) $j['id'])>{{ $j['text'] }}</option>
                            @endforeach
                        </select>
                        <div class="helper-text">The file's own live jobs. Issuing one later writes its number onto the card.</div>
                    </div>
                    <div class="form-group">
                        <label>Initial Movement Stage <span class="required">*</span></label>
                        <select name="initial_stage" required>
                            @foreach ($stages as $key => $label)
                                <option value="{{ $key }}" @selected(old('initial_stage', 'commissioned') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Movement Note</label>
                        <input type="text" name="movement_note" value="{{ old('movement_note') }}" maxlength="1000" />
                    </div>
                </div>
            </section>

            <section class="form-step" data-step data-review data-title="Review & Commission" data-icon="clipboard-check"
                     data-subtitle="Check the card, then commission it. The intake record's values are copied again on save.">
                <div data-wizard-summary></div>
            </section>
        </div>

        <div class="form-actions" data-wizard-nav>
            <button type="button" class="btn btn-outline" data-wizard-back><i data-lucide="arrow-left"></i> Back</button>
            <button type="button" class="btn btn-primary" data-wizard-next>Next <i data-lucide="arrow-right"></i></button>
            <button type="submit" class="btn btn-primary" data-wizard-submit><i data-lucide="id-card"></i> Commission</button>
        </div>
    </form>
@endcanDo

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="File no., title, card ref or job no.…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:280px;" />
        <select name="file_status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Statuses</option>
            @foreach (\App\Models\Cadastral\CadastralIndexCard::FILE_STATUSES as $k => $label)
                <option value="{{ $k }}" @selected(request('file_status')===$k)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','file_status']))
            <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.index-cards.index') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Card ID</th>
                    <th>File No</th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Survey Job</th>
                    <th>Last Movement</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cards as $card)
                    @php
                        $last   = $latest[$card->id] ?? null;
                        $office = $offices[trim((string) $card->file_number)] ?? null;
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $card->card_ref }}</strong>
                            @if ($card->file_status !== 'open')
                                <div><span class="status-badge {{ $card->status_badge }}"><span class="dot"></span>{{ $card->file_status_label }}</span></div>
                            @endif
                        </td>
                        <td>{{ $card->file_number }}</td>
                        <td>{{ Str::limit($card->file_title, 36) ?: '—' }}</td>
                        {{-- District, LGA, State — the plot is not part of the location. --}}
                        <td>{{ $card->property_location ?: '—' }}</td>
                        <td>{{ $card->survey_job_number ?: '—' }}</td>
                        <td>
                            @if ($last)
                                <strong>{{ $last['label'] }}</strong>
                                <div class="helper-text" style="margin:0;font-size:11px;">{{ optional($last['at'])->format('d M Y H:i') }}</div>
                            @else
                                —
                            @endif
                            @if ($office)
                                <div class="helper-text" style="margin:0;font-size:11px;" title="Current office in the file tracker">
                                    <i class="fas fa-route"></i> {{ $office }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('cadastral-module.index-cards.show', $card) }}" title="View the card">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @canDo('Cad - Records', 'print')
                                    <a href="{{ route('cadastral-module.index-cards.print', $card) }}" target="_blank" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                @endcanDo
                                @canDo('Cad - Records', 'edit')
                                    <a href="{{ route('cadastral-module.index-cards.show', $card) }}#movement" title="Update movement">
                                        <i class="fas fa-right-left"></i>
                                    </a>
                                @endcanDo
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-id-card" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No index cards yet. Commission one from a registered intake file above.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $cards->firstItem() ?? 0 }}–{{ $cards->lastItem() ?? 0 }}
            of {{ number_format($cards->total()) }} cards · one live card per file · last movement is the card's stage, with the tracker's office beneath
        </span>
        <div class="pagination">{{ $cards->links() }}</div>
    </div>
</div>

{{-- The job list follows the picked file: only that file's live jobs are
     offered (IndexCardController::requireJob refuses any other). --}}
<script>
document.addEventListener('cadastral:file-picked', function (e) {
    var jobs = document.getElementById('card-job');
    if (!jobs || jobs.closest('form') !== e.target || (e.detail && e.detail.initial)) return;
    while (jobs.options.length > 1) jobs.remove(1);
    var list = e.detail && e.detail.status === 'ok' && e.detail.records ? e.detail.records.survey_jobs : [];
    (list || []).forEach(function (j) { jobs.add(new Option(j.text, j.id)); });
});
</script>
