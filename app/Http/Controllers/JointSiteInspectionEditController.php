<?php

namespace App\Http\Controllers;

use App\Models\JointSiteInspectionReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\LandUseType;
use App\Models\District;
use App\Models\Lga;
use App\Models\StreetName;
use Illuminate\Support\Facades\Cache;

class JointSiteInspectionEditController extends Controller
{
    public function editPrimary(Request $request, int $applicationId)
    {
        $application = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->where('id', $applicationId)
            ->first();

        if (!$application) {
            abort(404, 'Application not found.');
        }

        $report = JointSiteInspectionReport::where('application_id', $applicationId)
            ->whereNull('sub_application_id')
            ->first();
        $reportPayload = $report ? $report->toArray() : null;

        $sharedUtilitiesOptions = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('application_id', $applicationId)
            ->whereNull('sub_application_id')
            ->pluck('utility_type')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $hasMotherApplicationColumn = Schema::connection('sqlsrv')->hasColumn('subapplications', 'mother_application_id');

        $unitDataQuery = DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('main_application_id', $applicationId);

        if ($hasMotherApplicationColumn) {
            $unitDataQuery->orWhere('mother_application_id', $applicationId);
        }

        $unitDataOptions = $unitDataQuery
            ->select('id', 'main_application_id', 'unit_number', 'block_number', 'first_name', 'surname', 'applicant_title')
            ->get()
            ->mapWithKeys(function ($unit) {
                $buyerName = trim(($unit->first_name ?? '') . ' ' . ($unit->surname ?? ''));

                return [
                    (string) $unit->id => [
                        'id' => $unit->id,
                        'main_application_id' => $unit->main_application_id,
                        'unit_number' => $unit->unit_number,
                        'block_number' => $unit->block_number,
                        'first_name' => $unit->first_name,
                        'surname' => $unit->surname,
                        'applicant_title' => $unit->applicant_title,
                        'buyer_name' => $buyerName,
                    ],
                ];
            })
            ->toArray();

        $defaults = [
            'application_id' => $applicationId,
            'sub_application_id' => null,
            'applicant_name' => $application->owner_name ?? null,
            'location' => $application->property_location ?? $application->location ?? null,
            'plot_number' => $application->plot_number ?? null,
            'scheme_number' => $application->scheme_plan_number ?? $application->scheme_number ?? null,
            'unit_dimension' => $report->unit_dimension ?? null,
            'shared_utilities' => $sharedUtilitiesOptions,
        ];

        $returnUrl = $request->query('return');
       $PageTitle   = 'Edit Inspection Details';

        $lookupData = $this->getJsiLookupData();

        return view('programmes.inspection.edit', array_merge([
            'context' => 'primary',
            'application' => $application,
            'subApplication' => null,
            'report' => $report,
            'reportPayload' => $reportPayload,
            'sharedUtilitiesOptions' => $sharedUtilitiesOptions,
            'unitDataOptions' => $unitDataOptions,
            'defaults' => $defaults,
            'returnUrl' => $returnUrl,
            'PageTitle' => $PageTitle,
        ], $lookupData));
    }

    public function editUnit(Request $request, int $subApplicationId)
    {
        $subApplication = DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('id', $subApplicationId)
            ->first();

        if (!$subApplication) {
            abort(404, 'Unit application not found.');
        }

        $parentApplicationId = $subApplication->main_application_id
            ?? data_get($subApplication, 'mother_application_id');

        $application = null;
        if ($parentApplicationId) {
            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $parentApplicationId)
                ->first();
        }

        $report = JointSiteInspectionReport::query()
            ->where('sub_application_id', $subApplicationId)
            ->orderByDesc('id')
            ->when($parentApplicationId, function ($query) use ($parentApplicationId) {
                $query->where(function ($innerQuery) use ($parentApplicationId) {
                    $innerQuery
                        ->where('application_id', $parentApplicationId)
                        ->orWhereNull('application_id');
                });
            }, function ($query) use ($subApplicationId) {
                $query->where(function ($innerQuery) use ($subApplicationId) {
                    $innerQuery
                        ->whereNull('application_id')
                        ->orWhere('application_id', $subApplicationId);
                });
            })
            ->first();
        $reportPayload = $report ? $report->toArray() : null;

