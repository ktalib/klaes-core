{{-- Recommended Site Plan Sketch Tab --}}
<div id="recommended-site-plan-tab" class="tab-content">
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-4 border-b bg-indigo-50">
            <h3 class="text-lg font-medium text-indigo-800 flex items-center">
                <i data-lucide="file-text" class="w-5 h-5 mr-2"></i>
                View Recommended Site Plan Sketch
            </h3>
            
        </div>

        <div class="p-6">
            @php
                $recommendedPlan = null;
                $tableExists = false;
                $storageUrl = null;
                $recommendedError = null;

                try {
                    $tableExists = !empty(DB::connection('sqlsrv')->select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'recommended_site_plans'"));

                    if ($tableExists) {
                        $recommendedPlan = DB::connection('sqlsrv')
                            ->table('recommended_site_plans')
                            ->where('application_id', $application->id)
                            ->orderByDesc('updated_at')
                            ->first();

                        if ($recommendedPlan && !empty($recommendedPlan->recommended_file)) {
                            $storageDisk = \Illuminate\Support\Facades\Storage::disk('public');
                            if ($storageDisk->exists($recommendedPlan->recommended_file)) {
                                $storageUrl = $storageDisk->url($recommendedPlan->recommended_file);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $recommendedError = $e->getMessage();
                }

                $fileExtension = null;
                $uploadedAt = null;

                if ($recommendedPlan) {
                    $path = $recommendedPlan->recommended_file ?? '';
                    $fileExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $timestamp = $recommendedPlan->updated_at ?? $recommendedPlan->created_at;
                    $uploadedAt = $timestamp ? \Carbon\Carbon::parse($timestamp)->format('Y-m-d H:i') : 'N/A';
                }
            @endphp

            @if ($recommendedError)
                <div class="text-center py-12">
                    <i data-lucide="alert-triangle" class="w-16 h-16 mx-auto text-red-400 mb-4"></i>
                    <h4 class="text-lg font-medium text-red-700 mb-2">Unable to load recommended site plan</h4>
                    <p class="text-red-600 mb-4">{{ $recommendedError }}</p>
                    <p class="text-sm text-red-500">Please contact the system administrator for assistance.</p>
                </div>
            @elseif (!$tableExists)
                <div class="text-center py-12">
                    <i data-lucide="database" class="w-16 h-16 mx-auto text-yellow-400 mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-800 mb-2">Recommended site plan table not found</h4>
                    <p class="text-gray-600 mb-4">The <code>recommended_site_plans</code> table has not been created yet.</p>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 max-w-md mx-auto">
                        <p class="text-sm text-yellow-800">
                            <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                            Run the <code>create_recommended_site_plans_table.sql</code> script to enable this feature.
                        </p>
                    </div>
                </div>
            @elseif (!$recommendedPlan || !$storageUrl)
                <div class="text-center py-12">
                    <i data-lucide="map-pin-off" class="w-16 h-16 mx-auto text-gray-400 mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-800 mb-2">No recommended site plan available</h4>
                    <p class="text-gray-600 mb-4">A recommended site plan sketch has not been uploaded for this application.</p>
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 max-w-md mx-auto">
                        
                    </div>
                </div>
            @else
                <div class="bg-gray-50 rounded-lg p-4 border">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h4 class="font-semibold text-gray-800 flex items-center">
                                <i data-lucide="file-check" class="w-4 h-4 mr-2"></i>
                                Recommended Site Plan Sketch
                            </h4>
                            <p class="text-sm text-gray-600 mt-1">
                                
                                <strong>File Type:</strong> {{ strtoupper($fileExtension ?: 'UNKNOWN') }}<br>
                                <strong>Uploaded:</strong> {{ $uploadedAt }}<br>
                                @if ($recommendedPlan->description)
                                    <strong>Description:</strong> {{ $recommendedPlan->description }}
                                @endif
                            </p>
                            @if ($recommendedPlan->uploaded_by)
                                <p class="text-xs text-gray-500 mt-2">
                                    Uploaded By User ID: {{ $recommendedPlan->uploaded_by }}
                                </p>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ $storageUrl }}" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                                <i data-lucide="external-link" class="w-4 h-4 mr-1"></i>
                                Open in New Tab
                            </a>
                            <a href="{{ $storageUrl }}" download="{{ basename($recommendedPlan->recommended_file) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center">
                                <i data-lucide="download" class="w-4 h-4 mr-1"></i>
                                Download
                            </a>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg border p-4">
                        <h5 class="font-medium text-gray-800 mb-3">Document Preview</h5>
                        <div class="flex justify-center">
                            @if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                <img src="{{ $storageUrl }}" alt="Recommended Site Plan" class="max-w-full max-h-96 object-contain rounded-lg shadow-md border">
                            @elseif ($fileExtension === 'pdf')
                                <div class="w-full">
                                    <iframe src="{{ $storageUrl }}" class="w-full h-96 border rounded-lg" frameborder="0">
                                        <p>Your browser does not support PDFs.
                                            <a href="{{ $storageUrl }}" target="_blank">Click here to view the PDF</a>
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
            @endif
        </div>
    </div>
</div>
