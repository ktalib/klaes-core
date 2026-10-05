/**
 * Land Registry register export (PDF / CSV).
 *
 * Cloned from instrument_capture_export.js and cut down to this register's one
 * instrument. Unlike the Deeds export it does not call the server: the register
 * page already holds every row, and passes them in as window.landRegisterExportRows
 * (with window.landRegisterExportConfig for the titles and logo URLs).
 */
(function () {
    var TH_CLASS = 'px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50';
    var TD_CLASS = 'px-4 py-3 text-sm text-gray-600 whitespace-nowrap';

    var filtered = [];

    function cfg() {
        return window.landRegisterExportConfig || {};
    }

    // Note: A4 landscape usable width is ~277mm with 10mm side margins.
    // Fixed widths stay well under that so District gets the remainder.
    function columns() {
        var c = cfg();
        return [
            { key: 'SN', label: 'S/N', pdfWidth: 10 },
            { key: 'fileno', label: 'File No', pdfWidth: 24 },
            { key: 'reg_particulars', label: 'Reg Particulars', pdfWidth: 20 },
            { key: 'vendor', label: c.firstParty || 'Vendor', pdfWidth: 30 },
            { key: 'purchaser', label: c.secondParty || 'Purchaser', pdfWidth: 30 },
            { key: 'amount', label: 'Amount', pdfWidth: 22 },
            { key: 'receipt_no', label: 'Receipt No', pdfWidth: 18 },
            { key: 'transaction_date', label: 'Transaction Date', pdfWidth: 18 },
            { key: 'reg_date_display', label: 'Reg Date', pdfWidth: 18 },
            { key: 'plot_number', label: 'Plot No', pdfWidth: 14 },
            { key: 'size', label: 'Plot Size', pdfWidth: 12 },
            { key: 'district', label: 'District', pdfWidth: 'auto' },
            { key: 'lga', label: 'LGA', pdfWidth: 20 },
            { key: 'registered_by', label: 'Registered By', csvOnly: true }
        ];
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function csvValue(value) {
        return '"' + String(value == null ? '' : value).replace(/"/g, '""') + '"';
    }

    function filterValues() {
        return {
            status: document.getElementById('landExportStatusFilter')?.value || '',
            volume: document.getElementById('landExportVolumeFilter')?.value || '',
            startDate: document.getElementById('landExportStartDate')?.value || '',
            endDate: document.getElementById('landExportEndDate')?.value || ''
        };
    }

    // reg_date is Y-m-d (or empty), so plain string comparison is a date comparison.
    function applyFilters(rows, f) {
        return rows.filter(function (row) {
            if (f.status && row.status !== f.status) return false;
            if (f.volume && String(row.volume_no || '') !== f.volume) return false;
            if (f.startDate && (!row.reg_date || row.reg_date < f.startDate)) return false;
            if (f.endDate && (!row.reg_date || row.reg_date > f.endDate)) return false;
            return true;
        }).map(function (row, index) {
            return Object.assign({}, row, { SN: index + 1 });
        });
    }

    function fileStem(f) {
        var name = (cfg().filePrefix || 'Land_Register');
        if (f.status) name += '_' + f.status;
        if (f.volume) name += '_Vol_' + f.volume;
        if (f.startDate) name += '_from_' + f.startDate;
        if (f.endDate) name += '_to_' + f.endDate;
        return name + '_' + new Date().toISOString().split('T')[0];
    }

    window.loadLandExportPreview = function () {
        var body = document.getElementById('landExportPreviewBody');
        if (!body) return;

        var cols = columns();
        var head = document.querySelector('#landExportPreviewTable thead tr');
        if (head) {
            head.innerHTML = cols.map(function (c) {
                return '<th class="' + TH_CLASS + '">' + escapeHtml(c.label) + '</th>';
            }).join('');
        }

        filtered = applyFilters(window.landRegisterExportRows || [], filterValues());
        document.getElementById('landExportRecordCount').textContent = filtered.length.toLocaleString();

        if (filtered.length === 0) {
            body.innerHTML = '<tr><td colspan="' + cols.length + '" class="px-6 py-8 text-center text-gray-500">No records found matching filters.</td></tr>';
            return;
        }

        body.innerHTML = filtered.map(function (row) {
            return '<tr class="hover:bg-gray-50">' + cols.map(function (c) {
                var v = row[c.key];
                return '<td class="' + TD_CLASS + '">' + escapeHtml(v == null || v === '' ? '' : v) + '</td>';
            }).join('') + '</tr>';
        }).join('');
    };

    window.openLandExportModal = function () {
        var modal = document.getElementById('landExportPreviewModal');
        if (!modal) return;

        // Volumes offered are the ones this register actually holds.
        var volSelect = document.getElementById('landExportVolumeFilter');
        if (volSelect && volSelect.options.length <= 1) {
            var vols = {};
            (window.landRegisterExportRows || []).forEach(function (r) {
                if (r.volume_no !== null && r.volume_no !== undefined && r.volume_no !== '') {
                    vols[String(r.volume_no)] = true;
                }
            });
            Object.keys(vols)
                .sort(function (a, b) { return (parseInt(a, 10) || 0) - (parseInt(b, 10) || 0); })
                .forEach(function (v) {
                    var opt = document.createElement('option');
                    opt.value = v;
                    opt.textContent = v;
                    volSelect.appendChild(opt);
                });
        }

        modal.classList.remove('hidden');
        window.loadLandExportPreview();
    };

    window.closeLandExportModal = function () {
        document.getElementById('landExportPreviewModal')?.classList.add('hidden');
    };

    window.downloadLandExportCsv = function () {
        if (!filtered.length) {
            Swal.fire('No Data', 'There is no data to export.', 'warning');
            return;
        }

        var cols = columns();
        var csv = [cols.map(function (c) { return csvValue(c.label); }).join(',')]
            .concat(filtered.map(function (row) {
                return cols.map(function (c) { return csvValue(row[c.key]); }).join(',');
            }))
            .join('\n');

        // BOM so Excel opens names with accents correctly.
        var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = fileStem(filterValues()) + '.csv';
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    function ensureJsPdf() {
        var ready = window.jspdf?.jsPDF || window.jspdf?.default?.jsPDF;
        if (ready) return Promise.resolve(ready);
        return new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
            script.onload = function () {
                var r = window.jspdf?.jsPDF || window.jspdf?.default?.jsPDF;
                r ? resolve(r) : reject(new Error('jsPDF failed to initialise.'));
            };
            script.onerror = function () { reject(new Error('Unable to load jsPDF from the CDN.')); };
            document.head.appendChild(script);
        });
    }

    function loadImage(url) {
        if (!url) return Promise.resolve(null);
        return fetch(url)
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.blob();
            })
            .then(function (blob) {
                return new Promise(function (resolve) {
                    var reader = new FileReader();
                    reader.onloadend = function () { resolve(reader.result); };
                    reader.onerror = function () { resolve(null); };
                    reader.readAsDataURL(blob);
                });
            })
            .catch(function (e) {
                console.warn('Failed to load logo:', url, e);
                return null;
            });
    }

    window.downloadLandExportPdf = async function () {
        if (!filtered.length) {
            Swal.fire('No Data', 'There is no data to export.', 'warning');
            return;
        }

        var jsPDFCtor;
        try {
            jsPDFCtor = await ensureJsPdf();
        } catch (e) {
            Swal.fire('Error', 'Failed to generate PDF: ' + e.message, 'error');
            return;
        }

        var c = cfg();
        var logos = await Promise.all([loadImage(c.leftLogo), loadImage(c.rightLogo), loadImage(c.watermark)]);

        try {
            var doc = new jsPDFCtor({ orientation: 'landscape', unit: 'mm', format: 'a4' });
            if (typeof doc.autoTable !== 'function') {
                throw new Error('The PDF table plugin (jspdf-autotable) is not loaded.');
            }

            var pageWidth = doc.internal.pageSize.getWidth();
            var pageHeight = doc.internal.pageSize.getHeight();
            var pageCenter = pageWidth / 2;
            var logoSize = 22;
            var f = filterValues();

            var period = '';
            if (f.startDate && f.endDate) period = ' | Period: ' + f.startDate + ' to ' + f.endDate;
            else if (f.startDate) period = ' | From: ' + f.startDate;
            else if (f.endDate) period = ' | To: ' + f.endDate;

            var metaLine = 'Volume: ' + (f.volume || 'All Volumes')
                + ' | Status: ' + (f.status ? f.status.charAt(0).toUpperCase() + f.status.slice(1) : 'All')
                + period
                + ' | Generated on: ' + new Date().toLocaleDateString();
            var title = (c.title || 'Register');
            var totalLabel = 'Total Records: ' + filtered.length.toLocaleString();

            function paintPage() {
                if (logos[0]) doc.addImage(logos[0], 'JPEG', 10, 8, logoSize, logoSize);
                if (logos[1]) doc.addImage(logos[1], 'JPEG', pageWidth - 10 - logoSize, 8, logoSize, logoSize);

                doc.setFont('helvetica', 'bold');
                doc.setTextColor(0, 0, 0);
                doc.setFontSize(16);
                doc.text('KANO STATE GOVERNMENT', pageCenter, 14, { align: 'center' });
                doc.setFontSize(12);
                doc.text('MINISTRY OF LAND AND PHYSICAL PLANNING', pageCenter, 20, { align: 'center' });
                doc.setFontSize(11);
                doc.text(c.department || 'LAND DEPARTMENT', pageCenter, 26, { align: 'center' });
                doc.setLineWidth(0.5);
                doc.line(10, 32, pageWidth - 10, 32);

                doc.setFontSize(13);
                doc.text(title, 14, 39);
                doc.setFontSize(11);
                doc.text(totalLabel, pageWidth - 10, 39, { align: 'right' });
                doc.setFontSize(10);
                doc.setFont('helvetica', 'normal');
                doc.text(metaLine, 14, 45);

                // Watermark drawn after the table body, at low opacity, so the
                // white cells do not hide it.
                if (logos[2]) {
                    try {
                        var wm = 120;
                        if (typeof doc.GState === 'function' && typeof doc.setGState === 'function') {
                            doc.setGState(new doc.GState({ opacity: 0.08 }));
                            doc.addImage(logos[2], 'PNG', (pageWidth - wm) / 2, (pageHeight - wm) / 2, wm, wm);
                            doc.setGState(new doc.GState({ opacity: 1 }));
                        }
                    } catch (e) {
                        console.warn('Watermark draw failed', e);
                    }
                }
            }

            var cols = columns().filter(function (col) { return !col.csvOnly; });
            var columnStyles = {};
            cols.forEach(function (col, i) {
                columnStyles[i] = { cellWidth: col.pdfWidth };
            });

            doc.autoTable({
                head: [cols.map(function (col) { return col.label; })],
                body: filtered.map(function (row) {
                    return cols.map(function (col) {
                        var v = row[col.key];
                        // Helvetica has no Naira glyph.
                        if (col.key === 'amount' && typeof v === 'string') v = v.replace('₦', 'N');
                        return v == null ? '' : v;
                    });
                }),
                startY: 50,
                theme: 'grid',
                styles: { fontSize: 7, cellPadding: 1.5, overflow: 'linebreak', valign: 'middle' },
                headStyles: { fillColor: [234, 88, 12], textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center' },
                columnStyles: columnStyles,
                margin: { top: 50, left: 10, right: 10 },
                didDrawPage: paintPage
            });

            var totalPages = doc.internal.getNumberOfPages();
            for (var p = 1; p <= totalPages; p++) {
                doc.setPage(p);
                doc.setFontSize(8);
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(100, 100, 100);
                doc.text('Page ' + p + ' of ' + totalPages, pageWidth - 10, pageHeight - 5, { align: 'right' });
            }

            doc.save(fileStem(f) + '.pdf');
        } catch (e) {
            console.error('PDF Generation Error:', e);
            Swal.fire('Error', 'Failed to generate PDF: ' + e.message, 'error');
        }
    };
})();
