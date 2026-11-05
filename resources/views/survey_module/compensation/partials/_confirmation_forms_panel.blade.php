{{-- Confirmation Forms (user, 2026-11-02), from the new paper template: serials are issued for a layout (a Survey
     project, or one typed under Other, e.g. BUK/Zakara Layout) with a blank printed form, and the filled paper is
     captured against its serial. A tab of the Plot Showing Register since 2026-11-03 (user): it opens there without
     loading a page. Its query keys carry a cf prefix so they never clash with the register's own (tab, q, page, edit). --}}
@php
  use App\Http\Controllers\Survey\ConfirmationFormController as CF;
  use App\Models\Survey\SurveyConfirmationForm as F;

  $cfField = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
  $cfItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $cfRoute = 'survey-module.compensation.confirmation-forms';
  $cfUrl = fn (array $p = []) => route('survey-module.gkn.plot-showing', ['tab' => 'confirmation'] + $p);
  $cfBadge = [F::GENERATED => 'pending', F::PRINTED => 'pending', F::CAPTURED => 'active', F::VOIDED => 'rejected'];

  $e = $cfEditing;
  $cfErr = $errors->any() ? old('_form') : null;
  $captureOpen = $e || $cfErr === 'capture' || request()->filled('cfserial') || request('cfcapture');
  $issueOpen = $cfErr === 'issue';
  $v = fn ($k) => old($k, $e ? ($e->{$k} instanceof \Carbon\Carbon ? $e->{$k}->format('Y-m-d') : $e->{$k}) : null);
  $chk = fn ($k) => (bool) old($k, $e ? $e->{$k} : false);
  $preSerial = (string) old('form_id', request('cfserial'));
  // Guarantor Forms (user, 2026-11-03): their own table and form under this tab, no longer a step of the capture dialog.
  $g = $cfGEditing;
  $gOpen = $g || $cfErr === 'guarantor' || request()->filled('cfgform');
  $gv = fn ($k) => old($k, $g ? ($g->{$k} instanceof \Carbon\Carbon ? $g->{$k}->format('Y-m-d') : $g->{$k}) : null);
  $gchk = fn ($k) => (bool) old($k, $g ? $g->{$k} : false);
  $gFor = old('guarantor_for', $g?->guarantor_for ?: 'owner');
  $gId = old('guarantor_id_type', $g?->guarantor_id_type);
  $gPre = (string) old('form_id', request('cfgform'));
  $cfKeep = array_filter(['cflayout' => $cfLayout ?: null]);
@endphp

@if (session('print_ids'))
  <div class="comp-type-note" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <span><i class="fas fa-print"></i> The blank forms are ready to print, one page per serial.</span>
    <a class="btn btn-success btn-sm" target="_blank" href="{{ route($cfRoute . '.print', ['ids' => implode(',', session('print_ids'))]) }}"><i class="fas fa-print"></i> Print the blank forms</a>
  </div>
@endif

<div class="kpi-grid">
  <div class="kpi-card"><div class="kpi-label">Serials Issued</div><div class="kpi-value">{{ number_format($cfStats['total']) }}</div></div>
  <div class="kpi-card"><div class="kpi-label">Awaiting Capture</div><div class="kpi-value">{{ number_format($cfStats['open']) }}</div></div>
  <div class="kpi-card"><div class="kpi-label">Captured</div><div class="kpi-value">{{ number_format($cfStats['captured']) }}</div></div>
  <div class="kpi-card"><div class="kpi-label">Voided</div><div class="kpi-value">{{ number_format($cfStats['voided']) }}</div></div>
</div>

<div class="page-header" style="margin-bottom:10px;">
  <div style="font-size:13px;color:var(--gray-500);">Next serial: <strong style="font-family:monospace;">{{ F::formatSerial($cfNextSerial) }}</strong> · numbers are never reused.</div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <button type="button" class="btn btn-secondary" onclick="document.getElementById('cfIssue').classList.add('open')"><i class="fas fa-hashtag"></i> Issue Serials</button>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('cfCapture').classList.add('open')"><i class="fas fa-keyboard"></i> Capture Form</button>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('cfGuarantor').classList.add('open')"><i class="fas fa-user-shield"></i> Capture Guarantor Form</button>
  </div>
</div>

{{-- Issue serials for a layout, as a modal. --}}
<form method="POST" id="cfIssue" action="{{ route($cfRoute . '.generate') }}" class="farmer-entry-form {{ $issueOpen ? 'open' : '' }}"
      data-sv-modal="Issue Serials" data-sv-modal-size="sm">
  @csrf
  <input type="hidden" name="_form" value="issue" />
  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-map"></i><span>Layout</span></div>
    <div class="form-group span-2">
      <label>Layout <span class="required">*</span></label>
      <select name="survey_project_id" id="cfLayout" required class="sv-searchable" onchange="cfLayoutOther()">
        <option value="">Select the layout…</option>
        @foreach ($cfProjects as $p)
          <option value="{{ $p->id }}" @selected((string) old('survey_project_id') === (string) $p->id)>{{ $p->name }}</option>
        @endforeach
        <option value="other" @selected(old('survey_project_id') === 'other')>Other (specify)</option>
      </select>
    </div>
    <div class="form-group">
      <label>Number of Forms <span class="required">*</span></label>
      <input type="number" name="count" min="1" max="{{ CF::MAX_BATCH }}" value="{{ old('count', 1) }}" required />
    </div>
    <div class="form-group full" id="cfLayoutOtherBox" style="{{ old('survey_project_id') === 'other' ? '' : 'display:none;' }}">
      <label>Layout Name <span class="required">*</span></label>
      <input type="text" name="layout_other" id="cfLayoutOtherInput" value="{{ old('layout_other') }}" maxlength="255" placeholder="e.g. BUK/Zakara Layout" {{ old('survey_project_id') === 'other' ? 'required' : 'disabled' }} />
      <p class="helper-text">Printed on the form as the layout heading.</p>
    </div>
  </div>
  <div class="form-actions">
    <div class="left"><button class="btn btn-secondary" type="button" onclick="document.getElementById('cfIssue').classList.remove('open')"><i class="fas fa-times"></i> Close</button></div>
    <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-hashtag"></i> Issue Serials</button></div>
  </div>
</form>

{{-- Capture / edit a filled form, as a modal. --}}
<form method="POST" id="cfCapture" enctype="multipart/form-data" onsubmit="return cfPassportReady()"
      action="{{ $e ? route($cfRoute . '.update', $e) : route($cfRoute . '.capture') }}"
      class="farmer-entry-form {{ $captureOpen ? 'open' : '' }}" data-sv-modal="{{ $e ? 'Edit Confirmation Form ' . $e->serial : 'Capture Confirmation Form' }}"
      data-sv-modal-size="lg" @if ($e) data-sv-modal-close="{{ $cfUrl() }}" @endif>
  @csrf
  <input type="hidden" name="_form" value="capture" />

  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-file-alt"></i><span>Form</span></div>
    <div class="form-group">
      <label>Serial No. <span class="required">*</span></label>
      @if ($e)
        <input type="text" value="{{ $e->serial }}" readonly style="background:var(--gray-100);font-family:monospace;font-weight:700;" />
      @else
        <select name="form_id" id="cfSerial" required class="sv-searchable" onchange="cfSerialPicked()">
          <option value="">Select the serial on the form…</option>
          @foreach ($cfOpen as $o)
            <option value="{{ $o->id }}" data-layout="{{ $o->layout_name }}" @selected($preSerial === (string) $o->id)>{{ $o->serial }} · {{ $o->layout_name ?: 'No layout' }}</option>
          @endforeach
        </select>
        <p class="helper-text">{{ number_format($cfOpen->count()) }} awaiting capture.@if ($cfOpen->isEmpty()) Issue serials first.@endif</p>
      @endif
    </div>
    <div class="form-group span-2">
      <label>Layout</label>
      <input type="text" id="cfSerialLayout" value="{{ $e?->layout_name }}" readonly placeholder="Comes from the serial" style="background:var(--gray-100);" />
    </div>
  </div>

  {{-- Land owner, with the passport photo in the first column. --}}
  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-user"></i><span>Land Owner</span></div>
    <div class="form-group" style="grid-row:span 3;">
      <label>Passport Photo</label>
      <div style="width:110px;height:138px;border:2px solid var(--gray-300);border-radius:10px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:var(--gray-100);color:var(--gray-500);font-size:12px;margin-bottom:10px;">
        <img id="cfPassportImg" alt="Passport" src="{{ $e && $e->passport_path ? asset('storage/' . $e->passport_path) : '' }}" style="width:100%;height:100%;object-fit:cover;{{ $e && $e->passport_path ? '' : 'display:none;' }}">
        <span id="cfPassportEmpty" style="{{ $e && $e->passport_path ? 'display:none;' : '' }}">No photo</span>
      </div>
      <input type="file" name="passport" id="cfPassport" accept="image/jpeg,image/png" onchange="cfPassportPicked(this)" />
      <p class="helper-text" id="cfPassportNote">JPG or PNG, 2MB or smaller. One clear face; a scan of the whole cover is cropped to the face.</p>
    </div>
    <div class="form-group span-2"><label>Land Owner's Name <span class="required">*</span></label><input type="text" name="land_owner_name" value="{{ $v('land_owner_name') }}" maxlength="255" required /></div>
    <div class="form-group"><label>Section</label><input type="text" name="section" value="{{ $v('section') }}" maxlength="100" /></div>
    <div class="form-group"><label>ID Number</label><input type="text" name="id_number" value="{{ $v('id_number') }}" maxlength="100" /></div>
    <div class="form-group"><label>NIN Number</label><input type="text" name="nin" value="{{ $v('nin') }}" inputmode="numeric" maxlength="11" placeholder="11 digits" /></div>
    <div class="form-group"><label>GSM</label><input type="tel" name="gsm" value="{{ $v('gsm') }}" maxlength="20" placeholder="e.g. 08031234567" /></div>
    <div class="form-group"><label>Location</label><input type="text" name="location" value="{{ $v('location') }}" maxlength="255" /></div>
    <div class="form-group span-2"><label>Address</label><input type="text" name="address" value="{{ $v('address') }}" maxlength="500" /></div>
  </div>

  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-briefcase"></i><span>Vendor / Agent</span></div>
    <div class="form-group span-2"><label>Vendor / Agent Name</label><input type="text" name="vendor_name" value="{{ $v('vendor_name') }}" maxlength="255" /></div>
    <div class="form-group"><label>GSM</label><input type="tel" name="vendor_gsm" value="{{ $v('vendor_gsm') }}" maxlength="20" placeholder="e.g. 08031234567" /></div>
    <div class="form-group"><label>Address</label><input type="text" name="vendor_address" value="{{ $v('vendor_address') }}" maxlength="500" /></div>
    <div class="form-group">
      <label>Signature</label>
      <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-top:8px;"><input type="checkbox" name="vendor_signed" value="1" style="width:auto;" @checked($chk('vendor_signed'))> Signed on the form</label>
    </div>
    <div class="form-group"><label>Date</label><input type="date" name="vendor_date" value="{{ $v('vendor_date') }}" /></div>
  </div>

  {{-- Name, signature and date for each signatory, one row each. --}}
  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-signature"></i><span>Signatories</span></div>
    @foreach ([['chairman', "Committee Chairman's Name"], ['village_head', 'Ward / Village Head Name'], ['compensation_head', 'Head of Compensations Name']] as [$p, $lbl])
      <div class="form-group"><label>{{ $lbl }}</label><input type="text" name="{{ $p }}_name" value="{{ $v($p . '_name') }}" maxlength="255" /></div>
      <div class="form-group">
        <label>Signature</label>
        <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-top:8px;"><input type="checkbox" name="{{ $p }}_signed" value="1" style="width:auto;" @checked($chk($p . '_signed'))> Signed on the form</label>
      </div>
      <div class="form-group"><label>Date</label><input type="date" name="{{ $p }}_date" value="{{ $v($p . '_date') }}" /></div>
    @endforeach
  </div>

  <div class="form-actions">
    <div class="left">
      @if ($e)
        <a class="btn btn-secondary" href="{{ $cfUrl() }}"><i class="fas fa-times"></i> Cancel</a>
      @else
        <button class="btn btn-secondary" type="button" onclick="document.getElementById('cfCapture').classList.remove('open')"><i class="fas fa-times"></i> Close</button>
      @endif
    </div>
    <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $e ? 'Save Changes' : 'Save Form' }}</button></div>
  </div>
