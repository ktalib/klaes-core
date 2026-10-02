/**
 * allocation_list_entry.js
 * Client-side logic for the Allocation List — capture of existing allocations.
 *
 * The capture form saves one entry at a time over AJAX and appends it to the
 * session table inside the modal, so the operator keeps filling and saving
 * without the form closing. Closing the modal resets the form and that table.
 */

'use strict';

// Fallback to avoid crashes if global config is missing
window.ALE = window.ALE || { urls: {}, csrf: '', activeFilter: '' };

let aleTable    = null;   // DataTable instance
let aleEditId   = null;   // ID of record being edited (null = capture mode)
let aleSession  = [];     // Entries captured since the modal was opened

// The registry's own free-text location, used when the file carries no plot /
// district / LGA for the builder to work from.
let aleRawLocation = '';

// The Name is seeded from the File Title, but it stays a normal editable field.
// Once the operator types in it, the backfill stops overwriting their version.
let aleNameEdited = false;

/**
 * Feedback goes to a corner toast. The capture form is meant to stay open and
 * keep taking entries, so a centred dialog that has to be dismissed after every
 * save would break that rhythm.
 *
 * Confirmations still use a real dialog — a toast cannot take an answer.
 */
function aleToast(icon, title, text) {
    if (!window.Swal) return;

    Swal.fire({
        toast             : true,
        position          : 'top-end',
        icon              : icon,
        title             : title,
        text              : text || undefined,
        showConfirmButton : false,
        // Errors are worth reading; successes should get out of the way.
        timer             : icon === 'error' || icon === 'warning' ? 4000 : 2200,
        timerProgressBar  : true,
        didOpen: el => {
            el.addEventListener('mouseenter', Swal.stopTimer);
            el.addEventListener('mouseleave', Swal.resumeTimer);
        },
    });
}

