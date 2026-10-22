 <!-- 6. Revenue Management -->

 @if(
  $hasRole('Billing') || $hasRole('Generate Receipt') ||
  $hasRole('Land Use Charge (LUC)') || $hasRole('Bill Balance') ||
  $hasRole('Supper Admin') || $hasRole('Transaction Token Control')
)
<div class="py-1 px-3 mb-0.5 border-t border-slate-100">
  <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="revenue">
    <div class="flex items-center gap-2">
      <i data-lucide="banknote" class="h-5 w-5 text-emerald-600"></i>
      <span class="text-sm font-bold uppercase tracking-wider">KLAES REV-M</span>
    </div>
    <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="revenue"></i>
  </div>

  <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="revenue">
    {{-- a. Dashboard --}}
    <a href="{{ route('revenue.dashboard') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('revenue.dashboard') ? 'active' : '' }}">
      <i data-lucide="layout-dashboard" class="h-4 w-4 text-emerald-500"></i>
      <span>Dashboard</span>
    </a>

    {{-- b. Billing --}}
    @if($hasRole('Billing') || $hasRole('Land Use Charge (LUC)') || $hasRole('Bill Balance') || $hasRole('Supper Admin'))
    <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="rev-billing">
      <div class="flex items-center gap-2">
        <i data-lucide="receipt" class="h-4 w-4 text-emerald-500"></i>
        <span>Billing</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="rev-billing"></i>
    </div>

    <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="rev-billing">
      {{-- Automated Billing --}}
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="rev-automated-billing">
        <div class="flex items-center gap-2">
          <i data-lucide="cpu" class="h-3.5 w-3.5 text-emerald-400"></i>
          <span>Automated Billing</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="rev-automated-billing"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="rev-automated-billing">
        @php
          // Automated Billing, in the order of the updated sidebar sheet (2026-10-20): Bill Master
          // (the whole register), then each bill type. LuC is on hold (# for now). The two PHS
          // types are not on the sheet; they stay at the end until the user says otherwise.
          // "On-the-fly Bills" (Bill Master ?raised=on_the_fly) is hidden for now (user, 2026-10-21);
          // the On-the-fly bill button on Bill Master remains.
          // [label, icon, href, active?, roles that see it]
          $revOnType = fn ($type, $sub = null) => request()->routeIs('revenue.bills.index') && request()->route('type') === $type
              && ($sub ? request('sub') === $sub : ! request('sub'));
          $revType = fn ($type, $sub = null) => route('revenue.bills.index', $type) . ($sub ? '?sub=' . $sub : '');
          $revAutomatedItems = [
            ['Bill Master', 'layers', route('revenue.bills.all'), request()->routeIs('revenue.bills.all') && request('raised') !== 'on_the_fly', ['Billing']],
            ['Land Use Charge (LuC)', 'tag', '#', false, ['Land Use Charge (LUC)', 'Billing']],
            ['Ground Rent', 'landmark', $revType('ground_rent'), $revOnType('ground_rent'), ['Billing']],
            ['Re-grant', 'refresh-ccw', $revType('re_grant'), $revOnType('re_grant'), ['Billing']],
            ['Betterment Bill', 'trending-up', $revType('betterment'), $revOnType('betterment'), ['Billing']],
            ['Initial Bill', 'file-plus', $revType('initial_bill'), $revOnType('initial_bill'), ['Billing']],
            ['Primary', 'file', $revType('initial_bill', 'initial_primary'), $revOnType('initial_bill', 'initial_primary'), ['Billing']],
            ['PuA', 'file-text', $revType('initial_bill', 'initial_pua'), $revOnType('initial_bill', 'initial_pua'), ['Billing']],
            ['SuA', 'files', $revType('initial_bill', 'initial_sua'), $revOnType('initial_bill', 'initial_sua'), ['Billing']],
            ['OSS Bill', 'store', $revType('oss_bill'), $revOnType('oss_bill'), ['Billing']],
            ['5% Deed of Purchase', 'percent', $revType('deed_of_purchase'), $revOnType('deed_of_purchase'), ['Billing']],
            ['Consent Bill', 'handshake', $revType('consent_bill'), $revOnType('consent_bill'), ['Billing']],
            ['Fees, Plan & Description Bill', 'map', $revType('fees_plan_description'), $revOnType('fees_plan_description'), ['Billing']],
            ['Legal Search Bill', 'search', $revType('legal_search'), $revOnType('legal_search'), ['Billing']],
            ['Contravention Bill', 'alert-triangle', $revType('contravention_bill'), $revOnType('contravention_bill'), ['Billing']],
            ['Bill Balance', 'calculator', $revType('bill_balance'), $revOnType('bill_balance'), ['Bill Balance', 'Billing']],
            ['PHS Onboarding / Subscription', 'building-2', $revType('phs_onboarding'), $revOnType('phs_onboarding'), ['Billing']],
            ['PHS Token Top-up', 'coins', $revType('phs_token_topup'), $revOnType('phs_token_topup'), ['Billing']],
          ];
        @endphp
        @foreach ($revAutomatedItems as [$revLabel, $revIcon, $revHref, $revActive, $revRoles])
          @if(collect($revRoles)->contains(fn ($r) => $hasRole($r)) || $hasRole('Supper Admin'))
          <a href="{{ $revHref }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $revActive ? 'active' : '' }}">
            <i data-lucide="{{ $revIcon }}" class="h-3.5 w-3.5 text-emerald-400"></i>
            <span>{{ $revLabel }}</span>
          </a>
          @endif
        @endforeach
      </div>

      {{-- Legacy Billing --}}
      @if($hasRole('Billing') || $hasRole('Supper Admin'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="rev-legacy-billing">
        <div class="flex items-center gap-2">
          <i data-lucide="history" class="h-3.5 w-3.5 text-emerald-400"></i>
          <span>Legacy Billing</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="rev-legacy-billing"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="rev-legacy-billing">
        <a href="{{ route('revenue.legacy.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('revenue.legacy.index') ? 'active' : '' }}">
          <i data-lucide="archive" class="h-3.5 w-3.5 text-emerald-400"></i>
          <span>Bill Legacy</span>
        </a>
      </div>
      @endif

      {{-- Payment Cycles: recurring bills and their next due dates --}}
      @if($hasRole('Billing') || $hasRole('Supper Admin'))
      <a href="{{ route('revenue.cycles.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('revenue.cycles.*') ? 'active' : '' }}">
        <i data-lucide="repeat" class="h-3.5 w-3.5 text-emerald-400"></i>
        <span>Payment Cycles</span>
      </a>
      @endif

      {{-- Bill Settings is reached from System Settings, Configurable Entries, Bill Settings tab (hidden from this menu 2026-10-19). --}}
    </div>
    @endif

    {{-- Receipting --}}
    @if($hasRole('Generate Receipt') || $hasRole('Supper Admin'))
    <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="rev-receipting">
      <div class="flex items-center gap-2">
        <i data-lucide="receipt-text" class="h-4 w-4 text-emerald-500"></i>
        <span>Receipting</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="rev-receipting"></i>
    </div>

    <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="rev-receipting">
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="rev-generate-receipt">
        <div class="flex items-center gap-2">
          <i data-lucide="file-check" class="h-3.5 w-3.5 text-emerald-400"></i>
          <span>Generate Receipt</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="rev-generate-receipt"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="rev-generate-receipt">
        <a href="{{ route('revenue.receipts.awaiting') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('revenue.receipts.awaiting') ? 'active' : '' }}">
          <i data-lucide="hourglass" class="h-3.5 w-3.5 text-emerald-400"></i>
          <span>Awaiting Receipt</span>
        </a>
        <a href="{{ route('revenue.receipts.index', 'e-receipt') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('revenue.receipts.index') && request()->route('mode') === 'e-receipt' ? 'active' : '' }}">
          <i data-lucide="mail" class="h-3.5 w-3.5 text-emerald-400"></i>
          <span>e-Receipt</span>
        </a>
        <a href="{{ route('revenue.receipts.index', 'print') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('revenue.receipts.index') && request()->route('mode') === 'print' ? 'active' : '' }}">
          <i data-lucide="printer" class="h-3.5 w-3.5 text-emerald-400"></i>
          <span>Print Receipt</span>
        </a>
      </div>
    </div>
    @endif

    {{-- Reconciliation: payments against Paystack / POS settlement / bank --}}
    @if($hasRole('Billing') || $hasRole('Supper Admin'))
    <a href="{{ route('revenue.reconciliation.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('revenue.reconciliation.*') ? 'active' : '' }}">
      <i data-lucide="scale" class="h-4 w-4 text-emerald-500"></i>
      <span>Reconciliation</span>
    </a>
    @endif

    @if($hasRole('Transaction Token Control') || $hasRole('Supper Admin'))
    <a href="{{ route('legal-search-tokens.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('legal-search-tokens.*') && request('url') !== 'ls' ? 'active' : '' }}">
      <i data-lucide="ticket" class="h-4 w-4 text-emerald-500"></i>
      <span>Transaction Token Control (TTC)</span>
    </a>
    @endif
  </div>
</div>
@endif
