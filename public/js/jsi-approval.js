/**
 * Joint Site Inspection Approval JavaScript
 * Handles JSI approval functionality for both primary and sub-applications
 */

// Global variables
let jsiApprovalInProgress = false;

/**
 * Prompt the user for explicit confirmation before approving a JSI report
 */
async function confirmJSIApproval(applicationType, appId, overrideMessage = '') {
    const message = overrideMessage || `Are you sure you want to approve the Joint Site Inspection Report for ${applicationType} ${appId}?`;

    if (window.Swal && typeof window.Swal.fire === 'function') {
        const result = await window.Swal.fire({
            title: 'Approve Joint Site Inspection?',
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, approve',
            cancelButtonText: 'No, cancel',
            reverseButtons: true,
            focusCancel: true,
        });

        return !!result.isConfirmed;
    }

    return window.confirm(`${message}\n\nSelect "Yes" to approve or "No" to cancel.`);
}

/**
 * Initialize JSI approval functionality
 */
function initializeJSIApproval() {
    console.log('DEBUG: Initializing JSI Approval system');
    
    // Add event listeners for approval buttons
    $(document).on('click', '[data-jsi-approve]', function(e) {
        e.preventDefault();
        const button = $(this);
        const applicationId = button.data('application-id');
        const subApplicationId = button.data('sub-application-id');
        
        handleJSIApproval(applicationId, subApplicationId, button).catch((error) => {
            console.error('DEBUG: JSI approval handler error:', error);
        });
    });
}

/**
 * Handle JSI approval process
 */
async function handleJSIApproval(applicationId, subApplicationId, button) {
    if (jsiApprovalInProgress) {
        console.log('DEBUG: JSI approval already in progress');
        return;
    }

    // Validate input
    if (!applicationId && !subApplicationId) {
        notifyJSI('Error: Application ID is required for approval', 'error');
        return;
    }

    // Show confirmation dialog
    const applicationType = subApplicationId ? 'sub-application' : 'application';
    const appId = subApplicationId || applicationId;
    const overrideMessage = button.data('confirm-message') || '';
    const userConfirmed = await confirmJSIApproval(applicationType, appId, overrideMessage);

    if (!userConfirmed) {
        console.log('DEBUG: User cancelled JSI approval');
        return;
    }

    // Set loading state
    jsiApprovalInProgress = true;
    const originalText = button.html();
    button.prop('disabled', true)
          .html('<i class="fas fa-spinner fa-spin mr-1"></i> Approving...');

    // Prepare request data
    const requestData = {
        _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
    };

    // Add appropriate ID
    if (subApplicationId) {
        requestData.sub_application_id = subApplicationId;
    } else {
        requestData.application_id = applicationId;
    }

    // Determine the correct route
    const approvalRoute = subApplicationId 
        ? '/sub-actions/planning-recommendation/joint-site-inspection/approve'
        : '/planning-recommendation/joint-site-inspection/approve';

    // Make AJAX request
    $.ajax({
        url: approvalRoute,
        type: 'POST',
        data: requestData,
        dataType: 'json',
        success: function(response) {
            console.log('DEBUG: JSI approval success:', response);
            
            if (response.success) {
                notifyJSI(response.message, 'success');
                
                // Update UI to reflect approval
                updateJSIApprovalUI(response.data);
                
                // Hide approve button and show approved status
                button.closest('.jsi-approval-container').html(`
                    <div class="flex items-center gap-2 text-green-600 bg-green-50 px-3 py-2 rounded-lg border border-green-200">
                        <i class="fas fa-check-circle"></i>
                        <span class="font-medium">Approved</span>
                        <span class="text-sm text-gray-600">by ${response.data.approved_by}</span>
                    </div>
                `);
                
                // Enable print and view buttons
                enableJSIPrintButtons();
                enablePlanningRecommendationTab();

                if (button.data('reload-on-success')) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 250);
                }
                
            } else {
                notifyJSI(response.message || 'Failed to approve JSI report', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('DEBUG: JSI approval error:', {xhr, status, error});
            
            let errorMessage = 'An error occurred while approving the JSI report';
            
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (xhr.status === 404) {
                errorMessage = 'JSI report not found';
            } else if (xhr.status === 422) {
                errorMessage = 'Invalid request data';
            } else if (xhr.status === 500) {
                errorMessage = 'Server error occurred';
            }
            
            notifyJSI(errorMessage, 'error');
        },
        complete: function() {
            // Reset loading state
            jsiApprovalInProgress = false;
            button.prop('disabled', false).html(originalText);
        }
    });
}

