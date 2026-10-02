@if($hasRole('Indexing') || $hasRole('Print File Labels') || $hasRole('Print Sign In & Out Sheet') || $hasRole('Blind Scanning') || $hasRole('Scanning') || $hasRole('Document Page Types') || $hasRole('PT Quality Control') || $hasRole('EDMS Update'))
  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="edms">
      <div class="flex items-center gap-2">
        <i data-lucide="database" class="h-5 w-5 text-emerald-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">DMS</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="edms"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="edms">
      <!-- a. Indexing -->
      @if($hasRole('Indexing'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="indexing">
          <div class="flex items-center gap-2">
            <i data-lucide="file-search" class="h-4 w-4 text-emerald-500"></i>
            <span>Indexing</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="indexing"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="indexing">
          <!-- File Indexing (nested) -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
            data-section="fileIndexing">
            <div class="flex items-center gap-2">
              <i data-lucide="folder-tree" class="h-4 w-4 text-emerald-500"></i>
              <span>File Indexing</span>
            </div>
            <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="fileIndexing"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="fileIndexing">
            <a href="{{route('indexed-files.index')}}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('indexed-files.index') ? 'active' : '' }}">
              <i data-lucide="file-search" class="h-3.5 w-3.5 text-emerald-400"></i>
              <span>File Indexing Assistant</span>
            </a>

            {{-- Match OP. The two halves of the same job, so they sit under one
                 collapsible entry: an officer looking for "Match OP" finds both here
                 rather than two differently-named siblings.

                 It lives with indexing because that is the comparison it makes — the
                 Occupancy Permit's holder against the File Indexing name — and the
                 officers who work these files are indexing officers. --}}
            <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
              data-section="matchOp">
              <div class="flex items-center gap-2">
                <i data-lucide="git-merge" class="h-4 w-4 text-emerald-500"></i>
                <span>Match OP</span>
              </div>
              <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="matchOp"></i>
            </div>

            <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="matchOp">
              {{-- 1. Match OP (ToT) — writes the missing transfer.

                   No role check, here or on the page itself: anyone who can reach this
                   menu can open it. The write it performs is still guarded on its own
                   terms — the card only offers Match on a file whose chain has no
                   transfer explaining the change, and the officer confirms both names
                   before anything is recorded. --}}
              <a href="{{ route('land-recommendations.create') }}?match-op"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('land-recommendations.create') && request()->has('match-op') && request()->query('from') !== 'tot' ? 'active' : '' }}">
                <i data-lucide="git-merge" class="h-3.5 w-3.5 text-emerald-400"></i>
                <span>Match OP (ToT)</span>
              </a>

              {{-- 2. Match OP (FC) — the OP → File Property ID Matching page. A different
                   job from Match OP (ToT) above: that one writes a missing transfer,
                   this one moves permits that were captured before their file existed
                   onto the file's parcel id, so the file's records stop reading as
                   separate properties. Route and page keep their own op-propid-match
                   names. --}}
              <a href="{{ route('op-propid-match.index') }}"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('op-propid-match.*') ? 'active' : '' }}">
                <i data-lucide="link-2" class="h-3.5 w-3.5 text-emerald-400"></i>
                <span>Match OP (FC)</span>
              </a>
            </div>

            {{-- Unlink / Reassign OP — HIDDEN. Unmatching turned out not to need a page
                 of its own: it is the same officer, the same permits and the same parcel
                 ids, so it lives on Match OP (FC) above, where the batch already is. The
                 standalone page and its routes still exist and still work; only this
                 entry is commented out, so nothing has to be rebuilt if it is wanted
                 back.

            <a href="{{ route('op-unlink.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('op-unlink.*') ? 'active' : '' }}">
              <i data-lucide="unlink" class="h-3.5 w-3.5 text-emerald-400"></i>
              <span>Unlink / Reassign OP</span>
            </a>
            --}}


            <a href="{{route('missing-files.index')}}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('missing-files.*') ? 'active' : '' }}">
              <i data-lucide="file-warning" class="h-3.5 w-3.5 text-emerald-400"></i>
              <span>Missing Files</span>
            </a>
            <a href="{{route('indexing-duplicates.index')}}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('indexing-duplicates.*') ? 'active' : '' }}">
              <i data-lucide="folder-minus" class="h-3.5 w-3.5 text-emerald-400"></i>
              <span>Indexing Duplicates</span>
            </a>
            {{-- <a href="#"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 opacity-60 cursor-not-allowed pointer-events-none">
              <i data-lucide="file-search" class="h-3.5 w-3.5 text-emerald-400"></i>
              <span>File Indexing Assistant (old)</span>
            </a> --}}

            @if($hasRole('Indexing Activity Log'))
              <a href="{{route('fileindexing.activity-log')}}"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('fileindexing.activity-log') ? 'active' : '' }}">
                <i data-lucide="activity" class="h-3.5 w-3.5 text-emerald-400"></i>
                <span>Indexing Activity Log</span>
              </a>
            @endif
          </div>

          <a href="{{route('file-index-view.index')}}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-index-view.index') && !request()->has('url') && !request()->has('sltr') ? 'active' : '' }}">
            <i data-lucide="eye" class="h-3.5 w-3.5 text-emerald-400"></i>
            <span>File History View</span>
          </a>

          @if($hasRole('File SerialNo Grouping') || $hasRole('SerialNo Grouping') || $hasRole('Print File Labels'))
            <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
              data-section="serialNoGrouping">
              <div class="flex items-center gap-2">
                <i data-lucide="hash" class="h-4 w-4 text-emerald-500"></i>
                <span>File SerialNo Grouping</span>
              </div>
              <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
                data-chevron="serialNoGrouping"></i>
            </div>
            <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="serialNoGrouping">
              @if($hasRole('SerialNo Grouping'))
                <a href="{{ route('grouping-analytics.dashboard') }}"
                  class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('grouping-analytics.dashboard') ? 'active' : '' }}">
                  <i data-lucide="hash" class="h-4 w-4 text-emerald-400"></i>
                  <span>SerialNo Grouping</span>
                </a>
              @endif
              @if($hasRole('Print File Labels'))
                <a href="{{route('printlabel.index')}}"
                  class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('printlabel.index') ? 'active' : '' }}">
                  <i data-lucide="printer" class="h-4 w-4 text-emerald-500"></i>
                  <span>Print Files Label</span>
                </a>
              @endif
            </div>
          @endif

        </div>
      @endif

      <!-- b. File SerialNo Grouping Dropdown -->


      <!-- c. Print Sign In & Out Sheet -->
      @if($hasRole('Print Sign In & Out Sheet'))
        <a href="{{ route('fileindexing.signin') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('fileindexing.signin') ? 'active' : '' }}">
          <i data-lucide="clipboard-list" class="h-4 w-4 text-emerald-500"></i>
          <span>Print Sign In & Out Sheet</span>
        </a>
      @endif

      <!-- d. Blind Scanning -->
      @if($hasRole('Blind Scanning'))
        <a href="{{route('blind-scanning.index')}}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="eye-off" class="h-4 w-4 text-emerald-500"></i>
          <span>Blind Scanning</span>
        </a>
      @endif

      <!-- e. Scanning -->
      @if($hasRole('Scanning'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="scanning">
          <div class="flex items-center gap-2">
            <i data-lucide="scan" class="h-4 w-4 text-emerald-500"></i>
            <span>Scanning</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="scanning"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="scanning">
          <a href="{{route('scan-uploads.index')}}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('scan-uploads.index') ? 'active' : '' }}">
            <i data-lucide="upload" class="h-3.5 w-3.5 text-emerald-400"></i>
            <span>Upload Indexed Files</span>
          </a>

          {{-- <a href="#"
            class="sidebar-item disabled flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="upload" class="h-3.5 w-3.5 text-emerald-400"></i>
            <span>Upload Unindexed Files</span>
          </a> --}}
        </div>
      @endif

      <!-- f. Document Page Types -->
      @if($hasRole('Document Page Types'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="documentPageTypes">
          <div class="flex items-center gap-2">
            <i data-lucide="file-type" class="h-4 w-4 text-emerald-500"></i>
            <span>Document Page Types</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="documentPageTypes"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="documentPageTypes">
          <a href="{{route('pagetyping.index')}}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('pagetyping.index') ? 'active' : '' }}">
            <i data-lucide="type" class="h-3.5 w-3.5 text-emerald-400"></i>
            <span>PageTyping</span>
          </a>
        </div>
      @endif



      <!-- g. PT Quality Control -->
      @if($hasRole('PT Quality Control'))
        <a href="#"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('ptq-control.index') ? 'active' : '' }}">
          <i data-lucide="shield-check" class="h-4 w-4 text-emerald-500"></i>
          <span>PT Quality Control</span>
        </a>
      @endif


      <!-- h. EDMS Update -->
      @if($hasRole('EDMS Update'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="edmsUpdate">
          <div class="flex items-center gap-2">
            <i data-lucide="refresh-cw" class="h-4 w-4 text-emerald-500"></i>
            <span>DMS Update</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="edmsUpdate"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="edmsUpdate">
          <a href="/scanning?url=scmore"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="scan-line" class="h-3.5 w-3.5 text-emerald-400"></i>
            <span>Scan More</span>
          </a>

          <a href="/pagetyping?url=ptmore"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-emerald-400"></i>
            <span>Type More</span>
          </a>

        </div>
      @endif

      @if($hasRole('Activity Monitoring'))
        <a href="{{ route('activity-monitoring.index') }}?url=edms"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('activity-monitoring.*') && request()->query('url') === 'edms') ? 'active' : '' }}">
          <i data-lucide="activity" class="h-3.5 w-3.5 text-emerald-400"></i>
          <span>Activity Monitoring</span>
        </a>
      @endif

    </div>
  </div>
@endif