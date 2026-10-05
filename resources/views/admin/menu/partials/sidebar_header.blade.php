<div class="sidebar-header border-b border-gray-200 h-28 flex items-center justify-center pl-4 pr-10 bg-gradient-to-r from-white via-blue-100 to-purple-200 relative">
  <div class="flex items-center justify-center gap-2 logo-container overflow-hidden">
    <img
      src="{{ asset('assets/logo/klas_core_logo.png') }}"
      alt="KLAES-CORE Logo"
      class="h-24 w-auto max-w-full object-contain transition-all duration-300"
      id="sidebar-logo"
    />
  </div>
  <!-- Desktop Collapse Toggle Button -->
  <button type="button" id="sidebar-collapse-toggle" class="absolute right-2 top-1/2 transform -translate-y-1/2 p-1.5 rounded-md hover:bg-slate-200/60 text-slate-600 hover:text-slate-800 focus:outline-none transition-all duration-300 hidden lg:flex items-center justify-center" aria-label="Collapse Sidebar">
    <i data-lucide="chevron-left" class="w-5 h-5 transition-transform duration-300" id="collapse-chevron"></i>
  </button>
  <!-- Mobile Close Toggle Button -->
  <button type="button" id="sidebar-mobile-close" class="absolute right-2 top-1/2 transform -translate-y-1/2 p-1.5 rounded-md hover:bg-slate-200/60 text-slate-600 hover:text-slate-800 focus:outline-none transition-all duration-300 flex lg:hidden items-center justify-center" aria-label="Close Sidebar">
    <i data-lucide="x" class="w-5 h-5"></i>
  </button>
</div>
