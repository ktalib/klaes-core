<!-- View Recommended Site Plan Sketch Modal -->
<div id="viewRecommendedSitePlanModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                    <i data-lucide="eye" class="w-5 h-5 inline mr-2"></i>
                    View Recommended Site Plan Sketch
                </h3>
                <button onclick="closeViewRecommendedSitePlanModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Content Container -->
            <div id="viewRecommendedSitePlanContent">
                <div class="text-center py-8">
                    <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-4 text-blue-500"></i>
                    <p class="text-gray-600">Loading recommended site plan sketch...</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" 
                        onclick="closeViewRecommendedSitePlanModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function openViewRecommendedSitePlanModal(applicationId) {
        document.getElementById('viewRecommendedSitePlanModal').style.display = 'block';
        
        // Reset content to loading state
        document.getElementById('viewRecommendedSitePlanContent').innerHTML = `
            <div class="text-center py-8">
                <i data-lucide="loader-2" class="w-8 h-8 animate-spin mx-auto mb-4 text-blue-500"></i>
                <p class="text-gray-600">Loading recommended site plan sketch...</p>
            </div>
        `;
        
        // Fetch the recommended site plan data
        fetch(`/stmemo/get-recommended-siteplan/${applicationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.recommendedSitePlan && data.recommendedSitePlan.recommended_file) {
                    const fileUrl = `/storage/${data.recommendedSitePlan.recommended_file}`;
                    const fileName = data.recommendedSitePlan.recommended_file.split('/').pop();
                    const fileExtension = fileName.split('.').pop().toLowerCase();
                    const description = data.recommendedSitePlan.description || 'No description provided';
                    const uploadedBy = data.recommendedSitePlan.uploaded_by || 'Unknown';
                    const createdAt = data.recommendedSitePlan.created_at || 'Unknown';
                    
                    let filePreview = '';
                    
                    // Generate preview based on file type
                    if (fileExtension === 'pdf') {
                        filePreview = `
                            <div class="border rounded-lg p-4 bg-gray-50">
                                <embed src="${fileUrl}" type="application/pdf" width="100%" height="400px" class="rounded">
                                <p class="text-center mt-2 text-sm text-gray-600">
                                    <i data-lucide="file-text" class="w-4 h-4 inline mr-1"></i>
                                    PDF Document
                                </p>
                            </div>
                        `;
                    } else if (['jpg', 'jpeg', 'png'].includes(fileExtension)) {
                        filePreview = `
                            <div class="border rounded-lg p-4 bg-gray-50">
                                <img src="${fileUrl}" alt="Recommended Site Plan Sketch" class="w-full h-auto max-h-96 object-contain rounded">
                                <p class="text-center mt-2 text-sm text-gray-600">
                                    <i data-lucide="image" class="w-4 h-4 inline mr-1"></i>
                                    Image File
                                </p>
                            </div>
                        `;
                    } else {
                        filePreview = `
                            <div class="border rounded-lg p-8 bg-gray-50 text-center">
                                <i data-lucide="file" class="w-16 h-16 mx-auto mb-4 text-gray-400"></i>
                                <p class="text-gray-600 mb-4">File preview not available</p>
                                <a href="${fileUrl}" target="_blank" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <i data-lucide="external-link" class="w-4 h-4 mr-2"></i>
                                    Open File
                                </a>
                            </div>
                        `;
                    }
                    
                    document.getElementById('viewRecommendedSitePlanContent').innerHTML = `
                        <!-- File Info -->
                        <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-medium text-gray-900">File Information</h4>
                                <a href="${fileUrl}" target="_blank" 
                                   class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                                    <i data-lucide="external-link" class="w-4 h-4 mr-1"></i>
                                    Open in New Tab
                                </a>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="font-medium text-gray-700">File Name:</span>
                                    <span class="text-gray-600">${fileName}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700">Uploaded:</span>
                                    <span class="text-gray-600">${new Date(createdAt).toLocaleDateString()}</span>
                                </div>
                                <div class="md:col-span-2">
                                    <span class="font-medium text-gray-700">Description:</span>
                                    <span class="text-gray-600">${description}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- File Preview -->
                        ${filePreview}
                        
                        <!-- Actions -->
                        <div class="flex gap-2 mt-4">
                            <button onclick="downloadRecommendedSitePlan('${fileUrl}', '${fileName}')" 
                                    class="flex items-center px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700">
                                <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                Download
                            </button>
                            <button onclick="openRecommendedSitePlanModal(${applicationId})" 
                                    class="flex items-center px-3 py-2 bg-orange-600 text-white text-sm rounded-md hover:bg-orange-700">
                                <i data-lucide="edit" class="w-4 h-4 mr-2"></i>
                                Update File
                            </button>
                            <button onclick="confirmDeleteRecommendedSitePlan(${applicationId})" 
                                    class="flex items-center px-3 py-2 bg-red-600 text-white text-sm rounded-md hover:bg-red-700">
                                <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                                Delete
                            </button>
                        </div>
                    `;
                } else {
                    document.getElementById('viewRecommendedSitePlanContent').innerHTML = `
                        <div class="text-center py-8">
                            <i data-lucide="file-x" class="w-16 h-16 mx-auto mb-4 text-gray-400"></i>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">No File Found</h4>
                            <p class="text-gray-600 mb-4">No recommended site plan sketch has been uploaded for this application.</p>
                            <button onclick="closeViewRecommendedSitePlanModal(); openRecommendedSitePlanModal(${applicationId})" 
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <i data-lucide="upload" class="w-4 h-4 mr-2"></i>
                                Upload Now
                            </button>
                        </div>
                    `;
                }
            } else {
                document.getElementById('viewRecommendedSitePlanContent').innerHTML = `
                    <div class="text-center py-8">
                        <i data-lucide="alert-circle" class="w-16 h-16 mx-auto mb-4 text-red-400"></i>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Error Loading File</h4>
                        <p class="text-gray-600">${data.message || 'Failed to load recommended site plan sketch'}</p>
                    </div>
                `;
            }
            
            // Reinitialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('viewRecommendedSitePlanContent').innerHTML = `
                <div class="text-center py-8">
                    <i data-lucide="wifi-off" class="w-16 h-16 mx-auto mb-4 text-red-400"></i>
                    <h4 class="text-lg font-medium text-gray-900 mb-2">Connection Error</h4>
                    <p class="text-gray-600">Unable to load recommended site plan sketch. Please try again.</p>
                </div>
            `;
            
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    }

    function closeViewRecommendedSitePlanModal() {
        document.getElementById('viewRecommendedSitePlanModal').style.display = 'none';
    }

    function downloadRecommendedSitePlan(fileUrl, fileName) {
        const link = document.createElement('a');
        link.href = fileUrl;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function confirmDeleteRecommendedSitePlan(applicationId) {
        closeViewRecommendedSitePlanModal();
        deleteRecommendedSitePlan(applicationId);
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('viewRecommendedSitePlanModal');
        if (event.target === modal) {
            closeViewRecommendedSitePlanModal();
        }
    });
</script>