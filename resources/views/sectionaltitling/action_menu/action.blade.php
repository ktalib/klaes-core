<div class="relative dropdown-container">
   <!-- Dropdown Toggle Button -->
   <button type="button" class="dropdown-toggle p-2 hover:bg-gray-100 focus:outline-none rounded-full"
      onclick="customToggleDropdown(this, event)">
      <i data-lucide="more-horizontal" class="w-5 h-5"></i>
   </button>
   <!-- Dropdown Menu -->
   <ul class="fixed action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-56">

      <li>
         <a href="{{ route('sectionaltitling.viewrecorddetail')}}?id={{$PrimaryApplication->id}}"
            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
            <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
            <span>View Application</span>
         </a>
      </li>

      @if(!request()->has('survey') && (!request()->has('url') || (request()->get('url') !== 'phy_planning' && request()->get('url') !== 'recommendation')))
         <li>
            <a href="{{ route('actions.payments', ['id' => $PrimaryApplication->id]) }}"
               class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
               <i data-lucide="credit-card" class="w-4 h-4 text-green-500"></i>
               <span>View Bills & Payments</span>
            </a>
         </li>


         <li>
            <a href="{{ route('actions.other-departments', ['id' => $PrimaryApplication->id]) }}"
               class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
               <i data-lucide="layout-grid" class="w-4 h-4  "></i>
               <span>View Other Departments</span>
            </a>
         </li>

      @endif
      @if(!request()->has('survey') && (!request()->has('url') || (request()->get('url') !== 'phy_planning' && request()->get('url') !== 'recommendation')))
         <li>
            <button type="button" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2" onclick="openERegistryModal(
                  '{{ $PrimaryApplication->id }}', 
                  '{{ $PrimaryApplication->fileno}}',
                  '{{ $PrimaryApplication->applicant_type }}', 
                  @if($PrimaryApplication->applicant_type == 'individual')
                     {{ json_encode(['applicant_title' => $PrimaryApplication->applicant_title, 'first_name' => $PrimaryApplication->first_name, 'surname' => $PrimaryApplication->surname]) }}
                  @elseif($PrimaryApplication->applicant_type == 'corporate')
                     {{ json_encode(['corporate_name' => $PrimaryApplication->corporate_name]) }}
                  @elseif($PrimaryApplication->applicant_type == 'multiple')
                     {{ $PrimaryApplication->multiple_owners_names }}
                  @else
                     null
                  @endif
                )">
               <i data-lucide="database" class="w-4 h-4 text-red-500"></i>
               <span>View e-Registry</span>
            </button>
         </li>
      @endif
      @if(!request()->has('survey'))
         @if(
               (request()->has('url') && (request()->get('url') === 'phy_planning' || request()->get('url') === 'recommendation')) ||
               !($PrimaryApplication->planning_recommendation_status == 'Pending' ||
                  $PrimaryApplication->planning_recommendation_status == 'Declined' ||
                  $PrimaryApplication->application_status == 'Pending' ||
                  $PrimaryApplication->application_status == 'Declined')
            )
            <li>
               <a href="{{ route('actions.recommendation', ['id' => $PrimaryApplication->id]) }}{{ 
                            request()->has('url') && request()->get('url') === 'phy_planning'
               ? '?url=phy_planning'
               : (request()->has('url') && request()->get('url') === 'recommendation'
                  ? '?url=recommendation'
                  : '') 
                        }}" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                  <i data-lucide="clipboard-check" class="w-4 h-4 text-blue-500"></i>
                  <span>
                     @if(request()->has('url') && request()->get('url') === 'phy_planning')
                        Planning Recommendation Approval
                     @else
                        View Planning Recommendation
                     @endif
                  </span>
               </a>
            </li>
         @else
            @if($PrimaryApplication->application_status == 'Approved')
               <li>
                  <a href="{{ route('actions.recommendation', ['id' => $PrimaryApplication->id]) }}"
                     class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                     <i data-lucide="clipboard-check" class="w-4 h-4 text-blue-500"></i>
                     <span>Planning Recommendation</span>
                  </a>
               </li>
            @else
               <li class="opacity-50 cursor-not-allowed">
                  <a href="#" class="w-full text-left px-4 py-2 flex items-center space-x-2">
                     <i data-lucide="clipboard-check" class="w-4 h-4 text-gray-500"></i>
                     <span>View Planning Recommendation</span>
                  </a>
               </li>
            @endif
         @endif

         @if(!request()->has('url') || (request()->get('url') !== 'phy_planning' && request()->get('url') !== 'recommendation'))

            @if($PrimaryApplication->application_status == 'Approved')
               <li>
                  <a href="{{ route('actions.director-approval', ['id' => $PrimaryApplication->id]) }}?url=view"
                     class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                     <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                     <span>View Director's Approval</span>
                  </a>
               </li>
            @else
               <li class="opacity-50 cursor-not-allowed">
                  <a href="#" class="w-full text-left px-4 py-2 flex items-center space-x-2">
                     <i data-lucide="check-circle" class="w-4 h-4 text-gray-500"></i>
                     <span>View Director's Approval</span>
                  </a>
               </li>
            @endif
         @endif
      @endif

      @php
         // Check if there are sub_applications with this main_application_id in the sub_applications table
         $hasSubApplications = \DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('main_application_id', $PrimaryApplication->id)
            ->exists();
       @endphp

      @if(
            ($PrimaryApplication->planning_recommendation_status == 'Pending' && $PrimaryApplication->application_status == 'Pending')
            || !$hasSubApplications
         )
         <li class="opacity-50 cursor-not-allowed">
            <a href="#" class="w-full text-left px-4 py-2 flex items-center space-x-2">
               <i data-lucide="list" class="w-4 h-4 text-gray-500"></i>
               <span>View Unit Application(s)</span>
            </a>
         </li>
      @else
         <li>
            <a href="{{ route('sectionaltitling.units') }}?main_application_id={{ $PrimaryApplication->id }}"
               class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
               <i data-lucide="list" class="w-4 h-4 text-blue-600"></i>
               <span>View Unit Application(s)</span>
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