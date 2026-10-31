@php
    use App\Http\Controllers\Survey\BeneficiaryController;

    /**
     * Beneficiaries register. The inline panel is both the create and the edit
     * form — BeneficiaryController::edit() renders this same page with the record
     * loaded, so editing never leaves the list.
     */
    $editing = $beneficiary->exists;
    // Keep the panel open while editing or after a rejected submission.
    $panelOpen = $editing || $errors->any();

    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';

    $badgeClass = fn ($s) => match ($s) {
        'Verified' => 'active',
        'Review'   => 'review',
        default    => 'pending',
    };
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('beneficiaryPanel').classList.toggle('open')">
        <i class="fas fa-user-plus"></i> {{ $editing ? 'Edit Beneficiary' : 'Add Beneficiary' }}
    </button>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total Beneficiaries</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Verified</div>
        <div class="kpi-value">{{ number_format($stats['verified']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Pending</div>
        <div class="kpi-value">{{ number_format($stats['pending']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Linked to a Case</div>
        <div class="kpi-value">{{ number_format($stats['linked']) }}</div>
    </div>
</div>

<div class="farmer-entry-form {{ $panelOpen ? 'open' : '' }}" id="beneficiaryPanel"
     data-sv-modal="{{ $editing ? 'Edit Beneficiary · ' . $beneficiary->full_name : 'Add Beneficiary' }}"
     @if ($editing) data-sv-modal-close="{{ route('survey-module.compensation.beneficiaries', request()->only(['q', 'status', 'case'])) }}" @endif>
    <form method="POST"
          action="{{ $editing
                      ? route('survey-module.compensation.beneficiaries.update', $beneficiary)
                      : route('survey-module.compensation.beneficiaries.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="form-grid">
            <div class="sv-group"><i class="fas fa-user"></i><span>Beneficiary</span></div>
            <div class="form-group">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" name="full_name" id="benFullName" value="{{ old('full_name', $beneficiary->full_name) }}"
                       placeholder="e.g. Chidi Okafor" />
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $beneficiary->phone) }}"
                       placeholder="e.g. 080-1234-5678" />
            </div>
            <div class="form-group">
                <label>NIN</label>
                <input type="text" name="nin" value="{{ old('nin', $beneficiary->nin) }}" placeholder="11-digit NIN" />
            </div>
            <div class="form-group">
                <label>Status <span class="required">*</span></label>
                <select name="status">
                    @foreach (App\Http\Controllers\Survey\BeneficiaryController::STATUSES as $s)
                        <option value="{{ $s }}" @selected(old('status', $beneficiary->status ?? 'Pending') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sv-group"><i class="fas fa-link"></i><span>Compensation case</span></div>
            <div class="form-group full">
                <label>Linked Compensation Case</label>
                <select name="survey_comp_case_id">
                    <option value="">— Not linked to a case —</option>
                    @foreach ($cases as $c)
                        <option value="{{ $c->id }}"
                            @selected((string) old('survey_comp_case_id', $beneficiary->survey_comp_case_id) === (string) $c->id)>
                            {{ $c->case_ref }} · {{ $c->project?->name ?? 'No project' }}
                            ({{ $c->isMonetary() ? 'Monetary Compensation' : 'Land to Land' }})
                        </option>
                    @endforeach
                </select>
                <p class="helper-text">A beneficiary may exist on its own; linking is optional.</p>
            </div>

        </div>

        {{-- Bank details (user transcript, 2026-10-25): account name starts as the beneficiary's name but stays editable, e.g. a spouse's account. --}}
        @php
            $bankVal   = old('bank_name', $beneficiary->bank_name);
            $bankKnown = $bankVal === null || $bankVal === '' || $bankVal === BeneficiaryController::BANK_OTHER || $banks->contains($bankVal);
            $bankPick  = $bankKnown ? $bankVal : BeneficiaryController::BANK_OTHER;
            $bankOther = old('bank_name_other', $bankKnown ? '' : $bankVal);
        @endphp
        <div style="margin-top:18px;">
            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-university"></i><span>Bank details</span></div>
                <div class="form-group">
                    <label>Account Name</label>
                    <input type="text" name="account_name" id="benAccountName" maxlength="255"
                           value="{{ old('account_name', $beneficiary->account_name) }}"
                           placeholder="Filled from the full name; edit if different" />
                    <p class="helper-text">Copied from the full name; change it if the account belongs to someone else.</p>
                </div>
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" inputmode="numeric" maxlength="10" pattern="\d{10}"
                           value="{{ old('account_number', $beneficiary->account_number) }}" placeholder="10 digits" />
                </div>
                <div class="form-group">
                    <label>Bank</label>
                    <select name="bank_name" id="benBank">
                        <option value="">— Select bank —</option>
                        @foreach ($banks as $b)
                            <option value="{{ $b }}" @selected($bankPick === $b)>{{ $b }}</option>
                        @endforeach
                        <option value="{{ BeneficiaryController::BANK_OTHER }}" @selected($bankPick === BeneficiaryController::BANK_OTHER)>Other (specify)</option>
                    </select>
                </div>
                <div class="form-group" id="benBankOtherBox" @style(['display:none' => $bankPick !== BeneficiaryController::BANK_OTHER])>
                    <label>Specify Bank <span class="required">*</span></label>
                    <input type="text" name="bank_name_other" id="benBankOther" maxlength="255" value="{{ $bankOther }}"
                           @disabled($bankPick !== BeneficiaryController::BANK_OTHER) placeholder="Bank not in the list" />
                </div>
            </div>
        </div>

        {{-- Person address: Street, Plot|House, District, LGA, State --}}
        @include('survey_module.partials._address_builder', [
            'prefix' => 'addr_',
            'mode'   => 'person',
            'model'  => $beneficiary,
            'legend' => 'Beneficiary Address',
        ])

        <div class="form-actions">
            <div class="left">
                @if ($editing)
                    <a class="btn btn-secondary" href="{{ route('survey-module.compensation.beneficiaries') }}">
                        <i class="fas fa-times"></i> Cancel Edit
                    </a>
                @else
                    <button type="button" class="btn btn-secondary"
                            onclick="document.getElementById('beneficiaryPanel').classList.remove('open')">
                        <i class="fas fa-times"></i> Close
                    </button>
                @endif
            </div>
            <div class="right">
                <button class="btn btn-success" type="submit">
                    <i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Add Beneficiary' }}
                </button>
            </div>
        </div>
    </form>
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, phone, NIN, case…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:250px;" />
        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach (App\Http\Controllers\Survey\BeneficiaryController::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        @include('survey_module.partials._origin_filter')
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'status', 'case', 'origin']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.beneficiaries') }}">Clear</a>
        @endif
    </div>
</form>

@include('survey_module.partials._table_ui')

<div class="table-wrapper">
    <div class="table-scroll">
        <table class="sv-dt">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>NIN</th>
                    <th>Address</th>
                    <th>Bank</th>
                    <th>Account Name</th>
                    <th>Account No.</th>
                    <th>Status</th>
                    <th>Case</th>
                    <th>Scheme Type</th>
                    <th>Created By</th>
                    <th>Date Created</th>
                    <th class="col-actions"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($beneficiaries as $b)
                    <tr>
                        <td class="muted">{{ $beneficiaries->firstItem() + $loop->index }}</td>
                        <td class="mid"><span class="strong">{{ $b->full_name }}</span></td>
                        <td style="white-space:nowrap;">
                            @if ($b->phone)
                                {{-- Opens the outreach card (Call / SMS / WhatsApp / Email). --}}
                                <button type="button" data-sv-contact data-phone="{{ $b->phone }}" data-name="{{ $b->full_name }}"
                                        data-email="" data-ref="{{ $b->case?->case_ref }}" title="Contact {{ $b->full_name }}"
                                        class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-600 hover:text-white hover:border-emerald-600">
                                    <i data-lucide="phone" class="h-3 w-3"></i><span>{{ $b->phone }}</span>
                                </button>
                            @else
                                —
                            @endif
                        </td>
                        <td class="nowrap">{!! $b->nin ? e($b->nin) : '<span class="muted">—</span>' !!}</td>
                        <td class="mid">{!! $b->person_address ? e($b->person_address) : '<span class="muted">—</span>' !!}</td>
                        <td>{!! $b->bank_name ? e($b->bank_name) : '<span class="muted">—</span>' !!}</td>
                        <td>{!! $b->account_name ? e($b->account_name) : '<span class="muted">—</span>' !!}</td>
                        <td class="nowrap">{!! $b->account_number ? e($b->account_number) : '<span class="muted">—</span>' !!}</td>
                        <td>
                            <span class="status-badge {{ $badgeClass($b->status) }}">
                                <span class="dot"></span>{{ $b->status }}
                            </span>
                        </td>
                        <td>
                            @if ($b->case)
                                <a class="nowrap" href="{{ route('survey-module.compensation.cases.show', $b->case) }}">{{ $b->case->case_ref }}</a>
                                @if ($b->case->project)<span class="sub" title="{{ $b->case->project->display_name }}{{ $b->case->project->plan_no ? ' · ' . $b->case->project->plan_no : '' }}">{{ \Illuminate\Support\Str::limit($b->case->project->display_name, 28) }}</span>@endif
                            @else
                                <span style="color:var(--gray-500);">Unlinked</span>
                            @endif
                        </td>
                        <td>
                            @if ($b->case)
                                <span class="nowrap">{{ $b->case->isMonetary() ? 'Monetary Compensation' : 'Land to Land' }}</span>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        @include('survey_module.partials._created_cells', ['row' => $b])
                        <td class="col-actions">
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a href="{{ route('survey-module.compensation.beneficiaries.edit', $b) }}" class="{{ $svItem }}">
                                        <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                    </a>
                                    @if ($b->case)
                                        <a href="{{ route('survey-module.compensation.cases.show', $b->case) }}" class="{{ $svItem }}">
                                            <i data-lucide="file-text" class="w-4 h-4 text-gray-500"></i> View Case
                                        </a>
                                    @endif
                                    <form method="POST" action="{{ route('survey-module.compensation.beneficiaries.destroy', $b) }}"
                                          onsubmit="return confirm('Delete {{ addslashes($b->full_name) }}? This cannot be undone.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="{{ $svItem }} text-red-600">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-users" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny(['q', 'status', 'case', 'origin']))
                                No beneficiaries match these filters.
                                <a href="{{ route('survey-module.compensation.beneficiaries') }}">Clear them</a>.
                            @else
                                No beneficiaries yet. Use <strong>Add Beneficiary</strong> above, or register them on
                                <a href="{{ route('survey-module.compensation.cases.register') }}">Step 2 of a case</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $beneficiaries->firstItem() ?? 0 }}–{{ $beneficiaries->lastItem() ?? 0 }}
            of {{ number_format($beneficiaries->total()) }} beneficiaries
        </span>
        <div class="pagination">{{ $beneficiaries->links() }}</div>
    </div>
</div>

<script>
    // Bank details: the account name follows the full name until someone edits it;
    // "Other" reveals the specify box (disabled, so not posted, otherwise).
    (function () {
        var full = document.getElementById('benFullName');
        var acc = document.getElementById('benAccountName');
        if (full && acc) {
            var mirrored = acc.value === '' || acc.value === full.value;
            acc.addEventListener('input', function () { mirrored = acc.value === '' || acc.value === full.value; });
            full.addEventListener('input', function () { if (mirrored) acc.value = full.value; });
        }
        var bank = document.getElementById('benBank');
        var box = document.getElementById('benBankOtherBox');
        var other = document.getElementById('benBankOther');
        if (bank && box && other) {
            bank.addEventListener('change', function () {
                var on = bank.value === @json(BeneficiaryController::BANK_OTHER);
                box.style.display = on ? '' : 'none';
                other.disabled = !on;
                other.required = on;
                if (on) other.focus();
            });
        }
    })();
</script>

@if ($panelOpen)
    <script>
        // Arriving from an Edit link or a rejected save: put the user on the form.
        document.addEventListener('DOMContentLoaded', function () {
            var panel = document.getElementById('beneficiaryPanel');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    </script>
@endif

@include('survey_module.partials._contact_card', ['contactWho' => 'Beneficiary'])
