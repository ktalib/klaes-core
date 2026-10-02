<script>
/**
 * KANGIS Approval Workflow JavaScript
 *
 * Handles:
 * 1. Workflow toggle (enable/disable workflow mode on create form)
 * 2. Workflow progress stepper on tracker cards
 * 3. Auto-suggesting next step when transferring files
 * 4. Purpose field injection into movement payloads
 */
(function () {
    'use strict';

    // ──────────────────────────────────────────────
    // 1. Workflow Toggle on Create Form
    // ──────────────────────────────────────────────

    const workflowToggle = document.getElementById('workflow-3step-toggle');
    const workflowStepper = document.getElementById('workflow-stepper');
    const workflowPanel = document.getElementById('workflow-toggle-panel');
    const officeDetailsCard = document.getElementById('office-details-card');

    // Keep the workflow block above Office Details as requested.
    if (workflowPanel && officeDetailsCard && officeDetailsCard.parentElement) {
        const host = officeDetailsCard.parentElement;
        if (workflowPanel.parentElement !== host || workflowPanel.nextElementSibling !== officeDetailsCard) {
            host.insertBefore(workflowPanel, officeDetailsCard);
        }
        workflowPanel.classList.remove('hidden', 'mt-4');
        workflowPanel.classList.add('mb-4');
    }

    if (workflowToggle && workflowStepper) {
        if (workflowToggle.checked) {
            workflowStepper.classList.remove('hidden');
        } else {
            workflowStepper.classList.add('hidden');
        }

        workflowToggle.addEventListener('change', function () {
            if (this.checked) {
                workflowStepper.classList.remove('hidden');
            } else {
                workflowStepper.classList.add('hidden');
            }
            // Re-apply the receiving-fields gate whenever the toggle changes
            if (typeof window.applyKangisOfficeWorkflowGate === 'function') {
                window.applyKangisOfficeWorkflowGate();
            }
        });
    }

    // Apply gate on initial load — this file runs last, so select2 etc. are ready
    if (typeof window.applyKangisOfficeWorkflowGate === 'function') {
        window.applyKangisOfficeWorkflowGate();
    }

    // ──────────────────────────────────────────────
    // 2. Workflow Progress Rendering for Tracker Cards
    // ──────────────────────────────────────────────

    const STEP_COLORS = {
        completed: {
            dot: 'bg-green-500',
            text: 'text-green-700',
            line: 'bg-green-400',
            icon: 'check-circle'
        },
        active: {
            dot: 'bg-blue-500 ring-4 ring-blue-200',
            text: 'text-blue-700 font-bold',
            line: 'bg-blue-300',
            icon: 'loader'
        },
        disabled: {
            dot: 'bg-gray-300',
            text: 'text-gray-400',
            line: 'bg-gray-200',
            icon: 'circle'
        }
    };

    /**
     * Build a horizontal stepper HTML string from workflow_progress data.
     *
     * @param {Array} progress - Array of step objects from the API
     * @returns {string} HTML string
     */
    window.renderWorkflowStepper = function (progress) {
        if (!progress || !progress.length) {
            return '';
        }

        // Mirror the 4-Step Approval Workflow from the page-level toggle:
        // KANGIS Registry → Director GIS → DG KANGIS → Department Queue
        const activeStepEntry = progress.find(function (step) {
            return step && step.status === 'active';
        });
        const currentStep = Number(activeStepEntry?.step || 0);

        const milestones = [
            { step: 1, label: 'KANGIS Registry',  icon: 'building-2'   },
            { step: 2, label: 'Director GIS',     icon: 'user-cog'     },
            { step: 3, label: 'DG KANGIS',        icon: 'shield-check' },
            { step: 4, label: 'Department Queue', icon: 'layers'       }
        ];

        const transitions = [
            { label: 'Submission',     fromStep: 1 },
            { label: 'Recommendation', fromStep: 2 },
            { label: 'Approval',       fromStep: 3 }
        ];

        const resolveMilestoneStatus = function (milestoneStep) {
            if (!currentStep) return 'disabled';
            if (currentStep > milestoneStep) return 'completed';
            if (currentStep === milestoneStep) return 'active';
            return 'disabled';
        };

        const stepNodes = [];
        milestones.forEach(function (milestone, idx) {
            const status = resolveMilestoneStatus(milestone.step);
            const colors = STEP_COLORS[status] || STEP_COLORS.disabled;
            const icon = status === 'completed' ? 'check' : milestone.icon;

            stepNodes.push(
                '<div class="flex flex-col items-center flex-1">' +
                    '<div class="w-8 h-8 rounded-full ' + colors.dot + ' flex items-center justify-center shadow">' +
                        '<i data-lucide="' + icon + '" class="h-4 w-4 text-white"></i>' +
                    '</div>' +
                    '<p class="mt-1 text-[10px] ' + colors.text + ' text-center truncate max-w-[110px]">' + escapeHtml(milestone.label) + '</p>' +
                '</div>'
            );

            if (idx < transitions.length) {
                const transition = transitions[idx];
                const transitionStatus = currentStep > transition.fromStep ? 'completed' : (currentStep === transition.fromStep ? 'active' : 'disabled');
                const transitionColors = STEP_COLORS[transitionStatus] || STEP_COLORS.disabled;

                stepNodes.push(
                    '<div class="flex flex-col items-center flex-shrink-0 px-0.5">' +
                        '<p class="text-[9px] text-gray-500 mb-0.5 whitespace-nowrap">' + escapeHtml(transition.label) + '</p>' +
                        '<div class="flex items-center">' +
                            '<div class="w-6 h-0.5 ' + transitionColors.line + '"></div>' +
                            '<i data-lucide="chevron-right" class="h-3 w-3 text-gray-400 -ml-0.5"></i>' +
                        '</div>' +
                    '</div>'
                );
            }
        });

        return '<div class="flex items-center justify-between gap-0.5 px-2 py-2 bg-gradient-to-r from-amber-50 to-yellow-50 rounded-lg border border-amber-200">' +
            '<div class="flex items-center w-full">' + stepNodes.join('') + '</div>' +
        '</div>';
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ──────────────────────────────────────────────
    // 2b. New KANGIS 9-Stage Workflow Stepper
    // ──────────────────────────────────────────────
    //
    // Renders the 9-stage / 8-transfer NEW KANGIS file tracking workflow:
    //   1) CS  Customer Service       (KANGIS Registry intake)
    //   2) OSS One Stop Shop
    //   3) VRF Verification
    //   4) GEO Geometry (GIS)
    //   5) VET Vetting Section
    //   6) PAG Pagination (Deeds)
    //   7) PROD Production (GIS)
    //   8) DG  DG, KANGIS (Collection)
    //   9) KRE KANGIS Registry (final log-in)

    const NEW_KANGIS_MILESTONES = [
        { step: 1, code: 'CS',   short: 'CS',   label: 'Customer Service',  icon: 'building-2' },
        { step: 2, code: 'OSS',  short: 'OSS',  label: 'One Stop Shop',     icon: 'door-open' },
        { step: 3, code: 'VRF',  short: 'VRF',  label: 'Verification',      icon: 'search-check' },
        { step: 4, code: 'GEO',  short: 'GEO',  label: 'Geometry (GIS)',    icon: 'map' },
        { step: 5, code: 'VET',  short: 'VET',  label: 'Vetting',           icon: 'gavel' },
        { step: 6, code: 'PAG',  short: 'PAG',  label: 'Pagination',        icon: 'file-stack' },
        { step: 7, code: 'PROD', short: 'PROD', label: 'Production (GIS)',  icon: 'printer' },
        { step: 8, code: 'DG',   short: 'DG',   label: 'DG, KANGIS',        icon: 'shield-check' },
        { step: 9, code: 'KRE',  short: 'KRE',  label: 'KANGIS Registry',   icon: 'archive' }
    ];

    const NEW_KANGIS_CODE_TO_STEP = NEW_KANGIS_MILESTONES.reduce(function (acc, m) {
        acc[m.code] = m.step;
        return acc;
    }, {});

    /**
     * Resolve the current step number for a New KANGIS tracker based on its
     * movement log entries. The latest entry's receiving office determines
     * the active stage. Falls back to step 1 (Customer Service) when there
     * are no movements yet.
     */
    function resolveNewKangisCurrentStep(logEntries) {
        if (!Array.isArray(logEntries) || !logEntries.length) {
            return 1;
        }

        // logEntries is ordered most-recent first in this codebase.
        for (let i = 0; i < logEntries.length; i++) {
            const entry = logEntries[i] || {};
            const codeCandidates = [
                entry.receivingOfficeCode,
                entry.officeId,
                entry.officeCode,
                entry.office_code
            ];
            for (let j = 0; j < codeCandidates.length; j++) {
                const raw = codeCandidates[j];
                if (!raw) continue;
                const code = String(raw).toUpperCase().trim();
                if (NEW_KANGIS_CODE_TO_STEP[code]) {
                    return NEW_KANGIS_CODE_TO_STEP[code];
                }
            }
        }
        return 1;
    }

    /**
     * Build the 9-stage horizontal stepper for new_kangis trackers.
     */
    window.renderNewKangisWorkflowStepper = function (logEntries) {
        const currentStep = resolveNewKangisCurrentStep(logEntries);

        const resolveStatus = function (milestoneStep) {
            if (currentStep > milestoneStep) return 'completed';
            if (currentStep === milestoneStep) return 'active';
            return 'disabled';
        };

        const nodes = [];
        NEW_KANGIS_MILESTONES.forEach(function (milestone, idx) {
            const status = resolveStatus(milestone.step);
            const colors = STEP_COLORS[status] || STEP_COLORS.disabled;
            const icon = status === 'completed' ? 'check' : milestone.icon;

            nodes.push(
                '<div class="flex flex-col items-center flex-1 min-w-0">' +
                    '<div class="w-7 h-7 rounded-full ' + colors.dot + ' flex items-center justify-center shadow" title="Step ' + milestone.step + ' — ' + escapeHtml(milestone.label) + '">' +
                        '<i data-lucide="' + icon + '" class="h-3.5 w-3.5 text-white"></i>' +
                    '</div>' +
                    '<p class="mt-1 text-[9px] font-semibold ' + colors.text + ' text-center truncate max-w-[60px]">' + escapeHtml(milestone.short) + '</p>' +
                    '<p class="text-[8px] ' + colors.text + ' text-center truncate max-w-[60px] opacity-80">' + escapeHtml(milestone.label) + '</p>' +
                '</div>'
            );

            if (idx < NEW_KANGIS_MILESTONES.length - 1) {
                const transitionStatus = currentStep > milestone.step ? 'completed' : 'disabled';
                const transitionColors = STEP_COLORS[transitionStatus] || STEP_COLORS.disabled;
                nodes.push(
                    '<div class="flex items-center flex-shrink-0 px-0.5 pt-3">' +
                        '<div class="w-3 h-0.5 ' + transitionColors.line + '"></div>' +
                        '<i data-lucide="chevron-right" class="h-3 w-3 ' + (transitionStatus === 'completed' ? 'text-green-500' : 'text-gray-400') + ' -ml-0.5"></i>' +
                    '</div>'
                );
            }
        });

        const headerLabel = (NEW_KANGIS_MILESTONES.find(function (m) { return m.step === currentStep; }) || {}).label || '—';

        return '<div class="px-2 py-2 bg-gradient-to-r from-amber-50 to-yellow-50 rounded-lg border border-amber-200">' +
                '<div class="flex items-center justify-between mb-1 px-1">' +
                    '<span class="text-[10px] font-semibold text-amber-800 uppercase tracking-wide">New KANGIS — 9-Stage Workflow</span>' +
                    '<span class="text-[10px] text-amber-700">Step ' + currentStep + ' of 9 · ' + escapeHtml(headerLabel) + '</span>' +
                '</div>' +
                '<div class="flex items-start w-full overflow-x-auto">' + nodes.join('') + '</div>' +
            '</div>';
    };

    // ──────────────────────────────────────────────
    // 3. Workflow-Aware Movement Dialog
    // ──────────────────────────────────────────────

    /**
     * Check if a tracker has a workflow and determine the next step suggestion.
     *
     * @param {object} tracker - The normalized tracker object
     * @returns {object|null} { purpose, toCode, toName, label } or null
     */
    window.getWorkflowNextStep = function (tracker) {
        if (!tracker) return null;

        const wfType = tracker.workflowType || tracker.workflow_type;
        if (wfType !== 'kangis_3step' && wfType !== 'kangis_approval_flow') return null;

        const nextStep = tracker.nextStep || tracker.next_step || tracker.currentStep || tracker.current_step;
        if (!nextStep) return null;

        return {
            purpose: nextStep.purpose,
            toCode: nextStep.to_code,
            toName: nextStep.to_name,
            label: nextStep.label
        };
    };

    /**
     * Inject the purpose field into the movement payload if the tracker
    * is using the KANGIS approval workflow.
     *
     * @param {object} tracker - The normalized tracker object
     * @param {object} payload - The movement payload being built
     * @returns {object} The modified payload
     */
    window.injectWorkflowPurpose = function (tracker, payload) {
        const next = window.getWorkflowNextStep(tracker);
        if (next && next.purpose) {
            payload.purpose = next.purpose;
        }
        return payload;
    };

    // ──────────────────────────────────────────────
    // 4. Patch the movement dialog to show workflow step info
    // ──────────────────────────────────────────────

    // Override the movement dialog summary when a workflow tracker is being transferred
    const originalUpdateMovementDestinationSummary = window.updateMovementDestinationSummary;
    if (typeof originalUpdateMovementDestinationSummary === 'function') {
        window.updateMovementDestinationSummary = function (tracker, officeId) {
            // Call original
            originalUpdateMovementDestinationSummary(tracker, officeId);

            // Append workflow step info
            const next = window.getWorkflowNextStep(tracker);
            if (next) {
                const label = document.getElementById('movement-destination-label');
                if (label) {
                    label.innerHTML += '<br><span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">' +
                        '<i data-lucide="git-branch" class="h-3 w-3 mr-1"></i> Step: ' + escapeHtml(next.label) +
                    '</span>';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            }
        };
    }

})();
</script>
