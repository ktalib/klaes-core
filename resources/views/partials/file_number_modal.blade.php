<!-- File Number Selection Modal -->
<div id="fileNumberModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-auto">
        <!-- Header -->
        <div class="flex justify-between items-center p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">File Number Generator</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeFileNumberModal()">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Content Area -->
        <div class="p-6">
            <p class="text-gray-600 mb-4">File number generation form will be implemented here.</p>
        </div>
        
        <!-- Footer -->
        <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50">
            <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50" onclick="closeFileNumberModal()">
                Cancel
            </button>
            <button type="button" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700" onclick="applyFileNumber()">
                Apply
            </button>
        </div>
    </div>
</div>

<script>
// Global functions for modal control
window.openFileNumberModal = function(targetSelector = null) {
    console.log('Opening file number modal for target:', targetSelector);
    
    // Store target input selector
    window.fileNumberModalTarget = targetSelector;
    
    // Show modal
    const modal = document.getElementById('fileNumberModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
};

window.closeFileNumberModal = function() {
    const modal = document.getElementById('fileNumberModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
};

window.applyFileNumber = function() {
    // This will be implemented with your new instructions
    console.log('Apply file number clicked');
    
    // For now, just close the modal
    closeFileNumberModal();
};

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('fileNumberModal');
    if (e.target === modal) {
        closeFileNumberModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('fileNumberModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeFileNumberModal();
        }
    }
});
</script>
