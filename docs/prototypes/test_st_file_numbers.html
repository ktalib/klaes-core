<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ST File Number Service Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">ST File Number Service Test</h1>
        
        <!-- Primary File Number Test -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Primary File Number Test</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <select id="primary-landuse" class="p-2 border rounded">
                    <option value="Residential">Residential</option>
                    <option value="Commercial">Commercial</option>
                    <option value="Industrial">Industrial</option>
                    <option value="Mixed">Mixed</option>
                </select>
                <button onclick="testPrimaryGeneration()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Generate Primary
                </button>
                <input type="text" id="primary-result" readonly class="p-2 border rounded bg-gray-100" placeholder="Generated file number">
            </div>
        </div>
        
        <!-- SUA File Number Test -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">SUA File Number Test</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <select id="sua-landuse" class="p-2 border rounded">
                    <option value="Residential">Residential</option>
                    <option value="Commercial">Commercial</option>
                    <option value="Industrial">Industrial</option>
                    <option value="Mixed">Mixed</option>
                </select>
                <button onclick="testSUAGeneration()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    Generate SUA
                </button>
                <div class="space-y-2">
                    <input type="text" id="sua-primary-result" readonly class="p-2 border rounded bg-gray-100 w-full" placeholder="Primary file number">
                    <input type="text" id="sua-unit-result" readonly class="p-2 border rounded bg-gray-100 w-full" placeholder="Unit file number">
                </div>
            </div>
        </div>
        
        <!-- PUA File Number Test -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">PUA File Number Test</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <input type="text" id="pua-parent" class="p-2 border rounded" placeholder="Parent file number (e.g., ST-RES-2025-1)">
                <button onclick="testPUAGeneration()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                    Generate PUA
                </button>
                <div class="space-y-2">
                    <input type="text" id="pua-np-result" readonly class="p-2 border rounded bg-gray-100 w-full" placeholder="NP file number">
                    <input type="text" id="pua-unit-result" readonly class="p-2 border rounded bg-gray-100 w-full" placeholder="Unit file number">
                </div>
            </div>
        </div>
        
        <!-- Database Status -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Database Status</h2>
            <button onclick="checkDatabaseStatus()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 mb-4">
                Check Database
            </button>
            <div id="db-status" class="text-sm"></div>
        </div>
    </div>

    <script>
        async function testPrimaryGeneration() {
            const landuse = document.getElementById('primary-landuse').value;
            const resultField = document.getElementById('primary-result');
            
            try {
                resultField.value = 'Generating...';
                
                const response = await fetch('/api/st-file-numbers/reserve-primary', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        land_use: landuse,
                        applicant_type: 'Individual',
                        first_name: 'Test',
                        surname: 'User'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultField.value = data.data.np_fileno;
                    Swal.fire('Success!', 'Primary file number generated successfully', 'success');
                } else {
                    resultField.value = 'Error';
                    Swal.fire('Error!', data.message, 'error');
                }
            } catch (error) {
                resultField.value = 'Error';
                Swal.fire('Error!', error.message, 'error');
            }
        }
        
        async function testSUAGeneration() {
            const landuse = document.getElementById('sua-landuse').value;
            const primaryResult = document.getElementById('sua-primary-result');
            const unitResult = document.getElementById('sua-unit-result');
            
            try {
                primaryResult.value = 'Generating...';
                unitResult.value = 'Generating...';
                
                const response = await fetch('/api/st-file-numbers/reserve-sua', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        land_use: landuse,
                        applicant_type: 'Individual',
                        first_name: 'Test',
                        surname: 'User'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    primaryResult.value = data.data.np_fileno;
                    unitResult.value = data.data.unit_fileno;
                    Swal.fire('Success!', 'SUA file numbers generated successfully', 'success');
                } else {
                    primaryResult.value = 'Error';
                    unitResult.value = 'Error';
                    Swal.fire('Error!', data.message, 'error');
                }
            } catch (error) {
                primaryResult.value = 'Error';
                unitResult.value = 'Error';
                Swal.fire('Error!', error.message, 'error');
            }
        }
        
        async function testPUAGeneration() {
            const parentFileNo = document.getElementById('pua-parent').value;
            const npResult = document.getElementById('pua-np-result');
            const unitResult = document.getElementById('pua-unit-result');
            
            if (!parentFileNo) {
                Swal.fire('Error!', 'Please enter a parent file number', 'error');
                return;
            }
            
            try {
                npResult.value = 'Generating...';
                unitResult.value = 'Generating...';
                
                const response = await fetch('/api/st-file-numbers/reserve-pua', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        parent_file_number: parentFileNo,
                        applicant_type: 'Individual',
                        first_name: 'Test',
                        surname: 'User'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    npResult.value = data.data.np_fileno;
                    unitResult.value = data.data.unit_fileno;
                    Swal.fire('Success!', 'PUA file number generated successfully', 'success');
                } else {
                    npResult.value = 'Error';
                    unitResult.value = 'Error';
                    Swal.fire('Error!', data.message, 'error');
                }
            } catch (error) {
                npResult.value = 'Error';
                unitResult.value = 'Error';
                Swal.fire('Error!', error.message, 'error');
            }
        }
        
        async function checkDatabaseStatus() {
            const statusDiv = document.getElementById('db-status');
            
            try {
                statusDiv.innerHTML = 'Checking...';
                
                const response = await fetch('/api/st-file-numbers/preview', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        land_use: 'Residential',
                        type: 'PRIMARY'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    statusDiv.innerHTML = `
                        <div class="text-green-600">Database connection: OK</div>
                        <div>Next Residential file number would be: ${data.data.preview_file_number}</div>
                        <div>Serial number: ${data.data.serial_no}</div>
                    `;
                } else {
                    statusDiv.innerHTML = `<div class="text-red-600">Database error: ${data.message}</div>`;
                }
            } catch (error) {
                statusDiv.innerHTML = `<div class="text-red-600">Connection error: ${error.message}</div>`;
            }
        }
    </script>
</body>
</html>