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
        margin: 3mm 8mm;
        size: A4 portrait;
      }

      body {
        margin: 0;
        padding: 0;
        font-size: 9pt !important;
        line-height: 1.1 !important;
      }

      .page {
        page-break-after: auto;
      }

      h1 {
        font-size: 16pt !important;
        margin: 2px 0 5px;
        text-align: center;
        font-weight: 700;
      }

      .section-title {
        font-size: 8.5pt !important;
        font-weight: bold;
        margin: 4px 0 1px;
        border-bottom: 1.5px solid #000;
        padding-bottom: 1px;
      }

      .field-row {
        margin: 1px 0;
        font-size: 8.5pt !important;
      }

      .label {
        width: 155px;
        display: inline-block;
        vertical-align: top;
        font-weight: 700;
      }

      input,
      textarea {
        border: none !important;
        border-bottom: 1px solid #000 !important;
        background: transparent !important;
        font-size: 10pt !important;
        padding: 0.5px 0;
        margin: 0;
        font-weight: 500;
      }

      textarea {
        height: 25px;
        resize: none;
        min-height: 20px;
      }

      .grid-compact {
        gap: 1px 4px !important;
      }

      .sign-block div {
        margin-top: 5px;
      }

      .sign-line {
        border-bottom: 1.5px solid #000;
        height: 10px;
      }

      .no-print {
        display: none !important;
      }

      .text-xs {
        font-size: 9pt !important;
      }
    }

    /* Screen styles */
    body {
      font-size: 12pt;
      line-height: 1;
    }

    h1 {
      font-size: 28pt;
      margin: 10px 0 20px;
      text-align: center;
      font-weight: 700;
    }

    .section-title {
      font-size: 16pt;
      font-weight: bold;
      margin: 14px 0 6px;
      border-bottom: 2.5px solid #000;
      padding-bottom: 4px;
    }

    .field-row {
      margin: 2px 0;
    }

    .label {
      width: 160px;
      display: inline-block;
      vertical-align: top;
      font-weight: 700;
    }

    input,
    textarea {
      border: none;
      border-bottom: 2px dotted #666;
      background: transparent;
      width: 100%;
      outline: none;
      font-size: 14pt;
      padding: 3px 0;
      font-weight: 500;
    }

    input:focus,
    textarea:focus {
      border-bottom: 3px solid #000;
      background-color: #f8f9fa;
    }

    textarea {
      height: 60px;
      resize: none;
      min-height: 60px;
    }

    .grid-compact {
      gap: 6px 10px !important;
    }

    .sign-line {
      border-bottom: 2px dotted #000;
      height: 24px;
    }

    .text-xs {
      font-size: 10pt;
    }

    /* Larger buttons */
    .no-print.text-sm {
      font-size: 14pt !important;
    }

    .no-print button {
      font-size: 13pt !important;
      padding: 10px 20px !important;
      font-weight: 600;
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
  <div class="page max-w-5xl mx-auto px-4 py-8 print:px-0 print:py-0">
    <div class="flex justify-between items-start mb-4">
        <div class="invisible">FILE NO: {{ $report->file_number }}</div>
        <div class="font-bold text-lg">FILE NO: {{ $report->file_number }}</div>
    </div>
    <h1>VALUATION REPORT</h1>
    <div class="space-y-1">
      <div class="section-title">1. INSPECTION DATE</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact">
        <span>a)  INSPECTION DATE:</span><input type="text" value="{{ $report->inspection_date ? $report->inspection_date->format('jS F, Y') : 'N/A' }}" />
      </div>

      <div class="section-title">2. PURPOSE OF VALUATION</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact">
        <span>a)  PURPOSE OF VALUATION:</span><input type="text" value="{{ $report->valuation_purpose }}" />
      </div>  

      <div class="section-title">3. OWNER</div>  
      <div class="grid grid-cols-[160px_1fr] grid-compact">
        <span>a) Name:</span><input type="text" value="{{ $report->full_name }}" />
        <span>b) Address:</span><input type="text" value="{{ $report->address }}" />
        <span>c) Plot/Property No:</span><input type="text" value="{{ $report->property_no }}" />
        <span>d) Street Name:</span><input type="text" value="{{ $report->street_name }}" />
        <span>e) Quarters:</span><input type="text" value="{{ $report->estate_quarters }}" />
        <span>f) Neighborhood Char.:</span><input type="text" value="{{ $report->neighborhood_characteristics }}" />
        <span>g) Town:</span><input type="text" value="{{ $report->town_city }}" />
        <span>h) L.G.A:</span><input type="text" value="{{ $report->lga }}" />
        <span>i) Phone No:</span><input type="text" value="{{ $report->phone }}" />
      </div>

      <div class="section-title">4. PROPERTY</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact">
        <span>a) Type:</span><input type="text" value="{{ $report->property_type }}" />
        <span>b) Number of Block:</span><input type="text" value="{{ $report->num_blocks }}" />
        <span>c) Story Height:</span><input type="text" value="{{ $report->storey_height }}" />
        <span>d) Number per Block:</span><input type="text" value="{{ $report->units_per_block }}" />
        <span>e) Bedroom Unit:</span><input type="text" value="{{ $report->bedroom_units }}" />
        <span>f) Ancillary:</span><input type="text" value="{{ $report->ancillary_bldgs }}" />
      </div>

      <div class="field-row">
        <span class="label">5. Use:</span>
        <input type="text" value="{{ $report->use ?? $report->current_use }}" />
      </div>

      <div class="section-title">CONSTRUCTION DETAILS</div>
      
      <div class="font-bold mb-1 uppercase">6. Foundation</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact mb-1">
        <span>a) Type:</span><input type="text" value="{{ $report->foundation_type }}" />
        <span>b) Stage:</span><input type="text" value="{{ $report->foundation_stage }}" />
      </div>

      <div class="font-bold mb-1 uppercase">7. Floor</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact mb-1">
        <span>a) Type:</span><input type="text" value="{{ $report->floor_type }}" />
        <span>b) Finishing:</span><input type="text" value="{{ $report->floor_finish }}" />
      </div>

      <div class="font-bold mb-1 uppercase">8. Main Walls</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact mb-1">
        <span>a) Type:</span><input type="text" value="{{ $report->walls_type }}" />
        <span>b) Stage:</span><input type="text" value="{{ $report->walls_stage }}" />
        <span>c) Finishing:</span><input type="text" value="{{ $report->walls_finish }}" />
      </div>

      <div class="font-bold mb-1 uppercase">9. Boundary Walls</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact mb-1">
        <span>a) Type:</span><input type="text" value="{{ $report->boundary_wall_type }}" />
        <span>b) Height:</span><input type="text" value="{{ $report->boundary_wall_height }}" />
        <span>c) Finishing:</span><input type="text" value="{{ $report->boundary_wall_finish }}" />
      </div>

      <div class="font-bold mb-1 uppercase">10. Roof</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact mb-1">
        <span>a) Type:</span><input type="text" value="{{ $report->roof_type }}" />
        <span>b) Materials:</span><input type="text" value="{{ $report->roof_material }}" />
      </div>

      <div class="font-bold mb-1 uppercase">11. Ceiling</div>
      <div class="field-row mb-1">
        <input type="text" value="{{ $report->ceiling_detail }}" class="w-full" />
      </div>

      <div class="font-bold mb-1 uppercase">12. Doors</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact mb-1">
        <span>a) Gate:</span><input type="text" value="{{ $report->gate_type }}" />
        <span>b) External:</span><input type="text" value="{{ $report->ext_doors }}" />
        <span>c) Internal:</span><input type="text" value="{{ $report->int_doors }}" />
        <span>d) Proof:</span><input type="text" value="{{ $report->door_security_proof }}" />
      </div>

      <div class="font-bold mb-1 uppercase">13. Windows</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact mb-1">
        <span>a) Type:</span><input type="text" value="{{ $report->window_type }}" />
        <span>b) Proof:</span><input type="text" value="{{ $report->burglary_proof }}" />
      </div>
