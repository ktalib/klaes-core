{{-- JavaScript and Scripts --}}
<script>
    window.jointInspectionDefaults = @json($jointInspectionDefaults);
    window.jointInspectionBoundarySegments = @json($jointInspectionDefaults['boundary_segments'] ?? []);
    window.jointInspectionSharedUtilities = @json($sharedUtilitiesOptions);
    window.jointInspectionExistingReportUrl = '{{ $jointInspectionReport ? route('planning-recommendation.joint-inspection.show', $application->id) : '' }}';
    
    // Debug information
    console.log('Scripts loading...', {
        applicationId: '{{ $application->id }}',
        hasJointInspection: {{ !empty($jointInspectionReport) ? 'true' : 'false' }}
    });
</script>

@include('actions.parts.recomm_js')

<!-- JSI Approval JavaScript -->
<script src="{{ asset('js/jsi-approval.js') }}"></script>

<script>
// Application Data Form JavaScript with Validation
function planningRunWhenReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
}

function planningGetCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && typeof meta.getAttribute === 'function') {
        var metaValue = meta.getAttribute('content');
        if (metaValue) {
            return metaValue;
        }
    }

    var hiddenInput = document.querySelector('input[name="_token"]');
    if (hiddenInput && typeof hiddenInput.value === 'string' && hiddenInput.value.length) {
        return hiddenInput.value;
    }

    return '';
}

