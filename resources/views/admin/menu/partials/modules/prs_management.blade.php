<!-- PRS Management (after Cadastral) -->
@if($hasRole('prs') || $hasRole('Supper Admin'))
  @php
      // All five entries open the same report; what differs is the department
      // filter and which section it lands on. The active test therefore has to
      // compare those parameters — matching on the route name alone lit up every
      // item at once, because they share one route.
      $prsDept = request()->query('dept', 'all');
      $prsSec  = request()->query('sec');

      $prsLinks = [
          ['label' => 'PRS Annual Report',       'icon' => 'file-chart-column', 'dept' => 'all',   'sec' => null],
          ['label' => 'Deeds Instruments',       'icon' => 'file-signature',    'dept' => 'deeds', 'sec' => 'deed_assignment'],
          ['label' => 'Legal Search',            'icon' => 'search',            'dept' => 'deeds', 'sec' => 'search'],
          ['label' => 'Land File Commissioning', 'icon' => 'folder-tree',       'dept' => 'lands', 'sec' => 'land_conversion'],
          ['label' => 'Land Allocation',         'icon' => 'landmark',          'dept' => 'lands', 'sec' => 'land_direct_allocation'],
      ];
  @endphp

  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="prs-management">
      <div class="flex items-center gap-2">
        <i data-lucide="bar-chart-3" class="h-5 w-5 text-emerald-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">PRS Management</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="prs-management"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="prs-management">
      @foreach($prsLinks as $link)
        @php
            // Exactly one item can match: the department must agree, and so must
            // the section — including both being absent for the top-level report.
            $isActive = request()->routeIs('prs-report.*')
                        && $prsDept === $link['dept']
                        && $prsSec === $link['sec'];
        @endphp
        <a href="{{ route('prs-report.index', array_filter(['dept' => $link['dept'], 'sec' => $link['sec']])) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ $isActive ? 'active' : '' }}">
          <i data-lucide="{{ $link['icon'] }}" class="h-4 w-4 text-emerald-500"></i>
          <span>{{ $link['label'] }}</span>
        </a>
      @endforeach
    </div>
  </div>
@endif
