{{--
  Configurable Entries -> Cadastral.

  Everything the Cadastral module is configured by (rebuild plan D4): the official
  fee sheet's unit rates, the S.L.N. No. 3 of 1983 area schedule and how it is read,
  the transport bands, survey job / ITS number formats, file-number prefixes and
  source registries, and which KLAES user holds each job post.

  Values are read through App\Services\Cadastral\CadastralSettings, so a key nobody
  has saved shows the shipped default from config/cadastral_module.php and says so.
  Nothing on this tab deletes: schedule rows, bands and officers are switched off.
  Every form posts through the page's AJAX handler and is audited.
--}}
@php
    $money = fn ($v) => $v === null ? '—' : '₦' . number_format((float) $v, 2);
    $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $userName = fn ($id) => $id ? ($cadUserNames[$id] ?? 'User #' . $id) : null;
    $columnLabel = $cadScheduleColumns[$cadScheduleColumn] ?? $cadScheduleColumn;
    $jobPlaceholder = (bool) ($cadFields['numbering']['job_number.is_placeholder']['value'] ?? true);
    $betweenConfirmed = (bool) ($cadFields['area']['fee_sheet.between_rows_confirmed']['value'] ?? false);
    $bandsConfirmed = (bool) ($cadFields['transport']['fee_sheet.transport_bands_confirmed']['value'] ?? false);
    $activeOfficers = $cadOfficers->filter(fn ($o) => (bool) $o->is_active && $o->post_code);
    $holders = $activeOfficers->groupBy('post_code');
    $unfilled = collect($cadPosts)->keys()->reject(fn ($code) => $holders->has($code));
@endphp

<div class="iw-alert info">
    <i data-lucide="info" class="h-4 w-4 mt-0.5"></i>
    <div>
        Values saved here are what the Cadastral module is configured by; a value nobody has saved uses the shipped default and is marked so.
        Bills keep a copy of the rates they were issued at, so a change here affects new bills only.
        The module's screens move over to these values phase by phase &mdash; the fee-sheet bill arrives with the Fee Calculator (Phase 7).
    </div>
</div>

{{-- ============ Fee sheet rates ============ --}}
<form method="POST" action="{{ route('configurable-entries.cadastral.settings', 'fees') }}" class="iw-card">
    @csrf
    <div class="iw-card-head">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile green"><i data-lucide="receipt" class="h-5 w-5"></i></span> Fee Sheet Rates</div>
            <div class="iw-card-sub">Unit rates from <em>Right of Occupancy &ndash; Cadastral Fees and Area</em>. The area fee comes from the schedule below; transport from the bands.</div>
        </div>
    </div>
    <div class="iw-card-body">
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr))">
            @foreach($cadFields['fees'] ?? [] as $field)
                @include('system_admin.configurable_entries.partials.cadastral-field', ['field' => $field])
            @endforeach
        </div>
    </div>
    <div class="iw-card-foot" style="border-radius:0 0 16px 16px">
        <span class="ce-muted">Beacons are charged per pillar recorded on the plan; days and print counts are entered by the officer on the bill.</span>
        <button class="iw-btn iw-btn-success iw-btn-sm"><i data-lucide="save" class="h-4 w-4"></i> Save rates</button>
    </div>
</form>

