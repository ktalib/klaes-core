@include('cadastral_module.partials._flash')


<div class="page-header">
    <div></div>
    <a href="{{ route('cadastral-module.file-status.history') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-clock-rotate-left"></i> Full History
    </a>
</div>

<div class="kpi-grid">
    @foreach (\App\Models\Cadastral\CadastralIndexCard::FILE_STATUSES as $key => $label)
        <div class="kpi-card">
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value">{{ number_format($counts[$key] ?? 0) }}</div>
        </div>
    @endforeach
</div>

{{-- Change a file's status. The card is picked with the shared file picker
     (scope: index card), or loaded from a row's Change button; the form posts
     to that card's update route, which re-reads everything about the file. --}}
@canDo('Cad - Records', 'edit')
    @include('cadastral_module.partials._wizard')

    @php
        $pickedCard = ($picked['status'] ?? null) === 'ok' ? ($picked['records']['card'] ?? null) : null;
        $actionTemplate = route('cadastral-module.file-status.update', ['card' => '__CARD__']);
    @endphp

    <form method="POST" id="status-change" class="form-container" style="margin-bottom:22px;"
          action="{{ $pickedCard ? str_replace('__CARD__', $pickedCard['id'], $actionTemplate) : '' }}"
          data-action-template="{{ $actionTemplate }}"
          enctype="multipart/form-data"
          data-wizard data-wizard-errors="{{ json_encode($errors->keys()) }}" novalidate>
        @csrf @method('PUT')

        <div class="card-header">
            <strong><i data-lucide="toggle-right" style="width:16px;height:16px;vertical-align:-3px;"></i> Change a File's Status</strong>
            <span class="helper-text" style="margin:0;">
                Remarks and an effective date are required, and every change is logged and notified.
            </span>
        </div>

        <div class="form-stepper" data-wizard-header></div>

        <div class="form-body">
            <section class="form-step" data-step data-title="Select File" data-icon="folder-search"
                     data-subtitle="A file's status is held on its index card, so only a file with a card can be changed.">
                <div class="form-grid">
                    @include('cadastral_module.partials._file_picker', [
                        'scope'   => 'card',
                        'hidden'  => ['cadastral_index_card_id'],
                        'initial' => $picked ?? null,
                    ])
                </div>
            </section>

            <section class="form-step" data-step data-title="Status Change" data-icon="file-cog"
                     data-subtitle="The new status, when it takes legal effect, and on whose authority.">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Current Status</label>
                        <input type="text" id="status-current" class="cad-locked" disabled value="{{ $pickedCard['status_label'] ?? '' }}" />
                    </div>
                    <div class="form-group">
                        <label>New Status <span class="required">*</span></label>
                        <select name="to_status" id="status-to" required>
                            <option value="">Choose…</option>
                            @foreach (\App\Models\Cadastral\CadastralIndexCard::FILE_STATUSES as $k => $label)
                                <option value="{{ $k }}" @selected(old('to_status') === $k) @disabled($pickedCard && $pickedCard['file_status'] === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Effective Date <span class="required">*</span></label>
                        <input type="date" name="effective_date" required value="{{ old('effective_date', now()->toDateString()) }}" />
                    </div>
                    <div class="form-group">
                        <label>Authority Reference</label>
                        <input type="text" name="authority_ref" value="{{ old('authority_ref') }}" maxlength="100" placeholder="Letter, minute or order number" />
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Remarks <span class="required">*</span></label>
                        <textarea name="reason" rows="3" required maxlength="4000"
                                  placeholder="Why the status is changing — a change without remarks cannot be defended later">{{ old('reason') }}</textarea>
                    </div>
                    @if ($canUpload)
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Supporting Documents</label>
                            <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" />
                            <div class="helper-text">PDF, JPG or PNG, up to 10 MB each, at most {{ $maxDocs }}. Filed into the file's EDMS folder and shown in Page Typing.</div>
                        </div>
                    @endif
                </div>
            </section>

            <section class="form-step" data-step data-review data-title="Review & Apply" data-icon="clipboard-check"
                     data-subtitle="Check the change, then apply it. It is logged with your name and the officers concerned are notified.">
                <div data-wizard-summary></div>
            </section>
        </div>

        <div class="form-actions" data-wizard-nav>
            <button type="button" class="btn btn-outline" data-wizard-back><i data-lucide="arrow-left"></i> Back</button>
            <button type="button" class="btn btn-primary" data-wizard-next>Next <i data-lucide="arrow-right"></i></button>
            <button type="submit" class="btn btn-primary" data-wizard-submit><i data-lucide="check"></i> Apply the Change</button>
        </div>
    </form>

    {{-- The form posts to the picked card. The card's current status cannot be
         chosen again (the server refuses it too). --}}
    <script>
    document.addEventListener('cadastral:file-picked', function (e) {
        var form = document.getElementById('status-change');
        if (!form || e.target !== form) return;
        var card = e.detail && e.detail.status === 'ok' && e.detail.records ? e.detail.records.card : null;
        form.action = card ? form.dataset.actionTemplate.replace('__CARD__', card.id) : '';
        var current = document.getElementById('status-current');
        if (current) current.value = card ? card.status_label : '';
        var to = document.getElementById('status-to');
        if (to) {
            Array.prototype.forEach.call(to.options, function (o) { o.disabled = !!(card && o.value === card.file_status); });
            if (card && to.value === card.file_status) to.value = '';
        }
    });
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-status-change]');
        if (!b || !window.CadastralFilePicker) return;
        window.CadastralFilePicker.load('#status-change', { card: b.getAttribute('data-status-change') });
        var form = document.getElementById('status-change');
        if (form && form._wizard) form._wizard.show(0);
        if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    </script>
@endcanDo

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="File number, title or card ref…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:280px;" />
        <select name="file_status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Statuses</option>
            @foreach (\App\Models\Cadastral\CadastralIndexCard::FILE_STATUSES as $k => $label)
                <option value="{{ $k }}" @selected(request('file_status')===$k)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','file_status']))
            <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.file-status.index') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>File Number</th>
                    <th>Title</th>
                    <th>Location</th>
                    <th>Current Status</th>
                    <th>Changed</th>
                    <th>Last Reason</th>
                    <th>Change</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cards as $card)
                    <tr>
                        <td><strong>{{ $card->file_number }}</strong></td>
                        <td>{{ Str::limit($card->file_title, 30) ?: '—' }}</td>
                        {{-- District, LGA, State. --}}
                        <td>{{ $card->property_location ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $card->status_badge }}">
                                <span class="dot"></span>{{ $card->file_status_label }}
                            </span>
                        </td>
                        <td>{{ optional($card->file_status_changed_at)->format('d M Y') ?: '—' }}</td>
                        <td>{{ Str::limit($card->file_status_reason, 40) ?: '—' }}</td>
                        <td>
                            @canDo('Cad - Records', 'edit')
                                <button type="button" class="btn btn-outline btn-xs" data-status-change="{{ $card->id }}">
                                    <i class="fas fa-pen-to-square"></i> Change
                                </button>
                            @endcanDo
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-id-card" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No index cards yet — a file's status is held on its card.
                            <a href="{{ route('cadastral-module.index-cards.index') }}">Commission one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $cards->firstItem() ?? 0 }}–{{ $cards->lastItem() ?? 0 }}
            of {{ number_format($cards->total()) }} · remarks and an effective date are required, and every change is logged
            @if ($canUpload)
                · supporting documents (PDF, JPG, PNG, 10 MB each) are filed into the file's EDMS folder and appear in Page Typing
            @else
                · supporting-document upload is pending installation
            @endif
        </span>
        <div class="pagination">{{ $cards->links() }}</div>
    </div>
</div>

@if ($recent->isNotEmpty())
    <div class="table-wrapper" style="margin-top:18px;">
        <div class="table-toolbar">
            <div class="left"><strong>Most recent changes</strong></div>
            <a class="btn btn-outline btn-xs" href="{{ route('cadastral-module.file-status.history') }}">All of them</a>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>File Number</th><th>From</th><th>To</th><th>Effective</th><th>By</th><th>Remarks</th><th>Documents</th></tr>
                </thead>
                <tbody>
                    @foreach ($recent as $event)
                        <tr>
                            <td>{{ $event->file_number }}</td>
                            <td>{{ $event->from_status ? ucfirst(str_replace('_', ' ', $event->from_status)) : '—' }}</td>
                            <td><strong>{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}</strong></td>
                            <td>{{ optional($event->effective_date)->format('d M Y') ?: '—' }}</td>
                            <td>{{ $event->actor_name ?: '—' }}</td>
                            <td>{{ Str::limit($event->reason, 60) }}</td>
                            <td>@include('cadastral_module.partials._documents', ['docs' => $documents[$event->id] ?? collect()])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