</form>


{{-- Capture / edit a Guarantor Form (user, 2026-11-03), as a modal: one per captured Confirmation Form. --}}
<form method="POST" id="cfGuarantor" enctype="multipart/form-data" onsubmit="return cfPassportReady()"
      action="{{ route($cfRoute . '.guarantor.save') }}"
      class="farmer-entry-form {{ $gOpen ? 'open' : '' }}" data-sv-modal="{{ $g ? 'Edit Guarantor Form · Confirmation Form ' . $g->serial : 'Capture Guarantor Form' }}"
      data-sv-modal-size="lg" @if ($g) data-sv-modal-close="{{ $cfUrl(['cftab' => 'guarantors']) }}" @endif>
  @csrf
  <input type="hidden" name="_form" value="guarantor" />
  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-file-signature"></i><span>Confirmation Form</span></div>
    <div class="form-group span-2">
      <label>Confirmation Form <span class="required">*</span></label>
      @if ($g)
        <input type="hidden" name="form_id" id="cfGForm" value="{{ $g->id }}" data-layout="{{ $g->layout_name }}" data-owner="{{ $g->land_owner_name }}"
               data-address="{{ $g->address }}" data-gsm="{{ $g->gsm }}" data-nin="{{ $g->nin }}" data-vendor="{{ $g->vendor_name }}"
               data-vaddress="{{ $g->vendor_address }}" data-vgsm="{{ $g->vendor_gsm }}" data-chairman="{{ $g->chairman_name }}" />
        <input type="text" value="{{ $g->serial }} · {{ $g->land_owner_name }}" readonly style="background:var(--gray-100);font-weight:700;" />
      @else
        <select name="form_id" id="cfGForm" required class="sv-searchable" onchange="cfApplicant()">
          <option value="">Select the Confirmation Form…</option>
          @foreach ($cfGChoices as $o)
            <option value="{{ $o->id }}" data-layout="{{ $o->layout_name }}" data-owner="{{ $o->land_owner_name }}" data-address="{{ $o->address }}"
                    data-gsm="{{ $o->gsm }}" data-nin="{{ $o->nin }}" data-vendor="{{ $o->vendor_name }}" data-vaddress="{{ $o->vendor_address }}"
                    data-vgsm="{{ $o->vendor_gsm }}" data-chairman="{{ $o->chairman_name }}" @selected($gPre === (string) $o->id)>{{ $o->serial }} · {{ $o->land_owner_name }} · {{ $o->layout_name ?: 'No layout' }}</option>
          @endforeach
        </select>
        <p class="helper-text">{{ number_format($cfGChoices->count()) }} captured form(s) without a Guarantor Form.@if ($cfGChoices->isEmpty()) Capture the Confirmation Form first.@endif</p>
      @endif
    </div>
    <div class="form-group">
      <label>Layout</label>
      <input type="text" id="cfGLayout" value="{{ $g?->layout_name }}" readonly placeholder="Comes from the form" style="background:var(--gray-100);" />
    </div>
  </div>

  <div class="comp-type-note" style="margin-bottom:16px;">
    <i class="fas fa-info-circle"></i>
    The Guarantor Form is signed and filled only by the <strong>Committee Chairman</strong>, with the Chairman's passport photograph and a copy of the Chairman's identity document.
    Pick the Confirmation Form it belongs to: the applicant's details come from it.
  </div>
  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-user-shield"></i><span>Guarantor (Committee Chairman)</span></div>
    <div class="form-group" style="grid-row:span 4;">
      <label>Passport Photo</label>
      <div style="width:110px;height:138px;border:2px solid var(--gray-300);border-radius:10px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:var(--gray-100);color:var(--gray-500);font-size:12px;margin-bottom:10px;">
        <img id="cfGPassportImg" alt="Passport" src="{{ $g && $g->guarantor_passport_path ? asset('storage/' . $g->guarantor_passport_path) : '' }}" style="width:100%;height:100%;object-fit:cover;{{ $g && $g->guarantor_passport_path ? '' : 'display:none;' }}">
        <span id="cfGPassportEmpty" style="{{ $g && $g->guarantor_passport_path ? 'display:none;' : '' }}">No photo</span>
      </div>
      <input type="file" name="guarantor_passport" id="cfGPassport" data-prefix="cfGPassport" data-who="Committee Chairman’s" accept="image/jpeg,image/png" onchange="cfPassportPicked(this)" />
      <p class="helper-text" id="cfGPassportNote">JPG or PNG, 2MB or smaller. One clear face.</p>
    </div>
    <div class="form-group span-2"><label>Full Name of Committee Chairman <span class="required">*</span></label><input type="text" name="guarantor_name" id="cfGName" value="{{ $gv('guarantor_name') }}" maxlength="255" required /></div>
    <div class="form-group"><label>Occupation</label><input type="text" name="guarantor_occupation" value="{{ $gv('guarantor_occupation') }}" maxlength="150" /></div>
    <div class="form-group"><label>Rank / Position</label><input type="text" name="guarantor_rank" value="{{ old('guarantor_rank', $g?->guarantor_rank ?: F::DEFAULT_RANK) }}" maxlength="150" /></div>
    <div class="form-group"><label>Phone Number(s)</label><input type="tel" name="guarantor_phone" value="{{ $gv('guarantor_phone') }}" maxlength="60" placeholder="e.g. 08031234567" /></div>
    <div class="form-group"><label>Email (if available)</label><input type="email" name="guarantor_email" value="{{ $gv('guarantor_email') }}" maxlength="255" /></div>
    <div class="form-group span-2"><label>Home Address</label><input type="text" name="guarantor_home_address" value="{{ $gv('guarantor_home_address') }}" maxlength="500" /></div>
  </div>

  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-users"></i><span>Committee</span></div>
    <div class="form-group"><label>Committee</label><input type="text" name="guarantor_committee" value="{{ $gv('guarantor_committee') }}" maxlength="255" placeholder="… Committee" /></div>
    <div class="form-group">
      <label>Local Government Area</label>
      <select name="guarantor_lga" class="sv-searchable">
        <option value="">Select LGA…</option>
        @foreach ($cfLgas as $l)<option value="{{ $l }}" @selected(old('guarantor_lga', $g?->guarantor_lga) === $l)>{{ $l }}</option>@endforeach
      </select>
      <p class="helper-text">Kano State.</p>
    </div>
    <div class="form-group"><label>Office Address (Committee Secretariat)</label><input type="text" name="guarantor_office_address" value="{{ $gv('guarantor_office_address') }}" maxlength="500" /></div>
  </div>

  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-id-card"></i><span>Identity Document</span></div>
    <div class="form-group">
      <label>Identity Document</label>
      <select name="guarantor_id_type" id="cfGIdType" onchange="cfGIdOther()">
        <option value="">Select…</option>
        @foreach (F::ID_TYPES as $t)<option value="{{ $t }}" @selected($gId === $t)>{{ $t }}</option>@endforeach
        <option value="{{ F::OTHER }}" @selected($gId === F::OTHER)>Other</option>
      </select>
    </div>
    <div class="form-group" id="cfGIdOtherBox" @unless ($gId === F::OTHER) style="display:none;" @endunless>
      <label>Specify Document <span class="required">*</span></label>
      <input type="text" name="guarantor_id_type_other" id="cfGIdOtherInput" value="{{ $gv('guarantor_id_type_other') }}" maxlength="100" @if ($gId === F::OTHER) required @else disabled @endif />
    </div>
    <div class="form-group">
      <label>Copy of the Document</label>
      <input type="file" name="guarantor_id" accept="application/pdf,image/jpeg,image/png" />
      <p class="helper-text">Signed and endorsed by the Chairman. PDF, JPG or PNG, 5MB or smaller.@if ($g && $g->guarantor_id_path) <a href="{{ asset('storage/' . $g->guarantor_id_path) }}" target="_blank" rel="noopener">View the current copy</a>; a new file replaces it.@endif</p>
    </div>
    <div class="form-group"><label>BVN (optional)</label><input type="text" name="guarantor_bvn" value="{{ $gv('guarantor_bvn') }}" inputmode="numeric" maxlength="11" placeholder="11 digits" /></div>
  </div>

  {{-- The applicant: shown from step 1, not typed again. --}}
  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-user-check"></i><span>Applicant</span></div>
    <div class="form-group">
      <label>Stands as Guarantor for</label>
      <select name="guarantor_for" id="cfGFor" onchange="cfApplicant()">
        @foreach (F::GUARANTOR_FOR as $k => $lbl)<option value="{{ $k }}" @selected($gFor === $k)>{{ $lbl }}</option>@endforeach
      </select>
      <p class="helper-text">Details come from the Confirmation Form.</p>
    </div>
    <div class="form-group span-2"><label>Applicant's Full Name</label><input type="text" id="cfGAppName" readonly style="background:var(--gray-100);" /></div>
    <div class="form-group span-2"><label>Address</label><input type="text" id="cfGAppAddress" readonly style="background:var(--gray-100);" /></div>
    <div class="form-group"><label>Phone Number</label><input type="text" id="cfGAppPhone" readonly style="background:var(--gray-100);" /></div>
    <div class="form-group"><label>National Identification Number (NIN)</label><input type="text" id="cfGAppNin" readonly style="background:var(--gray-100);" /></div>
  </div>

  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-signature"></i><span>Signature</span></div>
    <div class="form-group">
      <label>Committee Chairman's Signature</label>
      <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-top:8px;"><input type="checkbox" name="guarantor_signed" value="1" style="width:auto;" @checked($gchk('guarantor_signed'))> Signed on the form</label>
    </div>
    <div class="form-group"><label>Date</label><input type="date" name="guarantor_date" max="{{ now()->toDateString() }}" value="{{ $gv('guarantor_date') }}" /></div>
    <div class="form-group">
      <label>Stamp of Committee Secretariat</label>
      <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-top:8px;"><input type="checkbox" name="guarantor_stamped" value="1" style="width:auto;" @checked($gchk('guarantor_stamped'))> Stamped on the form</label>
    </div>
  </div>

  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-stamp"></i><span>For Official Use Only</span></div>
    <div class="form-group"><label>Registration Reference No.</label><input type="text" name="guarantor_reg_ref" value="{{ $gv('guarantor_reg_ref') }}" maxlength="100" /></div>
    <div class="form-group"><label>Verified by MLPP</label><input type="text" name="guarantor_verified_by" value="{{ $gv('guarantor_verified_by') }}" maxlength="255" /></div>
    <div class="form-group"><label>Date</label><input type="date" name="guarantor_verified_date" max="{{ now()->toDateString() }}" value="{{ $gv('guarantor_verified_date') }}" /></div>
  </div>

  <div class="form-actions">
    <div class="left">
      @if ($g)
        <a class="btn btn-secondary" href="{{ $cfUrl(['cftab' => 'guarantors']) }}"><i class="fas fa-times"></i> Cancel</a>
      @else
        <button class="btn btn-secondary" type="button" onclick="document.getElementById('cfGuarantor').classList.remove('open')"><i class="fas fa-times"></i> Close</button>
      @endif
    </div>
    <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $g ? 'Save Changes' : 'Save Guarantor Form' }}</button></div>
  </div>
