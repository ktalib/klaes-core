<!-- Primary File Selection Section -->
<div class="bg-gray-50 p-4 rounded-lg mb-4">
    
    <div class="grid grid-cols-3 gap-4">
        <div class="space-y-2">
            <label for="primary_file_select" class="block text-sm font-medium text-gray-700">Select Primary GIS FileNo</label>
            <select id="primary_file_select" class="w-full p-2 border border-gray-300 rounded-md   select2-searhc">
                <option value="">Select Primary GIS FileNo</option>
                 
            </select>
            <div class="text-xs text-gray-500">Search by MLSF No, KANGIS File No, or New KANGIS File No</div>
        </div>

        <!-- Mother Application Selection -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Mother FileNo</label>
            <select id="motherApplication" name="main_application_id" class="w-full p-2 border border-gray-300 rounded-md">
                <option value="">Select Mother Application</option>
                @php
                    // Fetch mother applications from the database
                    $motherApplications = DB::connection('sqlsrv')
                        ->table('mother_applications')
                        ->select('id', 'fileno')
                        ->orderBy('fileno')
                        ->get();
                @endphp
                
                @foreach($motherApplications as $app)
                    <option value="{{ $app->id }}">{{ $app->fileno }}</option>
                @endforeach
            </select>
        </div>

        <!-- Unit File Number Selection -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Unit FileNo</label>
            <select id="unitFileNo" name="STFileNo" class="w-full p-2 border border-gray-300 rounded-md" disabled>
                <option value="">Select Unit File Number</option>
            </select>
        </div>

     
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Original file number generation code
        const prefixSelect = document.getElementById('filePrefix');
        const yearField = document.getElementById('fileYear');
        const serialField = document.getElementById('serialNumber');
        const fileNumberPreview = document.getElementById('fileNumberPreview');
        const filenoField = document.getElementById('fileno');
        
        // Mother application and unit selection elements
        const motherSelect = document.getElementById('motherApplication');
        const unitSelect = document.getElementById('unitFileNo');
        
        // Unit information preview fields
        const schemeNoPreview = document.getElementById('scheme_no_preview');
        const schemeNoField = document.getElementById('scheme_no');
        const sectionNoPreview = document.getElementById('section_no_preview');
        const sectionNoField = document.getElementById('section_no');
        const blockNoPreview = document.getElementById('block_no_preview');
        const blockNoField = document.getElementById('block_no');
        const unitNoPreview = document.getElementById('unit_no_preview');
        const unitNoField = document.getElementById('unit_no');
        
        // When mother application changes, load the unit file numbers
        motherSelect.addEventListener('change', function() {
            const motherId = this.value;
            const motherText = this.options[this.selectedIndex].text;
            
            // Reset and disable the unit select if no mother application is selected
            if (!motherId) {
                unitSelect.innerHTML = '<option value="">Select Unit File Number</option>';
                unitSelect.disabled = true;
                
                // Hide summary if no mother app is selected
                const summaryContainer = document.getElementById('file-summary-container');
                if (summaryContainer) summaryContainer.classList.add('hidden');
                return;
            }
            
            // Update mother app in summary
            updateMotherAppSummary(motherId, motherText);
            
            // Enable the unit select
            unitSelect.disabled = false;
            
            // Fetch the units for this mother application
            fetch(`{{ route('gis_record.get-units') }}?mother_id=${motherId}`)
                .then(response => response.json())
                .then(data => {
                    // Clear current options
                    unitSelect.innerHTML = '<option value="">Select Unit File Number</option>';
                    
                    // Add the new options
                    data.forEach(unit => {
                        const option = document.createElement('option');
                        option.value = unit.id;
                        option.textContent = unit.fileno;
                        option.dataset.scheme = unit.scheme_no;
                        option.dataset.section = unit.floor_number;
                        option.dataset.block = unit.block_number;
                        option.dataset.unit = unit.unit_number;
                        option.dataset.landuse = unit.land_use; // Add land_use data attribute
                        option.dataset.unitid = unit.unit_id;   // Add unit_id data attribute
                        unitSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading units:', error);
                });
        });
        
        // When unit file number changes, update the unit information preview
        unitSelect.addEventListener('change', function() {
            if (!this.value) {
                // Clear preview fields
                schemeNoPreview.value = '';
                schemeNoField.value = '';
                sectionNoPreview.value = '';
                sectionNoField.value = '';
                blockNoPreview.value = '';
                blockNoField.value = '';
                unitNoPreview.value = '';
                unitNoField.value = '';
                
                // Clear application ID, land use, and unit ID fields if they exist
                if (document.getElementById('app_id')) document.getElementById('app_id').value = '';
                if (document.getElementById('landuse')) document.getElementById('landuse').value = '';
                if (document.getElementById('unit_id')) document.getElementById('unit_id').value = '';
                
                // Hide unit summary
                const unitSection = document.getElementById('unit-file-summary');
                if (unitSection) unitSection.classList.add('hidden');
                return;
            }
            
            // Get the selected option
            const selectedOption = this.options[this.selectedIndex];
            
            // Update unit summary
            updateUnitFileSummary(selectedOption);
            
            // Fill the preview fields
            schemeNoPreview.value = selectedOption.dataset.scheme;
            schemeNoField.value = selectedOption.dataset.scheme;
            sectionNoPreview.value = selectedOption.dataset.section;
            sectionNoField.value = selectedOption.dataset.section;
            blockNoPreview.value = selectedOption.dataset.block;
            blockNoField.value = selectedOption.dataset.block;
            unitNoPreview.value = selectedOption.dataset.unit;
            unitNoField.value = selectedOption.dataset.unit;
            
            // Also update the STFileNo value to be the unit's file number
            document.getElementById('STFileNo').value = selectedOption.textContent;
            
            // Set additional fields if they exist
            const appId = this.value; // The selected option value is the subapplication id
            if (document.getElementById('app_id')) document.getElementById('app_id').value = appId;
            
            // Land use and unit ID need special handling
            if (document.getElementById('landuse')) {
                document.getElementById('landuse').value = selectedOption.dataset.landuse || '';
                console.log('Setting landuse to:', selectedOption.dataset.landuse);
            }
            
            if (document.getElementById('unit_id')) {
                document.getElementById('unit_id').value = selectedOption.dataset.unitid || '';
                console.log('Setting unit_id to:', selectedOption.dataset.unitid);
            }
        });
        
        // Function to update mother app summary
        function updateMotherAppSummary(id, fileName) {
            // Show the container
            const summaryContainer = document.getElementById('file-summary-container');
            if (summaryContainer) summaryContainer.classList.remove('hidden');
            
            // Update title
            const summaryTitle = document.getElementById('summary-title');
            if (summaryTitle) summaryTitle.textContent = 'Summary';
            
            // Show mother section, hide others
            const primarySection = document.getElementById('primary-file-summary');
            const motherSection = document.getElementById('mother-app-summary');
            const unitSection = document.getElementById('unit-file-summary');
            
            if (primarySection) primarySection.classList.add('hidden');
            if (motherSection) motherSection.classList.remove('hidden');
            if (unitSection) unitSection.classList.add('hidden');
            
            // Update mother app details
            if (document.getElementById('summary-motherAppFileNo')) 
                document.getElementById('summary-motherAppFileNo').textContent = fileName || '-';
            if (document.getElementById('summary-motherAppId')) 
                document.getElementById('summary-motherAppId').textContent = id || '-';
            
            // Update note
            if (document.getElementById('summary-note'))
                document.getElementById('summary-note').textContent = 'Please select a unit file number to continue.';
        }
        
        // Function to update unit file summary
        function updateUnitFileSummary(selectedOption) {
            // Show unit section
            const unitSection = document.getElementById('unit-file-summary');
            if (unitSection) unitSection.classList.remove('hidden');
            
            // Update title
            const summaryTitle = document.getElementById('summary-title');
            if (summaryTitle) summaryTitle.textContent = 'Summary';
            
            // Update unit file details
            if (document.getElementById('summary-unitFileNo')) 
                document.getElementById('summary-unitFileNo').textContent = selectedOption.textContent || '-';
            if (document.getElementById('summary-schemeNo')) 
                document.getElementById('summary-schemeNo').textContent = selectedOption.dataset.scheme || '-';
            if (document.getElementById('summary-sectionNo')) 
                document.getElementById('summary-sectionNo').textContent = selectedOption.dataset.section || '-';
            if (document.getElementById('summary-blockNo')) 
                document.getElementById('summary-blockNo').textContent = selectedOption.dataset.block || '-';
            if (document.getElementById('summary-unitNo')) 
                document.getElementById('summary-unitNo').textContent = selectedOption.dataset.unit || '-';
            if (document.getElementById('summary-unitLandUse')) 
                document.getElementById('summary-unitLandUse').textContent = selectedOption.dataset.landuse || '-';
            
            // Update note
            if (document.getElementById('summary-note'))
                document.getElementById('summary-note').textContent = 'Unit file data has been loaded into the form below.';
        }
        
        // Link the land use dropdown to automatically set the file prefix
        const landUseSelect = document.getElementById('landUse');
        if (landUseSelect) {
            landUseSelect.addEventListener('change', function() {
                switch(this.value) {
                    case 'Residential':
                        prefixSelect.value = 'ST-RES';
                        break;
                    case 'Commercial':
                        prefixSelect.value = 'ST-COM';
                        break;
                    case 'Industrial':
                        prefixSelect.value = 'ST-IND';
                        break;
                    default:
                        prefixSelect.value = 'ST-RES'; // Default to residential
                }
                updateFileNumber();
            });
        }
        
        // Update the file number when prefix changes
        prefixSelect.addEventListener('change', updateFileNumber);
        
        // Update the file number when serial number changes
        serialField.addEventListener('input', updateFileNumber);
        
        // Set initial file number
        updateFileNumber();
        
        function updateFileNumber() {
            const prefix = prefixSelect.value;
            const year = yearField.value;
            const serial = serialField.value;
            
            const fullFileNumber = `${prefix}-${year}-${serial}`;
            fileNumberPreview.value = fullFileNumber;
            filenoField.value = fullFileNumber; // Set the actual database field
        }
    });
</script>
