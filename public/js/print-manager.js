/**
 * Global Smart Print Manager Helper
 *
 * Usage:
 * window.SmartPrintManager.open('REF-123', 'Document Type', '/url/to/print');
 *
 * A fourth argument carries everything the manager needs to be interactive rather
 * than a bare "print now" button. All of it is optional — a three-argument call
 * behaves exactly as it always did.
 *
 *   recordId    number  the row's id, so the Date Issued panel can read and write it
 *   issueDate   string  'YYYY-MM-DD' already on the record, or '' when there is none
 *   module      string  'oss' switches to the OSS document board
 *   record      object  the row itself, used by the OSS board
 *   reissuance  string  'klaes' | 'legacy' — a re-issued letter
 *   passes      bool    false hides the three-pass footer (single-copy documents)
 *   batch       object  opens the manager for a whole batch instead of one row:
 *                         ids           array   the record ids in the batch
 *                         count         number   how many letters that is
 *                         missingDates  number   how many carry no application date
 *                         status        object   the batch-print status, for the ticks
 *                         onPass        fn       called with ('all'|'original'|'office',
 *                                                extras) — the caller keeps its own
 *                                                print pipeline, this only names the pass
 *
 * Example:
 * window.SmartPrintManager.open(fileNo, 'Land RofO', url, {
 *     recordId: 42, issueDate: '2026-03-01'
 * });
 */
window.SmartPrintManager = {
    open(ref, type, url, options) {
        const detail = Object.assign({ ref, type, url }, options || {});
        window.dispatchEvent(new CustomEvent('open-print-manager', { detail }));
    }
};