$(document).ready(function () {
    // ── Build DataTable ────────────────────────────────────────────────────
    aleTable = $('#allocationTable').DataTable({
        processing : true,
        serverSide : false,
        ajax       : { url: window.ALE.urls.data, dataSrc: 'data' },
        dom        : "<'dt-top-bar'fl>t<'dt-bottom-bar'ip>",
        pageLength : 25,
        lengthMenu : [[10, 25, 50, 100, -1], ['10', '25', '50', '100', 'All']],
        order      : [[6, 'desc']], // Captured — newest first
        columns    : [
            { data: null, render: (d, t, r, m) => m.row + 1, orderable: false, searchable: false, width: '40px' },
            { data: 'file_no', render: v => v || '—', defaultContent: '—' },
            { data: 'file_title', render: v => v || '—', defaultContent: '—' },
            {
                data: null,
                render: r => r.allottee_name
                    || [r.first_name, r.middle_name, r.last_name].filter(Boolean).join(' ')
                    || '—',
                defaultContent: '—'
            },
            { data: 'plot_number', render: v => v || '—', defaultContent: '—' },
            { data: 'district', render: v => v || '—', defaultContent: '—' },
            { data: 'lga', render: v => v || '—', defaultContent: '—' },
            { data: 'location', render: v => v || '—', defaultContent: '—' },
            { data: 'allocation_year', render: v => v || '—', defaultContent: '—' },
            {
                data: 'created_at',
                // Sort on the timestamp, not on the rendered date. Without this
                // DataTables compares the display strings, so "01 AUG" lands
                // before "17 JUL" and the newest rows are not on top.
                render: (val, type) => {
                    if (type === 'sort' || type === 'type') {
                        return val ? new Date(val).getTime() : 0;
                    }
                    if (!val) return '—';

                    const d = new Date(val);
                    const dateStr = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                    const timeStr = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: true });

                    // Search the dates the reader can see, not the markup.
                    if (type === 'filter') return `${dateStr} ${timeStr}`;

                    return `<div class="flex flex-col"><span class="font-medium">${dateStr}</span><span class="text-[10px] text-gray-400 font-bold uppercase">${timeStr}</span></div>`;
                },
                defaultContent: ''
            },
            { data: 'created_by_name', render: v => v || '—', defaultContent: '—' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: r => `
                    <div class="relative inline-block text-left ale-dropdown-container">
                        <button type="button" class="ale-btn-dropdown p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 transition-all focus:outline-none"
                            onclick="aleToggleDropdown(event, ${r.id})">
                            <i data-lucide="more-vertical" class="h-4 w-4"></i>
                        </button>
                        <div id="ale-dropdown-${r.id}" class="ale-dropdown-menu hidden absolute right-0 mt-2 w-32 bg-white border border-gray-200 rounded-xl shadow-xl z-30 overflow-hidden">
                            <div class="py-1">
                                <button onclick="aleOpenEdit(${r.id})" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-all font-medium">
                                    <i data-lucide="pencil" class="h-4 w-4 text-blue-500"></i>
                                    Edit
                                </button>
                                <button onclick="aleDelete(${r.id})" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-all font-medium">
                                    <i data-lucide="trash-2" class="h-4 w-4 text-red-500"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                `
            }
        ],
        drawCallback: function() {
            if (window.lucide) window.lucide.createIcons();
        },
        initComplete: function () {
            // DataTable Buttons
            new $.fn.dataTable.Buttons(aleTable, {
                buttons: [
                    { extend: 'copyHtml5',  text: 'Copy', className: 'px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:bg-gray-50 transition-all' },
                    { extend: 'csvHtml5',   text: 'CSV',  className: 'px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:bg-gray-50 transition-all' },
                    { extend: 'excelHtml5', text: 'Excel',className: 'px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:bg-gray-50 transition-all' },
                    { extend: 'pdfHtml5',   text: 'PDF',  className: 'px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:bg-gray-50 transition-all' },
                ]
            });
            aleTable.buttons().container().appendTo('#dt-export-buttons');
            aleRefreshStats();
        }
    });

    // The capture modal only closes on the X or Close button. A stray click on
    // the backdrop used to discard the form and the whole session table, so
    // there is deliberately no backdrop-click handler here.

    // The form must never actually submit — a real submit reloads the page,
    // which reads as the modal closing by itself and loses the session.
    $('#ale-form').on('submit', function (e) {
        e.preventDefault();
    });

    // The builder fields rebuild the Location as they are filled.
    $('#ale-form').on('input change', '.ale-loc-part', aleUpdateLocation);

    // OTHER needs a district typed out; anything else hides that field again.
    $('#ale-form').on('change', '#ale-district', aleSyncDistrictOther);

    // A title typed by hand seeds the Name the same way a backfilled one does.
    $('#ale-form').on('input', '#ale-file-title', aleSetNameFromTitle);

    // Typing a name takes it out of the File Title's hands for good.
    $('#ale-form').on('input', '#ale-allottee-name', function () {
        aleNameEdited = true;
        $('#ale-name-hint').text('Edited — no longer follows the File Title.');
    });

    // Enter anywhere in the form saves, so capture stays keyboard-only. Bound
    // to selects as well: Enter on a native select submits the form otherwise.
    $('#ale-form').on('keydown', 'input, select, textarea', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            aleSubmit();
        }
    });

    // Close dropdowns when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.ale-dropdown-container').length) {
            $('.ale-dropdown-menu').addClass('hidden');
        }
    });

    // A fixed menu is placed against the viewport, so it would hang in mid-air
    // once the page or the table scrolls underneath it.
    $(window).on('scroll resize', function () {
        $('.ale-dropdown-menu').addClass('hidden');
    });
    $(document).on('scroll', '.ale-table-container', function () {
        $('.ale-dropdown-menu').addClass('hidden');
    });
});

/* ═══════════════════════════════════════════════════════════════════════════
   MODAL & DROPDOWN CONTROLS
═══════════════════════════════════════════════════════════════════════════ */

function aleToggleDropdown(event, id) {
    event.stopPropagation();
    const dropdown = $(`#ale-dropdown-${id}`);
    const isHidden = dropdown.hasClass('hidden');

    // Close all other dropdowns
    $('.ale-dropdown-menu').addClass('hidden');

    if (isHidden) {
        // The menu is position:fixed so the horizontally scrolling table cannot
        // clip it, which means it has to be placed against the viewport here.
        // Shown first, because a hidden element measures zero.
        dropdown.removeClass('hidden');

        const rect = event.currentTarget.getBoundingClientRect();
        const width = dropdown.outerWidth() || 128;
        const height = dropdown.outerHeight() || 0;

        // Flip above the button when there is no room beneath it.
        const below = rect.bottom + 4;
        const top = (below + height > window.innerHeight && rect.top - height - 4 > 0)
            ? rect.top - height - 4
            : below;

        dropdown.css({
            top : `${top}px`,
            left: `${Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8))}px`,
        });
    }
}