{{-- ============ Area fee schedule ============ --}}
<div class="iw-card">
    <div class="iw-card-head" style="align-items:center;flex-wrap:wrap">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile green"><i data-lucide="table-2" class="h-5 w-5"></i></span> Area Fee Schedule</div>
            <div class="iw-card-sub">
                Kano S.L.N. No. 3 of 1983. Bills use the <strong>{{ $columnLabel }}</strong> column.
                @if($cadScheduleSource === 'database')
                    {{ count($cadSchedule) }} rows saved here.
                @else
                    Showing the shipped schedule &mdash; copy it in to edit it.
                @endif
            </div>
        </div>
        <span class="ce-pill {{ $cadScheduleSource === 'database' ? 'green' : 'yellow' }}">{{ $cadScheduleSource === 'database' ? 'Editable' : 'Shipped default' }}</span>
    </div>

    {{-- How the schedule is read --}}
    <form method="POST" action="{{ route('configurable-entries.cadastral.settings', 'area') }}" style="padding:16px 22px;border-bottom:1px solid #f3f4f6">
        @csrf
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(230px, 1fr))">
            @foreach($cadFields['area'] ?? [] as $field)
                @include('system_admin.configurable_entries.partials.cadastral-field', ['field' => $field])
            @endforeach
        </div>
        @if(!$betweenConfirmed || $cadAboveMax === 'refuse')
            <div class="iw-alert warn" style="margin-top:12px">
                <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5"></i>
                <div>
                    @if(!$betweenConfirmed)
                        <strong>Unconfirmed:</strong> how an area between two rows is charged (0.45 Ha: next row up gives ₦4,200, interpolating gives ₦3,800) has not been confirmed by the department.
                    @endif
                    @if($cadAboveMax === 'refuse')
                        Areas above the largest row are refused, because the sheet's &ldquo;per add hctr&rdquo; notes are garbled and cannot be read reliably.
                    @endif
                </div>
            </div>
        @endif
        <div class="flex items-center justify-between gap-3 flex-wrap" style="margin-top:12px">
            <div class="flex flex-wrap gap-2 items-center">
                <span class="ce-muted">With the saved rules:</span>
                @foreach($cadExamples as $example)
                    <span class="ce-pill {{ $example['fee'] === null ? 'red' : 'blue' }}">{{ $num($example['ha']) }} Ha &rarr; {{ $example['fee'] === null ? 'refused' : $money($example['fee']) }}</span>
                @endforeach
            </div>
            <button class="iw-btn iw-btn-success iw-btn-sm"><i data-lucide="save" class="h-4 w-4"></i> Save rules</button>
        </div>
    </form>

    @if($cadScheduleSource !== 'database')
        <form method="POST" action="{{ route('configurable-entries.cadastral.defaults', 'area-schedule') }}" class="flex items-center justify-between gap-3 flex-wrap" style="padding:12px 22px;border-bottom:1px solid #f3f4f6;background:#fffbeb">
            @csrf
            <span class="text-sm text-gray-700">The schedule table is empty, so bills read the shipped schedule below. Copy it in to make the rows editable.</span>
            <button class="iw-btn iw-btn-primary iw-btn-sm"><i data-lucide="copy" class="h-4 w-4"></i> Copy official schedule in</button>
        </form>
    @endif

    <div class="overflow-x-auto">
        <table class="ce-table">
            <thead>
                <tr>
                    <th style="width:120px">Hectares</th>
                    <th style="width:150px;{{ $cadScheduleColumn === 'current' ? 'color:#15803d' : '' }}">Current fee ₦{{ $cadScheduleColumn === 'current' ? ' · in use' : '' }}</th>
                    <th style="width:150px;{{ $cadScheduleColumn === 'proposed' ? 'color:#15803d' : '' }}">Proposed fee ₦{{ $cadScheduleColumn === 'proposed' ? ' · in use' : '' }}</th>
                    <th>Additional (current)</th>
                    <th>Additional (proposed)</th>
                    <th style="width:80px">Order</th>
                    <th style="width:70px">Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($cadSchedule as $row)
                    @if($cadScheduleSource === 'database')
                        @php $formId = 'cad-sched-' . $row['id']; @endphp
                        <tr style="{{ $row['is_active'] ? '' : 'opacity:.55' }}">
                            <td>
                                <form id="{{ $formId }}" method="POST" action="{{ route('configurable-entries.cadastral.schedule.update', $row['id']) }}">@csrf</form>
                                <input form="{{ $formId }}" type="number" step="0.01" min="0.01" name="hectares" value="{{ number_format($row['hectares'], 2, '.', '') }}" required class="iw-input" style="padding:6px 8px">
                            </td>
                            <td><input form="{{ $formId }}" type="number" step="0.01" min="0" name="current_fee" value="{{ $row['current_fee'] !== null ? number_format($row['current_fee'], 2, '.', '') : '' }}" class="iw-input" style="padding:6px 8px;{{ $cadScheduleColumn === 'current' ? 'border-color:#16a34a' : '' }}"></td>
                            <td><input form="{{ $formId }}" type="number" step="0.01" min="0" name="proposed_fee" value="{{ $row['proposed_fee'] !== null ? number_format($row['proposed_fee'], 2, '.', '') : '' }}" class="iw-input" style="padding:6px 8px;{{ $cadScheduleColumn === 'proposed' ? 'border-color:#16a34a' : '' }}"></td>
                            <td><input form="{{ $formId }}" type="text" maxlength="255" name="additional_note" value="{{ $row['additional_note'] }}" class="iw-input" style="padding:6px 8px"></td>
                            <td><input form="{{ $formId }}" type="text" maxlength="255" name="proposed_additional_note" value="{{ $row['proposed_additional_note'] }}" class="iw-input" style="padding:6px 8px"></td>
                            <td><input form="{{ $formId }}" type="number" min="0" name="sort_order" value="{{ $row['sort_order'] }}" class="iw-input" style="padding:6px 8px"></td>
                            <td>
                                <input form="{{ $formId }}" type="hidden" name="is_active" value="0">
                                <label class="ce-switch"><input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($row['is_active'])><span></span></label>
                            </td>
                            <td class="text-right"><button form="{{ $formId }}" class="iw-btn iw-btn-success iw-btn-sm">Save</button></td>
                        </tr>
                    @else
                        <tr>
                            <td class="font-medium text-gray-900">{{ number_format($row['hectares'], 2) }}</td>
                            <td class="{{ $cadScheduleColumn === 'current' ? 'font-semibold text-gray-900' : 'ce-muted' }}">{{ $money($row['current_fee']) }}</td>
                            <td class="{{ $cadScheduleColumn === 'proposed' ? 'font-semibold text-gray-900' : 'ce-muted' }}">{{ $money($row['proposed_fee']) }}</td>
                            <td class="ce-muted">{{ $row['additional_note'] ?: '—' }}</td>
                            <td class="ce-muted">{{ $row['proposed_additional_note'] ?: '—' }}</td>
                            <td class="ce-muted">{{ $row['sort_order'] }}</td>
                            <td><span class="ce-pill green">Yes</span></td>
                            <td></td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="8" style="padding:32px"><div class="iw-empty">No schedule rows. Every area fee is refused until one is added.</div></td></tr>
                @endforelse
            </tbody>
            @if($cadScheduleSource === 'database')
                <tfoot>
                    <tr style="background:#f0fdf4">
                        <td>
                            <form id="cad-sched-new" method="POST" action="{{ route('configurable-entries.cadastral.schedule.store') }}">@csrf</form>
                            <input form="cad-sched-new" type="number" step="0.01" min="0.01" name="hectares" required placeholder="Ha" class="iw-input" style="padding:6px 8px">
                        </td>
                        <td><input form="cad-sched-new" type="number" step="0.01" min="0" name="current_fee" placeholder="Current" class="iw-input" style="padding:6px 8px"></td>
                        <td><input form="cad-sched-new" type="number" step="0.01" min="0" name="proposed_fee" placeholder="Proposed" class="iw-input" style="padding:6px 8px"></td>
                        <td><input form="cad-sched-new" type="text" maxlength="255" name="additional_note" class="iw-input" style="padding:6px 8px"></td>
                        <td><input form="cad-sched-new" type="text" maxlength="255" name="proposed_additional_note" class="iw-input" style="padding:6px 8px"></td>
                        <td><input form="cad-sched-new" type="number" min="0" name="sort_order" class="iw-input" style="padding:6px 8px"></td>
                        <td></td>
                        <td class="text-right"><button form="cad-sched-new" class="iw-btn iw-btn-primary iw-btn-sm"><i data-lucide="plus" class="h-3.5 w-3.5"></i> Add row</button></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- ============ Transport bands ============ --}}
