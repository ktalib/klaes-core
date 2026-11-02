<!DOCTYPE html>
{{-- Survey Confirmation Form, the new paper template (user, 2026-11-02): one A4 page per serial, headed with the
     form's layout (e.g. BUK/ZAKARA LAYOUT); blank for an open serial, filled in for a captured one. --}}
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Confirmation Form{{ $forms->count() === 1 ? ' ' . $forms->first()->serial : 's' }}</title>
<style>
  @page { size: A4 portrait; margin: 14mm 16mm; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #e5e7eb; font-family: Calibri, "Segoe UI", Arial, sans-serif; color: #111; }
  .bar { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: 12px;
         background: #fff; padding: 10px 18px; border-bottom: 1px solid #d1d5db; font-size: 14px; }
  .bar button { background: #1d4ed8; color: #fff; border: 0; border-radius: 6px; padding: 8px 16px; font-weight: 700; cursor: pointer; }
  .page { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 14mm 16mm; position: relative; box-shadow: 0 2px 10px rgba(0,0,0,.12); }
  .photo { position: absolute; top: 14mm; right: 16mm; width: 34mm; height: 42mm; border: 2px solid #111; border-radius: 14px; overflow: hidden; }
  .photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .head { text-align: center; margin: 8mm 40mm 0 0; font-weight: 700; line-height: 1.35; }
  .head .l1 { font-size: 17pt; }
  .head .l2, .head .l3 { font-size: 16pt; }
  .head .l4 { font-size: 15pt; text-decoration: underline; text-underline-offset: 3px; }
  .serial { text-align: right; margin: 12mm 0 10mm; font-family: "Courier New", monospace; font-size: 14pt; letter-spacing: 1px; }
  .serial b { display: inline-block; min-width: 38mm; border-bottom: 1.5px dotted #111; text-align: center; font-size: 15pt; }
  .row { display: flex; gap: 6mm; margin: 0 0 5.5mm; }
  .f { display: flex; align-items: flex-end; flex: 1; min-width: 0; font-size: 12.5pt; }
  .f .k { white-space: nowrap; text-transform: uppercase; }
  .f.bold .k { font-weight: 700; }
  .f .v { flex: 1; min-width: 0; border-bottom: 1.3px solid #111; margin-left: 1.5mm; padding: 0 2mm 0.5mm; min-height: 6.5mm;
          font-family: "Segoe Print", "Comic Sans MS", cursive; font-size: 11.5pt; color: #1e3a8a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .f .v.signed { color: #15803d; font-family: Calibri, Arial, sans-serif; font-size: 10pt; font-style: italic; }
  hr.rule { border: 0; border-top: 2.5px solid #111; margin: 2mm 0 9mm; }
  @media print {
    body { background: #fff; }
    .bar { display: none; }
    .page { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; page-break-after: always; }
    .page:last-child { page-break-after: auto; }
    .photo { top: 0; right: 0; }
  }
</style>
</head>
<body>
@php
    $v = function ($form, $k) {
        if ($form->status !== \App\Models\Survey\SurveyConfirmationForm::CAPTURED) return '';
        $x = $form->{$k};
        return $x instanceof \Carbon\Carbon ? $x->format('d/m/Y') : (string) $x;
    };
    $sig = fn ($form, $k) => $form->status === \App\Models\Survey\SurveyConfirmationForm::CAPTURED && $form->{$k};
@endphp
<div class="bar">
  <span>{{ $forms->count() === 1 ? 'Confirmation Form · Serial ' . $forms->first()->serial : $forms->count() . ' Confirmation Forms · ' . $forms->first()->serial . ' to ' . $forms->last()->serial }}</span>
  <button type="button" onclick="window.print()">Print</button>
</div>

@foreach ($forms as $form)
  <div class="page">
    <div class="photo">@if ($form->status === \App\Models\Survey\SurveyConfirmationForm::CAPTURED && $form->passport_path)<img src="{{ asset('storage/' . $form->passport_path) }}" alt="Passport">@endif</div>
    <div class="head">
      <div class="l1">MINISTRY OF LAND AND PHYSICAL PLANNING</div>
      <div class="l2">KANO STATE</div>
      <div class="l3">{{ $form->layout_heading }}</div>
      <div class="l4">CONFIRMATION FORM</div>
    </div>
    <div class="serial">SERIAL NO. <b>{{ $form->serial }}</b></div>

    <div class="row"><div class="f bold"><span class="k">Land Owner's Name:</span><span class="v">{{ $v($form, 'land_owner_name') }}</span></div></div>
    <div class="row">
      <div class="f"><span class="k">Section:</span><span class="v">{{ $v($form, 'section') }}</span></div>
      <div class="f"><span class="k">ID Number:</span><span class="v">{{ $v($form, 'id_number') }}</span></div>
    </div>
    <div class="row">
      <div class="f"><span class="k">Location:</span><span class="v">{{ $v($form, 'location') }}</span></div>
      <div class="f"><span class="k">NIN Number:</span><span class="v">{{ $v($form, 'nin') }}</span></div>
    </div>
    <div class="row"><div class="f"><span class="k">GSM:</span><span class="v">{{ $v($form, 'gsm') }}</span></div></div>
    <div class="row"><div class="f"><span class="k">Address:</span><span class="v">{{ $v($form, 'address') }}</span></div></div>
    <hr class="rule">

    <div class="row"><div class="f bold"><span class="k">Vendor/Agent Name:</span><span class="v">{{ $v($form, 'vendor_name') }}</span></div></div>
    <div class="row"><div class="f"><span class="k">Address:</span><span class="v">{{ $v($form, 'vendor_address') }}</span></div></div>
    <div class="row">
      <div class="f"><span class="k">Signature:</span><span class="v {{ $sig($form, 'vendor_signed') ? 'signed' : '' }}">{{ $sig($form, 'vendor_signed') ? 'Signed on the paper form' : '' }}</span></div>
      <div class="f"><span class="k">Date:</span><span class="v">{{ $v($form, 'vendor_date') }}</span></div>
    </div>
    <div class="row"><div class="f"><span class="k">GSM:</span><span class="v">{{ $v($form, 'vendor_gsm') }}</span></div></div>
    <hr class="rule">

    <div class="row"><div class="f bold"><span class="k">Committee Chairman's Name:</span><span class="v">{{ $v($form, 'chairman_name') }}</span></div></div>
    <div class="row">
      <div class="f"><span class="k">Signature:</span><span class="v {{ $sig($form, 'chairman_signed') ? 'signed' : '' }}">{{ $sig($form, 'chairman_signed') ? 'Signed on the paper form' : '' }}</span></div>
      <div class="f"><span class="k">Date:</span><span class="v">{{ $v($form, 'chairman_date') }}</span></div>
    </div>
    <div class="row"><div class="f bold"><span class="k">Ward/Village Head Name:</span><span class="v">{{ $v($form, 'village_head_name') }}</span></div></div>
    <div class="row">
      <div class="f"><span class="k">Signature:</span><span class="v {{ $sig($form, 'village_head_signed') ? 'signed' : '' }}">{{ $sig($form, 'village_head_signed') ? 'Signed on the paper form' : '' }}</span></div>
      <div class="f"><span class="k">Date:</span><span class="v">{{ $v($form, 'village_head_date') }}</span></div>
    </div>
    <hr class="rule">

    <div class="row"><div class="f bold"><span class="k">Head of Compensations Name:</span><span class="v">{{ $v($form, 'compensation_head_name') }}</span></div></div>
    <div class="row">
      <div class="f"><span class="k">Signature:</span><span class="v {{ $sig($form, 'compensation_head_signed') ? 'signed' : '' }}">{{ $sig($form, 'compensation_head_signed') ? 'Signed on the paper form' : '' }}</span></div>
      <div class="f"><span class="k">Date:</span><span class="v">{{ $v($form, 'compensation_head_date') }}</span></div>
    </div>
  </div>
@endforeach
</body>
</html>
