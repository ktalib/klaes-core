// Add Reset button to modal footer
const modalFooter = document.querySelector('#global-fileno-modal .flex.space-x-3');

if (modalFooter) {
    const cancelButton = modalFooter.querySelector('button');
    const applyButton = document.getElementById('apply-fileno-btn');

    // Create reset button
    const resetButton = document.createElement('button');
    resetButton.type = 'button';
    resetButton.className = 'px-4 py-2 border border-red-200 text-red-600 rounded hover:bg-red-50';
    resetButton.onclick = function() {
        GlobalFileNoModal.resetForm();
    };
    
    // Add button content
    resetButton.innerHTML = `
        <div class="flex items-center space-x-1">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            <span>Reset</span>
        </div>
    `;
    
    // Insert between cancel and apply
    if (cancelButton && applyButton) {
        modalFooter.insertBefore(resetButton, applyButton);
        
        // Init the icon
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }
}

console.log('Reset button added to modal');
