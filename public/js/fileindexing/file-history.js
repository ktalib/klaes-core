/**
 * File History Handler for File Indexing Module
 * Handles loading and displaying file transaction history with COFO date logic
 */

// Global variables
let currentFileNumber = null;
let fileHistoryData = [];

/**
 * Load file history for a given file number
 * @param {string} fileNumber - The file number to load history for
 */
async function loadFileHistory(fileNumber) {
    if (!fileNumber) {
        showFileHistoryEmpty();
        return;
    }

    currentFileNumber = fileNumber;
    showFileHistoryLoading();

    try {
        const response = await fetch(`/api/file-history?mlsFNo=${encodeURIComponent(fileNumber)}&per_page=50`);
        const data = await response.json();

        if (data.success && data.data) {
            fileHistoryData = data.data;
            renderFileHistory(data.data);
            enableTimelineButton();
        } else {
            showFileHistoryError('No history found for this file number.');
        }
    } catch (error) {
        console.error('Error loading file history:', error);
        showFileHistoryError('Failed to load file history. Please try again.');
    }
}

/**
 * Render file history transactions
 * @param {Array} transactions - Array of transaction objects
 */
function renderFileHistory(transactions) {
    const container = document.getElementById('file-history-list');
    const messagesContainer = document.getElementById('file-history-messages');
    
    if (!container) return;

    // Hide messages and show list
    hideFileHistoryMessages();
    
    if (!transactions || transactions.length === 0) {
        showFileHistoryEmpty();
        return;
    }

    // Sort by display_date (newest first)
    const sortedTransactions = [...transactions].sort((a, b) => {
        const dateA = new Date(getTransactionDisplayMeta(a).date || 0);
        const dateB = new Date(getTransactionDisplayMeta(b).date || 0);
        return dateB - dateA;
    });

    container.innerHTML = sortedTransactions.map((transaction, index) => {
        const displayMeta = getTransactionDisplayMeta(transaction);

        return createTransactionCard(transaction, index, displayMeta);
    }).join('');

    // Add click handlers for transaction cards
    addTransactionClickHandlers();
}

/**
 * Check if transaction type contains COFO keywords
 * @param {string} transactionType - The transaction type to check
 * @returns {boolean}
 */
function isCofoType(transactionType) {
    if (!transactionType) return false;
    
    const type = transactionType.toLowerCase();
    const cofoKeywords = ['cofo', 'certificate of occupancy', 'c of o', 'cof o'];
    
    return cofoKeywords.some(keyword => type.includes(keyword));
}

/**
 * Decide which date and label to present for a transaction.
 * Keeps CofO detection consistent across cards and timeline output.
 * @param {Object} transaction
 * @returns {{date: (string|null), label: string, isCofo: boolean}}
 */
function getTransactionDisplayMeta(transaction) {
    const cofoDate = transaction?.cofo_date || transaction?.cofoDate || null;
    const isCofoTransaction = isCofoType(transaction?.transaction_type);
    const baseDisplayDate = transaction?.display_date || transaction?.transaction_date || null;

    if (isCofoTransaction) {
        if (cofoDate) {
            return { date: cofoDate, label: 'CofO Date', isCofo: true };
        }

        if (transaction?.display_date && transaction.display_date !== transaction.transaction_date) {
            return { date: transaction.display_date, label: 'CofO Date', isCofo: true };
        }
    }

    return {
        date: baseDisplayDate || cofoDate,
        label: 'Transaction Date',
        isCofo: isCofoTransaction,
    };
}

/**
 * Create HTML for a transaction card
 * @param {Object} transaction - Transaction object
 * @param {number} index - Transaction index
 * @param {{date: (string|null), label: string, isCofo: boolean}} displayMeta
 * @returns {string} HTML string
 */
