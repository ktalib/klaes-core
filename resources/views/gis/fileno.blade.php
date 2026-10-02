<!-- GIS File Number System -->
<div class="bg-gray-50 p-4 rounded-lg mb-6">
    <h3 class="text-lg font-semibold mb-4 text-gray-700">File Information</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Prefix Selection -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">File Prefix</label>
            <select id="filePrefix" name="file_prefix" class="w-full p-2 border border-gray-300 rounded-md">
                <option value="ST-RES">ST-RES (Residential)</option>
                <option value="ST-COM">ST-COM (Commercial)</option>
                <option value="ST-IND">ST-IND (Industrial)</option>
            </select>
        </div>

        <!-- Year Selection (Current Year) -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Year</label>
            <input type="text" id="fileYear" class="w-full p-2 border border-gray-300 rounded-md bg-gray-100" 
                value="{{ date('Y') }}" readonly>
            <!-- Hidden input for Year -->
            <input type="hidden" id="fileYearHidden" name="file_year" value="{{ date('Y') }}">
        </div>

        <!-- Serial Number -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Serial Number</label>
            <?php
                // This would typically come from a database, but for now we'll use a placeholder
                // In a real implementation, you'd fetch the next available serial number for the selected prefix
                $serialNumber = '00001';
            ?>
            <input type="text" id="serialNumber" name="serial_number" class="w-full p-2 border border-gray-300 rounded-md"
                value="{{ $serialNumber }}">
            <!-- Hidden input removed since we're using the actual input field directly -->
        </div>
    </div>

    <!-- Full File Number Preview -->
    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Full File Number</label>
        <input type="text" id="fileNumberPreview" class="w-full p-2 border border-gray-300 rounded-md bg-gray-100" readonly>
        <input type="hidden" id="hiddenFileNumber" name="fileno">
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const prefixSelect = document.getElementById('filePrefix');
        const yearField = document.getElementById('fileYear');
        const serialField = document.getElementById('serialNumber');
        const fileNumberPreview = document.getElementById('fileNumberPreview');
        const hiddenFileNumber = document.getElementById('hiddenFileNumber');
        
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
            hiddenFileNumber.value = fullFileNumber;
        }
    });
</script>
