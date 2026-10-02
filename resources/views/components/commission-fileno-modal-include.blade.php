{{-- 
    Commission New File Number Modal - Global Component
    
    USAGE:
    1. Include this component in any Blade view:
       @include('components.commission-fileno-modal-include')
    
    2. Add a trigger button anywhere in your page:
       <button onclick="openCommissionFileNoModal()">Commission New File</button>
    
    3. Optional: Define a callback for after successful generation:
       <script>
           window.commissionFileNoSuccessCallback = function(response) {
               console.log('File generated:', response);
               // Refresh your table/data here
           };
       </script>
--}}

@php
    // Fetch required data for the modal
    $modalLgas = DB::connection('sqlsrv')->table('lgas')->select('name')->orderBy('name')->get();
    $modalLandUses = \App\Models\LandUse::all();
    $modalAllPrefixes = \App\Models\Prefix::select('id', 'prefix', 'land_use_id')->get();
    $modalUnallocatedEntries = \App\Models\AllocationListEntry::where('is_allocated', 0)->orderBy('first_name')->get();
    
    // Commission sheets for lookup
    $commissioningSheetsRaw = \DB::connection('sqlsrv')
        ->table('file_commissioning_sheets')
        ->select('id', 'file_number')
        ->get();
    $modalCommissioningSheets = [];
    foreach ($commissioningSheetsRaw as $sheet) {
        $key = strtoupper(trim($sheet->file_number));
        $modalCommissioningSheets[$key] = [
            'id' => $sheet->id,
            'file_number' => $sheet->file_number
        ];
    }
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 42px !important;
            display: flex;
            align-items: center;
            border-color: #d1d5db !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
        }
    </style>
@endpush

{{-- Pass data to JavaScript --}}
<script>
    window.modalCommissioningSheetsMap = @json($modalCommissioningSheets);
    window.modalLandUsesData = @json($modalLandUses);
    window.modalPrefixesData = @json($modalAllPrefixes);
    window.modalUnallocatedEntriesData = @json($modalUnallocatedEntries);
</script>

@include('partials.maps_scripts')

{{-- Include the modal HTML from the original file (lines 285-950) --}}
@include('components.partials.commission-fileno-modal-html', [
    'lgas' => $modalLgas,
    'landUses' => $modalLandUses,
    'allPrefixes' => $modalAllPrefixes,
    'unallocatedEntries' => $modalUnallocatedEntries
])

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    {{-- Include File Number Reservation System --}}
    <script src="{{ asset('js/file-number-reservation.js') }}"></script>
    
    {{-- Include Modal Reservation Integration --}}
    <script src="{{ asset('js/commission-modal-reservation-integration.js') }}"></script>
    
    {{-- Include the JavaScript logic from mls_js.blade.php --}}
    @php
        // Alias variables so mls_js.blade.php can find them
        $landUses = $modalLandUses ?? \App\Models\LandUse::all();
        $allPrefixes = $modalAllPrefixes ?? \App\Models\Prefix::select('id', 'prefix', 'land_use_id')->get();
        $commissioningSheets = $modalCommissioningSheets ?? [];
    @endphp
    @include('generate_fileno.mls_js')
    
    <script>
        /**
         * Global function to open the Commission File Number modal from anywhere
         * WITH RESERVATION SYSTEM INTEGRATED
         */
        window.openCommissionFileNoModal = function() {
            document.getElementById('generateModal').classList.remove('hidden');
            
            // Reinitialize icons after modal is shown
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            // Note: Serial reservation happens automatically when prefix/year changes
            // via the modified updatePreview() function in mls_js
        };
        
        /**
         * Global function to close the modal
         * Releases any active reservations
         */
        window.closeCommissionFileNoModal = async function(modalId = 'generateModal') {
            // Release any active reservations before closing
            if (typeof releaseAllReservations === 'function') {
                await releaseAllReservations();
            }
            
            document.getElementById(modalId).classList.add('hidden');
        };
        
        /**
         * Override the close function name for compatibility
         */
        window.closeGenerateModal = function() {
            window.closeCommissionFileNoModal();
        };
        
        /**
         * Hook into successful submission
         */
        const originalSubmitForm = window.submitForm;
        window.submitForm = function(event) {
            // Call the original submit function
            const result = originalSubmitForm(event);
            
            // If there's a custom callback defined, it will be called from mls_js
            // You can define window.commissionFileNoSuccessCallback in your parent page
            
            return result;
        };
    </script>
@endpush
