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

{{-- Add / Edit a Surveyor opens in a modal (user, 2026-10-12). It opens by itself when editing
     (?edit=) or when a save came back with errors. --}}
<div x-data="{ open: @js((bool) $editing || $errors->any()) }">
@cadCan('create', 'ad_information')
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        @if ($editing)
            <a href="{{ route('cadastral-module.surveyors.index') }}" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add a Surveyor</a>
        @else
            <button type="button" class="btn btn-primary" @click="open = true"><i class="fas fa-user-plus"></i> Add a Surveyor</button>
        @endif
    </div>
@endcadCan
@cadCan($editing ? 'edit' : 'create', 'ad_information')
  <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black bg-opacity-40 p-6"
       @keydown.escape.window="{{ $editing ? "window.location = '" . route('cadastral-module.surveyors.index') . "'" : 'open = false' }}">
    <form method="POST"
          action="{{ $editing ? route('cadastral-module.surveyors.update', $editing) : route('cadastral-module.surveyors.store') }}"
          class="form-container w-full" style="max-width:960px;margin:24px auto;">
        @csrf
        @if ($editing)
            @method('PUT')
            <input type="hidden" name="from_edit" value="1" />
        @endif
        <div class="card-header" style="position:relative;padding-right:56px;">
            @if ($editing)
                <a href="{{ route('cadastral-module.surveyors.index') }}" title="Close" aria-label="Close"
                   style="position:absolute;top:14px;right:18px;font-size:22px;line-height:1;color:var(--gray-500);">&times;</a>
            @else
                <button type="button" @click="open = false" title="Close" aria-label="Close"
                        style="position:absolute;top:14px;right:18px;font-size:22px;line-height:1;color:var(--gray-500);background:none;border:0;cursor:pointer;">&times;</button>
            @endif
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
                <button type="button" class="btn btn-secondary" @click="open = false">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add to the Directory</button>
            @endif
        </div>
    </form>
  </div>
@endcadCan
</div>

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
                    <th>S/N</th>
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
                        <td>{{ $surveyors->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $surveyor->full_name }}</strong>
                            @if (! empty($surveyor->surveyors_list_id))
                                <div class="text-xs text-blue-700">Survey Dept staff (from Survey &gt; Surveyors)</div>
                            @endif
                        </td>
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
                            {{-- One three-dots menu (app standard). --}}
                            @php
                              $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
                            @endphp
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    @if ($surveyor->jobs_count)
                                        <a href="{{ route('cadastral-module.survey-jobs.index', ['q' => $surveyor->full_name]) }}" class="{{ $svItem }}">
                                            <i data-lucide="list" class="w-4 h-4 text-gray-500"></i> View jobs ({{ $surveyor->jobs_count }})
                                        </a>
                                    @endif
                                    @if (! empty($surveyor->surveyors_list_id))
                                        {{-- Copied from the Survey staff list (SurveyStaffSync): edited there, so the next sync keeps it. --}}
                                        <a href="{{ route('survey-module.records.surveyors.index', ['edit' => $surveyor->surveyors_list_id]) }}" class="{{ $svItem }}">
                                            <i data-lucide="external-link" class="w-4 h-4 text-gray-500"></i> Edit in Survey
                                        </a>
                                    @else
                                    @cadCan('edit', 'ad_information')
                                        <a href="{{ route('cadastral-module.surveyors.index', ['edit' => $surveyor->id]) }}" class="{{ $svItem }}">
                                            <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                        </a>
                                    @endcadCan
                                    @endif
                                    <form method="POST" action="{{ route('cadastral-module.surveyors.update', $surveyor) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="full_name" value="{{ $surveyor->full_name }}" />
                                        <input type="hidden" name="surcon_number" value="{{ $surveyor->surcon_number }}" />
                                        <input type="hidden" name="firm_name" value="{{ $surveyor->firm_name }}" />
                                        <input type="hidden" name="licence_status"
                                               value="{{ $surveyor->licence_status === 'Active' ? 'Suspended' : 'Active' }}" />
                                        <input type="hidden" name="is_active" value="{{ $surveyor->is_active ? 1 : 0 }}" />
                                        <button type="submit" class="{{ $svItem }}">
                                            <i data-lucide="{{ $surveyor->licence_status === 'Active' ? 'ban' : 'rotate-ccw' }}" class="w-4 h-4 text-gray-500"></i>
                                            {{ $surveyor->licence_status === 'Active' ? 'Suspend' : 'Reinstate' }}
                                        </button>
                                    </form>
                                    @if ($surveyor->jobs_count === 0 && empty($surveyor->surveyors_list_id))
                                        @canDo('Cad - Records', 'delete')
                                            <form method="POST" action="{{ route('cadastral-module.surveyors.destroy', $surveyor) }}"
                                                  onsubmit="return confirm('Remove {{ $surveyor->full_name }} from the directory?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Remove</button>
                                            </form>
                                        @endcanDo
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-users" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            The directory is empty. Add one with the Add a Surveyor button.
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
