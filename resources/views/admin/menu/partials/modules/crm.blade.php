    <!-- 1. Customer Relationship Management -->
    @if(
      $hasRole('CRM - Person') || $hasRole('CRM - Corporate') || $hasRole('CRM - Customer Manager')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="customer">
      <div class="flex items-center gap-2">
        <i data-lucide="user-plus" class="h-6 w-6 module-icon-customer text-green-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Customer Relationship Management</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="customer"></i>
      </div>
      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="customer">
      @if($hasRole('CRM - Person'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="person">
        <div class="flex items-center gap-2">
        <i data-lucide="users" class="h-4 w-4 text-green-500"></i>
        <span>Person</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="person"></i>
      </div>
      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="person">
        <a href="{{ route('entities.index', ['entity_type' => 'Individual']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('entities.index') && request('entity_type') === 'Individual' ? 'active' : '' }}">
        <i data-lucide="user" class="h-4 w-4 text-green-500"></i>
        <span>Individual</span>
        </a>
        <a href="{{ route('entities.index', ['entity_type' => 'Group']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('entities.index') && request('entity_type') === 'Group' ? 'active' : '' }}">
        <i data-lucide="users" class="h-4 w-4 text-green-500"></i>
        <span>Group</span>
        </a>
        <a href="{{ route('entities.index', ['entity_type' => 'Multiple Owners']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('entities.index') && request('entity_type') === 'Multiple Owners' ? 'active' : '' }}">
        <i data-lucide="users-round" class="h-4 w-4 text-green-500"></i>
        <span>Multiple Owners</span>
        </a>
      </div>
      @endif
      @if($hasRole('CRM - Corporate'))
      <a href="{{ route('entities.index', ['entity_type' => 'Corporate']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('entities.index') && request('entity_type') === 'Corporate' ? 'active' : '' }}">
        <i data-lucide="building" class="h-4 w-4 text-green-500"></i>
        <span>Corporate</span>
      </a>
      @endif
      @if($hasRole('CRM - Customer Manager'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="customerManager">
        <div class="flex items-center gap-2">
        <i data-lucide="calendar-clock" class="h-4 w-4 text-green-500"></i>
        <span>Customer Manager</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="customerManager"></i>
      </div>
      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="customerManager">
        @if($hasRole('Appointment'))
        <a href="/appointment" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="calendar" class="h-4 w-4 text-green-500"></i>
        <span>Appointment</span>
        </a>
        @endif
        @if($hasRole('Appointment Calendar'))
        <a href="/appointment/calendar" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="calendar-days" class="h-4 w-4 text-green-500"></i>
        <span>Appointment Calendar</span>
        </a>
        @endif
      </div>
      @endif
      </div>
    </div>
    @endif
