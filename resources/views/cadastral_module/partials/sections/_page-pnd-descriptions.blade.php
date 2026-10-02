@include('cadastral_module.partials._flash')

<div class="unit-tag"><i class="fas fa-ruler-combined"></i> 4.4 · Plan and Description · Descriptions</div>

<div class="page-header">
    <div>
        @if ($record)
            <strong>{{ $record->pd_ref }}</strong> · {{ $record->file_number }}
        @endif
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if ($record)
            <a href="{{ route('cadastral-module.plan-description.area', ['record' => $record->id]) }}" class="btn btn-outline btn-sm">
                <i class="fas fa-ruler-combined"></i> Area &amp; Pillars
            </a>
        @endif
        <a href="{{ route('cadastral-module.plan-description.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-list"></i> Register
        </a>
    </div>
</div>

{{-- File: a GET form, so choosing a file just opens it here. --}}
<form method="GET" action="{{ route('cadastral-module.plan-description.descriptions') }}" class="form-container" id="desc-pick">
    <div class="card-header">
        <strong>File</strong>
        <span class="helper-text" style="margin:0;">
            Files with a plan and description record. A file without one starts on
            <a href="{{ route('cadastral-module.plan-description.area') }}">Area &amp; Pillars</a>.
        </span>
    </div>
    <div class="form-body">
        <div class="form-group" style="margin:0;">
            <select name="record" id="desc-file"
                    data-url="{{ route('cadastral-module.plan-description.description-files') }}"
                    data-placeholder="Type at least 2 characters of the file number, reference or owner…">
                <option value=""></option>
                @if ($picked)
                    <option value="{{ $picked['id'] }}" selected>{{ $picked['text'] }}</option>
                @endif
            </select>
        </div>
    </div>
</form>

