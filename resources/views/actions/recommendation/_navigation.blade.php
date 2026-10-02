{{-- Tab Navigation --}}
<div class="mb-6">
    {{-- Tab Headers --}}
    <div class="bg-gradient-to-br from-slate-50 to-white border border-slate-200 rounded-2xl shadow-sm">
        @php
            // Check if JSI is captured and approved
            $jsiCaptured = !empty($jointInspectionReport);
            $jsiApproved = $jsiCaptured && (bool) ($jointInspectionReport->is_approved ?? false);

            // JSI tab is disabled if not captured
            $jsiTabEnabled = $jsiCaptured;
            $jsiTabDisabledReason = $jsiTabEnabled
                ? null
                : __('Joint Site Inspection is not captured.');

            // Inspection details tab relies on captured inspection data
            $inspectionTabEnabled = $jsiCaptured;
            $inspectionTabDisabledReason = $inspectionTabEnabled
                ? null
                : __('Inspection details become available once a joint site inspection report is captured.');

            // Planning Recommendation tab is disabled if JSI has not been approved
            $planningRecommTabEnabled = $jsiApproved;
            $planningRecommTabDisabledReason = $planningRecommTabEnabled
                ? null
                : __('Planning Recommendation is disabled until JSI has been approved.');
            $currentUrlMode = strtolower(trim((string) request()->query('url', '')));
            $buyersTabAvailable = $currentUrlMode === 'recommendation';
            $shouldShowPlanningTab = $buyersTabAvailable || (($resolvedActiveTab ?? null) === 'initial');
            $finalTabEnabled = in_array(strtolower($application->planning_recommendation_status ?? ''), ['approve', 'approved'], true);

            $requestedTab = $resolvedActiveTab ?? request()->get('tab');
            $availableTabs = [
                'documents' => true,
                'jsi-report' => $jsiTabEnabled,
                'inspection-details' => $inspectionTabEnabled,
                'buyers-list' => $buyersTabAvailable,
                'initial' => $shouldShowPlanningTab && $planningRecommTabEnabled,
                'final' => $finalTabEnabled,
            ];

            $activeTab = 'documents';
            if ($requestedTab && isset($availableTabs[$requestedTab]) && $availableTabs[$requestedTab]) {
                $activeTab = $requestedTab;
            }
        @endphp
        <nav class="flex flex-wrap md:flex-nowrap items-stretch gap-2 md:gap-3 p-3 overflow-x-auto" aria-label="Recommendation Tabs">
            <button type="button" class="tab-button group {{ $activeTab === 'documents' ? 'active' : '' }}" data-tab="documents" role="tab" aria-selected="{{ $activeTab === 'documents' ? 'true' : 'false' }}">
                <span class="tab-button__inner">
                    <span class="tab-button__icon">
                        <i data-lucide="folder-open" class="w-4 h-4"></i>
                    </span>
                    <span class="tab-button__label">
                        <span class="">View Documents</span>
                    </span>
                </span>
                <span class="tab-button__indicator" aria-hidden="true"></span>
            </button>
            @if($buyersTabAvailable)
                <button 
                    type="button"
                    class="tab-button group {{ $activeTab === 'buyers-list' ? 'active' : '' }}"
                    data-tab="buyers-list"
                    role="tab"
                    aria-selected="{{ $activeTab === 'buyers-list' ? 'true' : 'false' }}">
                    <span class="tab-button__inner">
                        <span class="tab-button__icon">
                            <i data-lucide="users" class="w-4 h-4"></i>
                        </span>
                        <span class="tab-button__label">
                            <span class="">Buyers List</span>
                        </span>
                    </span>
                    <span class="tab-button__indicator" aria-hidden="true"></span>
                </button>
            @endif

                   <button 
                type="button"
                class="tab-button group {{ $inspectionTabEnabled ? '' : 'tab-button--disabled' }} {{ $activeTab === 'inspection-details' ? 'active' : '' }}"
                data-tab="inspection-details"
                role="tab"
                aria-selected="{{ $activeTab === 'inspection-details' ? 'true' : 'false' }}"
                @unless($inspectionTabEnabled)
                    disabled
                    aria-disabled="true"
                    @if($inspectionTabDisabledReason)
                        title="{{ $inspectionTabDisabledReason }}"
                        data-disabled-message="{{ $inspectionTabDisabledReason }}"
                    @endif
                @endunless
            >
                <span class="tab-button__inner">
                    <span class="tab-button__icon">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    </span>
                    <span class="tab-button__label">
                        <span class="">Joint Inspection Details</span>
                    </span>
                </span>
                <span class="tab-button__indicator" aria-hidden="true"></span>
            </button>

            <button 
                type="button" 
                class="tab-button group {{ $jsiTabEnabled ? '' : 'tab-button--disabled' }} {{ $activeTab === 'jsi-report' ? 'active' : '' }}" 
                data-tab="jsi-report" 
                role="tab" 
                aria-selected="{{ $activeTab === 'jsi-report' ? 'true' : 'false' }}"
                @unless($jsiTabEnabled)
                    disabled
                    aria-disabled="true"
                    @if($jsiTabDisabledReason)
                        title="{{ $jsiTabDisabledReason }}"
                        data-disabled-message="{{ $jsiTabDisabledReason }}"
                    @endif
                @endunless
            >
                <span class="tab-button__inner">
                    <span class="tab-button__icon">
                        <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                    </span>
                    <span class="tab-button__label">
                        <span class="">Joint Site Inspection</span>
                    </span>
                </span>
                <span class="tab-button__indicator" aria-hidden="true"></span>
            </button>

     



            @if($shouldShowPlanningTab)
                <button
                    type="button"
                    class="tab-button group {{ $planningRecommTabEnabled ? '' : 'tab-button--disabled' }} {{ $activeTab === 'initial' ? 'active' : '' }}"
                    data-tab="initial"
                    role="tab"
                    aria-selected="{{ $activeTab === 'initial' ? 'true' : 'false' }}"
                    @unless($planningRecommTabEnabled)
                        disabled
                        aria-disabled="true"
                        @if($planningRecommTabDisabledReason)
                            title="{{ $planningRecommTabDisabledReason }}"
                            data-disabled-message="{{ $planningRecommTabDisabledReason }}"
                        @endif
                    @endunless
                >
                    <span class="tab-button__inner">
                        <span class="tab-button__icon">
                            <i data-lucide="banknote" class="w-4 h-4"></i>
                        </span>
                        <span class="tab-button__label">
                            <span class="">Planning Recommendation</span>
                        </span>
                    </span>
                    <span class="tab-button__indicator" aria-hidden="true"></span>
                </button>
            @endif

            @if($finalTabEnabled)
                <button type="button" class="tab-button group {{ $activeTab === 'final' ? 'active' : '' }}" data-tab="final" id="planningRecommendationTab" role="tab" aria-selected="{{ $activeTab === 'final' ? 'true' : 'false' }}">
                    <span class="tab-button__inner">
                        <span class="tab-button__icon">
                            <i data-lucide="file-check" class="w-4 h-4"></i>
                        </span>
                        <span class="tab-button__label">
                            <span class="">Recommendation Report</span>
                            <span class="sm:hidden">Report</span>
                            <span class="tab-button__badge">Approved</span>
                        </span>
                    </span>
                    <span class="tab-button__indicator" aria-hidden="true"></span>
                </button>
            @endif
        </nav>