<div class="iw-card">
    <div class="iw-card-head" style="align-items:center;flex-wrap:wrap">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile blue"><i data-lucide="truck" class="h-5 w-5"></i></span> Transport Bands</div>
            <div class="iw-card-sub">Distance from Kano in whole kilometres; leave &ldquo;to&rdquo; empty for &ldquo;and over&rdquo;. The officer picks the band on the bill.</div>
        </div>
        <span class="ce-pill {{ $cadBandsSource === 'database' ? 'green' : 'yellow' }}">{{ $cadBandsSource === 'database' ? 'Editable' : 'Shipped default' }}</span>
    </div>

    <form method="POST" action="{{ route('configurable-entries.cadastral.settings', 'transport') }}" class="flex items-end justify-between gap-3 flex-wrap" style="padding:14px 22px;border-bottom:1px solid #f3f4f6">
        @csrf
        <div style="flex:1;min-width:260px">
            @foreach($cadFields['transport'] ?? [] as $field)
                @include('system_admin.configurable_entries.partials.cadastral-field', ['field' => $field])
            @endforeach
            @unless($bandsConfirmed)
                <div class="ce-muted" style="color:#92400e">The sheet reads &ldquo;1.1 km / 11-5 km / over 50 km&rdquo;; the bands below are a reading of it (1&ndash;10, 11&ndash;50, over 50) and are not yet confirmed.</div>
            @endunless
        </div>
        <button class="iw-btn iw-btn-success iw-btn-sm"><i data-lucide="save" class="h-4 w-4"></i> Save</button>
    </form>

    @if($cadBandsSource !== 'database')
        <form method="POST" action="{{ route('configurable-entries.cadastral.defaults', 'transport-bands') }}" class="flex items-center justify-between gap-3 flex-wrap" style="padding:12px 22px;border-bottom:1px solid #f3f4f6;background:#fffbeb">
            @csrf
            <span class="text-sm text-gray-700">The bands table is empty, so bills read the shipped bands below. Copy them in to make them editable.</span>
            <button class="iw-btn iw-btn-primary iw-btn-sm"><i data-lucide="copy" class="h-4 w-4"></i> Copy official bands in</button>
        </form>
    @endif

    <div class="overflow-x-auto">
        <table class="ce-table">
            <thead><tr><th>Label</th><th style="width:120px">From km</th><th style="width:120px">To km</th><th style="width:150px">Fee ₦</th><th style="width:80px">Order</th><th style="width:70px">Active</th><th></th></tr></thead>
            <tbody>
                @forelse($cadBands as $band)
                    @if($cadBandsSource === 'database')
                        @php $formId = 'cad-band-' . $band['id']; @endphp
                        <tr style="{{ $band['is_active'] ? '' : 'opacity:.55' }}">
                            <td>
                                <form id="{{ $formId }}" method="POST" action="{{ route('configurable-entries.cadastral.bands.update', $band['id']) }}">@csrf</form>
                                <input form="{{ $formId }}" type="text" maxlength="150" name="label" value="{{ $band['label'] }}" required class="iw-input" style="padding:6px 8px">
                            </td>
                            <td><input form="{{ $formId }}" type="number" step="0.01" min="0" name="min_km" value="{{ $num($band['min_km']) }}" required class="iw-input" style="padding:6px 8px"></td>
                            <td><input form="{{ $formId }}" type="number" step="0.01" min="0" name="max_km" value="{{ $num($band['max_km']) }}" placeholder="and over" class="iw-input" style="padding:6px 8px"></td>
                            <td><input form="{{ $formId }}" type="number" step="0.01" min="0" name="fee" value="{{ number_format($band['fee'], 2, '.', '') }}" required class="iw-input" style="padding:6px 8px"></td>
                            <td><input form="{{ $formId }}" type="number" min="0" name="sort_order" value="{{ $band['sort_order'] }}" class="iw-input" style="padding:6px 8px"></td>
                            <td>
                                <input form="{{ $formId }}" type="hidden" name="is_active" value="0">
                                <label class="ce-switch"><input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($band['is_active'])><span></span></label>
                            </td>
                            <td class="text-right"><button form="{{ $formId }}" class="iw-btn iw-btn-success iw-btn-sm">Save</button></td>
                        </tr>
                    @else
                        <tr>
                            <td class="font-medium text-gray-900">{{ $band['label'] }}</td>
                            <td>{{ $num($band['min_km']) }}</td>
                            <td>{{ $band['max_km'] === null ? 'and over' : $num($band['max_km']) }}</td>
                            <td class="font-semibold">{{ $money($band['fee']) }}</td>
                            <td class="ce-muted">{{ $band['sort_order'] }}</td>
                            <td><span class="ce-pill green">Yes</span></td>
                            <td></td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" style="padding:32px"><div class="iw-empty">No transport bands.</div></td></tr>
                @endforelse
            </tbody>
            @if($cadBandsSource === 'database')
                <tfoot>
                    <tr style="background:#eff6ff">
                        <td>
                            <form id="cad-band-new" method="POST" action="{{ route('configurable-entries.cadastral.bands.store') }}">@csrf</form>
                            <input form="cad-band-new" type="text" maxlength="150" name="label" required placeholder="e.g. Over 100 km from Kano" class="iw-input" style="padding:6px 8px">
                        </td>
                        <td><input form="cad-band-new" type="number" step="0.01" min="0" name="min_km" required class="iw-input" style="padding:6px 8px"></td>
                        <td><input form="cad-band-new" type="number" step="0.01" min="0" name="max_km" placeholder="and over" class="iw-input" style="padding:6px 8px"></td>
                        <td><input form="cad-band-new" type="number" step="0.01" min="0" name="fee" required class="iw-input" style="padding:6px 8px"></td>
                        <td><input form="cad-band-new" type="number" min="0" name="sort_order" class="iw-input" style="padding:6px 8px"></td>
                        <td></td>
                        <td class="text-right"><button form="cad-band-new" class="iw-btn iw-btn-primary iw-btn-sm"><i data-lucide="plus" class="h-3.5 w-3.5"></i> Add band</button></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- ============ Numbering ============ --}}
