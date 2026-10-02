@include('cadastral_module.partials._flash')

<div class="unit-tag"><i class="fas fa-id-card"></i> 4.3 · Cadastral Information</div>

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
    <form method="POST" action="{{ route('cadastral-module.index-cards.store') }}" class="form-container" style="margin-bottom:22px;"
          id="commission-card" data-picked="{{ $picked ? json_encode($picked) : '' }}">
        @csrf
        <div class="card-header">
            <strong>Commission Index Card</strong>
            <span class="helper-text" style="margin:0;">
                Pick a file registered at intake. Name and location come from the intake record and cannot be typed here.
            </span>
        </div>

        <div class="form-body">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;">
                    <label>File No <span class="required">*</span></label>
                    {{-- Only an intake receipt id can be posted: there is no free-text number. --}}
                    <select name="cadastral_file_receipt_id" id="card-file" required
                            data-url="{{ route('cadastral-module.index-cards.intake-files') }}"
                            data-placeholder="Type at least 2 characters of the file number or receipt ref…">
                        <option value=""></option>
                        @if ($picked)
                            <option value="{{ $picked['id'] }}" selected>{{ $picked['text'] }}</option>
                        @endif
                    </select>
                    <div class="helper-text">Registered intake files only. A file that already has a card is greyed out.</div>
                </div>

                <div class="form-group">
                    <label>Name / Owner</label>
                    <input type="text" id="card-owner" readonly class="intake-locked" placeholder="From the intake record" />
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" id="card-location" readonly class="intake-locked" placeholder="District, LGA, State" />
                </div>
                <div class="form-group">
                    <label>Plot</label>
                    <input type="text" id="card-plot" readonly class="intake-locked" />
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <input type="text" id="card-type" readonly class="intake-locked" />
                </div>

                <div class="form-group">
                    <label>Survey Job No</label>
                    <select name="cadastral_survey_job_id" id="card-job">
                        <option value="">— None yet —</option>
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
                <div class="form-group">
                    <label>Block Number</label>
                    <input type="text" name="block_no" value="{{ old('block_no') }}" />
                </div>
                <div class="form-group">
                    <label>Layout</label>
                    <input type="text" name="layout_name" value="{{ old('layout_name') }}" />
                </div>
                <div class="form-group">
                    <label>Scanned Card Folder</label>
                    <input type="text" name="image_folder" value="{{ old('image_folder') }}"
                           placeholder="Joins this row to the scanned card" />
                </div>
                <div class="form-group">
                    <label>Movement Note</label>
                    <input type="text" name="movement_note" value="{{ old('movement_note') }}" maxlength="1000" />
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <div id="card-notes" style="display:flex;gap:8px;flex-wrap:wrap;font-size:12px;"></div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-id-card"></i> Commission</button>
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

<style>
    .survey-proto .intake-locked { background: var(--gray-100); color: var(--gray-700); }
</style>

{{--
    Commission picker. Searches registered intake receipts; picking one fills
    the read-only Name, Location, Plot and Type boxes and offers the file's own
    survey jobs. The server copies the same values from the receipt on save,
    so the boxes are a preview, not the source.

    Runs after DOMContentLoaded plus a tick: Select2 is pushed to the page
    footer after this partial.
--}}
<script>
document.addEventListener('DOMContentLoaded', function () { setTimeout(function () {
    'use strict';

    var form = document.getElementById('commission-card');
    var file = document.getElementById('card-file');
    if (!form || !file) return;

    var $     = window.jQuery;
    var hasS2 = !!($ && $.fn && $.fn.select2);
    var jobs  = document.getElementById('card-job');
    var notes = document.getElementById('card-notes');
    var oldJob = @json(old('cadastral_survey_job_id'));

    function set(id, value) { var el = document.getElementById(id); if (el) el.value = value || ''; }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function badge(cls, text) {
        return '<span class="status-badge ' + cls + '"><span class="dot"></span>' + esc(text) + '</span>';
    }

    function apply(item) {
        item = item || {};
        set('card-owner', item.owner);
        set('card-location', item.location);
        set('card-plot', item.plot);
        set('card-type', item.type);

        while (jobs.options.length > 1) jobs.remove(1);
        (item.jobs || []).forEach(function (j) {
            var opt = new Option(j.text, j.id);
            if (String(j.id) === String(oldJob)) opt.selected = true;
            jobs.add(opt);
        });

        var bits = [];
        if (item.file_class === 'conversion') bits.push(badge('review', 'Conversion file: charting not required'));
        if (item.source) bits.push('<span style="color:var(--gray-600)">Source: ' + esc(item.source) + '</span>');
        if (item.card) bits.push(badge('rejected', 'Already on card ' + item.card));
        if (notes) notes.innerHTML = bits.join('');
    }

    if (hasS2) {
        var $file = $(file);
        $file.select2({
            width: '100%',
            placeholder: file.dataset.placeholder,
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: file.dataset.url,
                dataType: 'json',
                delay: 300,
                data: function (params) { return { q: params.term || '' }; },
                processResults: function (data) { return data; }
            },
            templateResult: function (item) {
                if (item.loading || !item.file_number) return item.text;
                return $('<div>').append(
                    $('<div>').text(item.text),
                    $('<div style="font-size:11px;opacity:.7">').text([item.type, item.plot ? 'Plot ' + item.plot : '', item.location].filter(Boolean).join(' · '))
                );
            }
        });
        $file.on('select2:select', function (e) { apply(e.params.data); });
        $file.on('select2:clear', function () { apply(null); });
    }

    // Re-rendered after a failed save: refill the preview and the job list.
    if (form.dataset.picked) {
        try { apply(JSON.parse(form.dataset.picked)); } catch (e) { /* leave it blank */ }
    }
}, 0); });
</script>
