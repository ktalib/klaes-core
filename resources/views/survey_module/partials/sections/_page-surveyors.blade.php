@php
    $e        = $editing;
    $formOpen = $e || $errors->any();
    $val      = fn ($field, $default = null) => old($field, $e ? $e->{$field} : $default);
    $keep     = request()->only(['q', 'rank', 'status', 'page']);
    $svItem   = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $fs       = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';

    // Rank dropdown: a listed rank, or Other with the typed value.
    $rankVal    = old('rank', $e ? $e->rank : '');
    $rankIsList = $rankVal === '' || $rankVal === null || $ranks->contains($rankVal);
    $rankChoice = $rankVal === 'other' || ! $rankIsList ? 'other' : $rankVal;
    $rankOther  = old('rank_other', $rankIsList ? '' : $rankVal);
@endphp

<style>
    .survey-proto .sv-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 20px; }
    .survey-proto .sv-grid label { display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px; }
    .survey-proto .sv-grid input, .survey-proto .sv-grid select {
        width: 100%; padding: 8px 12px; border: 1.5px solid var(--gray-300); border-radius: var(--radius-sm);
        font-family: var(--font); font-size: 14px; background: #fff;
    }
    .survey-proto .sv-grid .is-invalid { border-color: var(--danger); }
    .survey-proto .sv-sub { font-size: 12px; color: var(--gray-500); }
    @media (max-width: 1024px) { .survey-proto .sv-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px)  { .survey-proto .sv-grid { grid-template-columns: 1fr; } }
</style>

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary btn-sm" onclick="svOpenNew()">
        <i class="fas fa-user-plus"></i> Add Staff
    </button>
</div>

