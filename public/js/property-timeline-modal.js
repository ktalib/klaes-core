/**
 * property-timeline-modal.js
 * Helper to open the cross-table property timeline inside the project's
 * existing Tailwind modal system (window.openTailwindModal).
 *
 * Usage:
 *   openPropertyTimeline(propId, fileNumber)
 *
 * Either propId or fileNumber (or both) may be provided.
 */
function openPropertyTimeline(propId, fileNumber) {
    var pid  = (propId    || '').toString().trim();
    var fno  = (fileNumber || '').toString().trim();

    if (!pid && !fno) {
        alert('No property identifier available for this record.');
        return;
    }

    var params = new URLSearchParams();
    if (pid) params.set('prop_id',     pid);
    if (fno) params.set('file_number', fno);
    params.set('mode', 'partial');

    var url = '/property-search/timeline?' + params.toString();

    if (typeof window.openTailwindModal === 'function') {
        window.openTailwindModal(url, 'xl');
    } else {
        // Fallback: open in a new tab when the modal system isn't loaded yet
        window.open('/property-search/timeline?' + (new URLSearchParams({ prop_id: pid, file_number: fno })).toString(), '_blank');
    }
}
