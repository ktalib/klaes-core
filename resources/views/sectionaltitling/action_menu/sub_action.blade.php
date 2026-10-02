<div class="relative dropdown-container">
   <!-- Dropdown Toggle Button -->
   <button type="button" class="dropdown-toggle p-2 hover:bg-gray-100 focus:outline-none rounded-full"
      onclick="customToggleDropdown(this, event)">
      <i data-lucide="more-horizontal" class="w-5 h-5"></i>
   </button>
   <!-- Dropdown Menu -->
   <ul class="fixed action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-56">
      <li>
         <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $app->id) }}"
            class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
            <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
            <span>View Record</span>
         </a>
      </li>

      <li>
         <a href="{{ route('sub-actions.payments', $app->id) }}"
            class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
            <i data-lucide="credit-card" class="w-4 h-4 text-green-500"></i>
            <span>View Bills & Payments</span>
         </a>
      </li>



      <li>
         <a href="{{ route('sub-actions.other-departments', $app->id) }}"
            class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
            <i data-lucide="layout-grid" class="w-4 h-4 text-red-500"></i>
            <span>View Other Departments</span>
         </a>
      </li>
      @php
         $isSua_local = ($app->is_sua_unit ?? 0) == 1 || (strtoupper($app->unit_type ?? '') === 'SUA');
         $isPlanningApproved = ($app->planning_recommendation_status ?? '') === 'Approved' ||
            (!$isSua_local && strtolower($app->mother_planning_status ?? '') === 'approved');
         $isDirectorApproved = ($app->application_status ?? '') === 'Approved';
      @endphp

      <li>
         @if($isPlanningApproved)
            <a href="{{ route('sub-actions.recommendation', $app->id) }}"
               class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
               <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
               <span>View Planning Recommendation</span>
            </a>
         @else
            <button type="button"
               class="block w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50" disabled>
               <i data-lucide="eye" class="w-4 h-4 text-gray-400"></i>
               <span class="text-gray-400">View Planning Recommendation</span>
            </button>
         @endif
      </li>
      <li>
         @if($isDirectorApproved)
            <a href="{{ route('sub-actions.director-approval', $app->id) }}?url=view"
               class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
               <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
               <span>View Director's Approval</span>
            </a>
         @else
            <button type="button"
               class="block w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50" disabled>
               <i data-lucide="eye" class="w-4 h-4 text-gray-400"></i>
               <span class="text-gray-400">View Director's Approval</span>
            </button>
         @endif
      </li>
      <li>
         <button type="button" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2" onclick="openERegistryModal(
               '{{ $app->id }}',
               '{{ $app->fileno ?? '' }}',
               @if(!empty($app->corporate_name))
                  'corporate',
                  {{ json_encode(['corporate_name' => $app->corporate_name]) }}
               @elseif(!empty($app->multiple_owners_names))
                  'multiple',
                  @json(is_array($app->multiple_owners_names) ? $app->multiple_owners_names : json_decode($app->multiple_owners_names, true))
               @else
                                                               'individual',
                                                               {{ json_encode([
                     'applicant_title' => $app->applicant_title ?? '',
                     'first_name' => $app->first_name ?? '',
                     'surname' => $app->surname ?? ''
                  ]) }}
               @endif
            )">
            <i data-lucide="database" class="w-4 h-4 text-red-500"></i>
            <span>View e-Registry</span>
         </button>
      </li> @php
         $memoExists = DB::connection('sqlsrv')
            ->table('memos')
            ->where('application_id', $app->main_application_id ?? $app->id)
            ->exists();

         $filenoExists = !empty($app->fileno) && DB::connection('sqlsrv')
            ->table('registered_instruments')
            ->where(function ($query) use ($app) {
               $query->where('StFileNo', $app->fileno)
                  ->orWhere('MLSFileNo', $app->fileno)
                  ->orWhere('KAGISFileNO', $app->fileno)
                  ->orWhere('NewKANGISFileNo', $app->fileno);
            })
            ->exists();
      @endphp
      <li>
         @if($filenoExists)
            <a href="{{ route('coroi.index') }}?url=registered_instruments&fileno={{ $app->fileno }}"
               class="flex px-4 py-2 text-sm   hover:bg-gray-100 items-center">
               <i class="fas fa-eye mr-2 text-blue-400"></i>
               <span class="whitespace-nowrap text-sm" style="font-size: 75%">View Pagination Details</span>
            </a>
         @else
            <button type="button"
               class="flex px-4 py-2 text-sm text-gray-400 hover:bg-gray-100 items-center cursor-not-allowed opacity-50"
               disabled>
               <i class="fas fa-eye mr-2 text-gray-400"></i>
               <span class="whitespace-nowrap text-sm" style="font-size: 75%">View Pagination Details</span>
            </button>
         @endif
      </li>


      @if($memoExists)
         @php
            $memoId = DB::connection('sqlsrv')
               ->table('memos')
               ->where('application_id', $app->main_application_id ?? $app->id)
               ->value('application_id');
         @endphp
         <li>
            <a href="{{ route('programmes.view_memo_primary', $memoId) }}?url=unit={{ $app->unit_number ?? '' }}&unit_id={{ $app->id ?? '' }}"
               class="flex w-full text-left px-4 py-2 hover:bg-gray-100 items-center space-x-2">
               <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
               <span>View ST Memo</span>
            </a>
         </li>
      @else
         <li>
            <button type="button"
               class="flex w-full text-left px-4 py-2 items-center space-x-2 cursor-not-allowed opacity-50" disabled>
               <i data-lucide="eye" class="w-4 h-4 text-gray-400"></i>
               <span class="text-gray-400">View ST Memo</span>
            </button>
         </li>
      @endif

      <li>
         <button type="button"
            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 cursor-not-allowed opacity-50"
            data-id="{{ $app->id }}" disabled>
            <i data-lucide="trash-2" class="w-4 h-4 text-gray-400"></i>
            <span class="text-gray-400">Delete Record</span>
         </button>
      </li>
      {{-- Divider after CoR and ST Memo --}}
      <hr class="my-2 border-gray-200">

      @php
         $rofoExists = DB::connection('sqlsrv')
            ->table('rofo')
            ->where('sub_application_id', $app->id)
            ->exists();
      @endphp

      @if(!$rofoExists)
         {{-- <li>
            <button type="button" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2"
               onclick="window.location='{{ route('programmes.generate_rofo', $app->id) }}'">
               <i data-lucide="file-plus" class="w-4 h-4 text-purple-500"></i>
               <span>Generate RofO/Letter of Grant</span>
            </button>
         </li> --}}
         <li>
            <button type="button"
               class="block w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50" disabled>
               <i data-lucide="eye" class="w-4 h-4 text-gray-400"></i>
               <span class="text-gray-400">View RofO/Letter of Grant</span>
            </button>
         </li>
      @else
         {{-- <li>
            <button type="button"
               class="w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50" disabled>
               <i data-lucide="file-plus" class="w-4 h-4 text-gray-400"></i>
               <span class="text-gray-400">Generate RofO/Letter of Grant</span>
            </button>
         </li> --}}
         <li>
            <a href="{{ route('programmes.view_rofo', $app->id) }}"
               class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
               <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
               <span>View RofO/Letter of Grant</span>
            </a>
         </li>
      @endif

      <li>
         <button type="button"
            class="block w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50" disabled>
            <i data-lucide="eye" class="w-4 h-4 text-gray-400"></i>
            <span class="text-gray-400">View TDP</span>
         </button>
      </li>
      @php
         $cofoExists = DB::connection('sqlsrv')
            ->table('st_cofo')
            ->where('sub_application_id', $app->id)
            ->exists();
      @endphp

      @if(!$cofoExists)
         {{-- Removed Generate CofO button --}}
         <li>
            <button type="button"
               class="block w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50" disabled>
               <i data-lucide="eye" class="w-4 h-4 text-gray-400"></i>
               <span class="text-gray-400">View CofO</span>
            </button>
         </li>
      @else
         {{-- Removed disabled Generate CofO button --}}
         <li>
            <a href="{{ route('programmes.view_cofo', $app->id) }}"
               class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
               <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
               <span>View CofO</span>
            </a>
         </li>
      @endif

   </ul>
