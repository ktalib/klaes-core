    <!-- 2b. File Tracking (standalone, un-scoped / corporate) -->
    @if(
      $hasRole('Log a File') || $hasRole('File Tracker/Tracking - RFID') || $hasRole('File Digital Library - Doc-WARE') || $hasRole('EDMS Update') || $hasRole('File Movement (Department)')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="fileTracking">
        <div class="flex items-center gap-2">
          <i data-lucide="folder-search" class="h-5 w-5 text-teal-600"></i>
          <span class="text-sm font-bold uppercase tracking-wider">File Tracking</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="fileTracking"></i>
      </div>

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="fileTracking">
        <!-- a. File Tracker Dashboard -->
        <a href="{{ route('file-tracker.dashboard') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-tracker.dashboard') && !request('url') ? 'active' : '' }}">
          <i data-lucide="bar-chart-2" class="h-4 w-4 text-teal-500"></i>
          <span>File Tracker Dashboard</span>
        </a>

        <!-- b. File Tracker (Archive) -->
        <a href="{{ route('track-file-archive.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('track-file-archive.index') && !request('url') ? 'active' : '' }}">
          <i data-lucide="archive" class="h-4 w-4 text-teal-500"></i>
          <span>File Tracker (Archive)</span>
        </a>

        <!-- c. Quick Search -->
        <a href="{{ route('create-file-tracker.quick-search') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.quick-search') && !request('url') ? 'active' : '' }}">
          <i data-lucide="search" class="h-4 w-4 text-teal-500"></i>
          <span>Quick Search</span>
        </a>

        <!-- d. Log a File -->
        @if($hasRole('Log a File'))
        <a href="{{ route('create-file-tracker.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && !request('url') ? 'active' : '' }}">
          <i data-lucide="file-plus" class="h-4 w-4 text-teal-500"></i>
          <span>Log a File (Archive)</span>
        </a>
        @endif
        <!-- e. File Movement (Department) — in/out register for HC / PS / Directors' offices -->
        @if($hasRole('File Movement (Department)'))
        <a href="{{ route('secretariat-file-log.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('secretariat-file-log.*') ? 'active' : '' }}">
          <i data-lucide="arrow-left-right" class="h-4 w-4 text-teal-500"></i>
          <span>File Movement (Department)</span>
        </a>
        @endif
      </div>
    </div>
    @endif
