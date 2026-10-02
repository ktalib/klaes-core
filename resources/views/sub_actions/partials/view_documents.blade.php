{{-- View Documents Tab --}}
@php
    $documentsData = $application->documents ?? [];
    if (is_string($documentsData)) {
        $decodedDocuments = json_decode($documentsData, true);
        $documentsData = is_array($decodedDocuments) ? $decodedDocuments : [];
    } elseif (!is_array($documentsData)) {
        $documentsData = [];
    }

    $architecturalDocument = $documentsData['architectural_design'] ?? null;
    $surveyPlan = $documentsData['survey_plan'] ?? null;
@endphp

<style>
    .documents-subtab-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .documents-subtab-button {
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.55rem 0.95rem;
        border-radius: 0.85rem;
        border: 1px solid transparent;
        background: rgba(248, 250, 252, 0.85);
        color: #475569;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.2s ease;
        box-shadow: inset 0 -1px 0 rgba(148, 163, 184, 0.08);
    }

    .documents-subtab-button:hover {
        background: rgba(226, 232, 240, 0.95);
        color: #1d4ed8;
        border-color: rgba(148, 163, 184, 0.35);
    }

    .documents-subtab-button:focus-visible {
        outline: 2px solid rgba(59, 130, 246, 0.45);
        outline-offset: 1px;
    }

    .documents-subtab-button__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 999px;
        background: rgba(191, 219, 254, 0.65);
        color: #1d4ed8;
        transition: all 0.2s ease;
    }

    .documents-subtab-button__label {
        white-space: nowrap;
    }

    .documents-subtab-button.active,
    .documents-subtab-button[aria-selected="true"] {
        background: #ffffff;
        color: #1d4ed8;
        border-color: rgba(59, 130, 246, 0.35);
        box-shadow: 0 12px 25px rgba(59, 130, 246, 0.12);
    }

    .documents-subtab-button.active .documents-subtab-button__icon,
    .documents-subtab-button[aria-selected="true"] .documents-subtab-button__icon {
        background: rgba(59, 130, 246, 0.15);
    }

    .documents-subtab-panel {
        display: none;
    }

    .documents-subtab-panel.active {
        display: block;
    }
</style>