        $sharedUtilitiesOptions = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('sub_application_id', $subApplicationId)
            ->pluck('utility_type')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $unitDataOptions = [
            (string) $subApplication->id => [
                'id' => $subApplication->id,
                'main_application_id' => $parentApplicationId,
                'unit_number' => $subApplication->unit_number,
                'block_number' => $subApplication->block_number,
                'first_name' => $subApplication->first_name,
                'surname' => $subApplication->surname,
                'applicant_title' => $subApplication->applicant_title,
                'buyer_name' => trim(($subApplication->first_name ?? '') . ' ' . ($subApplication->surname ?? '')),
            ],
        ];

        $schemeNumber = null;
        if ($application) {
            $schemeNumber = $application->scheme_plan_number ?? $application->scheme_number ?? null;
        }

        $defaults = [
            'application_id' => $parentApplicationId,
            'sub_application_id' => $subApplication->id,
            'applicant_name' => trim(($subApplication->first_name ?? '') . ' ' . ($subApplication->surname ?? '')) ?: null,
            'location' => $subApplication->property_location ?? $subApplication->location ?? null,
            'plot_number' => $subApplication->unit_number ?? null,
            'unit_number' => $report->unit_number
                ?? ($subApplication->unit_number ? trim((string) $subApplication->unit_number) : null),
            'scheme_number' => $schemeNumber,
            'unit_dimension' => $report->unit_dimension
                ?? ($subApplication->unit_size ? trim((string) $subApplication->unit_size) : null),
            'shared_utilities' => $sharedUtilitiesOptions,
        ];

        $returnUrl = $request->query('return');

       $PageTitle = 'Edit Inspection Details';

        $lookupData = $this->getJsiLookupData();