</form>

{{-- Void a serial (one dialog; the serial is set when it opens). --}}
<form method="POST" id="cfVoid" action="" class="farmer-entry-form" data-sv-modal="Void Serial" data-sv-modal-size="sm">
  @csrf
  <div class="form-grid">
    <div class="form-group full">
      <p style="margin:0 0 6px;">Void serial <strong id="cfVoidSerial" style="font-family:monospace;"></strong>. For a spoilt or lost form: the number is kept as voided and never reissued.</p>
      <label>Reason <span class="required">*</span></label>
      <textarea name="void_reason" rows="3" maxlength="500" required placeholder="e.g. Form torn while printing"></textarea>
    </div>
  </div>
  <div class="form-actions">
    <div class="left"><button class="btn btn-secondary" type="button" onclick="document.getElementById('cfVoid').classList.remove('open')"><i class="fas fa-times"></i> Close</button></div>
    <div class="right"><button class="btn btn-danger" type="submit"><i class="fas fa-ban"></i> Void Serial</button></div>
  </div>
</form>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;" role="tablist">
  @foreach (CF::TABS as $k => $lbl)
    <a href="{{ $cfUrl(['cftab' => $k] + $cfKeep) }}" role="tab" aria-selected="{{ $cfTab === $k ? 'true' : 'false' }}"
       class="btn {{ $cfTab === $k ? 'btn-primary' : 'btn-outline' }} btn-sm"><i class="fas {{ ['forms' => 'fa-file-signature', 'serials' => 'fa-hashtag', 'guarantors' => 'fa-user-shield'][$k] }}"></i> {{ $lbl }}</a>
  @endforeach
