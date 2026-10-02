<!-- Tab Functionality JavaScript -->
<script>
// Configure PDF.js worker
if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    lucide.createIcons();
    
    // Tab switching functionality
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    // Make switchTab function global so it can be called from other places
    window.switchTab = function(tabName) {
        console.log('🔄 Switching to tab:', tabName);
        
        tabs.forEach(tab => {
            if (tab.getAttribute('data-tab') === tabName) {
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
            } else {
                tab.classList.remove('active');
                tab.setAttribute('aria-selected', 'false');
            }
        });

        tabContents.forEach(content => {
            if (content.getAttribute('data-tab-content') === tabName) {
                content.classList.remove('hidden');
                content.classList.add('active');
                content.setAttribute('aria-hidden', 'false');
            } else {
                content.classList.add('hidden');
                content.classList.remove('active');
                content.setAttribute('aria-hidden', 'true');
            }
        });

        // If switching to typing tab and we have a selected file, initialize typing interface
        if (tabName === 'typing' && typeof window.initializeTypingInterface === 'function') {
            setTimeout(() => {
                console.log('🚀 Initializing typing interface...');
                window.initializeTypingInterface();
            }, 100);
        }
    }
    
    // Add event listeners to tabs
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabName = tab.getAttribute('data-tab');
            switchTab(tabName);
        });
    });
    
    // Search functionality
    const searchInputs = document.querySelectorAll('input[type="search"]');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const listId = this.id.replace('search-', '') + '-list';
            const list = document.getElementById(listId);
            
            if (list) {
                const items = list.querySelectorAll('.border.rounded-lg, tr');
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
        });
    });

    // Check if we have a selected file and should open typing tab directly
    @if(isset($selectedFileIndexing))
        console.log('📄 Selected file detected, switching to typing tab');
        switchTab('typing');
    @endif
    
    console.log('✅ Page Typing Dashboard initialized');
});

// Toggle page details for completed files
function togglePageDetails(fileIndexingId) {
    console.log('👁️ Toggle page details for file:', fileIndexingId);
    alert('Page details view will be implemented soon');
}
</script>