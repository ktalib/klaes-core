@php
    /**
     * Beneficiaries register. The inline panel is both the create and the edit
     * form — BeneficiaryController::edit() renders this same page with the record
     * loaded, so editing never leaves the list.
     */
    $editing = $beneficiary->exists;
    // Keep the panel open while editing or after a rejected submission.
    $panelOpen = $editing || $errors->any();

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

<div class="farmer-entry-form {{ $panelOpen ? 'open' : '' }}" id="beneficiaryPanel">
    <form method="POST"
          action="{{ $editing
                      ? route('survey-module.compensation.beneficiaries.update', $beneficiary)
                      : route('survey-module.compensation.beneficiaries.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="form-grid">
            <div class="form-group">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" name="full_name" value="{{ old('full_name', $beneficiary->full_name) }}"
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

            <div class="form-group full">
                <label>Linked Compensation Case</label>
                <select name="survey_comp_case_id">
                    <option value="">— Not linked to a case —</option>
                    @foreach ($cases as $c)
                        <option value="{{ $c->id }}"
                            @selected((string) old('survey_comp_case_id', $beneficiary->survey_comp_case_id) === (string) $c->id)>
                            {{ $c->case_ref }} · {{ $c->project?->name ?? 'No project' }}
                            ({{ $c->isMonetary() ? 'Monetary' : 'Land-for-Land' }})
                        </option>
                    @endforeach
                </select>
                <p class="helper-text">A beneficiary may exist on its own; linking is optional.</p>
            </div>

            <div class="form-group">
                <label>Bank Name</label>
                <input type="text" name="bank_name" value="{{ old('bank_name', $beneficiary->bank_name) }}"
                       placeholder="e.g. Jaiz Bank" />
            </div>
            <div class="form-group">
                <label>Account Name</label>
                <input type="text" name="account_name" value="{{ old('account_name', $beneficiary->account_name) }}"
                       placeholder="As it appears on the account" />
            </div>
            <div class="form-group">
                <label>Account Number</label>
                <input type="text" name="account_number" value="{{ old('account_number', $beneficiary->account_number) }}"
                       placeholder="10 digits" />
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
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'status', 'case']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.beneficiaries') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>NIN</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Case</th>
                    <th>Scheme Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($beneficiaries as $b)
                    <tr>
                        <td><strong>{{ $b->full_name }}</strong></td>
                        <td>{{ $b->phone ?: '—' }}</td>
                        <td>{{ $b->nin ?: '—' }}</td>
                        <td>{{ $b->person_address ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $badgeClass($b->status) }}">
                                <span class="dot"></span>{{ $b->status }}
                            </span>
                        </td>
                        <td>
                            @if ($b->case)
                                <a href="{{ route('survey-module.compensation.cases.show', $b->case) }}">{{ $b->case->case_ref }}</a>
                            @else
                                <span style="color:var(--gray-500);">Unlinked</span>
                            @endif
                        </td>
                        <td>
                            @if ($b->case)
                                {{ $b->case->isMonetary() ? 'Monetary' : 'Land-for-Land' }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('survey-module.compensation.beneficiaries.edit', $b) }}" title="Edit"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="{{ route('survey-module.compensation.beneficiaries.destroy', $b) }}"
                                      style="display:inline" onsubmit="return confirm('Delete {{ $b->full_name }}? This cannot be undone.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-users" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny(['q', 'status', 'case']))
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

@if ($panelOpen)
    <script>
        // Arriving from an Edit link or a rejected save: put the user on the form.
        document.addEventListener('DOMContentLoaded', function () {
            var panel = document.getElementById('beneficiaryPanel');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    </script>
@endif