</div>

<form method="GET" action="{{ route('survey-module.gkn.plot-showing') }}" class="table-toolbar">
  <input type="hidden" name="tab" value="confirmation" />
  <input type="hidden" name="cftab" value="{{ $cfTab }}" />
  <div class="left" style="flex-wrap:wrap;">
    @if ($cfTab === 'forms')
      <input type="text" name="cfq" value="{{ $cfQ }}" placeholder="Search serial, owner, ID, NIN, GSM, location, vendor…" style="{{ $cfField }}width:280px;" />
    @elseif ($cfTab === 'guarantors')
      <input type="text" name="cfq" value="{{ $cfQ }}" placeholder="Search chairman, committee, LGA, phone, applicant, serial…" style="{{ $cfField }}width:280px;" />
    @endif
    <select name="cflayout" style="{{ $cfField }}min-width:200px;">
      <option value="">All layouts</option>
      @foreach ($cfLayouts as $l)<option value="{{ $l }}" @selected($cfLayout === $l)>{{ $l }}</option>@endforeach
    </select>
    @if ($cfTab === 'serials')
      <select name="cfstatus" style="{{ $cfField }}">
        <option value="">All statuses</option>
        @foreach (F::STATUSES as $s)<option value="{{ $s }}" @selected($cfStatus === $s)>{{ $s }}</option>@endforeach
      </select>
    @endif
    <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
    @if ($cfQ !== '' || $cfLayout !== '' || $cfStatus)<a class="btn btn-outline btn-sm" href="{{ $cfUrl(['cftab' => $cfTab]) }}">Clear</a>@endif
  </div>