planningRunWhenReady(function () {
    console.log('[Planning] helper scripts ready');

    var statusValue = '{{ strtolower($application->planning_recommendation_status ?? '') }}';
    var isApproved = statusValue === 'approved' || statusValue === 'approve';

    var fieldInputs = {
        lkn: document.getElementById('lkn_number'),
        tp: document.getElementById('tp_plan_number'),
        approved: document.getElementById('approved_plan_number'),
        scheme: document.getElementById('scheme_plan_number')
    };

    function collectStatusElements(selector) {
        var nodeList = document.querySelectorAll(selector);
        return nodeList ? Array.prototype.slice.call(nodeList) : [];
    }

    var statusBadges = {
        lkn: collectStatusElements('#lknStatus'),
        tp: collectStatusElements('#tpStatus'),
        approved: collectStatusElements('#approvedStatus'),
        scheme: collectStatusElements('#schemeStatus')
    };

    var completionMessage = document.getElementById('completionMessage');
    var planningTab = document.getElementById('planningRecommendationTab');
    var applicationDataForm = document.getElementById('applicationDataForm');
    var saveApplicationDataBtn = document.getElementById('saveApplicationDataBtn');
    var saveObservationsBtn = document.getElementById('saveObservations');
    var planningForm = document.getElementById('planningRecommendationForm');
    var planningSubmitBtn = document.getElementById('planningRecommendationSubmitBtn');

    var csrfToken = planningGetCsrfToken();
    if (!csrfToken) {
        console.warn('[Planning] CSRF token not found; requests may fail.');
    }

    for (var key in fieldInputs) {
        if (Object.prototype.hasOwnProperty.call(fieldInputs, key) && !fieldInputs[key]) {
            console.warn('[Planning] Missing application data input for ' + key);
        }
    }

    for (var statusKey in statusBadges) {
        if (Object.prototype.hasOwnProperty.call(statusBadges, statusKey) && statusBadges[statusKey].length === 0) {
            console.warn('[Planning] Missing status indicator element for ' + statusKey);
        }
    }

    function safeValue(field) {
        if (!field || typeof field.value !== 'string') {
            return '';
        }
        return field.value.trim();
    }

    function updateStatusBadges(key, isComplete) {
        if (!statusBadges[key] || !statusBadges[key].length) {
            return;
        }

        statusBadges[key].forEach(function (badge) {
            badge.textContent = isComplete ? '✅' : '❌';
        });
    }

    function validateRequiredFields() {
        var lknNumber = safeValue(fieldInputs.lkn);
        var tpNumber = safeValue(fieldInputs.tp);
        var approvedNumber = safeValue(fieldInputs.approved);
        var schemeNumber = safeValue(fieldInputs.scheme);

        updateStatusBadges('lkn', lknNumber && lknNumber !== 'Piece of Land');
        updateStatusBadges('tp', tpNumber && tpNumber !== 'Piece of Land');
        updateStatusBadges('approved', approvedNumber !== '');
        updateStatusBadges('scheme', schemeNumber !== '');

        var allComplete = Boolean(
            lknNumber &&
            tpNumber &&
            approvedNumber &&
            schemeNumber &&
            lknNumber !== 'Piece of Land' &&
            tpNumber !== 'Piece of Land'
        );

        if (completionMessage) {
            if (isApproved) {
                completionMessage.className = 'mt-4 p-3 bg-green-50 border border-green-200 rounded-lg';
                completionMessage.innerHTML = '<p class="text-sm text-green-700"><i data-lucide="check-circle" class="w-4 h-4 inline mr-1"></i>Application is approved! You can now access the Planning Recommendation Report tab.</p>';
            } else {
                completionMessage.className = 'mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg';
                completionMessage.innerHTML = '<p class="text-sm text-yellow-700"><i data-lucide="info" class="w-4 h-4 inline mr-1"></i>The Planning Recommendation Report tab will be enabled once the application is approved.</p>';
            }
        } else {
            console.warn('[Planning] completionMessage element not found');
        }

        if (planningTab) {
            if (isApproved) {
                planningTab.disabled = false;
                planningTab.classList.remove('opacity-50');
                planningTab.classList.remove('cursor-not-allowed');
                planningTab.classList.add('cursor-pointer');
            } else {
                planningTab.disabled = true;
                planningTab.classList.add('opacity-50');
                planningTab.classList.add('cursor-not-allowed');
                planningTab.classList.remove('cursor-pointer');
            }
        } else {
            console.warn('[Planning] planningRecommendationTab element not found');
        }

        return allComplete;
    }

    for (var inputKey in fieldInputs) {
        if (Object.prototype.hasOwnProperty.call(fieldInputs, inputKey)) {
            var field = fieldInputs[inputKey];
            if (field) {
                field.addEventListener('input', validateRequiredFields);
                field.addEventListener('blur', validateRequiredFields);
            }
        }
    }

    window.resetField = function (fieldId) {
        var field = document.getElementById(fieldId);
        if (!field) {
            console.warn('[Planning] resetField called for missing element #' + fieldId);
            return;
        }

        field.value = '';
        field.focus();
        validateRequiredFields();
    };

    validateRequiredFields();

    document.addEventListener('click', function (event) {
        var planningTabButton = event.target.closest ? event.target.closest('#planningRecommendationTab') : null;
        if (planningTabButton && planningTabButton.disabled) {
            event.preventDefault();
            event.stopPropagation();

            if (isApproved) {
                alert('The Planning Recommendation Report tab should be enabled for approved applications. Please refresh the page.');
            } else {
                alert('The Planning Recommendation Report tab is only available for approved applications.');
            }
        }
    });

    function handleApplicationDataSubmit(event) {
        event.preventDefault();

        if (!isApproved && !validateRequiredFields()) {
            alert('Please fill in all required fields (LPKN Number, TP Plan Number, Approved Plan Number, and Scheme Plan No) before saving.');
            return false;
        }

        var formData = new FormData(applicationDataForm);
        var originalText = saveApplicationDataBtn ? saveApplicationDataBtn.innerHTML : '';

        if (saveApplicationDataBtn) {
            saveApplicationDataBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-1 animate-spin"></i>Saving...';
            saveApplicationDataBtn.disabled = true;
        }

        fetch('{{ route("sectionaltitling.saveApplicationData") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    alert('Application data saved successfully!');
                    validateRequiredFields();
                } else {
                    var errorText = data && data.error ? data.error : 'Unknown error occurred';
                    alert('Error saving data: ' + errorText);
                }
            })
            .catch(function (error) {
                console.error('Error:', error);
                alert('Error saving data: ' + error.message);
            })
            .then(function () {
                if (saveApplicationDataBtn) {
                    saveApplicationDataBtn.innerHTML = originalText || '<i data-lucide="save" class="w-4 h-4 mr-1"></i>Save Application Data';
                    saveApplicationDataBtn.disabled = false;
                }
            });

        return false;
    }

    if (applicationDataForm) {
        applicationDataForm.addEventListener('submit', handleApplicationDataSubmit);
    } else {
        console.warn('[Planning] applicationDataForm not found; skipping submit handler');
    }

    window.handleSaveObservations = function (event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        var button = saveObservationsBtn || document.getElementById('saveObservations');
        if (!button) {
            console.warn('[Planning] Save Observations button not found during click handling');
            return false;
        }

        var applicationIdInput = document.getElementById('application_id');
        var additionalObservationsField = document.getElementById('additionalObservations');

        if (!applicationIdInput || !applicationIdInput.value) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Application ID not found'
            });
            return false;
        }

        if (!additionalObservationsField) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Observations field not found'
            });
            return false;
        }

        var originalLabel = button.textContent;
        button.disabled = true;
        button.textContent = 'Saving...';

        fetch('{{ route("pr_memos.save-observations") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                application_id: applicationIdInput.value,
                additional_observations: additionalObservationsField.value
            })
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Additional observations saved successfully',
                        timer: 1500
                    });
                } else {
                    var message = data && data.message ? data.message : 'Failed to save observations';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: message
                    });
                }
            })
            .catch(function (error) {
                console.error('Error saving observations:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while saving observations'
                });
            })
            .then(function () {
                button.disabled = false;
                button.textContent = originalLabel;
            });

        return false;
    };

    if (saveObservationsBtn && !saveObservationsBtn.dataset.observationsHandlerAttached) {
        saveObservationsBtn.addEventListener('click', window.handleSaveObservations);
        saveObservationsBtn.dataset.observationsHandlerAttached = 'true';
    }

    console.log('[Planning] planningRecommendationForm present:', !!planningForm);
    console.log('[Planning] planningRecommendationSubmitBtn present:', !!planningSubmitBtn);
    console.log('[Planning] handlePlanningRecommendation available:', typeof window.handlePlanningRecommendation);

    if (planningForm && !planningForm.dataset.handlerAttached) {
        planningForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (typeof window.handlePlanningRecommendation === 'function') {
                window.handlePlanningRecommendation(event);
            } else {
                console.error('handlePlanningRecommendation function not found');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Planning recommendation handler is not available. Please refresh the page.',
                    confirmButtonColor: '#EF4444'
                });
            }
        });

        planningForm.dataset.handlerAttached = 'true';
    } else if (!planningForm) {
        console.warn('[Planning] planningRecommendationForm not found; cannot attach submit handler');
    }
});
</script>