<div id="documents-tab" class="tab-content active" data-documents-tab>
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-4 border-b">
            <div class="flex flex-col gap-2">
                <h3 class="text-sm font-medium text-slate-800">View Documents</h3>
                <p class="text-xs text-slate-500">
                    Use the subtabs below to switch between the architectural design, site plan, and recommended sketch documents.
                </p>
            </div>
        </div>
        <div class="p-4 space-y-5">
            <nav class="documents-subtab-nav" role="tablist" aria-label="Document types">
                <button
                    type="button"
                    id="documents-subtab-architectural"
                    class="documents-subtab-button active"
                    data-documents-subtab="architectural"
                    role="tab"
                    aria-controls="documents-panel-architectural"
                    aria-selected="true"
                >
                    <span class="documents-subtab-button__icon">
                        <i data-lucide="drafting-compass" class="w-4 h-4"></i>
                    </span>
                    <span class="documents-subtab-button__label">Architectural Design</span>
                </button>
                <button
                    type="button"
                    id="documents-subtab-survey"
                    class="documents-subtab-button"
                    data-documents-subtab="survey"
                    role="tab"
                    aria-controls="documents-panel-survey"
                    aria-selected="false"
                >
                    <span class="documents-subtab-button__icon">
                        <i data-lucide="map" class="w-4 h-4"></i>
                    </span>
                    <span class="documents-subtab-button__label">Survey Plan</span>
                </button>
                <button
                    type="button"
                    id="documents-subtab-recommended"
                    class="documents-subtab-button"
                    data-documents-subtab="recommended"
                    role="tab"
                    aria-controls="documents-panel-recommended"
                    aria-selected="false"
                >
                    <span class="documents-subtab-button__icon">
                        <i data-lucide="file-check" class="w-4 h-4"></i>
                    </span>
                    <span class="documents-subtab-button__label">Recommended Sketch</span>
                </button>
            </nav>

            <input type="hidden" id="application_id" value="{{ $application->id }}">
            <input type="hidden" name="fileno" value="{{ $application->fileno ?? '' }}">

            <div
                id="documents-panel-architectural"
                class="documents-subtab-panel active"
                data-documents-panel="architectural"
                role="tabpanel"
                aria-labelledby="documents-subtab-architectural"
            >
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b">
                        <div class="space-y-2">
                            <h4 class="text-sm font-semibold text-slate-800">Architectural Design</h4>
                            @if(isset($application->primary_fileno))
                                <p class="text-xs text-gray-500">
                                    Primary Application: {{ $application->primary_applicant_title ?? '' }} {{ $application->primary_first_name ?? '' }} {{ $application->primary_surname ?? '' }}
                                    <span class="inline-flex items-center px-2 py-0.5 ml-2 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        <i data-lucide="link" class="w-3 h-3 mr-1"></i>
                                        File No: {{ $application->primary_fileno ?? 'N/A' }}
                                    </span>
                                </p>
                            @endif
                        </div>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <h5 class="text-sm font-medium mb-3">Architectural Design Document</h5>

                            <div class="gap-4" id="architectural-designs-container">
                                @if ($architecturalDocument && !empty($architecturalDocument['path']))
                                    @php
                                        $architecturalPath = asset('storage/app/public/' . $architecturalDocument['path']);
                                    @endphp
                                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                        <div class="h-48 bg-gray-100 relative">
                                            <img
                                                src="{{ $architecturalPath }}"
                                                alt="Architectural Design"
                                                class="w-full h-full object-cover"
                                            >
                                            <div class="absolute top-2 right-2">
                                                <button
                                                    class="p-1 bg-white rounded-full shadow-sm hover:bg-gray-100"
                                                    onclick="previewDesign('{{ $architecturalPath }}')"
                                                    type="button"
                                                >
                                                    <i data-lucide="maximize-2" class="w-4 h-4 text-gray-700"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="p-3">
                                            <h6 class="text-sm font-medium">Architectural Design</h6>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Uploaded on:
                                                @if (!empty($architecturalDocument['uploaded_at']))
                                                    {{ \Carbon\Carbon::parse($architecturalDocument['uploaded_at'])->format('Y-m-d') }}
                                                @else
                                                    N/A
                                                @endif
                                            </p>
                                            <div class="flex mt-2 gap-2">
                                                <a
                                                    href="{{ $architecturalPath }}"
                                                    download
                                                    class="text-xs px-2 py-1 bg-blue-50 text-blue-600 rounded-md flex items-center"
                                                >
                                                    <i data-lucide="download" class="w-3 h-3 mr-1"></i>
                                                    Download
                                                </a>
                                                <button
                                                    class="text-xs px-2 py-1 bg-gray-50 text-gray-600 rounded-md flex items-center"
                                                    onclick="previewDesign('{{ $architecturalPath }}')"
                                                    type="button"
                                                >
                                                    <i data-lucide="eye" class="w-3 h-3 mr-1"></i>
                                                    View
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden p-4 flex flex-col items-center justify-center">
                                        <div class="text-gray-400 mb-2">
                                            <i data-lucide="file-question" class="w-10 h-10"></i>
                                        </div>
                                        <p class="text-sm text-gray-500">No architectural design uploaded yet</p>
                                    </div>
                                @endif
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div
                id="documents-panel-survey"
                class="documents-subtab-panel"
                data-documents-panel="survey"
                role="tabpanel"
                aria-labelledby="documents-subtab-survey"
                hidden
            >
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b bg-green-50">
                        <h4 class="text-lg font-medium text-green-800 flex items-center">
                            <i data-lucide="map" class="w-5 h-5 mr-2"></i>
                            View Site Plan (Survey)
                        </h4>
                        <p class="text-sm text-green-600 mt-1">View the uploaded Site Plan (Survey) document for this application.</p>
                    </div>
                    <div class="p-6">
                        @if ($surveyPlan && isset($surveyPlan['path']))
                            @php
                                $surveyFileType = strtolower($surveyPlan['type'] ?? '');
                                $surveyPath = asset('storage/' . $surveyPlan['path']);
                            @endphp
                            <div class="bg-gray-50 rounded-lg p-4 border">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h5 class="font-semibold text-gray-800 flex items-center">
                                            <i data-lucide="file-image" class="w-4 h-4 mr-2"></i>
                                            Survey Plan Document
                                        </h5>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <strong>Original Name:</strong> {{ $surveyPlan['original_name'] ?? 'N/A' }}<br>
                                            <strong>File Type:</strong> {{ strtoupper($surveyPlan['type'] ?? 'Unknown') }}<br>
                                            <strong>Uploaded:</strong> {{ $surveyPlan['uploaded_at'] ?? 'N/A' }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <a
                                            href="{{ $surveyPath }}"
                                            target="_blank"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center"
                                        >
                                            <i data-lucide="external-link" class="w-4 h-4 mr-1"></i>
                                            Open in New Tab
                                        </a>
                                        <a
                                            href="{{ $surveyPath }}"
                                            download="{{ $surveyPlan['original_name'] ?? 'survey_plan' }}"
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center"
                                        >
                                            <i data-lucide="download" class="w-4 h-4 mr-1"></i>
                                            Download
                                        </a>
                                    </div>
                                </div>

                                <div class="bg-white rounded-lg border p-4">
                                    <h6 class="font-medium text-gray-800 mb-3">Document Preview</h6>
                                    <div class="flex justify-center">
                                        @if (in_array($surveyFileType, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                            <img
                                                src="{{ $surveyPath }}"
                                                alt="Survey Plan"
                                                class="max-w-full max-h-96 object-contain rounded-lg shadow-md border"
                                            >
                                        @elseif ($surveyFileType === 'pdf')
                                            <div class="w-full">
                                                <iframe
                                                    src="{{ $surveyPath }}"
                                                    class="w-full h-96 border rounded-lg"
                                                    frameborder="0"
                                                >
                                                    <p>
                                                        Your browser does not support PDFs.
                                                        <a href="{{ $surveyPath }}" target="_blank">Click here to view the PDF</a>
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
                                <h5 class="text-lg font-medium text-gray-800 mb-2">No Survey Plan Available</h5>
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

            <div
                id="documents-panel-recommended"
                class="documents-subtab-panel"
                data-documents-panel="recommended"
                role="tabpanel"
                aria-labelledby="documents-subtab-recommended"
                hidden
            >
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b bg-indigo-50">
                        <h4 class="text-lg font-medium text-indigo-800 flex items-center">
                            <i data-lucide="file-text" class="w-5 h-5 mr-2"></i>
                            View Recommended Site Plan Sketch
                        </h4>
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
                                <h5 class="text-lg font-medium text-red-700 mb-2">Unable to load recommended site plan</h5>
                                <p class="text-red-600 mb-4">{{ $recommendedError }}</p>
                                <p class="text-sm text-red-500">Please contact the system administrator for assistance.</p>
                            </div>
                        @elseif (!$tableExists)
                            <div class="text-center py-12">
                                <i data-lucide="database" class="w-16 h-16 mx-auto text-yellow-400 mb-4"></i>
                                <h5 class="text-lg font-medium text-gray-800 mb-2">Recommended site plan table not found</h5>
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
                                <h5 class="text-lg font-medium text-gray-800 mb-2">No recommended site plan available</h5>
                                <p class="text-gray-600 mb-4">A recommended site plan sketch has not been uploaded for this application.</p>
                                <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 max-w-md mx-auto"></div>
                            </div>
                        @else
                            <div class="bg-gray-50 rounded-lg p-4 border">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h5 class="font-semibold text-gray-800 flex items-center">
                                            <i data-lucide="file-check" class="w-4 h-4 mr-2"></i>
                                            Recommended Site Plan Sketch
                                        </h5>
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
                                    <h6 class="font-medium text-gray-800 mb-3">Document Preview</h6>
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
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        document.querySelectorAll('[data-documents-tab]').forEach(function(tabContainer) {
            const subtabButtons = tabContainer.querySelectorAll('[data-documents-subtab]');
            const subtabPanels = tabContainer.querySelectorAll('[data-documents-panel]');

            subtabButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const target = button.getAttribute('data-documents-subtab');
                    if (!target || button.getAttribute('aria-selected') === 'true') {
                        return;
                    }

                    subtabButtons.forEach(function(btn) {
                        const isActive = btn === button;
                        btn.classList.toggle('active', isActive);
                        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    });

                    subtabPanels.forEach(function(panel) {
                        const isMatch = panel.getAttribute('data-documents-panel') === target;
                        panel.classList.toggle('active', isMatch);
                        panel.hidden = !isMatch;
                    });

                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            });

            const activePanel = tabContainer.querySelector('[data-documents-panel].active');
            if (activePanel) {
                activePanel.hidden = false;
            }
        });

        const fileInput = document.getElementById('architectural_design_upload');
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                    const maxSize = 10 * 1024 * 1024;

                    if (!allowedTypes.includes(file.type)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid File Type',
                            text: 'Please upload an image or PDF file',
                            confirmButtonColor: '#EF4444'
                        });
                        return;
                    }

                    if (file.size > maxSize) {
                        Swal.fire({
                            icon: 'error',
                            title: 'File Too Large',
                            text: 'File size should not exceed 10MB',
                            confirmButtonColor: '#EF4444'
                        });
                        return;
                    }

                    uploadArchitecturalDesign(file);
                }
            });
        }
    });

    function previewDesign(url) {
        const previewImage = document.getElementById('preview-image');
        if (previewImage) {
            previewImage.src = url;
        }

        const modal = document.getElementById('design-preview-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function closePreview() {
        const modal = document.getElementById('design-preview-modal');
        if (modal) {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    function uploadArchitecturalDesign(file) {
        const formData = new FormData();
        formData.append('architectural_design', file);
        formData.append('_token', '{{ csrf_token() }}');

        Swal.fire({
            title: 'Uploading Design...',
            html: `
                <div class="mb-3">Uploading: ${file.name}</div>
                <div class="text-sm text-gray-500">Size: ${(file.size / 1024).toFixed(2)} KB</div>
                <div class="mt-4">Please wait while we process your file</div>
            `,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('{{ route('actions.update-architectural-design', $application->id) }}', {
            method: 'POST',
            body: formData,
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Upload Successful!',
                        text: data.message || 'Your design has been uploaded successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        updateDesignUI(data.design);
                        setTimeout(() => location.reload(), 500);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        text: data.message || 'There was an error uploading your design.',
                        confirmButtonColor: '#EF4444'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    text: 'There was an error uploading your design. Please try again.',
                    confirmButtonColor: '#EF4444',
                    footer: `<div class="text-xs text-red-500">${error.message}</div>`
                });
            });
    }

    function updateDesignUI(design) {
        const container = document.getElementById('architectural-designs-container');
        if (!container) {
            return;
        }

        const formattedDate = new Date(design.uploaded_at).toISOString().split('T')[0];

        const designHtml = `
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="h-48 bg-gray-100 relative">
                    <img src="${design.full_path}" alt="Architectural Design" class="w-full h-full object-cover">
                    <div class="absolute top-2 right-2">
                        <button class="p-1 bg-white rounded-full shadow-sm hover:bg-gray-100" onclick="previewDesign('${design.full_path}')">
                            <i data-lucide="maximize-2" class="w-4 h-4 text-gray-700"></i>
                        </button>
                    </div>
                </div>
                <div class="p-3">
                    <h6 class="text-sm font-medium">Architectural Design</h6>
                    <p class="text-xs text-gray-500 mt-1">Uploaded on: ${formattedDate}</p>
                    <div class="flex mt-2 gap-2">
                        <a href="${design.full_path}" download class="text-xs px-2 py-1 bg-blue-50 text-blue-600 rounded-md flex items-center">
                            <i data-lucide="download" class="w-3 h-3 mr-1"></i> Download
                        </a>
                        <button class="text-xs px-2 py-1 bg-gray-50 text-gray-600 rounded-md flex items-center" onclick="previewDesign('${design.full_path}')">
                            <i data-lucide="eye" class="w-3 h-3 mr-1"></i> View
                        </button>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = designHtml;

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
</script>

<div id="design-preview-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center" aria-hidden="true">
    <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-medium">Design Preview</h3>
            <button onclick="closePreview()" class="p-1 hover:bg-gray-100 rounded-full">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-4 flex-1 overflow-auto flex items-center justify-center">
            <img id="preview-image" src="" alt="Design Preview" class="max-w-full max-h-[70vh] object-contain">
        </div>
    </div>
</div>
