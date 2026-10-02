<style>
/* Enhanced active item styling */
.sidebar-item.active {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  color: #1e40af;
  font-weight: 500;
  box-shadow: 0 1px 3px rgba(59, 130, 246, 0.1);
  border-left: 2px solid #3b82f6;
  border-radius: 6px;
  margin: 2px 8px;
}

/* Alternative option - even more subtle */
.sidebar-item.active.subtle {
  background: rgba(59, 130, 246, 0.08);
  color: #1e40af;
  font-weight: 500;
  box-shadow: none;
  border-left: 3px solid #3b82f6;
  border-radius: 4px;
}

/* Hover state for better interaction */
.sidebar-item:hover {
  background-color: #f8fafc;
  transform: translateX(2px);
}

.sidebar-item.active:hover {
  background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
  transform: translateX(0);
}

/* Animation for expanding sections */
[data-content] {
  transition: all 0.3s ease;
}

/* Highlight animation keyframes */
@keyframes activeHighlight {
  0% { 
    transform: scale(1);
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
  }
  50% { 
    transform: scale(1.02);
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
  }
  100% { 
    transform: scale(1);
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
  }
}

.sidebar-item.active.highlight {
  animation: activeHighlight 0.6s ease-in-out;
}
</style>

<style>
/* Enhanced scrollbar visibility */
.scrollbar-visible {
  scrollbar-width: thin;
  scrollbar-color: #94a3b8 #f1f5f9;
}

.scrollbar-visible::-webkit-scrollbar {
  width: 10px;
}

.scrollbar-visible::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 6px;
  margin: 4px 0;
  border: 1px solid #e2e8f0;
}

.scrollbar-visible::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, #94a3b8 0%, #64748b 100%);
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
}

.scrollbar-visible::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(180deg, #64748b 0%, #475569 100%);
  box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.2);
}

.scrollbar-visible::-webkit-scrollbar-thumb:active {
  background: linear-gradient(180deg, #475569 0%, #334155 100%);
}

/* Add subtle gradient indicators for scrollable content */
.sidebar-content {
  position: relative;
}

.sidebar-content::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 8px;
  height: 12px;
  background: linear-gradient(to bottom, rgba(255,255,255,0.95), transparent);
  pointer-events: none;
  z-index: 10;
  opacity: var(--scroll-top-opacity, 0);
  transition: opacity 0.3s ease;
}

.sidebar-content::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 8px;
  height: 12px;
  background: linear-gradient(to top, rgba(255,255,255,0.95), transparent);
  pointer-events: none;
  z-index: 10;
  opacity: var(--scroll-bottom-opacity, 1);
  transition: opacity 0.3s ease;
}

/* Add scroll indicator shadow */
.sidebar-content {
  box-shadow: inset 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

/* Enhance hover effects for better UX */
.sidebar-item:hover {
  background-color: #f8fafc;
  transform: translateX(2px);
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

.sidebar-module-header:hover {
  background-color: #f8fafc;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

/* Add visual feedback for scrollable areas */
@media (hover: hover) {
  .sidebar-content:hover::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, #64748b 0%, #475569 100%);
  }
}

/* Mobile scrollbar enhancement */
@media (max-width: 768px) {
  .scrollbar-visible::-webkit-scrollbar {
    width: 8px;
  }
}

/* Add a subtle border to indicate scrollable content */
.sidebar-content {
  border-left: 2px solid transparent;
  transition: border-color 0.3s ease;
}

.sidebar-content:hover {
  border-left-color: #e2e8f0;
}

/* Scroll position indicators */
.sidebar-content[data-scroll="top"] {
  border-top: 2px solid #3b82f6;
}

.sidebar-content[data-scroll="bottom"] {
  border-bottom: 2px solid #3b82f6;
}

.sidebar-content[data-scroll="middle"] {
  border-left-color: #3b82f6;
}

/* --- Responsive & Collapsible Sidebar Overrides --- */

/* Transition for layout elements */
.sidebar, .sidebar-header, #sidebar-logo, .sidebar-item, .sidebar-module-header, .sidebar-submodule-header, #sidebar-backdrop {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Collapsed Desktop State (HTML class sidebar-collapsed) */
@media (min-width: 1024px) {
  html.sidebar-collapsed .sidebar {
    width: 72px;
  }

  html.sidebar-collapsed .sidebar-header {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
    justify-content: center;
  }

  html.sidebar-collapsed #sidebar-logo {
    height: 28px;
    width: auto;
  }

  /* Hide toggle button unless hovered */
  html.sidebar-collapsed #sidebar-collapse-toggle {
    position: absolute;
    right: 8px;
    background: white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    display: none;
    z-index: 50;
  }

  html.sidebar-collapsed .sidebar-header:hover #sidebar-collapse-toggle {
    display: flex;
  }

  /* Hide labels, badges, and chevrons */
  html.sidebar-collapsed .sidebar-item span,
  html.sidebar-collapsed .sidebar-module-header span,
  html.sidebar-collapsed .sidebar-submodule-header span,
  html.sidebar-collapsed .sidebar-module-header i[data-chevron],
  html.sidebar-collapsed .sidebar-submodule-header i[data-chevron],
  html.sidebar-collapsed .sidebar-badge {
    display: none !important;
  }

  /* Align icons in center */
  html.sidebar-collapsed .sidebar-item,
  html.sidebar-collapsed .sidebar-module-header,
  html.sidebar-collapsed .sidebar-submodule-header {
    justify-content: center;
    padding-left: 0;
    padding-right: 0;
    margin-left: 4px;
    margin-right: 4px;
    height: 40px;
  }

  html.sidebar-collapsed .sidebar-item i,
  html.sidebar-collapsed .sidebar-module-header i:not([data-chevron]),
  html.sidebar-collapsed .sidebar-submodule-header i:not([data-chevron]) {
    margin-right: 0 !important;
    width: 1.25rem;
    height: 1.25rem;
  }

  /* Hide nested content when collapsed */
  html.sidebar-collapsed .sidebar-content [data-content] {
    display: none !important;
  }

  /* Rotate chevron icon when collapsed */
  html.sidebar-collapsed #collapse-chevron {
    transform: rotate(180deg);
  }
}

/* Mobile Sidebar Drawer State (Width < 1024px) */
@media (max-width: 1023px) {
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 40;
    transform: translateX(-100%);
    width: 280px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.15);
  }

  .sidebar.mobile-open {
    transform: translateX(0);
  }

  /* Overlay Backdrop Active State */
  #sidebar-backdrop.active {
    opacity: 1;
    pointer-events: auto;
  }
}
</style>