</div>
<script>
   function customToggleDropdown(button, event) {
      event.stopPropagation();
      const currentDropdown = button.closest('.dropdown-container').querySelector('.action-menu');
      const isCurrentlyHidden = currentDropdown.classList.contains('hidden');

      // Close all other dropdowns first
      const allDropdowns = document.querySelectorAll('.action-menu');
      allDropdowns.forEach(dropdown => {
         dropdown.classList.add('hidden');
      });

      // If the current dropdown was hidden, show it
      if (isCurrentlyHidden) {
         currentDropdown.classList.remove('hidden');

         // Get button position
         const rect = button.getBoundingClientRect();

         // Position dropdown above the button
         currentDropdown.style.top = (rect.top - currentDropdown.offsetHeight - 5) + 'px';
         currentDropdown.style.left = (rect.left - currentDropdown.offsetWidth + rect.width) + 'px';

         // Check if dropdown would appear off the top of the screen
         if (rect.top - currentDropdown.offsetHeight < 0) {
            // If so, position it below the button instead


            currentDropdown.style.top = (rect.bottom + 5) + 'px';
         }
      }
   }

   // Close dropdown when clicking outside
   document.addEventListener('click', function (event) {
      const dropdowns = document.querySelectorAll('.action-menu');
      dropdowns.forEach(dropdown => {
         if (!dropdown.contains(event.target) &&
            !dropdown.closest('.dropdown-container').querySelector('.dropdown-toggle').contains(event.target)) {
            dropdown.classList.add('hidden');
         }
      });
   });




</script>