@include('cadastral_module.partials._flash')


<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Surveyors</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Current Licences</div>
        <div class="kpi-value">{{ number_format($stats['active']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Suspended / Struck Off</div>
        <div class="kpi-value">{{ number_format($stats['suspended']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Firms</div>
        <div class="kpi-value">{{ number_format($stats['firms']) }}</div>
    </div>
</div>

@php
    $editing = $editing ?? null;
    $sv = fn (string $field, $default = '') => old($field, $editing->{$field} ?? $default);
@endphp

@canDo('Cad - Records', $editing ? 'edit' : 'create')
    <form method="POST"
          action="{{ $editing ? route('cadastral-module.surveyors.update', $editing) : route('cadastral-module.surveyors.store') }}"
          class="form-container" style="margin-bottom:22px;">
        @csrf
        @if ($editing)
            @method('PUT')
            <input type="hidden" name="from_edit" value="1" />
        @endif
        <div class="card-header">
            <strong>{{ $editing ? 'Edit ' . $editing->full_name : 'Add a Surveyor' }}</strong>
            <span class="helper-text" style="margin:0;">
                Only a current licence may receive an Instruction to Surveyor — the issue screen refuses otherwise.
            </span>
        </div>

        <div class="form-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" value="{{ $sv('full_name') }}" required />
                </div>
                <div class="form-group">
                    <label>SURCON Number</label>
                    <input type="text" name="surcon_number" value="{{ $sv('surcon_number') }}" />
                </div>
                <div class="form-group">
                    <label>Firm</label>
                    <input type="text" name="firm_name" value="{{ $sv('firm_name') }}" />
                </div>
                <div class="form-group">
                    <label>Firm RC No.</label>
                    <input type="text" name="firm_rc_no" value="{{ $sv('firm_rc_no') }}" />
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="{{ $sv('phone') }}" />
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ $sv('email') }}" />
                </div>
                <div class="form-group">
                    <label>Licence Status <span class="required">*</span></label>
                    <select name="licence_status" required>
                        @foreach (\App\Models\Cadastral\CadastralSurveyor::LICENCE_STATUSES as $s)
                            <option value="{{ $s }}" @selected($sv('licence_status', 'Active')===$s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Licence Expires</label>
                    <input type="date" name="licence_expires_on" value="{{ old('licence_expires_on', optional($editing?->licence_expires_on)->format('Y-m-d')) }}" />
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal;">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active ?? true)) /> Active
                    </label>
                </div>
            </div>

            {{-- Street, plot, district, LGA, state — the person-address format. --}}
            @include('cadastral_module.partials._address_builder', [
                'prefix' => 'addr_',
                'mode'   => 'person',
                'model'  => $editing,
                'legend' => 'Surveyor / Firm Address',
            ])
        </div>

        <div class="form-actions">
            @if ($editing)
                <a class="btn btn-secondary" href="{{ route('cadastral-module.surveyors.index') }}">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Changes</button>
            @else
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add to the Directory</button>
            @endif
        </div>
    </form>
@endcanDo

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Name, firm or SURCON number…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:260px;" />
        <select name="licence_status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Licences</option>
            @foreach (\App\Models\Cadastral\CadastralSurveyor::LICENCE_STATUSES as $s)
                <option value="{{ $s }}" @selected(request('licence_status')===$s)>{{ $s }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','licence_status']))
            <a class="btn btn-outline btn-sm" href="{{ route('cadastral-module.surveyors.index') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>SURCON No.</th>
                    <th>Firm</th>
                    <th>Address</th>
                    <th>Contact</th>
                    <th>Licence</th>
                    <th>Expires</th>
                    <th>Jobs</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($surveyors as $surveyor)
                    <tr>
                        <td><strong>{{ $surveyor->full_name }}</strong></td>
                        <td>{{ $surveyor->surcon_number ?: '—' }}</td>
                        <td>{{ $surveyor->firm_name ?: '—' }}</td>
                        {{-- Street, plot, district, LGA, state. --}}
                        <td>{{ Str::limit($surveyor->person_address, 40) ?: '—' }}</td>
                        <td>{{ $surveyor->phone ?: $surveyor->email ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $surveyor->canReceiveInstruction() ? 'active' : 'rejected' }}">
                                <span class="dot"></span>{{ $surveyor->licence_status }}
                            </span>
                        </td>
                        <td>{{ optional($surveyor->licence_expires_on)->format('d M Y') ?: '—' }}</td>
                        <td>{{ $surveyor->jobs_count }}</td>
                        <td>
                            <div class="action-icons">
                                @canDo('Cad - Records', 'edit')
                                    <a href="{{ route('cadastral-module.surveyors.index', ['edit' => $surveyor->id]) }}" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                @endcanDo
                                <form method="POST" action="{{ route('cadastral-module.surveyors.update', $surveyor) }}" style="display:inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="full_name" value="{{ $surveyor->full_name }}" />
                                    <input type="hidden" name="surcon_number" value="{{ $surveyor->surcon_number }}" />
                                    <input type="hidden" name="firm_name" value="{{ $surveyor->firm_name }}" />
                                    <input type="hidden" name="licence_status"
                                           value="{{ $surveyor->licence_status === 'Active' ? 'Suspended' : 'Active' }}" />
                                    <input type="hidden" name="is_active" value="{{ $surveyor->is_active ? 1 : 0 }}" />
                                    <button type="submit" title="{{ $surveyor->licence_status === 'Active' ? 'Suspend' : 'Reinstate' }}">
                                        <i class="fas fa-{{ $surveyor->licence_status === 'Active' ? 'ban' : 'rotate-left' }}"></i>
                                    </button>
                                </form>

                                @if ($surveyor->jobs_count === 0)
                                    @canDo('Cad - Records', 'delete')
                                        <form method="POST" action="{{ route('cadastral-module.surveyors.destroy', $surveyor) }}"
                                              style="display:inline"
                                              onsubmit="return confirm('Remove {{ $surveyor->full_name }} from the directory?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="Remove"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endcanDo
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-users" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            The directory is empty. Add a surveyor using the form above.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $surveyors->firstItem() ?? 0 }}–{{ $surveyors->lastItem() ?? 0 }} of {{ number_format($surveyors->total()) }}</span>
        <div class="pagination">{{ $surveyors->links() }}</div>
    </div>
</div>