@if ($record)
    @php
        $edited = old('description_body', $record->description_body);
    @endphp

    @if ($record->validation_notes)
        <div class="caveat" style="margin-top:18px;">
            <i class="fas fa-circle-info"></i>
            <div><strong>Checks against the chart.</strong> {{ $record->validation_notes }}</div>
        </div>
    @endif

    {{--
        One form, two routes. Save is the form's own action and carries the PUT
        on the button itself; the two generate buttons post to the generate
        route through formaction. Save comes first in the markup so Enter in a
        box saves rather than overwrites; flex order puts it third on screen.
    --}}
    <form method="POST" action="{{ route('cadastral-module.plan-description.description.save', $record) }}"
          class="form-container" style="margin-top:18px;" id="desc-form">
        @csrf

        <div class="card-header">
            <strong>Land Description</strong>
            @if ($record->validation_status !== 'unvalidated')
                <span class="status-badge {{ $record->validation_status === 'passed' ? 'active' : 'rejected' }}">
                    <span class="dot"></span>{{ ucfirst($record->validation_status) }}
                </span>
            @endif
        </div>

        <div class="form-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>File No</label>
                    <input type="text" value="{{ $record->file_number }}" readonly class="intake-locked" />
                </div>
                <div class="form-group">
                    <label>Owner</label>
                    <input type="text" value="{{ $record->file_title }}" readonly class="intake-locked" />
                </div>
                <div class="form-group">
                    <label>Template</label>
                    <select name="template_key" id="desc-template">
                        @foreach ($templates as $key => $label)
                            <option value="{{ $key }}" @selected(old('template_key', $template) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Location Text</label>
                    {{-- District, LGA, State from the address builder. The plot has its own sentence. --}}
                    <input type="text" value="{{ $record->property_location }}" readonly class="intake-locked" placeholder="District, LGA, State" />
                </div>
                <div class="form-group">
                    <label>Land Use</label>
                    <input type="text" value="{{ $record->land_use ?: '—' }}" readonly class="intake-locked" />
                </div>
                <div class="form-group">
                    <label>Area</label>
                    <input type="text" readonly class="intake-locked"
                           value="{{ $areas['sqm'] !== null ? number_format($areas['sqm'], 2) . ' m² · ' . number_format($areas['hectares'], 4) . ' ha · ' . number_format($areas['acres'], 2) . ' acres' : 'Not recorded' }}" />
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Pillars</label>
                    <input type="text" readonly class="intake-locked"
                           value="{{ $pillars->isEmpty() ? 'None recorded' : $pillars->count() . ' — ' . $pillars->map(fn ($p) => ($p->pillar_number ?: '#' . ($p->sort_order + 1)) . ' (' . ucfirst($p->ownership) . ')')->implode(', ') }}" />
                    <div class="helper-text">
                        Area, land use and pillars are changed on
                        <a href="{{ route('cadastral-module.plan-description.area', ['record' => $record->id]) }}">Area &amp; Pillars</a>.
                    </div>
                </div>
            </div>

            <div class="form-grid" style="margin-top:6px;">
                <div class="form-group"><label>Boundary Notes — North</label>
                    <input type="text" name="boundary_north" maxlength="255" value="{{ old('boundary_north', $record->boundary_north) }}" placeholder="e.g. Plot 13, or a 12 m road" /></div>
                <div class="form-group"><label>Boundary Notes — South</label>
                    <input type="text" name="boundary_south" maxlength="255" value="{{ old('boundary_south', $record->boundary_south) }}" /></div>
                <div class="form-group"><label>Boundary Notes — East</label>
                    <input type="text" name="boundary_east" maxlength="255" value="{{ old('boundary_east', $record->boundary_east) }}" /></div>
                <div class="form-group"><label>Boundary Notes — West</label>
                    <input type="text" name="boundary_west" maxlength="255" value="{{ old('boundary_west', $record->boundary_west) }}" /></div>
            </div>

            <div class="form-group" id="text">
                <label>Description Text</label>
                <textarea name="description_body" id="desc-body" rows="14"
                          placeholder="Generate from a template, then edit.">{{ $edited }}</textarea>
                <div class="helper-text">
                    Generation is a starting point, not an authority: a description is a legal form of words,
                    so the officer has the last say over it. Generating replaces the text in this box.
                    @if ($record->description_generated_at)
                        Last generated {{ $record->description_generated_at->format('d M Y H:i') }}.
                    @endif
                </div>
            </div>
        </div>

        <div class="form-actions" style="display:flex;flex-wrap:wrap;gap:8px;">
            @canDo('Cad - Records', 'edit')
                <button type="submit" name="_method" value="PUT" class="btn btn-primary" style="order:3;">
                    <i class="fas fa-save"></i> Save Description
                </button>
            @endcanDo
            @canDo('Cad - Records', 'create')
                <button type="submit" name="mode" value="template" class="btn btn-secondary" style="order:1;"
                        formaction="{{ route('cadastral-module.plan-description.description.generate', $record) }}"
                        data-overwrites>
                    <i class="fas fa-wand-magic-sparkles"></i> Generate From Template
                </button>
                <button type="submit" name="mode" value="regenerate" class="btn btn-outline" style="order:2;"
                        formaction="{{ route('cadastral-module.plan-description.description.generate', $record) }}"
                        data-overwrites title="Rebuild the text from the record's own template and its current area, pillars and boundaries">
                    <i class="fas fa-rotate"></i> Regenerate
                </button>
            @endcanDo
            @if (trim((string) $record->description_body) !== '')
                @canDo('Cad - Records', 'print')
                    <a href="{{ route('cadastral-module.plan-description.description.print', $record) }}" target="_blank"
                       class="btn btn-outline" style="order:4;">
                        <i class="fas fa-print"></i> Print
                    </a>
                @endcanDo
            @endif
            {{-- The bill still lives on the combined screen until Phase 7 rebuilds it. --}}
            <a href="{{ route('cadastral-module.plan-description.edit', $record) }}#fee-calculator"
               class="btn btn-outline" style="order:5;margin-left:auto;">
                Next: Fee Calculator <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </form>
@endif

{{-- ===================== Saved descriptions ===================== --}}
<div class="table-wrapper" style="margin-top:22px;">
    <div class="card-header" style="padding:12px 16px;"><strong>Saved Descriptions</strong></div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>File No</th>
                    <th>Template</th>
                    <th>Preview</th>
                    <th>Saved</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($saved as $row)
                    <tr>
                        <td>
                            <strong>{{ $row->file_number }}</strong>
                            <div class="helper-text" style="margin:0;">{{ $row->pd_ref }}{{ $row->file_title ? ' · ' . $row->file_title : '' }}</div>
                        </td>
                        <td>{{ $templates[$row->template_key] ?? ($row->template_key ?: '—') }}</td>
                        <td style="max-width:420px;">
                            <details>
                                <summary style="cursor:pointer;">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', (string) $row->description_body), 110) }}</summary>
                                <div style="white-space:pre-wrap;margin-top:8px;font-size:12.5px;">{{ $row->description_body }}</div>
                            </details>
                        </td>
                        {{-- The record's last change: there is no separate saved-at column. --}}
                        <td>{{ optional($row->updated_at)->format('d M Y H:i') ?: '—' }}</td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('cadastral-module.plan-description.descriptions', ['record' => $row->id]) }}#text" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @canDo('Cad - Records', 'print')
                                    <a href="{{ route('cadastral-module.plan-description.description.print', $row) }}" target="_blank" title="Print">
                                        <i class="fas fa-print"></i>
                                    </a>
                                @endcanDo
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-file-lines" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No descriptions saved yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $saved->firstItem() ?? 0 }}–{{ $saved->lastItem() ?? 0 }} of {{ number_format($saved->total()) }}
            · open the preview to read the full text
        </span>
        <div class="pagination">{{ $saved->links() }}</div>
    </div>
</div>

<style>
    .survey-proto .intake-locked { background: var(--gray-100); color: var(--gray-700); }
</style>

{{--
    File picker and the overwrite guard. Picking a file opens it on this page.
    Generating replaces the text box, so a box edited since the page loaded
    asks first.

    Runs after DOMContentLoaded plus a tick, because Select2 is pushed to the
    page footer after this partial.
--}}
<script>
document.addEventListener('DOMContentLoaded', function () { setTimeout(function () {
    'use strict';

    var $     = window.jQuery;
    var file  = document.getElementById('desc-file');
    var pick  = document.getElementById('desc-pick');

    if (file && $ && $.fn && $.fn.select2) {
        var $file = $(file);
        $file.select2({
            width: '100%',
            placeholder: file.dataset.placeholder,
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
                    $('<div style="font-size:11px;opacity:.7">').text([item.location, item.has_text ? 'Description saved' : 'No description yet'].filter(Boolean).join(' · '))
                );
            }
        });
        $file.on('select2:select', function (e) {
            if (e.params.data && e.params.data.url) window.location.href = e.params.data.url; else pick.submit();
        });
    } else if (file) {
        file.addEventListener('change', function () { pick.submit(); });
    }

    var body = document.getElementById('desc-body');
    if (!body) return;

    var original = body.value;
    document.querySelectorAll('[data-overwrites]').forEach(function (button) {
        button.addEventListener('click', function (e) {
            if (body.value.trim() !== '' && body.value !== original
                && !confirm('Generating replaces the text you have edited. Continue?')) {
                e.preventDefault();
            }
        });
    });
}, 0); });
</script>
