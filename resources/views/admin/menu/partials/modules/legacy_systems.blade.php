    <!-- 15. Legacy Systems -->
    @if($hasRole('Legacy System'))
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="legacy">
        <div class="flex items-center gap-2">
          <i data-lucide="hard-drive" class="h-5 w-5 text-stone-600"></i>
          <span class="text-sm font-bold uppercase tracking-wider">Legacy Systems</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="legacy"></i>
      </div>

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="legacy">
        <a href="/legacy-systems" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="database" class="h-4 w-4 text-stone-500"></i>
          <span>Legacy Systems</span>
        </a>
      </div>
    </div>
    @endif
