<!-- Debug JavaScript -->
<script>
console.log("=== DEBUG: File loading test ===");

// Test if we can access the DOM elements
document.addEventListener('DOMContentLoaded', function() {
    console.log("=== DEBUG: DOM loaded ===");
    
    // Test if we can find the key elements
    const startAiIndexingBtn = document.getElementById('start-ai-indexing-btn');
    const aiInsightsContainer = document.getElementById('ai-insights-container');
    const selectedFiles = window.selectedFiles || [];
    const pendingFiles = window.pendingFiles || [];
    
    console.log("Start AI Indexing Button:", startAiIndexingBtn);
    console.log("AI Insights Container:", aiInsightsContainer);
    console.log("Selected Files:", selectedFiles);
    console.log("Pending Files:", pendingFiles);
    
    // Add a click handler to test
    if (startAiIndexingBtn) {
        startAiIndexingBtn.addEventListener('click', function() {
            console.log("=== DEBUG: Start AI Indexing button clicked ===");
            
            // Show cards immediately without any simulation
            if (aiInsightsContainer) {
                console.log("=== DEBUG: Showing test cards ===");
                aiInsightsContainer.innerHTML = `
                    <div class="flex items-center mb-4">
                        <h4 class="font-medium text-lg">DEBUG: Test Cards Display</h4>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h5 class="font-semibold text-blue-600 text-sm mb-1">RES-2025-24356</h5>
                                    <p class="text-gray-800 font-medium text-sm">OLAKUNLE OLAFEMI OLANIYAN</p>
                                </div>
                                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">File 1</span>
                            </div>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Type:</span>
                                    <span class="text-gray-800">Application</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Land Use:</span>
                                    <span class="text-gray-800">Residential</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">District:</span>
                                    <span class="text-gray-800">FAGGE</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Date:</span>
                                    <span class="text-gray-800">2025-08-30</span>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <div class="flex flex-wrap gap-1">
                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Residential</span>
                                    <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">FAGGE</span>
                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Ready</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h5 class="font-semibold text-blue-600 text-sm mb-1">COM-2025-24357</h5>
                                    <p class="text-gray-800 font-medium text-sm">AISHA MUHAMMAD BELLO</p>
                                </div>
                                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">File 2</span>
                            </div>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Type:</span>
                                    <span class="text-gray-800">Application</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Land Use:</span>
                                    <span class="text-gray-800">Commercial</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">District:</span>
                                    <span class="text-gray-800">MUNICIPAL</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Date:</span>
                                    <span class="text-gray-800">2025-08-29</span>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-100">
                                <div class="flex flex-wrap gap-1">
                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Commercial</span>
                                    <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">MUNICIPAL</span>
                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Ready</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                console.log("=== DEBUG: Cards displayed successfully ===");
            } else {
                console.error("=== DEBUG: AI Insights Container not found ===");
            }
        });
    } else {
        console.error("=== DEBUG: Start AI Indexing button not found ===");
    }
});
</script>