</form>

@if ($cfTab === 'guarantors')
<div class="table-wrapper">
  <div class="table-scroll">
    <table>
      <thead>
        <tr><th>S/N</th><th>Photo</th><th>Committee Chairman</th><th>Rank / Position</th><th>Committee</th><th>LGA</th><th>Phone</th><th>Applicant</th><th>Confirmation Form</th><th>Signed</th><th>Date</th><th>Reg. Ref.</th><th></th></tr>
      </thead>
      <tbody>
        @forelse ($cfGuarantors as $row)
          @php $app = $row->guarantorApplicant(); @endphp
          <tr>
            <td>{{ $cfGuarantors->firstItem() + $loop->index }}</td>
            <td>
              @if ($row->guarantor_passport_path)
                <img src="{{ asset('storage/' . $row->guarantor_passport_path) }}" alt="Passport" loading="lazy" style="width:34px;height:42px;object-fit:cover;border-radius:4px;border:1px solid var(--gray-300);">
              @else — @endif
            </td>
            <td><strong>{{ $row->guarantor_name }}</strong></td>
            <td>{{ $row->guarantor_rank ?: '—' }}</td>
            <td>{{ $row->guarantor_committee ?: '—' }}</td>
            <td>{{ $row->guarantor_lga ?: '—' }}</td>
            <td style="white-space:nowrap;">{{ $row->guarantor_phone ?: '—' }}</td>
            <td>{{ $app['name'] ?: '—' }}<div style="font-size:11px;color:var(--gray-500);">{{ \App\Models\Survey\SurveyConfirmationForm::GUARANTOR_FOR[$row->guarantor_for ?: 'owner'] }}</div></td>
            <td><strong style="font-family:monospace;">{{ $row->serial }}</strong><div style="font-size:11px;color:var(--gray-500);">{{ $row->layout_name }}</div></td>
            <td>{{ $row->guarantor_signed ? 'Yes' : 'No' }}{{ $row->guarantor_stamped ? ' · Stamped' : '' }}</td>
            <td style="white-space:nowrap;">{{ $row->guarantor_date?->format('d M Y') ?: '—' }}</td>
            <td>{{ $row->guarantor_reg_ref ?: '—' }}</td>
            <td>
              <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                  <i data-lucide="more-vertical" class="w-5 h-5"></i>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                  <a href="{{ route($cfRoute . '.guarantor', $row) }}" target="_blank" class="{{ $cfItem }}"><i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> Print Guarantor Form</a>
                  <a href="{{ $cfUrl(['cftab' => 'guarantors', 'cfgedit' => $row->id]) }}" class="{{ $cfItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                  <a href="{{ route($cfRoute . '.print', ['ids' => $row->id]) }}" target="_blank" class="{{ $cfItem }}"><i data-lucide="file-text" class="w-4 h-4 text-gray-500"></i> View Confirmation Form</a>
                  <form method="POST" action="{{ route($cfRoute . '.guarantor.remove', $row) }}" onsubmit="return confirm({{ json_encode('Remove the Guarantor Form from Confirmation Form ' . $row->serial . '?') }});">
                    @csrf
                    <button type="submit" class="{{ $cfItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Remove</button>
                  </form>
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="13" style="text-align:center;padding:28px;color:var(--gray-500);">
            <i class="fas fa-user-shield" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
            {{ $cfQ !== '' || $cfLayout !== '' ? 'No Guarantor Form matches.' : 'No Guarantor Form captured yet. Use Capture Guarantor Form.' }}
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="table-footer">
    <span>Showing {{ $cfGuarantors->firstItem() ?? 0 }}–{{ $cfGuarantors->lastItem() ?? 0 }} of {{ number_format($cfGuarantors->total()) }} Guarantor Forms</span>
    <div class="pagination">{{ $cfGuarantors->links() }}</div>
  </div>