/**
 * Update UI after successful approval
 */
function updateJSIApprovalUI(approvalData) {
    console.log('DEBUG: Updating JSI approval UI with data:', approvalData);
    
    // Update status indicators
    $('.jsi-status').each(function() {
        const statusElement = $(this);
        statusElement.removeClass('bg-yellow-100 text-yellow-800 bg-red-100 text-red-800')
                    .addClass('bg-green-100 text-green-800')
                    .html('<i class="fas fa-check-circle mr-1"></i>Approved');
    });
    
    // Update approval timestamp if exists
    if (approvalData.approved_at) {
        $('.jsi-approval-time').text(new Date(approvalData.approved_at).toLocaleString());
    }
    
    // Update approved by information
    if (approvalData.approved_by) {
        $('.jsi-approved-by').text(approvalData.approved_by);
    }
}

/**
 * Enable print and view buttons after approval
 */
function enableJSIPrintButtons() {
    console.log('DEBUG: Enabling JSI print buttons');
    
    // Enable disabled print buttons
    $('.jsi-print-btn').each(function() {
        const button = $(this);
        if (button.hasClass('disabled') || button.prop('disabled')) {
            button.removeClass('disabled bg-gray-400 text-gray-200 cursor-not-allowed')
                  .addClass('bg-green-600 text-white hover:bg-green-700')
                  .prop('disabled', false);
        }
    });
    
    // Enable disabled view buttons  
    $('.jsi-view-btn').each(function() {
        const button = $(this);
        if (button.hasClass('disabled') || button.prop('disabled')) {
            button.removeClass('disabled bg-gray-400 text-gray-200 cursor-not-allowed')
                  .addClass('bg-blue-600 text-white hover:bg-blue-700')
                  .prop('disabled', false);
        }
    });
}

/**
 * Enable Planning Recommendation tab once JSI is approved
 */
function enablePlanningRecommendationTab() {
    console.log('DEBUG: Enabling Planning Recommendation tab');

    const planningTabButtons = document.querySelectorAll('[data-tab="initial"]');
    if (!planningTabButtons.length) {
        return;
    }

    planningTabButtons.forEach((button) => {
        button.classList.remove('tab-button--disabled');
        button.removeAttribute('disabled');
        button.removeAttribute('aria-disabled');

        if (button.dataset.disabledMessage) {
            delete button.dataset.disabledMessage;
        }

        button.removeAttribute('data-disabled-message');
        button.removeAttribute('title');
    });

    notifyJSI('Planning Recommendation tab is now unlocked. You can continue with the recommendation.', 'info');
}

/**
 * Show notification message
 */
function notifyJSI(message, type = 'info') {
    // Try to use existing notification system
    if (typeof window.showNotification === 'function' && window.showNotification !== notifyJSI) {
        window.showNotification(message, type);
        return;
    }

    // Use SweetAlert when available
    if (window.Swal && typeof window.Swal.fire === 'function') {
        const titles = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Information'
        };

        window.Swal.fire({
            title: titles[type] || 'Notice',
            text: message,
            icon: ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info',
            confirmButtonText: 'OK',
        });
        return;
    }

    // Final fallback to native alert
    const prefix = type === 'success' ? 'Success:' : type === 'error' ? 'Error:' : type === 'warning' ? 'Warning:' : 'Info:';
    window.alert(`${prefix} ${message}`);
}

/**
 * Check JSI approval status on page load
 */
function checkJSIApprovalStatus() {
    console.log('DEBUG: Checking JSI approval status');
    
    // Check if JSI is already approved and update UI accordingly
    const approvedElements = $('.jsi-approved-indicator');
    if (approvedElements.length > 0) {
        console.log('DEBUG: JSI already approved, enabling print buttons');
        enableJSIPrintButtons();
    }
}

// Initialize when document is ready
$(document).ready(function() {
    console.log('DEBUG: JSI Approval JavaScript loaded');
    initializeJSIApproval();
    checkJSIApprovalStatus();
});

// Export functions for global access
window.handleJSIApproval = handleJSIApproval;
window.enableJSIPrintButtons = enableJSIPrintButtons;
window.checkJSIApprovalStatus = checkJSIApprovalStatus;
window.enablePlanningRecommendationTab = enablePlanningRecommendationTab;