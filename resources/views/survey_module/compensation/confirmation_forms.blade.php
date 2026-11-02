@extends('survey_module.layouts.klaes')

@section('page-title', 'Confirmation Forms — KLAES')

{{-- Survey > Compensation > Confirmation Forms (user, 2026-11-02), from the new paper template. Serials are issued
     for a layout (a Survey project, or one typed under Other, e.g. BUK/Zakara Layout) with a blank printed form; the
     filled paper is captured against its serial. Not on the sidebar yet (user). --}}
@php
  use App\Http\Controllers\Survey\ConfirmationFormController as CF;
  use App\Models\Survey\SurveyConfirmationForm as F;

  $PageTitle = 'Confirmation Forms';
  $PageDescription = 'Issue serial numbers with blank forms for a layout, then capture each filled form against its serial.';
  $field = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $route = 'survey-module.compensation.confirmation-forms';
  $badge = [F::GENERATED => 'pending', F::PRINTED => 'pending', F::CAPTURED => 'active', F::VOIDED => 'rejected'];

  $e = $editing;
  $formErr = $errors->any() ? old('_form') : null;
  $captureOpen = $e || $formErr === 'capture' || request()->filled('serial') || request('capture');
  $issueOpen = $formErr === 'issue';
  $v = fn ($k) => old($k, $e ? ($e->{$k} instanceof \Carbon\Carbon ? $e->{$k}->format('Y-m-d') : $e->{$k}) : null);
  $chk = fn ($k) => (bool) old($k, $e ? $e->{$k} : false);
  $preSerial = (string) old('form_id', request('serial'));
  $keep = array_filter(['layout' => $layout ?: null]);
@endphp

@section('survey-content')
@include('survey_module.partials._flash')

@if (session('print_ids'))
  <div class="comp-type-note" style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <span><i class="fas fa-print"></i> The blank forms are ready to print, one page per serial.</span>
    <a class="btn btn-success btn-sm" target="_blank" href="{{ route($route . '.print', ['ids' => implode(',', session('print_ids'))]) }}"><i class="fas fa-print"></i> Print the blank forms</a>
  </div>
@endif

<div class="page-header">
  <div style="font-size:13px;color:var(--gray-500);">Next serial: <strong style="font-family:monospace;">{{ F::formatSerial($nextSerial) }}</strong> · numbers are never reused.</div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <button type="button" class="btn btn-secondary" onclick="document.getElementById('cfIssue').classList.add('open')"><i class="fas fa-hashtag"></i> Issue Serials</button>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('cfCapture').classList.add('open')"><i class="fas fa-keyboard"></i> Capture Form</button>
  </div>
</div>

{{-- Issue serials for a layout, as a modal. --}}
<form method="POST" id="cfIssue" action="{{ route($route . '.generate') }}" class="farmer-entry-form {{ $issueOpen ? 'open' : '' }}"
      data-sv-modal="Issue Serials" data-sv-modal-size="sm">
  @csrf
  <input type="hidden" name="_form" value="issue" />
  <div class="form-grid cols-3">
    <div class="sv-group"><i class="fas fa-map"></i><span>Layout</span></div>
    <div class="form-group span-2">
      <label>Layout <span class="required">*</span></label>
      <select name="survey_project_id" id="cfLayout" required class="sv-searchable" onchange="cfLayoutOther()">
        <option value="">Select the layout…</option>
        @foreach ($projects as $p)
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
      action="{{ $e ? route($route . '.update', $e) : route($route . '.capture') }}"
      class="farmer-entry-form {{ $captureOpen ? 'open' : '' }}" data-sv-modal="{{ $e ? 'Edit Confirmation Form ' . $e->serial : 'Capture Confirmation Form' }}"
      data-sv-modal-size="lg" @if ($e) data-sv-modal-close="{{ route($route) }}" @endif>
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
          @foreach ($open as $o)
            <option value="{{ $o->id }}" data-layout="{{ $o->layout_name }}" @selected($preSerial === (string) $o->id)>{{ $o->serial }} · {{ $o->layout_name ?: 'No layout' }}</option>
          @endforeach
        </select>
        <p class="helper-text">{{ number_format($open->count()) }} awaiting capture.@if ($open->isEmpty()) Issue serials first.@endif</p>
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
        <a class="btn btn-secondary" href="{{ route($route) }}"><i class="fas fa-times"></i> Cancel</a>
      @else
        <button class="btn btn-secondary" type="button" onclick="document.getElementById('cfCapture').classList.remove('open')"><i class="fas fa-times"></i> Close</button>
      @endif
    </div>
    <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $e ? 'Save Changes' : 'Save Form' }}</button></div>
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

