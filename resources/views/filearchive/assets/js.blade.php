<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
  $(document).ready(function () {
    console.log('[File Archive] Document ready, initializing');
    lucide.createIcons();

    const $viewerDialog = $('#document-viewer-dialog');
    console.log('[File Archive] $viewerDialog found:', $viewerDialog.length > 0, 'Element:', $viewerDialog[0]);
    let documentRequestToken = 0;
    let activeFileCardIndex = -1;
    const statusColorClasses = ['bg-green-600', 'bg-orange-500', 'bg-amber-600'];
    const defaultHeaderState = buildRegistryState();

    $('.dialog-backdrop').hide();

    initFileTrackerStatusColors();

    // Handle sort dropdown change
    $('#sort-select').on('change', function () {
        const sortValue = $(this).val();
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('sort', sortValue);
        currentUrl.searchParams.delete('page'); // Reset to first page
        window.location.href = currentUrl.toString();
    });

    function fileCards() {
      return $('.file-card');
    }

    function updateFileNavigation() {
      const $cards = fileCards();
      const total = $cards.length;
      const hasSelection = activeFileCardIndex >= 0 && activeFileCardIndex < total;

      $('#viewer-previous-file').prop('disabled', !hasSelection || activeFileCardIndex === 0);
      $('#viewer-next-file').prop('disabled', !hasSelection || activeFileCardIndex >= total - 1);
      $('#viewer-file-position').text(hasSelection ? `File ${activeFileCardIndex + 1} of ${total}` : `File 0 of ${total}`);

      if (hasSelection) {
        const previousNumber = activeFileCardIndex > 0
          ? $cards.eq(activeFileCardIndex - 1).attr('data-file-number')
          : null;
        const nextNumber = activeFileCardIndex < total - 1
          ? $cards.eq(activeFileCardIndex + 1).attr('data-file-number')
          : null;
        $('#viewer-previous-file').attr('title', previousNumber ? `Previous file: ${previousNumber}` : 'No previous file');
        $('#viewer-next-file').attr('title', nextNumber ? `Next file: ${nextNumber}` : 'No next file');
      }
    }

    function openFileCardAt(index) {
      const $cards = fileCards();
      if (index < 0 || index >= $cards.length) return;

      activeFileCardIndex = index;
      const $card = $cards.eq(index);
      const pagesUrl = $card.attr('data-pages-url');
      const meta = {
        number: $card.attr('data-file-number') || '-',
        title: $card.attr('data-file-title') || '-'
      };
      if (!pagesUrl) {
        console.warn('No document pages URL found for file card.');
        return;
      }

      updateFileNavigation();
      openDocumentViewer(pagesUrl, false, meta);
    }

    $('.file-card').on('click', function () {
      openFileCardAt(fileCards().index(this));
    });

    $('#viewer-previous-file').on('click', function () {
      openFileCardAt(activeFileCardIndex - 1);
    });

    $('#viewer-next-file').on('click', function () {
      openFileCardAt(activeFileCardIndex + 1);
    });

    updateFileNavigation();

    $('.dialog-backdrop').on('click', function (e) {
      if ($(e.target).hasClass('dialog-backdrop')) {
        $(this).fadeOut('fast');
      }
    });

    $('#close-details, #close-viewer').on('click', function () {
      $(this).closest('.dialog-backdrop').fadeOut('fast');
    });

    $('#toggle-star').on('click', function () {
      $(this).find('i').toggleClass('fill-yellow-400 text-yellow-400');
    });

    function initFileTrackerStatusColors() {
      const $cards = $('.file-card');
      if (!$cards.length) {
        return;
      }

      const fileNumbers = [...new Set(
        $cards.map(function () {
          const value = $(this).data('fileNumber');
          return typeof value === 'string' ? value.trim() : '';
        })
          .get()
          .filter(function (value) {
            return value.length > 0;
          })
      )];

      if (!fileNumbers.length) {
        return;
      }

      fetchFileTrackerStatuses(fileNumbers)
        .done(function (response) {
          const statusMap = buildStatusMapFromResponse(response);
          applyTrackerStatuses($cards, statusMap);
        })
        .fail(function (error) {
          console.error('Failed to fetch file tracker statuses', error);
        });
    }

    function fetchFileTrackerStatuses(fileNumbers) {
      return $.ajax({
        url: '/api/file-trackers',
        method: 'GET',
        data: {
          'file_numbers': fileNumbers,
          'per_page': Math.max(fileNumbers.length, 15),
          'sort_by': 'updated_at',
          'sort_order': 'desc'
        },
        cache: false
      });
    }

    function buildStatusMapFromResponse(response) {
      const trackers = normalizeTrackerArray(response);
      return trackers.reduce(function (acc, tracker) {
        if (!tracker || !tracker.file_number) {
          return acc;
        }

        const normalizedFileNumber = normalizeFileNumber(tracker.file_number);
        const derivedStatus = deriveTrackerStatus(tracker);
        derivedStatus.details = buildTrackerDetails(tracker);

        if (normalizedFileNumber && derivedStatus) {
          const previous = acc[normalizedFileNumber];

          // A re-dispatched file has old Completed trackers alongside the
          // current ACTIVE one. Ground truth (App\Services\FileLocationResolver):
          // the file is physically OUT if ANY tracker derives in_transit, so
          // that derivation must win no matter where it sits in the response —
          // previously the last tracker overwrote the map and wrongly turned
          // logged-out-again files green.
          if (previous) {
            previous.hasHistory = previous.hasHistory || trackerHasMovementHistory(tracker);
            if (previous.status !== 'in_transit' && derivedStatus.status === 'in_transit') {
              derivedStatus.hasHistory = previous.hasHistory;
              acc[normalizedFileNumber] = derivedStatus;
            }
            return acc;
          }

          derivedStatus.hasHistory = trackerHasMovementHistory(tracker);
          acc[normalizedFileNumber] = derivedStatus;
        }

        return acc;
      }, {});
    }

    // Details shown as label/value rows in the card tooltip.
    function buildTrackerDetails(tracker) {
      if (!tracker) {
        return {};
      }

      return {
        registry: String(tracker.origin_office_name || tracker.registry_code || '').trim(),
        department: String(tracker.department || '').trim(),
        officer: String(tracker.receiving_officer_name || tracker.current_officer || '').trim(),
        dateRequested: formatTrackerDate(tracker.date_requested || tracker.date_created || tracker.created_at)
      };
    }

    function formatTrackerDate(value) {
      if (!value) {
        return '';
      }

      const date = new Date(value);
      if (Number.isNaN(date.getTime())) {
        return '';
      }

      const pad = function (n) { return String(n).padStart(2, '0'); };
      let hours = date.getHours();
      const ampm = hours >= 12 ? 'PM' : 'AM';
      hours = hours % 12 || 12;

      return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate())
        + ' ' + hours + ':' + pad(date.getMinutes()) + ' ' + ampm;
    }

    function trackerHasMovementHistory(tracker) {
      if (!tracker) {
        return false;
      }

      let movementLog = tracker.movement_log;
      if (typeof movementLog === 'string') {
        try {
          movementLog = JSON.parse(movementLog);
        } catch (error) {
          movementLog = [];
        }
      }

      return Array.isArray(movementLog) && movementLog.length > 0;
    }

    function normalizeFileNumber(value) {
      if (typeof value !== 'string') {
        return '';
      }

      return value.trim().toUpperCase();
    }

    function normalizeTrackerArray(response) {
      if (!response) {
        return [];
      }

      const payload = response.data || response;

      if (Array.isArray(payload)) {
        return payload;
      }

      if (payload && Array.isArray(payload.data)) {
        return payload.data;
      }

      return [];
    }

    function getLatestMovementEntry(movementLog) {
      if (!Array.isArray(movementLog) || !movementLog.length) {
        return null;
      }

      return movementLog.reduce(function (latest, entry) {
        if (!entry) {
          return latest;
        }

        if (!latest) {
          return entry;
        }

        const currentTimestamp = parseMovementTimestamp(entry);
        const latestTimestamp = parseMovementTimestamp(latest);

        return currentTimestamp >= latestTimestamp ? entry : latest;
      }, null);
    }

    function parseMovementTimestamp(entry) {
      if (!entry) {
        return 0;
      }

      if (entry.timestamp) {
        const parsedTimestamp = Date.parse(entry.timestamp);
        if (!Number.isNaN(parsedTimestamp)) {
          return parsedTimestamp;
        }
      }

      const datePart = entry.log_in_date || entry.log_out_date;
      if (datePart) {
        const timePart = entry.log_in_time || entry.log_out_time || '00:00';
        const parsedDate = Date.parse(`${datePart}T${timePart}`);
        if (!Number.isNaN(parsedDate)) {
          return parsedDate;
        }
      }

      return 0;
    }

    function deriveTrackerStatus(tracker) {
      if (!tracker) {
        return buildRegistryState();
      }

      const trackerStatus = String(tracker.status || '').trim().toUpperCase();
      const movementLog = Array.isArray(tracker.movement_log) ? tracker.movement_log : [];

      // Ground truth — mirrors App\Services\FileLocationResolver: a file is physically
      // OUT of the registry only while its tracker is ACTIVE with a real movement log.
      // Any other status — Log-in (file logged back to the Registry), Completed,
      // Cancelled, or a workflow status — means the file is back in / with the registry.
      //
      // We must NOT infer transit from an "open" movement entry (log-in set, no log-out):
      // the log-back-to-registry action itself writes an open entry (status 'active') at
      // the registry office, so that heuristic wrongly kept logged-back files showing red.
      const isTransit = trackerStatus === 'ACTIVE' && movementLog.length > 0;

      if (isTransit) {
        // The entry the file is currently sitting on tells us where it physically is.
        const currentMovement = tracker.current_movement && isOpenMovementEntry(tracker.current_movement)
          ? tracker.current_movement
          : null;
        const activeEntry = movementLog.find(isOpenMovementEntry)
          || currentMovement
          || getLatestMovementEntry(movementLog);
        return buildTransitState(
          resolveTrackerLocation(tracker, activeEntry),
          resolveTrackerOfficer(tracker, activeEntry)
        );
      }

      return buildRegistryState();
    }

    // Resolve the file's current physical location using the same field-fallback
    // order as the create-file-tracker quick-search, preferring the active movement
    // entry's office (where the file currently is).
    function resolveTrackerLocation(tracker, entry) {
      const fromEntry = entry
        ? (entry.office_name || entry.receiving_office_name || entry.current_location
           || entry.location || entry.destination_office_name || entry.office)
        : '';
      return String(
        fromEntry
        || tracker.current_office_name
        || tracker.currentOffice
        || tracker.receiving_office_name
        || tracker.receivingOfficeName
        || tracker.location
        || tracker.current_location
        || ''
      ).trim();
    }

    function resolveTrackerOfficer(tracker, entry) {
      const fromEntry = entry
        ? (entry.receiving_officer_name || entry.officer_name || entry.officer)
        : '';
      return String(
        fromEntry
        || tracker.receiving_officer_name
        || tracker.receivingOfficerName
        || tracker.current_officer
        || tracker.current_handler
        || ''
      ).trim();
    }

    function isOpenMovementEntry(entry) {
      if (!entry) {
        return false;
      }

      const status = String(entry.status || '').trim().toUpperCase();
      const hasLoggedIn = Boolean(entry.log_in_time || entry.log_in_date);
      const hasLoggedOut = Boolean(entry.log_out_time || entry.log_out_date);

      if (hasLoggedIn && !hasLoggedOut) {
        return true;
      }

      return ['ACTIVE', 'PENDING_ACCEPTANCE', 'IN_PROGRESS'].includes(status);
    }

    function buildTransitState(location, officer) {
      return {
        colorClass: 'bg-orange-500',
        label: 'File in Transit',
        status: 'in_transit',
        location: location || '',
        officer: officer || ''
      };
    }

    function buildRegistryState() {
      return {
        colorClass: 'bg-green-600',
        label: 'File in the Registry',
        status: 'registry',
        location: '',
        officer: ''
      };
    }

    function applyTrackerStatuses($cards, statusMap) {
      $cards.each(function () {
        const $card = $(this);
        const fileNumber = normalizeFileNumber($card.data('fileNumber'));
        const $header = $card.find('.file-card-status-header');

        if (!$header.length) {
          return;
        }

        const mapping = statusMap[fileNumber];
        updateHeaderColor($header, mapping);
        updateCardTooltip($header, mapping, $card.data('fileNumber'), $card.data('shelfLocation'));
        updatePrintHistoryButton($header, mapping, $card.data('fileNumber'));
      });
    }

    // Show a print button on files that are out now or were logged out before,
    // opening the printable File Movement History sheet in a new tab.
    function updatePrintHistoryButton($header, mapping, fileNumberRaw) {
      const $existing = $header.find('.print-history-btn');

      if (!mapping || !mapping.hasHistory) {
        $existing.remove();
        return;
      }

      if ($existing.length) {
        return;
      }

      const url = '{{ route('filearchive.movement-history.print') }}'
        + '?file_number=' + encodeURIComponent(fileNumberRaw || '');

      const $button = $(
        '<button type="button" class="print-history-btn inline-flex items-center justify-center w-5 h-5 rounded bg-white hover:bg-gray-100 text-gray-800 shadow-sm transition-colors" title="Print movement history">'
        + '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
        + '<polyline points="6 9 6 2 18 2 18 9"></polyline>'
        + '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>'
        + '<rect x="6" y="14" width="12" height="8"></rect>'
        + '</svg>'
        + '</button>'
      );

      $button.on('click', function (event) {
        event.stopPropagation();
        window.open(url, '_blank');
      });

      $header.find('.flex.items-center.space-x-2').first().append($button);
    }

    // Rebuild the hover tooltip from authoritative tracker data so an out-of-registry
    // file shows WHERE it currently is (office) and with whom (officer).
    function updateCardTooltip($header, mapping, fileNumberRaw, shelfLocationRaw) {
      const $tooltip = $header.find('.status-tooltip');
      if (!$tooltip.length) {
        return;
      }

      const state = mapping || defaultHeaderState;
      const esc = function (value) {
        return $('<div>').text(value == null ? '' : value).html();
      };
      const fileNumber = esc(fileNumberRaw);
      const shelfLocation = String(shelfLocationRaw || '').trim();
      const isOut = state.status === 'in_transit';
      const themeClass = isOut ? 'status-tooltip--out' : 'status-tooltip--in-registry';

      $tooltip.removeClass('status-tooltip--out status-tooltip--in-registry').addClass(themeClass);

      let body;
      if (isOut) {
        const details = state.details || {};
        const shelfRackValue = shelfLocation || details.shelfRack || details.shelf_location || details.shelfLocation || '—';
        const rows = [
          ['Registry', details.registry],
          ['Shelf/Rack', shelfRackValue],
          ['Department', details.department || state.location],
          ['Receiving Officer (holder)', details.officer || state.officer],
          ['Date Requested', details.dateRequested]
        ].filter(function (pair) { return pair[1]; });

        const rowsHtml = rows.map(function (pair) {
          return `<div class="tt-row"><span class="tt-label">${esc(pair[0])}</span><span class="tt-value">${esc(pair[1])}</span></div>`;
        }).join('');

        body =
          `<div class="text-gray-200 mb-1">File out of the Registry</div>` +
          (rowsHtml || `<div class="text-gray-300 mt-1">⏳ File is in transit</div>`);
      } else {
        const shelfRackValue = shelfLocation || '—';
        const rows = [
          ['Shelf/Rack', shelfRackValue]
        ];
        const rowsHtml = rows.map(function (pair) {
          return `<div class="tt-row"><span class="tt-label">${esc(pair[0])}</span><span class="tt-value">${esc(pair[1])}</span></div>`;
        }).join('');

        body =
          `<div class="text-gray-200">File in the Registry</div>` +
          (rowsHtml || `<div class="text-gray-300 mt-1">✅ File is in the registry</div>`);
      }

      $tooltip.html(`<div class="font-semibold mb-1">${fileNumber}</div>${body}`);
    }

    function updateHeaderColor($header, mapping) {
      const appliedState = mapping || defaultHeaderState;
      $header.removeClass(statusColorClasses.join(' '));
      $header.addClass(appliedState.colorClass);

      if (appliedState.label) {
        $header.attr('data-status-label', appliedState.label);
      } else {
        $header.removeAttr('data-status-label');
      }

      if (appliedState.status) {
        $header.attr('data-file-status', appliedState.status);
      } else {
        $header.removeAttr('data-file-status');
      }
    }

    function openDocumentViewer(pagesUrl, triggeredFromDetails = false, meta = null) {
      console.log('[Document Viewer] openDocumentViewer called with:', { pagesUrl, triggeredFromDetails, meta });
      const requestToken = ++documentRequestToken;
      console.log('[Document Viewer] Request token:', requestToken);

      resetDocumentViewerState(true, meta);
      console.log('[Document Viewer] Viewer state reset');
      
      console.log('[Document Viewer] Attempting to show modal. $viewerDialog:', $viewerDialog, '$viewerDialog.length:', $viewerDialog.length);
      $viewerDialog.fadeIn('fast');
      console.log('[Document Viewer] Modal fadeIn initiated');

      console.log('[Document Viewer] Starting AJAX request to:', pagesUrl);
      $.ajax({
        url: pagesUrl,
        method: 'GET',
        data: { _: Date.now() },
        cache: false
      })
        .done(function (response) {
          console.log('[Document Viewer] AJAX success. Response:', response);
          if (documentRequestToken !== requestToken) {
            console.warn('[Document Viewer] Request token mismatch, ignoring response');
            return;
          }

          if (response.success) {
            console.log('[Document Viewer] Response success. Loading', response.pages?.length || 0, 'pages');
            loadDocumentPages(response.file, response.pages || []);
            $('#viewer-file-number').text(response.file?.file_number || (meta?.number ?? '-'));
            $('#viewer-file-title').text(response.file?.file_title || (meta?.title ?? '-'));
            if (triggeredFromDetails) {
              $('#file-details-dialog').fadeOut('fast');
            }
          } else {
            console.error('[Document Viewer] Response not successful:', response.message);
            showViewerError(response.message || 'Unable to load document pages.');
          }
        })
        .fail(function (xhr) {
          console.error('[Document Viewer] AJAX failed. Status:', xhr.status, 'StatusText:', xhr.statusText, 'Response:', xhr.responseText);
          if (documentRequestToken !== requestToken) {
            console.warn('[Document Viewer] Request token mismatch on fail, ignoring');
            return;
          }

          showViewerError('Failed to load document pages. Status: ' + xhr.status);
        });
    }

    function resetDocumentViewerState(isLoading = false, meta = null) {
      if (typeof window.clearDocumentViewerData === 'function') {
        window.clearDocumentViewerData();
      }

      $('#pages-list').html(isLoading ? '<div class="p-4 text-sm text-gray-500">Loading document pages...</div>' : '');
      $('#page-info').html(`<span class="font-medium">${isLoading ? 'Loading document...' : 'Select a page'}</span>`);
      $('#page-indicator').text('Page 0 of 0');

      if (meta) {
        $('#viewer-file-number').text(meta.number || '-');
        $('#viewer-file-title').text(meta.title || '-');
      } else {
        $('#viewer-file-number').text('-');
        $('#viewer-file-title').text('-');
      }

      $('#document-image').attr('src', '').addClass('hidden');
      $('#document-pdf').attr('src', '').addClass('hidden');
      $('#document-placeholder').css('display', 'none');

      if (typeof updateTransform === 'function') {
        updateTransform();
      }
    }

    function showViewerError(message) {
      if (typeof window.clearDocumentViewerData === 'function') {
        window.clearDocumentViewerData();
      }
      $('#pages-list').html(`<div class="p-4 text-sm text-red-600">${message}</div>`);
      $('#page-info').html(`<span class="text-red-600">${message}</span>`);
      $('#page-indicator').text('Page 0 of 0');
      $('#viewer-file-number').text('-');
      $('#viewer-file-title').text('-');
      $('#document-image').attr('src', '').addClass('hidden');
      $('#document-pdf').attr('src', '').addClass('hidden');
      $('#document-placeholder').css('display', 'flex');
      const placeholderText = $('#document-placeholder').find('p');
      if (placeholderText.length) {
        placeholderText.text(message);
      }
    }

    window.openDocumentViewer = openDocumentViewer;
    console.log('[File Archive] window.openDocumentViewer exported:', typeof window.openDocumentViewer);
  });
</script>
