@php
   $isSua_local = ($app->is_sua_unit ?? 0) == 1 || (strtoupper($app->unit_type ?? '') === 'SUA');
   $applicationStatus = strtolower((string) ($app->application_status ?? ''));
   $planningStatusRaw = strtolower((string) ($app->planning_recommendation_status ?? ''));
   $motherPlanningStatus = strtolower((string) ($app->mother_planning_status ?? ''));

   $isPlanningApproved = $planningStatusRaw === 'approved' || (!$isSua_local && $motherPlanningStatus === 'approved');
   $isBothApproved = $applicationStatus === 'approved' && $isPlanningApproved;
   $editDisabled = $isBothApproved;
@endphp

<div class="relative dropdown-container">
   <!-- Dropdown Toggle Button -->
   <button type="button" class="dropdown-toggle p-2 hover:bg-gray-100 focus:outline-none rounded-full"
      onclick="customToggleDropdown(this, event)">
      <i data-lucide="more-horizontal" class="w-5 h-5"></i>
   </button>
   <!-- Dropdown Menu -->
   <ul class="fixed action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-56">
      <li>
         @php
            $editDisabled = $isBothApproved;
         @endphp

         @if($editDisabled)
            <button type="button"
               class="block w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50" disabled>
               <i data-lucide="edit" class="w-4 h-4 text-gray-400"></i>
               <span class="text-gray-400">View Application</span>
            </button>
         @else
            <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $app->id) }}"
               class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
               <i data-lucide="edit" class="w-4 h-4 text-blue-500"></i>
               <span>View Application</span>
            </a>
         @endif
      </li>
      {{-- <li>
         <a href="{{ route('programmes.generate_memo', $app->id) }}"
            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2" data-id="{{ $app->id }}"
            onclick="generateMemo('{{ $app->id }}')">
            <i data-lucide="file-text" class="w-4 h-4 text-indigo-500"></i>
            <span>Generate ST Memo</span>
         </a>
      </li> --}}

      {{-- Divider after View/Edit Record, Bills & Payments --}}





      <li>
         @php
            $deleteDisabled = $editDisabled; // disallow delete when both approvals are Approved
         @endphp
         @if($deleteDisabled)
            <button type="button"
               class="w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50"
               data-id="{{ $app->id }}" disabled>
               <i data-lucide="trash-2" class="w-4 h-4 text-gray-400"></i>
               <span class="text-gray-400">Delete Record</span>
            </button>
         @else
            <button type="button" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2"
               data-id="{{ $app->id }}" onclick="deleteSubApplication({{ $app->id }})">
               <i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i>
               <span>Delete Record</span>
            </button>
         @endif
      </li>
      {{-- Divider --}}
      <hr class="my-2 border-gray-200">
      @php
         $subAckExists = DB::connection('sqlsrv')->table('dbo.st_acknowledgement_tracking')
            ->where('sub_application_id', $app->id)
            ->exists();
      @endphp

      <!-- Generate Acknowledgement Sheet (Sub) -->
      <li class="{{ $subAckExists ? 'opacity-50 cursor-not-allowed' : '' }}">
         <button type="button"
            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 {{ $subAckExists ? 'pointer-events-none' : '' }}"
            onclick="{{ $subAckExists ? 'return false;' : 'generateSubAcknowledgement(' . $app->id . ')' }}">
            <i data-lucide="file-plus" class="w-4 h-4 text-purple-600"></i>
            <span>Generate Acknowledgement Sheet</span>
         </button>
      </li>

      <!-- View Acknowledgement Sheet (Sub) -->
      <li class="{{ $subAckExists ? '' : 'opacity-50 cursor-not-allowed' }}">
         <a href="{{ $subAckExists ? route('sectionaltitling.sub.acknowledgement', ['id' => $app->id]) : '#' }}"
            target="_blank"
            class="w-full text-left px-4 py-2 flex items-center space-x-2 {{ $subAckExists ? 'hover:bg-gray-100' : '' }}"
            title="{{ $subAckExists ? '' : 'Generate Acknowledgement Sheet first' }}">
            <i data-lucide="printer" class="w-4 h-4 {{ $subAckExists ? 'text-indigo-600' : 'text-gray-400' }}"></i>
            <span>View Acknowledgement Sheet</span>
         </a>
      </li>

      <hr class="my-2 border-gray-200">
      @php
         $edmsFile = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('subapplication_id', $app->id)
            ->first();
      @endphp

      <li>

         <a href="/edms/sub/{{ $app->id }}"
            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
            <i data-lucide="folder-open" class="w-4 h-4 text-blue-500"></i>
            <span>Capture Initial DMS</span>
         </a>

      </li>
      <hr class="my-2 border-gray-200">
      @php
         $rofoExists = DB::connection('sqlsrv')
            ->table('rofo')
            ->where('sub_application_id', $app->id)
            ->exists();

         // Check if both approvals are given
         $approvalsGiven = $isBothApproved;
      @endphp

      @if(!$rofoExists && $approvalsGiven)
         <li class="hidden">
            <button type="button" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2"
               onclick="window.location='{{ route('programmes.generate_rofo', $app->id) }}'">
               <i data-lucide="file-plus" class="w-4 h-4 text-purple-500"></i>
               <span class="text-gray-400">Generate RofO/Letter of Grant</span>
            </button>
         </li>
      @else
         <li class="hidden">
            <button type="button"
               class="w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50" disabled>
               <i data-lucide="file-plus" class="w-4 h-4 text-gray-400"></i>
               <span class="text-gray-400">
                  Generate RofO/Letter of Grant
                  @if($rofoExists)

                  @elseif(!$approvalsGiven)

                  @endif
               </span>
            </button>
         </li>
      @endif

      @php
         $cofoExists = DB::connection('sqlsrv')
            ->table('st_cofo')
            ->where('sub_application_id', $app->id)
            ->exists();
      @endphp

      @if(!$cofoExists)
         <li class="hidden">
            <button type="button" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2"
               onclick="window.location='{{ route('programmes.generate_cofo', $app->id) }}'">
               <i data-lucide="file-plus" class="w-4 h-4 text-purple-500"></i>
               <span class="text-gray-400">Generate CofO (FrontPage)</span>
            </button>
         </li>
      @else
         <li class="hidden">
            <button type="button"
               class="w-full text-left px-4 py-2 flex items-center space-x-2 cursor-not-allowed opacity-50" disabled>
               <i data-lucide="file-plus" class="w-4 h-4 text-gray-400"></i>
               <span class="text-gray-400" style="font-size: 65%">Generate CofO (FrontPage)</span>
            </button>
         </li>
      @endif


      @if($rofoExists)
      <hr class="my-2 border-gray-200">
      @php
         $stRofoSerial = DB::connection('sqlsrv')
            ->table('rofo')
            ->where('sub_application_id', $app->id)
            ->value('security_paper_code');
      @endphp
      <li>
         <button type="button"
            class="w-full text-left px-4 py-2 hover:bg-emerald-50 flex items-center space-x-2 {{ $stRofoSerial ? 'text-emerald-600 font-semibold' : 'text-slate-600' }}"
            onclick="openAssignSecurityPaperModal({{ $app->id }}, '{{ addslashes($app->unit_number ?? $app->id) }}', '{{ $stRofoSerial }}', '{{ url('programmes/rofo/assign-security-paper') }}/{{ $app->id }}', { codesApiUrl: '{{ url('security-paper-codes/available') }}', fieldName: 'security_paper_code' })">
            <i data-lucide="hash" class="w-4 h-4"></i>
            <span>{{ $stRofoSerial ? 'Code: '.$stRofoSerial : 'Enter Security Paper Code' }}</span>
         </button>
      </li>
      @if($stRofoSerial)
      <li>
         <button type="button"
            class="w-full text-left px-4 py-2 hover:bg-red-50 flex items-center space-x-2 text-red-600 font-semibold"
            onclick="openResetSecurityPaperModal({{ $app->id }}, @js($app->unit_number ?? $app->id), @js($stRofoSerial), '{{ url('programmes/rofo/reset-security-paper') }}/{{ $app->id }}', { assignEndpoint: '{{ url('programmes/rofo/assign-security-paper') }}/{{ $app->id }}', codesApiUrl: '{{ url('security-paper-codes/available') }}', fieldName: 'security_paper_code' })">
            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
            <span>Reset Security Paper Code</span>
         </button>
      </li>
      @endif
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

   // Global delete handler (guarded to avoid redefinition on repeated includes)
   if (typeof window.deleteSubApplication !== 'function') {
      window.deleteSubApplication = function (id) {
         const tokenMeta = document.querySelector('meta[name="csrf-token"]');
         const csrf = tokenMeta ? tokenMeta.getAttribute('content') : '';

         if (!id) return;

         // Simple confirmation using native confirm to avoid extra deps
         if (!confirm('Are you sure you want to delete this sub-application? This can be undone by an admin.')) {
            return;
         }

         fetch(`/sectionaltitling/sub/${id}`, {
            method: 'DELETE',
            headers: {
               'Content-Type': 'application/json',
               'X-Requested-With': 'XMLHttpRequest',
               'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({})
         })
            .then(async (res) => {
               const data = await res.json().catch(() => ({}));
               if (!res.ok) {
                  throw new Error(data.message || 'Failed to delete');
               }
               return data;
            })
            .then(() => {
               alert('Sub-application deleted successfully.');
               window.location.reload();
            })
            .catch((err) => {
               alert('Error: ' + err.message);
            });
      }
   }

   // Generate Sub Acknowledgement and open print
   if (typeof window.generateSubAcknowledgement !== 'function') {
      window.generateSubAcknowledgement = function (id) {
         const tokenMeta = document.querySelector('meta[name="csrf-token"]');
         const csrf = tokenMeta ? tokenMeta.getAttribute('content') : '{{ csrf_token() }}';
         fetch(`{{ url('/sectionaltitling/sub/acknowledgement/generate') }}/${id}`, {
            method: 'POST',
            headers: {
               'Content-Type': 'application/json',
               'X-Requested-With': 'XMLHttpRequest',
               'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({})
         })
            .then(r => r.json())
            .then(data => {
               if (data && data.success) {
                  window.open(`{{ url('/sectionaltitling/sub/acknowledgement') }}/${id}`, '_blank');
                  setTimeout(() => window.location.reload(), 600);
               } else {
                  alert(data.message || 'Failed to generate acknowledgement');
               }
            })
            .catch(() => alert('Unexpected error generating acknowledgement'));
      }
   }

</script>