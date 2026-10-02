<script>
  $(document).ready(function() {
    // API Base URL
    const API_BASE = '/api';
    
    // Current page and filters
    let currentPage = 1;
    let currentFilters = {};
    
    // Indexed Files functionality
    let selectedIndexedFiles = [];
    
    // Try to load data from API, but keep original UI functionality
    tryLoadApiData();
    
    // Load indexed files by default since it's the active tab
    loadIndexedFiles();
    
    // Initialize header button for indexed files tab (default active tab)
    updateHeaderButtonForIndexedTab();
    
    // Handle header track button click
    $('#header-track-btn').click(function() {
      if (selectedIndexedFiles.length === 0) {
        // If no files selected, go to regular create page
        window.location.href = '{{ route("filetracker.create") }}';
      } else if (selectedIndexedFiles.length === 1) {
        // Single file tracking
        const fileId = selectedIndexedFiles[0].id;
        window.location.href = `{{ route("filetracker.create") }}?files=${fileId}`;
      } else {
        // Batch tracking
        const fileIds = selectedIndexedFiles.map(f => f.id).join(',');
        window.location.href = `{{ route("filetracker.create") }}?files=${fileIds}&batch=true`;
      }
    });
    
    // Toggle RFID mode
    $('#rfid-mode').change(function() {
      if ($(this).is(':checked')) {
        $('#scan-rfid-btn').removeClass('hidden');
      } else {
        $('#scan-rfid-btn').addClass('hidden');
      }
    });
    
    // RFID scan button
    $('#scan-rfid-btn').click(function() {
      const $button = $(this);
      $button.html(`
        <svg class="animate-spin h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        Scanning...
      `);
      $button.addClass('bg-yellow-100 text-yellow-800 border-yellow-300');
      $button.prop('disabled', true);
      
      // Simulate scanning process
      setTimeout(() => {
        $button.html(`
          <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
          </svg>
          Scan RFID Tags
        `);
        $button.removeClass('bg-yellow-100 text-yellow-800 border-yellow-300');
        $button.prop('disabled', false);
        
        // Show results modal
        $('#rfid-modal').removeClass('hidden');
      }, 2000);
    });
    
    // Close RFID modal
    $('#close-rfid-modal').click(function() {
      $('#rfid-modal').addClass('hidden');
    });
    
    // Tab functionality (enhanced for indexed files and tracking files)
    $('.tab-button').off('click').on('click', function(e) {
      e.preventDefault();
      const tabId = $(this).data('tab');
      
      // Update active tab button
      $('.tab-button').removeClass('active bg-white shadow');
      $(this).addClass('active bg-white shadow');
      
      // Handle different tab content
      if (tabId === 'indexed') {
        // Show indexed files tab content
        $('#indexed-files-content').show();
        $('#tracking-files-content').hide();
        loadIndexedFiles();
        // Enable header button functionality for indexed files
        updateHeaderButtonForIndexedTab();
        
        // Hide File Details sidebar for indexed files tab
        $('.file-details').hide();
      } else {
        // Show tracking files tab content
        $('#indexed-files-content').hide();
        $('#tracking-files-content').show();
        
        // Reset header button for other tabs
        $('#header-track-btn').prop('disabled', false);
        $('#header-track-text').text('Track New File');
        
        // Show File Details sidebar for other tabs
        $('.file-details').show();
        
        // Apply status filtering for tracking files based on tab
        filterTrackingFilesByTab(tabId);
      }
    });

    // Filter tracking files based on tab selection
    function filterTrackingFilesByTab(tabId) {
      $('.file-row').each(function() {
        const $row = $(this);
        const status = $row.data('status');
        let shouldShow = false;

        switch(tabId) {
          case 'all':
            shouldShow = true;
            break;
          case 'in-process':
            // Show files with status Pending or On Hold
            shouldShow = (status === 'pending' || status === 'on-hold');
            break;
          case 'pending':
            shouldShow = (status === 'pending');
            break;
          case 'on-hold':
            shouldShow = (status === 'on-hold');
            break;
          case 'completed':
            shouldShow = (status === 'completed');
            break;
        }

        if (shouldShow) {
          $row.show();
        } else {
          $row.hide();
        }
      });
    }

    // Load indexed files via AJAX
    function loadIndexedFiles() {
      $('#indexed-files-loading').show();
      $('#indexed-files-empty').hide();
      $('#indexed-files-tbody').empty();

      $.ajax({
        url: '{{ route("filetracker.get-indexed-files") }}',
        method: 'GET',
        success: function(response) {
          $('#indexed-files-loading').hide();
          
          if (response.success && response.data.length > 0) {
            displayIndexedFiles(response.data);
          } else {
            $('#indexed-files-empty').show();
          }
        },
        error: function(xhr) {
          $('#indexed-files-loading').hide();
          console.error('Error loading indexed files:', xhr);
          $('#indexed-files-empty').show();
        }
      });
    }

    // Display indexed files in table
    function displayIndexedFiles(files) {
      let html = '';
      files.forEach(function(file) {
        html += `
          <tr class="border-b hover:bg-gray-50 indexed-file-row" data-file-id="${file.id}">
            <td class="py-3 px-4">
              <input type="checkbox" class="indexed-file-checkbox rounded border-gray-300" 
                     value="${file.id}" data-file-number="${file.file_number}" 
                     data-file-title="${file.file_title || ''}">
            </td>
            <td class="py-3 px-4 font-medium">${file.file_number}</td>
            <td class="py-3 px-4">${file.file_title || 'No Title'}</td>
            <td class="py-3 px-4">${file.land_use_type || 'N/A'}</td>
            <td class="py-3 px-4">${file.district || 'N/A'}</td>
            <td class="py-3 px-4">${formatDate(file.created_at)}</td>
            <td class="py-3 px-4 text-right">
              <div class="flex justify-end gap-2">
                <div class="relative">
                  <button class="action-menu-btn p-1 rounded-md hover:bg-gray-100" 
                          data-file-id="${file.id}" title="More Options">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"></path>
                    </svg>
                  </button>
                  <div class="action-dropdown absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-gray-200 z-10 hidden">
                    <div class="py-1">
                      <button class="start-tracking-dropdown-btn w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center" 
                              data-file-id="${file.id}">
                        <svg class="h-4 w-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Start Tracking File
                      </button>
                      <button class="view-file-btn w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center" 
                              data-file-id="${file.id}">
                        <svg class="h-4 w-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View Details
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        `;
      });
      $('#indexed-files-tbody').html(html);
    }

    // Format date helper
    function formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    }

    // Handle checkbox selection for indexed files
    $(document).on('change', '.indexed-file-checkbox', function() {
      updateSelectedFiles();
    });

    // Handle select all checkbox
    $(document).on('change', '#select-all-indexed, #header-checkbox', function() {
      const isChecked = $(this).is(':checked');
      $('.indexed-file-checkbox').prop('checked', isChecked);
      updateSelectedFiles();
    });

    // Update header button for indexed files tab
    function updateHeaderButtonForIndexedTab() {
      const count = selectedIndexedFiles.length;
      const $headerBtn = $('#header-track-btn');
      const $headerText = $('#header-track-text');
      
      if (count > 0) {
        $headerBtn.prop('disabled', false);
        if (count === 1) {
          $headerText.text('Track New File');
        } else {
          $headerText.text(`Track Batch Files (${count})`);
        }
      } else {
        $headerBtn.prop('disabled', true);
        $headerText.text('Track New File');
      }
    }

    // Update selected files count and header button state
    function updateSelectedFiles() {
      selectedIndexedFiles = [];
      $('.indexed-file-checkbox:checked').each(function() {
        selectedIndexedFiles.push({
          id: $(this).val(),
          file_number: $(this).data('file-number'),
          file_title: $(this).data('file-title')
        });
      });

      const count = selectedIndexedFiles.length;
      $('#selected-count').text(`${count} selected`);
      
      // Update header button
      updateHeaderButtonForIndexedTab();
    }

    // Handle individual start tracking button
    $(document).on('click', '.start-tracking-btn', function() {
      const fileId = $(this).data('file-id');
      window.location.href = `{{ route('filetracker.create') }}?files=${fileId}`;
    });

    // Handle action menu dropdown toggle
    $(document).on('click', '.action-menu-btn', function(e) {
      e.stopPropagation();
      const $dropdown = $(this).siblings('.action-dropdown');
      
      // Close all other dropdowns
      $('.action-dropdown').not($dropdown).addClass('hidden');
      
      // Toggle current dropdown
      $dropdown.toggleClass('hidden');
    });

    // Handle start tracking from dropdown
    $(document).on('click', '.start-tracking-dropdown-btn', function(e) {
      e.stopPropagation();
      const fileId = $(this).data('file-id');
      window.location.href = `{{ route('filetracker.create') }}?files=${fileId}`;
    });

    // Handle view file from dropdown
    $(document).on('click', '.view-file-btn', function(e) {
      e.stopPropagation();
      const fileId = $(this).data('file-id');
      // Load file details in sidebar
      loadIndexedFileDetails(fileId);
      
      // Close dropdown
      $(this).closest('.action-dropdown').addClass('hidden');
    });

    // Handle update file from dropdown (for tracked files)
    $(document).on('click', '.update-file-btn', function(e) {
      e.stopPropagation();
      const trackingId = $(this).data('tracking-id');
      window.location.href = `{{ route('filetracker.create') }}?update=${trackingId}`;
    });

    // Load indexed file details for sidebar
    function loadIndexedFileDetails(fileId) {
      $.ajax({
        url: '{{ route("filetracker.get-indexed-files") }}',
        method: 'GET',
        success: function(response) {
          if (response.success && response.data.length > 0) {
            const file = response.data.find(f => f.id == fileId);
            if (file) {
              updateSidebarWithIndexedFile(file);
            }
          }
        },
        error: function(xhr) {
          console.error('Error loading indexed file details:', xhr);
        }
      });
    }

    // Update sidebar with indexed file data
    function updateSidebarWithIndexedFile(file) {
      // Update title
      $('.file-details h3').text(file.file_title || 'File Title Not Available');
      
      // Update ID display
      $('.file-details .px-6.py-4.border-b p.text-sm.text-gray-500').text(`IDX-${String(file.id).padStart(6, '0')}`);
      
      // Update status badge for indexed file
      $('.file-details .px-6.py-4.border-b .badge').removeClass().addClass('badge badge-secondary').text('Not Tracked');
      
      // Update file numbers
      const $numbers = $('.file-details').find('p.text-sm.font-medium:contains("File Numbers")').closest('div').next('.space-y-1');
      $numbers.find('div:contains("File Number:")').find('p.text-xs.font-medium').text(file.file_number);
      
      // Clear tracking-specific fields for indexed files
      $('.file-details').find('p.text-sm.font-medium:contains("Current Location")').parent().find('p.text-sm').last().text('Not Set');
      $('.file-details').find('p.text-sm.font-medium:contains("Current Handler")').parent().find('p.text-sm').last().text('Not Assigned');
      $('.file-details').find('p.text-sm.font-medium:contains("Date Received")').parent().find('p.text-sm').last().text('Not Set');
      $('.file-details').find('p.text-sm.font-medium:contains("Due Date")').parent().find('p.text-sm').first().text('Not Set');
      
      // Clear RFID info
      const $rfidWrap = $('.file-details').find('p.text-sm.font-medium:contains("RFID Tag")').parent();
      $rfidWrap.find('p.text-sm').first().text('Not assigned');
      $rfidWrap.find('p.text-xs.text-gray-500').text('No RFID tag assigned to this file');
      
      // Clear movement history
      const $history = $('.file-details').find('h4:contains("Movement History")').next();
      $history.empty().append('<div class="text-center py-4"><p class="text-sm text-gray-500">File not yet tracked</p></div>');
      
      // Highlight the selected indexed file row
      $('.indexed-file-row').removeClass('bg-gray-50');
      $(`.indexed-file-row[data-file-id="${file.id}"]`).addClass('bg-gray-50');
    }

    // File view functionality (clicking on file rows or view buttons) - dynamic sidebar update (no reload)
    $(document).on('click', '.file-row, .file-view-btn', function(e) {
      e.preventDefault();

      // Get the tracking ID from the row/button
      const trackingId = $(this).closest('tr').data('tracking-id') || $(this).data('tracking-id');
      if (!trackingId) {
        console.error('No tracking ID found');
        return;
      }

      // Highlight the selected row
      $('.file-row').removeClass('bg-gray-50');
      $(this).closest('tr').addClass('bg-gray-50');

      // Fetch file details via API and update sidebar
      $.ajax({
        url: `${API_BASE}/file-trackings/${trackingId}`,
        method: 'GET',
        success: function(response) {
          if (response.success && response.data) {
            updateSidebarDetails(response.data);
          } else {
            console.error('Failed to load file details');
          }
        },
        error: function(xhr) {
          console.error('Error loading file details:', xhr);
        }
      });
    });

    // Helper function to format date strings
    function formatDateString(dateString) {
      if (!dateString) return 'Not Set';
      
      // Handle different date formats
      const date = new Date(dateString);
      if (isNaN(date.getTime())) return 'Invalid Date';
      
      // Return formatted date as YYYY-MM-DD
      return date.toISOString().split('T')[0];
    }

    function updateSidebarDetails(tracking) {
      // Update ID
      const idText = 'TRK-' + String(tracking.id).padStart(6, '0');
      $('.file-details .px-6.py-4.border-b p.text-sm.text-gray-500').text(idText);

      // Update title
      const title = tracking.file_indexing?.file_title || 'File Title Not Available';
      $('.file-details h2').nextAll('div').find('h3').text(title);

      // Update status badge with proper formatting
      const statusMap = {
        'in_process': { text: 'In Process', cls: 'badge-default' },
        'pending': { text: 'Pending', cls: 'badge-warning' },
        'on_hold': { text: 'On Hold', cls: 'badge-destructive' },
        'completed': { text: 'Completed', cls: 'badge-outline' },
        'active': { text: 'Active', cls: 'badge-default' },
        'checked_out': { text: 'Checked Out', cls: 'badge-warning' },
        'overdue': { text: 'Overdue', cls: 'badge-destructive' },
        'returned': { text: 'Returned', cls: 'badge-outline' },
        'lost': { text: 'Lost', cls: 'badge-destructive' },
        'archived': { text: 'Archived', cls: 'badge-outline' }
      };
      const statusInfo = statusMap[tracking.status] || { text: tracking.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()), cls: 'badge-secondary' };
      const $badge = $('.file-details .px-6.py-4.border-b .badge');
      $badge.removeClass().addClass('badge ' + statusInfo.cls).text(statusInfo.text);

      // Update numbers
      const fileNumber = tracking.file_indexing?.file_number || 'N/A';
      const oldFileNumber = tracking.file_indexing?.old_file_number || null;
      const surveyPlan = tracking.file_indexing?.survey_plan_number || null;
      
      const $numbers = $('.file-details').find('p.text-sm.font-medium:contains("File Numbers")').closest('div').next('.space-y-1');
      $numbers.find('div:contains("File Number:")').find('p.text-xs.font-medium').text(fileNumber);
      if (oldFileNumber) {
        // ensure exists or append
      }

      // Update RFID
      const rfid = tracking.rfid_tag || null;
      const $rfidWrap = $('.file-details').find('p.text-sm.font-medium:contains("RFID Tag")').parent();
      if (rfid) {
        $rfidWrap.find('p.text-sm').first().text(rfid);
        $rfidWrap.find('p.text-xs.text-gray-500').text('Last updated: ' + (tracking.updated_at || '')); 
      } else {
        $rfidWrap.find('p.text-sm').first().text('Not assigned');
        $rfidWrap.find('p.text-xs.text-gray-500').text('No RFID tag assigned to this file');
      }

      // Update Current Location / Handler
      $('.file-details').find('p.text-sm.font-medium:contains("Current Location")').parent().find('p.text-sm').last().text(tracking.current_location || 'Not Set');
      $('.file-details').find('p.text-sm.font-medium:contains("Current Handler")').parent().find('p.text-sm').last().text(tracking.current_handler || 'Not Assigned');

      // Update dates with proper formatting
      const dateReceived = formatDateString(tracking.date_received);
      const dueDate = formatDateString(tracking.due_date);
      
      $('.file-details').find('p.text-sm.font-medium:contains("Date Received")').parent().find('p.text-sm').last().text(dateReceived);
      const $dueWrap = $('.file-details').find('p.text-sm.font-medium:contains("Due Date")').parent();
      if (tracking.due_date) {
        $dueWrap.find('p.text-sm').first().text(dueDate);
      } else {
        $dueWrap.find('p.text-sm').first().text('Not Set');
      }

      // Movement history (top 5)
      const $history = $('.file-details').find('h4:contains("Movement History")').next();
      $history.empty();
      if (tracking.movement_history && tracking.movement_history.length > 0) {
        tracking.movement_history.slice(0,5).forEach(mv => {
          const when = mv.timestamp ? new Date(mv.timestamp) : null;
          const whenText = when ? when.toISOString().slice(0,16).replace('T',' ') : '';
          $history.append(`
            <div class="relative pl-5 pb-3">
              <div class="absolute top-0 left-0 h-5 w-5 rounded-full bg-blue-600 flex items-center justify-center">
                <div class="h-2 w-2 rounded-full bg-white"></div>
              </div>
              <div class="ml-2">
                <div class="flex items-center text-xs text-gray-500">${whenText}</div>
                <p class="text-sm font-medium mt-1">${(mv.action || 'Action').replace('_',' ')}</p>
                ${mv.to_location || mv.initial_location ? `<div class="flex items-center text-xs mt-1">${mv.from_location ? mv.from_location + ' → ' : ''}${mv.to_location || mv.initial_location}</div>` : ''}
                ${mv.user_name ? `<div class="flex items-center text-xs mt-1">${mv.user_name}</div>` : ''}
                ${mv.reason ? `<p class="text-xs text-gray-500 mt-1">${mv.reason}</p>` : ''}
              </div>
            </div>
          `);
        });
      } else {
        $history.append('<div class="text-center py-4"><p class="text-sm text-gray-500">No movement history available</p></div>');
      }
    }

    // View buttons from RFID modal
    $('.view-file-btn').click(function() {
      const fileId = $(this).closest('tr').find('td:nth-child(2)').text();
      const fileNumber = $(this).closest('tr').find('td:nth-child(3)').text();
      
      // Close the modal
      $('#rfid-modal').addClass('hidden');
      
      // Update file details
      updateFileDetails(fileId, fileNumber, 'in-process');
      
      // Find and highlight the corresponding row
      $('.file-row').removeClass('bg-gray-50');
      $('.file-row').each(function() {
        if($(this).find('td:first').text() === fileId) {
          $(this).addClass('bg-gray-50');
        }
      });
    });
    
    // Function to update file details sidebar
    function updateFileDetails(fileId, fileNumber, status) {
      // This is simulated - in a real app you'd fetch data from the server
      
      // Update file ID and number
      $('.file-details h2 + p').text(fileId);
      
      // Update status badge
      let statusText = 'In Process';
      let badgeClass = 'badge-default';
      
      if (status === 'pending') {
        statusText = 'Pending';
        badgeClass = 'badge-warning';
      } else if (status === 'on-hold') {
        statusText = 'On Hold';
        badgeClass = 'badge-destructive';
      } else if (status === 'awaiting') {
        statusText = 'Awaiting Approval';
        badgeClass = 'badge-secondary';
      } else if (status === 'completed') {
        statusText = 'Completed';
        badgeClass = 'badge-outline';
      }
      
      $('.file-details .badge').attr('class', 'badge ' + badgeClass).text(statusText);
      
      // Update file number in details
      $('.file-details .text-xs.font-medium:contains("RES")').text(fileNumber);
    }
    
    // Generate QR code (if qrcodejs library is available)
    if (typeof QRCode !== 'undefined') {
      const qrElement = document.getElementById('qr-code');
      if (qrElement) {
        const qrData = JSON.stringify({
          id: "TRK-2023-001",
          fileNumber: "RES-2015-4859",
          kangisFileNo: "KNGP 00338",
          newKangisFileNo: "KNO001",
          dateReceived: "2023-06-15",
          dueDate: "2023-06-30"
        });
        
        // Clear previous content
        qrElement.innerHTML = '';
        
        // Create new QR code
        const qr = new QRCode(qrElement, {
          text: qrData,
          width: 96,
          height: 96,
          colorDark: "#000000",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.H
        });
      }
    }
    
    // Search functionality (original behavior)
    $('#search-input').on('keyup', function() {
      const value = $(this).val().toLowerCase();
      $('.file-row').filter(function() {
        const rowText = $(this).text().toLowerCase();
        $(this).toggle(rowText.indexOf(value) > -1);
      });
    });
    
    // API Integration Functions (background functionality)
    
    // Try to load data from API
    function tryLoadApiData() {
      $.ajax({
        url: `${API_BASE}/file-trackings?per_page=10`,
        method: 'GET',
        success: function(response) {
          if (response.success && response.data.data && response.data.data.length > 0) {
            console.log('API data available:', response.data.data.length, 'records');
            // Optionally enhance the existing table with API data
            enhanceTableWithApiData(response.data.data);
          }
        },
        error: function(xhr) {
          console.log('API not available, using static data');
        }
      });
    }
    
    // Enhance existing table with API data
    function enhanceTableWithApiData(apiData) {
      // Add RFID indicators to existing rows if they have RFID tags
      $('.file-row').each(function(index) {
        if (apiData[index] && apiData[index].rfid_tag) {
          const fileNumberCell = $(this).find('td:nth-child(2) .flex');
          fileNumberCell.append('<span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded ml-2">RFID</span>');
        }
      });
    }
    
    // RFID scan functionality (enhanced with API)
    function scanRfidTag(rfidTag) {
      $.ajax({
        url: `${API_BASE}/rfid/scan/${rfidTag}`,
        method: 'GET',
        success: function(response) {
          if (response.success) {
            showRfidScanResult(response.data);
          } else {
            showError('RFID tag not found');
          }
        },
        error: function(xhr) {
          console.error('Error scanning RFID tag:', xhr);
          // Fallback to showing the modal with static data
          $('#rfid-modal').removeClass('hidden');
        }
      });
    }
    
    // Show RFID scan result
    function showRfidScanResult(tracking) {
      // Update modal content with tracking data
      const modalContent = `
        <div class="p-6">
          <h3 class="text-lg font-semibold mb-4">RFID Scan Result</h3>
          <div class="space-y-3">
            <div><strong>File Number:</strong> ${tracking.file_indexing?.file_number || 'N/A'}</div>
            <div><strong>Current Location:</strong> ${tracking.current_location || 'N/A'}</div>
            <div><strong>Current Handler:</strong> ${tracking.current_handler || 'N/A'}</div>
            <div><strong>Status:</strong> ${getStatusBadge(tracking.status)}</div>
            <div><strong>RFID Tag:</strong> ${tracking.rfid_tag}</div>
            ${tracking.is_overdue ? '<div class="text-red-600 font-semibold">⚠️ This file is overdue!</div>' : ''}
          </div>
          <div class="mt-6 flex gap-2">
            <button class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm" onclick="viewFileDetails(${tracking.id})">View Details</button>
            <button class="bg-gray-600 text-white px-4 py-2 rounded-md text-sm" onclick="updateFileLocation(${tracking.id})">Update Location</button>
            <button class="border rounded-md px-4 py-2 text-sm" onclick="closeRfidModal()">Close</button>
          </div>
        </div>
      `;
      
      // Update the modal if it exists, otherwise show the original modal
      const modalElement = $('#rfid-modal .modal-content');
      if (modalElement.length) {
        modalElement.html(modalContent);
      }
      $('#rfid-modal').removeClass('hidden');
    }
    
    // Get status badge HTML
    function getStatusBadge(status) {
      const badges = {
        'active': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>',
        'checked_out': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Checked Out</span>',
        'overdue': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Overdue</span>',
        'returned': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Returned</span>',
        'lost': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Lost</span>',
        'archived': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Archived</span>'
      };
      return badges[status] || '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Unknown</span>';
    }
    
    // View file details (enhanced with API)
    function viewFileDetails(trackingId) {
      $.ajax({
        url: `${API_BASE}/file-trackings/${trackingId}`,
        method: 'GET',
        success: function(response) {
          if (response.success) {
            updateFileDetailsFromApi(response.data);
          }
        },
        error: function(xhr) {
          console.error('Error loading file details:', xhr);
        }
      });
    }
    
    // Load file details from API
    function loadFileDetails(trackingId) {
      $.ajax({
        url: `${API_BASE}/file-trackings/${trackingId}`,
        method: 'GET',
        success: function(response) {
          if (response.success) {
            // Reload the page to show the selected file details
            // In a more sophisticated implementation, you would update the sidebar dynamically
            window.location.href = window.location.pathname + '?selected=' + trackingId;
          } else {
            showError('Failed to load file details');
          }
        },
        error: function(xhr) {
          console.error('Error loading file details:', xhr);
          showError('Error loading file details');
        }
      });
    }
    
    // Update file details from API data
    function updateFileDetailsFromApi(tracking) {
      updateFileDetails(tracking.id, tracking.file_indexing?.file_number, tracking.status);
      closeRfidModal();
    }
    
    // Show error message
    function showError(message) {
      console.error('Error:', message);
      // You can implement a better notification system here
    }
    
    // Show success message
    function showSuccess(message) {
      console.log('Success:', message);
      // You can implement a better notification system here
    }
    
    // Close RFID modal
    function closeRfidModal() {
      $('#rfid-modal').addClass('hidden');
    }
    
    // Update file location (enhanced with API)
    function updateFileLocation(trackingId) {
      const newLocation = prompt('Enter new location:');
      if (newLocation) {
        $.ajax({
          url: `${API_BASE}/file-trackings/${trackingId}`,
          method: 'PUT',
          data: JSON.stringify({
            current_location: newLocation,
            reason: 'Location updated via RFID scan'
          }),
          contentType: 'application/json',
          success: function(response) {
            if (response.success) {
              showSuccess('Location updated successfully');
              closeRfidModal();
            } else {
              showError('Failed to update location');
            }
          },
          error: function(xhr) {
            console.error('Error updating location:', xhr);
            showError('Error updating location');
          }
        });
      }
    }
    
    // Make functions globally available for onclick handlers
    window.viewFileDetails = viewFileDetails;
    window.updateFileLocation = updateFileLocation;
    window.closeRfidModal = closeRfidModal;
    window.scanRfidTag = scanRfidTag;
    
    // Print selected file function
    window.printSelectedFile = function() {
      // Get the currently selected/highlighted tracking ID
      const $selectedRow = $('.file-row.bg-gray-50');
      let trackingId = null;
      
      if ($selectedRow.length > 0) {
        trackingId = $selectedRow.data('tracking-id');
      }
      
      if (!trackingId) {
        // Try to get from sidebar content
        const sidebarId = $('.file-details .px-6.py-4.border-b p.text-sm.text-gray-500').text();
        if (sidebarId && sidebarId.includes('TRK-')) {
          trackingId = parseInt(sidebarId.replace('TRK-', '').replace(/^0+/, ''));
        }
      }
      
      if (trackingId) {
        const printUrl = '{{ route("filetracker.print") }}?id=' + trackingId;
        console.log('Opening print URL:', printUrl);
        window.open(printUrl, '_blank');
      } else {
        alert('Please select a file first by clicking on it in the table.');
      }
    };

    // Initialize the page - ensure indexed tab starts with hidden sidebar
    if ($('.tab-button.active').data('tab') === 'indexed') {
      $('.file-details').hide();
    }
  });

  // Fix for table row clicks - add simple click handler
  $(document).ready(function() {
    $(document).on('click', 'tr.file-row', function(e) {
      e.preventDefault();
      const trackingId = $(this).data('tracking-id');
      if (trackingId) {
        console.log('Row clicked, tracking ID:', trackingId);
        
        // Highlight the selected row
        $('tr.file-row').removeClass('bg-gray-50');
        $(this).addClass('bg-gray-50');
        
        // Update sidebar with row data (simple fallback)
        const fileId = 'TRK-' + String(trackingId).padStart(6, '0');
        const status = $(this).find('.badge').text().trim();
        const location = $(this).find('td:nth-child(4)').text().trim();
        const handler = $(this).find('td:nth-child(5)').text().trim();
        
        // Update sidebar elements
        $('.file-details .px-6.py-4.border-b p.text-sm.text-gray-500').text(fileId);
        if (status) $('.file-details .px-6.py-4.border-b .badge').text(status);
        
        // Update location and handler if elements exist
        const locationEl = $('.file-details').find('p.text-sm.font-medium:contains("Current Location")').parent().find('p.text-sm').last();
        if (locationEl.length && location) locationEl.text(location);
        
        const handlerEl = $('.file-details').find('p.text-sm.font-medium:contains("Current Handler")').parent().find('p.text-sm').last();
        if (handlerEl.length && handler) handlerEl.text(handler);
        
        console.log('Sidebar updated with basic info');
      }
    });
  });
</script>