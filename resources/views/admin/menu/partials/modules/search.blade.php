    <!-- 5. Legal Search -->
    @if(
      $hasRole('Deeds - Official (for filing purpose)') || $hasRole('Deeds - On-Premise (Pay-Per-Search)') || $hasRole('Deeds - Legal Search Reports') || auth()->user()->assign_role === 'Supper Admin'
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">

      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="search">
      <div class="flex items-center gap-2">
        <i data-lucide="file-search" class="h-6 w-6 module-icon-legal-search text-cyan-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Legal Search</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="search"></i>
      </div>


      

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="search">

      <!-- a. Property Records -->
      <a href="{{ route('property-search.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('property-search.*') ? 'active' : '' }}">
        <i data-lucide="file-bar-chart" class="h-3.5 w-3.5 text-cyan-400"></i>
        <span>Property Records</span>
      </a>

      <!-- b. On-Premise Legal Search -->
      @if($hasRole('Deeds - Official (for filing purpose)') || $hasRole('Deeds - On-Premise (Pay-Per-Search)') || $hasRole('Deeds - Legal Search Reports'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="onPremiseLegalSearch">
        <div class="flex items-center gap-2">
          <i data-lucide="scale" class="h-4 w-4 text-cyan-500"></i>
          <span>On-Premise Legal Search</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="onPremiseLegalSearch"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="onPremiseLegalSearch">
        <!-- i. Official (for filing purpose) -->
        @if($hasRole('Deeds - Official (for filing purpose)'))
        <a href="{{route('legal_search.index')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('legal_search.index') ? 'active' : '' }}">
          <i data-lucide="file-check-2" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Official (for filing purpose)</span>
        </a>
        @endif
        <!-- ii. On-Premise -->
        @if($hasRole('Deeds - On-Premise (Pay-Per-Search)'))
        <a href="{{route('onpremise.index')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('onpremise.index') ? 'active' : '' }}">
          <i data-lucide="building" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>On-Premise</span>
        </a>
        @endif
        <!-- iii. Legal Search Reports -->
        @if($hasRole('Deeds - Legal Search Reports'))
        <a href="{{route('legalsearchreports.index')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('legalsearchreports.index') ? 'active' : '' }}">
          <i data-lucide="file-bar-chart" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Legal Search Reports</span>
        </a>
        @endif
      </div>
      @endif

      <!-- c. Transaction Token Control (TTC) -->
      @if($hasRole('Transaction Token Control') || auth()->user()->assign_role === 'Supper Admin')
      <a href="{{ route('legal-search-tokens.index', ['url' => 'ls']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('legal-search-tokens.*') && request('url') === 'ls' ? 'active' : '' }}">
        <i data-lucide="ticket" class="h-4 w-4 text-cyan-500"></i>
        <span>Transaction Token Control (TTC)</span>
      </a>
      @endif

      <!-- d. Online Legal Search -->
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="onlineLegalSearch">
        <div class="flex items-center gap-2">
          <i data-lucide="globe" class="h-4 w-4 text-cyan-500"></i>
          <span>Online Legal Search</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="onlineLegalSearch"></i>
      </div>
      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="onlineLegalSearch">
        <!-- i. Online -->
        <a href="{{ route('ols.landing') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('ols.landing') ? 'active' : '' }}">
          <i data-lucide="globe" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Online</span>
        </a>
        <!-- ii. Search Requests (Director / Deputy Director approval queue) -->
        <a href="{{ route('legal-search-online.admin.requests') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('legal-search-online.admin.requests*') ? 'active' : '' }}">
          <i data-lucide="inbox" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Search Requests</span>
        </a>
        <!-- iii. Online Legal Search Admin -->
        @if(auth()->user()->assign_role === 'Supper Admin')
        <a href="{{ route('legal-search-online.admin.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('legal-search-online.admin.index') ? 'active' : '' }}">
          <i data-lucide="shield-check" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Online Legal Search Admin</span>
        </a>
        @endif
        <!-- iii. Feedback & Complaints -->
        <a href="{{ route('legal-search-online.admin.feedback') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('legal-search-online.admin.feedback') ? 'active' : '' }}">
          <i data-lucide="message-square-warning" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Feedback &amp; Complaints</span>
        </a>
      </div>

      @if(auth()->user()->assign_role === 'Supper Admin')
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="phsPortalAdmin">
        <div class="flex items-center gap-2">
        <i data-lucide="building-2" class="h-4 w-4 text-cyan-500"></i>
        <span>PHS Portal Admin</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="phsPortalAdmin"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="phsPortalAdmin">
        <a href="{{ route('system-admin.phs.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.phs.index') ? 'active' : '' }}">
          <i data-lucide="layout-dashboard" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>PHS-P Dashboard</span>
        </a>
        <a href="{{ route('system-admin.phs.requests.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.phs.requests.*') ? 'active' : '' }}">
          <i data-lucide="user-plus" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Onboarding Requests</span>
        </a>
        <a href="{{ route('system-admin.phs.pending-invoices') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.phs.pending-invoices') ? 'active' : '' }}">
          <i data-lucide="file-clock" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Pending Invoice</span>
        </a>
        <a href="{{ route('system-admin.phs.invoices') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.phs.invoices') ? 'active' : '' }}">
          <i data-lucide="receipt" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Subscriptions</span>
        </a>
        <a href="{{ route('system-admin.phs.usage') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.phs.usage') ? 'active' : '' }}">
          <i data-lucide="bar-chart-3" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Usage and Revenue</span>
        </a>
        <a href="{{ route('system-admin.phs.packages.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.phs.packages.*') ? 'active' : '' }}">
          <i data-lucide="package" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Packages</span>
        </a>

        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="phsTokenTopup">
          <div class="flex items-center gap-2">
          <i data-lucide="wallet" class="h-4 w-4 text-cyan-500"></i>
          <span>Token Top-up</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="phsTokenTopup"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="phsTokenTopup">
          <a href="{{ route('system-admin.phs.topups') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.phs.topups') ? 'active' : '' }}">
            <i data-lucide="coins" class="h-3.5 w-3.5 text-cyan-400"></i>
            <span>Token Top-up</span>
          </a>
          <a href="{{ route('system-admin.phs.wallets') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.phs.wallets') ? 'active' : '' }}">
            <i data-lucide="landmark" class="h-3.5 w-3.5 text-cyan-400"></i>
            <span>Wallets</span>
          </a>
        </div>

        <a href="{{ route('system-admin.phs.legal.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.phs.legal.*') ? 'active' : '' }}">
          <i data-lucide="scale" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Legal Department</span>
        </a>

        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="phsFeedback">
          <div class="flex items-center gap-2">
          <i data-lucide="message-square-warning" class="h-4 w-4 text-cyan-500"></i>
          <span>Feedback &amp; Complaints</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="phsFeedback"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="phsFeedback">
          <a href="{{ route('system-admin.phs.feedback') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.phs.feedback') ? 'active' : '' }}">
            <i data-lucide="message-square-warning" class="h-3.5 w-3.5 text-cyan-400"></i>
            <span>Feedback &amp; Complaints</span>
          </a>
          <a href="{{ route('system-admin.phs.edit-requests') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('system-admin.phs.edit-requests*') ? 'active' : '' }}">
            <i data-lucide="file-pen-line" class="h-3.5 w-3.5 text-cyan-400"></i>
            <span>Edit Request</span>
          </a>
        </div>
      </div>
      @endif
      </div>
    </div>
    @endif

   

 
