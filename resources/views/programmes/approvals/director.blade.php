@extends('layouts.app')

@section('page-title')
  {{ $PageTitle ?? __('KLAES') }}
@endsection

@section('styles')

@endsection

@section('content')
  <style>
    /* Enhanced Badge Styles */
    .badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.375rem 0.75rem;
      border-radius: 0.5rem;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.025em;
      box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      border: 1px solid transparent;
    }

    .badge-approved {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      color: #065f46;
      border-color: #10b981;
    }

    .badge-pending {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      color: #92400e;
      border-color: #f59e0b;
    }

    .badge-pending2 {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      color: #920e41;
      border-color: #a50427;
    }

    .badge-declined {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      color: #991b1b;
      border-color: #ef4444;
    }

    .badge-awaiting {
      background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
      color: #3730a3;
      border-color: #6366f1;
    }

    /* Enhanced Table Styles */
    .primary-table {
      border: 2px solid #3b82f6;
      border-radius: 0.75rem;
      overflow: hidden;
      box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.1), 0 2px 4px -1px rgba(59, 130, 246, 0.06);
    }

    .unit-table {
      border: 2px solid #8b5cf6;
      border-radius: 0.75rem;
      overflow: hidden;
      box-shadow: 0 4px 6px -1px rgba(139, 92, 246, 0.1), 0 2px 4px -1px rgba(139, 92, 246, 0.06);
    }

    .primary-table .table-header {
      background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
      color: white;
      font-weight: 600;
      text-align: left;
      padding: 1rem;
      border-bottom: 2px solid #1e40af;
      position: relative;
    }

    .unit-table .table-header {
      background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
      color: white;
      font-weight: 600;
      text-align: left;
      padding: 1rem;
      border-bottom: 2px solid #6d28d9;
      position: relative;
    }

    .primary-table .table-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, #60a5fa, #3b82f6, #1d4ed8);
    }

    .unit-table .table-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, #a78bfa, #8b5cf6, #7c3aed);
    }

    .table-cell {
      padding: 0.875rem 1rem;
      border-bottom: 1px solid #e5e7eb;
      transition: background-color 0.2s ease;
    }

    .primary-table tbody tr:hover {
      background-color: #eff6ff;
    }

    .unit-table tbody tr:hover {
      background-color: #f3f4f6;
    }

    /* Enhanced Tab Styles */
    .tab-primary {
      background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
      border: 2px solid #1e40af;
      box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);
    }

    .tab-primary:hover {
      background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
      transform: translateY(-1px);
      box-shadow: 0 6px 8px -1px rgba(59, 130, 246, 0.4);
    }

    .tab-unit {
      background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
      border: 2px solid #6d28d9;
      box-shadow: 0 4px 6px -1px rgba(139, 92, 246, 0.3);
    }

    .tab-unit:hover {
      background: linear-gradient(135deg, #7c3aed 0%, #6b21a8 100%);
      transform: translateY(-1px);
      box-shadow: 0 6px 8px -1px rgba(139, 92, 246, 0.4);
    }

    /* Enhanced Stats Cards */
    .stat-card {
      background: white;
      border-radius: 0.75rem;
      padding: 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      border: 1px solid #e5e7eb;
      transition: all 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    /* Section Headers */
    .section-header {
      position: relative;
      padding: 1rem 1.5rem;
      margin-bottom: 1rem;
      border-radius: 0.75rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .section-header-primary {
      background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
      border: 2px solid #3b82f6;
      color: #1e40af;
    }

    .section-header-unit {
      background: linear-gradient(135deg, #e9d5ff 0%, #ddd6fe 100%);
      border: 2px solid #8b5cf6;
      color: #6b21a8;
    }

    /* Filter Enhancements */
    .filter-container {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      padding: 1rem;
      margin-bottom: 1rem;
    }

    /* Action Button Enhancements */
    .action-menu {
      min-width: 200px;
      border-radius: 0.5rem;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .action-menu li a:hover {
      background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    }

    /* Responsive Improvements */
    @media (max-width: 768px) {
      .table-cell {
        padding: 0.5rem;
        font-size: 0.75rem;
      }

      .section-header {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
      }
    }

    .status-chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 600;
      border-radius: 9999px;
      padding: 0.25rem 0.75rem;
      border: 1px solid transparent;
      text-transform: uppercase;
      letter-spacing: 0.02em;
    }

    .status-chip-passed {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      color: #065f46;
      border-color: #34d399;
    }

    .status-chip-pending {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      color: #92400e;
      border-color: #f59e0b;
    }

    .status-chip-declined {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      color: #991b1b;
      border-color: #ef4444;
    }

    .status-chip-paid {
      background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
      color: #0369a1;
      border-color: #38bdf8;
    }

    .status-chip-not-paid {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      color: #92400e;
      border-color: #f59e0b;
    }

    .status-chip-unknown {
      background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
      color: #374151;
      border-color: #9ca3af;
    }

    .modal-content-scroll {
      max-height: 70vh;
      overflow-y: auto;
    }
  </style>

  <!-- Add the script at the beginning of the content section to ensure it's loaded before the buttons -->
  <script>
    function showTab(tabId) {
      // Hide all tab contents
      document.getElementById('primary-survey').classList.add('hidden');
      document.getElementById('unit-survey').classList.add('hidden');

      // Reset all tab buttons to inactive state
      const primaryTab = document.getElementById('primary-survey-tab');
      const unitTab = document.getElementById('unit-survey-tab');

      // Reset primary tab
      primaryTab.classList.remove('tab-primary', 'text-white');
      primaryTab.classList.add('bg-white', 'text-gray-700', 'border-2', 'border-gray-200');

      // Reset unit tab
      unitTab.classList.remove('tab-unit', 'text-white');
      unitTab.classList.add('bg-white', 'text-gray-700', 'border-2', 'border-gray-200');

      // Show selected tab content
      document.getElementById(tabId).classList.remove('hidden');

      // Highlight active tab button
      if (tabId === 'primary-survey') {
        primaryTab.classList.remove('bg-white', 'text-gray-700', 'border-2', 'border-gray-200');
        primaryTab.classList.add('tab-primary', 'text-white');
      } else if (tabId === 'unit-survey') {
        unitTab.classList.remove('bg-white', 'text-gray-700', 'border-2', 'border-gray-200');
        unitTab.classList.add('tab-unit', 'text-white');
      }
    }

    // Add dropdown toggle functionality
    function customToggleDropdown(button, event) {
      event.stopPropagation();

      // Close all other open dropdowns first
      const allMenus = document.querySelectorAll('.action-menu');
      allMenus.forEach(menu => {
        if (menu !== button.nextElementSibling && !menu.classList.contains('hidden')) {
          menu.classList.add('hidden');
        }
      });

      // Toggle the clicked dropdown
      const menu = button.nextElementSibling;
      menu.classList.toggle('hidden');

      // Position the dropdown near the button
      if (!menu.classList.contains('hidden')) {
        const rect = button.getBoundingClientRect();
        menu.style.top = rect.bottom + 'px';
        menu.style.left = (rect.left - menu.offsetWidth + rect.width) + 'px';
      }
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (event) {
      const allMenus = document.querySelectorAll('.action-menu');
      allMenus.forEach(menu => {
        menu.classList.add('hidden');
      });
    });

    const tableFilterConfig = {
      primaryApplicationTable: { prerequisitesIndex: 4, planningIndex: 5, directorIndex: 6 },
      unitApplicationTable: { prerequisitesIndex: 5, planningIndex: 6, directorIndex: 7 }
    };

    // Add table filtering functionality
    function filterTable(tableId, status) {
      const table = document.getElementById(tableId);
      if (!table) {
        return;
      }

      const config = tableFilterConfig[tableId] || {};
      const rows = table.getElementsByTagName('tr');

      for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        const prerequisiteCell = config.prerequisitesIndex !== undefined ? cells[config.prerequisitesIndex] : null;
        const planningCell = config.planningIndex !== undefined ? cells[config.planningIndex] : null;
        const directorCell = config.directorIndex !== undefined ? cells[config.directorIndex] : null;

        const prerequisiteText = prerequisiteCell ? prerequisiteCell.textContent.trim() : '';
        const planningText = planningCell ? planningCell.textContent.trim() : '';
        const directorText = directorCell ? directorCell.textContent.trim() : '';

        let shouldShow = false;

        switch (status) {
          case 'All...':
            shouldShow = true;
            break;
          case 'Pending':
            shouldShow = directorText.includes('Pending');
            break;
          case 'Approved':
            shouldShow = directorText.includes('Approved') || planningText.includes('Approved');
            break;
          case 'Awaiting Planning Rec':
            shouldShow = planningText.includes('Awaiting');
            break;
          case 'Planning Approved':
            shouldShow = planningText.includes('Approved');
            break;
          case 'Pending':
            shouldShow = directorText.includes('Pending');
            break;
          default:
            shouldShow = prerequisiteText.includes(status) || planningText.includes(status) || directorText.includes(status);
        }

        rows[i].style.display = shouldShow ? '' : 'none';
      }
    }

    // Initialize filtering when the page loads
    document.addEventListener('DOMContentLoaded', function () {
      // Tab persistence logic
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('unit_page') || urlParams.has('unit_status') || urlParams.has('unit_search')) {
        showTab('unit-survey');
      } else if (urlParams.has('primary_page') || urlParams.has('primary_status') || urlParams.has('primary_search')) {
        showTab('primary-survey');
      }

      // Set up event listeners for the filter dropdowns
      const primaryFilter = document.getElementById('primaryStatusFilter');
      const unitFilter = document.getElementById('unitStatusFilter');

      if (primaryFilter) {
        primaryFilter.addEventListener('change', function () {
          const url = new URL(window.location.href);
          url.searchParams.set('primary_status', this.value);
          url.searchParams.set('primary_page', 1);
          window.location.href = url.toString();
        });
      }

      if (unitFilter) {
        unitFilter.addEventListener('change', function () {
          const url = new URL(window.location.href);
          url.searchParams.set('unit_status', this.value);
          url.searchParams.set('unit_page', 1);
          window.location.href = url.toString();
        });
      }

      // Search inputs handling
      const primarySearchInput = document.getElementById('primarySearchInput');
      if (primarySearchInput) {
        primarySearchInput.addEventListener('keypress', function (e) {
          if (e.key === 'Enter') {
            const url = new URL(window.location.href);
            url.searchParams.set('primary_search', this.value);
            url.searchParams.set('primary_page', 1);
            window.location.href = url.toString();
          }
        });
      }

      const unitSearchInput = document.getElementById('unitSearchInput');
      if (unitSearchInput) {
        unitSearchInput.addEventListener('keypress', function (e) {
          if (e.key === 'Enter') {
            const url = new URL(window.location.href);
            url.searchParams.set('unit_search', this.value);
            url.searchParams.set('unit_page', 1);
            window.location.href = url.toString();
          }
        });
      }

      const confirmBtn = document.getElementById('actionSheetConfirmBtn');
      if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
          performActionSheetGeneration();
        });
      }

      setActionSheetConfirmState(false);
    });

    const actionSheetStatusClassMap = {
      'PASSED': 'status-chip-passed',
      'PENDING': 'status-chip-pending',
      'DECLINED': 'status-chip-declined',
      'PAID': 'status-chip-paid',
      'NOT PAID': 'status-chip-not-paid',
      'UNKNOWN': 'status-chip-unknown'
    };

    let actionSheetContext = { applicationId: null, type: null };

    function showLoadingOverlay(message = 'Loading...') {
      const overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      overlay.innerHTML = `
                        <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4">
                            <div class="flex items-center space-x-3">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                                <span class="text-gray-700">${message}</span>
                            </div>
                        </div>
                    `;
      document.body.appendChild(overlay);
      return overlay;
    }

    function hideLoadingOverlay(overlay) {
      if (overlay && overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
    }

    function statusToClassName(status) {
      const normalized = (status || '').toUpperCase();
      return actionSheetStatusClassMap[normalized] || actionSheetStatusClassMap['UNKNOWN'];
    }

    function setActionSheetConfirmState(isDisabled) {
      const confirmBtn = document.getElementById('actionSheetConfirmBtn');
      if (!confirmBtn) {
        return;
      }

      if (isDisabled) {
        confirmBtn.setAttribute('disabled', 'disabled');
        confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
      } else {
        confirmBtn.removeAttribute('disabled');
        confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
      }
    }

    function resetActionSheetModal() {
      const modal = document.getElementById('actionSheetModal');
      if (!modal) {
        return;
      }

      modal.querySelectorAll('[data-status-pill]').forEach(pill => {
        pill.textContent = '--';
        pill.className = 'status-chip ' + actionSheetStatusClassMap['UNKNOWN'];
      });

      modal.querySelectorAll('[data-status-details]').forEach(detail => {
        detail.textContent = '-';
      });

      document.getElementById('actionSheetModalNpFileNo').textContent = '-';
      document.getElementById('actionSheetModalFileNo').textContent = '-';
      document.getElementById('actionSheetModalOwner').textContent = '-';
      document.getElementById('actionSheetModalLandUse').textContent = 'Land Use: -';
      document.getElementById('actionSheetModalLocation').textContent = '-';
      document.getElementById('actionSheetModalMeta').textContent = 'Confirming will mark the action sheet as generated.';

      document.querySelectorAll('input[name="actionSheetDecision"]').forEach(radio => {
        radio.checked = false;
      });
    }

    function populateActionSheetModal(payload) {
      if (!payload || !payload.application) {
        return;
      }

      const { application, statuses } = payload;
      document.getElementById('actionSheetModalNpFileNo').textContent = application.np_fileno || 'N/A';
      document.getElementById('actionSheetModalFileNo').textContent = application.fileno ? `MLPP File No: ${application.fileno}` : 'MLPP File No: N/A';
      document.getElementById('actionSheetModalOwner').textContent = application.owner_name || 'N/A';
      document.getElementById('actionSheetModalLandUse').textContent = `Land Use: ${application.land_use || 'N/A'}`;
      document.getElementById('actionSheetModalLocation').textContent = application.location || 'N/A';

      if (application.application_status) {
        document.getElementById('actionSheetModalMeta').textContent = `Director status: ${application.application_status}. Confirm to finalise action sheet.`;
      }

      const decisionValue = (application.application_status || '').toLowerCase() === 'declined' ? 'Declined' : 'Approved';
      const decisionRadio = document.querySelector(`input[name="actionSheetDecision"][value="${decisionValue}"]`);
      if (decisionRadio) {
        decisionRadio.checked = true;
      }

      if (Array.isArray(statuses)) {
        statuses.forEach(item => {
          const row = document.querySelector(`#actionSheetModalTableBody tr[data-status-row="${item.code}"]`);
          if (!row) {
            return;
          }

          const pill = row.querySelector('[data-status-pill]');
          const detail = row.querySelector('[data-status-details]');

          if (pill) {
            pill.textContent = item.status || '--';
            pill.className = 'status-chip ' + statusToClassName(item.status);
          }

          if (detail) {
            detail.textContent = item.details || '-';
          }
        });
      }
    }

    function displayActionSheetModal() {
      const modal = document.getElementById('actionSheetModal');
      if (!modal) {
        return;
      }
      modal.classList.remove('hidden');
      modal.classList.add('flex');

      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    }

    function closeActionSheetModal() {
      const modal = document.getElementById('actionSheetModal');
      if (!modal) {
        return;
      }
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      actionSheetContext = { applicationId: null, type: null };
      setActionSheetConfirmState(false);
    }

    function openActionSheetPreview(applicationId, type) {
      actionSheetContext = { applicationId, type };

      const allMenus = document.querySelectorAll('.action-menu');
      allMenus.forEach(menu => menu.classList.add('hidden'));

      resetActionSheetModal();
      setActionSheetConfirmState(true);

      const overlay = showLoadingOverlay('Preparing action sheet summary...');
      const route = type === 'primary'
        ? '{{ route("actions.action-sheet-summary", ":id") }}'.replace(':id', applicationId)
        : '{{ route("sub-actions.action-sheet-summary", ":id") }}'.replace(':id', applicationId);

      fetch(route, {
        method: 'GET',
        headers: {
          'Accept': 'application/json'
        }
      })
        .then(response => response.json())
        .then(data => {
          hideLoadingOverlay(overlay);

          if (!data.success) {
            showNotification(data.message || 'Failed to load action sheet summary.', 'error');
            setActionSheetConfirmState(false);
            return;
          }

          populateActionSheetModal(data.data);
          setActionSheetConfirmState(false);
          displayActionSheetModal();
        })
        .catch(error => {
          console.error('Error:', error);
          hideLoadingOverlay(overlay);
          setActionSheetConfirmState(false);
          showNotification('An error occurred while loading the action sheet summary.', 'error');
        });
    }

    function performActionSheetGeneration() {
      if (!actionSheetContext.applicationId || !actionSheetContext.type) {
        showNotification('Missing application context for action sheet generation.', 'error');
        return;
      }

      const selectedDecisionInput = document.querySelector('input[name="actionSheetDecision"]:checked');
      if (!selectedDecisionInput) {
        showNotification('Select a director decision before generating the action sheet.', 'error');
        return;
      }

      setActionSheetConfirmState(true);
      const overlay = showLoadingOverlay('Generating action sheet...');

      const route = actionSheetContext.type === 'primary'
        ? '{{ route("actions.generate-action-sheet", ":id") }}'.replace(':id', actionSheetContext.applicationId)
        : '{{ route("sub-actions.generate-action-sheet", ":id") }}'.replace(':id', actionSheetContext.applicationId);

      fetch(route, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          decision: selectedDecisionInput.value
        })
      })
        .then(response => response.json())
        .then(data => {
          hideLoadingOverlay(overlay);

          if (data.success) {
            closeActionSheetModal();
            showNotification('Action sheet generated successfully!', 'success');
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          } else {
            setActionSheetConfirmState(false);
            showNotification(data.message || 'Failed to generate action sheet.', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          hideLoadingOverlay(overlay);
          setActionSheetConfirmState(false);
          showNotification('An error occurred while generating the action sheet.', 'error');
        });
    }

    // Notification function
    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';

      notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300 transform translate-x-full`;
      notification.innerHTML = `
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                ${type === 'success' ?
          '<i data-lucide="check-circle" class="w-5 h-5"></i>' :
          type === 'error' ?
            '<i data-lucide="x-circle" class="w-5 h-5"></i>' :
            '<i data-lucide="info" class="w-5 h-5"></i>'
        }
                            </div>
                            <span>${message}</span>
                        </div>
                    `;

      document.body.appendChild(notification);

      // Animate in
      setTimeout(() => {
        notification.classList.remove('translate-x-full');
      }, 100);

      // Initialize Lucide icons for the notification
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }

      // Remove after delay
      setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
          if (notification.parentNode) {
            document.body.removeChild(notification);
          }
        }, 300);
      }, 4000);
    }
  </script>

  <div class="flex-1 overflow-auto">
    <!-- Action Sheet Summary Modal -->
    <div id="actionSheetModal" class="fixed inset-0 z-50 hidden items-center justify-center">
      <div class="absolute inset-0 bg-black bg-opacity-40" onclick="closeActionSheetModal()"></div>
      <div class="relative w-full max-w-3xl mx-4 bg-white rounded-2xl shadow-2xl border border-gray-200">
        <div class="flex items-start justify-between px-6 py-4 border-b border-gray-200">
          <div>
            <h2 class="text-lg font-semibold text-gray-900">Review Requirements Before Generating</h2>
            <p class="text-sm text-gray-500">Confirm all prerequisites before creating the director's action sheet.</p>
          </div>
          <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeActionSheetModal()">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>
        <div class="px-6 py-4 space-y-6 modal-content-scroll">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4">
              <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide">ST File No.</p>
              <p id="actionSheetModalNpFileNo" class="text-sm font-semibold text-blue-900">-</p>
              <p class="text-xs text-blue-500" id="actionSheetModalFileNo">-</p>
            </div>
            <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4">
              <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide">Owner</p>
              <p id="actionSheetModalOwner" class="text-sm font-semibold text-indigo-900">-</p>
              <p class="text-xs text-indigo-500" id="actionSheetModalLandUse">Land Use: -</p>
            </div>
          </div>
          <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Director's Decision</p>
            <div class="mt-3 flex flex-col sm:flex-row sm:items-center gap-3">
              <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                <input type="radio" name="actionSheetDecision" value="Approved"
                  class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                <span>Approve</span>
              </label>
              <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                <input type="radio" name="actionSheetDecision" value="Declined"
                  class="w-4 h-4 text-red-600 border-gray-300 focus:ring-red-500">
                <span>Decline</span>
              </label>
            </div>
            <p class="mt-2 text-xs text-gray-500">Choose the director's decision before generating the action sheet.</p>
          </div>
          <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Location</p>
            <p id="actionSheetModalLocation" class="text-sm text-gray-800">-</p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-100">
                <tr>
                  <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                    Requirement</th>
                  <th scope="col" class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                    Status</th>
                </tr>
              </thead>
              <tbody id="actionSheetModalTableBody" class="divide-y divide-gray-100 bg-white">
                <tr data-status-row="application_requirements">
                  <td class="px-4 py-3 text-sm text-gray-700">a) Application Requirements</td>
                  <td class="px-4 py-3">
                    <div class="flex flex-col gap-1">
                      <span class="status-chip status-chip-unknown" data-status-pill>--</span>
                      <span class="text-xs text-gray-500" data-status-details>-</span>
                    </div>
                  </td>
                </tr>
                <tr data-status-row="site_plan">
                  <td class="px-4 py-3 text-sm text-gray-700">b) Site Plan</td>
                  <td class="px-4 py-3">
                    <div class="flex flex-col gap-1">
                      <span class="status-chip status-chip-unknown" data-status-pill>--</span>
                      <span class="text-xs text-gray-500" data-status-details>-</span>
                    </div>
                  </td>
                </tr>
                <tr data-status-row="oss_inspection_report">
                  <td class="px-4 py-3 text-sm text-gray-700">c) OSS Inspection Report</td>
                  <td class="px-4 py-3">
                    <div class="flex flex-col gap-1">
                      <span class="status-chip status-chip-unknown" data-status-pill>--</span>
                      <span class="text-xs text-gray-500" data-status-details>-</span>
                    </div>
                  </td>
                </tr>
                <tr data-status-row="planning_advice">
                  <td class="px-4 py-3 text-sm text-gray-700">d) Planning Advice</td>
                  <td class="px-4 py-3">
                    <div class="flex flex-col gap-1">
                      <span class="status-chip status-chip-unknown" data-status-pill>--</span>
                      <span class="text-xs text-gray-500" data-status-details>-</span>
                    </div>
                  </td>
                </tr>
                <tr data-status-row="application_processing_fees">
                  <td class="px-4 py-3 text-sm text-gray-700">e) Application and Processing Fees</td>
                  <td class="px-4 py-3">
                    <div class="flex flex-col gap-1">
                      <span class="status-chip status-chip-unknown" data-status-pill>--</span>
                      <span class="text-xs text-gray-500" data-status-details>-</span>
                    </div>
                  </td>
                </tr>
                <tr data-status-row="outstanding_land_use_charges">
                  <td class="px-4 py-3 text-sm text-gray-700">f) Outstanding Land Use Charges</td>
                  <td class="px-4 py-3">
                    <div class="flex flex-col gap-1">
                      <span class="status-chip status-chip-unknown" data-status-pill>--</span>
                      <span class="text-xs text-gray-500" data-status-details>-</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="flex items-center justify-between px-6 py-4 bg-gray-50 border-t border-gray-200">
          <div class="text-xs text-gray-500" id="actionSheetModalMeta">Confirming will mark the action sheet as generated.
          </div>
          <div class="flex items-center gap-3">
            <button type="button"
              class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-100"
              onclick="closeActionSheetModal()">Cancel</button>
            <button type="button" id="actionSheetConfirmBtn"
              class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1">Generate
              Action Sheet</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Header -->
    @include($headerPartial ?? 'admin.header')

    <!-- Main Content -->
    <div class="p-6">
      <!-- Payments Overview -->

      <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="stat-card">
          <div class="flex justify-between items-start mb-4">
            <h3 class="text-gray-600 font-medium">Total Director's Approvals</h3>
            <i data-lucide="file-text" class="text-gray-400 w-5 h-5"></i>
          </div>
          <div class="text-3xl font-bold">{{ $totalPrimaryApplications + $totalUnitApplications }}</div>
          <div class="flex items-center mt-2 text-sm">
            <i data-lucide="info" class="text-blue-500 w-4 h-4 mr-1"></i>
            <span class="text-blue-500">All Applications in system </span>
          </div>
          <span class="text-xs italic">(Primary + Unit Applications)</span>
        </div>

        <div class="stat-card">
          <div class="flex justify-between items-start mb-4">
            <h3 class="text-gray-600 font-medium">Primary Applications</h3>

            <i data-lucide="home" class="text-gray-400 w-5 h-5"></i>
          </div>
          <div class="text-3xl font-bold">{{ $totalPrimaryApplications }}</div>
          <div class="flex items-center mt-2 text-sm">
            <i data-lucide="check-circle" class="text-green-500 w-4 h-4 mr-1"></i>
            <span class="text-green-500">{{ $approvedPrimaryApplications }} Approved</span>
            <i data-lucide="x-circle" class="text-red-500 w-4 h-4 ml-3 mr-1"></i>
            <span class="text-red-500">{{ $rejectedPrimaryApplications }} Declined</span>
            <i data-lucide="clock" class="text-amber-500 w-4 h-4 ml-3 mr-1"></i>
            <span
              class="text-amber-500">{{ $pendingPrimaryApplications ?? ($totalPrimaryApplications - $approvedPrimaryApplications - $rejectedPrimaryApplications) }}
              Pending</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="flex justify-between items-start mb-4">
            <h3 class="text-gray-600 font-medium">Unit Applications</h3>
            <i data-lucide="layers" class="text-gray-400 w-5 h-5"></i>
          </div>
          <div class="text-3xl font-bold">{{ $totalUnitApplications }}</div>
          <div class="flex items-center mt-2 text-sm">
            <i data-lucide="check-circle" class="text-green-500 w-4 h-4 mr-1"></i>
            <span class="text-green-500">{{ $approvedUnitApplications }} Approved</span>
            <i data-lucide="x-circle" class="text-red-500 w-4 h-4 ml-3 mr-1"></i>
            <span class="text-red-500">{{ $rejectedUnitApplications }} Declined</span>
            <i data-lucide="clock" class="text-amber-500 w-4 h-4 ml-3 mr-1"></i>
            <span
              class="text-amber-500">{{ $pendingUnitApplications ?? ($totalUnitApplications - $approvedUnitApplications - $rejectedUnitApplications) }}
              Pending</span>
          </div>
        </div>
      </div>


      <div class="flex flex-wrap gap-4 mb-4">
        <button onclick="showTab('primary-survey')" id="primary-survey-tab"
          class="tab-primary flex items-center px-6 py-3 text-sm font-semibold rounded-xl shadow-lg transition-all duration-300 ease-in-out focus:outline-none focus:ring-4 focus:ring-blue-300 text-white">
          <i data-lucide="home" class="w-5 h-5 mr-2"></i>
          <span>Primary Applications</span>
          <span
            class="ml-2 bg-white bg-opacity-20 text-white text-xs px-2 py-1 rounded-full">{{ $totalPrimaryApplications }}</span>
        </button>
        <button onclick="showTab('unit-survey')" id="unit-survey-tab"
          class="tab-unit flex items-center px-6 py-3 text-sm font-semibold rounded-xl shadow-lg transition-all duration-300 ease-in-out focus:outline-none focus:ring-4 focus:ring-purple-300 bg-white text-gray-700 hover:bg-gray-50 border-2 border-gray-200">
          <i data-lucide="layers" class="w-5 h-5 mr-2"></i>
          <span>Unit Applications</span>
          <span
            class="ml-2 bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full">{{ $totalUnitApplications }}</span>
        </button>
      </div>
      <!-- Primary Application  -->
      <div id="primary-survey">
        @include('programmes.partials.director_report')
        <!-- Enhanced Tab Navigation -->

        <!-- Section Header for Primary Applications -->
        <div class="section-header section-header-primary">
          <i data-lucide="home" class="w-6 h-6"></i>
          <div>
            <h3 class="text-lg font-bold">Primary Applications - Director's Approval</h3>
            <p class="text-sm opacity-80">Original property applications requiring director approval</p>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg border-2 border-blue-200 p-6">
          <div class="filter-container">
            <div class="flex justify-between items-center">
              <div class="flex items-center space-x-3">
                <i data-lucide="filter" class="w-5 h-5 text-blue-600"></i>
                <span class="font-medium text-gray-700">Filter & Actions</span>
              </div>
              <div class="flex items-center space-x-3">
                <div class="relative">
                  <i data-lucide="search"
                    class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                  <input type="text" id="primarySearchInput" placeholder="Search primary..."
                    value="{{ request()->query('primary_search') }}"
                    class="pl-10 pr-4 py-2 border border-blue-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div class="relative">
                  <select id="primaryStatusFilter"
                    class="pl-4 pr-8 py-2 border border-blue-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none bg-white text-sm">
                    <option value="All..." {{ request()->query('primary_status') == 'All...' ? 'selected' : '' }}>All Status
                    </option>
                    <option value="Approved" {{ request()->query('primary_status') == 'Approved' ? 'selected' : '' }}>
                      Approved</option>
                    <option value="Pending" {{ request()->query('primary_status') == 'Pending' ? 'selected' : '' }}>Pending
                    </option>
                    <option value="Awaiting Planning Rec" {{ request()->query('primary_status') == 'Awaiting Planning Rec' ? 'selected' : '' }}>Awaiting Planning Rec</option>
                    <option value="Declined" {{ request()->query('primary_status') == 'Declined' ? 'selected' : '' }}>
                      Declined</option>
                    <option value="Planning Approved" {{ request()->query('primary_status') == 'Planning Approved' ? 'selected' : '' }}>Planning Approved</option>
                  </select>
                  <i data-lucide="chevron-down"
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-blue-400 w-4 h-4"></i>
                </div>

                <button
                  class="flex items-center space-x-2 px-4 py-2 border border-blue-300 rounded-lg hover:bg-blue-50 transition-colors">
                  <i data-lucide="upload" class="w-4 h-4 text-blue-600"></i>
                  <span class="text-blue-600">Import</span>
                </button>

                <button
                  class="flex items-center space-x-2 px-4 py-2 border border-blue-300 rounded-lg hover:bg-blue-50 transition-colors">
                  <i data-lucide="download" class="w-4 h-4 text-blue-600"></i>
                  <span class="text-blue-600">Export</span>
                </button>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table id="primaryApplicationTable" class="primary-table min-w-full">
              <thead>
                <tr class="text-sm">
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="file-text" class="w-4 h-4"></i>
                      <span>ST File No</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="file-text" class="w-4 h-4"></i>
                      <span>MLPP File No</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="user" class="w-4 h-4"></i>
                      <span>Owner</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="building" class="w-4 h-4"></i>
                      <span>Land Use</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                      <span>Extant CofO Status</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="map" class="w-4 h-4"></i>
                      <span>Planning Rec</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="user-check" class="w-4 h-4"></i>
                      <span>Director's Approval</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                      <span> Application Date </span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="clock" class="w-4 h-4"></i>
                      <span>Date Captured</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="calendar" class="w-4 h-4"></i>
                      <span>Approval Date</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="message-square" class="w-4 h-4"></i>
                      <span>Comment</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="settings" class="w-4 h-4"></i>
                      <span>Actions</span>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                @forelse($applications as $application)
                  <tr class="text-xs">
                    <td class="table-cell">
                      @if($application->np_fileno)
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 whitespace-nowrap">
                          {{ $application->np_fileno }}
                        </span>
                      @else
                        <span class="text-gray-500">N/A</span>
                      @endif
                    </td>
                    <td class="table-cell">
                      @if($application->fileno)
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 whitespace-nowrap">
                          {{ $application->fileno }}
                        </span>
                      @else
                        <span class="text-gray-500">N/A</span>
                      @endif
                    </td>
                    <td class="table-cell">{{ $application->owner_name ?? 'N/A' }}</td>
                    <td class="table-cell">
                      @if($application->land_use)
                        @php
                          $landUseBadgeClass = match (strtolower($application->land_use)) {
                            'residential' => 'bg-blue-100 text-blue-800 border-blue-200',
                            'commercial' => 'bg-green-100 text-green-800 border-green-200',
                            'industrial' => 'bg-red-100 text-red-800 border-red-200',
                            'mixed use' => 'bg-purple-100 text-purple-800 border-purple-200',
                            'mixed-use' => 'bg-purple-100 text-purple-800 border-purple-200',
                            default => 'bg-gray-100 text-gray-800 border-gray-200'
                          };
                        @endphp
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $landUseBadgeClass }} whitespace-nowrap">
                          {{ strtoupper($application->land_use) }}
                        </span>
                      @else
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-100 text-gray-800 border-gray-200">
                          N/A
                        </span>
                      @endif
                    </td>
                    <td class="table-cell">
                      @php
                        $cofoRecord = DB::connection('sqlsrv')
                          ->table('Cofo')
                          ->where('mlsFNo', $application->fileno)
                          ->orWhere('kangisFileNo', $application->fileno)
                          ->orWhere('NewKANGISFileno', $application->fileno)
                          ->select('cofo_no', 'application_id')
                          ->first();
                      @endphp
                      <div class="flex flex-col space-y-2">
                        @if(!$cofoRecord)
                          <span class="badge badge-declined">
                            <i data-lucide="x-circle" class="w-4 h-4 mr-1 text-red-600"></i>
                            CofO Not Captured
                          </span>
                        @elseif($cofoRecord->cofo_no == 'NULL' || empty($cofoRecord->cofo_no))
                          <span class="badge badge-pending">
                            <i data-lucide="alert-triangle" class="w-4 h-4 mr-1 text-yellow-600"></i>
                            No CofO
                          </span>
                        @else
                          <span class="badge badge-approved">
                            <i data-lucide="check-circle" class="w-4 h-4 mr-1 text-green-600"></i>
                            CofO Captured
                          </span>
                        @endif
                      </div>
                    </td>
                    <td class="table-cell">
                      @if($application->planning_recommendation_status == 'Approved')
                        <span class="badge badge-approved">
                          <i data-lucide="check-circle" class="w-4 h-4 mr-1 text-green-600"></i>
                          Planning Rec. Approved
                        </span>
                      @else
                        <span class="badge badge-awaiting">
                          <i data-lucide="clock" class="w-4 h-4 mr-1 text-indigo-600"></i>
                          Awaiting Planning Rec.
                        </span>
                      @endif
                    </td>
                    <td class="table-cell">
                      @if($application->application_status == 'Approved')
                        <span class="badge badge-approved">Approved</span>
                      @elseif($application->application_status == 'Declined')
                        <span class="badge badge-declined">Declined</span>
                      @elseif($application->planning_recommendation_status == 'Approved')
                        <span class="badge badge-pending2">Pending</span>
                      @else
                        <span class="badge" style="background: #f3f4f6; color: #6b7280; border-color: #d1d5db;">
                          <span class="badge badge-pending2">Pending</span>
                        </span>
                      @endif
                    </td>
                    <td class="table-cell">
                      @if($application->sys_date)
                        {{ \Carbon\Carbon::parse($application->sys_date)->format('d/m/Y') }}
                      @else
                        N/A
                      @endif
                    </td>
                    <td class="table-cell">
                      @if($application->sys_date)
                        {{ \Carbon\Carbon::parse($application->sys_date)->format('d/m/Y h:i A') }}
                      @else
                        N/A
                      @endif
                    </td>
                    <td class="table-cell">
                      @if($application->approval_date)
                        {{ \Carbon\Carbon::parse($application->approval_date)->format('d/m/Y') }}
                      @else
                        N/A
                      @endif
                    </td>
                    <td class="table-cell">{{ $application->comments ?? 'N/A' }}</td>


                    <td class="table-cell relative">
                      <!-- Dropdown Toggle Button -->
                      <button type="button" class="p-2 hover:bg-gray-100 focus:outline-none rounded-full"
                        onclick="customToggleDropdown(this, event)">
                        <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                      </button>

                      <!-- Dropdown Menu Primary Application Surveys -->
                      <ul class="fixed action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-56">
                        <li>
                          <a href="{{ route('sectionaltitling.viewrecorddetail')}}?id={{$application->id}}&url=view"
                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                            <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
                            <span>View Application</span>
                          </a>
                        </li>
                        <li>
                          @if($application->planning_recommendation_status == 'Approved' && $application->application_status != 'Approved')
                            <a href="{{ route('actions.director-approval', ['id' => $application->id]) }}"
                              class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                              <i data-lucide="check-circle" class="w-4 h-4 text-blue-500"></i>
                              <span>Approve/Decline</span>
                            </a>
                          @else
                            <div class="w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50">
                              <i data-lucide="check-circle" class="w-4 h-4 text-gray-400"></i>
                              <span>Approve/Decline</span>
                            </div>
                          @endif
                        </li>
                        <li>
                          <a href="{{ $application->application_status == 'Approved' ? route('actions.director-approval', ['id' => $application->id]) : '#' }}"
                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 {{ $application->application_status != 'Approved' ? 'cursor-not-allowed opacity-50' : '' }}">
                            <i data-lucide="check-circle" class="w-4 h-4 text-green-600"></i>
                            <span>View approval</span>
                          </a>
                        </li>
                        <li>
                          @if($application->application_status == 'Approved' && (empty($application->action_sheet_generated) || $application->action_sheet_generated != 'Yes'))
                            <button onclick="openActionSheetPreview({{ $application->id }}, 'primary')"
                              class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                              <i data-lucide="file-plus" class="w-4 h-4 text-purple-600"></i>
                              <span>Generate Action Sheet</span>
                            </button>
                          @else
                            <div class="w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50">
                              <i data-lucide="file-plus" class="w-4 h-4 text-gray-400"></i>
                              <span>Generate Action Sheet</span>
                            </div>
                          @endif
                        </li>
                        <li>
                          @if(!empty($application->action_sheet_generated) && $application->action_sheet_generated == 'Yes')
                            <a href="{{ route('actions.view-action-sheet', ['id' => $application->id]) }}" target="_blank"
                              class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                              <i data-lucide="file-text" class="w-4 h-4 text-orange-600"></i>
                              <span>View Action Sheet</span>
                            </a>
                          @else
                            <div class="w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50">
                              <i data-lucide="file-text" class="w-4 h-4 text-gray-400"></i>
                              <span>View Action Sheet</span>
                            </div>
                          @endif
                        </li>

                      </ul>
                    </td>
                  </tr>
                @empty
                  <tr class="text-xs">
                    <td colspan="12" class="table-cell text-center py-4 text-gray-500">No primary survey records found</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          <div class="mt-6 px-4 py-3 bg-gray-50 border-t border-gray-200">
            {{ $applications->appends([
    'unit_page' => request()->query('unit_page'),
    'primary_status' => request()->query('primary_status'),
    'primary_search' => request()->query('primary_search'),
    'unit_status' => request()->query('unit_status'),
    'unit_search' => request()->query('unit_search')
  ])->links() }}
          </div>
        </div>
      </div>


      <!-- Unit Application  -->
      <div id="unit-survey" class="hidden">

        @include('programmes.partials.unit_director_report')

        <!-- Section Header for Unit Applications -->
        <div class="section-header section-header-unit">
          <i data-lucide="layers" class="w-6 h-6"></i>
          <div>
            <h3 class="text-lg font-bold">Unit Applications - Director's Approval</h3>
            <p class="text-sm opacity-80">Sectional titling unit applications requiring director approval</p>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg border-2 border-purple-200 p-6">
          <div class="filter-container">
            <div class="flex justify-between items-center">
              <div class="flex items-center space-x-3">
                <i data-lucide="filter" class="w-5 h-5 text-purple-600"></i>
                <span class="font-medium text-gray-700">Filter & Actions</span>
              </div>
              <div class="flex items-center space-x-3">
                <div class="relative">
                  <i data-lucide="search"
                    class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                  <input type="text" id="unitSearchInput" placeholder="Search units..."
                    value="{{ request()->query('unit_search') }}"
                    class="pl-10 pr-4 py-2 border border-purple-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm">
                </div>
                <div class="relative">
                  <select id="unitStatusFilter"
                    class="pl-4 pr-8 py-2 border border-purple-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 appearance-none bg-white text-sm">
                    <option value="All..." {{ request()->query('unit_status') == 'All...' ? 'selected' : '' }}>All Status
                    </option>
                    <option value="Approved" {{ request()->query('unit_status') == 'Approved' ? 'selected' : '' }}>Approved
                    </option>
                    <option value="Pending" {{ request()->query('unit_status') == 'Pending' ? 'selected' : '' }}>Pending
                    </option>
                    <option value="Awaiting Planning Rec" {{ request()->query('unit_status') == 'Awaiting Planning Rec' ? 'selected' : '' }}>Awaiting Planning Rec</option>
                    <option value="Declined" {{ request()->query('unit_status') == 'Declined' ? 'selected' : '' }}>Declined
                    </option>
                    <option value="Planning Approved" {{ request()->query('unit_status') == 'Planning Approved' ? 'selected' : '' }}>Planning Approved</option>
                  </select>
                  <i data-lucide="chevron-down"
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-purple-400 w-4 h-4"></i>
                </div>

                <button
                  class="flex items-center space-x-2 px-4 py-2 border border-purple-300 rounded-lg hover:bg-purple-50 transition-colors">
                  <i data-lucide="upload" class="w-4 h-4 text-purple-600"></i>
                  <span class="text-purple-600">Import</span>
                </button>

                <button
                  class="flex items-center space-x-2 px-4 py-2 border border-purple-300 rounded-lg hover:bg-purple-50 transition-colors">
                  <i data-lucide="download" class="w-4 h-4 text-purple-600"></i>
                  <span class="text-purple-600">Export</span>
                </button>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table id="unitApplicationTable" class="unit-table min-w-full">
              <thead>
                <tr class="text-sm">
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="file-text" class="w-4 h-4"></i>
                      <span>NP FileNo</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="file-text" class="w-4 h-4"></i>
                      <span>File No</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="user" class="w-4 h-4"></i>
                      <span>Owner</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="building" class="w-4 h-4"></i>
                      <span>Land Use</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="home" class="w-4 h-4"></i>
                      <span>Unit Type</span>
                    </div>
                  </th>

                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="map" class="w-4 h-4"></i>
                      <span>Planning Rec</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="user-check" class="w-4 h-4"></i>
                      <span>Director's Approval</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                      <span>Application Date</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="clock" class="w-4 h-4"></i>
                      <span>Date Captured</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="calendar" class="w-4 h-4"></i>
                      <span>Approval Date</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="message-square" class="w-4 h-4"></i>
                      <span>Comment</span>
                    </div>
                  </th>
                  <th class="table-header">
                    <div class="flex items-center space-x-2">
                      <i data-lucide="settings" class="w-4 h-4"></i>
                      <span>Actions</span>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                @forelse($unitApplications as $unitApplication)
                  <tr class="text-xs">
                    <td class="table-cell">
                      @php
                        $displayNpFileNo = $unitApplication->np_fileno ?? $unitApplication->mother_np_fileno ?? $unitApplication->mls_fileno;
                      @endphp
                      @if($displayNpFileNo)
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 whitespace-nowrap">
                          {{ $displayNpFileNo }}
                        </span>
                      @else
                        <span class="text-gray-500">N/A</span>
                      @endif
                    </td>
                    <td class="table-cell">
                      @if($unitApplication->fileno)
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 whitespace-nowrap">
                          {{ $unitApplication->fileno }}
                        </span>
                      @else
                        <span class="text-gray-500">N/A</span>
                      @endif
                    </td>
                    <td class="table-cell">{{ $unitApplication->owner_name ?? 'N/A' }}</td>
                    <td class="table-cell">
                      @if($unitApplication->land_use)
                        @php
                          $unitLandUseBadgeClass = match (strtolower($unitApplication->land_use)) {
                            'residential' => 'bg-blue-100 text-blue-800 border-blue-200',
                            'commercial' => 'bg-green-100 text-green-800 border-green-200',
                            'industrial' => 'bg-red-100 text-red-800 border-red-200',
                            'mixed use' => 'bg-purple-100 text-purple-800 border-purple-200',
                            'mixed-use' => 'bg-purple-100 text-purple-800 border-purple-200',
                            default => 'bg-gray-100 text-gray-800 border-gray-200'
                          };
                        @endphp
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $unitLandUseBadgeClass }} whitespace-nowrap">
                          {{ strtoupper($unitApplication->land_use) }}
                        </span>
                      @else
                        <span
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-100 text-gray-800 border-gray-200">
                          N/A
                        </span>
                      @endif
                    </td>
                    <td class="table-cell">
                      @php
                        $isSUA = ($unitApplication->is_sua_unit == 1) || (strtoupper($unitApplication->unit_type ?? '') === 'SUA');
                        $displayUnitType = $isSUA ? 'SUA' : 'PUA';
                        $unitTypeTitle = $isSUA ? 'Standalone Unit Application' : 'Parented Unit Application';
                        $unitTypeBadgeClass = $isSUA
                          ? 'bg-purple-100 text-purple-800 border-purple-200'
                          : 'bg-blue-100 text-blue-800 border-blue-200';
                      @endphp
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $unitTypeBadgeClass }}"
                        title="{{ $unitTypeTitle }}">
                        {{ $displayUnitType }}
                      </span>
                    </td>

                    <td class="table-cell">
                      @php
                        $unitPlanningStatus = $unitApplication->planning_recommendation_status;
                        $unitAppStatus = $unitApplication->application_status;

                        if (!$isSUA && strtolower($unitApplication->mother_planning_status ?? '') === 'approved') {
                          $unitPlanningStatus = 'Approved';
                        }
                      @endphp
                      @if($unitPlanningStatus == 'Approved')
                        <span class="badge badge-approved">Approved</span>
                      @else
                        <span class="badge badge-awaiting">Pending</span>
                      @endif
                    </td>
                    <td class="table-cell">
                      @if($unitAppStatus == 'Approved')
                        <span class="badge badge-approved">Approved</span>
                      @elseif($unitAppStatus == 'Declined')
                        <span class="badge badge-declined">Declined</span>
                      @elseif($unitPlanningStatus == 'Approved')
                        <span class="badge badge-pending2">Pending</span>
                      @else
                        <span class="badge" style="background: #f3f4f6; color: #6b7280; border-color: #d1d5db;">
                          <span class="badge badge-pending2">Pending</span>
                        </span>
                      @endif
                    </td>
                    <td class="table-cell">
                      @if($unitApplication->sys_date)
                        {{ \Carbon\Carbon::parse($unitApplication->sys_date)->format('d/m/Y') }}
                      @else
                        N/A
                      @endif
                    </td>
                    <td class="table-cell">
                      @if($unitApplication->sys_date)
                        {{ \Carbon\Carbon::parse($unitApplication->sys_date)->format('d/m/Y h:i A') }}
                      @else
                        N/A
                      @endif
                    </td>
                    <td class="table-cell">
                      @if($unitApplication->approval_date)
                        {{ \Carbon\Carbon::parse($unitApplication->approval_date)->format('d/m/Y') }}
                      @else
                        N/A
                      @endif
                    </td>
                    <td class="table-cell">{{ $unitApplication->comments ?? 'N/A' }}</td>
                    <td class="table-cell relative">
                      <!-- Dropdown Toggle Button -->
                      <button type="button" class="p-2 hover:bg-gray-100 focus:outline-none rounded-full"
                        onclick="customToggleDropdown(this, event)">
                        <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                      </button>

                      <!-- Dropdown Menu Unit Application Surveys -->
                      <ul class="fixed action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-56">
                        <li>
                          <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $unitApplication->id) }}"
                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                            <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
                            <span>View Unit Application</span>
                          </a>
                        </li>
                        <li>
                          @if($unitPlanningStatus == 'Approved' && $unitAppStatus != 'Approved')
                            <a href="{{ route('sub-actions.director-approval', ['id' => $unitApplication->id]) }}"
                              class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                              <i data-lucide="check-circle" class="w-4 h-4 text-blue-500"></i>
                              <span>Approve/Decline</span>
                            </a>
                          @else
                            <div class="w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50">
                              <i data-lucide="check-circle" class="w-4 h-4 text-gray-400"></i>
                              <span>Approve/Decline</span>
                            </div>
                          @endif
                        </li>
                        <li>
                          <a href="{{ $unitAppStatus == 'Approved' ? route('sub-actions.director-approval', ['id' => $unitApplication->id]) : '#' }}"
                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 {{ $unitAppStatus != 'Approved' ? 'cursor-not-allowed opacity-50' : '' }}">
                            <i data-lucide="check-circle" class="w-4 h-4 text-green-600"></i>
                            <span>View approval</span>
                          </a>
                        </li>
                        <li>
                          @if($unitAppStatus == 'Approved' && (empty($unitApplication->action_sheet_generated) || $unitApplication->action_sheet_generated != 'Yes'))
                            <button onclick="openActionSheetPreview({{ $unitApplication->id }}, 'unit')"
                              class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                              <i data-lucide="file-plus" class="w-4 h-4 text-purple-600"></i>
                              <span>Generate Action Sheet</span>
                            </button>
                          @else
                            <div class="w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50">
                              <i data-lucide="file-plus" class="w-4 h-4 text-gray-400"></i>
                              <span>Generate Action Sheet</span>
                            </div>
                          @endif
                        </li>
                        <li>
                          @if(!empty($unitApplication->action_sheet_generated) && $unitApplication->action_sheet_generated == 'Yes')
                            <a href="{{ route('sub-actions.view-action-sheet', ['id' => $unitApplication->id]) }}"
                              target="_blank"
                              class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                              <i data-lucide="file-text" class="w-4 h-4 text-orange-600"></i>
                              <span>View Action Sheet</span>
                            </a>
                          @else
                            <div class="w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50">
                              <i data-lucide="file-text" class="w-4 h-4 text-gray-400"></i>
                              <span>View Action Sheet</span>
                            </div>
                          @endif
                        </li>

                      </ul>
                    </td>
                  </tr>
                @empty
                  <tr class="text-xs">
                    <td colspan="13" class="table-cell text-center py-4 text-gray-500">No unit applications found</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          <div class="mt-6 px-4 py-3 bg-gray-50 border-t border-gray-200 rounded-b-xl">
            {{ $unitApplications->appends([
    'primary_page' => request()->query('primary_page'),
    'primary_status' => request()->query('primary_status'),
    'primary_search' => request()->query('primary_search'),
    'unit_status' => request()->query('unit_status'),
    'unit_search' => request()->query('unit_search')
  ])->links() }}
          </div>
        </div>
      </div>
    </div>

    <!-- Page Footer -->
    @include($footerPartial ?? 'admin.footer')
  </div>
@endsection

@section('scripts')
  <script>
    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function () {
      // Initialize Lucide icons if available
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    });
  </script>
@endsection