function aleOpenModal() {
    $('#aleModal').removeClass('hidden').addClass('flex');
    $('body').addClass('overflow-hidden');
    aleInitDistrictSelect();
    if (window.lucide) window.lucide.createIcons();
}

/**
 * The district list runs to a couple of thousand entries, so it needs Select2's
 * search box. Built on first open — Select2 sizes itself wrong against a hidden
 * element — and only once.
 */
function aleInitDistrictSelect() {
    const select = $('#ale-district');

    if (!$.fn.select2 || select.data('select2')) return;

    select.select2({
        // Without this the dropdown renders behind the modal.
        dropdownParent  : $('#aleModal'),
        placeholder     : 'Search district…',
        allowClear      : true,
        width           : '100%',
    });

    // Select2 fires change on the original select, which the .ale-loc-part
    // handler picks up to rebuild the Location.
}

/**
 * Closing ends the capture session: the form and the session table both reset.
 */
function aleCloseModal() {
    $('#aleModal').removeClass('flex').addClass('hidden');
    $('body').removeClass('overflow-hidden');

    aleEditId  = null;
    aleSession = [];
    aleResetForm();
    aleRenderSession();
}

function aleOpenAdd() {
    aleEditId  = null;
    aleSession = [];

    aleResetForm();
    aleRenderSession();

    $('#ale-modal-title').text('Capture Existing Allocation');
    $('#ale-modal-subtitle').text('Select a file number — the title, location and year fill in automatically.');
    $('#ale-save-btn-text').text('Save Entry');

    aleOpenModal();
    setTimeout(() => $('#ale-file-no').focus(), 100);
}

async function aleOpenEdit(id) {
    try {
        const resp = await fetch(window.ALE.urls.fetchrowinfo.replace('__ID__', id), {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf }
        });
        const json = await resp.json();
        if (!json.success) throw new Error(json.message);

        const r = json.data;
        aleEditId  = id;
        aleSession = [];

        aleResetForm();
        aleRenderSession();

        $('#ale-entry-id').val(id);
        $('#ale-file-no').val(r.file_no || '');
        $('#ale-file-title').val(r.file_title || '');
        const storedName = r.allottee_name
            || [r.first_name, r.middle_name, r.last_name].filter(Boolean).join(' ');
        $('#ale-allottee-name').val(storedName);

        // A stored name is the operator's own — the title backfill must not
        // overwrite it while the row is being edited.
        if (storedName.trim()) {
            aleNameEdited = true;
            $('#ale-name-hint').text('Name on the existing allocation.');
        }

        // A stored row keeps its own parts, so the builder reloads from them
        // rather than re-deriving anything from the file.
        aleSetDistrict(r.district);
        aleSetLga(r.lga);
        aleRawLocation = r.location || '';
        aleUpdateLocation();

        // A row saved without a title can have one added; one that has it keeps
        // it locked, same as capture.
        aleSetTitleLocked(!!(r.file_title || '').trim());

        aleSetYearFromFileNo(r.file_no);
        if (!$('#ale-allocation-year').val() && r.allocation_year) {
            $('#ale-allocation-year').val(r.allocation_year);
        }

        $('#ale-plot-number').val(r.plot_number || '');

        $('#ale-modal-title').text('Edit Allocation Entry');
        $('#ale-modal-subtitle').text('Update this allocation record.');
        $('#ale-save-btn-text').text('Update Entry');

        aleOpenModal();
    } catch (err) {
        aleToast('error', 'Error', err.message);
    }
}

function aleResetForm() {
    aleRawLocation = '';
    aleNameEdited  = false;

    $('#ale-entry-id').val(aleEditId || '');
    $('#ale-file-no').val('');
    $('#ale-file-title').val('');
    $('#ale-allottee-name').val('');
    $('#ale-plot-number').val('');
    $('#ale-name-hint').text('Fills in from the File Title — edit if it differs.');
    aleSetDistrict('');
    aleSetLga('');

    aleSetTitleLocked(true);
    aleUpdateLocation();
    aleSetYearFromFileNo('');
    aleSetFileStatus('', '');
}