</div>
@elseif ($cfTab === 'forms')
<div class="table-wrapper">
  <div class="table-scroll">
    <table>
      <thead>
        <tr><th>S/N</th><th>Serial No.</th><th>Photo</th><th>Land Owner</th><th>Layout</th><th>Section</th><th>ID Number</th><th>Location</th><th>NIN</th><th>GSM</th><th>Vendor / Agent</th><th>Guarantor</th><th>Captured</th><th></th></tr>
      </thead>
      <tbody>
        @forelse ($cfForms as $row)
          <tr>
            <td>{{ $cfForms->firstItem() + $loop->index }}</td>
            <td><strong style="font-family:monospace;">{{ $row->serial }}</strong></td>
            <td>
              @if ($row->passport_path)
                <img src="{{ asset('storage/' . $row->passport_path) }}" alt="Passport" loading="lazy" style="width:34px;height:42px;object-fit:cover;border-radius:4px;border:1px solid var(--gray-300);">
              @else — @endif
            </td>
            <td><strong>{{ $row->land_owner_name }}</strong></td>
            <td>{{ $row->layout_name ?: '—' }}</td>
            <td>{{ $row->section ?: '—' }}</td>
            <td>{{ $row->id_number ?: '—' }}</td>
            <td>{{ $row->location ?: '—' }}</td>
            <td style="font-family:monospace;">{{ $row->nin ?: '—' }}</td>
            <td style="white-space:nowrap;">{{ $row->gsm ?: '—' }}</td>
            <td>{{ $row->vendor_name ?: '—' }}</td>
            <td>{{ $row->guarantor_name ?: '—' }}@if ($row->guarantor_name && $row->guarantor_lga)<div style="font-size:11px;color:var(--gray-500);">{{ $row->guarantor_lga }}</div>@endif</td>
            <td style="white-space:nowrap;">{{ $row->captured_at?->format('d M Y') }}<div style="font-size:11px;color:var(--gray-500);">{{ $cfUsers[$row->captured_by] ?? '' }}</div></td>
            <td>
              <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                  <i data-lucide="more-vertical" class="w-5 h-5"></i>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                  <a href="{{ route($cfRoute . '.print', ['ids' => $row->id]) }}" target="_blank" class="{{ $cfItem }}"><i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> View / Print Form</a>
                  <a href="{{ $cfUrl(['cfedit' => $row->id]) }}" class="{{ $cfItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                  @if ($row->guarantor_name)
                    <a href="{{ route($cfRoute . '.guarantor', $row) }}" target="_blank" class="{{ $cfItem }}"><i data-lucide="shield-check" class="w-4 h-4 text-gray-500"></i> Print Guarantor Form</a>
                    <a href="{{ $cfUrl(['cftab' => 'guarantors', 'cfgedit' => $row->id]) }}" class="{{ $cfItem }}"><i data-lucide="user-check" class="w-4 h-4 text-gray-500"></i> Edit Guarantor Form</a>
                  @else
                    <a href="{{ $cfUrl(['cftab' => 'guarantors', 'cfgform' => $row->id]) }}" class="{{ $cfItem }}"><i data-lucide="user-plus" class="w-4 h-4 text-gray-500"></i> Add Guarantor Form</a>
                  @endif
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="14" style="text-align:center;padding:28px;color:var(--gray-500);">
            <i class="fas fa-file-signature" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
            {{ $cfQ !== '' || $cfLayout !== '' ? 'No captured form matches.' : 'No form captured yet. Issue serials, print the blank forms, then use Capture Form.' }}
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="table-footer">
    <span>Showing {{ $cfForms->firstItem() ?? 0 }}–{{ $cfForms->lastItem() ?? 0 }} of {{ number_format($cfForms->total()) }} captured</span>
    <div class="pagination">{{ $cfForms->links() }}</div>
  </div>
