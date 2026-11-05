@extends('survey_module.layouts.klaes')

@section('page-title', 'Beacon Requests — KLAES')

@php
  $PageTitle = 'Beacon Requests';
  $PageDescription = 'Surveyor registrations and beacon requests for the Surveyor General.';
@endphp

@section('survey-content')
@php
    $item = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $tabUrl = fn ($t) => route('survey-module.beacon-requests.index', $t === 'requests' ? [] : ['tab' => $t]);
    $badge = fn ($s) => match (strtolower((string) $s)) {
        'submitted', 'pending' => 'pending',
        'issued', 'active'     => 'review',
        'accepted'             => 'completed',
        default                => 'cancelled',
    };
    $filtered = request()->hasAny(['q', 'status']);
@endphp

@include('survey_module.partials._flash')

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Requests Waiting</div><div class="kpi-value">{{ number_format($stats['waiting']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Issued, Not Yet Accepted</div><div class="kpi-value">{{ number_format($stats['issued']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Accepted</div><div class="kpi-value">{{ number_format($stats['accepted']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Registrations Waiting</div><div class="kpi-value">{{ number_format($stats['pending']) }}</div></div>
</div>

<div class="tabs" style="margin-bottom:12px;">
    <a class="tab {{ $tab === 'requests' ? 'active' : '' }}" href="{{ $tabUrl('requests') }}"><i class="fas fa-map-pin"></i> Beacon Requests</a>
    <a class="tab {{ $tab === 'registrations' ? 'active' : '' }}" href="{{ $tabUrl('registrations') }}"><i class="fas fa-user-check"></i> Registrations</a>
</div>

<form method="GET" class="table-toolbar">
    @if ($tab === 'registrations')<input type="hidden" name="tab" value="registrations" />@endif
    <div class="left" style="flex-wrap:wrap;gap:8px;">
        @php $fs = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;'; @endphp
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Name, company, number…" style="{{ $fs }}width:240px;" />
        <select name="status" style="{{ $fs }}">
            <option value="">All Status</option>
            @foreach ($tab === 'requests' ? ['Submitted', 'Issued', 'Accepted', 'Rejected'] : ['pending' => 'Waiting', 'active' => 'Approved', 'rejected' => 'Not approved'] as $v => $l)
                @php $val = is_int($v) ? $l : $v; @endphp
                <option value="{{ $val }}" @selected($status === $val)>{{ $l }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if ($filtered)<a class="btn btn-outline btn-sm" href="{{ $tabUrl($tab) }}">Clear</a>@endif
    </div>
</form>

@if ($tab === 'requests')
<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th><th>Request No.</th><th>Date</th><th>From</th><th>Type</th><th>Company</th><th>Surveyor's Name</th><th>Job</th>
                    <th>Beacons</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Status</th><th>Issued Numbers</th><th style="width:48px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $q)
                    <tr>
                        <td>{{ $requests->firstItem() + $loop->index }}</td>
                        <td style="white-space:nowrap;"><strong>{{ $q->request_no }}</strong></td>
                        <td style="white-space:nowrap;">{{ optional($q->created_at)->format('d/m/Y') }}</td>
                        <td style="white-space:nowrap;">{{ $q->source_label }}</td>
                        <td>{{ $q->type_label }}</td>
                        <td>{{ $q->company_name ?: '—' }}</td>
                        <td>{{ $q->surveyor_name }}</td>
                        <td>{{ $q->job_name ?: '—' }}</td>
                        <td>{{ number_format($q->quantity) }}</td>
                        <td>{{ $q->plot_no ?: '—' }}</td>
                        <td>{{ $q->district ?: '—' }}</td>
                        <td>{{ $q->lga ? mb_strtoupper($q->lga) : '—' }}</td>
                        <td>{{ $q->location ?: '—' }}</td>
                        <td style="white-space:nowrap;">
                            <span class="status-badge {{ $badge($q->status) }}"><span class="dot"></span>{{ $q->status === 'Rejected' ? 'Not issued' : $q->status }}</span>
                            @if ($q->review_note)<div class="helper-text" style="margin:2px 0 0;white-space:normal;">{{ $q->review_note }}</div>@endif
                        </td>
                        <td>{{ $q->issued_numbers ?: '—' }}</td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    @if ($q->status === 'Submitted')
                                        <button type="button" class="{{ $item }}" @click="open = false"
                                                data-brq-issue data-url="{{ route('survey-module.beacon-requests.issue', $q) }}"
                                                data-no="{{ $q->request_no }}" data-qty="{{ $q->quantity }}" data-type="{{ $q->layout_type }}"
                                                data-company="{{ $q->company_id }}" data-company-name="{{ $q->company_name }}">
                                            <i data-lucide="hash" class="w-4 h-4 text-gray-500"></i> Issue Beacons
                                        </button>
                                        <button type="button" class="{{ $item }} text-red-600" @click="open = false"
                                                data-brq-reject data-url="{{ route('survey-module.beacon-requests.reject', $q) }}" data-no="{{ $q->request_no }}">
                                            <i data-lucide="x-circle" class="w-4 h-4 text-red-500"></i> Do Not Issue
                                        </button>
                                    @elseif (in_array($q->status, ['Issued', 'Accepted'], true))
                                        <a href="{{ route('survey-module.beacon-requests.slip', $q) }}" target="_blank" class="{{ $item }}">
                                            <i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> Print Slip
                                        </a>
                                    @else
                                        <div class="px-3 py-2 text-xs text-gray-500">No actions available.</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="16" style="text-align:center;padding:28px;color:var(--gray-500);">No beacon requests{{ $filtered ? ' for this selection' : ' yet' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ number_format($requests->total()) }} requests</span>
        <div class="pagination">{{ $requests->links() }}</div>
    </div>
</div>

{{-- Issue: Public takes the next numbers from the pool; Private is A + serial + letters. --}}
<form method="POST" id="brqIssue" action="" class="farmer-entry-form" data-sv-modal="Issue Beacons" data-sv-modal-size="sm" data-sv-modal-static>
    @csrf
    <p id="brqIssueHead" style="font-weight:600;margin:0 0 12px;"></p>
    <div class="form-grid" style="grid-template-columns:1fr;">
        <div class="form-group">
            <label>Issue Date <span class="required">*</span></label>
            <input type="date" name="issue_date" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required />
        </div>
        <div id="brqPublic" style="display:none;">
            <p class="helper-text" style="margin:0;">
                @if ($pool['prefix'])
                    The next numbers come from the pool, now at {{ $pool['prefix'] }} with {{ number_format($pool['available']) }} free.
                @else
                    The public beacon pool has no free numbers.
                @endif
            </p>
        </div>
        <div id="brqPrivate" style="display:none;">
            <div class="form-group">
                <label>Company <span class="required">*</span></label>
                <select name="company_choice" id="brqCompany">
                    <option value="">Select company…</option>
                    @foreach ($companies as $c)<option value="{{ $c->id }}" data-letters="{{ $c->beacon_letters }}">{{ $c->name }}{{ $c->beacon_letters ? ' · ' . $c->beacon_letters : '' }}</option>@endforeach
                    <option value="other">Other (specify)</option>
                </select>
                <input type="text" name="company_other" id="brqCompanyOther" maxlength="255" placeholder="Specify company *" aria-label="Specify company" style="display:none;margin-top:6px;" />
            </div>
            <div class="form-group">
                <label>Letters <span class="required">*</span></label>
                <input type="text" name="letters" id="brqLetters" maxlength="3" placeholder="e.g. G" style="text-transform:uppercase;width:90px;" />
                <p class="helper-text" id="brqNextNote"></p>
            </div>
            <div class="form-group">
                <label>Start Serial <span class="required">*</span></label>
                <input type="number" name="start" id="brqStart" min="1" max="9999" style="width:120px;" />
                <p class="helper-text" id="brqPreview" style="font-weight:600;"></p>
            </div>
        </div>
    </div>
    <div class="form-actions">
        <div class="left"><button type="button" class="btn btn-secondary" onclick="document.getElementById('brqIssue').classList.remove('open')">Cancel</button></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> Issue</button></div>
    </div>
</form>

<form method="POST" id="brqReject" action="" class="farmer-entry-form" data-sv-modal="Do Not Issue" data-sv-modal-size="sm">
    @csrf
    <p id="brqRejectHead" style="font-weight:600;margin:0 0 12px;"></p>
    <div class="form-group">
        <label>Reason <span class="required">*</span></label>
        <textarea name="review_note" rows="3" maxlength="1000" required></textarea>
        <p class="helper-text">The requester sees this.</p>
    </div>
    <div class="form-actions">
        <div class="left"><button type="button" class="btn btn-secondary" onclick="document.getElementById('brqReject').classList.remove('open')">Cancel</button></div>
        <div class="right"><button class="btn btn-danger" type="submit">Do Not Issue</button></div>
    </div>
</form>

<script>
(function () {
    var el = function (id) { return document.getElementById(id); };
    var nextUrl = @json(route('survey-module.workflow.beacon-register.next-serial'));
    var qty = 0;
    function pad(n) { n = String(n); while (n.length < 4) n = '0' + n; return n; }
    function preview() {
        var letters = el('brqLetters').value.replace(/[^a-z]/gi, '').toUpperCase(), start = parseInt(el('brqStart').value, 10);
        el('brqPreview').textContent = letters && start > 0 ? 'A' + pad(start) + letters + (qty > 1 ? ' to A' + pad(start + qty - 1) + letters : '') + ' (' + qty + ')' : '';
    }
    function next() {
        var letters = el('brqLetters').value.replace(/[^a-z]/gi, '').toUpperCase();
        el('brqNextNote').textContent = '';
        if (!letters) { preview(); return; }
        fetch(nextUrl + '?prefix=A&suffix=' + encodeURIComponent(letters), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                el('brqStart').value = d.next;
                el('brqNextNote').textContent = d.last ? 'Last issued: A' + pad(d.last) + letters + '.' : 'None issued yet with ' + letters + '.';
                preview();
            }).catch(function () { el('brqNextNote').textContent = 'The last serial could not be checked; type the serial.'; });
    }
    function companyChanged() {
        var s = el('brqCompany'), o = s.options[s.selectedIndex], other = s.value === 'other';
        el('brqCompanyOther').style.display = other ? '' : 'none';
        el('brqCompanyOther').required = other;
        el('brqLetters').value = o && o.dataset.letters ? o.dataset.letters : '';
        next();
    }
    el('brqCompany').addEventListener('change', companyChanged);
    el('brqLetters').addEventListener('input', function () { this.value = this.value.replace(/[^a-z]/gi, '').toUpperCase(); next(); });
    el('brqStart').addEventListener('input', preview);

    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-brq-issue]');
        if (b) {
            var isPublic = b.dataset.type === 'Government';
            qty = parseInt(b.dataset.qty, 10);
            el('brqIssue').action = b.dataset.url;
            el('brqIssueHead').textContent = b.dataset.no + ': ' + qty + ' beacon' + (qty === 1 ? '' : 's');
            el('brqPublic').style.display = isPublic ? '' : 'none';
            el('brqPrivate').style.display = isPublic ? 'none' : '';
            el('brqLetters').required = el('brqStart').required = el('brqCompany').required = !isPublic;
            if (!isPublic) {
                var s = el('brqCompany'), has = Array.prototype.some.call(s.options, function (o) { return o.value === b.dataset.company; });
                s.value = has ? b.dataset.company : (b.dataset.companyName ? 'other' : '');
                companyChanged();
                el('brqCompanyOther').value = s.value === 'other' ? (b.dataset.companyName || '') : '';
            }
            el('brqIssue').classList.add('open');
            return;
        }
        var x = e.target.closest('[data-brq-reject]');
        if (x) {
            el('brqReject').action = x.dataset.url;
            el('brqRejectHead').textContent = x.dataset.no;
            el('brqReject').classList.add('open');
        }
    });
})();
</script>
@else
<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th><th>Passport</th><th>Registration ID</th><th>Surveyor ID</th><th>Date</th><th>Type</th><th>Company</th><th>Surveyor's Name</th><th>SURCON No.</th>
                    <th>Phone</th><th>Email</th><th>Address</th><th>Status</th><th style="width:48px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $a)
                    <tr>
                        <td>{{ $accounts->firstItem() + $loop->index }}</td>
                        <td>
                            {{-- Passport photograph given at registration (2026-11-05); opens full size. --}}
                            @if ($a->passport_path)
                                <a href="{{ route('survey-module.beacon-requests.accounts.passport', $a) }}" target="_blank" rel="noopener" title="Open {{ $a->name }}'s passport photograph">
                                    <img src="{{ route('survey-module.beacon-requests.accounts.passport', $a) }}" alt="Passport of {{ $a->name }}" loading="lazy"
                                         style="width:40px;height:50px;object-fit:cover;border-radius:4px;border:1px solid var(--gray-300, #d1d5db);display:block;">
                                </a>
                            @else
                                <span style="color:var(--gray-500);">—</span>
                            @endif
                        </td>
                        <td style="white-space:nowrap;"><strong>{{ $a->registration_id }}</strong></td>
                        <td style="white-space:nowrap;">{{ $a->surveyor_id_no ?: '—' }}</td>
                        <td style="white-space:nowrap;">{{ optional($a->created_at)->format('d/m/Y') }}</td>
                        <td>{{ $a->type_label }}</td>
                        <td>{{ $a->company_name ?: '—' }}</td>
                        <td>{{ $a->name }}</td>
                        <td>{{ $a->surcon_number ?: '—' }}</td>
                        <td style="white-space:nowrap;">{{ $a->phone }}</td>
                        <td>{{ $a->email }}</td>
                        <td style="min-width:180px;">{{ $a->person_address ?: '—' }}</td>
                        <td style="white-space:nowrap;">
                            <span class="status-badge {{ $badge($a->status) }}"><span class="dot"></span>{{ ['pending' => 'Waiting', 'active' => 'Approved', 'rejected' => 'Not approved', 'suspended' => 'Suspended'][$a->status] ?? $a->status }}</span>
                            @if ($a->review_note)<div class="helper-text" style="margin:2px 0 0;white-space:normal;">{{ $a->review_note }}</div>@endif
                        </td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    @if ($a->status === 'pending')
                                        <form method="POST" action="{{ route('survey-module.beacon-requests.accounts.approve', $a) }}" onsubmit="return confirm('Approve {{ addslashes($a->name) }}? They will be able to sign in.');">
                                            @csrf
                                            <button type="submit" class="{{ $item }}"><i data-lucide="check" class="w-4 h-4 text-gray-500"></i> Approve</button>
                                        </form>
                                        <button type="button" class="{{ $item }} text-red-600" @click="open = false"
                                                data-acc-reject data-url="{{ route('survey-module.beacon-requests.accounts.reject', $a) }}" data-name="{{ $a->name }}">
                                            <i data-lucide="x-circle" class="w-4 h-4 text-red-500"></i> Do Not Approve
                                        </button>
                                    @else
                                        <div class="px-3 py-2 text-xs text-gray-500">No actions available.</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="14" style="text-align:center;padding:28px;color:var(--gray-500);">No registrations{{ $filtered ? ' for this selection' : ' yet' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $accounts->firstItem() ?? 0 }}–{{ $accounts->lastItem() ?? 0 }} of {{ number_format($accounts->total()) }} registrations</span>
        <div class="pagination">{{ $accounts->links() }}</div>
    </div>
</div>

<form method="POST" id="accReject" action="" class="farmer-entry-form" data-sv-modal="Do Not Approve" data-sv-modal-size="sm">
    @csrf
    <p id="accRejectHead" style="font-weight:600;margin:0 0 12px;"></p>
    <div class="form-group">
        <label>Reason <span class="required">*</span></label>
        <textarea name="review_note" rows="3" maxlength="1000" required></textarea>
    </div>
    <div class="form-actions">
        <div class="left"><button type="button" class="btn btn-secondary" onclick="document.getElementById('accReject').classList.remove('open')">Cancel</button></div>
        <div class="right"><button class="btn btn-danger" type="submit">Do Not Approve</button></div>
    </div>
</form>
<script>
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-acc-reject]');
        if (!b) return;
        var f = document.getElementById('accReject');
        f.action = b.dataset.url;
        document.getElementById('accRejectHead').textContent = b.dataset.name;
        f.classList.add('open');
    });
</script>
@endif
@endsection