/**
 * The File Title is locked while it holds a value backfilled from the file
 * number, and opened up only when the lookup could not supply one.
 */
function aleSetTitleLocked(locked) {
    const field = $('#ale-file-title');

    field.prop('readonly', locked);
    field.toggleClass('bg-gray-100 text-gray-500 cursor-not-allowed', locked);
    field.toggleClass('bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500', !locked);
    field.attr('placeholder', locked ? 'Fills in from the FileNo' : 'Not on record — type the title');

    $('#ale-title-hint').text(locked ? '' : 'No title on record — enter it here.');
}

/**
 * Seed the Name from the File Title. The title is the allottee on almost every
 * file, so this saves retyping it — but the field stays editable and the seed
 * backs off the moment the operator types their own.
 */
function aleSetNameFromTitle() {
    if (aleNameEdited) return;

    const title = ($('#ale-file-title').val() || '').trim().toUpperCase();
    if (!title) return;

    $('#ale-allottee-name').val(title);
    $('#ale-name-hint').text('From the File Title — edit if it differs.');
}

function aleSetFileStatus(message, tone) {
    const tones = {
        ok      : 'text-emerald-600',
        warn    : 'text-amber-600',
        loading : 'text-gray-400',
        error   : 'text-red-600',
    };
    $('#ale-file-status')
        .removeClass('text-emerald-600 text-amber-600 text-gray-400 text-red-600')
        .addClass(tones[tone] || 'text-gray-400')
        .text(message);
}

/* ═══════════════════════════════════════════════════════════════════════════
   FILE NUMBER SELECTION & BACKFILL
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Read the allocation year out of a file number, e.g. RES-1982-2081 → 1982.
 *
 * Takes the leftmost plausible 4-digit group so a serial that happens to look
 * like a year (…-2081) never wins over the real one. Mirrors
 * AllocationListEntryController::detectYearFromFileNumber().
 */
function aleDetectYear(fileNo) {
    const value = String(fileNo || '').trim();
    if (!value) return '';

    const maxYear = new Date().getFullYear() + 1;

    for (const group of value.split(/[^0-9]+/)) {
        if (group.length !== 4) continue;
        const year = parseInt(group, 10);
        if (year >= 1900 && year <= maxYear) return group;
    }
    return '';
}

function aleCurrentFileNo() {
    return ($('#ale-file-no').val() || '').trim().toUpperCase();
}

/**
 * The year belongs to the file number, so the field is display-only — the
 * server derives it the same way and would overwrite anything typed here.
 */
function aleSetYearFromFileNo(fileNo) {
    $('#ale-allocation-year')
        .val(aleDetectYear(fileNo))
        .attr('placeholder', fileNo ? 'No year in this file number' : 'From the FileNo');
}

/* ═══════════════════════════════════════════════════════════════════════════
   LOCATION BUILDER
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Build the location out of district and LGA, e.g. "ADAKAWA, GAYA". Empty when
 * there is nothing to build from.
 *
 * Mirrors AllocationListEntryController::composeLocation() so the form shows
 * exactly what gets stored.
 */
function aleComposeLocation() {
    const district = aleEffectiveDistrict();
    const lga      = ($('#ale-lga').val() || '').trim().toUpperCase();

    return [district, lga].filter(Boolean).join(', ');
}

/**
 * The district as it is saved and composed into the Location. On OTHER that is
 * the typed name — "OTHER, AJINGI" names no place — and the empty string until
 * one is typed, so the Location never carries the placeholder.
 */
function aleEffectiveDistrict() {
    const selected = ($('#ale-district').val() || '').trim().toUpperCase();

    if (selected !== 'OTHER') return selected;

    return ($('#ale-district-other').val() || '').trim().toUpperCase();
}

/**
 * Show the "Specify District" field only for OTHER, and clear it on the way out
 * so a stale name cannot survive into another district.
 */
function aleSyncDistrictOther() {
    const isOther = ($('#ale-district').val() || '').trim().toUpperCase() === 'OTHER';

    $('#ale-district-other-wrap').toggleClass('hidden', !isOther);
    $('#ale-district-other').prop('required', isOther);

    if (!isOther) {
        $('#ale-district-other').val('');
        aleClearDistrictOtherError();
    } else {
        setTimeout(() => $('#ale-district-other').focus(), 50);
    }

    aleUpdateLocation();
}