<form method="POST" action="{{ route('configurable-entries.cadastral.settings', 'numbering') }}" class="iw-card">
    @csrf
    <div class="iw-card-head">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile orange"><i data-lucide="hash" class="h-5 w-5"></i></span> Numbering</div>
            <div class="iw-card-sub">Tokens: <span class="iw-mono">{year}</span> and <span class="iw-mono">{serial}</span> (both required; the serial restarts each year and is zero-padded to the digits given).</div>
        </div>
    </div>
    @if($jobPlaceholder)
        <div class="iw-alert danger" style="margin:16px 22px 0">
            <i data-lucide="alert-octagon" class="h-4 w-4 mt-0.5"></i>
            <div><strong>The survey job number format is a placeholder, not the SURCON format.</strong> Nobody has confirmed the pattern SURCON mandates. Numbers issued in this format may have to be reissued. Confirm it with the Surveyor-General's office, enter it here, then switch the placeholder flag off.</div>
        </div>
    @endif
    <div class="iw-card-body">
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr))">
            @foreach($cadFields['numbering'] ?? [] as $field)
                @include('system_admin.configurable_entries.partials.cadastral-field', ['field' => $field])
            @endforeach
        </div>
    </div>
    <div class="iw-card-foot" style="border-radius:0 0 16px 16px">
        <span class="ce-muted">Numbers already issued keep their format.</span>
        <button class="iw-btn iw-btn-success iw-btn-sm"><i data-lucide="save" class="h-4 w-4"></i> Save numbering</button>
    </div>
