<!-- 7. Lands -->
@if(
    $hasRole('Land') ||
    $hasRole('Allocation List') ||
    $hasRole('Land One Stop Shop') ||
    $hasRole('File History View') ||
    $hasRole('File Search') ||
    $hasRole('Letter of Grant (RofO)') ||
    $hasRole('Land to Cad/Survey') ||
    $hasRole('Parcel/Title Management-Land') ||
    $hasRole('Generate New FileNo (MLSFileNo)') ||
    $hasRole('EDMS Update')||
    $hasRole('Regrant') ||
    $hasRole('Land Registration')
)
  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="lands">
      <div class="flex items-center gap-2">
        <i data-lucide="landmark" class="h-5 w-5 text-orange-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Land</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 text-black transition-transform duration-200"
        data-chevron="lands"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="lands">

 

      <!-- a. Land -->
      @if($hasRole('Land') || $hasRole('Allocation List'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="land-main">
          <div class="flex items-center gap-2">
            <i data-lucide="map-pin" class="h-4 w-4 text-orange-500"></i>
            <span>Land</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="land-main"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="land-main">
          @if($hasRole('Allocation List'))
          <!-- i. Allocation List -->
          <a href="{{ route('allocation-list.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('allocation-list.index') ? 'active' : '' }}">
            <i data-lucide="list" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Allocation List</span>
          </a>
          @endif

          @if($hasRole('Land'))
          <!-- ii. Generate New FileNo (MLSFileNo) -->
          <a href="{{ route('file-numbers.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-numbers.index') ? 'active' : '' }}">
            <i data-lucide="hash" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Generate New FileNo (MLSFileNo)</span>
          </a>

          <!-- iii. Land Applications -->
          <a href="{{ route('lands-one-stop-shop.all-applications.index', ['type' => 'no-change-of-name']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.all-applications.index') && request()->query('type') === 'no-change-of-name' ? 'active' : '' }}">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Land Applications</span>
          </a>

          <!-- iv. Bill -->
          <a href="{{ route('lands-one-stop-shop.bill.index') }}?type=land"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.bill.index') && request()->query('type') === 'land' ? 'active' : '' }}">
            <i data-lucide="receipt" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Bill</span>
          </a>

          <!-- v. Land to Cad/Survey -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
            data-section="cadSurvey-land-main">
            <div class="flex items-center gap-2">
              <i data-lucide="map" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Land to Cad/Survey</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="cadSurvey-land-main"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadSurvey-land-main">
            <!-- 1. Land 12 (Request for Survey Report) -->
            <a href="{{ route('survey-report.index') }}?url=lands12"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-report.index') && request()->query('url') === 'lands12' ? 'active' : '' }}">
              <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Land 12 (Request for Survey Report)</span>
            </a>

            <!-- 2. For Information -->
            <a href="{{ route('for-information.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('for-information.index') ? 'active' : '' }}">
              <i data-lucide="info" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>For Information</span>
            </a>
          </div>

          <!-- vi. Capture/Manage an Existing File -->
          <a href="{{ route('existing-file-numbers.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('existing-file-numbers.index') ? 'active' : '' }}">
            <i data-lucide="folder-plus" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Capture/Manage an Existing File</span>
          </a>

          <!-- vii. File Decommissioning -->
          <a href="{{ route('file-decommissioning.decommissioned') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-decommissioning.decommissioned') ? 'active' : '' }}">
            <i data-lucide="archive" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>File Decommissioning</span>
          </a>
          @endif
        </div>
      @endif

      <!-- b. Land One Stop Shop -->
      @if($hasRole('Land One Stop Shop'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="lands-one-stop-shop">
          <div class="flex items-center gap-2">
            <i data-lucide="store" class="h-4 w-4 text-orange-500"></i>
            <span>OSS Management</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="lands-one-stop-shop"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="lands-one-stop-shop">
          <!-- i. Generate FileNos (Change of Name) -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
            data-section="lands-oss-change-name">
            <div class="flex items-center gap-2">
              <i data-lucide="hash" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Generate FileNos (Change of Ownership)</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="lands-oss-change-name"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="lands-oss-change-name">
            <!-- 1. File Commissioning (FC) -->
            <a href="{{ route('lands-one-stop-shop.applications.index', ['source' => 'lands-one-stop-shop', 'type' => 'change-of-name', 'record_type' => 'fc']) }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.applications.index') && request()->query('record_type') === 'fc' ? 'active' : '' }}">
              <div class="w-1.5 h-1.5 rounded-full bg-blue-400"></div>
              <span>File Commissioning (FC)</span>
            </a>

            <!-- 2. Fetch Existing File Record (FEFR) -->
            <a href="{{ route('lands-one-stop-shop.applications.index', ['source' => 'lands-one-stop-shop', 'type' => 'change-of-name', 'record_type' => 'fefr']) }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.applications.index') && request()->query('record_type') === 'fefr' ? 'active' : '' }}">
              <div class="w-1.5 h-1.5 rounded-full bg-orange-400"></div>
              <span>Fetch Existing File Record (FEFR)</span>
            </a>

            <!-- 3. Transfer of Title (ToT) -->
            <a href="{{ url('/maintenance/tot') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('maintenance/tot') ? 'active' : '' }}">
              <div class="w-1.5 h-1.5 rounded-full bg-green-400"></div>
              <span>Transfer of Title (ToT)</span>
            </a>
          </div>

          <!-- ii. New Applications (Existing OP Change of Name) -->
          <a href="{{ route('lands-one-stop-shop.all-applications.index', ['type' => 'change-of-name']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.all-applications.index') && request()->query('type') === 'change-of-name' ? 'active' : '' }}">
            <i data-lucide="users" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>New Applications (Existing OP Change of Ownership)</span>
          </a>

          <!-- iii. Bill -->
          <a href="{{ route('lands-one-stop-shop.bill.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.bill.index') && !request()->has('type') ? 'active' : '' }}">
            <i data-lucide="receipt" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Bill</span>
          </a>

          <!-- iv. OSS to Cad/Survey -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
            data-section="cadSurvey-oss">
            <div class="flex items-center gap-2">
              <i data-lucide="map" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>OSS to Cad/Survey</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="cadSurvey-oss"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadSurvey-oss">
            <!-- 1. Land 12 -->
            <a href="{{ route('survey-report.index') }}?url=lands"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-report.index') && request()->query('url') === 'lands' ? 'active' : '' }}">
              <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Land 12</span>
            </a>

            <!-- 2. For Information -->
            <a href="{{ route('for-information.index') }}?url=oss"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('for-information.index') && request()->query('url') === 'oss' ? 'active' : '' }}">
              <i data-lucide="info" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>For Information</span>
            </a>
          </div>

          <!-- v. OSS Letter of Grant -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
            data-section="ossLetterOfGrant-oss">
            <div class="flex items-center gap-2">
              <i data-lucide="scroll" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>OSS Letter of Grant</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="ossLetterOfGrant-oss"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="ossLetterOfGrant-oss">
            <!-- 1. OSS Recommendation -->
            <a href="{{ route('land-recommendations.index', ['type' => 'OSS']) }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('land-recommendations.index') && strtoupper((string) request()->query('type')) === 'OSS' ? 'active' : '' }}">
              <i data-lucide="check-circle" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>OSS Recommendation</span>
            </a>

            <!-- 2. OSS RofO -->
            <a href="/land-rofos?=oss-rofo&view=only"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('land-rofos') && request()->query('view') === 'only' ? 'active' : '' }}">
              <i data-lucide="file-check" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>OSS RofO</span>
            </a>
          </div>
        </div>
      @endif

      <!-- c. Letter of Grant (RofO) -->
      @if($hasRole('Letter of Grant (RofO)') || $hasRole('Regrant'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="rofo-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="scroll" class="h-4 w-4 text-orange-500"></i>
            <span>Letter of Grant (RofO)</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="rofo-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="rofo-lands">
          <!-- i. Land Recommendation -->
          <a href="{{ route('land-recommendations.index', ['type' => 'ROFO']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('land-recommendations.index') && strtoupper((string) request()->query('type')) === 'ROFO' ? 'active' : '' }}">
            <i data-lucide="check-circle" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Land Recommendation</span>
          </a>

          <!-- ii. RofO -->
          <a href="{{ route('land-rofos.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('land-rofos.index') && request()->query('view') !== 'only' ? 'active' : '' }}">
            <i data-lucide="file-check" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>RofO</span>
          </a>

          <!-- iii. Re-grant Files -->
          <a href="{{ route('regrant.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('regrant.index') ? 'active' : '' }}">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Re-grant Files</span>
          </a>


        </div>
      @endif


      <!-- d. Land Registration -->
      @if($hasRole('Land Registration') || $hasRole('Super Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="land-registration">
          <div class="flex items-center gap-2">
            <i data-lucide="book-marked" class="h-4 w-4 text-orange-500"></i>
            <span>Land Registration</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="land-registration"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="land-registration">
          {{-- Two registers, two sources. "Land Registry" lists the historical
               Deeds of Purchase backfilled through PRA; "New Registration" lists
               the ones registered through this system, which hold a number from
               the vault. They are deliberately not merged. --}}

        

          <!-- i. Land Registry (New Registration) -->
          <a href="{{ route('land-registration.registration.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('land-registration.registration.*') ? 'active' : '' }}">
            <i data-lucide="book-open" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Land Registry (New Registration)</span>
          </a>

          <!-- iI. Land Registry (historical, from PRA) -->

  <a href="{{ route('land-registration.capture.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('land-registration.capture.index') ? 'active' : '' }}">
            <i data-lucide="archive" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Land Registry (Legacy)</span>
          </a>

        </div>
      @endif
      <!-- e. File History (parent menu: History View + Related Files + File Search) -->
      @if($hasRole('File History View') || $hasRole('File Search'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="fileHistory-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="history" class="h-4 w-4 text-orange-500"></i>
            <span>File History</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="fileHistory-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="fileHistory-lands">
          <!-- i. History View -->
          <a href="{{ route('file-index-view.index', ['url' => 'land']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-index-view.index') && request()->query('url') === 'land' ? 'active' : '' }}">
            <i data-lucide="eye" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>History View</span>
          </a>

          <!-- ii. Related Files -->
          <a href="{{ route('related-file-number.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('related-file-number.*') ? 'active' : '' }}">
            <i data-lucide="link" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Related Files</span>
          </a>

          <!-- iii. File Search -->
          @if($hasRole('File Search'))
            <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
              data-section="fileSearch-lands">
              <div class="flex items-center gap-2">
                <i data-lucide="search" class="h-3.5 w-3.5 text-orange-400"></i>
                <span>File Search</span>
              </div>
              <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200"
                data-chevron="fileSearch-lands"></i>
            </div>

            <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="fileSearch-lands">
              <!-- 1. Scans -->
              <a href="{{ route('file-search.scans') }}"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-search.scans') ? 'active' : '' }}">
                <i data-lucide="image" class="h-3.5 w-3.5 text-orange-400"></i>
                <span>Scans</span>
              </a>

            </div>
          @endif

        </div>
      @endif

      <!-- f. Problem Files -->
      @if($hasRole('File History View') || $hasRole('File Search'))
        <a href="{{ route('file-search-db.index') }}"
          class="sidebar-submodule-header flex items-center gap-2 py-1.5 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-search-db.index') ? 'active' : '' }}">
          <i data-lucide="table" class="h-4 w-4 text-orange-500"></i>
          <span>Problem Files</span>
        </a>
      @endif

      <!-- g. Parcel/Title Management -->
      @if($hasRole('Parcel/Title Management-Land'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="otherApplications-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="files" class="h-4 w-4 text-orange-500"></i>
            <span>Parcel/Title Management</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="otherApplications-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="otherApplications-lands">
          {{-- Duplex leads the module: it is the whole instruction, and every entry
               below it — Change of Purpose included — is one part of what a duplex can
               carry. ?mode=land labels the page as Land's, it no longer limits it:
               Land raises its own parcel updates here as well as commissioning them
               from the MLPP Commission New File Number modal. --}}
          @if($hasRole('Duplex Parcel Update-Land'))
            <a href="{{ route('duplex-parcel-update.index') }}?mode=land"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('duplex-parcel-update.*') && request()->query('mode') === 'land') ? 'active' : '' }}">
              <i data-lucide="layers" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Advance Parcel Update (Duplex)</span>
            </a>
          @endif

          <!-- ii. Simple Parcel Update -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
            data-section="simpleParcelUpdate-lands">
            <div class="flex items-center gap-2">
              <i data-lucide="map" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Simple Parcel Update</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="simpleParcelUpdate-lands"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="simpleParcelUpdate-lands">
            <!-- 1. Change of Purpose -->
            <a href="{{ route('change-of-purpose.index') }}?mode=land"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('change-of-purpose.*') && request()->query('mode') === 'land') ? 'active' : '' }}">
              <i data-lucide="repeat" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Change of Purpose</span>
            </a>

            <!-- 2. Parcel Update - New -->
            @if($hasRole('Parcel Update-New'))
            <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
              data-section="parcelUpdate-lands">
              <div class="flex items-center gap-2">
                <i data-lucide="map" class="h-3.5 w-3.5 text-orange-400"></i>
                <span>Parcel Update - New</span>
              </div>
              <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="parcelUpdate-lands"></i>
            </div>

            <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="parcelUpdate-lands">
              <!-- 1. Plot Subdivision -->
              <a href="{{ route('plot-subdivision.index') }}?mode=land"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('plot-subdivision.*') && request()->query('mode') === 'land') ? 'active' : '' }}">
                <i data-lucide="split-square-horizontal" class="h-3.5 w-3.5 text-orange-400"></i>
                <span>Plot Subdivision</span>
              </a>

              <!-- 2. Plot Merger -->
              <a href="{{ route('plot-merger.index') }}?mode=land"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('plot-merger.*') && request()->query('mode') === 'land') ? 'active' : '' }}">
                <i data-lucide="combine" class="h-3.5 w-3.5 text-orange-400"></i>
                <span>Plot Merger</span>
              </a>

              <!-- 3. Plot Extension -->
              <a href="{{ route('plot-extension.index') }}?mode=land"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('plot-extension.*') && request()->query('mode') === 'land') ? 'active' : '' }}">
                <i data-lucide="expand" class="h-3.5 w-3.5 text-orange-400"></i>
                <span>Plot Extension</span>
              </a>

              <!-- 4. Plot Separation -->
              <a href="{{ route('plot-separation.index') }}?mode=land"
                class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('plot-separation.*') && request()->query('mode') === 'land') ? 'active' : '' }}">
                <i data-lucide="scissors" class="h-3.5 w-3.5 text-orange-400"></i>
                <span>Plot Separation</span>
              </a>
            </div>

            <!-- 3. Parcel Update - Legacy (view only) -->
            <a href="{{ route('admin.manual-linkage.index') }}?url=land_view"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('admin.manual-linkage.*') && request()->query('url') === 'land_view' ? 'active' : '' }}">
              <i data-lucide="link" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Parcel Update - Legacy</span>
            </a>
            @endif
          </div>

          <!-- iii. Title Status Update -->
          <a href="{{ route('title-status.index') }}?url=land"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('title-status.index') && request('url') === 'land' ? 'active' : '' }}">
            <i data-lucide="badge-check" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Title Status Update</span>
          </a>

          <!-- iv. Loss of Document -->
          <a href="{{ route('loss-of-document.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('loss-of-document.index') ? 'active' : '' }}">
            <i data-lucide="file-warning" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Loss of Document</span>
          </a>

          <!-- v. Temporary File -->
          <a href="{{ route('temporary-file.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('temporary-file.*') ? 'active' : '' }}">
            <i data-lucide="file-clock" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Temporary File</span>
          </a>

          <!-- vi. Re-grant Management -->
          {{-- <a href="{{ route('regrant.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('regrant.index') ? 'active' : '' }}">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5 text-indigo-400"></i>
            <span>Re-grant</span>
          </a> --}}
        </div>
      @endif

      <!-- h. Digital Archive (standalone) -->
      @if($hasRole('File History View') || $hasRole('File Search'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="digitalArchive-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="archive" class="h-4 w-4 text-orange-500"></i>
            <span>Digital Archive</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="digitalArchive-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive-lands">
          <!-- i. File Tracker Dashboard -->
          <a href="{{ route('file-tracker.dashboard', ['url' => 'land']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-tracker.dashboard') && request('url') === 'land' ? 'active' : '' }}">
            <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>File Tracker Dashboard</span>
          </a>

          <!-- ii. File Tracker (Archive) -->
          <a href="{{ route('track-file-archive.index', ['url' => 'land']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('track-file-archive.index') && request('url') === 'land' ? 'active' : '' }}">
            <i data-lucide="archive" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>File Tracker (Archive)</span>
          </a>

          <!-- iii. Quick Search -->
          <a href="{{ route('create-file-tracker.quick-search', ['url' => 'land']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.quick-search') && request('url') === 'land' ? 'active' : '' }}">
            <i data-lucide="search" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Quick Search</span>
          </a>

          <!-- iv. Log a File -->
          <a href="{{ route('create-file-tracker.index', ['url' => 'land']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'land' ? 'active' : '' }}">
            <i data-lucide="file-plus" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Log a File</span>
          </a>

          <!-- v. File Digital Library – Doc-WARE -->
          <a href="{{ route('filearchive.index', ['url' => 'land']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && request('url') === 'land' ? 'active' : '' }}">
            <i data-lucide="library" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>File Digital Library – Doc-WARE</span>
          </a>
        </div>
      @endif

      <!-- i. EDMS Update -->
      @if($hasRole('EDMS Update'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="edmsUpdate-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="refresh-cw" class="h-4 w-4 text-orange-500"></i>
            <span>DMS Update</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="edmsUpdate-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="edmsUpdate-lands">
          <a href="/scanning?url=scmore"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('scanning') && request()->query('url') === 'scmore' ? 'active' : '' }}">
            <i data-lucide="scan-line" class="h-3.5 w-3.5 text-orange-500"></i>
            <span>Scan More</span>
          </a>
          <a href="/pagetyping?url=ptmore"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('pagetyping') && request()->query('url') === 'ptmore' ? 'active' : '' }}">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-500"></i>
            <span>Type More</span>
          </a>
        </div>
      @endif
     <!-- LAAS Portal — applications submitted by the public. Gated on the same
           roles that already review and allocate land applications. -->
      @if($hasRole('Land') || $hasRole('Land One Stop Shop') || $hasRole('Generate New FileNo (MLSFileNo)'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="laas-portal">
          <div class="flex items-center gap-2">
            <i data-lucide="globe" class="h-4 w-4 text-orange-500"></i>
            <span>LAAS Portal</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="laas-portal"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="laas-portal">
          <a href="{{ route('laas-admin.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('laas-admin.index') || request()->routeIs('laas-admin.show') ? 'active' : '' }}">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Applications</span>
          </a>
          <a href="{{ route('laas-admin.applicants') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('laas-admin.applicants*') ? 'active' : '' }}">
            <i data-lucide="users" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Applicants</span>
          </a>
        </div>
      @endif
    </div>
  </div>
@endif