<br>
      <div class="section-title">SERVICES & UTILITIES</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact">
        <span>14. TOILET/BATHROOM:</span><input type="text" value="{{ $report->toilet_bath }}" />
        <span>15. WATER SUPPLY:</span><input type="text" value="{{ $report->water_supply }}" />
        <span>16. ELECTRICITY:</span><input type="text" value="{{ $report->electricity }}" />
        <span>17. DRAINAGE SYSTEM:</span><input type="text" value="{{ $report->drainage }}" />
      </div>

      <div class="section-title">18. FITTING & FIXTURE</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact">
        <span>a) Sitting Room:</span><input type="text" value="{{ $report->fittings_sitting }}" />
        <span>b) Dinning:</span><input type="text" value="{{ $report->fittings_dining }}" />
        <span>c) Bed:</span><input type="text" value="{{ $report->fittings_bedroom }}" />
        <span>d) Toilet:</span><input type="text" value="{{ $report->fittings_toilet }}" />
        <span>e) Bath:</span><input type="text" value="{{ $report->fittings_bath }}" />
        <span>f) Kitchen:</span><input type="text" value="{{ $report->fittings_kitchen }}" />
        <span>g) Store:</span><input type="text" value="{{ $report->fittings_store }}" />
        <span>h) B/Q:</span><input type="text" value="{{ $report->fittings_bq }}" />
      </div>

      <div class="section-title">19. REMARKS & OBSERVATIONS</div>
      <textarea placeholder="...">{{ $report->remarks }}</textarea>

      <div class="section-title">20. BASIS OF VALUATION</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact">
        <span></span><input type="text" value="{{ $report->valuation_basis }}" />
      </div>

      @php
        // Print re-evaluation values when present, otherwise fall back to the originals.
        $printValueWords = filled($report->re_value_words) ? $report->re_value_words : $report->value_words;
        $printValueFigures = filled($report->re_value_figures) ? $report->re_value_figures : $report->value_figures;
      @endphp
      <div class="section-title">21. OPINION/RECOMMENDATION</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact">
        <span>a) Value in Words:</span><textarea class="font-bold uppercase text-sm w-full leading-tight">{{ $printValueWords }}</textarea>
        <span>b) Amount (₦):</span><input type="text" value="{{ $printValueFigures }}" class="font-bold text-lg" />
      </div>

      <div class="section-title">22. SURVEYOR DETAILS</div>
      <div class="grid grid-cols-[160px_1fr] grid-compact">
        <span>a) Name:</span><input type="text" value="{{ $report->surveyor_name }}" />
        <span>b) Rank/Reg:</span><input type="text" value="{{ $report->surveyor_rank }}" />
      </div>
    <br> <br> <br> <br> <br> <br>

      <div class="sign-block grid grid-cols-2 gap-8 mt-6">
        <div class="text-center">
            <div class="sign-line w-64 mx-auto"></div>
            <div class="font-bold mt-1 uppercase text-[8pt]">SURVEYOR'S SIGNATURE & DATE</div>
        </div>
        <div class="text-center">
            <div class="sign-line w-64 mx-auto"></div>
            <div class="font-bold mt-1 uppercase text-[8pt]">{{ $report->chief_valuer ?? 'O/C VALUATION' }} SIGNATURE</div>
        </div>
      </div>
<br> <br> <br> <br> <br> <br>
<br> <br> <br> <br> <br> <br>
<br> <br> <br> <br> <br> <br>
 
 
      <div class="flex justify-end mt-16">
        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Logo" style="max-height: 32px;" />
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
