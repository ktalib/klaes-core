<script>
  // Initialize Lucide icons safely
  if (window.lucide && typeof lucide.createIcons === 'function') {
    lucide.createIcons();
  }

  function scrollToActiveItem() {
    const activeItem = document.querySelector('.sidebar-item.active');
    const sidebarContent = document.querySelector('.sidebar-content');
    if (activeItem && sidebarContent) {
      const offsetTop = activeItem.offsetTop;
      const sidebarHeight = sidebarContent.clientHeight;
      const itemHeight = activeItem.offsetHeight;
      const idealScrollTop = offsetTop - (sidebarHeight / 2) + (itemHeight / 2);
      sidebarContent.scrollTo({ top: Math.max(0, idealScrollTop), behavior: 'smooth' });
    }
  }

  function toggleModule(moduleName) {
    const content = document.querySelector(`[data-content="${moduleName}"]`);
    const chevron = document.querySelector(`[data-chevron="${moduleName}"]`);
    if (!content) return;
    const willOpen = content.classList.contains('hidden');
    content.classList.toggle('hidden');
    if (chevron) chevron.classList.toggle('rotate-90', willOpen);
  }

  function toggleSection(sectionName) {
    const content = document.querySelector(`[data-content="${sectionName}"]`);
    const chevron = document.querySelector(`[data-chevron="${sectionName}"]`);
    if (!content) return;
    const willOpen = content.classList.contains('hidden');
    content.classList.toggle('hidden');
    if (chevron) chevron.classList.toggle('rotate-90', willOpen);
  }

  function expandActiveMenus() {
    const activeItems = document.querySelectorAll('.sidebar-item.active');
    activeItems.forEach(item => {
      let ancestor = item.parentElement;
      while (ancestor && ancestor !== document) {
        if (ancestor.hasAttribute('data-content')) {
          ancestor.classList.remove('hidden');
          const key = ancestor.getAttribute('data-content');
          const relatedChevron = document.querySelector(`[data-chevron="${key}"]`);
          if (relatedChevron) {
            relatedChevron.classList.add('rotate-90');
          }
        }
        ancestor = ancestor.parentElement;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    // Module toggle handlers
    const moduleHeaders = document.querySelectorAll('[data-module]');
    moduleHeaders.forEach(header => {
      header.addEventListener('click', function() {
        const moduleName = this.getAttribute('data-module');
        toggleModule(moduleName);
      });
    });

    // Section toggle handlers
    const sectionHeaders = document.querySelectorAll('[data-section]');
    sectionHeaders.forEach(header => {
      header.addEventListener('click', function(e) {
        e.stopPropagation();
        const sectionName = this.getAttribute('data-section');
        toggleSection(sectionName);
      });
    });

    // Sidebar item active class + gentle scroll (on click only)
    const sidebarItems = document.querySelectorAll('.sidebar-item');
    sidebarItems.forEach(item => {
      item.addEventListener('click', function() {
        sidebarItems.forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        setTimeout(scrollToActiveItem, 50);
      });
    });

    // User menu toggle
    const userMenuButton = document.getElementById('userMenuButton');
    const userMenu = document.getElementById('userMenu');
    if (userMenuButton && userMenu) {
      userMenuButton.addEventListener('click', function() {
        userMenu.classList.toggle('hidden');
      });
      document.addEventListener('click', function(e) {
        if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
          userMenu.classList.add('hidden');
        }
      });
    }

    expandActiveMenus();
    setTimeout(scrollToActiveItem, 100);
  });
  
  // Enhanced scroll indicators and feedback
  const sidebarContent = document.querySelector('.sidebar-content');
  
  if (sidebarContent) {
    function updateScrollIndicators() {
      const { scrollTop, scrollHeight, clientHeight } = sidebarContent;
      const isScrolledToTop = scrollTop <= 5;
      const isScrolledToBottom = scrollTop + clientHeight >= scrollHeight - 5;
      
      // Update CSS custom properties for gradient indicators
      sidebarContent.style.setProperty('--scroll-top-opacity', isScrolledToTop ? '0' : '1');
      sidebarContent.style.setProperty('--scroll-bottom-opacity', isScrolledToBottom ? '0' : '1');
      
      // Update scroll position data attribute for styling
      if (isScrolledToTop) {
        sidebarContent.setAttribute('data-scroll', 'top');
      } else if (isScrolledToBottom) {
        sidebarContent.setAttribute('data-scroll', 'bottom');
      } else {
        sidebarContent.setAttribute('data-scroll', 'middle');
      }
    }
    
    // Add scroll event listener with throttling for performance
    let scrollTimeout;
    sidebarContent.addEventListener('scroll', function() {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(updateScrollIndicators, 10);
    });
    
    // Initial call
    updateScrollIndicators();
    
    // Add smooth scroll behavior for menu items
    const menuItems = document.querySelectorAll('.sidebar-item, .sidebar-module-header');
    menuItems.forEach(item => {
      item.addEventListener('click', function(e) {
        // Add a subtle animation feedback
        this.style.transform = 'scale(0.98)';
  

        setTimeout(() => {
          this.style.transform = '';
        }, 100);
      });
    });
    
    // Add keyboard navigation support
    sidebarContent.addEventListener('keydown', function(e) {
      if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        e.preventDefault();
        const scrollAmount = 50;
        if (e.key === 'ArrowUp') {
          this.scrollTop -= scrollAmount;
        } else {
          this.scrollTop += scrollAmount;
        }
      }
    });
    
    // Make sidebar content focusable for keyboard navigation
    sidebarContent.setAttribute('tabindex', '0');
  }

  // --- Responsive & Collapsible Sidebar Logic ---
  document.addEventListener('DOMContentLoaded', function() {
    const htmlEl = document.documentElement;
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const desktopToggle = document.getElementById('sidebar-collapse-toggle');
    const mobileToggle = document.getElementById('sidebar-mobile-toggle');
    const mobileClose = document.getElementById('sidebar-mobile-close');

    // Update titles as tooltips when sidebar is collapsed
    function updateSidebarTooltips() {
      const isCollapsed = htmlEl.classList.contains('sidebar-collapsed');
      const items = document.querySelectorAll('.sidebar-item, .sidebar-module-header, .sidebar-submodule-header');
      
      items.forEach(item => {
        if (isCollapsed) {
          const textEl = item.querySelector('span');
          if (textEl) {
            item.setAttribute('title', textEl.textContent.trim());
          }
        } else {
          item.removeAttribute('title');
        }
      });
    }

    // Toggle desktop collapse state
    if (desktopToggle) {
      desktopToggle.addEventListener('click', function() {
        htmlEl.classList.toggle('sidebar-collapsed');
        const isCollapsed = htmlEl.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebar_state', isCollapsed ? 'collapsed' : 'expanded');
        updateSidebarTooltips();
        
        // Dispatch resize event so that responsive tables/charts adapt to layout changes
        setTimeout(() => {
          window.dispatchEvent(new Event('resize'));
        }, 305);
      });
    }

    // Open mobile sidebar drawer
    if (mobileToggle && sidebar && backdrop) {
      mobileToggle.addEventListener('click', function() {
        sidebar.classList.add('mobile-open');
        backdrop.classList.add('active');
      });
    }

    // Close mobile sidebar drawer
    function closeMobileSidebar() {
      if (sidebar) sidebar.classList.remove('mobile-open');
      if (backdrop) backdrop.classList.add('active');
    }

    if (mobileClose) {
      mobileClose.addEventListener('click', closeMobileSidebar);
    }
    if (backdrop) {
      backdrop.addEventListener('click', closeMobileSidebar);
    }

    // Initial tooltip calculation on page load
    updateSidebarTooltips();
  });
</script>