function createTransactionCard(transaction, index, displayMeta) {
    const formattedDate = displayMeta.date ? formatDisplayDate(displayMeta.date) : 'N/A';
    const parties = formatTransactionParties(transaction.parties || {});
    const registrationNo = formatRegistrationNumber(transaction.serialNo, transaction.pageNo, transaction.volumeNo);
    
    return `
        <div class="transaction-card bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" data-transaction-index="${index}">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <h4 class="font-semibold text-gray-900">${escapeHtml(transaction.transaction_type || 'Unknown Transaction')}</h4>
                        ${displayMeta.isCofo ? '<span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-medium">COFO</span>' : ''}
                    </div>
                    <p class="text-sm text-gray-600">${escapeHtml(transaction.location || 'Location not specified')}</p>
                </div>
                <div class="text-right text-sm">
                    <div class="font-medium text-gray-900">${formattedDate}</div>
                    <div class="text-gray-500">${displayMeta.label}</div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                <div>
                    <span class="text-gray-500">Registration No:</span>
                    <span class="font-medium">${registrationNo}</span>
                </div>
                <div>
                    <span class="text-gray-500">Land Use:</span>
                    <span class="font-medium">${escapeHtml(transaction.land_use || 'N/A')}</span>
                </div>
            </div>
            
            ${parties ? `
                <div class="border-t border-gray-100 pt-3">
                    <div class="text-sm text-gray-600">
                        <div class="font-medium mb-1">Transaction Parties:</div>
                        ${parties}
                    </div>
                </div>
            ` : ''}
        </div>
    `;
}

/**
 * Format transaction parties for display
 * @param {Object} parties - Parties object
 * @returns {string} Formatted parties HTML
 */
function formatTransactionParties(parties) {
    if (!parties || Object.keys(parties).length === 0) return null;
    
    return Object.entries(parties)
        .filter(([key, value]) => value && value.trim())
        .map(([role, name]) => `<div><strong>${role}:</strong> ${escapeHtml(name)}</div>`)
        .join('');
}

/**
 * Format registration number
 * @param {string|number} serialNo
 * @param {string|number} pageNo  
 * @param {string|number} volumeNo
 * @returns {string}
 */
function formatRegistrationNumber(serialNo, pageNo, volumeNo) {
    const serial = serialNo || '0';
    const page = pageNo || '0';
    const volume = volumeNo || '0';
    return `${serial}/${page}/${volume}`;
}

/**
 * Format date for display
 * @param {string} dateString
 * @returns {string}
 */
function formatDisplayDate(dateString) {
    if (!dateString) return 'N/A';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (error) {
        return dateString;
    }
}

/**
 * Add click handlers to transaction cards
 */
function addTransactionClickHandlers() {
    document.querySelectorAll('.transaction-card').forEach(card => {
        card.addEventListener('click', function() {
            const index = parseInt(this.dataset.transactionIndex);
            showTransactionDetails(fileHistoryData[index]);
        });
    });
}

/**
 * Show transaction details (placeholder for modal or expanded view)
 * @param {Object} transaction
 */
function showTransactionDetails(transaction) {
    console.log('Transaction details:', transaction);
    // TODO: Implement modal or expanded view for transaction details
}

/**
 * Show loading state
 */
function showFileHistoryLoading() {
    hideFileHistoryMessages();
    document.getElementById('file-history-loading')?.classList.remove('hidden');
    document.getElementById('file-history-list').innerHTML = '';
    disableTimelineButton();
}

/**
 * Show error message
 * @param {string} message
 */
function showFileHistoryError(message) {
    hideFileHistoryMessages();
    const errorDiv = document.getElementById('file-history-error');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
    }
    disableTimelineButton();
}

/**
 * Show empty state
 */
function showFileHistoryEmpty() {
    hideFileHistoryMessages();
    document.getElementById('file-history-empty')?.classList.remove('hidden');
    document.getElementById('file-history-list').innerHTML = '';
    disableTimelineButton();
}

/**
 * Hide all message containers
 */
function hideFileHistoryMessages() {
    document.getElementById('file-history-loading')?.classList.add('hidden');
    document.getElementById('file-history-error')?.classList.add('hidden');
    document.getElementById('file-history-empty')?.classList.add('hidden');
}

/**
 * Enable timeline button
 */
