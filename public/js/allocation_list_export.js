/**
 * Allocation List Report Export
 *
 * Register-style PDF and CSV export for captured land allocations, built on the
 * same footing as the Instrument Capture Register export: a filtered preview is
 * fetched once, and both downloads are generated from that exact array, so what
 * the user sees in the modal is what lands in the file.
 *
 * The register runs to many pages, so the ministry header and the coat-of-arms
 * watermark are repainted inside autoTable's didDrawPage hook rather than drawn
 * once up front — every page carries both, including the pages autoTable adds
 * on its own when a group overflows.
 */

window.aleExportData = [];

const ALE_EXPORT_TD = 'px-4 py-3 text-sm text-gray-600';

/* ── Columns ────────────────────────────────────────────────────────────────
   A4 landscape usable width is ~277mm at 10mm side margins. The fixed widths
   below total 202mm, which leaves Location roughly 75mm to wrap into — enough
   for "YAR GAYA EXT, DAWAKIN KUDU" on one line.                              */
function aleExportColumns() {
    return [
        { key: 'SN',              label: 'S/N',           preview: true, pdfWidth: 10, noWrap: true },
        { key: 'file_no',         label: 'File No',       preview: true, pdfWidth: 28 },
        { key: 'file_title',      label: 'File Title',    preview: true, pdfWidth: 42 },
        { key: 'allottee_name',   label: 'Allottee Name', preview: true, pdfWidth: 42 },
        { key: 'plot_number',     label: 'Plot No',       preview: true, pdfWidth: 16 },
        { key: 'location',        label: 'Location',      preview: true, pdfWidth: 'auto' },
        { key: 'allocation_year', label: 'Year',          preview: true, pdfWidth: 12, noWrap: true },
        { key: 'captured_on',     label: 'Captured On',   preview: true, pdfWidth: 22, noWrap: true },
        // The key stays captured_by (what the endpoint returns); the label
        // matches the "Created By" header on the main Allocation List table so
        // the two read as the same column.
        { key: 'captured_by',     label: 'Created By',    preview: true, pdfWidth: 30 },
        // Carried in the CSV only — the PDF already says it via Location.
        { key: 'district',        label: 'District', csvOnly: true },
        { key: 'lga',             label: 'LGA',      csvOnly: true },
        { key: 'state',           label: 'State',    csvOnly: true }
    ];
}

function aleEscapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function aleCsvValue(value) {
    return '"' + String(value ?? '').replace(/"/g, '""') + '"';
}

// A blank cell in a printed register reads as an omission, so empty values get
// a visible dash. Plain "-" rather than an em dash: jsPDF's built-in helvetica
// encodes WinAnsi, and a stray glyph in a legal register is worse than a dash.
function aleExportValue(item, column, fallback) {
    if (fallback === undefined) fallback = '-';
    const value = item[column.key];
    return (value === null || value === undefined || String(value).trim() === '') ? fallback : value;
}

function aleExportFilterValues() {
    return {
        year:     document.getElementById('aleExportYear')?.value || '',
        lga:      document.getElementById('aleExportLga')?.value || '',
        district: document.getElementById('aleExportDistrict')?.value || '',
        start:    document.getElementById('aleExportStartDate')?.value || '',
        end:      document.getElementById('aleExportEndDate')?.value || '',
        groupBy:  document.getElementById('aleExportGroupBy')?.value || ''
    };
}

/* ── Library loading ────────────────────────────────────────────────────── */

function aleLoadScript(src, isReady) {
    return new Promise(function (resolve, reject) {
        if (isReady()) { resolve(); return; }
        const tag = document.createElement('script');
        tag.src = src;
        tag.async = true;
        tag.onload = function () {
            isReady() ? resolve() : reject(new Error('Loaded ' + src + ' but it did not initialise.'));
        };
        tag.onerror = function () { reject(new Error('Unable to load ' + src)); };
        document.head.appendChild(tag);
    });
}

window.aleEnsurePdfLibs = function () {
    if (window.__alePdfLibs) return window.__alePdfLibs;

    const jsPdfReady = () => !!(window.jspdf?.jsPDF || window.jspdf?.default?.jsPDF);

    window.__alePdfLibs = aleLoadScript(
        'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
        jsPdfReady
    ).then(function () {
        const Ctor = window.jspdf.jsPDF || window.jspdf.default.jsPDF;
        // autoTable registers itself onto the jsPDF prototype.
        return aleLoadScript(
            'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js',
            () => typeof Ctor.API?.autoTable === 'function'
        ).then(() => Ctor);
    });

    return window.__alePdfLibs;
};

/* ── Modal ──────────────────────────────────────────────────────────────── */

window.aleOpenExportModal = function () {
    const modal = document.getElementById('aleExportModal');
    if (!modal) return;

    modal.classList.remove('hidden');
    window.aleExportData = [];

    const count = document.getElementById('aleExportCount');
    if (count) count.textContent = '0';

    aleLoadExportFilterOptions();

    const body = document.getElementById('aleExportPreviewBody');
    if (body) {
        // Derived, not hard-coded, so adding a preview column cannot leave this
        // placeholder spanning the wrong number of cells.
        const span = aleExportColumns().filter(function (c) { return c.preview; }).length;
        body.innerHTML =
            '<tr>' +
            '<td colspan="' + span + '" class="px-6 py-20 text-center text-gray-500">' +
            '<div class="flex flex-col items-center gap-3 justify-center max-w-md mx-auto">' +
            '<div class="bg-blue-50 text-blue-600 p-4 rounded-full shadow-inner">' +
            '<i data-lucide="file-text" class="h-8 w-8"></i></div>' +
            '<h4 class="text-base font-black text-gray-700 tracking-wide uppercase">Allocation Register</h4>' +
            '<p class="text-xs text-gray-500 leading-relaxed">Narrow the register by <strong>Year</strong>, ' +
            '<strong>LGA</strong>, <strong>District</strong> or a <strong>capture date range</strong>, then press ' +
            '<strong class="text-blue-600">Load Preview</strong> to compile the report.</p>' +
            '</div></td></tr>';
        if (window.lucide) window.lucide.createIcons();
    }
};

window.aleCloseExportModal = function () {
    document.getElementById('aleExportModal')?.classList.add('hidden');
};

// Only offer filter values that actually exist in the table, so a chosen filter
// can never come back empty.
async function aleLoadExportFilterOptions() {
    if (window.__aleFiltersLoaded) return;
    try {
        const resp = await fetch(window.ALE.urls.exportFilters);
        const json = await resp.json();
        if (!json.success) return;

        const fill = function (id, values, allLabel) {
            const select = document.getElementById(id);
            if (!select) return;
            select.innerHTML = '<option value="">' + allLabel + '</option>' +
                (values || []).map(function (v) {
                    return '<option value="' + aleEscapeHtml(v) + '">' + aleEscapeHtml(v) + '</option>';
                }).join('');
        };

        fill('aleExportYear', json.years, 'All Years');
        fill('aleExportLga', json.lgas, 'All LGAs');
        fill('aleExportDistrict', json.districts, 'All Districts');
        window.__aleFiltersLoaded = true;
    } catch (e) {
        console.warn('Export filter options failed to load', e);
    }
}

window.aleLoadExportPreview = async function () {
    const body = document.getElementById('aleExportPreviewBody');
    if (!body) return;

    const f = aleExportFilterValues();
    const columns = aleExportColumns().filter(function (c) { return c.preview; });

    body.innerHTML =
        '<tr><td colspan="' + columns.length + '" class="px-6 py-12 text-center text-gray-500 italic">' +
        '<div class="flex flex-col items-center gap-2">' +
        '<i class="fas fa-spinner fa-spin text-3xl text-blue-500"></i>' +
        '<span>Compiling the register&hellip;</span></div></td></tr>';

    try {
        const params = new URLSearchParams();
        if (f.year) params.set('year', f.year);
        if (f.lga) params.set('lga', f.lga);
        if (f.district) params.set('district', f.district);
        if (f.start) params.set('start_date', f.start);
        if (f.end) params.set('end_date', f.end);

        const resp = await fetch(window.ALE.urls.export + '?' + params.toString());
        const json = await resp.json();
        if (!json.success) throw new Error(json.error || 'Failed to fetch the register.');

        // Number the rows once, here, so the preview and both downloads share
        // one S/N sequence.
        window.aleExportData = (json.data || []).map(function (item, i) {
            return Object.assign({}, item, { SN: i + 1 });
        });

        const count = document.getElementById('aleExportCount');
        if (count) count.textContent = window.aleExportData.length;

        if (window.aleExportData.length === 0) {
            body.innerHTML = '<tr><td colspan="' + columns.length +
                '" class="px-6 py-8 text-center text-gray-500">No allocations match these filters.</td></tr>';
            return;
        }

        body.innerHTML = window.aleExportData.map(function (item) {
            const cells = columns.map(function (column) {
                const value = aleExportValue(item, column, '—');
                const isLong = ['file_title', 'allottee_name', 'location'].indexOf(column.key) !== -1;
                const classes = [
                    ALE_EXPORT_TD,
                    isLong ? 'truncate max-w-[180px]' : '',
                    column.key === 'file_no' ? 'font-mono font-semibold text-gray-800' : ''
                ].filter(Boolean).join(' ');
                return '<td class="' + classes + '" title="' + aleEscapeHtml(value) + '">' +
                       aleEscapeHtml(value) + '</td>';
            }).join('');
            return '<tr class="hover:bg-gray-50 transition-colors">' + cells + '</tr>';
        }).join('');
    } catch (e) {
        body.innerHTML = '<tr><td colspan="' + columns.length +
            '" class="px-6 py-8 text-center text-red-500">Error: ' + aleEscapeHtml(e.message) + '</td></tr>';
    }
};

/* ── Filenames and labels ───────────────────────────────────────────────── */

function aleExportFilename(extension) {
    const f = aleExportFilterValues();
    let name = 'Allocation_List_Register';
    if (f.year) name += '_' + f.year;
    if (f.lga) name += '_' + String(f.lga).replace(/\s+/g, '_');
    if (f.district) name += '_' + String(f.district).replace(/\s+/g, '_');
    if (f.start) name += '_from_' + f.start;
    if (f.end) name += '_to_' + f.end;
    return name + '_' + new Date().toISOString().split('T')[0] + '.' + extension;
}

function aleScopeLabel() {
    const f = aleExportFilterValues();
    const bits = [f.year ? 'Year: ' + f.year : 'All Years'];
    if (f.lga) bits.push('LGA: ' + f.lga);
    if (f.district) bits.push('District: ' + f.district);
    if (f.start && f.end) bits.push('Captured: ' + f.start + ' to ' + f.end);
    else if (f.start) bits.push('Captured from: ' + f.start);
    else if (f.end) bits.push('Captured to: ' + f.end);
    return bits.join(' | ');
}

function aleGroupLabel(groupBy) {
    if (groupBy === 'allocation_year') return 'Year';
    if (groupBy === 'lga') return 'LGA';
    if (groupBy === 'district') return 'District';
    return '';
}

/* ── CSV ────────────────────────────────────────────────────────────────── */

window.aleDownloadExportCsv = function () {
    if (!window.aleExportData || !window.aleExportData.length) {
        Swal.fire('Nothing to Export', 'Load a preview first.', 'warning');
        return;
    }

    const columns = aleExportColumns();
    const csv = [columns.map(function (c) { return c.label; }).join(',')]
        .concat(window.aleExportData.map(function (item) {
            return columns.map(function (c) { return aleCsvValue(aleExportValue(item, c, '')); }).join(',');
        }))
        .join('\n');

    // BOM so Excel opens the file as UTF-8 rather than the system codepage.
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = aleExportFilename('csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
};

/* ── PDF ────────────────────────────────────────────────────────────────── */

// Fetch an image and hand jsPDF a data URL; a failed logo resolves to null so a
// missing asset costs the report its crest, not the whole download.
function aleLoadImage(url) {
    return fetch(url)
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.blob(); })
        .then(function (blob) {
            return new Promise(function (resolve) {
                const reader = new FileReader();
                reader.onloadend = function () { resolve(reader.result); };
                reader.onerror = function () { resolve(null); };
                reader.readAsDataURL(blob);
            });
        })
        .catch(function (e) { console.warn('Report image failed to load:', url, e); return null; });
}

