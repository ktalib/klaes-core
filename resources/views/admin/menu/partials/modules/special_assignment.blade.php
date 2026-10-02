    <!-- 18. Special Assignment -->
    @php
        $spaActive      = request()->routeIs('special-assignment.*');
        $spaFieldActive = request()->routeIs('special-assignment.field-data');
        $spaBillsActive = (request()->is('programmes/bills') || request()->is('programmes/payments')) && request()->query('url') === 'spa';
        $spaNoticeActive = request()->routeIs('special-assignment.notice');
        $spaOtherActive = request()->routeIs('special-assignment.other-departments');
    @endphp
    @if(
      $hasRole('Special Assignment - Land Records') ||
      $hasRole('Special Assignment - Field Data') ||
      $hasRole('Special Assignment - Bills & Payments') ||
      $hasRole('Special Assignment - Notice') ||
      $hasRole('Special Assignment - Other Departments') ||
      $hasRole('Special Assignment - Certificate') ||
      $hasRole('Special Assignment - Report') ||
      $hasRole('Supper Admin')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="special-assignment">
        <div class="flex items-center gap-2">
          <i data-lucide="star" class="h-5 w-5 text-[rgb(186,191,12)]"></i>
          <span class="text-sm font-bold uppercase tracking-wider">Special Assignment</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 {{ $spaActive ? 'rotate-90' : '' }}" data-chevron="special-assignment"></i>
      </div>

      <div class="pl-4 mt-1 space-y-0.5 {{ $spaActive ? '' : 'hidden' }}" data-content="special-assignment">

        <!-- a. Land Records -->
        @if($hasRole('Special Assignment - Land Records') || $hasRole('Supper Admin'))
        <a href="{{ route('special-assignment.land-records') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('special-assignment.land-records') ? 'active' : '' }}">
          <i data-lucide="land-plot" class="h-4 w-4 text-[rgb(186,191,12)]"></i>
          <span>Land Records</span>
        </a>
        @endif

        <!-- b. Field Data (collapsible) -->
        @if($hasRole('Special Assignment - Field Data') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md {{ $spaFieldActive ? 'active' : '' }}" data-section="sa-field-data">
          <div class="flex items-center gap-2">
            <i data-lucide="clipboard-list" class="h-4 w-4 text-[rgb(186,191,12)]"></i>
            <span>Field Data</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 {{ $spaFieldActive ? 'rotate-90' : '' }}" data-chevron="sa-field-data"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 {{ $spaFieldActive ? '' : 'hidden' }}" data-content="sa-field-data">
          <a href="{{ route('special-assignment.field-data') }}#field-map" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="map" class="h-3.5 w-3.5 text-[rgb(186,191,12)]"></i>
            <span>Field Map</span>
          </a>
          <a href="{{ route('special-assignment.field-data') }}#records" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-[rgb(186,191,12)]"></i>
            <span>Records</span>
          </a>
        </div>
        @endif

        <!-- c. Bills & Payments (collapsible) -->
        @if($hasRole('Special Assignment - Bills & Payments') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md {{ $spaBillsActive ? 'active' : '' }}" data-section="sa-bills-payments">
          <div class="flex items-center gap-2">
            <i data-lucide="receipt" class="h-4 w-4 text-[rgb(186,191,12)]"></i>
            <span>Bills &amp; Payments</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 {{ $spaBillsActive ? 'rotate-90' : '' }}" data-chevron="sa-bills-payments"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 {{ $spaBillsActive ? '' : 'hidden' }}" data-content="sa-bills-payments">
          <a href="{{ route('programmes.bills') }}?url=spa" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('programmes/bills') && request()->query('url')==='spa' ? 'active' : '' }}">
            <i data-lucide="file-minus" class="h-3.5 w-3.5 text-[rgb(186,191,12)]"></i>
            <span>Bills</span>
          </a>
          <a href="{{ route('programmes.payments') }}?url=spa" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('programmes/payments') && request()->query('url')==='spa' && !request()->query('sub') ? 'active' : '' }}">
            <i data-lucide="credit-card" class="h-3.5 w-3.5 text-[rgb(186,191,12)]"></i>
            <span>Payments</span>
          </a>
          <a href="{{ route('programmes.payments') }}?url=spa&sub=report" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('programmes/payments') && request()->query('url')==='spa' && request()->query('sub')==='report' ? 'active' : '' }}">
            <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-[rgb(186,191,12)]"></i>
            <span>Payment Reports</span>
          </a>
        </div>
        @endif

        <!-- d. Notice (collapsible) -->
        @if($hasRole('Special Assignment - Notice') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md {{ $spaNoticeActive ? 'active' : '' }}" data-section="sa-notice">
          <div class="flex items-center gap-2">
            <i data-lucide="bell" class="h-4 w-4 text-[rgb(186,191,12)]"></i>
            <span>Notice</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 {{ $spaNoticeActive ? 'rotate-90' : '' }}" data-chevron="sa-notice"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 {{ $spaNoticeActive ? '' : 'hidden' }}" data-content="sa-notice">
          <a href="{{ route('special-assignment.notice') }}#first-serve" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="send" class="h-3.5 w-3.5 text-[rgb(186,191,12)]"></i>
            <span>First Serve</span>
          </a>
          <a href="{{ route('special-assignment.notice') }}#second-serve" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="send" class="h-3.5 w-3.5 text-[rgb(186,191,12)]"></i>
            <span>Second Serve</span>
          </a>
        </div>
        @endif

        <!-- e. Memo -->
        @if($hasRole('Special Assignment - Notice') || $hasRole('Supper Admin'))
        <a href="{{ route('special-assignment.memo') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('special-assignment.memo') ? 'active' : '' }}">
          <i data-lucide="file-text" class="h-4 w-4 text-[rgb(186,191,12)]"></i>
          <span>Memo</span>
        </a>
        @endif

        <!-- g. Other Departments (collapsible) -->
        @if($hasRole('Special Assignment - Other Departments') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md {{ $spaOtherActive ? 'active' : '' }}" data-section="sa-other-departments">
          <div class="flex items-center gap-2">
            <i data-lucide="building-2" class="h-4 w-4 text-[rgb(186,191,12)]"></i>
            <span>Other Departments</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200 {{ $spaOtherActive ? 'rotate-90' : '' }}" data-chevron="sa-other-departments"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 {{ $spaOtherActive ? '' : 'hidden' }}" data-content="sa-other-departments">
          <a href="{{ route('special-assignment.other-departments') }}#cadastral" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="layout-grid" class="h-3.5 w-3.5 text-[rgb(186,191,12)]"></i>
            <span>Cadastral</span>
          </a>
          <a href="{{ route('special-assignment.other-departments') }}#physical-planning" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="drafting-compass" class="h-3.5 w-3.5 text-[rgb(186,191,12)]"></i>
            <span>Physical Planning/KAMMA</span>
          </a>
          <a href="{{ route('special-assignment.other-departments') }}#survey" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="scan-line" class="h-3.5 w-3.5 text-[rgb(186,191,12)]"></i>
            <span>Survey</span>
          </a>
        </div>
        @endif

        <!-- h. Certificate -->
        @if($hasRole('Special Assignment - Certificate') || $hasRole('Supper Admin'))
        <a href="{{ route('special-assignment.certificate') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('special-assignment.certificate') ? 'active' : '' }}">
          <i data-lucide="file-badge" class="h-4 w-4 text-[rgb(186,191,12)]"></i>
          <span>Change of Purpose Sheet</span>
        </a>
        @endif

        <!-- i. Report -->
        @if($hasRole('Special Assignment - Report') || $hasRole('Supper Admin'))
        <a href="{{ route('special-assignment.report') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('special-assignment.report') ? 'active' : '' }}">
          <i data-lucide="file-bar-chart" class="h-4 w-4 text-[rgb(186,191,12)]"></i>
          <span>Report</span>
        </a>
        @endif

      </div>
    </div>
    @endif
