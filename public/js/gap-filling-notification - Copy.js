/**
 * File Number Gap-Filling Notification System
 * 
 * Displays user-friendly notifications when a file number is assigned
 * that fills a gap from an expired or released reservation.
 * 
 * Usage:
 *   import { showGapFillingNotification } from './gap-filling-notification.js';
 *   
 *   // When draft save response includes gap_filling_info:
 *   if (response.gap_filling_info) {
 *       showGapFillingNotification(response.gap_filling_info);
 *   }
 */

/**
 * Show a notification when a gap-filled file number is assigned
 * 
 * @param {Object} gapInfo - Gap filling information from backend
 * @param {boolean} gapInfo.is_gap_filled - Whether this is a gap-filled number
 * @param {string} gapInfo.file_number - The assigned file number (e.g., ST-RES-2025-1)
 * @param {string} gapInfo.reason - Reason for gap (expired/released)
 * @param {string} gapInfo.next_new_number - What the next NEW number would be
 */
export function showGapFillingNotification(gapInfo) {
    if (!gapInfo || !gapInfo.is_gap_filled) {
        return;
    }

    const notification = createGapFillingNotification(gapInfo);
    
    // Insert at top of form or in designated notification area
    const targetElement = document.querySelector('#file-number-field')?.parentElement 
        || document.querySelector('.form-container')
        || document.querySelector('form')
        || document.body;
    
    targetElement.insertBefore(notification, targetElement.firstChild);
    
    // Auto-dismiss after 15 seconds (longer than normal because it's informational)
    setTimeout(() => {
        dismissNotification(notification);
    }, 15000);
    
    // Log for debugging
    console.info('[GapFilling] File number assigned:', gapInfo.file_number, 
        'Next new:', gapInfo.next_new_number);
}

/**
 * Create the notification DOM element
 */
function createGapFillingNotification(gapInfo) {
    const container = document.createElement('div');
    container.className = 'gap-filling-notification bg-blue-50 border-l-4 border-blue-500 p-4 mb-4 rounded shadow-sm';
    container.setAttribute('role', 'alert');
    container.setAttribute('aria-live', 'polite');
    
    container.innerHTML = `
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-blue-800 mb-1">
                    📋 File Number Assigned: <span class="font-bold">${escapeHtml(gapInfo.file_number)}</span>
                </h3>
                <div class="text-sm text-blue-700 space-y-2">
                    <p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-200 text-blue-800">
                            Gap-Filled
                        </span>
                        ${escapeHtml(gapInfo.reason)}
                    </p>
                    <p class="text-xs text-blue-600">
                        ℹ️ You are filling a gap to maintain sequential numbering. 
                        The next <strong>new</strong> file number will be <strong>${escapeHtml(gapInfo.next_new_number)}</strong>.
                    </p>
                    <p class="text-xs text-blue-600 font-medium">
                        ✓ This ensures no gaps in the final file number sequence!
                    </p>
                </div>
            </div>
            <div class="ml-3 flex-shrink-0">
                <button type="button" 
                        class="gap-notification-dismiss inline-flex text-blue-400 hover:text-blue-600 focus:outline-none"
                        aria-label="Dismiss notification">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" 
                              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" 
                              clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
    `;
    
    // Add dismiss button handler
    const dismissButton = container.querySelector('.gap-notification-dismiss');
    dismissButton.addEventListener('click', () => {
        dismissNotification(container);
    });
    
    return container;
}

/**
 * Dismiss notification with animation
 */
function dismissNotification(notification) {
    notification.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
    notification.style.opacity = '0';
    notification.style.transform = 'translateY(-10px)';
    
    setTimeout(() => {
        notification.remove();
    }, 300);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Show inline indicator next to file number field
 * 
 * @param {string} fileNumber - The file number to mark as gap-filled
 */
export function showGapFillingIndicator(fileNumber) {
    const fileNumberInput = document.querySelector('#file-number-field') 
        || document.querySelector('input[name="np_fileno"]');
    
    if (!fileNumberInput) {
        return;
    }
    
    // Remove existing indicator if any
    const existingIndicator = fileNumberInput.parentElement.querySelector('.gap-filled-indicator');
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    const indicator = document.createElement('span');
    indicator.className = 'gap-filled-indicator inline-flex items-center ml-2 px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800';
    indicator.innerHTML = `
        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        Gap-Filled
    `;
    indicator.title = 'This file number fills a gap from an expired/released reservation';
    
    fileNumberInput.parentElement.appendChild(indicator);
}

/**
 * jQuery plugin for backward compatibility
 */
if (typeof jQuery !== 'undefined') {
    jQuery.fn.showGapFillingNotification = function(gapInfo) {
        showGapFillingNotification(gapInfo);
        return this;
    };
}

// Export for module systems
export default {
    showGapFillingNotification,
    showGapFillingIndicator
};