window.aleDownloadExportPdf = async function () {
    if (!window.aleExportData || !window.aleExportData.length) {
        Swal.fire('Nothing to Export', 'Load a preview first.', 'warning');
        return;
    }

    let jsPDFCtor;
    try {
        jsPDFCtor = await window.aleEnsurePdfLibs();
    } catch (e) {
        console.error('PDF library error:', e);
        Swal.fire('Error', 'Could not load the PDF engine: ' + e.message, 'error');
        return;
    }

    const images = await Promise.all([
        aleLoadImage('/assets/logo/ministry1.jpg'),
        aleLoadImage('/assets/logo/ministry2.jpeg'),
        aleLoadImage('/assets/logo/Nigerian-Coat-of-Arms.png')
    ]);
    const leftLogo = images[0];
    const rightLogo = images[1];
    const watermark = images[2];

    try {
        const doc = new jsPDFCtor({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const pageCenter = pageWidth / 2;
        const logoSize = 22;

        const f = aleExportFilterValues();
        const scopeLabel = aleScopeLabel();
        const groupLabel = aleGroupLabel(f.groupBy);
        const generatedOn = new Date().toLocaleDateString();
        const totalRecords = window.aleExportData.length;

        /* Every page of a multi-page register carries the full ministry
           letterhead — a loose page pulled from the middle of the bundle has to
           stand on its own as an official document. */
        function paintHeader() {
            if (leftLogo)  doc.addImage(leftLogo, 'JPEG', 10, 8, logoSize, logoSize);
            if (rightLogo) doc.addImage(rightLogo, 'JPEG', pageWidth - 10 - logoSize, 8, logoSize, logoSize);

            doc.setFont('helvetica', 'bold');
            doc.setTextColor(0, 0, 0);
            doc.setFontSize(16);
            doc.text('KANO STATE GOVERNMENT', pageCenter, 14, { align: 'center' });
            doc.setFontSize(12);
            doc.text('MINISTRY OF LAND AND PHYSICAL PLANNING', pageCenter, 20, { align: 'center' });
            doc.setFontSize(11);
            doc.text('LAND DEPARTMENT', pageCenter, 26, { align: 'center' });
            doc.setLineWidth(0.5);
            doc.line(10, 32, pageWidth - 10, 32);
        }

        /* Painted per page from didDrawPage, i.e. after that page's table body
           is down, so the crest reads through the cells at low opacity instead
           of being buried under their white fill. */
        function paintWatermark() {
            if (!watermark) return;
            try {
                const size = 120;
                const x = (pageWidth - size) / 2;
                const y = (pageHeight - size) / 2;
                if (typeof doc.GState === 'function' && typeof doc.setGState === 'function') {
                    doc.setGState(new doc.GState({ opacity: 0.08 }));
                    doc.addImage(watermark, 'PNG', x, y, size, size);
                    doc.setGState(new doc.GState({ opacity: 1 }));
                } else {
                    doc.addImage(watermark, 'PNG', x, y, size, size);
                }
            } catch (e) {
                console.warn('Watermark draw failed', e);
            }
        }

        const columns = aleExportColumns().filter(function (c) { return c.preview; });
        const head = [columns.map(function (c) { return c.label; })];
        const columnStyles = {};
        columns.forEach(function (column, i) {
            columnStyles[i] = {};
            if (column.pdfWidth && column.pdfWidth !== 'auto') columnStyles[i].cellWidth = column.pdfWidth;
            if (column.noWrap) columnStyles[i].overflow = 'hidden';
            // The file number is the row's identifier -- monospaced so digits line
            // up down the column, and bold so it reads as the key at a glance.
            if (column.key === 'file_no') {
                columnStyles[i].font = 'courier';
                columnStyles[i].fontStyle = 'bold';
            }
        });

        // Optional grouping puts each year / LGA / district on its own run of
        // pages, the way the paper register is bound in sections.
        const groups = new Map();
        if (f.groupBy) {
            window.aleExportData.forEach(function (item) {
                const key = String(item[f.groupBy] ?? '').trim() || 'Unspecified';
                if (!groups.has(key)) groups.set(key, []);
                groups.get(key).push(item);
            });
        } else {
            groups.set('', window.aleExportData);
        }

        let currentGroup = '';
        let firstTable = true;

        groups.forEach(function (items, groupKey) {
            if (!firstTable) doc.addPage();
            firstTable = false;
            currentGroup = groupKey;

            doc.autoTable({
                head: head,
                body: items.map(function (item) {
                    return columns.map(function (c) { return aleExportValue(item, c); });
                }),
                startY: 50,
                theme: 'grid',
                styles: { fontSize: 8, cellPadding: 1.5, overflow: 'linebreak', valign: 'middle' },
                headStyles: { fillColor: [37, 99, 235], textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center' },
                alternateRowStyles: { fillColor: [248, 250, 252] },
                columnStyles: columnStyles,
                margin: { top: 50, left: 10, right: 10, bottom: 14 },
                didDrawPage: function () {
                    paintHeader();

                    const suffix = currentGroup ? ' — ' + groupLabel + ': ' + currentGroup : '';

                    doc.setFont('helvetica', 'bold');
                    doc.setFontSize(13);
                    doc.setTextColor(0, 0, 0);
                    doc.text('Allocation List Register' + suffix, 14, 39);

                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(10);
                    doc.text(scopeLabel + ' | Total Records: ' + totalRecords +
                             ' | Generated on: ' + generatedOn, 14, 45);

                    paintWatermark();

                    // Only the left half of the footer is drawn here. "Page N
                    // of M" needs a total that is not known until every group
                    // has been laid out, so it is stamped in the second pass
                    // below. Writing a "{total}" placeholder and covering it
                    // with a white rectangle would leave the placeholder in the
                    // content stream, where copy-paste and search still find it.
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(8);
                    doc.setTextColor(100, 100, 100);
                    doc.text('Kano State Land Administration System', 10, pageHeight - 5);
                }
            });
        });

        // Second pass: the page count is known now, so the right-hand footer
        // can be written once, cleanly, with nothing underneath it.
        const totalPages = doc.internal.getNumberOfPages();
        for (let p = 1; p <= totalPages; p++) {
            doc.setPage(p);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(100, 100, 100);
            doc.text('Page ' + p + ' of ' + totalPages, pageWidth - 10, pageHeight - 5, { align: 'right' });
        }

        doc.save(aleExportFilename('pdf'));
    } catch (e) {
        console.error('PDF generation error:', e);
        Swal.fire('Error', 'Failed to generate the PDF: ' + e.message, 'error');
    }
};