<form method="POST" id="svForm" autocomplete="off"
      action="{{ $e ? route('survey-module.records.surveyors.update', $e) : route('survey-module.records.surveyors.store') }}"
      class="farmer-entry-form {{ $formOpen ? 'open' : '' }}"
      data-sv-modal="{{ $e ? 'Edit ' . $e->full_name : 'Add Staff' }}"
      @if ($e) data-sv-modal-close="{{ route('survey-module.records.surveyors.index', \Illuminate\Support\Arr::except($keep, 'page')) }}" @endif>
    @csrf
    @if ($e)
        @method('PUT')
        @foreach ($keep as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}" />
        @endforeach
    @endif

    <div class="sv-grid">
        <div class="sv-group"><i class="fas fa-user"></i><span>Personal details</span></div>
        <div>
            <label>First Name <span class="required">*</span></label>
            <input type="text" name="first_name" value="{{ $val('first_name') }}" maxlength="150" required class="@error('first_name') is-invalid @enderror" />
        </div>
        <div>
            <label>Surname <span class="required">*</span></label>
            <input type="text" name="surname" value="{{ $val('surname') }}" maxlength="150" required class="@error('surname') is-invalid @enderror" />
        </div>
        <div>
            <label>Other Name</label>
            <input type="text" name="other_name" value="{{ $val('other_name') }}" maxlength="150" />
        </div>
        <div>
            <label>Gender</label>
            <select name="gender">
                <option value="">—</option>
                @foreach (\App\Models\Survey\SurveyorListEntry::GENDERS as $g)
                    <option value="{{ $g }}" @selected($val('gender') === $g)>{{ ucfirst(strtolower($g)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" max="{{ now()->subDay()->format('Y-m-d') }}"
                   value="{{ old('date_of_birth', $e && $e->date_of_birth ? $e->date_of_birth->format('Y-m-d') : '') }}" />
        </div>
        <div class="sv-group"><i class="fas fa-id-badge"></i><span>Service</span></div>
        <div>
            <label>Staff I.D.</label>
            <input type="text" name="staff_id" value="{{ $val('staff_id') }}" maxlength="50" placeholder="e.g. KANO SG 011956" class="@error('staff_id') is-invalid @enderror" />
        </div>
        <div>
            <label>Rank</label>
            <select name="rank" id="svRank" onchange="svRankChanged()">
                <option value="">—</option>
                @foreach ($ranks as $rk)
                    <option value="{{ $rk }}" @selected($rankChoice === $rk)>{{ $rk }}</option>
                @endforeach
                <option value="other" @selected($rankChoice === 'other')>Other</option>
            </select>
        </div>
        <div id="svRankOtherWrap">
            <label>Specify rank <span class="required">*</span></label>
            <input type="text" name="rank_other" id="svRankOther" value="{{ $rankOther }}" maxlength="150" class="@error('rank_other') is-invalid @enderror" />
        </div>
        <div>
            <label>Unit</label>
            <input type="text" name="unit" value="{{ $val('unit') }}" maxlength="100" />
        </div>
        <div>
            <label>G/L</label>
            <input type="number" name="grade_level" value="{{ $val('grade_level') }}" min="1" max="17" />
        </div>
        <div class="sv-group"><i class="fas fa-phone"></i><span>Contact</span></div>
        <div>
            <label>Mobile No.</label>
            <input type="text" name="phone" value="{{ $val('phone') }}" maxlength="50" class="@error('phone') is-invalid @enderror" />
        </div>
        <div>
            <label>Official E-mail</label>
            <input type="email" name="email" value="{{ $val('email') }}" maxlength="255" class="@error('email') is-invalid @enderror" />
        </div>

    </div>

    <div class="form-actions">
        <div class="left"><a class="btn btn-secondary" href="{{ route('survey-module.records.surveyors.index', $keep) }}"><i class="fas fa-times"></i> Cancel</a></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $e ? 'Save changes' : 'Add staff' }}</button></div>
    </div>
</form>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Staff</div><div class="kpi-value">{{ number_format($stats['total']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Active</div><div class="kpi-value">{{ number_format($stats['active']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">With Staff I.D.</div><div class="kpi-value">{{ number_format($stats['with_id']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">SLTR</div><div class="kpi-value">{{ number_format($stats['sltr']) }}</div></div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;gap:8px;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Name, staff I.D., phone, e-mail…" style="{{ $fs }}width:240px;" />
        <select name="rank" style="{{ $fs }}">
            <option value="">All Ranks</option>
            @foreach ($ranks as $rk)
                <option value="{{ $rk }}" @selected(request('rank') === $rk)>{{ $rk }}</option>
            @endforeach
        </select>
        <select name="status" style="{{ $fs }}">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'rank', 'status']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.records.surveyors.index') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Staff I.D.</th>
                    <th>First Name</th>
                    <th>Surname</th>
                    <th>Other Name</th>
                    <th>Rank</th>
                    <th>Unit</th>
                    <th>Mobile No.</th>
                    <th>Official E-mail</th>
                    <th>Gender</th>
                    <th>D.O.B</th>
                    <th>G/L</th>
                    <th>Status</th>
                    <th style="width:48px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $s)
                    <tr @if ($e && $e->id === $s->id) style="background:#fff8e1;" @endif>
                        <td>{{ $staff->firstItem() + $loop->index }}</td>
                        <td style="white-space:nowrap;">{{ $s->staff_id ?: '—' }}</td>
                        <td>{{ $s->first_name }}</td>
                        <td>{{ $s->surname }}</td>
                        <td>{{ $s->other_name ?: '—' }}</td>
                        <td>{{ $s->rank ?: '—' }}</td>
                        <td>{{ $s->unit ?: '—' }}</td>
                        <td style="white-space:nowrap;">
                            @if ($s->phone)
                                {{-- Opens the outreach card (Call / SMS / WhatsApp / Email). --}}
                                <button type="button" data-sv-contact data-phone="{{ $s->phone }}" data-name="{{ $s->full_name }}"
                                        data-email="{{ $s->email }}" data-ref="{{ $s->staff_id }}" title="Contact {{ $s->full_name }}"
                                        class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-600 hover:text-white hover:border-emerald-600">
                                    <i data-lucide="phone" class="h-3 w-3"></i><span>{{ $s->phone }}</span>
                                </button>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $s->email ?: '—' }}</td>
                        <td>{{ $s->gender ? ucfirst(strtolower($s->gender)) : '—' }}</td>
                        <td style="white-space:nowrap;">{{ optional($s->date_of_birth)->format('d/m/Y') ?: '—' }}</td>
                        <td>{{ $s->grade_level ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $s->is_active ? 'active' : 'rejected' }}"><span class="dot"></span>{{ $s->is_active ? 'Active' : 'Inactive' }}</span>
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
                                    <a href="{{ route('survey-module.records.surveyors.index', $keep + ['edit' => $s->id]) }}" class="{{ $svItem }}">
                                        <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                    </a>
                                    <form method="POST" action="{{ route('survey-module.records.surveyors.toggle', $s) }}"
                                          @if ($s->is_active) onsubmit="return confirm('Deactivate {{ addslashes($s->full_name) }}? They will no longer be offered in the Beacon Register.');" @endif>
                                        @csrf
                                        <button type="submit" class="{{ $svItem }}">
                                            <i data-lucide="{{ $s->is_active ? 'user-x' : 'user-check' }}" class="w-4 h-4 text-gray-500"></i>
                                            {{ $s->is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" style="text-align:center;padding:28px;color:var(--gray-500);">No staff match this selection.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $staff->firstItem() ?? 0 }}–{{ $staff->lastItem() ?? 0 }} of {{ number_format($staff->total()) }} staff</span>
        <div class="pagination">{{ $staff->links() }}</div>
    </div>
</div>

<script>
    function svOpenNew() {
        @if ($e)
            window.location = @json(route('survey-module.records.surveyors.index', \Illuminate\Support\Arr::except($keep, 'page')));
        @else
            var f = document.getElementById('svForm');
            f.classList.add('open');
        @endif
    }

    function svRankChanged() {
        var other = document.getElementById('svRank').value === 'other';
        var wrap = document.getElementById('svRankOtherWrap'), input = document.getElementById('svRankOther');
        wrap.style.display = other ? '' : 'none';
        input.disabled = !other;
        input.required = other;
    }

    document.addEventListener('DOMContentLoaded', function () {
        svRankChanged();
        @if ($formOpen)
            document.getElementById('svForm').scrollIntoView({ block: 'start' });
        @endif
    });
</script>

@include('survey_module.partials._contact_card')