function aleMarkDistrictOtherError(message) {
    $('#ale-district-other').addClass('border-red-400 ring-2 ring-red-500/20');
    $('#ale-district-other-hint').removeClass('text-gray-400').addClass('text-red-600').text(message);
}

function aleClearDistrictOtherError() {
    $('#ale-district-other').removeClass('border-red-400 ring-2 ring-red-500/20');
    $('#ale-district-other-hint')
        .removeClass('text-red-600').addClass('text-gray-400')
        .text('Required — this replaces "OTHER" in the Location.');
}

/**
 * Select a district, adding it as an option first when the registry supplied
 * one outside the districts table — otherwise the backfill would drop it.
 */
function aleSetDistrict(value) {
    const district = String(value || '').trim().toUpperCase();
    const select   = $('#ale-district');

    if (district && !select.find('option').filter((i, o) => o.value === district).length) {
        select.append($('<option>').val(district).text(district));
    }

    // Select2 mirrors the underlying select, so it has to be told to redraw.
    select.val(district);
    if (select.data('select2')) select.trigger('change.select2');

    // change.select2 deliberately skips the change handlers, and a plain .val()
    // fires nothing at all, so the OTHER field is synced by hand.
    aleSyncDistrictOther();
}

/**
 * Refresh the Location preview. The builder wins whenever it holds anything;
 * an empty builder falls back to the location the registry supplied, so a file
 * that only stores a free-text location does not lose it.
 */
function aleUpdateLocation() {
    $('#ale-location').val(aleComposeLocation() || aleRawLocation);
}

/**
 * Select an LGA, adding it as an option first when the registry supplied one
 * outside the Kano list — otherwise the backfill would silently drop it.
 */
function aleSetLga(value) {
    const lga    = String(value || '').trim().toUpperCase();
    const select = $('#ale-lga');

    if (!lga) {
        select.val('');
        return;
    }

    if (!select.find('option').filter((i, o) => o.value === lga).length) {
        select.append($('<option>').val(lga).text(lga));
    }
    select.val(lga);
}

/**
 * Open the shared file number selector and backfill from whatever is picked.
 */
function aleOpenFileSelector() {
    if (!window.GlobalFileNoModal) {
        aleToast('error', 'File number selector is unavailable.');
        return;
    }

    GlobalFileNoModal.open({
        callback: function (data) {
            const fileNo = (data.fileNumber || '').trim().toUpperCase();
            if (!fileNo) return;

            // A different file replaces the previous one outright, so nothing
            // from the last selection lingers in the builder.
            $('#ale-file-no').val(fileNo);
            $('#ale-file-title').val('');
            // A name seeded from the previous title goes with it; one the
            // operator typed is theirs and stays.
            if (!aleNameEdited) $('#ale-allottee-name').val('');
            aleSetDistrict('');
            aleSetLga('');
            aleRawLocation = '';
            aleSetTitleLocked(true);
            aleUpdateLocation();
            aleSetYearFromFileNo(fileNo);

            // The selector already carries the title for most registries — show
            // it immediately, then let the lookup confirm and fill the rest.
            const title = data.file_title || data.file_name || (data.record && data.record.file_name) || '';
            if (title) {
                $('#ale-file-title').val(title.toUpperCase());
                aleSetNameFromTitle();
            }

            aleLookupFile(fileNo);
            setTimeout(() => $('#ale-allottee-name').focus(), 150);
        }
    });
}

/**
 * Backfill the title and the location parts for a file number. Blank fields
 * only — anything the operator has already filled in is left alone.
 */
