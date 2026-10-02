<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Session Lock System - Live Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto py-8">
        <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4">
                <h1 class="text-2xl font-bold text-white">Enhanced Session Lock System - Live Test</h1>
                <p class="text-blue-100">Testing auto-lock (3min) and auto-logout (15min) with database persistence</p>
            </div>
            
            <div class="p-6">
                <!-- User Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-blue-900 mb-2">Current User Session</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-blue-800">Name:</span> 
                            <span class="text-blue-600">{{ Auth::user()->first_name ?? Auth::user()->name }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-blue-800">Email:</span> 
                            <span class="text-blue-600">{{ Auth::user()->email }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-blue-800">Session ID:</span> 
                            <span class="text-blue-600 font-mono text-xs">{{ session()->getId() }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-blue-800">Login Time:</span> 
                            <span class="text-blue-600">{{ session('last_login_time') ? date('H:i:s', session('last_login_time')) : 'Unknown' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Status Panel -->
                <div id="statusPanel" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-green-900 mb-2">Live Session Status</h3>
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-medium text-green-800">Status:</span>
                            <span id="sessionStatus" class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm font-medium">Initializing...</span>
                        </div>
                        <div>
                            <span class="font-medium text-green-800">Last Check:</span>
                            <span id="lastCheck" class="text-green-600 text-sm">--</span>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="font-medium text-green-800">Next Lock Check:</span>
                        <span id="nextCheck" class="text-green-600 text-sm">--</span>
                    </div>
                </div>

                <!-- Feature Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <div class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900">Auto-Lock (3 Minutes)</h4>
                        </div>
                        <p class="text-gray-600 text-sm">Session locks after 3 minutes of inactivity. Beautiful lock screen with password unlock.</p>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900">Auto-Logout (15 Minutes)</h4>
                        </div>
                        <p class="text-gray-600 text-sm">Complete logout after 15 minutes of total inactivity. Redirects to home page.</p>
                    </div>
                </div>

                <!-- Manual Testing -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Manual Testing Controls</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <button id="checkStatusBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                            Check Status Now
                        </button>
                        <button id="updateActivityBtn" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors font-medium">
                            Update Activity
                        </button>
                        <button id="forceLogoutBtn" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors font-medium">
                            Force Logout
                        </button>
                    </div>
                </div>

                <!-- Activity Log -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-4">Activity Log</h3>
                    <div id="activityLog" class="space-y-2 max-h-40 overflow-y-auto text-sm">
                        <div class="text-gray-500">System initializing...</div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="mt-6 text-sm text-gray-600">
                    <h4 class="font-medium text-gray-900 mb-2">Testing Instructions:</h4>
                    <ul class="list-disc list-inside space-y-1">
                        <li>Wait 3 minutes without moving mouse/keyboard - session will lock</li>
                        <li>Refresh the page while locked - lock screen should persist</li>
                        <li>Enter your password to unlock</li>
                        <li>Wait 15 minutes total - session will logout completely</li>
                        <li>Try opening in multiple tabs to test consistency</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the header which contains the session lock system -->
    @include('admin.header')

    <script>
        // Activity logging
        function logActivity(message, type = 'info') {
            const log = document.getElementById('activityLog');
            const timestamp = new Date().toLocaleTimeString();
            const div = document.createElement('div');
            div.className = `text-${type === 'error' ? 'red' : type === 'success' ? 'green' : 'blue'}-600`;
            div.textContent = `[${timestamp}] ${message}`;
            log.insertBefore(div, log.firstChild);
            
            // Keep only last 20 entries
            while (log.children.length > 20) {
                log.removeChild(log.lastChild);
            }
        }

        // Update status display
        function updateStatus(status, isError = false) {
            const statusElement = document.getElementById('sessionStatus');
            const statusPanel = document.getElementById('statusPanel');
            const lastCheckElement = document.getElementById('lastCheck');
            
            statusElement.textContent = status;
            lastCheckElement.textContent = new Date().toLocaleTimeString();
            
            // Update panel color based on status
            if (isError) {
                statusPanel.className = 'bg-red-50 border border-red-200 rounded-lg p-4 mb-6';
                statusElement.className = 'px-2 py-1 bg-red-100 text-red-800 rounded text-sm font-medium';
            } else if (status.toLowerCase().includes('locked')) {
                statusPanel.className = 'bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6';
                statusElement.className = 'px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-sm font-medium';
            } else {
                statusPanel.className = 'bg-green-50 border border-green-200 rounded-lg p-4 mb-6';
                statusElement.className = 'px-2 py-1 bg-green-100 text-green-800 rounded text-sm font-medium';
            }
        }

        // Manual test buttons
        document.getElementById('checkStatusBtn').addEventListener('click', async () => {
            try {
                const response = await fetch('/session-lock/check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                const data = await response.json();
                updateStatus(data.status || 'Unknown');
                logActivity(`Manual status check: ${data.status}`, 'info');
            } catch (error) {
                updateStatus('Error', true);
                logActivity(`Status check failed: ${error.message}`, 'error');
            }
        });

        document.getElementById('updateActivityBtn').addEventListener('click', async () => {
            try {
                const response = await fetch('/session-lock/update-activity', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                const data = await response.json();
                logActivity('Activity updated manually', 'success');
            } catch (error) {
                logActivity(`Activity update failed: ${error.message}`, 'error');
            }
        });

        document.getElementById('forceLogoutBtn').addEventListener('click', async () => {
            if (confirm('This will immediately logout and redirect to home page. Continue?')) {
                try {
                    await fetch('/session-lock/force-logout', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });
                    
                    logActivity('Force logout initiated', 'info');
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 1000);
                } catch (error) {
                    logActivity(`Logout failed: ${error.message}`, 'error');
                }
            }
        });

        // Initialize
        logActivity('Session lock test page loaded');
        logActivity('Enhanced session management active');
        
        // Update next check countdown
        let checkInterval = 30;
        setInterval(() => {
            checkInterval--;
            if (checkInterval <= 0) {
                checkInterval = 30;
                document.getElementById('nextCheck').textContent = 'Checking now...';
            } else {
                document.getElementById('nextCheck').textContent = `${checkInterval}s`;
            }
        }, 1000);
    </script>
</body>
</html>