<!DOCTYPE html>
<html>
<head>
    <title>ST File Numbers API Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        button { padding: 8px 15px; margin: 5px; cursor: pointer; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .loading { color: #6c757d; font-style: italic; }
    </style>
</head>
<body>
    <h1>🧪 ST File Numbers API Test</h1>
    
    <div class="test-section info">
        <h2>🔧 API Endpoint Tests</h2>
        <p>Use these buttons to test the file number API endpoints directly:</p>
        
        <button onclick="testSUAEndpoint()">Test SUA File Numbers</button>
        <button onclick="testUnitEndpoint()">Test Unit File Numbers</button>
        <button onclick="testGeneralEndpoint()">Test General Search</button>
        <button onclick="clearResults()">Clear Results</button>
    </div>
    
    <div class="test-section">
        <h3>📊 Test Results:</h3>
        <div id="results">
            <p class="loading">Click a test button above to begin...</p>
        </div>
    </div>
    
    <div class="test-section info">
        <h3>🔍 Debug Information:</h3>
        <p><strong>CSRF Token:</strong> <span id="csrf-token-display">Loading...</span></p>
        <p><strong>Base URL:</strong> <span id="base-url"></span></p>
        <p><strong>Current Page:</strong> <span id="current-page"></span></p>
    </div>

    <script>
        // Display debug info
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            document.getElementById('csrf-token-display').textContent = csrfToken ? 'Present' : 'Missing';
            document.getElementById('base-url').textContent = window.location.origin;
            document.getElementById('current-page').textContent = window.location.pathname;
        });

        function displayResult(title, data, isError = false) {
            const resultsDiv = document.getElementById('results');
            const resultSection = document.createElement('div');
            resultSection.className = `test-section ${isError ? 'error' : 'success'}`;
            
            const titleEl = document.createElement('h4');
            titleEl.textContent = title;
            resultSection.appendChild(titleEl);
            
            const preEl = document.createElement('pre');
            preEl.textContent = JSON.stringify(data, null, 2);
            resultSection.appendChild(preEl);
            
            resultsDiv.appendChild(resultSection);
        }

        function clearResults() {
            document.getElementById('results').innerHTML = '<p class="loading">Results cleared. Click a test button to begin...</p>';
        }

        async function testSUAEndpoint() {
            console.log('🧪 Testing SUA endpoint...');
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<p class="loading">Testing SUA file numbers endpoint...</p>';
            
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch('/api/st-file-numbers/search?file_no_type=SUA&status=ACTIVE&per_page=100', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                displayResult(`✅ SUA Endpoint (Status: ${response.status})`, data);
                
            } catch (error) {
                console.error('❌ SUA endpoint error:', error);
                displayResult('❌ SUA Endpoint Error', { error: error.message }, true);
            }
        }

        async function testUnitEndpoint() {
            console.log('🧪 Testing Unit endpoint...');
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<p class="loading">Testing Unit file numbers endpoint...</p>';
            
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch('/api/st-file-numbers/search?file_no_type=PUA&status=ACTIVE&per_page=100', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                displayResult(`✅ Unit Endpoint (Status: ${response.status})`, data);
                
            } catch (error) {
                console.error('❌ Unit endpoint error:', error);
                displayResult('❌ Unit Endpoint Error', { error: error.message }, true);
            }
        }

        async function testGeneralEndpoint() {
            console.log('🧪 Testing general endpoint...');
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<p class="loading">Testing general search endpoint...</p>';
            
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch('/api/st-file-numbers/search?per_page=20', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                displayResult(`✅ General Search (Status: ${response.status})`, data);
                
            } catch (error) {
                console.error('❌ General endpoint error:', error);
                displayResult('❌ General Endpoint Error', { error: error.message }, true);
            }
        }
    </script>
</body>
</html>