{{-- View Site Plan (Survey) Tab --}}
<div id="survey-plan-tab" class="tab-content">
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-4 border-b bg-green-50">
            <h3 class="text-lg font-medium text-green-800">🗺️ View Site Plan (Survey)</h3>
            <p class="text-sm text-green-600 mt-1">View the uploaded Site Plan (Survey) document for this application.</p>
        </div>
        
        <div class="p-6">
            @php
                $documents = null;
                $surveyPlan = null;
                
                if (!empty($application->documents)) {
                    $documents = json_decode($application->documents, true);
                    if (is_array($documents) && isset($documents['survey_plan'])) {
                        $surveyPlan = $documents['survey_plan'];
                    }
                }
            @endphp
            
            @if ($surveyPlan && isset($surveyPlan['path']))
                <div class="bg-gray-50 rounded-lg p-4 border">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h4 class="font-semibold text-gray-800 flex items-center">
                                <i data-lucide="file-image" class="w-4 h-4 mr-2"></i>
                                Survey Plan Document
                            </h4>
                            <p class="text-sm text-gray-600 mt-1">
                                <strong>Original Name:</strong> {{ $surveyPlan['original_name'] ?? 'N/A' }}<br>
                                <strong>File Type:</strong> {{ strtoupper($surveyPlan['type'] ?? 'Unknown') }}<br>
                                <strong>Uploaded:</strong> {{ $surveyPlan['uploaded_at'] ?? 'N/A' }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ asset('storage/' . $surveyPlan['path']) }}" 
                               target="_blank"
                               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                                <i data-lucide="external-link" class="w-4 h-4 mr-1"></i>
                                Open in New Tab
                            </a>
                            <a href="{{ asset('storage/' . $surveyPlan['path']) }}" 
                               download="{{ $surveyPlan['original_name'] ?? 'survey_plan' }}"
                               class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center">
                                <i data-lucide="download" class="w-4 h-4 mr-1"></i>
                                Download
                            </a>
                        </div>
                    </div>
                    
                    <!-- Document Preview -->
                    <div class="bg-white rounded-lg border p-4">
                        <h5 class="font-medium text-gray-800 mb-3">Document Preview</h5>
                        <div class="flex justify-center">
                            @if (in_array(strtolower($surveyPlan['type'] ?? ''), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                <img src="{{ asset('storage/' . $surveyPlan['path']) }}" 
                                     alt="Survey Plan" 
                                     class="max-w-full max-h-96 object-contain rounded-lg shadow-md border">
                            @elseif (strtolower($surveyPlan['type'] ?? '') === 'pdf')
                                <div class="w-full">
                                    <iframe src="{{ asset('storage/' . $surveyPlan['path']) }}" 
                                            class="w-full h-96 border rounded-lg"
                                            frameborder="0">
                                        <p>Your browser does not support PDFs. 
                                           <a href="{{ asset('storage/' . $surveyPlan['path']) }}" target="_blank">Click here to view the PDF</a>
                                        </p>
                                    </iframe>
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <i data-lucide="file" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                                    <p class="text-gray-600">Preview not available for this file type.</p>
                                    <p class="text-sm text-gray-500 mt-2">Please download the file to view it.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-12">
                    <i data-lucide="map-pin-off" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-800 mb-2">No Survey Plan Available</h4>
                    <p class="text-gray-600 mb-4">No survey plan document has been uploaded for this application.</p>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 max-w-md mx-auto">
                        <p class="text-sm text-yellow-800">
                            <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                            Please ensure the survey plan is uploaded in the application documents.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>