        return view('programmes.inspection.edit', array_merge([
            'context' => 'unit',
            'application' => $application,
            'subApplication' => $subApplication,
            'report' => $report,
            'reportPayload' => $reportPayload,
            'sharedUtilitiesOptions' => $sharedUtilitiesOptions,
            'unitDataOptions' => $unitDataOptions,
            'defaults' => $defaults,
            'returnUrl' => $returnUrl,
            'PageTitle' => $PageTitle,
        ], $lookupData));
    }

    public function detailsPrimary(Request $request, int $applicationId)
    {
        $application = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->where('id', $applicationId)
            ->first();

        if (!$application) {
            abort(404, 'Application not found.');
        }

        $report = JointSiteInspectionReport::where('application_id', $applicationId)
            ->whereNull('sub_application_id')
            ->first();

        $sharedUtilitiesOptions = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('application_id', $applicationId)
            ->whereNull('sub_application_id')
            ->pluck('utility_type')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $payload = $this->prepareInspectionDetailsPayload(
            $request,
            'primary',
            $application,
            null,
            $report,
            $sharedUtilitiesOptions
        );

        return view('programmes.inspection.details', $payload);
    }

    public function detailsUnit(Request $request, int $subApplicationId)
    {
        $subApplication = DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('id', $subApplicationId)
            ->first();

        if (!$subApplication) {
            abort(404, 'Unit application not found.');
        }

        $parentApplicationId = $subApplication->main_application_id
            ?? data_get($subApplication, 'mother_application_id');

        if (!$parentApplicationId && !empty($subApplication->mls_fileno ?? null)) {
            $parentApplicationId = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('fileno', $subApplication->mls_fileno)
                ->value('id');
        }

        $application = null;
        if ($parentApplicationId) {
            $application = DB::connection('sqlsrv')
                ->table('mother_applications')
                ->where('id', $parentApplicationId)
                ->first();
        }

        $report = JointSiteInspectionReport::where('sub_application_id', $subApplicationId)
            ->whereNull('application_id')
            ->first();

        $sharedUtilitiesOptions = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('sub_application_id', $subApplicationId)
            ->pluck('utility_type')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $payload = $this->prepareInspectionDetailsPayload(
            $request,
            'unit',
            $application,
            $subApplication,
            $report,
            $sharedUtilitiesOptions,
            $parentApplicationId
        );

        return view('programmes.inspection.details', $payload);
    }

    protected function prepareInspectionDetailsPayload(
        Request $request,
        string $context,
        $application,
        $subApplication,
        ?JointSiteInspectionReport $report,
        array $sharedUtilitiesOptions,
        ?int $parentApplicationId = null
    ): array {
        $sharedUtilitiesFallback = collect($sharedUtilitiesOptions)
            ->filter(fn ($value) => !is_null($value) && $value !== '')
            ->values()
            ->all();

        $sharedUtilities = $report && is_array($report->shared_utilities)
            ? collect($report->shared_utilities)->filter(fn ($value) => !is_null($value) && $value !== '')->values()->all()
            : $sharedUtilitiesFallback;

        $measurementEntries = $this->normalizeMeasurementEntries($report, $sharedUtilities);

        $statusMeta = $this->determineStatusMeta($report);

        $returnUrl = $request->query('return');
        $defaultBackUrl = route('programmes.approvals.planning_recomm', ['url' => 'view']);
        $backUrl = $returnUrl ?: $defaultBackUrl;

        $editRouteParams = $context === 'unit'
            ? ['subApplication' => $subApplication->id ?? null]
            : ['application' => $application->id ?? null];
        if ($returnUrl) {
            $editRouteParams['return'] = $returnUrl;
        }

        $editUrl = $context === 'unit'
            ? route('sub-actions.planning-recommendation.joint-inspection.edit', $editRouteParams)
            : route('planning-recommendation.joint-inspection.edit', $editRouteParams);

        $storeUrl = $context === 'unit'
            ? route('sub-actions.planning-recommendation.joint-inspection.store')
            : route('planning-recommendation.joint-inspection.store');

        $subjectLabel = $context === 'unit'
            ? __('Unit Application #:id', ['id' => $subApplication->id ?? '—'])
            : __('Primary Application #:id', ['id' => $application->id ?? '—']);

        $targetApplicationId = $context === 'unit'
            ? ($application->id ?? $parentApplicationId ?? data_get($subApplication, 'main_application_id') ?? data_get($subApplication, 'mother_application_id'))
            : ($application->id ?? null);

        if ($context === 'unit' && !$targetApplicationId) {
            $mlsFileno = data_get($subApplication, 'mls_fileno');
            if ($mlsFileno) {
                $targetApplicationId = DB::connection('sqlsrv')
                    ->table('mother_applications')
                    ->where('fileno', $mlsFileno)
                    ->value('id');
            }
        }

        $applicantName = $report->applicant_name ?? (
            $context === 'unit'
                ? trim(($subApplication->first_name ?? '') . ' ' . ($subApplication->surname ?? '')) ?: null
                : ($application->owner_name ?? null)
        );

        $location = $report->location ?? (
            $context === 'unit'
                ? ($subApplication->property_location ?? $subApplication->location ?? null)
                : ($application->property_location ?? $application->location ?? null)
        );

        $plotOrUnit = $context === 'unit'
            ? ($report->plot_number ?? $subApplication->unit_number ?? null)
            : ($report->plot_number ?? $application->plot_number ?? null);

        $schemeNumber = $report->scheme_number
            ?? ($context === 'unit'
                ? ($application->scheme_plan_number ?? $application->scheme_number ?? null)
                : ($application->scheme_plan_number ?? $application->scheme_number ?? null));

        $complianceMap = [
            'obtainable' => __('Obtainable'),
            'not_obtainable' => __('Not Obtainable'),
        ];

        $generalInfo = [
            $this->makeInfoCard(
                __('Inspection Date'),
                $report && $report->inspection_date ? $report->inspection_date->format('d M Y') : __('Not captured'),
                'calendar',
                'rose'
            ),
            $this->makeInfoCard(
                __('LPKN Number'),
                $report->lkn_number ?? __('Not captured'),
                'hash',
                'sky'
            ),
            $this->makeInfoCard(
                __('Applicant'),
                $applicantName ?? __('Not captured'),
                'user',
                'violet'
            ),
            $this->makeInfoCard(
                $context === 'unit' ? __('Unit Number') : __('Plot Number'),
                $plotOrUnit ?? __('Not captured'),
                'map-pin',
                'emerald'
            ),
        ];

        if ($context === 'unit') {
            $generalInfo[] = $this->makeInfoCard(
                __('Block Number'),
                $subApplication->block_number ?? __('Not captured'),
                'layers',
                'amber'
            );
            $generalInfo[] = $this->makeInfoCard(
                __('Scheme Number'),
                $schemeNumber ?? __('Not captured'),
                'compass',
                'cyan'
            );
            $generalInfo[] = $this->makeInfoCard(
                __('Unit Dimension (m²)'),
                $report->unit_dimension
                    ?? ($subApplication->unit_size ? trim((string) $subApplication->unit_size) : __('Not captured')),
                'ruler',
                'indigo'
            );
        } else {
            $generalInfo[] = $this->makeInfoCard(
                __('Scheme Number'),
                $schemeNumber ?? __('Not captured'),
                'compass',
                'cyan'
            );
        }

        $generalInfo = array_merge($generalInfo, [
            $this->makeInfoCard(
                __('Location'),
                $location ?? __('Not captured'),
                'map',
                'teal'
            ),
            $this->makeInfoCard(
                __('Road Reservation'),
                $report->road_reservation ?? __('Not captured'),
                'route',
                'orange'
            ),
            $this->makeInfoCard(
                __('Compliance Status'),
                $report && $report->compliance_status ? ($complianceMap[$report->compliance_status] ?? ucfirst($report->compliance_status)) : __('Not captured'),
                'shield-check',
                'emerald-deep'
            ),
            $this->makeInfoCard(
                __('Applied Land Use'),
                $report->applied_land_use ?? __('Not captured'),
                'clipboard-list',
                'indigo'
            ),
            $this->makeInfoCard(
                __('Prevailing Land Use'),
                $report->prevailing_land_use ?? __('Not captured'),
                'building-2',
                'fuchsia'
            ),
        ]);

        $availableOnGround = $report ? $report->available_on_ground : null;
        $additionalObservations = ($report && $report->has_additional_observations) ? ($report->additional_observations ?? '') : null;

        $measurementSummary = $report->existing_site_measurement_summary
            ?? __('The following existing site measurements were observed during the joint site inspection:');

        $canGenerate = $report && !$report->is_generated && !$report->is_submitted;
        $canApprove = $report && $report->is_generated && !$report->is_submitted;

        $isApproved = $report ? (bool) ($report->is_approved ?? false) : false;
        $canEditDetails = !$report || (!$report->is_submitted && !$isApproved);

        $editDisabledReason = null;
        if (!$canEditDetails && $report) {
            $editDisabledReason = __('This inspection report has been approved and can no longer be edited.');
        }

        $generateDisabledReason = null;
        if (!$report) {
            $generateDisabledReason = __('Capture inspection details before generating a report.');
        } elseif ($report->is_submitted) {
            $generateDisabledReason = __('This report has already been approved.');
        } elseif ($report->is_generated) {
            $generateDisabledReason = __('The report is already generated.');
        }

        $approveDisabledReason = null;
        if (!$report) {
            $approveDisabledReason = __('Capture inspection details before approving the report.');
        } elseif (!$report->is_generated) {
            $approveDisabledReason = __('Generate the report before approving it.');
        } elseif ($report->is_submitted) {
            $approveDisabledReason = __('This report has already been approved.');
        }

        $actionHint = match (true) {
            !$report => __('No inspection details have been captured yet. Save the form to enable report generation.'),
            $report->is_submitted => __('The inspection report was approved on :date.', [
                'date' => optional($report->submitted_at)->format('d M Y \a\t h:i A') ?? __('an earlier date'),
            ]),
            $report->is_generated => __('Review the generated report and approve it when ready.'),
            default => __('Generate the report to lock the captured details before approval.'),
        };

        return [
            'context' => $context,
            'application' => $application,
            'subApplication' => $subApplication,
            'report' => $report,
            'reportPayload' => $report ? $report->toArray() : null,
            'sharedUtilities' => $sharedUtilities,
            'measurementEntries' => $measurementEntries,
            'statusMeta' => $statusMeta,
            'generalInfo' => $generalInfo,
            'measurementSummary' => $measurementSummary,
            'additionalObservations' => $additionalObservations,
            'availableOnGround' => $availableOnGround,
            'actionHint' => $actionHint,
            'canGenerate' => $canGenerate,
            'canApprove' => $canApprove,
            'canEditDetails' => $canEditDetails,
            'generateDisabledReason' => $generateDisabledReason,
            'approveDisabledReason' => $approveDisabledReason,
            'editDisabledReason' => $editDisabledReason,
            'storeUrl' => $storeUrl,
            'returnUrl' => $returnUrl,
            'backUrl' => $backUrl,
            'editUrl' => $editUrl,
            'subjectLabel' => $subjectLabel,
            'PageTitle' => __('Inspection Details'),
            'targetApplicationId' => $targetApplicationId,
            'targetSubApplicationId' => $context === 'unit' ? data_get($subApplication, 'id') : null,
            'inspectionOfficerLabel' => __('Inspection Officer / Rank'),
            'inspectionOfficerValue' => $report->inspection_officer ?? null,
        ];
    }

    protected function determineStatusMeta(?JointSiteInspectionReport $report): array
    {
        if (!$report) {
            return [
                'label' => __('Not Captured'),
                'badgeClasses' => 'bg-slate-100 text-slate-600 border border-slate-200',
                'icon' => 'file-warning',
                'description' => __('No joint site inspection report has been saved for this application yet.'),
                'timestamp' => null,
            ];
        }

        if ($report->is_submitted) {
            return [
                'label' => __('Approved'),
                'badgeClasses' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                'icon' => 'badge-check',
                'description' => __('The joint site inspection report has been approved and locked.'),
                'timestamp' => optional($report->submitted_at)->format('d M Y \a\t h:i A'),
            ];
        }

        if ($report->is_generated) {
            return [
                'label' => __('Generated'),
                'badgeClasses' => 'bg-blue-100 text-blue-700 border border-blue-200',
                'icon' => 'file-output',
                'description' => __('The report has been generated and is ready for approval.'),
                'timestamp' => optional($report->generated_at)->format('d M Y \a\t h:i A'),
            ];
        }

        return [
            'label' => __('Draft'),
            'badgeClasses' => 'bg-amber-100 text-amber-700 border border-amber-200',
            'icon' => 'pencil',
            'description' => __('The inspection details are saved as a draft and can be updated.'),
            'timestamp' => optional($report->updated_at ?? $report->created_at)->format('d M Y \a\t h:i A'),
        ];
    }

    protected function normalizeMeasurementEntries(?JointSiteInspectionReport $report, array $sharedUtilitiesFallback = []): array
    {
        $entries = [];

        if ($report && is_array($report->existing_site_measurement_entries) && !empty($report->existing_site_measurement_entries)) {
            $entries = collect($report->existing_site_measurement_entries)
                ->map(function ($entry, $index) {
                    $description = trim((string) (data_get($entry, 'description') ?? data_get($entry, 'utility_type') ?? ''));
                    $dimension = trim((string) (data_get($entry, 'dimension') ?? data_get($entry, 'measurement') ?? ''));
                    $count = trim((string) (data_get($entry, 'count') ?? ''));

                    if ($description === '' && $dimension === '' && $count === '') {
                        return null;
                    }

                    // Skip internal meta-sentinel rows (conversion-only storage:
                    // __existing_structure__, __need_for_eia__, __conversion_reservation_meta__,
                    // __conversion_purpose_lut__). These are never real measurements and must
                    // not surface in the Existing Site Measurements table (esp. for ST One Stop Shop).
                    if (preg_match('/^__.+__$/', $description) === 1) {
                        return null;
                    }

                    return [
                        'sn' => data_get($entry, 'sn', $index + 1),
                        'description' => $description,
                        'dimension' => $dimension,
                        'count' => $count,
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        if (empty($entries) && !empty($sharedUtilitiesFallback)) {
            $entries = collect($sharedUtilitiesFallback)
                ->filter(fn ($value) => !is_null($value) && $value !== '')
                ->values()
                ->map(function ($label, $index) {
                    return [
                        'sn' => $index + 1,
                        'description' => (string) $label,
                        'dimension' => '',
                        'count' => null,
                    ];
                })
                ->all();
        }

        return $entries;
    }

    protected function makeInfoCard(string $label, $value, string $icon, string $tone = 'slate'): array
    {
        $styles = $this->resolveToneStyles($tone);

        return [
            'label' => $label,
            'value' => $value,
            'icon' => $icon,
            'wrapperClasses' => $styles['wrapper'],
            'iconContainerClasses' => $styles['icon'],
            'accentClasses' => $styles['accent'],
            'labelClasses' => $styles['label'],
            'valueClasses' => $styles['value'],
        ];
    }

    protected function resolveToneStyles(string $tone): array
    {
        $base = [
            'wrapper' => 'relative overflow-hidden rounded-2xl border bg-white px-4 py-4 shadow-sm',
            'icon' => 'flex h-10 w-10 items-center justify-center rounded-full',
            'accent' => 'pointer-events-none absolute -right-8 top-1/2 h-28 w-28 -translate-y-1/2 rounded-full opacity-40 blur-3xl z-0',
            'label' => 'text-xs font-semibold uppercase tracking-wide text-slate-500/80',
            'value' => 'text-sm font-semibold text-slate-900',
        ];

        $tones = [
            'rose' => [
                'wrapper' => 'border-rose-100 shadow-rose-100/60',
                'icon' => 'bg-rose-100 text-rose-600',
                'accent' => 'bg-rose-200/70',
            ],
            'sky' => [
                'wrapper' => 'border-sky-100 shadow-sky-100/60',
                'icon' => 'bg-sky-100 text-sky-600',
                'accent' => 'bg-sky-200/70',
            ],
            'violet' => [
                'wrapper' => 'border-violet-100 shadow-violet-100/60',
                'icon' => 'bg-violet-100 text-violet-600',
                'accent' => 'bg-violet-200/70',
            ],
            'emerald' => [
                'wrapper' => 'border-emerald-100 shadow-emerald-100/60',
                'icon' => 'bg-emerald-100 text-emerald-600',
                'accent' => 'bg-emerald-200/70',
            ],
            'amber' => [
                'wrapper' => 'border-amber-100 shadow-amber-100/60',
                'icon' => 'bg-amber-100 text-amber-600',
                'accent' => 'bg-amber-200/70',
            ],
            'cyan' => [
                'wrapper' => 'border-cyan-100 shadow-cyan-100/60',
                'icon' => 'bg-cyan-100 text-cyan-600',
                'accent' => 'bg-cyan-200/70',
            ],
            'teal' => [
                'wrapper' => 'border-teal-100 shadow-teal-100/60',
                'icon' => 'bg-teal-100 text-teal-600',
                'accent' => 'bg-teal-200/70',
            ],
            'orange' => [
                'wrapper' => 'border-orange-100 shadow-orange-100/60',
                'icon' => 'bg-orange-100 text-orange-600',
                'accent' => 'bg-orange-200/70',
            ],
            'fuchsia' => [
                'wrapper' => 'border-fuchsia-100 shadow-fuchsia-100/60',
                'icon' => 'bg-fuchsia-100 text-fuchsia-600',
                'accent' => 'bg-fuchsia-200/70',
            ],
            'indigo' => [
                'wrapper' => 'border-indigo-100 shadow-indigo-100/60',
                'icon' => 'bg-indigo-100 text-indigo-600',
                'accent' => 'bg-indigo-200/70',
            ],
            'emerald-deep' => [
                'wrapper' => 'border-emerald-200 shadow-emerald-100/70',
                'icon' => 'bg-emerald-200 text-emerald-700',
                'accent' => 'bg-emerald-300/60',
            ],
            'slate' => [
                'wrapper' => 'border-slate-200 shadow-slate-100/50',
                'icon' => 'bg-slate-200 text-slate-600',
                'accent' => 'bg-slate-300/50',
            ],
        ];

        $selected = $tones[$tone] ?? $tones['slate'];

        return [
            'wrapper' => trim($base['wrapper'] . ' ' . $selected['wrapper']),
            'icon' => trim($base['icon'] . ' ' . $selected['icon']),
            'accent' => trim($base['accent'] . ' ' . $selected['accent']),
            'label' => $selected['label'] ?? $base['label'],
            'value' => $selected['value'] ?? $base['value'],
        ];
    }

    private function getJsiLookupData()
    {
        $districts = District::where('is_active', 1)->orderBy('name')->get();
        $lgas = Lga::where('is_active', 1)->orderBy('name')->get();
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        
        $landUseTypes = Cache::remember('land_use_types_all', 3600, function () {
            return LandUseType::orderBy('name')->get(['id', 'name']);
        });

        $inspectorColumns = ['id', 'name', 'rank'];
        if (Schema::connection('sqlsrv')->hasColumn('pp_inspectors', 'in_sectional_titling')) {
            $inspectorColumns[] = 'in_physical_planning';
            $inspectorColumns[] = 'in_sectional_titling';
        }
        $inspectors = Cache::remember('pp_inspectors_all', 1800, function () use ($inspectorColumns) {
            return DB::connection('sqlsrv')
                ->table('pp_inspectors')
                ->select($inspectorColumns)
                ->orderBy('name')
                ->get();
        });

        $detailedLandUseHasLandUseId = Schema::connection('sqlsrv')->hasColumn('detailed_land_use', 'land_use_id');
        $jsiDetailedLandUsesQuery = DB::connection('sqlsrv')
            ->table('detailed_land_use')
            ->where('is_active', 1)
            ->orderByRaw("CASE name WHEN 'Residential' THEN 1 WHEN 'Commercial' THEN 2 WHEN 'Agricultural' THEN 3 WHEN 'Industrial' THEN 4 ELSE 99 END")
            ->orderBy('name');

        if ($detailedLandUseHasLandUseId) {
            $jsiDetailedLandUsesQuery->select('id', 'land_use_id', 'name');
        } else {
            $jsiDetailedLandUsesQuery->select('id', 'name');
        }
        $jsiDetailedLandUses = $jsiDetailedLandUsesQuery->get();

        $jsiPurposesByLandUse = DB::connection('sqlsrv')
            ->table('jsi_lut_purposes')
            ->select('id', 'land_use_id', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->groupBy('land_use_id');

        $jsiRoadCategories = DB::connection('sqlsrv')
            ->table('jsi_lut_road_categories')
            ->select('id', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $jsiReservations = DB::connection('sqlsrv')
            ->table('jsi_lut_reservations')
            ->select('id', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return compact(
            'districts',
            'lgas',
            'states',
            'streetNames',
            'landUseTypes',
            'inspectors',
            'jsiDetailedLandUses',
            'jsiPurposesByLandUse',
            'jsiRoadCategories',
            'jsiReservations'
        );
    }
}