async function aleLookupFile(fileNo) {
    const value = String(fileNo || '').trim();
    if (!value) {
        aleSetFileStatus('', '');
        return;
    }

    // The field may have moved on since this lookup was queued.
    if (aleCurrentFileNo() !== value.toUpperCase()) return;

    aleSetFileStatus('Looking up file…', 'loading');

    try {
        const url = `${window.ALE.urls.fileLookup}?file_no=${encodeURIComponent(value)}`;
        const resp = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf }
        });
        const json = await resp.json();

        // A slower earlier lookup must not overwrite a number typed since.
        if (aleCurrentFileNo() !== value.toUpperCase()) return;

        if (!json.success) throw new Error(json.message || 'Lookup failed.');

        const d = json.data || {};

        if (d.file_title && !$('#ale-file-title').val().trim()) {
            $('#ale-file-title').val(String(d.file_title).toUpperCase());
        }
        // The title stays locked only if one actually came back; otherwise it
        // opens up so the operator can supply it.
        aleSetTitleLocked(!!$('#ale-file-title').val().trim());
        aleSetNameFromTitle();

        if (d.district && !$('#ale-district').val()) {
            aleSetDistrict(d.district);
        }
        if (d.lga && !$('#ale-lga').val()) {
            aleSetLga(d.lga);
        }

        // Registries that hold only a free-text location keep it as the
        // fallback the builder falls back to while it is empty.
        aleRawLocation = d.location ? String(d.location).toUpperCase() : '';
        aleUpdateLocation();

        if (d.allocation_year) {
            $('#ale-allocation-year').val(d.allocation_year);
        }

        // Plot number comes off the same registry row as the title and
        // location. Only fill an empty box, so a value typed for a file the
        // registry has nothing for is not wiped by a later lookup.
        if (d.plot_number && !$('#ale-plot-number').val().trim()) {
            $('#ale-plot-number').val(String(d.plot_number).toUpperCase());
        }

        // Already in the list — say so now rather than at save time.
        if (json.already_captured) {
            aleSetFileStatus('Already captured — this file is in the list.', 'error');
        } else if (json.found) {
            aleSetFileStatus('File found — details backfilled.', 'ok');
        } else {
            aleSetFileStatus('No record for this file number — enter the details manually.', 'warn');
        }
    } catch (err) {
        aleSetFileStatus('Could not look up this file number.', 'error');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   SESSION TABLE
═══════════════════════════════════════════════════════════════════════════ */

function aleRenderSession() {
    const body = $('#ale-session-body');
    body.empty();

    $('#ale-session-count').text(aleSession.length);
    $('#ale-session-empty').toggleClass('hidden', aleSession.length > 0);

    aleSession.forEach((entry, index) => {
        body.append(`
            <tr class="border-t border-gray-100 hover:bg-blue-50/40 transition-colors">
                <td class="px-4 py-2.5 text-gray-400 font-semibold">${index + 1}</td>
                <td class="px-4 py-2.5 font-semibold text-gray-800">${aleEscape(entry.file_no)}</td>
                <td class="px-4 py-2.5 text-gray-600">${aleEscape(entry.file_title) || '—'}</td>
                <td class="px-4 py-2.5 text-gray-800">${aleEscape(entry.allottee_name) || '—'}</td>
                <td class="px-4 py-2.5 text-gray-600">${aleEscape(entry.location) || '—'}</td>
                <td class="px-4 py-2.5 text-gray-600 font-semibold">${aleEscape(entry.allocation_year) || '—'}</td>
                <td class="px-4 py-2.5 text-center">
                    <button type="button" onclick="aleDeleteSessionEntry(${entry.id})"
                        title="Remove this entry"
                        class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition-all">
                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                </td>
            </tr>
        `);
    });

    if (window.lucide) window.lucide.createIcons();
}

function aleEscape(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

/**
 * Remove an entry saved earlier in this session (it is already in the DB).
 */
function aleDeleteSessionEntry(id) {
    Swal.fire({
        icon             : 'warning',
        title            : 'Remove Entry?',
        text             : 'This deletes the saved allocation record.',
        showCancelButton : true,
        confirmButtonText: 'Yes, Remove',
        confirmButtonColor: '#dc2626',
    }).then(async result => {
        if (!result.isConfirmed) return;
        const ok = await aleDeleteRecord(id);
        if (!ok) return;
        aleSession = aleSession.filter(e => e.id !== id);
        aleRenderSession();
    });
}

/* ═══════════════════════════════════════════════════════════════════════════
   OPERATIONS
═══════════════════════════════════════════════════════════════════════════ */

async function aleSubmit() {
    const fileNo = $('#ale-file-no').val().trim();
    const name   = $('#ale-allottee-name').val().trim();

    if (!fileNo) {
        aleSetFileStatus('File number is required.', 'error');
        $('#ale-file-no').focus();
        return;
    }
    if (!name) {
        aleToast('warning', 'Name is required.');
        $('#ale-allottee-name').focus();
        return;
    }

    // OTHER is a placeholder, not a district — it cannot be saved on its own.
    const isOtherDistrict = ($('#ale-district').val() || '').trim().toUpperCase() === 'OTHER';
    if (isOtherDistrict && !aleEffectiveDistrict()) {
        aleMarkDistrictOtherError('Specify the district before saving.');
        aleToast('warning', 'Specify the district.');
        $('#ale-district-other').focus();
        return;
    }
    aleClearDistrictOtherError();

    const isEdit = aleEditId !== null;
    const payload = {
        file_no         : fileNo.toUpperCase(),
        allottee_name   : name.toUpperCase(),
        file_title      : $('#ale-file-title').val().trim().toUpperCase(),
        district        : aleEffectiveDistrict(),
        lga             : ($('#ale-lga').val() || '').trim().toUpperCase(),
        location        : $('#ale-location').val().trim().toUpperCase(),
        plot_number     : $('#ale-plot-number').val().trim().toUpperCase(),
        allocation_year : $('#ale-allocation-year').val().trim(),
    };
    if (isEdit) payload._method = 'PUT';

    const url = isEdit
        ? window.ALE.urls.update.replace('__ID__', aleEditId)
        : window.ALE.urls.storeExisting;

    const btn = $('#ale-save-btn');
    const originalText = $('#ale-save-btn-text').text();
    btn.prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
    $('#ale-save-btn-text').text('Saving...');

    try {
        const resp = await fetch(url, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf },
            body   : JSON.stringify(payload),
        });
        const json = await resp.json();

        // A file number already in the list is a correction, not a failure —
        // keep everything typed so the operator can just pick another file.
        if (!json.success && json.duplicate) {
            aleSetFileStatus('Already captured — pick a different file.', 'error');
            aleToast('warning', 'Duplicate FileNo', json.message);
            return;
        }
        if (!json.success) throw new Error(json.message);

        aleTable.ajax.reload(null, false);
        aleRefreshStats();

        if (isEdit) {
            aleCloseModal();
            aleToast('success', 'Entry updated');
            return;
        }

        // Capture mode: the entry joins the session table and the form clears
        // for the next one, with the modal left open.
        aleSession.unshift({
            id              : json.data ? json.data.id : null,
            file_no         : payload.file_no,
            file_title      : payload.file_title || (json.data ? json.data.file_title : ''),
            allottee_name   : payload.allottee_name,
            location        : payload.location || (json.data ? json.data.location : ''),
            allocation_year : (json.data ? json.data.allocation_year : '') || payload.allocation_year,
        });
        aleRenderSession();

        aleResetForm();
        $('#ale-file-no').focus();
        aleSetFileStatus(`${payload.file_no} saved.`, 'ok');
        aleToast('success', 'Allocation saved', payload.file_no);
    } catch (err) {
        aleToast('error', 'Could not save', err.message || 'An error occurred.');
    } finally {
        btn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
        $('#ale-save-btn-text').text(originalText);
    }
}

