<div class="tab-content hidden" id="notes-tab">
    <div class="mb-6">
        @php
            $sitePlanExists = DB::connection('sqlsrv')->table('site_plans')->where('application_id', $application->id)->exists();
            $sitePlanStatus = $sitePlanExists ? 'Uploaded' : 'Not Uploaded';
            // Get the uploaded file info if exists
            $sitePlanFile = null;
            if ($sitePlanExists) {
                $sitePlanFile = DB::connection('sqlsrv')->table('site_plans')->where('application_id', $application->id)->first();
            }
        @endphp
        
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Site Plan File</h3> 
            <span id="site-plan-status-badge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sitePlanExists ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                <span id="site-plan-status-text">{{ $sitePlanStatus }}</span>
            </span>
        </div>
        
        {{-- Display uploaded file if exists --}}
        @if($sitePlanExists && $sitePlanFile && $sitePlanFile->site_file)
            <div class="mb-4">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    @php
                        $fileUrl = asset('storage/site_plans/' . $sitePlanFile->site_file);
                        $extension = strtolower(pathinfo($sitePlanFile->site_file, PATHINFO_EXTENSION));
                    @endphp
                    
                    @if(in_array($extension, ['jpg','jpeg','png']))
                        <div class="text-center">
                            <img src="{{ $fileUrl }}" alt="Site Plan Image" class="max-w-full h-auto rounded border border-gray-300 mb-3">
                            <div class="flex justify-center space-x-2">
                                <a href="{{ $fileUrl }}" target="_blank" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100">
                                    <i data-lucide="external-link" class="w-3 h-3 mr-1"></i> View Full Size
                                </a>
                                <a href="{{ $fileUrl }}" download class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded hover:bg-gray-100">
                                    <i data-lucide="download" class="w-3 h-3 mr-1"></i> Download
                                </a>
                            </div>
                        </div>
                    @elseif($extension === 'pdf')
                        <div class="text-center py-6">
                            <i data-lucide="file-text" class="w-12 h-12 mx-auto text-gray-400 mb-3"></i>
                            <p class="text-sm font-medium text-gray-900 mb-2">{{ $sitePlanFile->site_file }}</p>
                            <div class="flex justify-center space-x-2">
                                <a href="{{ $fileUrl }}" target="_blank" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100">
                                    <i data-lucide="eye" class="w-3 h-3 mr-1"></i> View PDF
                                </a>
                                <a href="{{ $fileUrl }}" download class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded hover:bg-gray-100">
                                    <i data-lucide="download" class="w-3 h-3 mr-1"></i> Download
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-6">
                            <i data-lucide="file" class="w-12 h-12 mx-auto text-gray-400 mb-3"></i>
                            <p class="text-sm font-medium text-gray-900 mb-2">{{ $sitePlanFile->site_file }}</p>
                            <a href="{{ $fileUrl }}" download class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100">
                                <i data-lucide="download" class="w-3 h-3 mr-1"></i> Download File
                            </a>
                        </div>
                    @endif
                </div>
                
                <div class="mt-3 text-xs text-gray-500 text-center">
                    Uploaded: {{ date('d M Y, g:i A', strtotime($sitePlanFile->created_at)) }}
                </div>
            </div>
        @else
            <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                <i data-lucide="file-x" class="w-12 h-12 mx-auto text-gray-400 mb-3"></i>
                <h4 class="text-sm font-medium text-gray-900 mb-2">No Site Plan Uploaded</h4>
                <p class="text-xs text-gray-500">Use the action menu to upload a site plan for this application.</p>
            </div>
        @endif
    </div>
</div>