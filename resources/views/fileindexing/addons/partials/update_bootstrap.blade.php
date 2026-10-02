{{-- Update-mode bootstrap for the create form.

     create_indexing.blade.php carries a $record edit mode whose only populate path is
     checkFileAlreadyIndexed() → applyExistingIndexedRecord(), and that path is driven by
     a lookup on the file-number field. On a freshly loaded update screen that field is
     empty, so the lookup never fires and nothing prefills — the form sits there in
     create mode with a "Create File Index" button.

     This applies the record the server already handed us, directly and by id. It never
     goes back through /fileindex/check-indexed, whose normalisation strips separators
     and so cannot tell "KNML 1_2" from "KNML 12".

     Included by create_indexing.blade.php and kangis_update_indexing.blade.php. Both
     include it inside their `@isset($record)` branch, so it is inert on the create page.

     Fires `fileindex:update-populated` when done, which the KANGIS screen listens for to
     pin its file number after the fields have settled. --}}
<script>
    (function initFileIndexUpdateMode() {
        if (!window.isEditMode || !window.editingRecord) {
            return;
        }

        // CofO lives in CofO_staging / pra, not on file_indexings, so the server prepares
        // it separately (FileIndexingController::prepareCofODetailsForEdit). RoFO and
        // Occupancy Permit are merged onto the record itself and prefill server-side.
        window.editingCofoDetails = @json($cofoDetails ?? null);

        function setFieldValue(id, value) {
            const el = document.getElementById(id);
            if (!el || value === null || value === undefined || value === '') {
                return false;
            }

            if (el.tagName === 'SELECT') {
                const wanted = String(value).trim().toUpperCase();
                const option = Array.from(el.options).find(function (o) {
                    return (o.value || '').trim().toUpperCase() === wanted
                        || (o.textContent || '').trim().toUpperCase() === wanted;
                });

                if (!option) {
                    return false;
                }

                el.value = option.value;
            } else if (el.type === 'checkbox') {
                el.checked = !!value;
            } else {
                el.value = value;
            }

            el.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        }

        function applyCofoDetails() {
            const details = window.editingCofoDetails;
            if (!details || typeof details !== 'object' || Array.isArray(details)) {
                return;
            }

            const hasAnyValue = Object.values(details).some(function (v) {
                return v !== null && v !== undefined && String(v).trim() !== '';
            });

            if (!hasAnyValue) {
                return;
            }

            // Open the section first — its inputs are inside a container the toggle
            // reveals, and setting a hidden field then toggling can reset it.
            const hasCofoToggle = document.getElementById('has-cofo-toggle');
            if (hasCofoToggle && !hasCofoToggle.checked) {
                hasCofoToggle.checked = true;
                hasCofoToggle.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // cofo_first_party -> #cofo-first-party. Keys that have no matching input
            // are skipped silently.
            Object.keys(details).forEach(function (key) {
                setFieldValue(key.replace(/_/g, '-'), details[key]);
            });
        }

        // Fields applyExistingIndexedRecord() does not cover, because on the create page
        // they are driven by user interaction rather than by a fetched record.
        function applyUncoveredFields() {
            const record = window.editingRecord || {};

            // Customer Type: the visible <select> drives a hidden #file_type that is what
            // actually gets submitted, so set the select and let its onchange sync it.
            const fileTypeSelect = document.getElementById('file_type_select');
            const fileTypeValue = (record.file_type || '').toString().trim();
            if (fileTypeSelect && fileTypeValue !== '') {
                const matched = Array.from(fileTypeSelect.options).find(function (o) {
                    return (o.value || '').trim().toUpperCase() === fileTypeValue.toUpperCase();
                });

                if (matched) {
                    fileTypeSelect.value = matched.value;
                } else {
                    // Retired customer type — keep it rather than silently blanking it.
                    const option = document.createElement('option');
                    option.value = fileTypeValue;
                    option.textContent = fileTypeValue;
                    fileTypeSelect.appendChild(option);
                    fileTypeSelect.value = fileTypeValue;
                }

                fileTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));

                const hiddenFileType = document.getElementById('file_type');
                if (hiddenFileType && !hiddenFileType.value) {
                    hiddenFileType.value = fileTypeSelect.value;
                }
            }

            setFieldValue('indexing-type', record.indexing_type);
            setFieldValue('awaiting-file-no', record.awaiting_file_no);
            setFieldValue('registry-batch-no', record.registry_batch_no);
            setFieldValue('sys-batch-no', record.sys_batch_no);
            setFieldValue('mdc-batch-no', record.mdc_batch_no || record.batch_no);
            setFieldValue('shelf-rack-no', record.shelf_rack_no);
            setFieldValue('sub_prefix', record.sub_prefix);
            setFieldValue('suffix', record.suffix);
        }

        function run() {
            if (typeof window.applyExistingIndexedRecord === 'function') {
                window.applyExistingIndexedRecord(window.editingRecord);
            } else {
                console.error('[file-index update] applyExistingIndexedRecord unavailable — form cannot prefill.');
            }

            applyUncoveredFields();
            applyCofoDetails();

            window.dispatchEvent(new CustomEvent('fileindex:update-populated', {
                detail: { record: window.editingRecord },
            }));
        }

        // Defer past every other DOMContentLoaded listener so the form module has
        // finished wiring itself up before we push values into it.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                setTimeout(run, 0);
            });
        } else {
            setTimeout(run, 0);
        }
    })();
</script>