/**
 * Delete one record. Returns true on success; surfaces its own error otherwise.
 */
async function aleDeleteRecord(id) {
    try {
        const resp = await fetch(window.ALE.urls.destroy.replace('__ID__', id), {
            method : 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf },
            body   : JSON.stringify({ _method: 'DELETE', _token: window.ALE.csrf }),
        });
        const json = await resp.json();
        if (!json.success) throw new Error(json.message);

        aleTable.ajax.reload(null, false);
        aleRefreshStats();
        return true;
    } catch (err) {
        aleToast('error', 'Error', err.message);
        return false;
    }
}

function aleDelete(id) {
    Swal.fire({
        icon             : 'warning',
        title            : 'Delete Entry?',
        text             : 'This action cannot be undone.',
        showCancelButton : true,
        confirmButtonText: 'Yes, Delete',
        confirmButtonColor: '#dc2626',
        cancelButtonText : 'Cancel',
    }).then(async result => {
        if (!result.isConfirmed) return;
        const ok = await aleDeleteRecord(id);
        if (!ok) return;
        aleSession = aleSession.filter(e => e.id !== id);
        aleRenderSession();
        aleToast('success', 'Entry deleted');
    });
}

/* ═══════════════════════════════════════════════════════════════════════════
   IMPORT / STATS
═══════════════════════════════════════════════════════════════════════════ */

