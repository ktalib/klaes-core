/**
 * Generic records export (preview + date range + CSV + PDF).
 *
 * Driven entirely by the server response, so any page can reuse it:
 *   GET <endpoint>?format=json&... -> { success, columns: [{key,label,pdfWidth,align}], data: [...] }
 *
 * Page wiring lives in resources/views/exports/records_export_modal.blade.php,
 * which sets window.recordsExportConfig before loading this file.
 */

(function () {
    var TH_CLASS = 'px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50';
    var TD_CLASS = 'px-4 py-3 text-sm text-gray-600';

    window.recordsExportData = [];
    window.recordsExportColumns = [];
    var requestVersion = 0;
    var loadedFilters = null;

    function clearLoadedReport() {
        window.recordsExportData = [];
        window.recordsExportColumns = [];
        loadedFilters = null;
        var count = document.getElementById('recordsExportCount');
        if (count) count.textContent = '0';
    }

    function config() {
        return window.recordsExportConfig || {};
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function csvValue(value) {
        var text = String(value === null || value === undefined ? '' : value);
        if (/^[\s]*[=+@-]/.test(text) && !/^-?\d+(\.\d+)?$/.test(text)) text = "'" + text;
        return '"' + text.replace(/"/g, '""') + '"';
    }

    function fieldValue(id) {
        var el = document.getElementById(id);
        return el ? (el.value || '') : '';
    }

    function clearDateField(id) {
        var el = document.getElementById(id);
        if (!el) return;
        // A global flatpickr enhancer may own this input — clearing .value alone
        // would leave the visible alt field stale.
        if (el._flatpickr) {
            el._flatpickr.clear();
        } else {
            el.value = '';
        }
    }

    function currentFilters() {
        return {
            search: fieldValue('recordsExportSearch'),
            status: fieldValue('recordsExportStatus'),
            start_date: fieldValue('recordsExportStartDate'),
            end_date: fieldValue('recordsExportEndDate')
        };
    }

    function buildUrl() {
        var cfg = config();
        var url = new URL(cfg.endpoint, window.location.origin);
        url.searchParams.set('format', 'json');

        var params = cfg.params || {};
        Object.keys(params).forEach(function (key) {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                url.searchParams.set(key, params[key]);
            }
        });

        var filters = currentFilters();
        Object.keys(filters).forEach(function (key) {
            if (filters[key]) {
                url.searchParams.set(key, filters[key]);
            }
        });

        return url.toString();
    }

    function renderHeaders(columns) {
        var head = document.querySelector('#recordsExportTable thead tr');
        if (!head) return;
        head.innerHTML = columns.map(function (column) {
            return '<th class="' + TH_CLASS + '">' + escapeHtml(column.label) + '</th>';
        }).join('');
    }

    function renderMessage(html, colspan) {
        var body = document.getElementById('recordsExportBody');
        if (!body) return;
        body.innerHTML = '<tr><td colspan="' + (colspan || 8) + '" class="px-6 py-12 text-center text-gray-500">' + html + '</td></tr>';
    }

    function filterLabelSuffix() {
        var filters = currentFilters();
        var parts = [];
        if (filters.status) parts.push((config().statusLabel || 'Status') + ': ' + filters.status.toUpperCase());
        if (config().dateLabel) parts.push('Date basis: ' + config().dateLabel);
        if (filters.search) parts.push('Search: "' + filters.search + '"');
        if (filters.start_date && filters.end_date) {
            parts.push('Period: ' + filters.start_date + ' to ' + filters.end_date);
        } else if (filters.start_date) {
            parts.push('From: ' + filters.start_date);
        } else if (filters.end_date) {
            parts.push('To: ' + filters.end_date);
        }
        return parts;
    }

    function filenameFor(extension) {
        var cfg = config();
        var filters = currentFilters();
        var name = (cfg.filename || 'Records');
        if (filters.status) name += '_' + filters.status;
        if (filters.start_date) name += '_from_' + filters.start_date;
        if (filters.end_date) name += '_to_' + filters.end_date;
        name += '_' + new Date().toISOString().split('T')[0] + '.' + extension;
        return name.replace(/\s+/g, '_');
    }

    window.openRecordsExportModal = function () {
        var modal = document.getElementById('recordsExportModal');
        if (!modal) return;

        clearDateField('recordsExportStartDate');
        clearDateField('recordsExportEndDate');

        window.recordsExportData = [];
        window.recordsExportColumns = [];

        var count = document.getElementById('recordsExportCount');
        if (count) count.textContent = '0';

        var head = document.querySelector('#recordsExportTable thead tr');
        if (head) head.innerHTML = '';

        modal.classList.remove('hidden');
        requestVersion++;
        clearLoadedReport();

        // Don't hammer the database on open — the user picks filters first.
        renderMessage(
            '<div class="flex flex-col items-center gap-3 justify-center max-w-md mx-auto">' +
                '<div class="bg-emerald-50 text-emerald-600 p-4 rounded-full shadow-inner">' +
                    '<i data-lucide="calendar-range" class="h-7 w-7"></i>' +
                '</div>' +
                '<h4 class="text-base font-black text-gray-700 tracking-wide uppercase">Consolidated Report Engine</h4>' +
                '<p class="text-xs text-gray-500 leading-relaxed">Refine the Search, Status or <strong>Date Range</strong> above, then click ' +
                '<strong class="text-emerald-600">Refresh</strong> to compile the report preview.</p>' +
            '</div>',
            8
        );

        if (window.lucide) window.lucide.createIcons();
    };

    window.closeRecordsExportModal = function () {
        requestVersion++;
        var modal = document.getElementById('recordsExportModal');
        if (modal) modal.classList.add('hidden');
    };

    window.loadRecordsExportData = async function () {
        var body = document.getElementById('recordsExportBody');
        if (!body) return;

        var version = ++requestVersion;
        var filters = currentFilters();
        clearLoadedReport();
        if (filters.start_date && filters.end_date && filters.start_date > filters.end_date) {
            renderMessage('End date must be on or after start date.', 8);
            return;
        }

        renderMessage(
            '<div class="flex flex-col items-center gap-2 italic">' +
                '<i data-lucide="loader" class="h-7 w-7 text-emerald-500 animate-spin"></i>' +
                '<span>Fetching records for preview...</span>' +
            '</div>',
            8
        );
        if (window.lucide) window.lucide.createIcons();

        try {
            var response = await fetch(buildUrl(), { headers: { 'Accept': 'application/json' } });
            var result = await response.json();
            if (version !== requestVersion) return;

            if (!response.ok || !result.success) {
                throw new Error(result.error || result.message || 'Failed to fetch data');
            }

            var columns = result.columns || [];
            var rows = (result.data || []).map(function (row) {
                var formatted = {};
                Object.keys(row).forEach(function (key) {
                    formatted[key] = typeof row[key] === 'string' ? row[key].toUpperCase() : row[key];
                });
                return formatted;
            });

            window.recordsExportColumns = columns;
            window.recordsExportData = rows;
            loadedFilters = JSON.stringify(filters);

            renderHeaders(columns);

            var count = document.getElementById('recordsExportCount');
            if (count) count.textContent = rows.length;

            if (rows.length === 0) {
                renderMessage('No records found matching these filters.', columns.length || 8);
                return;
            }

            body.innerHTML = rows.map(function (row) {
                return '<tr class="hover:bg-gray-50 transition-colors">' +
                    columns.map(function (column) {
                        var value = row[column.key];
                        if (value === null || value === undefined) value = '';
                        var classes = TD_CLASS +
                            (column.align === 'right' ? ' text-right' : '') +
                            (column.wrap === false ? ' whitespace-nowrap' : '');
                        return '<td class="' + classes + '" title="' + escapeHtml(value) + '">' + escapeHtml(value) + '</td>';
                    }).join('') +
                '</tr>';
            }).join('');
        } catch (e) {
            if (version !== requestVersion) return;
            clearLoadedReport();
            renderMessage('<span class="text-red-500">Error: ' + escapeHtml(e.message) + '</span>', 8);
        }
    };

    function guardEmpty() {
        if (loadedFilters && loadedFilters !== JSON.stringify(currentFilters())) {
            alert('The filters have changed. Refresh the preview before exporting.');
            return true;
        }
        if (!window.recordsExportData || window.recordsExportData.length === 0) {
            if (window.Swal) {
                Swal.fire('No Data', 'Load a preview first — there is nothing to export.', 'warning');
            } else {
                alert('Load a preview first — there is nothing to export.');
            }
            return true;
        }
        return false;
    }

    window.downloadRecordsExportCsv = function () {
        if (guardEmpty()) return;

        var columns = window.recordsExportColumns;
        var csv = [columns.map(function (c) { return c.label; }).join(',')]
            .concat(window.recordsExportData.map(function (row) {
                return columns.map(function (c) { return csvValue(row[c.key]); }).join(',');
            }))
            .join('\n');

        // UTF-8 BOM so Excel renders the naira sign correctly.
        var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filenameFor('csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    /**
     * Fit the declared column widths into the usable page width.
     *
     * Columns without a numeric `pdfWidth` (e.g. Location) are flexible: they share
     * whatever is left over, but never drop below MIN_FLEX_WIDTH — otherwise autoTable
     * squeezes them to a single character per line when the fixed widths already
     * overflow the page.
     */
    function buildColumnStyles(columns, usableWidth) {
        var MIN_FLEX_WIDTH = 34; // mm

        var flexIndexes = [];
        var fixedTotal = 0;

        columns.forEach(function (column, index) {
            var width = typeof column.pdfWidth === 'number' ? column.pdfWidth : parseFloat(column.pdfWidth);
            if (isFinite(width) && width > 0) {
                fixedTotal += width;
            } else {
                flexIndexes.push(index);
            }
        });

        var reserved = flexIndexes.length * MIN_FLEX_WIDTH;
        var scale = 1;
        if (fixedTotal > 0 && fixedTotal + reserved > usableWidth) {
            scale = Math.max((usableWidth - reserved) / fixedTotal, 0.1);
        }

        var scaledFixedTotal = fixedTotal * scale;
        var flexWidth = flexIndexes.length
            ? Math.max((usableWidth - scaledFixedTotal) / flexIndexes.length, MIN_FLEX_WIDTH)
            : 0;

        var styles = {};
        columns.forEach(function (column, index) {
            styles[index] = {};
            var width = typeof column.pdfWidth === 'number' ? column.pdfWidth : parseFloat(column.pdfWidth);
            styles[index].cellWidth = (isFinite(width) && width > 0) ? width * scale : flexWidth;
            if (column.align === 'right') styles[index].halign = 'right';
        });

        return styles;
    }

    function loadImage(url) {
        return fetch(url)
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.blob();
            })
            .then(function (blob) {
                return new Promise(function (resolve) {
                    var reader = new FileReader();
                    reader.onloadend = function () { resolve(reader.result); };
                    reader.onerror = function () { resolve(null); };
                    reader.readAsDataURL(blob);
                });
            })
            .catch(function () { return null; });
    }

    window.downloadRecordsExportPdf = function () {
        if (guardEmpty()) return;

        if (!window.jspdf || !window.jspdf.jsPDF) {
            Swal.fire('Unavailable', 'The PDF library did not load on this page.', 'error');
            return;
        }

        Promise.all([
            loadImage('/assets/logo/ministry1.jpg'),
            loadImage('/assets/logo/ministry2.jpeg'),
            loadImage('/assets/logo/Nigerian-Coat-of-Arms.png')
        ]).then(function (logos) {
            if (guardEmpty()) return;
            try {
                var headerLeftLogo = logos[0];
                var headerRightLogo = logos[1];
                var watermarkLogo = logos[2];

                var jsPDF = window.jspdf.jsPDF;
                var doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

                if (typeof doc.autoTable !== 'function') {
                    Swal.fire('Unavailable', 'The PDF table plugin did not load on this page.', 'error');
                    return;
                }

                var pageWidth = doc.internal.pageSize.getWidth();
                var pageHeight = doc.internal.pageSize.getHeight();
                var pageCenter = pageWidth / 2;
                var headerLogoSize = 22;

                function paintWatermark() {
                    if (!watermarkLogo) return;
                    try {
                        var wmSize = 120;
                        var wmX = (pageWidth - wmSize) / 2;
                        var wmY = (pageHeight - wmSize) / 2;
                        if (typeof doc.GState === 'function' && typeof doc.setGState === 'function') {
                            doc.setGState(new doc.GState({ opacity: 0.08 }));
                            doc.addImage(watermarkLogo, 'PNG', wmX, wmY, wmSize, wmSize);
                            doc.setGState(new doc.GState({ opacity: 1 }));
                        } else {
                            doc.addImage(watermarkLogo, 'PNG', wmX, wmY, wmSize, wmSize);
                        }
                    } catch (e) {
                        console.warn('Watermark draw failed', e);
                    }
                }

                function paintMinistryHeader() {
                    if (headerLeftLogo) {
                        doc.addImage(headerLeftLogo, 'JPEG', 10, 8, headerLogoSize, headerLogoSize);
                    }
                    if (headerRightLogo) {
                        doc.addImage(headerRightLogo, 'JPEG', pageWidth - 10 - headerLogoSize, 8, headerLogoSize, headerLogoSize);
                    }
                    doc.setFont('helvetica', 'bold');
                    doc.setFontSize(16);
                    doc.setTextColor(0, 0, 0);
                    doc.text('KANO STATE GOVERNMENT', pageCenter, 14, { align: 'center' });
                    doc.setFontSize(12);
                    doc.text('MINISTRY OF LAND AND PHYSICAL PLANNING', pageCenter, 20, { align: 'center' });
                    doc.setFontSize(11);
                    doc.text((config().department || 'LAND DEPARTMENT').toUpperCase(), pageCenter, 26, { align: 'center' });
                    doc.setLineWidth(0.5);
                    doc.line(10, 32, pageWidth - 10, 32);
                }

                var columns = window.recordsExportColumns;
                var columnStyles = buildColumnStyles(columns, pageWidth - 20);
                var fontSize = columns.length > 14 ? 6.5 : 7.5;
                // Keep identifiers marked as non-wrapping complete on one line.
                // Shrink only that column when its longest value needs more room.
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(fontSize);
                columns.forEach(function (column, index) {
                    if (column.wrap !== false) return;
                    var longest = window.recordsExportData.reduce(function (width, row) {
                        return Math.max(width, doc.getTextWidth(String(row[column.key] == null ? '' : row[column.key])));
                    }, 0);
                    var available = columnStyles[index].cellWidth - 2.4;
                    columnStyles[index].fontSize = longest > available ? fontSize * available / longest : fontSize;
                    columnStyles[index].overflow = 'visible';
                });

                var reportTitle = config().reportTitle || 'Records Register';
                var subtitleParts = filterLabelSuffix();
                subtitleParts.unshift('Total Records: ' + window.recordsExportData.length.toLocaleString());
                subtitleParts.push('Generated on: ' + new Date().toLocaleDateString());
                var subtitle = subtitleParts.join(' | ');

                doc.autoTable({
                    head: [columns.map(function (c) { return c.label; })],
                    body: window.recordsExportData.map(function (row) {
                        return columns.map(function (c) {
                            var value = row[c.key];
                            return value === null || value === undefined ? '' : String(value);
                        });
                    }),
                    startY: 50,
                    theme: 'grid',
                    styles: { fontSize: fontSize, cellPadding: 1.2, overflow: 'linebreak', valign: 'middle' },
                    tableWidth: pageWidth - 20,
                    headStyles: { fillColor: [5, 150, 105], textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center' },
                    columnStyles: columnStyles,
                    margin: { top: 50, left: 10, right: 10 },
                    didDrawPage: function () {
                        paintMinistryHeader();
                        doc.setFontSize(13);
                        doc.setFont('helvetica', 'bold');
                        doc.setTextColor(0, 0, 0);
                        doc.text(reportTitle, 14, 39);
                        doc.setFontSize(9);
                        doc.setFont('helvetica', 'normal');
                        doc.text(subtitle, 14, 45);
                        paintWatermark();
                    }
                });

                var totalPages = doc.internal.getNumberOfPages();
                for (var p = 1; p <= totalPages; p++) {
                    doc.setPage(p);
                    doc.setFontSize(8);
                    doc.setFont('helvetica', 'normal');
                    doc.setTextColor(100, 100, 100);
                    doc.text('Page ' + p + ' of ' + totalPages, pageWidth - 10, pageHeight - 5, { align: 'right' });
                    doc.text('Total Records: ' + window.recordsExportData.length, 10, pageHeight - 5);
                }

                doc.save(filenameFor('pdf'));
            } catch (e) {
                console.error('PDF Generation Error:', e);
                Swal.fire('Error', 'Failed to generate PDF: ' + e.message, 'error');
            }
        });
    };

    // Enter in the search box triggers a refresh instead of submitting a stray form.
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') return;
        if (event.target && event.target.id === 'recordsExportSearch') {
            event.preventDefault();
            window.loadRecordsExportData();
        }
    });

    // Escape closes the modal.
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var modal = document.getElementById('recordsExportModal');
        if (modal && !modal.classList.contains('hidden')) {
            window.closeRecordsExportModal();
        }
    });
})();
