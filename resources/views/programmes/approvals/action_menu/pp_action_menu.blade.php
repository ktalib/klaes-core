      <!-- Dropdown Toggle Button -->
                                    <button type="button" class="p-2 hover:bg-gray-100 focus:outline-none rounded-full" onclick="customToggleDropdown(this, event)">
                                        <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                    </button>

                                    <!-- Dropdown Menu Primary Application Surveys -->
                                    <ul class="fixed action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-56">
                                        @php
                                            if (!isset($jsiReport) || $jsiReport === null) {
                                                $jsiReport = DB::connection('sqlsrv')
                                                    ->table('joint_site_inspection_reports')
                                                    ->where('application_id', $application->id)
                                                    ->whereNull('sub_application_id')
                                                    ->first();
                                            }
                                            $isJSIGenerated = $jsiReport && (bool) $jsiReport->is_generated;
                                            $isJSISubmitted = $jsiReport && (bool) $jsiReport->is_submitted;
                                            $isJSIApproved = $jsiReport && (bool) $jsiReport->is_approved;
                                            $hasJsiReport = (bool) $jsiReport;

                                            $inspectionDetailsLink = route('planning-recommendation.joint-inspection.details', ['application' => $application->id, 'return' => request()->fullUrl()]);
                                            $inspectionEditLink = route('planning-recommendation.joint-inspection.edit', ['application' => $application->id, 'return' => request()->fullUrl()]);
                                            $canEditInspection = $hasJsiReport && !$isJSIApproved;
                                            $inspectionLink = $canEditInspection ? $inspectionEditLink : $inspectionDetailsLink;
                                            $inspectionLabel = $canEditInspection ? 'View/Edit Inspection Details' : 'View Inspection Details';
                                            $inspectionIcon = $canEditInspection ? 'file-edit' : 'layout-dashboard';
                                            $currentUrlMode = request()->query('url');

                                            $recommendedSitePlanUploaded = false;
                                            try {
                                                $tableExists = DB::connection('sqlsrv')->select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'recommended_site_plans'");

                                                if ($tableExists) {
                                                    $recommendedSitePlanUploaded = DB::connection('sqlsrv')
                                                        ->table('recommended_site_plans')
                                                        ->where('application_id', $application->id)
                                                        ->exists();
                                                }
                                            } catch (\Exception $e) {
                                                $recommendedSitePlanUploaded = false;
                                            }
                                        @endphp

                                        @if($currentUrlMode === 'view')
                                            <li>  
                                                <a href="{{ route('sectionaltitling.viewrecorddetail')}}?id={{$application->id}}&url=view" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                    <i data-lucide="eye" class="w-4 h-4 text-sky-600"></i>
                                                    <span>View Application</span>
                                                </a>
                                            </li> 
 
                                            <li>
                                                @if($hasJsiReport)
                                                    <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link" title="Inspection details already captured">
                                                        <i data-lucide="clipboard-list" class="w-4 h-4 disabled-icon"></i>
                                                        <span>Enter Inspection Details</span>
                                                    </div>
                                                @else
                                                    <a href="#" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 joint-inspection-trigger" data-application-id="{{ $application->id }}" data-sub-application-id="">
                                                        <i data-lucide="clipboard-list" class="w-4 h-4 text-purple-600"></i>
                                                        <span>Enter Inspection Details</span>
                                                    </a>
                                                @endif
                                            </li>

                                            <li>
                                                @if(!$hasJsiReport)
                                                    <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link" title="No inspection record yet">
                                                        <i data-lucide="layout-dashboard" class="w-4 h-4 disabled-icon"></i>
                                                        <span>View/Edit Inspection Details</span>
                                                    </div>
                                                @else
                                                    <a href="{{ $inspectionLink }}" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                        <i data-lucide="{{ $inspectionIcon }}" class="w-4 h-4 text-indigo-600"></i>
                                                        <span>{{ $inspectionLabel }}</span>
                                                    </a>
                                                @endif
                                            </li>

                                            <li>
                                                <a href="{{ route('actions.buyers_list', ['id' => $application->id]) }}" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                    <i data-lucide="users" class="w-4 h-4 text-blue-600"></i>
                                                    <span>View/Edit Buyers List</span>
                                                </a>
                                            </li>

                                            <li>
                                                <a href="{{ route('programmes.planning.primary.complete-survey', $application->id) }}" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                    <i data-lucide="clipboard-list" class="w-4 h-4 text-teal-600"></i>
                                                    <span>Complete Survey Details</span>
                                                </a>
                                            </li>

                          <li>
                                                @if($recommendedSitePlanUploaded)
                                                    <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link" title="Recommended site plan already uploaded">
                                                        <i data-lucide="file-text" class="w-4 h-4 disabled-icon"></i>
                                                        <span>Upload Recommended Site Plan</span>
                                                    </div>
                                                @else
                                                    <a href="#"
                                                       onclick="event.preventDefault(); openRecommendedSitePlanModal({{ $application->id }})"
                                                       class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                        <i data-lucide="file-text" class="w-4 h-4 text-gray-600"></i>
                                                        <span>Upload Recommended Site Plan</span>
                                                    </a>
                                                @endif
                                            </li>
                                            <li>
                                                @if($application->planning_recommendation_status == 'Approved')
                                                    <a href="{{ route('actions.recommendation', ['id' => $application->id]) }}" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                                                        <span>View Planning Recommendation </span>
                                                    </a>
                                                @else
                                                    <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link">
                                                        <i data-lucide="check-circle" class="w-4 h-4 disabled-icon"></i>
                                                        <span>View Planning Recommendation </span>
                                                    </div>
                                                @endif
                                            </li>

                                          
                                        @elseif($currentUrlMode === 'approval')
                                            <li>
                                                <a href="{{ route('sectionaltitling.viewrecorddetail')}}?id={{$application->id}}&url=view" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                    <i data-lucide="eye" class="w-4 h-4 text-sky-600"></i>
                                                    <span>View Application</span>
                                                </a>
                                            </li>

                                            {{-- <li>
                                                @if(!$hasJsiReport)
                                                    <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link" title="No inspection record yet">
                                                        <i data-lucide="layout-dashboard" class="w-4 h-4 disabled-icon"></i>
                                                        <span>View Inspection Details</span>
                                                    </div>
                                                @else
                                                    <a href="{{ $inspectionDetailsLink }}" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                        <i data-lucide="layout-dashboard" class="w-4 h-4 text-indigo-600"></i>
                                                        <span>View Inspection Details</span>
                                                    </a>
                                                @endif
                                            </li> --}}

                                            {{-- <li>
                                                <a href="{{ route('actions.buyers_list', ['id' => $application->id]) }}?url=view" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                    <i data-lucide="users" class="w-4 h-4 text-blue-600"></i>
                                                    <span>View Buyers List</span>
                                                </a>
                                            </li> --}}

                                            <li>
                                                @php
                                                    $fileExists = DB::connection('sqlsrv')
                                                        ->table('Cofo')
                                                        ->where('mlsFNo', $application->fileno)
                                                        ->orWhere('kangisFileNo', $application->fileno)
                                                        ->orWhere('NewKANGISFileno', $application->fileno)
                                                        ->exists();
                                                @endphp

                                                @if($fileExists && $application->planning_recommendation_status != 'Approved')
                                                    <a href="{{ route('actions.recommendation', ['id' => $application->id]) }}?url=recommendation" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                        <i data-lucide="check-circle" class="w-4 h-4 text-amber-600"></i>
                                                        <span>Approve/Decline</span>
                                                    </a>
                                                @else
                                                    <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link" title="Joint site inspection must be approved first">
                                                        <i data-lucide="check-circle" class="w-4 h-4 disabled-icon"></i>
                                                        <span>Approve/Decline</span>
                                                    </div>
                                                @endif
                                            </li>

                                            <li>
                                                @if($application->planning_recommendation_status == 'Approved')
                                                    <a href="{{ route('actions.recommendation', ['id' => $application->id]) }}?url=recommendation" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
                                                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                                                        <span>View Planning Recommendation </span>
                                                    </a>
                                                @else
                                                    <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link">
                                                        <i data-lucide="check-circle" class="w-4 h-4 disabled-icon"></i>
                                                        <span>View Planning Recommendation </span>
                                                    </div>
                                                @endif
                                            </li>
                                        @else
                                            <li>
                                                <div class="w-full text-left px-4 py-2 flex items-center space-x-2 disabled-link">
                                                    <i data-lucide="shield-alert" class="w-4 h-4 disabled-icon"></i>
                                                    <span>No access</span>
                                                </div>
                                            </li>
                                        @endif

                                        {{-- ST keeps no recommendation record — the decision is three
                                             columns on the application — so Master Delete here unmakes
                                             the decision rather than deleting a row: the file goes back
                                             to Pending and can be recommended again. The application,
                                             its units, its memo and its RofO all survive; erasing an ST
                                             FILE is a different screen. Supper Admin only, and only once
                                             a decision has actually been recorded. --}}
                                        @if(auth()->user()?->assign_role === 'Supper Admin' && !empty($application->planning_approval_date))
                                            <li>
                                                <button type="button"
                                                    onclick="masterDeleteStRecommendation('primary', {{ (int) $application->id }}, @js($application->fileno))"
                                                    class="w-full text-left px-4 py-2 flex items-center space-x-2 text-red-700 hover:bg-red-50 font-semibold">
                                                    <i data-lucide="shield-alert" class="w-4 h-4"></i>
                                                    <span>Master Delete</span>
                                                </button>
                                            </li>
                                        @endif
                                    </ul>

