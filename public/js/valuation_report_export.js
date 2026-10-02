/**
 * Valuation Report Export Logic
 * High-fidelity PDF and CSV export for valuation reports
 */

// Global state for export data
window.exportData = [];

window.openValuationExportModal = async function () {
    const modal = document.getElementById('exportPreviewModal');
    const body = document.getElementById('exportPreviewBody');
    const type = document.getElementById('propertyTypeFilter')?.value || '';

    // Update labels in modal
    if (document.getElementById('previewFilterType')) {
        document.getElementById('previewFilterType').textContent = type || 'All Properties';
    }
    // No volume filter for valuation reports, so hide it or set to N/A
    if (document.getElementById('previewFilterVolume')) {
        document.getElementById('previewFilterVolume').textContent = 'N/A';
    }
    
    // Update headers
    const head = document.querySelector('#exportPreviewTable thead tr');
    if (head) {
        head.innerHTML = `
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">SN</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">File Number</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Owner</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Property Type</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Inspector</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Inspection</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Date Gen</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Time Gen</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Value</th>
            <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Address</th>
        `;
    }
    
    modal.classList.remove('hidden');

    // Show loading
    body.innerHTML = `
        <tr>
            <td colspan="10" class="px-6 py-12 text-center text-gray-500 italic">
                <div class="flex flex-col items-center gap-2">
                    <i class="fas fa-spinner fa-spin text-3xl text-green-500"></i>
                    <span>Fetching valuation data for preview...</span>
                </div>
            </td>
        </tr>
    `;

    try {
        const response = await fetch(`${window.baseUrl}/valuation-reports/export?property_type=${encodeURIComponent(type)}`);
        const result = await response.json();

        if (result.success) {
            window.exportData = result.data;
            if (document.getElementById('previewRecordCount')) {
                document.getElementById('previewRecordCount').textContent = result.data.length;
            }

            if (result.data.length === 0) {
                body.innerHTML = '<tr><td colspan="10" class="px-6 py-8 text-center text-gray-500">No reports found matching filters.</td></tr>';
                return;
            }

            body.innerHTML = result.data.slice(0, 50).map(item => `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-sm text-gray-600">${item.SN}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">${item.file_number}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 truncate max-w-[150px]" title="${item.owner}">${item.owner}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.property_type}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.generated_by}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.inspection_date}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.date_generated}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${item.time_generated}</td>
                    <td class="px-4 py-3 text-sm font-bold text-gray-900">${item.value_figures}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 truncate max-w-[200px]" title="${item.address}">${item.address}</td>
                </tr>
            `).join('');
            
            if (result.data.length > 50) {
                body.innerHTML += `<tr><td colspan="10" class="px-6 py-2 text-center text-xs text-gray-400 italic">Showing first 50 reports in preview...</td></tr>`;
            }
        } else {
            throw new Error(result.error || 'Failed to fetch data');
        }
    } catch (e) {
        body.innerHTML = `<tr><td colspan="10" class="px-6 py-8 text-center text-red-500">Error: ${e.message}</td></tr>`;
    }
};

window.closeExportModal = function () {
    document.getElementById('exportPreviewModal').classList.add('hidden');
};

window.downloadExportCsv = function () {
    if (!window.exportData || window.exportData.length === 0) {
        Swal.fire('No Data', 'There is no data to export.', 'warning');
        return;
    }

    const headers = ['SN', 'File Number', 'Owner', 'Property Type', 'Generated By', 'Inspection Date', 'Date Generated', 'Time Generated', 'Value', 'Address'];
    const csvContent = [
        headers.join(','),
        ...window.exportData.map(item => [
            item.SN,
            `"${item.file_number}"`,
            `"${item.owner}"`,
            `"${item.property_type}"`,
            `"${item.generated_by}"`,
            `"${item.inspection_date}"`,
            `"${item.date_generated}"`,
            `"${item.time_generated}"`,
            `"${item.value_figures}"`,
            `"${item.address ? item.address.replace(/"/g, '""') : ''}"`
        ].join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    const typeLabel = (document.getElementById('propertyTypeFilter')?.value || 'All').replace(/\s+/g, '_');

    link.setAttribute('href', url);
    link.setAttribute('download', `Valuation_Reports_${typeLabel}_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

window.downloadExportPdf = function () {
    if (!window.exportData || window.exportData.length === 0) {
        Swal.fire('No Data', 'There is no data to export.', 'warning');
        return;
    }

    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        
        const typeLabel = document.getElementById('propertyTypeFilter')?.value || 'All Property Types';
        
        // Add Title
        doc.setFontSize(18);
        doc.text(`Valuation Reports Registry - ${typeLabel}`, 14, 15);
        doc.setFontSize(11);
        doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 14, 22);

        const tableColumn = [
            "SN", "File Number", "Owner", "Property Type", "Generated By", 
            "Inspection Date", "Date Generated", "Time Generated", "Value", "Address"
        ];
        
        const tableRows = window.exportData.map(item => [
            item.SN,
            item.file_number,
            item.owner,
            item.property_type,
            item.generated_by,
            item.inspection_date,
            item.date_generated,
            item.time_generated,
            item.value_figures,
            item.address
        ]);

        doc.autoTable({
            head: [tableColumn],
            body: tableRows,
            startY: 28,
            theme: 'grid',
            styles: { fontSize: 8, cellPadding: 2, overflow: 'linebreak' },
            headStyles: { fillColor: [59, 130, 246], textColor: [255, 255, 255], fontStyle: 'bold' }, // Blue color for valuation
            columnStyles: {
                0: { cellWidth: 10 }, // SN
                1: { cellWidth: 30 }, // File Number
                2: { cellWidth: 40 }, // Owner
                3: { cellWidth: 30 }, // Property Type
                4: { cellWidth: 30 }, // Generated By
                5: { cellWidth: 25 }, // Inspection Date
                6: { cellWidth: 25 }, // Date Generated
                7: { cellWidth: 25 }, // Time Generated
                8: { cellWidth: 30 }, // Value
                9: { cellWidth: 'auto' } // Address
            }
        });

        doc.save(`Valuation_Reports_${typeLabel.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.pdf`);
        
    } catch (e) {
        console.error('PDF Generation Error:', e);
        Swal.fire('Error', 'Failed to generate PDF: ' + e.message, 'error');
    }
};

// Initialize components when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Refresh Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }
});