function aleTriggerImport() {
    $('#import-csv-input').click();
}

function aleHandleImport(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async function (e) {
        const lines   = e.target.result.split(/\r?\n/).filter(l => l.trim());
        const headers = lines[0].split(',').map(h => h.replace(/["ï»¿]/g, '').trim());

        const rows = [];
        for (let i = 1; i < lines.length; i++) {
            const vals  = lines[i].split(',').map(v => v.replace(/"/g, '').trim());
            const entry = {};
            headers.forEach((h, idx) => { entry[h] = vals[idx] || ''; });
            rows.push(entry);
        }

        if (rows.length === 0) {
            aleToast('warning', 'The CSV contains no data rows.');
            return;
        }

        if (rows.length > 100) {
            aleToast('error', 'Too many records', `Limit 100 records. File has ${rows.length}.`);
            event.target.value = '';
            return;
        }

        const res = await Swal.fire({ title: `Import ${rows.length} Records?`, showCancelButton: true });
        if (!res.isConfirmed) { event.target.value = ''; return; }

        try {
            const resp = await fetch(window.ALE.urls.importCsv, {
                method : 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf },
                body   : JSON.stringify({ records: rows, _token: window.ALE.csrf }),
            });
            const json = await resp.json();
            if (!json.success) throw new Error(json.message);
            aleToast('success', 'Imported', json.message);
            aleTable.ajax.reload(null, false);
            aleRefreshStats();
        } catch (err) {
            aleToast('error', 'Import failed', err.message);
        } finally {
            event.target.value = '';
        }
    };
    reader.readAsText(file);
}

/* ═══════════════════════════════════════════════════════════════════════════
   DASHBOARD
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Refresh the headline tiles and the two breakdown panels.
 *
 * Aggregates come from the server, so this stays one small request no matter
 * how long the list gets.
 */
async function aleRefreshStats() {
    try {
        const resp = await fetch(window.ALE.urls.stats, { headers: { 'Accept': 'application/json' } });
        const json = await resp.json();
        if (!json.success) return;

        const d = json.data || {};

        $('#stat-today').text(aleNumber(d.today));
        $('#stat-total').text(aleNumber(d.total));
        $('#stat-captured').text(aleNumber(d.captured));
        $('#stat-month').text(aleNumber(d.this_month));

        aleRenderBars('#ale-chart-years', d.by_year, 'No years captured yet.');
        aleRenderBars('#ale-chart-lgas',  d.by_lga,  'No locations captured yet.');
    } catch (_) {}
}

function aleNumber(value) {
    return Number(value || 0).toLocaleString('en-GB');
}

/**
 * A bar list: label, a proportional bar, and the count.
 *
 * One hue for every bar — length already encodes the magnitude, so shading each
 * bar differently would double-encode it. The count sits beside each row, which
 * makes the panel its own table view rather than hiding values in a tooltip.
 */
function aleRenderBars(selector, rows, emptyText) {
    const container = $(selector);
    container.empty();

    if (!rows || !rows.length) {
        container.append(
            $('<p>').addClass('text-sm text-gray-400 py-6 text-center').text(emptyText)
        );
        return;
    }

    const max = Math.max(...rows.map(r => Number(r.value) || 0), 1);

    rows.forEach(row => {
        const value = Number(row.value) || 0;
        const pct   = Math.max((value / max) * 100, 2); // keep a sliver visible

        const line = $('<div>').addClass('flex items-center gap-3');

        line.append(
            $('<span>')
                .addClass('w-28 shrink-0 truncate text-xs font-semibold text-gray-600')
                .attr('title', row.label)
                .text(row.label)
        );

        // Track + fill. Rounded ends, thin mark, recessive track.
        line.append(
            $('<div>').addClass('flex-1 h-2.5 bg-gray-100 rounded-full overflow-hidden').append(
                $('<div>').addClass('h-full bg-blue-600 rounded-full').css('width', pct + '%')
            )
        );

        line.append(
            $('<span>')
                .addClass('w-10 shrink-0 text-right text-xs font-bold text-gray-800 tabular-nums')
                .text(aleNumber(value))
        );

        container.append(line);
    });
}
