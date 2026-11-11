{{-- Add Company / Add Surveyor on MLPP-SG Portal Admin > Registrations (user, 2026-11-10): the SG's own forms in a
     pop-up, not the portal page. Company = the portal's company form; Surveyor = the Cadastral "Add a Surveyor" form,
     with the company picked from the approved list (Firm / RC No. come from it, not shown) and the portal username added;
     Email optional and no Licence Expires (user, 2026-11-10).
     Both are approved on save; the login is sent once by SMS and email. One form per tab, so one address builder. --}}
@php
    $brForm = $registrationTab === 'surveyors' ? 'surveyor' : 'company';
    $brOpen = old('br_form') === $brForm && $errors->any();
    $brCompanies = \App\Services\Survey\SurveySettings::companies();
    $brOld = fn (string $f, $d = '') => old($f, $d);
@endphp
{{-- Shown with style.display: the "flex" class would override a hidden attribute and leave an invisible layer over the page. --}}
<div id="brAddModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black bg-opacity-40 p-6" style="{{ $brOpen ? '' : 'display:none;' }}">
    <form method="POST" action="{{ $brForm === 'surveyor' ? route('survey-module.beacon-requests.accounts.store') : route('survey-module.beacon-requests.companies.store') }}"
          class="form-container w-full" style="max-width:960px;margin:24px auto;" autocomplete="off">
        @csrf
        <input type="hidden" name="br_form" value="{{ $brForm }}">
        <div class="card-header" style="position:relative;padding-right:56px;">
            <button type="button" data-br-close title="Close" aria-label="Close"
                    style="position:absolute;top:14px;right:18px;font-size:22px;line-height:1;color:var(--gray-500);background:none;border:0;cursor:pointer;">&times;</button>
            <strong>{{ $brForm === 'surveyor' ? 'Add a Surveyor' : 'Add a Company' }}</strong>
            <span class="helper-text" style="margin:0;">
                {{ $brForm === 'surveyor'
                    ? 'Approved when saved: the surveyor gets a Surveyor ID, and the portal login is sent once by SMS and email.'
                    : 'Approved when saved: the company gets its beacon prefix, and its portal login is sent once by SMS and email.' }}
            </span>
        </div>

        <div class="form-body">
            @if ($brOpen)
                <div class="alert alert-error" style="margin-bottom:12px;">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            @if ($brForm === 'surveyor')
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company <span class="required">*</span></label>
                        <select name="company_id" id="brCompany" class="sv-searchable" data-placeholder="Choose the company…" required>
                            <option value=""></option>
                            @foreach ($brCompanies as $c)
                                <option value="{{ $c->id }}" @selected((string) $brOld('company_id') === (string) $c->id)>{{ $c->name }}{{ $c->beacon_letters ? ' (' . $c->beacon_letters . ')' : '' }}</option>
                            @endforeach
                        </select>
                        <p class="helper-text">Approved companies. A new company is added on the Company Registrations tab.</p>
                    </div>
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" value="{{ $brOld('full_name') }}" maxlength="255" required />
                    </div>
                    <div class="form-group">
                        <label>SURCON Number</label>
                        <input type="text" name="surcon_number" value="{{ $brOld('surcon_number') }}" maxlength="50" />
                    </div>
                    <div class="form-group">
                        <label>Phone <span class="required">*</span></label>
                        <input type="text" name="phone" value="{{ $brOld('phone') }}" maxlength="30" required placeholder="08031234567" />
                    </div>
                    {{-- Optional on the SG's form (user, 2026-11-10); without one the login goes by SMS only. --}}
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="{{ $brOld('email') }}" maxlength="150" />
                    </div>
                    <div class="form-group">
                        <label>Portal Username <span class="required">*</span></label>
                        <input type="text" name="username" value="{{ $brOld('username') }}" maxlength="150" required pattern="[A-Za-z0-9._@-]+" />
                        <p class="helper-text">Letters, numbers and . _ - @ (no spaces).</p>
                    </div>
                    <div class="form-group">
                        <label>Licence Status <span class="required">*</span></label>
                        <select name="licence_status" required>
                            @foreach (\App\Models\Cadastral\CadastralSurveyor::LICENCE_STATUSES as $s)
                                <option value="{{ $s }}" @selected($brOld('licence_status', 'Active') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <label style="display:inline-flex;align-items:center;justify-content:flex-start;gap:6px;font-weight:normal;width:auto;">
                            <input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('br_form') ? old('is_active') : true) /> Active
                        </label>
                    </div>
                </div>
                @include('survey_module.beacon_requests._address_builder', ['prefix' => 'addr_', 'mode' => 'person', 'model' => null, 'legend' => 'Surveyor / Firm Address'])
            @else
                <div class="form-grid">
                    <div class="form-group">
                        <label>Registered Company Name <span class="required">*</span></label>
                        <input type="text" name="name" value="{{ $brOld('name') }}" maxlength="255" required />
                    </div>
                    <div class="form-group">
                        <label>RC No. <span class="required">*</span></label>
                        <input type="text" name="company_rc_no" value="{{ $brOld('company_rc_no') }}" maxlength="50" required placeholder="RC 123456" />
                    </div>
                    <div class="form-group">
                        <label>Company Phone <span class="required">*</span></label>
                        <input type="text" name="phone" value="{{ $brOld('phone') }}" maxlength="30" required />
                    </div>
                    <div class="form-group">
                        <label>Company Email <span class="required">*</span></label>
                        <input type="email" name="email" value="{{ $brOld('email') }}" maxlength="150" required />
                    </div>
                </div>
                @include('survey_module.beacon_requests._address_builder', ['prefix' => 'addr_', 'mode' => 'person', 'model' => null, 'legend' => 'Company Address'])
            @endif
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" data-br-close>Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> {{ $brForm === 'surveyor' ? 'Add and Approve Surveyor' : 'Add and Approve Company' }}</button>
        </div>
    </form>
</div>
<script>
(function () {
    var modal = document.getElementById('brAddModal');
    if (!modal) return;
    var shown = function () { return modal.style.display !== 'none'; };
    var open = function (on) { modal.style.display = on ? 'flex' : 'none'; document.body.style.overflow = on ? 'hidden' : ''; };
    document.querySelectorAll('[data-br-open]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); open(true); }); });
    modal.querySelectorAll('[data-br-close]').forEach(function (b) { b.addEventListener('click', function () { open(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && shown()) open(false); });
    if (shown()) document.body.style.overflow = 'hidden';
})();
</script>
