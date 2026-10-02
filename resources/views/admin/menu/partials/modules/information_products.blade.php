    <!-- 3. Information Products -->
    @if(
      $hasRole('Letter of Grant/RofO') || $hasRole('Occupancy Permit (OP)') || $hasRole('Site Plan/Parcel Plan') ||
      $hasRole('Certificate of Occupancy')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="infoProducts">
        <div class="flex items-center gap-2"> 
          <i data-lucide="file-output" class="h-5 w-5 text-pink-600"></i>
          <span class="text-sm font-bold uppercase tracking-wider">Information Products</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="infoProducts"></i>
      </div>

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="infoProducts">
          {{-- <a href="{{ route('information-products.verification') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="shield-check" class="h-4 w-4 text-pink-500"></i>
          <span class="text-sm">Verification</span>
        </a>   --}}
        @if($hasRole('Letter of Grant/RofO'))
        <a href="{{ route('rofo-staging.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="file-plus" class="h-4 w-4 text-pink-500"></i>
          <span class="text-sm">Letter of Grant/RofO</span>
        </a>
        @endif
        @if($hasRole('Occupancy Permit (OP)'))
        <a href="{{ route('ops-dashboard.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="file-warning" class="h-4 w-4 text-pink-500"></i>
          <span>Occupancy Permit (OP)</span>
        </a>
        {{-- <a href="{{ route('ops-dashboard.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 ml-3">
          <i data-lucide="layout-dashboard" class="h-4 w-4 text-pink-400"></i>
          <span class="text-slate-600">OPs Dashboard</span>
        </a> --}}
        @endif
        @if($hasRole('Site Plan/Parcel Plan'))
        <a href="/documents/site-plan" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="file-text" class="h-4 w-4 text-pink-500"></i>
          <span>Site Plan/Parcel Plan</span>
        </a>
        @endif

        @if($hasRole('Certificate of Occupancy'))
        <a href="/propertycard/cofo" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="file-text" class="h-4 w-4 text-pink-500"></i>
          <span>Certificate of Occupancy</span>
        </a>
        @endif
      </div>
    </div>
    @endif
