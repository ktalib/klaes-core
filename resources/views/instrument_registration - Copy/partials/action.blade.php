<div 
    x-ref="actionMenu"
    x-show="open" 
    x-cloak 
    @click.away="open = false"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="transform opacity-0 scale-95"
    x-transition:enter-end="transform opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="transform opacity-100 scale-100"
    x-transition:leave-end="transform opacity-0 scale-95"
    class="action-menu">

    <!-- Edit Record: enabled for pending, disabled for registered -->
    <a href="{{ $app->status == 'pending' ? route('instrument_registration.edit', $app->id) : '#' }}" 
       @if($app->status != 'pending') onclick="return false;" @endif
       class="block px-4 py-2 text-sm {{ $app->status == 'pending' ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed' }}">
        <i class="fas fa-edit mr-2 {{ $app->status == 'pending' ? 'text-blue-500' : 'text-gray-300' }}"></i>
        Edit Record
    </a>
    <!-- Register Instrument: enabled for pending instruments, with special logic for ST CofO -->
    @php
        $canRegister = false;
        if ($app->status == 'pending') {
            if ($app->instrument_type === 'Sectional Titling CofO') {
                // For ST CofO, check if corresponding ST Assignment is registered
                // This would need to be checked in the controller and passed to the view
                // For now, we'll assume it's not registerable unless explicitly enabled
                $canRegister = false; // This should be dynamically determined
            } else {
                // For all other pending instruments, allow registration
                $canRegister = true;
            }
        }
    @endphp
    <a href="#"
       @if($canRegister) 
           onclick="openSingleRegisterModalWithData('{{ $app->id }}'); return false;" 
           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
       @else 
           onclick="showSTCofoRestrictionMessage(); return false;" 
           class="block px-4 py-2 text-sm text-gray-400 cursor-not-allowed"
       @endif
    >
        <i class="fas fa-file-signature mr-2 {{ $canRegister ? 'text-green-500' : 'text-gray-300' }}"></i>
        Register Instrument
    </a>



    {{-- <!-- Always show View Details for both statuses -->
    <a href="{{ route('instrument_registration.view', $app->id) }}" 
       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
        <i class="fas fa-info-circle mr-2 text-gray-500"></i>
        View Details
    </a> --}}

 <!-- Show View CoR only for registered with STM_Ref, disabled if the instrument_type = 'ST Fragmentation' -->
<!-- Show View CoR only for registered with STM_Ref, disabled if the instrument_type = 'ST Fragmentation' -->
 <!-- Show View CoR - disabled for ST Fragmentation instruments -->
@php
    $isSTFragmentation = ($app->particularsRegistrationNumber === '0/0/0');
    $isRegistered = ($app->status === 'registered');
    $hasSTMRef = !empty($app->STM_Ref);
    $showActiveLink = $isRegistered && $hasSTMRef && !$isSTFragmentation;
@endphp

@if($showActiveLink)
    <a href="{{ route('coroi.index') }}?url=registered_instruments?STM_Ref={{ $app->STM_Ref }}" 
       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
        <i class="fas fa-eye mr-2 text-blue-500"></i>
        View CoR
    </a>
@else
    <a href="#" onclick="return false;" class="block px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
        <i class="fas fa-eye mr-2 text-gray-300"></i>
        View CoR
    </a>
@endif

{{-- Debug info (remove after testing) --}}
@if(config('app.debug'))
    <!-- Debug: Status: {{ $app->status }}, STM_Ref: {{ $app->STM_Ref }}, Type: {{ $app->instrument_type }} -->
@endif

        <!-- Delete Record: enabled for pending, disabled for registered -->
    <a href="#" 
       @if($app->status == 'pending') onclick="deleteInstrument('{{ $app->id }}'); return false;" 
       class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100"
       @else onclick="return false;" 
       class="block px-4 py-2 text-sm text-gray-400 cursor-not-allowed"
       @endif
    >
        <i class="fas fa-trash mr-2 {{ $app->status == 'pending' ? '' : 'text-gray-300' }}"></i>
        Delete Record
    </a>
</div>

<script>
function deleteInstrument(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Send DELETE request
            fetch(`{{ url('instrument_registration/delete') }}/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        'Deleted!',
                        data.message,
                        'success'
                    ).then(() => {
                        // Reload the page to refresh the table
                        location.reload();
                    });
                } else {
                    Swal.fire(
                        'Error!',
                        data.error || 'Failed to delete instrument',
                        'error'
                    );
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire(
                    'Error!',
                    'An error occurred while deleting the instrument',
                    'error'
                );
            });
        }
    });
}

// Function to show ST CofO restriction message
function showSTCofoRestrictionMessage() {
    Swal.fire({
        title: 'Registration Restriction',
        html: `
            <div class="text-left">
                <p class="mb-3"><strong>ST CofO (Sectional Titling Certificate of Occupancy)</strong> cannot be registered directly.</p>
                <p class="mb-3">To register an ST CofO, you must first ensure that the corresponding <strong>ST Assignment (Transfer of Title)</strong> has been registered.</p>
                <div class="bg-blue-50 p-3 rounded-lg mt-4">
                    <p class="text-sm text-blue-800"><i class="fas fa-info-circle mr-2"></i><strong>Registration Process:</strong></p>
                    <ol class="text-sm text-blue-700 mt-2 ml-4">
                        <li>1. Register the ST Assignment (Transfer of Title) first</li>
                        <li>2. Once registered, the ST CofO will become available for registration</li>
                    </ol>
                </div>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'I Understand',
        confirmButtonColor: '#3085d6',
        width: '500px'
    });
}
</script>