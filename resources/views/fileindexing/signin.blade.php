@extends('layouts.app')
@section('page-title')
    {{ __('Sign In & Out') }}
@endsection

@section('content')
  @include('fileindexing.css.style')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">

            <div class="container py-6">
                <!-- Page Header -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Sign In & Out</h1>
                        <p class="text-sm text-gray-500">Generate Sign In & Out Sheet per batch (max 100 records)</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('fileindex.index') }}" class="btn btn-outline">
                            <i data-lucide="arrow-left" class="h-4 w-4 mr-2"></i>
                            Back to File Indexing
                        </a>
                    </div>
                </div>

                <!-- Main Card -->
                <div class="card">
                    <div class="card-header">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="flex items-center gap-4 w-full md:w-auto">
                                <div>
                                  <label for="signin-batch-select" class="block text-sm font-medium text-gray-700 mb-1">Select Batch</label>
                                  <select id="signin-batch-select" class="input"
                                      onchange="(function(el){const btn=document.getElementById('generate-signin-report'); if(el.value){ btn.disabled=false; btn.classList.remove('opacity-50','cursor-not-allowed'); } else { btn.disabled=true; btn.classList.add('opacity-50','cursor-not-allowed'); }})(this);">
                                    <option value="">Select batch</option>
                                  </select>
                                </div>
                                <div class="flex items-center gap-2 mt-6">
                                  <button class="btn btn-primary opacity-50 cursor-not-allowed" id="generate-signin-report" disabled>
                                    <i data-lucide="file-text" class="h-4 w-4 mr-2"></i>
                                    Generate Sheet
                                  </button>
                                    <button class="btn btn-outline" id="export-signin-pdf">
                                        <i data-lucide="download" class="h-4 w-4"></i>
                                        Export PDF
                                    </button>
                                    <button class="btn btn-outline" id="export-signin-excel">
                                        <i data-lucide="download" class="h-4 w-4"></i>
                                        Export Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-content p-4">
                        <div id="signin-report-container" class="rounded-md border overflow-x-auto p-4" style="display:none;">
                            <div class="flex justify-between items-start mb-4">
                                <div class="text-left">
                                    <div class="font-semibold">REGISTRY: <span id="signin-report-registry"></span></div>
                                    <div class="font-semibold">BATCH: <span id="signin-report-batch"></span></div>
                                </div>
                                <div class="text-center flex-1">
                                    <h4 class="font-semibold text-lg">SIGN IN & OUT SHEET</h4>
                                </div>
                                <div class="w-32"></div> <!-- Spacer to balance the layout -->
                            </div>
                            <table class="w-full text-sm text-left border-collapse" id="signin-report-table">
                                <thead class="bg-gray-50">
                  <tr>
                    <th class="p-2">SN</th>
                    <th class="p-2">FILE NO</th>
                    <th class="p-2">FILE NAME</th>
                    <th class="p-2">PLOTNO</th>
                    <th class="p-2">DISTRICT</th>
                    <th class="p-2">LGA</th>
                    <th class="p-2">INDEXED DATE</th>
                    <th class="p-2">INDEXED BY</th>
                  </tr>
                                </thead>
                                <tbody></tbody>
                            </table>

                            <div class="mt-6 flex justify-between gap-8">
                                <div style="width:45%;">
                                    <div class="border-t mt-8 pt-4">
                                        <div class="text-sm font-semibold">SIGN IN (KLAES MDC)</div>
                                        <div class="mt-3">SIGNED BY: ____________________________</div>
                                        <div class="text-sm mt-2">DATE RECEIVED: _______________________</div>
                                        <div class="text-sm mt-2">DATE SUBMITTED: ______________________</div>
                                    </div>
                                </div>

                                <div style="width:45%;">
                                    <div class="border-t mt-8 pt-4">
                                        <div class="text-sm font-semibold">SIGN OUT (MINISTRY)</div>
                                        <div class="mt-3">SIGNED BY: ____________________________</div>
                                        <div class="text-sm mt-2">DATE SUBMITTED: ______________________</div>
                                        <div class="text-sm mt-2">DATE RECEIVED: _______________________</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- jsPDF + autotable CDN for client-side PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const batchSelect = document.getElementById('signin-batch-select');
    const generateBtn = document.getElementById('generate-signin-report');
    const reportContainer = document.getElementById('signin-report-container');
    const reportTableBody = document.querySelector('#signin-report-table tbody');
    const reportRegistrySpan = document.getElementById('signin-report-registry');
    const reportBatchSpan = document.getElementById('signin-report-batch');
    const exportPdf = document.getElementById('export-signin-pdf');
    const exportExcel = document.getElementById('export-signin-excel');

    // Helper: normalize/format date strings to `YYYY-MM-DD HH:MM:SS`
    function formatDateTime(input) {
      if (!input) return '';
      // If already in the form YYYY-MM-DD HH:MM:SS or similar, normalize fractions/Z/T
      let s = String(input).trim();
      // Handle ISO8601 like 2025-09-06T15:16:07Z or with offset
      s = s.replace('T', ' ');
      // Remove timezone Z or offsets like +00:00
      s = s.replace(/Z|\+[0-9:\-]+|-[0-9:\-]+$/, '').trim();
      // Remove fractional seconds (e.g. .123456)
      s = s.replace(/\.[0-9]+$/, '');
      // If only date present, add midnight
      if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        return s + ' 00:00:00';
      }
      // If time has only hours/minutes, append :00
      if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(s)) {
        return s + ':00';
      }
      // If matches full datetime with seconds, return as-is (truncate extra chars)
      const m = s.match(/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/);
      if (m) return m[1];
      // Fallback: try Date parsing and format in local timezone
      const dt = new Date(s);
      if (!isNaN(dt.getTime())) {
        const yyyy = dt.getFullYear();
        const mm = String(dt.getMonth()+1).padStart(2,'0');
        const dd = String(dt.getDate()).padStart(2,'0');
        const hh = String(dt.getHours()).padStart(2,'0');
        const mi = String(dt.getMinutes()).padStart(2,'0');
        const ss = String(dt.getSeconds()).padStart(2,'0');
        return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
      }
      return s;
    }

    // Load distinct batch_no values from server
    fetch('/fileindexing/distinct-batches')
      .then(r => r.json())
      .then(data => {
        if (data.success && data.batches) {
          data.batches.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b;
            opt.textContent = 'Batch ' + b;
            batchSelect.appendChild(opt);
          });
        }
      }).catch(e => console.error(e));

    generateBtn.addEventListener('click', function() {
      const batch = batchSelect.value;
      if (!batch) { alert('Select a batch number'); return; }
      fetch(`/fileindexing/signin-report?batch_no=${batch}`)
        .then(r => r.json())
        .then(data => {
          if (!data.success) { alert(data.message || 'Failed to load report'); return; }
          console.log('Debug - Received data:', data);
          console.log('Debug - First row:', data.rows[0]);
          reportTableBody.innerHTML = '';
          
          // Get registry/batch from first row for header (assuming all rows have same registry/batch)
          let registryBatch = '';
          if (data.rows.length > 0) {
            const firstRow = data.rows[0];
            registryBatch = (firstRow.registry || '') + (firstRow.registry && firstRow.batch_no ? '/' : '') + (firstRow.batch_no || '');
          }
          
          data.rows.forEach((row, idx) => {
            const tr = document.createElement('tr');
            const formattedDate = formatDateTime(row.indexed_date || '');
            console.log(`Debug row ${idx + 1}: registry='${row.registry}', batch_no='${row.batch_no}'`);
            tr.innerHTML = `
              <td class="p-2">${idx + 1}</td>
              <td class="p-2">${row.file_number || ''}</td>
              <td class="p-2">${row.name || ''}</td>
              <td class="p-2">${row.plot_number || ''}</td>
              <td class="p-2">${row.district || ''}</td>
              <td class="p-2">${row.lga || ''}</td>
              <td class="p-2">${formattedDate}</td>
              <td class="p-2">${row.indexed_by || ''}</td>
            `;
            reportTableBody.appendChild(tr);
          });
          
          // Update header with registry and batch info separately
          if (data.rows.length > 0) {
            const firstRow = data.rows[0];
            if (reportRegistrySpan) {
              reportRegistrySpan.textContent = firstRow.registry || '';
            }
            if (reportBatchSpan) {
              reportBatchSpan.textContent = firstRow.batch_no || '';
            }
          }
          reportContainer.style.display = 'block';
        }).catch(e => { console.error(e); alert('Failed to load report'); });
    });

    exportPdf.addEventListener('click', async function() {
      const batch = batchSelect.value; if (!batch) { alert('Select a batch'); return; }
      try {
        const resp = await fetch(`/fileindexing/signin-report?batch_no=${batch}`);
        const data = await resp.json();
        if (!data.success) { alert(data.message || 'Failed to load report'); return; }
        const rows = data.rows || [];

        // Get registry/batch from first row for title (assuming all rows have same registry/batch)
        let registryBatch = '';
        if (rows.length > 0) {
          const firstRow = rows[0];
          registryBatch = (firstRow.registry || '') + (firstRow.registry && firstRow.batch_no ? '/' : '') + (firstRow.batch_no || '');
        }
        
        // Prepare head and body for autoTable
        const head = [['SN','FILE NO','FILE NAME','PLOTNO','DISTRICT','LGA','INDEXED DATE','INDEXED BY']];
        const body = rows.map((r, i) => [
          i+1,
          r.file_number || '',
          r.name || '',
          r.plot_number || '',
          r.district || '',
          r.lga || '',
          formatDateTime(r.indexed_date || ''),
          r.indexed_by || ''
        ]);

        // Use jsPDF UMD
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
        
        // Get separate registry and batch values
        let registry = '', batchNo = '';
        if (rows.length > 0) {
          registry = rows[0].registry || '';
          batchNo = rows[0].batch_no || '';
        }
        
        doc.setFontSize(10);
        doc.text(`REGISTRY: ${registry}`, 40, 30);
        doc.text(`BATCH: ${batchNo}`, 40, 45);
        
        doc.setFontSize(14);
        doc.text('SIGN IN & OUT SHEET', 400, 35); // Centered title
        // autoTable will start below the title
        doc.autoTable({
          head: head,
          body: body,
          startY: 60,
          styles: { 
            fontSize: 10, 
            cellPadding: 4,
            lineColor: [0, 0, 0],
            lineWidth: 0.5
          },
          headStyles: { 
            fillColor: [200,200,200], 
            textColor: [0,0,0],
            lineColor: [0, 0, 0],
            lineWidth: 0.5
          },
          bodyStyles: {
            lineColor: [0, 0, 0],
            lineWidth: 0.5
          },
          columnStyles: {
            // Removed Registry/Batch column, no special column styling needed
          },
          theme: 'grid',
          margin: { left: 20, right: 20 }
        });

        // Add signature blocks at bottom
        const finalY = doc.lastAutoTable ? doc.lastAutoTable.finalY + 40 : doc.internal.pageSize.getHeight() - 120;
        const leftX = 40;
        const rightX = doc.internal.pageSize.getWidth() / 2 + 20;
        const signatureWidth = 200;
        
        // Sign In (KLAES MDC) - Left side
        doc.setFontSize(10);
        doc.text('SIGN IN (KLAES MDC)', leftX, finalY);
        doc.text('SIGNED BY: ____________________________', leftX, finalY + 20);
        doc.text('DATE RECEIVED: _______________________', leftX, finalY + 35);
        doc.text('DATE SUBMITTED: ______________________', leftX, finalY + 50);
        
        // Sign Out (Ministry) - Right side
        doc.text('SIGN OUT (MINISTRY)', rightX, finalY);
        doc.text('SIGNED BY: ____________________________', rightX, finalY + 20);
        doc.text('DATE SUBMITTED: ______________________', rightX, finalY + 35);
        doc.text('DATE RECEIVED: _______________________', rightX, finalY + 50);

        doc.save(`signin_batch_${batch}.pdf`);
      } catch (err) {
        console.error(err);
        alert('Failed to generate PDF client-side');
      }
    });

    exportExcel.addEventListener('click', function() {
      const batch = batchSelect.value; if (!batch) { alert('Select a batch'); return; }
      window.open(`/fileindexing/signin-report/export/excel?batch_no=${batch}`,'_blank');
    });
});
</script>
@endsection