<div class="kpi-grid">
  <div class="kpi-card"><div class="kpi-label">Serials Issued</div><div class="kpi-value">{{ number_format($stats['total']) }}</div></div>
  <div class="kpi-card"><div class="kpi-label">Awaiting Capture</div><div class="kpi-value">{{ number_format($stats['open']) }}</div></div>
  <div class="kpi-card"><div class="kpi-label">Captured</div><div class="kpi-value">{{ number_format($stats['captured']) }}</div></div>
  <div class="kpi-card"><div class="kpi-label">Voided</div><div class="kpi-value">{{ number_format($stats['voided']) }}</div></div>
</div>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;" role="tablist">
  @foreach (CF::TABS as $k => $lbl)
    <a href="{{ route($route, array_merge(['tab' => $k], $keep)) }}" role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}"
       class="btn {{ $tab === $k ? 'btn-primary' : 'btn-outline' }} btn-sm"><i class="fas {{ $k === 'forms' ? 'fa-file-signature' : 'fa-hashtag' }}"></i> {{ $lbl }}</a>
  @endforeach
</div>

<form method="GET" class="table-toolbar">
  <input type="hidden" name="tab" value="{{ $tab }}" />
  <div class="left" style="flex-wrap:wrap;">
    @if ($tab === 'forms')
      <input type="text" name="q" value="{{ $q }}" placeholder="Search serial, owner, ID, NIN, GSM, location, vendor…" style="{{ $field }}width:280px;" />
    @endif
    <select name="layout" style="{{ $field }}min-width:200px;">
      <option value="">All layouts</option>
      @foreach ($layouts as $l)<option value="{{ $l }}" @selected($layout === $l)>{{ $l }}</option>@endforeach
    </select>
    @if ($tab === 'serials')
      <select name="status" style="{{ $field }}">
        <option value="">All statuses</option>
        @foreach (F::STATUSES as $s)<option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>@endforeach
      </select>
    @endif
    <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
    @if ($q !== '' || $layout !== '' || $status)<a class="btn btn-outline btn-sm" href="{{ route($route, ['tab' => $tab]) }}">Clear</a>@endif
  </div>
</form>

@if ($tab === 'forms')
<div class="table-wrapper">
  <div class="table-scroll">
    <table>
      <thead>
        <tr><th>S/N</th><th>Serial No.</th><th>Photo</th><th>Land Owner</th><th>Layout</th><th>Section</th><th>ID Number</th><th>Location</th><th>NIN</th><th>GSM</th><th>Vendor / Agent</th><th>Captured</th><th></th></tr>
      </thead>
      <tbody>
        @forelse ($forms as $row)
          <tr>
            <td>{{ $forms->firstItem() + $loop->index }}</td>
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
            <td style="white-space:nowrap;">{{ $row->captured_at?->format('d M Y') }}<div style="font-size:11px;color:var(--gray-500);">{{ $users[$row->captured_by] ?? '' }}</div></td>
            <td>
              <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                  <i data-lucide="more-vertical" class="w-5 h-5"></i>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                  <a href="{{ route($route . '.print', ['ids' => $row->id]) }}" target="_blank" class="{{ $svItem }}"><i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> View / Print Form</a>
                  <a href="{{ route($route, ['edit' => $row->id]) }}" class="{{ $svItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="13" style="text-align:center;padding:28px;color:var(--gray-500);">
            <i class="fas fa-file-signature" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
            {{ $q !== '' || $layout !== '' ? 'No captured form matches.' : 'No form captured yet. Issue serials, print the blank forms, then use Capture Form.' }}
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="table-footer">
    <span>Showing {{ $forms->firstItem() ?? 0 }}–{{ $forms->lastItem() ?? 0 }} of {{ number_format($forms->total()) }} captured</span>
    <div class="pagination">{{ $forms->links() }}</div>
  </div>
