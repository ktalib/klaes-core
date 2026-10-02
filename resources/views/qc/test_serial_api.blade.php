<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Serial Number API</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <h1>Serial Number API Test</h1>
    
    <div>
        <label>File Indexing ID:</label>
        <input type="number" id="fileId" value="1" placeholder="Enter file indexing ID">
        <button onclick="testAPI()">Test API</button>
    </div>
    
    <div id="result" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc;"></div>
    
    <script>
        async function testAPI() {
            const fileId = document.getElementById('fileId').value;
            const resultDiv = document.getElementById('result');
            
            if (!fileId) {
                resultDiv.innerHTML = '<p style="color: red;">Please enter a file indexing ID</p>';
                return;
            }
            
            try {
                resultDiv.innerHTML = '<p>Testing API...</p>';
                
                // Test the file details API
                const response = await fetch(`/pagetyping/api/file-details?file_indexing_id=${fileId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                const data = await response.json();
                
                resultDiv.innerHTML = `
                    <h3>API Response:</h3>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                `;
                
                if (data.success && data.file) {
                    console.log('Next serial from API:', data.file.next_serial);
                    console.log('Next serial formatted:', data.file.next_serial_formatted);
                }
                
            } catch (error) {
                resultDiv.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
                console.error('API Test Error:', error);
            }
        }
    </script>
</body>
</html>