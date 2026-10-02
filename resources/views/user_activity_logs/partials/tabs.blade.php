<!-- Modern Tab Navigation -->
<div class="mb-6 mt-4">
    <!-- Tab Navigation -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <nav class="flex border-b border-gray-100 bg-white px-2 py-2 gap-2" aria-label="Tabs">
            <!-- Activity Logs Tab -->
            <button 
                onclick="switchTab('activity-logs')" 
                id="activity-logs-tab" 
                class="tab-button flex-1 px-6 py-3 border-b-2 border-transparent text-gray-600 hover:text-blue-600 font-bold text-sm relative overflow-hidden transition-all duration-300 active rounded-lg hover:bg-blue-50">
                <div class="flex items-center justify-center space-x-2">
                    <div class="p-1.5 rounded-lg bg-gray-100 transition-all duration-200">
                        <i class="fas fa-list text-gray-600 transition-colors duration-200"></i>
                    </div>
                    <span>Activity Logs</span>
                </div>
                <div class="absolute bottom-0 left-0 w-0 h-1 bg-gradient-to-r from-blue-600 to-blue-400 transition-all duration-300 rounded-full"></div>
            </button>
            
            <!-- Online Users Tab -->
            <button 
                onclick="switchTab('online-users')" 
                id="online-users-tab" 
                class="tab-button flex-1 px-6 py-3 border-b-2 border-transparent text-gray-600 hover:text-emerald-600 font-bold text-sm relative overflow-hidden transition-all duration-300 rounded-lg hover:bg-emerald-50">
                <div class="flex items-center justify-center space-x-2">
                    <div class="p-1.5 rounded-lg bg-gray-100 transition-all duration-200">
                        <i class="fas fa-users text-gray-600 transition-colors duration-200"></i>
                    </div>
                    <span>Online Users</span>
                    <span class="ml-1 bg-gradient-to-r from-emerald-400 to-emerald-500 text-white text-xs font-bold px-2.5 py-0.5 rounded-full shadow-sm transform hover:scale-110 transition-transform duration-200" id="online-count-badge">{{ count($onlineUsers) }}</span>
                </div>
                <div class="absolute bottom-0 left-0 w-0 h-1 bg-gradient-to-r from-emerald-600 to-emerald-400 transition-all duration-300 rounded-full"></div>
            </button>
            
            <!-- Security Tab -->
            <button 
                onclick="switchTab('security')" 
                id="security-tab" 
                class="tab-button flex-1 px-6 py-3 border-b-2 border-transparent text-gray-600 hover:text-rose-600 font-bold text-sm relative overflow-hidden transition-all duration-300 rounded-lg hover:bg-rose-50">
                <div class="flex items-center justify-center space-x-2">
                    <div class="p-1.5 rounded-lg bg-gray-100 transition-all duration-200">
                        <i class="fas fa-shield-alt text-gray-600 transition-colors duration-200"></i>
                    </div>
                    <span>Security</span>
                </div>
                <div class="absolute bottom-0 left-0 w-0 h-1 bg-gradient-to-r from-rose-600 to-rose-400 transition-all duration-300 rounded-full"></div>
            </button>
        </nav>
    </div>
    
    <!-- Tab Info Card -->
    <div class="mt-4 p-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-200 flex items-start space-x-3 shadow-sm">
        <div class="flex-shrink-0 pt-0.5">
            <div class="flex items-center justify-center w-8 h-8 bg-blue-600 rounded-lg">
                <i class="fas fa-lightbulb text-white text-sm"></i>
            </div>
        </div>
        <div class="flex-1">
            <div id="tab-info-content" class="text-sm text-gray-700">
                <p><span class="font-semibold text-gray-900">Activity Logs:</span> View and analyze all user login activities, sessions, and system interactions in real-time.</p>
            </div>
        </div>
    </div>
</div>

<!-- Tab Info Messages & Underline Animation Script -->
<script>
    const tabInfoMessages = {
        'activity-logs': '<span class="font-semibold text-gray-900">Activity Logs:</span> View and analyze all user login activities, sessions, and system interactions in real-time.',
        'online-users': '<span class="font-semibold text-gray-900">Online Users:</span> Monitor currently active sessions and take immediate action when needed. Click logout to end user sessions.',
        'security': '<span class="font-semibold text-gray-900">Security:</span> Track suspicious activities, analyze IP addresses, and configure security settings to protect your system.'
    };
    
    window.updateTabInfo = function(tabName) {
        const infoContent = document.getElementById('tab-info-content');
        if (infoContent && tabInfoMessages[tabName]) {
            infoContent.innerHTML = tabInfoMessages[tabName];
        }
    };
    
    // Initialize tab underlines on page load
    window.initializeTabUnderlines = function() {
        // Animate active tab underline
        const activeTab = document.getElementById('activity-logs-tab');
        if (activeTab) {
            const underline = activeTab.querySelector('div[class*="bg-gradient-to-r"]');
            if (underline) {
                underline.style.width = '100%';
            }
        }
    };
    
    // Animate underline when switching tabs
    window.animateTabUnderline = function(tabName) {
        // Reset all underlines
        document.querySelectorAll('.tab-button').forEach(tab => {
            const underline = tab.querySelector('div[class*="bg-gradient-to-r"]');
            if (underline) {
                underline.style.width = '0%';
            }
        });
        
        // Animate new active underline
        const activeTab = document.getElementById(tabName + '-tab');
        if (activeTab) {
            const underline = activeTab.querySelector('div[class*="bg-gradient-to-r"]');
            if (underline) {
                setTimeout(() => {
                    underline.style.width = '100%';
                }, 10);
            }
        }
    };
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', initializeTabUnderlines);
</script>

<style>
    .tab-button div[class*="bg-gradient-to-r"] {
        width: 0;
        transition: width 0.3s ease;
    }
    
    .tab-button.active div[class*="bg-gradient-to-r"] {
        width: 100%;
    }
</style>