<style>
.tab-button:focus-visible .tab-button__inner {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.35);
}

.tab-button:active .tab-button__inner {
    transform: translateY(1px);
}

.tab-button__inner {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    min-height: 2.75rem;
    padding: 0.65rem 1rem;
    border-radius: 0.9rem;
    border: 1px solid transparent;
    background: rgba(248, 250, 252, 0.8);
    color: #475569;
    font-size: 0.9rem;
    font-weight: 500;
    line-height: 1.25rem;
    transition: all 0.25s ease;
    box-shadow: inset 0 -1px 0 rgba(148, 163, 184, 0.08);
}

.tab-button__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 999px;
    background: rgba(191, 219, 254, 0.45);
    color: #1e3a8a;
    transition: all 0.25s ease;
}

.tab-button__label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.tab-button__badge {
    margin-left: 0.5rem;
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 600;
    background: rgba(187, 247, 208, 0.9);
    color: #166534;
    box-shadow: 0 2px 4px rgba(22, 101, 52, 0.15);
}

.tab-button__indicator {
    position: absolute;
    left: 16%;
    right: 16%;
    bottom: 6px;
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(90deg, #60a5fa, #2563eb);
    transform: scaleX(0);
    transform-origin: center;
    opacity: 0;
    transition: transform 0.25s ease, opacity 0.25s ease;
}

.tab-button:hover .tab-button__inner {
    background: rgba(226, 232, 240, 0.85);
    color: #1e3a8a;
    border-color: rgba(148, 163, 184, 0.45);
}

.tab-button:hover .tab-button__icon {
    background: rgba(191, 219, 254, 0.7);
    color: #1d4ed8;
}

.tab-button:hover .tab-button__indicator {
    transform: scaleX(0.55);
    opacity: 0.6;
    background: linear-gradient(90deg, rgba(191, 219, 254, 0.9), rgba(147, 197, 253, 0.9));
}

.tab-button.active {
    flex: 1 1 100%;
}

.tab-button.active .tab-button__inner {
    background: #ffffff;
    color: #1d4ed8;
    border-color: rgba(59, 130, 246, 0.35);
    box-shadow:
        0 10px 30px rgba(59, 130, 246, 0.12),
        inset 0 0 0 1px rgba(59, 130, 246, 0.2);
}

.tab-button.active .tab-button__icon {
    background: rgba(59, 130, 246, 0.15);
    color: #1d4ed8;
    box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.15);
}

