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
    @if($hasRole('Automated Billing') || $hasRole('Legacy Billing'))
    <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="billing">
      <div class="flex items-center gap-2">
        <i data-lucide="receipt" class="h-4 w-4 text-emerald-500"></i>
        <span>Billing</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="billing"></i>
    </div>

    <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="billing">
      @if($hasRole('Automated Billing'))
      <a href="/revenue/billing/automated" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="cpu" class="h-3.5 w-3.5 text-emerald-400"></i>
        <span>Automated Billing</span>
      </a>
      @endif
      @if($hasRole('Legacy Billing'))
      <a href="/revenue/billing/legacy" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="history" class="h-3.5 w-3.5 text-emerald-400"></i>
        <span>Legacy Billing</span>
      </a>
      @endif
    </div>
    @endif
    
    @if($hasRole('Generate Receipt') || $hasRole('Land Use Charge (LUC)') || $hasRole('Bill Balance'))
    <a href="/revenue/generate-receipt" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
      <i data-lucide="receipt" class="h-4 w-4 text-emerald-500"></i>
      <span>Generate Receipt</span>
    </a>
    @endif
    @if($hasRole('Land Use Charge (LUC)'))
    <a href="/revenue/land-use-charge" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
      <i data-lucide="tag" class="h-4 w-4 text-emerald-500"></i>
      <span>Land Use Charge (LUC)</span>
    </a>
    @endif
    @if($hasRole('Bill Balance'))
    <a href="/revenue/bill-balance" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
      <i data-lucide="calculator" class="h-4 w-4 text-emerald-500"></i>
      <span>Bill Balance</span>
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