</div>
@else
<div class="table-wrapper">
  <div class="table-scroll">
    <table>
      <thead><tr><th>S/N</th><th>Serial No.</th><th>Layout</th><th>Status</th><th>Issued</th><th>Printed</th><th>Land Owner</th><th></th></tr></thead>
      <tbody>
        @forelse ($cfSerials as $s)
          <tr>
            <td>{{ $cfSerials->firstItem() + $loop->index }}</td>
            <td><strong style="font-family:monospace;">{{ $s->serial }}</strong></td>
            <td>{{ $s->layout_name ?: '—' }}</td>
            <td><span class="status-badge {{ $cfBadge[$s->status] ?? '' }}"><span class="dot"></span>{{ $s->status }}</span>
              @if ($s->status === F::VOIDED && $s->void_reason)<div style="font-size:11px;color:var(--gray-500);margin-top:2px;">{{ $s->void_reason }}</div>@endif</td>
            <td style="white-space:nowrap;">{{ $s->created_at?->format('d M Y') }}</td>
            <td style="white-space:nowrap;">{{ $s->print_count ? $s->print_count . '× · ' . $s->printed_at?->format('d M Y') : '—' }}</td>
            <td>{{ $s->land_owner_name ?: '—' }}</td>
            <td>
              @if ($s->status !== F::VOIDED)
                <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                  <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                          @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                          class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                  </button>
                  <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                       class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                    <a href="{{ route($cfRoute . '.print', ['ids' => $s->id]) }}" target="_blank" class="{{ $cfItem }}">
                      <i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> {{ $s->isOpen() ? 'Print Blank Form' : 'View / Print Form' }}</a>
                    @if ($s->isOpen())
                      <button type="button" class="{{ $cfItem }}" @click="open = false; cfCaptureSerial({{ $s->id }})"><i data-lucide="keyboard" class="w-4 h-4 text-gray-500"></i> Capture</button>
                      <button type="button" class="{{ $cfItem }} text-red-600" @click="open = false; cfVoidOpen(@js(route($cfRoute . '.void', $s)), @js($s->serial))"><i data-lucide="ban" class="w-4 h-4"></i> Void Serial</button>
                    @else
                      @if ($s->guarantor_name)
                        <a href="{{ route($cfRoute . '.guarantor', $s) }}" target="_blank" class="{{ $cfItem }}"><i data-lucide="shield-check" class="w-4 h-4 text-gray-500"></i> Print Guarantor Form</a>
                      @endif
                      <a href="{{ $cfUrl(['cfedit' => $s->id, 'cftab' => 'serials']) }}" class="{{ $cfItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                    @endif
                  </div>
                </div>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
            <i class="fas fa-hashtag" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>No serial issued yet. Use <strong>Issue Serials</strong>.
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="table-footer">
    <span>Showing {{ $cfSerials->firstItem() ?? 0 }}–{{ $cfSerials->lastItem() ?? 0 }} of {{ number_format($cfSerials->total()) }} serials</span>
    <div class="pagination">{{ $cfSerials->links() }}</div>
  </div>
</div>
@endif

