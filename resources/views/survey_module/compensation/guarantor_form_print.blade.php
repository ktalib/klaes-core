<!DOCTYPE html>
{{-- The Guarantor's Form of the Ministry of Land and Physical Planning (user, 2026-11-03: Guarantor Form.pdf), step 2 of
     a Confirmation Form and printed from its row menu. Captured details fill the lines; the rest print blank. --}}
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Guarantor's Form {{ $form->serial }}</title>
<style>
  @page { size: A4 portrait; margin: 14mm 18mm; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #e5e7eb; font-family: Calibri, "Segoe UI", Arial, sans-serif; color: #111; font-size: 11.5pt; line-height: 1.45; }
  .bar { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: 12px;
         background: #fff; padding: 10px 18px; border-bottom: 1px solid #d1d5db; font-size: 14px; }
  .bar button { background: #1d4ed8; color: #fff; border: 0; border-radius: 6px; padding: 8px 16px; font-weight: 700; cursor: pointer; }
  .page { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 14mm 18mm; position: relative; box-shadow: 0 2px 10px rgba(0,0,0,.12); overflow: hidden; }
  .wm { position: absolute; left: 50%; top: 52%; width: 120mm; transform: translate(-50%, -50%); opacity: .07; pointer-events: none; }
  .photo { position: absolute; top: 12mm; right: 18mm; width: 30mm; height: 37mm; border: 1.3px solid #111; overflow: hidden; }
  .photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
  h1 { font-family: "Times New Roman", Times, serif; font-size: 20pt; margin: 22mm 0 4mm; letter-spacing: .3px; }
  .ministry { display: flex; align-items: center; gap: 8mm; font-family: "Times New Roman", Times, serif; font-weight: 700; font-size: 14pt; }
  .ministry img { width: 17mm; height: auto; }
  .notice { font-family: "Times New Roman", Times, serif; font-weight: 700; font-size: 16pt; text-decoration: underline; margin: 1mm 0 2mm; }
  p { margin: 0 0 3mm; text-align: justify; }
  .box { border: 1.3px solid #4d7c0f; padding: 2mm 4mm; margin: 2mm 0 6mm; font-family: "Times New Roman", Times, serif; font-size: 12.5pt; line-height: 1.35; }
  .ln { display: flex; align-items: flex-end; gap: 1.5mm; margin: 0 0 1.4mm; }
  .ln .k { white-space: nowrap; }
  .fill { flex: 1; min-width: 12mm; border-bottom: 1.2px dotted #111; padding: 0 1.5mm; min-height: 5.6mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
          font-family: "Segoe Print", "Comic Sans MS", cursive; font-size: 10.5pt; color: #1e3a8a; }
  .fill.line { border-bottom-style: solid; }
  .fill.fixed { font-family: inherit; font-size: inherit; color: #111; border: 0; }
  .two { display: flex; gap: 6mm; }
  .two > .ln { flex: 1; }
  .gap { height: 4mm; }
  .tick { color: #15803d; font-family: Calibri, Arial, sans-serif; font-size: 10pt; font-style: italic; }
  .sub { font-size: 10.5pt; margin: -0.5mm 0 1.4mm; }
  .official { margin-top: 8mm; }
  @media print {
    body { background: #fff; }
    .bar { display: none; }
    .page { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
    .photo { top: 0; right: 0; }
    h1 { margin-top: 10mm; }
  }
</style>
</head>
<body>
@php
  $a = $form->guarantorApplicant();
  $d = fn ($x) => $x ? $x->format('d/m/Y') : '';
@endphp
<div class="bar">
  <span>Guarantor's Form · Confirmation Form {{ $form->serial }} · {{ $form->layout_name }}</span>
  <button type="button" onclick="window.print()">Print</button>
</div>

<div class="page">
  <img class="wm" src="{{ asset('assets/logo/ministry1.jpg') }}" alt="">
  <div class="photo">@if ($form->guarantor_passport_path)<img src="{{ asset('storage/' . $form->guarantor_passport_path) }}" alt="Passport">@endif</div>

  <h1>GUARANTOR’S FORM</h1>
  <div class="ministry"><img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Kano State"> MINISTRY OF LAND AND PHYSICAL PLANNING</div>
  <div class="notice">NOTICE!</div>
  <p>This Land Agent Registration Exercise requires that every applicant must submit a duly completed Guarantor Form, which must be signed and filled exclusively by the Committee Chairman.</p>
  <p style="margin-bottom:0;">No other person shall be accepted as a guarantor;</p>
  <p style="margin-bottom:0;">(i) A recent passport-sized photograph of the Committee Chairman, and</p>
  <p>(ii) A clear photocopy of the Committee Chairman’s official identity document (e.g., Driver’s License, Voter’s Card, NIN Card, or International Passport), signed and endorsed by the Chairman.</p>
  <div class="box">Please kindly attach to this form a passport photograph and photocopy of your official identity card/Drivers License/Voters Card/NIN Card/International Passport signed by you.</div>

  <div class="ln"><span class="k">I,</span><span class="fill">{{ $form->guarantor_name }}</span></div>
  <div class="sub">(Full Name of Committee Chairman)</div>
  <div class="ln"><span class="k">of Home Address:</span><span class="fill">{{ $form->guarantor_home_address }}</span></div>
  <div class="ln"><span class="k">Office Address (Committee Secretariat):</span><span class="fill">{{ $form->guarantor_office_address }}</span></div>
  <div class="two">
    <div class="ln"><span class="k">Occupation:</span><span class="fill">{{ $form->guarantor_occupation }}</span></div>
    <div class="ln"><span class="k">Rank/Position:</span><span class="fill fixed">{{ $form->guarantor_rank ?: \App\Models\Survey\SurveyConfirmationForm::DEFAULT_RANK }}</span></div>
  </div>
  <div class="two">
    <div class="ln"><span class="k">Phone Number(s):</span><span class="fill">{{ $form->guarantor_phone }}</span></div>
    <div class="ln"><span class="k">Email (if available):</span><span class="fill">{{ $form->guarantor_email }}</span></div>
  </div>
  <div class="ln"><span class="k">BVN (Optional but encouraged):</span><span class="fill">{{ $form->guarantor_bvn }}</span></div>
  @if ($form->guarantor_id_label)
    <div class="ln"><span class="k">Identity document attached:</span><span class="fill">{{ $form->guarantor_id_label }}</span></div>
  @endif

  <div class="gap"></div>
  <p style="margin-bottom:1mm;">HEREBY CONFIRM AND DECLARE that I am the duly appointed Committee Chairman of</p>
  <div class="ln"><span class="fill">{{ $form->guarantor_committee }}</span><span class="k">Committee,</span><span class="fill">{{ $form->guarantor_lga }}</span><span class="k">Local Government Area, Kano State.</span></div>
  <p>I irrevocably and unconditionally stand as GUARANTOR for the following Land Agent Registration applicant:</p>

  <div class="gap"></div>
  <div class="ln"><span class="k">Applicant’s Full Name:</span><span class="fill">{{ $a['name'] }}</span></div>
  <div class="ln"><span class="k">Address:</span><span class="fill">{{ $a['address'] }}</span></div>
  <div class="two">
    <div class="ln"><span class="k">Phone Number:</span><span class="fill">{{ $a['phone'] }}</span></div>
    <div class="ln"><span class="k">National Identification Number (NIN):</span><span class="fill">{{ $a['nin'] }}</span></div>
  </div>
  <p>I guarantee the good conduct, trustworthiness, and authenticity of the above-named applicant. I further undertake to indemnify the Ministry of Land and Physical Planning (MLPP) against any loss, damage, or liability arising from the applicant’s actions or omissions before, during, or after the registration process or any subsequent assignment.</p>
  <p>I agree to produce the applicant before the Ministry whenever required for clarification, verification, or investigation.</p>
  <p>This guarantee shall be governed in accordance with the laws of Kano State and the Federal Republic of Nigeria.</p>

  <div class="gap"></div>
  <div class="ln" style="width:120mm;"><span class="k">Committee Chairman’s Signature:</span><span class="fill line">@if ($form->guarantor_signed)<span class="tick">Signed on the form</span>@endif</span></div>
  <div class="ln" style="width:130mm;"><span class="k">Name (in full):</span><span class="fill line">{{ $form->guarantor_name }}</span></div>
  <div class="two">
    <div class="ln" style="flex:0 0 62mm;"><span class="k">Date:</span><span class="fill line">{{ $d($form->guarantor_date) }}</span></div>
    <div class="ln"><span class="k">Stamp of Committee Secretariat (if available):</span>@if ($form->guarantor_stamped)<span class="tick">Stamped on the form</span>@endif</div>
  </div>

  <div class="official">
    <div style="margin-bottom:1.4mm;">FOR OFFICIAL USE ONLY</div>
    <div class="ln" style="width:112mm;"><span class="k">Registration Reference No.:</span><span class="fill line">{{ $form->guarantor_reg_ref }}</span></div>
    <div class="two" style="width:150mm;">
      <div class="ln"><span class="k">Verified by MLPP:</span><span class="fill line">{{ $form->guarantor_verified_by }}</span></div>
      <div class="ln" style="flex:0 0 40mm;"><span class="k">Date:</span><span class="fill line">{{ $d($form->guarantor_verified_date) }}</span></div>
    </div>
  </div>
    @include('partials._klaes_print_logo')
</div>
</body>
</html>