</div>
@else
<div class="table-wrapper">
  <div class="table-scroll">
    <table>
      <thead><tr><th>S/N</th><th>Serial No.</th><th>Layout</th><th>Status</th><th>Issued</th><th>Printed</th><th>Land Owner</th><th></th></tr></thead>
      <tbody>
        @forelse ($serials as $s)
          <tr>
            <td>{{ $serials->firstItem() + $loop->index }}</td>
            <td><strong style="font-family:monospace;">{{ $s->serial }}</strong></td>
            <td>{{ $s->layout_name ?: '—' }}</td>
            <td><span class="status-badge {{ $badge[$s->status] ?? '' }}"><span class="dot"></span>{{ $s->status }}</span>
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
                    <a href="{{ route($route . '.print', ['ids' => $s->id]) }}" target="_blank" class="{{ $svItem }}">
                      <i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> {{ $s->isOpen() ? 'Print Blank Form' : 'View / Print Form' }}</a>
                    @if ($s->isOpen())
                      <a href="{{ route($route, ['serial' => $s->id]) }}" class="{{ $svItem }}"><i data-lucide="keyboard" class="w-4 h-4 text-gray-500"></i> Capture</a>
                      <button type="button" class="{{ $svItem }} text-red-600" @click="open = false; cfVoidOpen(@js(route($route . '.void', $s)), @js($s->serial))"><i data-lucide="ban" class="w-4 h-4"></i> Void Serial</button>
                    @else
                      <a href="{{ route($route, ['edit' => $s->id]) }}" class="{{ $svItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
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
    <span>Showing {{ $serials->firstItem() ?? 0 }}–{{ $serials->lastItem() ?? 0 }} of {{ number_format($serials->total()) }} serials</span>
    <div class="pagination">{{ $serials->links() }}</div>
  </div>
</div>
@endif
@endsection

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
  document.addEventListener('DOMContentLoaded', function () {
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
  function cfPassportNote(text, bad) { var n = document.getElementById('cfPassportNote'); if (n) { n.textContent = text; n.style.color = bad ? 'var(--danger, #b91c1c)' : ''; } }
  function cfPassportRefuse(input, title, text) {
    input.value = ''; delete input.dataset.faceCheck; cfPassportNote(text, true);
    if (window.Swal) Swal.fire({ icon: 'error', title: title, text: text, confirmButtonColor: '#dc2626' }); else alert(text);
  }
  function cfPassportKeep(input, file, cropped, skipped) {
    if (file.size > 2 * 1024 * 1024) return cfPassportRefuse(input, 'File Too Large', 'The passport photo must be 2MB or smaller.');
    if (cropped) { try { var dt = new DataTransfer(); dt.items.add(file); input.files = dt.files; } catch (e) {} }
    input.dataset.faceCheck = skipped ? 'skipped' : 'ok';
    var img = document.getElementById('cfPassportImg');
    img.src = URL.createObjectURL(file); img.style.display = ''; document.getElementById('cfPassportEmpty').style.display = 'none';
    cfPassportNote(cropped ? 'Cropped to the land owner’s face.' : (skipped ? 'Photo added (the face check could not run).' : 'One clear face found.'));
  }
  function cfPassportPicked(input) {
    var file = input.files && input.files[0];
    if (!file) { delete input.dataset.faceCheck; return; }
    if (!/^image\/(jpeg|png)$/.test(file.type)) return cfPassportRefuse(input, 'Passport', 'The passport must be a JPG or PNG photo.');
    if (!window.PassportFaceCheck) return cfPassportKeep(input, file, false, true);
    input.dataset.faceCheck = 'pending'; cfPassportNote('Checking the photo…');
    window.PassportFaceCheck.check(file).then(function (res) {
      if (input.files[0] !== file) return;
      if (!res.ok) return cfPassportRefuse(input, 'Not a passport photograph', res.message);
      cfPassportKeep(input, res.file, res.cropped, res.skipped);
    }).catch(function (e) { cfPassportRefuse(input, 'Passport', 'The photo could not be checked: ' + ((e && e.message) || 'unknown error') + '. Please choose it again.'); });
  }
  function cfPassportReady() {
    var i = document.getElementById('cfPassport');
    if (i && i.dataset.faceCheck === 'pending') { cfPassportNote('Wait for the photo check to finish, then save.', true); return false; }
    return true;
  }
</script>
@endpush