function enableTimelineButton() {
    const button = document.getElementById('file-history-timeline-btn');
    if (button) {
        button.disabled = false;
        button.classList.remove('opacity-60', 'cursor-not-allowed');
    }
}

/**
 * Disable timeline button
 */
function disableTimelineButton() {
    const button = document.getElementById('file-history-timeline-btn');
    if (button) {
        button.disabled = true;
        button.classList.add('opacity-60', 'cursor-not-allowed');
    }
}

/**
 * Show timeline modal
 */
function showTimelineModal() {
    if (!fileHistoryData || fileHistoryData.length === 0) {
        return;
    }

    const modal = document.getElementById('file-history-timeline-modal');
    const content = document.getElementById('file-history-timeline-content');
    
    if (!modal || !content) return;

    // Create timeline content
    content.innerHTML = createTimelineContent(fileHistoryData);
    
    // Show modal
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
}

/**
 * Create timeline content
 * @param {Array} transactions
 * @returns {string}
 */
function createTimelineContent(transactions) {
    const sortedTransactions = [...transactions].sort((a, b) => {
        const dateA = new Date(getTransactionDisplayMeta(a).date || 0);
        const dateB = new Date(getTransactionDisplayMeta(b).date || 0);
        return dateA - dateB; // Oldest first for timeline
    });

    return `
        <div class="space-y-6">
            <div class="relative">
                <div class="absolute left-4 top-4 bottom-4 w-0.5 bg-blue-200"></div>
                <div class="space-y-6">
                    ${sortedTransactions.map((transaction, index) => {
                        const displayMeta = getTransactionDisplayMeta(transaction);
                        const formattedDate = formatDisplayDate(displayMeta.date);
                        
                        return `
                            <div class="relative flex items-start">
                                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center relative z-10">
                                    <span class="text-white text-sm font-medium">${index + 1}</span>
                                </div>
                                <div class="ml-4 flex-1 bg-white border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-start justify-between mb-2">
                                        <h4 class="font-semibold text-gray-900">${escapeHtml(transaction.transaction_type || 'Unknown')}</h4>
                                        <div class="text-right">
                                            <div class="font-medium text-gray-900">${formattedDate}</div>
                                            ${displayMeta.isCofo ? `<span class="text-xs text-blue-600 font-medium">${displayMeta.label}</span>` : ''}
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600">${escapeHtml(transaction.location || 'No location')}</p>
                                    <div class="mt-2 text-xs text-gray-500">
                                        Reg: ${formatRegistrationNumber(transaction.serialNo, transaction.pageNo, transaction.volumeNo)}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        </div>
    `;
}

/**
 * Hide timeline modal
 */
function hideTimelineModal() {
    const modal = document.getElementById('file-history-timeline-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    }
}

/**
 * Escape HTML to prevent XSS
 * @param {string} text
 * @returns {string}
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Initialize file history functionality
 */
function initFileHistory() {
    // Timeline button click handler
    document.getElementById('file-history-timeline-btn')?.addEventListener('click', showTimelineModal);
    
    // Load history button click handler
    document.getElementById('load-file-history-btn')?.addEventListener('click', function() {
        const input = document.getElementById('file-history-input');
        const fileNumber = input?.value?.trim();
        
        if (!fileNumber) {
            showFileHistoryError('Please enter a file number.');
            return;
        }
        
        loadFileHistory(fileNumber);
    });
    
    // Enter key handler for input field
    document.getElementById('file-history-input')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('load-file-history-btn')?.click();
        }
    });
    
    // Modal close handlers
    document.getElementById('file-history-timeline-close')?.addEventListener('click', hideTimelineModal);
    document.getElementById('file-history-timeline-dismiss')?.addEventListener('click', hideTimelineModal);
    
    // Click outside modal to close
    document.getElementById('file-history-timeline-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            hideTimelineModal();
        }
    });
}

// Export functions for external use
window.fileHistory = {
    loadFileHistory,
    initFileHistory
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFileHistory);
} else {
    initFileHistory();
}