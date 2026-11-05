<!DOCTYPE html>
{{-- Farm Capture Confirmation Sheet (CR 03112026 item 8): one A4 page for one farmer's farm. --}}
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Farm Capture Confirmation {{ $farm->farm_id_no ?: $case->case_ref }}</title>
<style>
  @page { size: A4 portrait; margin: 14mm 16mm; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #e5e7eb; font-family: Calibri, "Segoe UI", Arial, sans-serif; color: #111; }
  .bar { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: 12px;
         background: #fff; padding: 10px 18px; border-bottom: 1px solid #d1d5db; font-size: 14px; }
  .bar button { background: #1d4ed8; color: #fff; border: 0; border-radius: 6px; padding: 8px 16px; font-weight: 700; cursor: pointer; }
  .page { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 14mm 16mm; box-shadow: 0 2px 10px rgba(0,0,0,.12); }
  .head { text-align: center; font-weight: 700; line-height: 1.35; margin-bottom: 8mm; }
  .head .l1 { font-size: 16pt; }
  .head .l2 { font-size: 15pt; }
  .head .l3 { font-size: 14pt; text-decoration: underline; text-underline-offset: 3px; margin-top: 2mm; }
  .ids { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3mm; margin-bottom: 7mm; }
  .ids div { border: 1.5px solid #111; border-radius: 6px; padding: 2mm 3mm; text-align: center; }
  .ids span { display: block; font-size: 9pt; text-transform: uppercase; letter-spacing: .03em; color: #444; }
  .ids strong { font-size: 13pt; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; font-size: 11.5pt; }
  th, td { border: 1px solid #555; padding: 1.8mm 2.5mm; text-align: left; vertical-align: top; }
  th { background: #f0f2f5; font-size: 10pt; }
  .k { width: 24%; background: #f0f2f5; font-weight: 600; font-size: 10.5pt; }
  h3 { font-size: 11.5pt; margin: 0 0 2mm; text-transform: uppercase; letter-spacing: .03em; }
  .note { font-size: 10.5pt; margin: 0 0 12mm; }
  .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 14mm; margin-top: 14mm; }
  .sign div { border-top: 1.3px solid #111; padding-top: 1.5mm; font-size: 10.5pt; }
  @media print {
    body { background: #fff; }
    .bar { display: none; }
    .page { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
  }
</style>
</head>
<body>
@php
    $lga = \App\Support\AddressBuilder::resolve($case->prop_lga);
    $trees = $case->trees;
    $treeTotal = $trees->sum('line_total');
@endphp
<div class="bar">
  <span>Farm Capture Confirmation · {{ $farm->full_name }}</span>
  <button type="button" onclick="window.print()">Print</button>
</div>
<div class="page">
    <div class="head">
        <div class="l1">MINISTRY OF LAND AND PHYSICAL PLANNING</div>
        <div class="l2">KANO STATE</div>
        <div class="l3">FARM CAPTURE CONFIRMATION SHEET</div>
    </div>

    <div class="ids">
        <div><span>Section No.</span><strong>{{ $case->section_no ?: '—' }}</strong></div>
        <div><span>Farmer ID</span><strong>{{ $farmer->farmer_id_no ?? '—' }}</strong></div>
        <div><span>Farm ID</span><strong>{{ $farm->farm_id_no ?: '—' }}</strong></div>
        <div><span>Farm No.</span><strong>{{ $farm->farm_no ?: '—' }}</strong></div>
    </div>

    <h3>Farmer</h3>
    <table>
        <tr><td class="k">Name</td><td colspan="3">{{ $farm->full_name }}</td></tr>
        <tr><td class="k">Phone</td><td>{{ $farm->phone ?: '—' }}</td><td class="k">NIN</td><td>{{ $farm->nin ?: '—' }}</td></tr>
    </table>

    <h3>Farm</h3>
    <table>
        <tr><td class="k">Project</td><td colspan="3">{{ $case->project?->project_code }} · {{ $case->project?->name }}</td></tr>
        <tr><td class="k">Assessment No.</td><td>{{ $case->case_ref }}</td><td class="k">Date</td><td>{{ optional($case->case_date)->format('d/m/Y') }}</td></tr>
        <tr><td class="k">Plot No.</td><td>{{ $case->prop_plot ?: '—' }}</td><td class="k">District</td><td>{{ $case->resolvedDistrict('prop_') ?: '—' }}</td></tr>
        <tr><td class="k">LGA</td><td>{{ $lga ? mb_strtoupper($lga) : '—' }}</td><td class="k">Location</td><td>{{ $case->resolvedStreet('prop_') ?: '—' }}</td></tr>
        <tr><td class="k">Area</td><td>{{ rtrim(rtrim(number_format((float) $case->area_ha, 4, '.', ''), '0'), '.') }} ha</td><td class="k">Surveyor</td><td>{{ $case->survey_officer }}</td></tr>
    </table>

    @if ($trees->isNotEmpty())
        <h3>Economic Trees and Crops</h3>
        <table>
            <thead><tr><th style="width:12mm;">S/N</th><th>Item</th><th>Category</th><th>Quantity</th></tr></thead>
            <tbody>
                @foreach ($trees as $t)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $t->tree_type }}</td><td>{{ $t->maturity ?: '—' }}</td><td>{{ number_format($t->quantity) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="note">I confirm that the details above are correct.</p>

    <div class="sign">
        <div>Farmer's signature or thumbprint, and date</div>
        <div>Surveyor's signature and date</div>
    </div>
</div>
</body>
</html>