.tab-button.active .tab-button__indicator {
    transform: scaleX(1);
    opacity: 1;
}

@media (min-width: 768px) {
    .tab-button {
        flex: 0 0 auto;
    }

    .tab-button.active {
        flex: 0 0 auto;
    }
}

@media (max-width: 640px) {
    .tab-button {
        flex: 1 1 calc(50% - 0.5rem);
    }

    .tab-button__inner {
        justify-content: flex-start;
    }
}

.tab-button--disabled,
.tab-button[disabled] {
    cursor: not-allowed;
}

.tab-button--disabled .tab-button__inner,
.tab-button[disabled] .tab-button__inner {
    background: rgba(241, 245, 249, 0.7);
    color: #94a3b8;
    border-color: rgba(148, 163, 184, 0.25);
    box-shadow: inset 0 -1px 0 rgba(148, 163, 184, 0.05);
}

.tab-button--disabled .tab-button__icon,
.tab-button[disabled] .tab-button__icon {
    background: rgba(226, 232, 240, 0.6);
    color: #94a3b8;
}

.tab-button--disabled .tab-button__indicator,
.tab-button[disabled] .tab-button__indicator {
    display: none;
}

.tab-button--disabled:hover .tab-button__inner,
.tab-button[disabled]:hover .tab-button__inner {
    background: rgba(241, 245, 249, 0.7);
    color: #94a3b8;
    border-color: rgba(148, 163, 184, 0.25);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.recommendationTabsInitialized) {
            return;
        }
        window.recommendationTabsInitialized = true;

        const tabButtons = Array.from(document.querySelectorAll('.tab-button'));
        const tabContents = Array.from(document.querySelectorAll('.tab-content'));

        const activateTab = (tabId, triggeringButton = null) => {
            const targetContent = document.getElementById(`${tabId}-tab`);
            if (!targetContent) {
                console.warn(`Recommendation navigation: content for tab "${tabId}" not found.`);
                return;
            }

            tabButtons.forEach(button => {
                button.classList.toggle('active', button === triggeringButton);
                button.setAttribute('aria-selected', button === triggeringButton ? 'true' : 'false');
            });

            tabContents.forEach(content => {
                content.classList.toggle('active', content === targetContent);
            });

            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        };

        tabButtons.forEach(button => {
            button.addEventListener('click', event => {
                const isDisabled = button.disabled || button.classList.contains('tab-button--disabled');
                if (isDisabled) {
                    const disabledMessage = button.dataset.disabledMessage || button.getAttribute('title');
                    if (disabledMessage) {
                        alert(disabledMessage);
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }

                const tabId = button.getAttribute('data-tab');
                if (!tabId) {
                    return;
                }

                activateTab(tabId, button);
            });
        });

        const initiallyActiveButton = tabButtons.find(button => button.classList.contains('active')) || tabButtons[0];
        if (initiallyActiveButton) {
            const initialTabId = initiallyActiveButton.getAttribute('data-tab');
            if (initialTabId) {
                activateTab(initialTabId, initiallyActiveButton);
            }
        }
    });
</script>