</form>

{{-- ============ File-number prefixes & source registries ============ --}}
<form method="POST" action="{{ route('configurable-entries.cadastral.settings', 'file_numbers') }}" class="iw-card">
    @csrf
    <div class="iw-card-head">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile gray"><i data-lucide="folder-tree" class="h-5 w-5"></i></span> File-number Prefixes &amp; Source Registries</div>
            <div class="iw-card-sub">Comma-separated. A <strong>conversion</strong> file is not charted and goes straight to index-card commissioning; a <strong>direct</strong> one is charted. Source registries are the departments intake receives files from.</div>
        </div>
    </div>
    <div class="iw-card-body">
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr))">
            @foreach($cadFields['file_numbers'] ?? [] as $field)
                @include('system_admin.configurable_entries.partials.cadastral-field', ['field' => $field])
            @endforeach
        </div>
    </div>
    <div class="iw-card-foot" style="border-radius:0 0 16px 16px">
        <span class="ce-muted">Prefixes are letters and digits; a prefix cannot be both direct and conversion.</span>
        <button class="iw-btn iw-btn-success iw-btn-sm"><i data-lucide="save" class="h-4 w-4"></i> Save prefixes</button>
    </div>
</form>

{{-- ============ Officers & posts ============ --}}
<div class="iw-card">
    <div class="iw-card-head" style="align-items:center;flex-wrap:wrap">
        <div>
            <div class="iw-card-title"><span class="iw-icon-tile blue"><i data-lucide="users" class="h-5 w-5"></i></span> Officers &amp; Posts</div>
            <div class="iw-card-sub">Who holds each job post. A report step that names a post can only be completed by a user holding it. One line per person per post; switch a line off to end it &mdash; lines are never deleted.</div>
        </div>
        <span class="ce-pill {{ $unfilled->isEmpty() ? 'green' : 'red' }}">{{ count($cadPosts) - $unfilled->count() }} of {{ count($cadPosts) }} posts filled</span>
    </div>

    <div class="overflow-x-auto">
        <table class="ce-table">
            <thead><tr><th>Post</th><th>Held by</th><th>Needed by</th></tr></thead>
            <tbody>
                @foreach($cadPosts as $code => $label)
                    <tr>
                        <td>
                            <div class="font-medium text-gray-900">{{ $label }}</div>
                            <div class="ce-muted iw-mono">{{ $code }}</div>
                        </td>
                        <td>
                            @forelse($holders->get($code, collect()) as $holder)
                                <span class="ce-pill {{ $holder->user_id ? 'green' : 'yellow' }}" title="{{ $holder->user_id ? 'Linked to a KLAES user' : 'Not linked to a KLAES user, so nobody can act as this post' }}">
                                    {{ $userName($holder->user_id) ?: $holder->name }}{{ $holder->user_id ? '' : ' · no user' }}
                                </span>
                            @empty
                                <span class="ce-pill red">Unfilled</span>
                            @endforelse
                        </td>
                        <td class="ce-muted" style="white-space:normal">{{ implode(' · ', $cadNeededBy[$code] ?? []) ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="iw-card-head" style="border-top:1px solid #f3f4f6">
        <div>
            <div class="iw-card-title" style="font-size:15px">Directory</div>
            <div class="iw-card-sub">The cadastral_officers lines. Name and rank are what the Land 12 officer dropdown shows and are not changed here.</div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="ce-table">
            <thead><tr><th>Officer (Land 12)</th><th style="min-width:240px">KLAES user</th><th style="min-width:240px">Post</th><th style="width:70px">Active</th><th></th></tr></thead>
            <tbody>
                @forelse($cadOfficers as $officer)
                    @php $formId = 'cad-officer-' . $officer->id; @endphp
                    <tr style="{{ $officer->is_active ? '' : 'opacity:.55' }}">
                        <td>
                            <form id="{{ $formId }}" method="POST" action="{{ route('configurable-entries.cadastral.officers.update', $officer->id) }}">@csrf</form>
                            <div class="font-medium text-gray-900">{{ $officer->name }}</div>
                            <div class="ce-muted">{{ $officer->rank }}</div>
                        </td>
                        <td>
                            <select form="{{ $formId }}" name="user_id" class="ce-select">
                                <option value="">— no user —</option>
                                @foreach($cadUsers as $user)
                                    <option value="{{ $user->id }}" @selected((int) $officer->user_id === (int) $user->id)>{{ $user->display }}{{ $user->email ? ' · ' . $user->email : '' }}{{ $user->is_active ? '' : ' (inactive)' }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select form="{{ $formId }}" name="post_code" class="ce-select">
                                <option value="">— no post —</option>
                                @foreach($cadPosts as $code => $label)
                                    <option value="{{ $code }}" @selected($officer->post_code === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input form="{{ $formId }}" type="hidden" name="is_active" value="0">
                            <label class="ce-switch"><input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($officer->is_active)><span></span></label>
                        </td>
                        <td class="text-right"><button form="{{ $formId }}" class="iw-btn iw-btn-success iw-btn-sm">Save</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:32px"><div class="iw-empty">The directory is empty.</div></td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background:#eff6ff">
                    <td>
                        <form id="cad-officer-new" method="POST" action="{{ route('configurable-entries.cadastral.officers.store') }}">@csrf</form>
                        <div class="font-medium text-gray-900">Assign a user to a post</div>
                        <div class="ce-muted">Name from the user, rank from the post.</div>
                    </td>
                    <td>
                        <select form="cad-officer-new" name="user_id" class="ce-select" required>
                            <option value="">Choose a user…</option>
                            @foreach($cadUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->display }}{{ $user->email ? ' · ' . $user->email : '' }}{{ $user->is_active ? '' : ' (inactive)' }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select form="cad-officer-new" name="post_code" class="ce-select" required>
                            <option value="">Choose a post…</option>
                            @foreach($cadPosts as $code => $label)
                                <option value="{{ $code }}">{{ $label }}{{ $holders->has($code) ? '' : ' (unfilled)' }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td></td>
                    <td class="text-right"><button form="cad-officer-new" class="iw-btn iw-btn-primary iw-btn-sm"><i data-lucide="user-plus" class="h-3.5 w-3.5"></i> Assign</button></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
