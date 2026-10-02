<!-- Enhanced Scrollbar Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableContainer = document.getElementById('tableContainer');
    const tableWrapper = document.getElementById('tableWrapper');
    const scrollProgress = document.getElementById('scrollProgress');
    const scrollHint = document.getElementById('scrollHint');
    
    if (!tableContainer || !tableWrapper || !scrollProgress || !scrollHint) {
        console.warn('Scrollbar elements not found');
        return;
    }
    
    // Function to update scroll progress
    function updateScrollProgress() {
        const scrollLeft = tableContainer.scrollLeft;
        const scrollWidth = tableContainer.scrollWidth;
        const clientWidth = tableContainer.clientWidth;
        const maxScroll = scrollWidth - clientWidth;
        
        if (maxScroll > 0) {
            const progress = (scrollLeft / maxScroll) * 100;
            scrollProgress.style.width = progress + '%';
            
            // Show/hide scroll hint based on scroll position
            if (scrollLeft === 0 && maxScroll > 50) {
                scrollHint.style.opacity = '0.7';
            } else {
                scrollHint.style.opacity = '0';
            }
        } else {
            scrollProgress.style.width = '100%';
            scrollHint.style.opacity = '0';
        }
    }
    
    // Function to check if table is scrollable
    function checkScrollable() {
        const isScrollable = tableContainer.scrollWidth > tableContainer.clientWidth;
        
        if (isScrollable) {
            tableWrapper.classList.add('table-scrollable', 'has-overflow');
            tableWrapper.classList.remove('table-not-scrollable');
        } else {
            tableWrapper.classList.add('table-not-scrollable');
            tableWrapper.classList.remove('table-scrollable', 'has-overflow');
            scrollProgress.style.width = '100%';
        }
        
        updateScrollProgress();
    }
    
    // Add scroll event listener
    tableContainer.addEventListener('scroll', updateScrollProgress);
    
    // Add resize observer to check scrollability when window resizes
    if (window.ResizeObserver) {
        const resizeObserver = new ResizeObserver(checkScrollable);
        resizeObserver.observe(tableContainer);
    }
    
    // Initial check
    setTimeout(checkScrollable, 100);
    
    // Smooth scrolling with arrow keys when table is focused
    tableContainer.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            tableContainer.scrollBy({ left: -100, behavior: 'smooth' });
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            tableContainer.scrollBy({ left: 100, behavior: 'smooth' });
        }
    });
    
    // Add mouse wheel horizontal scrolling (Shift + scroll)
    tableContainer.addEventListener('wheel', function(e) {
        if (e.shiftKey) {
            e.preventDefault();
            tableContainer.scrollBy({ left: e.deltaY, behavior: 'smooth' });
        }
    });
    
    // Make table container focusable for keyboard navigation
    tableContainer.setAttribute('tabindex', '0');
    
    console.log('Enhanced scrollbar functionality initialized');
});
</script>