@push('styles')
<style>
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/passport-face-check.js') }}?v={{ filemtime(public_path('js/passport-face-check.js')) }}"></script>
<script>
  // Layout: "Other (specify)" reveals a required name.
  function cfLayoutOther() {
    var other = document.getElementById('cfLayout').value === 'other', box = document.getElementById('cfLayoutOtherBox'), input = document.getElementById('cfLayoutOtherInput');
    box.style.display = other ? '' : 'none'; input.disabled = !other; input.required = other;
  }
  // The serial picked shows its layout.
  function cfSerialPicked() {
    var sel = document.getElementById('cfSerial'); if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    document.getElementById('cfSerialLayout').value = opt && opt.value ? (opt.dataset.layout || '') : '';
  }
  // Capture from a serial's menu: the capture dialog opens on that serial, no page load.
  function cfCaptureSerial(id) {
    var sel = document.getElementById('cfSerial');
    if (sel) { sel.value = String(id); sel.dispatchEvent(new Event('change', { bubbles: true })); cfSerialPicked(); }
    document.getElementById('cfCapture').classList.add('open');
  }
  // The applicant (Land Owner, or Vendor / Agent) as captured on the picked Confirmation Form; its Committee
  // Chairman is offered as the guarantor when no name is typed yet.
  function cfApplicant() {
    var src = document.getElementById('cfGForm'), sel = document.getElementById('cfGFor'); if (!src || !sel) return;
    var d = (src.tagName === 'SELECT' ? (src.options[src.selectedIndex] || {}) : src).dataset || {};
    var vendor = sel.value === 'vendor';
    document.getElementById('cfGLayout').value = d.layout || '';
    document.getElementById('cfGAppName').value = (vendor ? d.vendor : d.owner) || '';
    document.getElementById('cfGAppAddress').value = (vendor ? d.vaddress : d.address) || '';
    document.getElementById('cfGAppPhone').value = (vendor ? d.vgsm : d.gsm) || '';
    document.getElementById('cfGAppNin').value = vendor ? '' : (d.nin || '');
    document.getElementById('cfGAppNin').placeholder = vendor ? 'Not on the form for a vendor / agent' : '';
    var g = document.getElementById('cfGName');
    if (g && !g.value.trim() && d.chairman) g.value = d.chairman;
  }
  // Identity document: Other reveals a required specify field.
  function cfGIdOther() {
    var other = document.getElementById('cfGIdType').value === 'Other', box = document.getElementById('cfGIdOtherBox'), input = document.getElementById('cfGIdOtherInput');
    box.style.display = other ? '' : 'none'; input.disabled = !other; input.required = other;
  }
  document.addEventListener('DOMContentLoaded', function () {
    cfApplicant();
    if (window.jQuery) jQuery('#cfGForm').on('change', cfApplicant);
    cfSerialPicked();
    if (window.jQuery) jQuery('#cfLayout').on('change', cfLayoutOther);
    if (window.jQuery) jQuery('#cfSerial').on('change', cfSerialPicked);
  });

  function cfVoidOpen(action, serial) {
    var f = document.getElementById('cfVoid');
    f.action = action; document.getElementById('cfVoidSerial').textContent = serial;
    f.classList.add('open');
  }

  // Passport: the commissioning face check (js/passport-face-check.js). A refused photo is cleared; a whole-cover
  // scan comes back cropped and replaces the chosen file. Let through if the detector cannot load.
  // The land owner's (cfPassport…) and the Committee Chairman's (cfGPassport…) photos share these, by data-prefix.
  function cfPx(input) { return (input && input.dataset.prefix) || 'cfPassport'; }
  function cfPassportNote(text, bad, input) { var n = document.getElementById(cfPx(input) + 'Note'); if (n) { n.textContent = text; n.style.color = bad ? 'var(--danger, #b91c1c)' : ''; } }
  function cfPassportRefuse(input, title, text) {
    input.value = ''; delete input.dataset.faceCheck; cfPassportNote(text, true, input);
    if (window.Swal) Swal.fire({ icon: 'error', title: title, text: text, confirmButtonColor: '#dc2626' }); else alert(text);
  }
  function cfPassportKeep(input, file, cropped, skipped) {
    if (file.size > 2 * 1024 * 1024) return cfPassportRefuse(input, 'File Too Large', 'The passport photo must be 2MB or smaller.');
    if (cropped) { try { var dt = new DataTransfer(); dt.items.add(file); input.files = dt.files; } catch (e) {} }
    input.dataset.faceCheck = skipped ? 'skipped' : 'ok';
    var img = document.getElementById(cfPx(input) + 'Img');
    img.src = URL.createObjectURL(file); img.style.display = ''; document.getElementById(cfPx(input) + 'Empty').style.display = 'none';
    cfPassportNote(cropped ? 'Cropped to the ' + (input.dataset.who || 'land owner’s') + ' face.' : (skipped ? 'Photo added (the face check could not run).' : 'One clear face found.'), false, input);
  }
  function cfPassportPicked(input) {
    var file = input.files && input.files[0];
    if (!file) { delete input.dataset.faceCheck; return; }
    if (!/^image\/(jpeg|png)$/.test(file.type)) return cfPassportRefuse(input, 'Passport', 'The passport must be a JPG or PNG photo.');
    if (!window.PassportFaceCheck) return cfPassportKeep(input, file, false, true);
    input.dataset.faceCheck = 'pending'; cfPassportNote('Checking the photo…', false, input);
    window.PassportFaceCheck.check(file).then(function (res) {
      if (input.files[0] !== file) return;
      if (!res.ok) return cfPassportRefuse(input, 'Not a passport photograph', res.message);
      cfPassportKeep(input, res.file, res.cropped, res.skipped);
    }).catch(function (e) { cfPassportRefuse(input, 'Passport', 'The photo could not be checked: ' + ((e && e.message) || 'unknown error') + '. Please choose it again.'); });
  }
  function cfPassportReady() {
    var wait = ['cfPassport', 'cfGPassport'].map(function (id) { return document.getElementById(id); })
      .find(function (i) { return i && i.dataset.faceCheck === 'pending'; });
    if (wait) { cfPassportNote('Wait for the photo check to finish, then save.', true, wait); return false; }
    return true;
  }
</script>
@endpush