@once
<!-- Recommended Site Plan Modal -->
<div id="recommendedSitePlanModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                    <i data-lucide="file-plus" class="w-5 h-5 inline mr-2"></i>
                    Upload Recommended Site Plan Sketch
                </h3>
                <button type="button" onclick="closeRecommendedSitePlanModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div id="existing-recommended-file"></div>

            <form id="recommendedSitePlanForm" enctype="multipart/form-data">
                <input type="hidden" id="recommended_application_id" name="application_id">

                <div class="mb-4">
                    <label for="recommended_file" class="block text-sm font-medium text-gray-700 mb-2">
                        Select Recommended Site Plan Sketch
                    </label>
                    <input type="file"
                           id="recommended_file"
                           name="recommended_file"
                           accept=".pdf,.jpg,.jpeg,.png"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    <p class="text-xs text-gray-500 mt-1">
                        Supported formats: PDF, JPG, JPEG, PNG (Max size: 10MB)
                    </p>
                </div>

                <div class="mb-4">
                    <label for="recommended_description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description (Optional)
                    </label>
                    <textarea id="recommended_description"
                              name="description"
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Enter description for the recommended site plan sketch..."></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button"
                            onclick="closeRecommendedSitePlanModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button"
                            id="uploadRecommendedBtn"
                            onclick="uploadRecommendedSitePlan()"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                        <i data-lucide="upload" class="w-4 h-4 mr-2"></i>
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    if (!window.Swal) {
        const swalScript = document.createElement('script');
        swalScript.src = '//cdn.jsdelivr.net/npm/sweetalert2@11';
        document.head.appendChild(swalScript);
    }

    function ensureCsrfToken() {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        return tokenMeta ? tokenMeta.getAttribute('content') : '{{ csrf_token() }}';
    }

    function openRecommendedSitePlanModal(applicationId) {
        const modal = document.getElementById('recommendedSitePlanModal');
        modal.style.display = 'flex';
        document.getElementById('recommended_application_id').value = applicationId;
        const uploadBtn = document.getElementById('uploadRecommendedBtn');
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            uploadBtn.innerHTML = '<i data-lucide="upload" class="w-4 h-4 mr-2"></i>Upload';
        }

        fetch(`/stmemo/get-recommended-siteplan/${applicationId}`)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('existing-recommended-file');
                if (data.success && data.recommendedSitePlan) {
                    container.innerHTML = `
                        <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded">
                            <p class="text-sm text-blue-700 mb-2">
                                <i data-lucide="file-check" class="w-4 h-4 inline mr-1"></i>
                                Current recommended site plan sketch uploaded
                            </p>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-blue-600">Uploaded: ${data.recommendedSitePlan.created_at}</span>
                                <div class="flex gap-2">
                                    <a href="/storage/${data.recommendedSitePlan.recommended_file}" target="_blank" class="text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700">
                                        View
                                    </a>
                                    <button type="button" onclick="deleteRecommendedSitePlan(${applicationId})" class="text-xs bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    if (uploadBtn) {
                        uploadBtn.disabled = true;
                        uploadBtn.classList.add('opacity-50', 'cursor-not-allowed');
                        uploadBtn.innerHTML = '<i data-lucide="ban" class="w-4 h-4 mr-2"></i>Already Uploaded';
                    }
                } else {
                    container.innerHTML = '';
                    if (uploadBtn) {
                        uploadBtn.disabled = false;
                        uploadBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        uploadBtn.innerHTML = '<i data-lucide="upload" class="w-4 h-4 mr-2"></i>Upload';
                    }
                }
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            })
            .catch(() => {
                document.getElementById('existing-recommended-file').innerHTML = '';
                if (uploadBtn) {
                    uploadBtn.disabled = false;
                    uploadBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    uploadBtn.innerHTML = '<i data-lucide="upload" class="w-4 h-4 mr-2"></i>Upload';
                }
            });
    }

    function closeRecommendedSitePlanModal() {
        document.getElementById('recommendedSitePlanModal').style.display = 'none';
        const form = document.getElementById('recommendedSitePlanForm');
        if (form) {
            form.reset();
        }
        document.getElementById('existing-recommended-file').innerHTML = '';
        const uploadBtn = document.getElementById('uploadRecommendedBtn');
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            uploadBtn.innerHTML = '<i data-lucide="upload" class="w-4 h-4 mr-2"></i>Upload';
        }
    }

    function uploadRecommendedSitePlan() {
        const form = document.getElementById('recommendedSitePlanForm');
        const submitBtn = document.getElementById('uploadRecommendedBtn');
        const fileInput = document.getElementById('recommended_file');
        const applicationId = document.getElementById('recommended_application_id').value;

        if (!applicationId || !fileInput.files.length) {
            window.Swal && Swal.fire('Error', 'Please select a file to upload', 'error');
            return;
        }

        const formData = new FormData(form);
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i>Uploading...';

        fetch('/stmemo/save-recommended-siteplan', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': ensureCsrfToken()
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.Swal && Swal.fire('Success', data.message, 'success');
                    closeRecommendedSitePlanModal();
                    window.location.reload();
                } else {
                    window.Swal && Swal.fire('Error', data.message || 'Failed to upload file', 'error');
                }
            })
            .catch(() => {
                window.Swal && Swal.fire('Error', 'An error occurred while uploading the file', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="upload" class="w-4 h-4 mr-2"></i>Upload';
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            });
    }

    function deleteRecommendedSitePlan(applicationId) {
        const confirmDelete = () => fetch(`/stmemo/delete-recommended-siteplan/${applicationId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': ensureCsrfToken(),
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.Swal && Swal.fire('Deleted!', data.message, 'success');
                    closeRecommendedSitePlanModal();
                    window.location.reload();
                } else {
                    window.Swal && Swal.fire('Error', data.message || 'Failed to delete file', 'error');
                }
            })
            .catch(() => {
                window.Swal && Swal.fire('Error', 'An error occurred while deleting the file', 'error');
            });

        if (window.Swal) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This action will permanently delete the recommended site plan sketch.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    confirmDelete();
                }
            });
        } else {
            if (confirm('Delete the recommended site plan sketch?')) {
                confirmDelete();
            }
        }
    }

    function viewRecommendedSitePlan(applicationId) {
        fetch(`/stmemo/get-recommended-siteplan/${applicationId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.recommendedSitePlan && data.recommendedSitePlan.recommended_file) {
                        window.open(`/storage/${data.recommendedSitePlan.recommended_file}`, '_blank');
                    } else if (window.Swal) {
                        Swal.fire({
                            title: 'No File Found',
                            text: 'No recommended site plan sketch has been uploaded for this application yet.',
                            icon: 'info',
                            showCancelButton: true,
                            confirmButtonText: 'Upload Now',
                            cancelButtonText: 'Close'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                openRecommendedSitePlanModal(applicationId);
                            }
                        });
                    }
                } else if (window.Swal) {
                    if (data.table_missing) {
                        Swal.fire('Database Error', 'The recommended site plans table has not been created yet. Please contact the system administrator.', 'error');
                    } else {
                        Swal.fire('Error', data.message || 'Failed to retrieve recommended site plan data', 'error');
                    }
                }
            })
            .catch(() => {
                window.Swal && Swal.fire('Error', 'An error occurred while retrieving the recommended site plan', 'error');
            });
    }
</script>
@endonce