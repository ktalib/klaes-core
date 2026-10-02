{{-- SUA Action Dropdown Menu Component --}}
<div class="relative" x-data="{ 
    open: false,
    toggleDropdown() {
        this.open = !this.open;
        if (this.open) {
            this.$nextTick(() => {
                this.positionDropdown();
            });
        }
    },
    positionDropdown() {
        const button = this.$refs.button;
        const dropdown = this.$refs.dropdown;
        const rect = button.getBoundingClientRect();
        
        // Position dropdown relative to viewport
        dropdown.style.position = 'fixed';
        dropdown.style.top = (rect.bottom + 5) + 'px';
        dropdown.style.right = (window.innerWidth - rect.right) + 'px';
        dropdown.style.zIndex = '99999';
        
        // Check if dropdown goes below viewport
        const dropdownRect = dropdown.getBoundingClientRect();
        if (dropdownRect.bottom > window.innerHeight - 10) {
            // Position above the button instead
            dropdown.style.top = (rect.top - dropdown.offsetHeight - 5) + 'px';
        }
    }
}">
    <button @click="toggleDropdown()" @click.away="open = false" 
            x-ref="button"
            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
        <span class="sr-only">Open actions menu</span>
    </button>
    
    <!-- Dropdown Menu -->
    <div x-show="open" 
         x-ref="dropdown"
         x-cloak
         style="display: none;"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="transform opacity-0 scale-95"
         x-transition:enter-end="transform opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="transform opacity-100 scale-100"
         x-transition:leave-end="transform opacity-0 scale-95"
         class="w-48 rounded-md shadow-xl bg-white ring-1 ring-black ring-opacity-5 origin-top-right"
         style="position: fixed; z-index: 99999;">
        <div class="py-1" role="menu">
            @php
                $subAckExists = DB::connection('sqlsrv')->table('dbo.st_acknowledgement_tracking')
                    ->where('sub_application_id', $sua->id)
                    ->exists();
            @endphp

            <!-- Generate Acknowledgement Sheet (SUA) -->
            <button type="button"
                class="group flex items-center w-full px-4 py-2 text-xs {{ $subAckExists ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}"
                @if(!$subAckExists) onclick="generateSubAcknowledgement({{ $sua->id }})" @endif
                {{ $subAckExists ? 'disabled' : '' }}>
                <i data-lucide="file-plus" class="mr-3 h-4 w-4 {{ $subAckExists ? 'text-gray-300' : 'text-purple-500 group-hover:text-purple-600' }}"></i>
                <span class="text-left">
                Generate Acknowledgement<br>Sheet
                </span>
            </button>

            <!-- View Acknowledgement Sheet (SUA) -->
            <a href="{{ $subAckExists ? route('sectionaltitling.sub.acknowledgement', ['id' => $sua->id]) : '#' }}"
               target="_blank"
               class="group flex items-center px-4 py-2 text-xs {{ $subAckExists ? 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' : 'text-gray-400 cursor-not-allowed' }}">
                <i data-lucide="printer" class="mr-3 h-4 w-4 {{ $subAckExists ? 'text-indigo-600' : 'text-gray-300' }}"></i>
                <span class="text-left">
                View Acknowledgement<br>Sheet
                </span>
            </a>

            
            {{-- View Application --}}
         <a href="{{ route('sua.show', $sua->id) }}" 
               class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                <i data-lucide="eye" class="mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-500"></i>
                View Application
            </a>  
            
            {{-- Edit Application --}}
            @php
                $isApproved = $sua->application_status === 'Approved' || 
                              $sua->planning_recommendation_status === 'Approved';
            @endphp

            <a href="{{ url('/sectionaltitling/edit_sub/' . $sua->id) }}" 
               class="group flex items-center px-4 py-2 text-sm {{ $isApproved ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}"
               @if($isApproved) tabindex="-1" aria-disabled="true" @endif
               {{ $isApproved ? 'onclick="return false;"' : '' }}>
                <i data-lucide="edit" class="mr-3 h-4 w-4 {{ $isApproved ? 'text-gray-300' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                Edit Application
            </a>  

            {{-- Delete Record --}}
            <form action="{{ route('sua.destroy', $sua->id) }}" method="POST" 
                  class="block" 
                  onsubmit="return {{ $isApproved ? 'false' : 'confirm(\'Are you sure you want to delete this record? This action cannot be undone.\')' }}">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="group flex items-center w-full px-4 py-2 text-sm {{ $isApproved ? 'text-gray-400 cursor-not-allowed' : 'text-red-700 hover:bg-red-50 hover:text-red-900' }}"
                        {{ $isApproved ? 'disabled' : '' }}>
                    <i data-lucide="trash-2" class="mr-3 h-4 w-4 {{ $isApproved ? 'text-gray-300' : 'text-red-400 group-hover:text-red-500' }}"></i>
                    Delete Record
                </button>
            </form>
            
            {{-- Update EDMS --}}
            <a href="/edms/sub/{{ $sua->id }}" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                <i data-lucide="database" class="mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-500"></i>
               Capture Initial DMS
            </a>
        </div>
    </div>
</div>

<script>
// Generate Sub Acknowledgement and open print (shared)
if (typeof window.generateSubAcknowledgement !== 'function') {
    window.generateSubAcknowledgement = function(id) {
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
// Update EDMS function
function updateEDMS(id) {
    if (confirm('Are you sure you want to update EDMS for this record?')) {
        // Implement EDMS update functionality
        alert('EDMS update functionality will be implemented here.');
    }
}
</script>
