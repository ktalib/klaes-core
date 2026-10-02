<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Valuation Report - {{ $report->file_number }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @media print {
      @page {
        margin: 10mm 8mm;
        size: A4 portrait;
      }

      body {
        margin: 0;
        padding: 0;
        font-size: 11pt !important;
        line-height: 1.35 !important;
      }

      .page {
        page-break-after: always;
      }

      h1 {
        font-size: 20pt !important;
        margin: 10px 0 20px;
        text-align: center;
        font-weight: 700;
        text-decoration: underline;
      }

      .section-title {
        font-size: 10pt !important;
        font-weight: bold;
        margin: 14px 0 6px;
        border-bottom: 2.5px solid #000;
        padding-bottom: 4px;
      }

      .field-row {
        margin: 6px 0;
        font-size: 10pt !important;
      }

      .label {
        width: 160px;
        display: inline-block;
        vertical-align: top;
        font-weight: 700;
      }

      .print-value {
        border-bottom: 1px dotted #000 !important;
        background: transparent !important;
        font-size: 12pt !important;
        padding: 2px 5px;
        margin: 0;
        font-weight: 600;
        display: inline-block;
        min-height: 1.2em;
        width: 100%;
      }

      .sign-line {
        border-bottom: 2px solid #000;
        height: 20px;
      }

      .no-print {
        display: none !important;
      }

      .grid-compact {
        gap: 6px 10px !important;
      }
    }

    /* Screen styles */
    body {
      font-size: 12pt;
      line-height: 1.4;
    }

    h1 {
      font-size: 24pt;
      margin: 10px 0 20px;
      text-align: center;
      font-weight: 700;
      text-decoration: underline;
    }

    .section-title {
      font-size: 14pt;
      font-weight: bold;
      margin: 14px 0 6px;
      border-bottom: 2.5px solid #000;
      padding-bottom: 4px;
    }

    .field-row {
      margin: 8px 0;
    }

    .label {
      width: 160px;
      display: inline-block;
      vertical-align: top;
      font-weight: 700;
    }

    .print-value {
        border-bottom: 1px dotted #000;
        padding: 2px 5px;
        display: inline-block;
        width: 100%;
        font-weight: 600;
        min-height: 1.2em;
    }

    .sign-line {
      border-bottom: 2px dotted #000;
      height: 24px;
    }
  </style>
</head>

<body class="bg-white text-black">
  <div class="no-print bg-amber-50 p-5 mb-8 max-w-5xl mx-auto rounded text-base">
    <strong class="text-lg">Print Tips:</strong> Chrome/Edge • Margins: Minimal/Default •
    Headers/Footers: Off • Scale: 100%<br />
    <div class="mt-4 space-x-4">
      <button onclick="handlePrint()" class="bg-blue-600 text-white px-5 py-3 rounded mr-2 font-semibold">
        Print Report
      </button>
      <button onclick="window.close()" class="bg-gray-700 text-white px-5 py-3 rounded mr-2 font-semibold">
        Close Preview
      </button>
    </div>
  </div>

  <!-- Single Page Container -->
  <div class="page max-w-5xl mx-auto px-8 py-8 print:px-0 print:py-0">
    <div class="flex justify-between items-start mb-4">
        <div class="invisible">FILE NO: {{ $report->file_number }}</div>
        <div class="font-bold text-lg">FILE NO: {{ $report->file_number }}</div>
    </div>
    
    <h1>VALUATION REPORT</h1>

    <div class="space-y-4">
      <div class="field-row grid grid-cols-[200px_1fr] gap-4">
        <span class="font-bold">1. INSPECTION DATE</span>
        <div class="print-value">{{ $report->inspection_date ? $report->inspection_date->format('jS F, Y') : 'N/A' }}</div>
      </div>
      <div class="field-row grid grid-cols-[200px_1fr] gap-4">
        <span class="font-bold">2. PURPOSE OF VALUATION</span>
        <div class="print-value uppercase">{{ $report->valuation_purpose ?? 'N/A' }}</div>
      </div>

      <div class="section-title">3. OWNER / CLIENT DETAILS</div>
      <div class="grid grid-cols-[180px_1fr] grid-compact gap-y-2">
        <span class="font-bold">a) Full Name</span><div class="print-value uppercase">{{ $report->full_name }}</div>
        <span class="font-bold">b) Address</span><div class="print-value">{{ $report->address }}</div>
        <span class="font-bold">c) Plot/Property No.</span><div class="print-value">{{ $report->property_no }}</div>
        <span class="font-bold">d) Street Name</span><div class="print-value">{{ $report->street_name }}</div>
        <span class="font-bold">e) Estate/Quarters</span><div class="print-value">{{ $report->estate_quarters }}</div>
        <span class="font-bold">f) Town/City</span><div class="print-value">{{ $report->town_city }}</div>
        <span class="font-bold">g) Local Government Area</span><div class="print-value">{{ $report->lga }}</div>
      </div>

      <div class="section-title">4. PROPERTY DESCRIPTION</div>
      <div class="mb-2 font-bold uppercase italic">Property: <span class="underline">{{ $report->property_desc ?? 'N/A' }}</span></div>
      <div class="grid grid-cols-[180px_1fr] grid-compact gap-y-2">
        <span class="font-bold">a) Property Type</span><div class="print-value uppercase">{{ $report->property_type }}</div>
        <span class="font-bold">b) Number of Blocks</span><div class="print-value">{{ $report->num_blocks }}</div>
        <span class="font-bold">c) Storey Height</span><div class="print-value">{{ $report->storey_height }}</div>
        <span class="font-bold">d) Bedroom Units</span><div class="print-value">{{ $report->bedroom_units }}</div>
        <span class="font-bold">e) Ancillary Buildings</span><div class="print-value">{{ $report->ancillary_bldgs }}</div>
        <span class="font-bold">f) Use</span><div class="print-value uppercase">{{ $report->use ?? $report->current_use }}</div>
      </div>

      <div class="section-title">5. CONSTRUCTION DETAILS</div>
      <div class="grid grid-cols-2 gap-x-12 gap-y-4">
        <div class="space-y-4">
          <div>
            <div class="font-bold mb-1 text-sm border-b border-black">FOUNDATION</div>
            <div class="grid grid-cols-[80px_1fr] gap-x-2 gap-y-1 items-end">
              <span class="text-sm">Type</span><div class="print-value text-sm">{{ $report->foundation_type }}</div>
              <span class="text-sm">Stage</span><div class="print-value text-sm">{{ $report->foundation_stage }}</div>
            </div>
          </div>

          <div>
            <div class="font-bold mb-1 text-sm border-b border-black">FLOOR</div>
            <div class="grid grid-cols-[80px_1fr] gap-x-2 gap-y-1 items-end">
              <span class="text-sm">Type</span><div class="print-value text-sm">{{ $report->floor_type }}</div>
              <span class="text-sm">Finish</span><div class="print-value text-sm">{{ $report->floor_finish }}</div>
            </div>
          </div>

          <div>
            <div class="font-bold mb-1 text-sm border-b border-black">WALLS</div>
            <div class="grid grid-cols-[80px_1fr] gap-x-2 gap-y-1 items-end">
              <span class="text-sm">Type</span><div class="print-value text-sm">{{ $report->walls_type }}</div>
              <span class="text-sm">Finish</span><div class="print-value text-sm">{{ $report->walls_finish }}</div>
            </div>
          </div>
        </div>
        
        <div class="space-y-4">
          <div>
            <div class="font-bold mb-1 text-sm border-b border-black">ROOF</div>
            <div class="grid grid-cols-[80px_1fr] gap-x-2 gap-y-1 items-end">
              <span class="text-sm">Type</span><div class="print-value text-sm">{{ $report->roof_type }}</div>
              <span class="text-sm">Material</span><div class="print-value text-sm">{{ $report->roof_material }}</div>
            </div>
          </div>

          <div>
            <div class="font-bold mb-1 text-sm border-b border-black">CEILING</div>
            <div class="print-value text-sm">{{ $report->ceiling_detail }}</div>
          </div>

          <div>
            <div class="font-bold mb-1 text-sm border-b border-black">DOORS & WINDOWS</div>
            <div class="grid grid-cols-[100px_1fr] gap-x-2 gap-y-1 items-end">
              <span class="text-sm">External Doors</span><div class="print-value text-sm">{{ $report->ext_doors }}</div>
              <span class="text-sm">Window Type</span><div class="print-value text-sm">{{ $report->window_type }}</div>
              <span class="text-sm">Burglary Proof</span><div class="print-value text-sm">{{ $report->burglary_proof }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="section-title">6. SERVICES & UTILITIES</div>
      <div class="grid grid-cols-2 gap-x-12 gap-y-4">
        <div>
          <div class="font-bold mb-1 text-sm border-b border-black">TOILET & BATHROOM</div>
          <div class="print-value text-sm">{{ $report->toilet_bath }}</div>
        </div>
        <div>
          <div class="font-bold mb-1 text-sm border-b border-black">WATER SUPPLY</div>
          <div class="print-value text-sm">{{ $report->water_supply }}</div>
        </div>
        <div>
          <div class="font-bold mb-1 text-sm border-b border-black">ELECTRICITY</div>
          <div class="print-value text-sm">{{ $report->electricity }}</div>
        </div>
        <div>
          <div class="font-bold mb-1 text-sm border-b border-black">DRAINAGE SYSTEM</div>
          <div class="print-value text-sm">{{ $report->drainage }}</div>
        </div>
      </div>

      <div class="section-title">7. FITTINGS & FIXTURES</div>
      <div class="grid grid-cols-2 gap-x-12 gap-y-1">
          <div class="grid grid-cols-[100px_1fr] gap-2 items-end">
              <span class="text-xs">Sitting Room</span><div class="print-value text-xs">{{ $report->fittings_sitting }}</div>
          </div>
          <div class="grid grid-cols-[100px_1fr] gap-2 items-end">
              <span class="text-xs">Dining Room</span><div class="print-value text-xs">{{ $report->fittings_dining }}</div>
          </div>
          <div class="grid grid-cols-[100px_1fr] gap-2 items-end">
              <span class="text-xs">Bedrooms</span><div class="print-value text-xs">{{ $report->fittings_bedroom }}</div>
          </div>
          <div class="grid grid-cols-[100px_1fr] gap-2 items-end">
              <span class="text-xs">Toilets</span><div class="print-value text-xs">{{ $report->fittings_toilet }}</div>
          </div>
          <div class="grid grid-cols-[100px_1fr] gap-2 items-end">
              <span class="text-xs">Bathrooms</span><div class="print-value text-xs">{{ $report->fittings_bath }}</div>
          </div>
          <div class="grid grid-cols-[100px_1fr] gap-2 items-end">
              <span class="text-xs">Kitchen</span><div class="print-value text-xs">{{ $report->fittings_kitchen }}</div>
          </div>
          <div class="grid grid-cols-[100px_1fr] gap-2 items-end">
              <span class="text-xs">Store</span><div class="print-value text-xs">{{ $report->fittings_store }}</div>
          </div>
          <div class="grid grid-cols-[100px_1fr] gap-2 items-end">
              <span class="text-xs">B/Q</span><div class="print-value text-xs">{{ $report->fittings_bq }}</div>
          </div>
      </div>

      <div class="section-title">8. REMARKS & OBSERVATIONS</div>
      <div class="print-value min-h-[60px] uppercase text-sm leading-relaxed">{{ $report->remarks }}</div>

      <div class="section-title">9. VALUATION DETAILS</div>
      <div class="grid grid-cols-[180px_1fr] grid-compact gap-y-2">
        <span class="font-bold">Basis of Valuation</span><div class="print-value uppercase">{{ $report->valuation_basis }}</div>
        <span class="font-bold">Value in Words</span><div class="print-value uppercase font-bold text-sm">{{ $report->value_words }}</div>
        <span class="font-bold">Amount (₦)</span><div class="print-value font-black text-lg">{{ $report->value_figures }}</div>
      </div>

      <div class="grid grid-cols-2 gap-8 mt-4">
        <div>
          <div class="section-title">10. SURVEYOR DETAILS</div>
          <div class="grid grid-cols-[120px_1fr] grid-compact gap-y-1 items-end">
            <span class="font-bold text-xs uppercase">Name</span><div class="print-value text-xs uppercase">{{ $report->surveyor_name }}</div>
            <span class="font-bold text-xs uppercase">Rank/Reg No.</span><div class="print-value text-xs uppercase">{{ $report->surveyor_rank }}</div>
          </div>
        </div>
        <div>
          <div class="section-title">11. CHIEF VALUER</div>
          <div class="grid grid-cols-[80px_1fr] grid-compact gap-y-1 items-end">
            <span class="font-bold text-xs uppercase">Name</span><div class="print-value text-xs uppercase">{{ $report->chief_valuer ?? 'O/C Valuation' }}</div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-8 mt-12">
        <div>
          <!-- Placeholder for possible left signature -->
        </div>
        <div class="text-right">
          <div class="mb-3 font-bold text-sm uppercase">SURVEYOR'S SIGNATURE & DATE</div>
          <div class="sign-line w-72 inline-block"></div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    function handlePrint() {
      fetch('{{ route("valuation-reports.log-print", $report->id) }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            window.print();
          } else {
            Swal.fire({
              title: 'Note',
              text: data.message || 'Issue logging print, but you can still print.',
              icon: 'info'
            }).then(() => {
              window.print();
            });
          }
        })
        .catch(error => {
          console.error('Error:', error);
          window.print();
        });
    }
  </script>
</body>

</html>
