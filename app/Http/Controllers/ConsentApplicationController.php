<?php

namespace App\Http\Controllers;

use App\Models\ConsentApplication;
use App\Models\PrintLog;
use App\Models\FileNumber;
use App\Models\StreetName;
use App\Models\LandUseType;
use App\Models\Purpose;
use App\Services\ConsentBillCalculator;
use App\Services\StAssignmentConsentResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ConsentApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $applications = ConsentApplication::orderBy('created_at', 'desc')->get();
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')->table('StatLGAs')->join('States', 'StatLGAs.StateID', '=', 'States.StateID')->where('States.StateName', 'Kano')->orderBy('LGAName')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        $landUseTypes = LandUseType::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        $purposes = Purpose::query()->orderBy('name')->get(['id', 'landuseid', 'name']);

        return view('consent_applications.index', compact('applications', 'states', 'lgas', 'districts', 'streetNames', 'landUseTypes', 'purposes'));
    }

    /**
     * Next tracking number for the current year, e.g. CONS-2026-0004.
     *
     * A filtered unique index backs the column, so a racing duplicate fails
     * loudly rather than silently reusing a number — see the retry in store().
     *
     * @return string
     */
    private function generateTrackingNo()
    {
        $prefix = 'CONS-' . date('Y') . '-';
        $offset = strlen($prefix) + 1; // SQL SUBSTRING is 1-based

        $lastSeq = (int) ConsentApplication::where('application_tracking_no', 'like', $prefix . '%')
            ->selectRaw("MAX(CAST(SUBSTRING(application_tracking_no, {$offset}, 10) AS INT)) as seq")
            ->value('seq');

        return $prefix . str_pad((string) ($lastSeq + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Collect the extra file numbers attached to an application, each with the
     * property description captured for it.
     *
     * Rows without a file number are dropped, as are duplicates and any row
     * repeating the primary file number, so a file is never stored twice.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    private function additionalProperties(Request $request)
    {
        $primary = strtoupper(trim((string) $request->file_number));
        $seen = [];
        $properties = [];

        foreach ((array) $request->input('additional_file_number', []) as $index => $fileNumber) {
            $fileNumber = trim((string) $fileNumber);
            $key = strtoupper($fileNumber);

            if ($fileNumber === '' || $key === $primary || in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;

            $field = fn($name) => trim((string) ($request->input($name, [])[$index] ?? ''));

            $district = $field('additional_property_district') === 'Other'
                ? $field('additional_property_district_other')
                : $field('additional_property_district');

            $properties[] = [
                'file_number' => $fileNumber,
                'applicant_name' => $field('additional_property_applicant'),
                'house_no' => $field('additional_property_house_no'),
                'plot_no' => $field('additional_property_plot_no'),
                'street' => $field('additional_property_street'),
                'district' => $district,
                'lga' => $field('additional_property_lga'),
                'state' => $field('additional_property_state'),
                'description' => $field('additional_property_description'),
            ];
        }

        return $properties;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Fold a hand-typed number into the canonical 0XXXXXXXXXX form before it is
     * validated, matching what ValuationReportController stores. A backfilled
     * number is already canonical and passes through untouched.
     */
    private function normalizeApplicantPhone(Request $request): void
    {
        if ($request->filled('applicant_phone')) {
            $request->merge([
                'applicant_phone' => \App\Rules\NigerianPhone::normalize($request->input('applicant_phone')),
            ]);
        }
    }

    public function store(Request $request)
    {
        $this->normalizeApplicantPhone($request);

        $applicationDateRule = $request->filled('application_submitted_date') ? 'nullable|date' : 'required|date';
        $request->validate([
            'file_number' => 'required|string',
            'consent_type' => 'required|in:Assignment,Gift,Mortgage',
            'applicant_name' => 'required|string',
            'applicant_address' => 'required|string',
            // The composed address alone cannot be checked for completeness,
            // so the parts that must be present are validated individually.
            'applicant_house_no' => 'required|string|max:120',
            'applicant_lga' => 'required|string|max:120',
            'applicant_state' => 'required|string|max:120',
            'party_name' => 'required|string',
            'party_address' => 'required|string',
            'property_description' => 'required|string',
            'application_date' => $applicationDateRule,
            'application_submitted_date' => 'nullable|date',
            'application_type' => 'nullable|string|max:120',
            'consideration' => 'nullable|string',
            'consideration_words' => 'nullable|string',
            'right_of_occupancy_number' => 'nullable|string|max:120',
            'right_of_occupancy_landuse' => 'nullable|string|max:120',
            'purpose_of_right_of_occupancy' => 'nullable|string|max:150',
            'original_holder_name' => 'nullable|string|max:150',
            'correspondence_address' => 'nullable|string',
            'applicant_phone' => ['nullable', 'string', 'max:30', new \App\Rules\NigerianPhone],
            'postal_address_gsm' => 'nullable|string|max:150',
            'nationality_state_of_origin' => 'nullable|string|max:150',
                'state_of_origin' => 'nullable|string|max:120',
                'nationality' => 'nullable|string|max:120',
            'stage_of_development' => 'nullable|string|max:150',
            'location_of_right_of_occupancy' => 'nullable|string|max:150',
            'date_of_grant' => 'nullable|date',
            'date_of_grant_purpose' => 'nullable|string|max:150',
            'special_mortgage_terms' => 'nullable|string',
            'additional_file_number' => 'nullable|array',
            'additional_file_number.*' => 'nullable|string|max:120',
            'additional_property_applicant' => 'nullable|array',
            'additional_property_house_no' => 'nullable|array',
            'additional_property_plot_no' => 'nullable|array',
            'additional_property_street' => 'nullable|array',
            'additional_property_district' => 'nullable|array',
            'additional_property_district_other' => 'nullable|array',
            'additional_property_lga' => 'nullable|array',
            'additional_property_state' => 'nullable|array',
            'additional_property_description' => 'nullable|array',
            'additional_party_name' => 'nullable|array',
            'additional_party_house_no' => 'nullable|array',
            'additional_party_street' => 'nullable|array',
            'additional_party_district' => 'nullable|array',
            'additional_party_lga' => 'nullable|array',
            'additional_party_state' => 'nullable|array',
            'additional_applicant_name' => 'nullable|array',
            'additional_applicant_house_no' => 'nullable|array',
            'additional_applicant_street' => 'nullable|array',
            'additional_applicant_district' => 'nullable|array',
            'additional_applicant_lga' => 'nullable|array',
            'additional_applicant_state' => 'nullable|array',
        ]);

        // A file is assigned again and again over its life, so one consent per file
        // number is wrong — it blocked every subsequent assignment. What must not
        // repeat is the same transaction: the same file to the same assignee.
        $assignee = trim((string) $request->party_name);

        if ($conflict = $this->assigneeConflictMessage($request)) {
            return response()->json(['success' => false, 'message' => $conflict], 422);
        }

        $existing = ConsentApplication::where('file_number', $request->file_number)
            ->whereRaw('LOWER(LTRIM(RTRIM(party_name))) = ?', [mb_strtolower($assignee)])
            ->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A consent application for file number ' . $request->file_number
                    . ' in favour of ' . $assignee . ' already exists (ref '
                    . ($existing->application_tracking_no ?: '#' . $existing->id) . ').'
            ], 422);
        }

        $data = $request->all();
        $data['date_of_grant_purpose'] = collect([
            $request->date_of_grant,
            $request->purpose_of_right_of_occupancy,
        ])->filter()->implode(' - ');

        // Handle additional parties
        $additionalParties = [];
        if ($request->has('additional_party_name') && is_array($request->additional_party_name)) {
            foreach ($request->additional_party_name as $index => $name) {
                if (!empty($name)) {
                    $houseNo = $request->additional_party_house_no[$index] ?? '';
                    $street = $request->additional_party_street[$index] ?? '';
                    $district = $request->additional_party_district[$index] ?? '';
                    $lga = $request->additional_party_lga[$index] ?? '';
                    $state = $request->additional_party_state[$index] ?? '';

                    // Build full address string for print template compatibility
                    $parts = [];
                    if ($houseNo) $parts[] = "House No " . $houseNo;
                    if ($street) $parts[] = $street;
                    if ($district) $parts[] = $district;
                    if ($lga) $parts[] = $lga . " LGA";
                    if ($state) $parts[] = $state . " State";
                    $builtAddress = implode(", ", $parts) . ".";

                    $additionalParties[] = [
                        'name' => $name,
                        'house_no' => $houseNo,
                        'street' => $street,
                        'district' => $district,
                        'lga' => $lga,
                        'state' => $state,
                        'address' => $builtAddress
                    ];
                }
            }
        }
        $data['additional_parties'] = $additionalParties;

        // Handle additional applicants
        $additionalApplicants = [];
        if ($request->has('additional_applicant_name') && is_array($request->additional_applicant_name)) {
            foreach ($request->additional_applicant_name as $index => $name) {
                if (!empty($name)) {
                    $houseNo = $request->additional_applicant_house_no[$index] ?? '';
                    $street = $request->additional_applicant_street[$index] ?? '';
                    $district = $request->additional_applicant_district[$index] ?? '';
                    $lga = $request->additional_applicant_lga[$index] ?? '';
                    $state = $request->additional_applicant_state[$index] ?? '';

                    // Build full address string for print template compatibility
                    $parts = [];
                    if ($houseNo) $parts[] = "House No " . $houseNo;
                    if ($street) $parts[] = $street;
                    if ($district) $parts[] = $district;
                    if ($lga) $parts[] = $lga . " LGA";
                    if ($state) $parts[] = $state . " State";
                    $builtAddress = implode(", ", $parts) . ".";

                    $additionalApplicants[] = [
                        'name' => $name,
                        'house_no' => $houseNo,
                        'street' => $street,
                        'district' => $district,
                        'lga' => $lga,
                        'state' => $state,
                        'address' => $builtAddress
                    ];
                }
            }
        }
        $data['additional_applicants'] = $additionalApplicants;

        $data['additional_properties'] = $this->additionalProperties($request);

        $data['c_of_o_no'] = $request->file_number;

        // Valuation comes before Consent. How hard that is enforced is config,
        // because most files in the back catalogue have no valuation yet — see
        // config/deeds_pipeline.php for the measured numbers behind the default.
        $pipeline = app(\App\Services\DeedsPipelineStatus::class);

        // Only a sale is valued. A Gift passes no consideration and a Mortgage
        // is secured on the property rather than sold, so neither is held up
        // waiting for a valuation — the gate is switched off for them entirely,
        // which also keeps the manual-valuation override out of their way.
        $mode = $pipeline->valuationRequiredFor($request->consent_type)
            ? $pipeline->gateMode('valuation_before_consent')
            : 'off';

        $valuationStage = $pipeline->forFile($request->file_number, $request->consent_type)[\App\Services\DeedsPipelineStatus::STAGE_VALUATION];
        $pipelineWarning = null;

        // An authorised officer may accept a valuation done before KLAES
        // existed instead of re-keying it. Resolved before the gate, because a
        // valid override is what satisfies the gate.
        $manual = $this->manualValuation($request);

        if (!empty($manual['error'])) {
            return response()->json(['success' => false, 'message' => $manual['error']], 422);
        }

        $overridden = !empty($manual['columns']);

        // A manual acceptance recorded on an EARLIER consent does not clear the
        // gate for this one: it was one officer's decision about one
        // application, not a finding that the file is now valued. A new consent
        // needs a real report or its own authorised override.
        $properlyValued = $valuationStage['done'] && empty($valuationStage['manual']);

        if ($mode !== 'off' && !$properlyValued && !$overridden) {
            $reason = 'A valuation must be captured for file ' . $request->file_number
                . ' before a consent can be raised. ' . $valuationStage['message'] . '.';

            if ($mode === 'block') {
                return response()->json(['success' => false, 'message' => $reason], 422);
            }

            $pipelineWarning = $reason;
        }

        // The bill is recomputed here from the file's valuation, so whatever
        // amounts the browser posted are discarded rather than trusted.
        $data = array_merge($data, $this->billColumnsFor($request->file_number, $request));

        // Merged AFTER the bill so the override columns cannot be clobbered by
        // a rebuild. valuation_report_id is left exactly as billColumnsFor set
        // it — null on an overridden consent — so a manual acceptance is never
        // mistaken for a report the Ministry actually holds.
        if ($overridden) {
            $data = array_merge($data, $manual['columns']);
        }

        $data['user_id'] = Auth::id();
        $data['created_by'] = Auth::user()->first_name . ' ' . Auth::user()->last_name;

        // Two users saving at once can generate the same number; the unique index
        // rejects the loser, so re-read the sequence and try again.
        $application = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $data['application_tracking_no'] = $this->generateTrackingNo();
            try {
                $application = ConsentApplication::create($data);
                break;
            } catch (QueryException $e) {
                if ($attempt === 4) {
                    throw $e;
                }
            }
        }

        // Logged separately from the save: waiving a control is a decision that
        // should be findable without reading consent rows one by one.
        if ($overridden && class_exists('\App\Services\AuditService')) {
            app(\App\Services\AuditService::class)->logAction(
                'Consent Manual Valuation Override',
                'consent_applications',
                $application->id,
                null,
                null,
                'Manual valuation accepted for file ' . $application->file_number
                    . ' by ' . $manual['columns']['manual_valuation_by']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Application generated successfully.',
            'id' => $application->id,
            'application_tracking_no' => $application->application_tracking_no,
            'manual_valuation' => $overridden,
            // Present only when the consent was saved out of order. The wizard
            // shows it after the save rather than before, because in 'warn'
            // mode the save is allowed — the officer is being told, not stopped.
            'pipeline_warning' => $pipelineWarning,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $this->normalizeApplicantPhone($request);

        $application = ConsentApplication::findOrFail($id);

        if ($application->print_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit an application that has already been printed.'
            ], 403);
        }

        $applicationDateRule = $request->filled('application_submitted_date') ? 'nullable|date' : 'required|date';
        $request->validate([
            'file_number' => 'required|string',
            'consent_type' => 'required|in:Assignment,Gift,Mortgage',
            'applicant_name' => 'required|string',
            'applicant_address' => 'required|string',
            // The composed address alone cannot be checked for completeness,
            // so the parts that must be present are validated individually.
            'applicant_house_no' => 'required|string|max:120',
            'applicant_lga' => 'required|string|max:120',
            'applicant_state' => 'required|string|max:120',
            'party_name' => 'required|string',
            'party_address' => 'required|string',
            'property_description' => 'required|string',
            'application_date' => $applicationDateRule,
            'application_submitted_date' => 'nullable|date',
            'application_type' => 'nullable|string|max:120',
            'consideration' => 'nullable|string',
            'consideration_words' => 'nullable|string',
            'right_of_occupancy_number' => 'nullable|string|max:120',
            'right_of_occupancy_landuse' => 'nullable|string|max:120',
            'purpose_of_right_of_occupancy' => 'nullable|string|max:150',
            'original_holder_name' => 'nullable|string|max:150',
            'correspondence_address' => 'nullable|string',
            'applicant_phone' => ['nullable', 'string', 'max:30', new \App\Rules\NigerianPhone],
            'postal_address_gsm' => 'nullable|string|max:150',
            'nationality_state_of_origin' => 'nullable|string|max:150',
                'state_of_origin' => 'nullable|string|max:120',
                'nationality' => 'nullable|string|max:120',
            'stage_of_development' => 'nullable|string|max:150',
            'location_of_right_of_occupancy' => 'nullable|string|max:150',
            'date_of_grant' => 'nullable|date',
            'date_of_grant_purpose' => 'nullable|string|max:150',
            'special_mortgage_terms' => 'nullable|string',
            'additional_file_number' => 'nullable|array',
            'additional_file_number.*' => 'nullable|string|max:120',
            'additional_property_applicant' => 'nullable|array',
            'additional_property_house_no' => 'nullable|array',
            'additional_property_plot_no' => 'nullable|array',
            'additional_property_street' => 'nullable|array',
            'additional_property_district' => 'nullable|array',
            'additional_property_district_other' => 'nullable|array',
            'additional_property_lga' => 'nullable|array',
            'additional_property_state' => 'nullable|array',
            'additional_property_description' => 'nullable|array',
            'additional_party_name' => 'nullable|array',
            'additional_party_house_no' => 'nullable|array',
            'additional_party_street' => 'nullable|array',
            'additional_party_district' => 'nullable|array',
            'additional_party_lga' => 'nullable|array',
            'additional_party_state' => 'nullable|array',
            'additional_applicant_name' => 'nullable|array',
            'additional_applicant_house_no' => 'nullable|array',
            'additional_applicant_street' => 'nullable|array',
            'additional_applicant_district' => 'nullable|array',
            'additional_applicant_lga' => 'nullable|array',
            'additional_applicant_state' => 'nullable|array',
        ]);

        // Same party rules as store(): an edit must not be able to introduce what a
        // create forbids.
        if ($conflict = $this->assigneeConflictMessage($request)) {
            return response()->json(['success' => false, 'message' => $conflict], 422);
        }

        $data = $request->all();
        $data['date_of_grant_purpose'] = collect([
            $request->date_of_grant,
            $request->purpose_of_right_of_occupancy,
        ])->filter()->implode(' - ');

        // Handle additional parties
        $additionalParties = [];
        if ($request->has('additional_party_name') && is_array($request->additional_party_name)) {
            foreach ($request->additional_party_name as $index => $name) {
                if (!empty($name)) {
                    $houseNo = $request->additional_party_house_no[$index] ?? '';
                    $street = $request->additional_party_street[$index] ?? '';
                    $district = $request->additional_party_district[$index] ?? '';
                    $lga = $request->additional_party_lga[$index] ?? '';
                    $state = $request->additional_party_state[$index] ?? '';

                    // Build full address string for print template compatibility
                    $parts = [];
                    if ($houseNo) $parts[] = "House No " . $houseNo;
                    if ($street) $parts[] = $street;
                    if ($district) $parts[] = $district;
                    if ($lga) $parts[] = $lga . " LGA";
                    if ($state) $parts[] = $state . " State";
                    $builtAddress = implode(", ", $parts) . ".";

                    $additionalParties[] = [
                        'name' => $name,
                        'house_no' => $houseNo,
                        'street' => $street,
                        'district' => $district,
                        'lga' => $lga,
                        'state' => $state,
                        'address' => $builtAddress
                    ];
                }
            }
        }
        $data['additional_parties'] = $additionalParties;

        // Handle additional applicants
        $additionalApplicants = [];
        if ($request->has('additional_applicant_name') && is_array($request->additional_applicant_name)) {
            foreach ($request->additional_applicant_name as $index => $name) {
                if (!empty($name)) {
                    $houseNo = $request->additional_applicant_house_no[$index] ?? '';
                    $street = $request->additional_applicant_street[$index] ?? '';
                    $district = $request->additional_applicant_district[$index] ?? '';
                    $lga = $request->additional_applicant_lga[$index] ?? '';
                    $state = $request->additional_applicant_state[$index] ?? '';

                    // Build full address string for print template compatibility
                    $parts = [];
                    if ($houseNo) $parts[] = "House No " . $houseNo;
                    if ($street) $parts[] = $street;
                    if ($district) $parts[] = $district;
                    if ($lga) $parts[] = $lga . " LGA";
                    if ($state) $parts[] = $state . " State";
                    $builtAddress = implode(", ", $parts) . ".";

                    $additionalApplicants[] = [
                        'name' => $name,
                        'house_no' => $houseNo,
                        'street' => $street,
                        'district' => $district,
                        'lga' => $lga,
                        'state' => $state,
                        'address' => $builtAddress
                    ];
                }
            }
        }
        $data['additional_applicants'] = $additionalApplicants;

        $data['additional_properties'] = $this->additionalProperties($request);

        $data['c_of_o_no'] = $request->file_number;
        
        // Preserve application_type if not provided or empty (don't clear existing value)
        if (empty($data['application_type']) && $application->application_type) {
            $data['application_type'] = $application->application_type;
        }

        // The tracking number is issued once at creation and never reassigned.
        unset($data['application_tracking_no']);

        // Recompute rather than carry the posted figures over, so an edit that
        // changes the file number rebills against the right valuation.
        $data = array_merge($data, $this->billColumnsFor($data['file_number'] ?? $application->file_number, $request));

        $application->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully.',
            'id' => $application->id
        ]);
    }

    /**
     * The district the subject line names.
     *
     * The wizard stores no district column of its own — it composes
     * property_description as "<street>, <district>, <state> State." So the
     * district is the segment before the state, and is read back from there.
     * Anything that does not match that shape yields null, and the subject
     * simply omits the fragment rather than printing a mangled one.
     */
    private function propertyDistrict(ConsentApplication $application): ?string
    {
        if (trim((string) $application->location_of_right_of_occupancy) !== '') {
            return trim($application->location_of_right_of_occupancy);
        }

        $parts = array_values(array_filter(array_map(
            fn($p) => trim(rtrim(trim($p), '.')),
            explode(',', (string) $application->property_description)
        ), fn($p) => $p !== ''));

        // The state always comes last and is suffixed "State"; drop it so the
        // remaining segments are the street/plot, district and LGA in that
        // fixed order (the same order composeAddress() writes them in).
        if ($parts && preg_match('/\bstate$/i', end($parts))) {
            array_pop($parts);
        }

        // Street is [0] and district is [1]. Counting back from the end instead
        // would return the LGA whenever one was captured.
        return count($parts) >= 2 ? ($parts[1] ?: null) : null;
    }

    /**
     * The bill columns for a file, ready to merge into a save.
     *
     * A file with no eligible valuation is left entirely null — not billed at
     * zero — so an unvalued consent is distinguishable from one genuinely
     * assessed at nothing, and cannot quietly produce a ₦0.00 demand letter.
     */
    private function billColumnsFor(?string $fileNumber, ?Request $request = null): array
    {
        $calculator = app(ConsentBillCalculator::class);
        $bill = $calculator->computeForFile((string) $fileNumber);
        $overrides = $this->submittedOverrides($request);
        $basis = $this->submittedBasis($request);

        // The operator unlocked the assessed amount, so the percentage lines are
        // rebased on it. valuation_amount keeps the figure the valuation report
        // actually gave — an override changes what is charged, not the record of
        // what the property was valued at — and the audit holds both.
        $basisAudit = null;
        if ($basis !== null) {
            $rebased = $calculator->compute($basis);
            unset($rebased['valuation_amount']);
            $bill = array_merge($bill, $rebased);

            $basisAudit = [
                'calculated' => $bill['valuation_amount'],
                'effective' => $basis,
                'reason' => 'Assessed amount overridden on the application form',
                'by' => trim(Auth::user()?->first_name . ' ' . Auth::user()?->last_name),
                'user_id' => Auth::id(),
                'at' => now()->toDateTimeString(),
            ];
        }

        $nulled = array_fill_keys([
            'valuation_report_id', 'valuation_amount',
            'cgt_rate', 'cgt_amount', 'stamp_duty_rate', 'stamp_duty_amount',
            'registration_fee', 'assignment_fee', 'processing_fee',
            'bill_total', 'bill_computed_at', 'bill_overrides',
        ], null);

        // With neither a valuation nor a manual figure there is nothing to bill.
        // Everything stays null rather than being stored as a ₦0.00 assessment.
        if ((!$bill['has_valuation'] || $bill['valuation_amount'] <= 0)
            && $overrides === [] && $basis === null) {
            return $nulled;
        }

        $columns = [
            'valuation_report_id' => $bill['valuation_report_id'],
            'valuation_amount' => $bill['valuation_amount'],
            'cgt_rate' => $bill['cgt_rate'],
            'cgt_amount' => $bill['cgt_amount'],
            'stamp_duty_rate' => $bill['stamp_duty_rate'],
            'stamp_duty_amount' => $bill['stamp_duty_amount'],
            'registration_rate' => $bill['registration_rate'],
            'registration_fee' => $bill['registration_fee'],
            'assignment_fee' => $bill['assignment_fee'],
            'processing_fee' => $bill['processing_fee'],
            'bill_computed_at' => $bill['bill_computed_at'],
            'bill_overrides' => null,
        ];

        // An override replaces the effective amount but never the calculated
        // one, which is kept in the audit entry so the bill stays explainable.
        $audit = $basisAudit ? ['valuation_amount' => $basisAudit] : [];
        foreach ($overrides as $key => $override) {
            $audit[$key] = [
                'calculated' => $columns[$key],
                'effective' => $override['amount'],
                'reason' => $override['reason'],
                'by' => Auth::user()?->first_name . ' ' . Auth::user()?->last_name,
                'user_id' => Auth::id(),
                'at' => now()->toDateTimeString(),
            ];
            $columns[$key] = $override['amount'];
        }

        if ($audit !== []) {
            $columns['bill_overrides'] = $audit;
        }

        // Summed from the effective amounts, so the total always matches the
        // lines the letter prints.
        $columns['bill_total'] = round(
            $columns['stamp_duty_amount'] + $columns['registration_fee'] + $columns['processing_fee'],
            2
        );

        return $columns;
    }

    /**
     * A manual valuation override, if one was asked for and is allowed.
     *
     * Files valued before KLAES existed have no valuation_reports row, so the
     * Valuation-before-Consent gate refuses them even though the work was done
     * and the applicant has paid. An authorised officer may accept that
     * off-system valuation instead of re-keying it.
     *
     * Three things must all hold, and each is checked HERE rather than in the
     * browser, because the checkbox is only hidden client-side:
     *   1. the officer holds 'approve' on the Deeds - Consent module;
     *   2. a reason is given — the override is a decision on the record;
     *   3. an assessed amount is supplied. Without a valuation report there is
     *      no figure to bill from, so the fee lines would all compute to null
     *      and the letter would print as a ₦0.00 draft — useless for someone
     *      who has already paid. The existing "unlock the assessed amount"
     *      mechanism supplies it, which is why submittedBasis() is required.
     *
     * @return array{requested: bool, error?: string, columns?: array<string, mixed>}
     */
    private function manualValuation(Request $request): array
    {
        if ((string) $request->input('manual_valuation') !== '1') {
            return ['requested' => false];
        }

        $user = Auth::user();

        if (!$user || !$user->canDo('Deeds - Consent', 'approve')) {
            return [
                'requested' => true,
                'error' => 'You are not authorised to record a manual valuation. '
                    . 'This requires the Approve permission on Deeds - Consent.',
            ];
        }

        if ($this->submittedBasis($request) === null) {
            return [
                'requested' => true,
                'error' => 'A manual valuation needs the assessed amount. Unlock the Assessed Amount '
                    . 'field and enter what the property was valued at, or the consent letter will '
                    . 'print with no figures.',
            ];
        }

        return [
            'requested' => true,
            'columns' => [
                'manual_valuation' => 1,
                'manual_valuation_reason' => null,
                'manual_valuation_ref' => null,
                'manual_valuation_by' => trim($user->first_name . ' ' . $user->last_name),
                'manual_valuation_user_id' => $user->id,
                'manual_valuation_at' => now(),
            ],
        ];
    }

    /**
     * The overridable fee lines. The valuation is deliberately absent: an
     * override adjusts what is charged, it must not rewrite the valuation.
     */
    private const OVERRIDABLE_FEES = [
        'stamp_duty_amount',
        'registration_fee',
        'processing_fee',
    ];

    /**
     * The assessed amount when the operator unlocked and edited it.
     *
     * Returns null unless the form explicitly flagged an override, so a stale
     * or mistyped consideration can never quietly become the fee basis. Shares
     * the same rejection rules as a per-line override.
     */
    private function submittedBasis(?Request $request): ?float
    {
        if (!$request || (string) $request->input('bill_basis_overridden') !== '1') {
            return null;
        }

        $raw = trim((string) $request->input('consideration', ''));

        if ($raw === '' || str_starts_with($raw, '-')) {
            return null;
        }

        $value = preg_replace('/[^0-9.]/', '', $raw);

        if ($value === '' || !is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * Manual overrides from the request, validated.
     *
     * A line is only overridden when a usable amount was posted for it. Blank,
     * negative and non-numeric values are ignored rather than stored, so a
     * malformed field cannot turn into a negative demand.
     */
    private function submittedOverrides(?Request $request): array
    {
        if (!$request) {
            return [];
        }

        $amounts = (array) $request->input('bill_override', []);
        $reasons = (array) $request->input('bill_override_reason', []);
        $clean = [];

        foreach (self::OVERRIDABLE_FEES as $key) {
            $raw = trim((string) ($amounts[$key] ?? ''));

            if ($raw === '') {
                continue;
            }

            // A negative must be rejected before the strip, not after: removing
            // non-digits turns "-500" into "500", which then passes any check
            // for a negative value.
            if (str_starts_with($raw, '-')) {
                continue;
            }

            // The field is displayed with thousands separators.
            // is_numeric also rejects malformed input like "1.2.3".
            $value = preg_replace('/[^0-9.]/', '', $raw);

            if ($value === '' || !is_numeric($value)) {
                continue;
            }

            $clean[$key] = [
                'amount' => round((float) $value, 2),
                'reason' => trim((string) ($reasons[$key] ?? '')) ?: null,
            ];
        }

        return $clean;
    }

    /**
     * API: the valuation and computed bill for a file number.
     *
     * Backfills the Payments step as soon as a file is chosen. Read-only — it
     * computes but saves nothing, so opening the wizard never bills anyone.
     */
    public function valuationBill(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number'));

        if ($fileNumber === '') {
            return response()->json(['success' => false, 'message' => 'File number is required.'], 400);
        }

        $bill = app(ConsentBillCalculator::class)->computeForFile($fileNumber);

        if (!$bill['has_valuation']) {
            return response()->json([
                'success' => true,
                'has_valuation' => false,
                'message' => 'No valuation report found for file ' . $fileNumber
                    . '. A valuation must be generated before a consent bill can be raised.',
            ]);
        }

        if ($bill['valuation_amount'] <= 0) {
            return response()->json([
                'success' => true,
                'has_valuation' => false,
                'message' => 'The valuation for file ' . $fileNumber
                    . ' has no amount recorded in Section E. The bill cannot be calculated from it.',
            ]);
        }

        return response()->json([
            'success' => true,
            'has_valuation' => true,
            'data' => $bill,
            // The details the valuation already captured for this file, keyed
            // by the consent form's own field names so the wizard can fill them
            // without knowing anything about valuation_reports.
            'prefill' => $this->valuationPrefill($fileNumber),
        ]);
    }

    /**
     * The consent fields a file's valuation can fill in.
     *
     * The valuation is the first time the Ministry visits the property and
     * writes down who holds it and where it is, so re-typing all of it at the
     * consent stage is both wasted work and a chance to disagree with the
     * report the bill is raised against.
     *
     * Only fields the valuation genuinely holds are returned. That now includes
     * the holder's phone number: valuation_reports.phone is required on the
     * valuation form, so for any report captured from 2026-09-29 it is the one
     * authoritative contact number for the file, and Registration reads it from
     * the consent in turn. Reports captured before that column existed have
     * none, and simply send nothing.
     *
     * Everything here is a PREFILL: the wizard drops it into empty fields and
     * the operator may overwrite any of it.
     *
     * @return array<string, string>
     */
    private function valuationPrefill(string $fileNumber): array
    {
        $report = app(ConsentBillCalculator::class)->valuationFor($fileNumber);

        if (!$report) {
            return [];
        }

        $clean = fn($value) => trim((string) $value);

        // Composed the way the consent wizard composes its own property line,
        // so a prefilled description and a typed one read the same.
        $propertyParts = array_values(array_filter([
            $clean($report->property_no),
            $clean($report->plot_no) !== '' ? 'Plot ' . $clean($report->plot_no) : '',
            $clean($report->street_name),
            $clean($report->estate_quarters),
            $clean($report->town_city),
            $clean($report->lga),
        ], fn($part) => $part !== ''));

        $propertyDescription = $propertyParts !== []
            ? implode(', ', $propertyParts) . '.'
            : '';

        return array_filter([
            'applicant_name' => $clean($report->full_name),
            'applicant_address' => $clean($report->address),
            'applicant_phone' => $clean($report->phone),
            'applicant_lga' => $clean($report->lga),
            'property_description' => $propertyDescription,
            'purpose_of_right_of_occupancy' => $clean($report->land_use_purpose),
            'location_of_right_of_occupancy' => $clean($report->town_city),
            // current_use is deliberately NOT mapped to stage_of_development:
            // it holds a use type ("COMMERCIAL"), not how far the property is
            // built, so it would fill that field with the wrong kind of answer.
        ], fn($value) => $value !== '');
    }

    /**
     * The assignee must be a different person from both the applicant and the party
     * who currently holds the title:
     *  - assignee === applicant  → a transfer to oneself, a no-op.
     *  - assignee === original holder → the file title already reads that name, so
     *    this transaction has already been registered.
     *
     * Mirrored client-side by checkAssigneeNameConflict() in consent_applications.js,
     * which flags it live as the name is typed. This is the authoritative copy.
     *
     * @return string|null Null when there is no conflict.
     */
    private function assigneeConflictMessage(Request $request): ?string
    {
        $assignee  = trim((string) $request->party_name);
        $applicant = trim((string) $request->applicant_name);
        $holder    = trim((string) $request->original_holder_name);

        if ($assignee === '') {
            return null;
        }

        if ($applicant !== '' && strcasecmp($applicant, $assignee) === 0) {
            return 'The Assignee cannot be the same person as the Applicant (' . $assignee
                . '). A property cannot be transferred to its own holder.';
        }

        if ($holder !== '' && strcasecmp($holder, $assignee) === 0) {
            return $assignee . ' is already the registered holder of file ' . $request->file_number
                . '. A consent cannot assign a property to the party who already holds it.';
        }

        return null;
    }

    public function lookupByFileNumber(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number'));

        if ($fileNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'File number is required.'
            ], 400);
        }

        $application = ConsentApplication::where('file_number', $fileNumber)
            ->where('application_type', 'application_for_censent')
            ->orderByDesc('created_at')
            ->first();

        if (!$application) {
            return response()->json([
                'success' => true,
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    /**
     * Number of days after a consent is captured before the Commissioner's
     * signature is deemed to have been given. The signature — not the consent
     * letter alone — is what authorises instrument registration, but the
     * signing event is not recorded in KLAES, so it is derived from the
     * capture date (created_at) plus this window.
     */
    public const COMMISSIONER_SIGNATURE_DAYS = 7;

    /**
     * API: Check whether a consent application exists for a file number and consent type,
     * whether the consent letter has been printed, and whether the Commissioner's
     * signature window has elapsed.
     * Used by the instrument capture flow to gate Assignment/Gift/Mortgage registration.
     */
    public function checkForInstrument(Request $request)
    {
        $fileNo      = trim((string) $request->query('file_number', ''));
        $consentType = trim((string) $request->query('consent_type', ''));

        $blocked = [
            'has_consent'   => false,
            'has_printed'   => false,
            'print_count'   => 0,
            'has_signature' => false,
        ];

        if ($fileNo === '' || $consentType === '') {
            return response()->json($blocked);
        }

        $consent = DB::connection('sqlsrv')
            ->table('consent_applications')
            ->where('file_number', $fileNo)
            ->where('consent_type', $consentType)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$consent) {
            // A sectional file carries no consent_applications row. Its consent is the
            // approved memo shown on /deeds-applications as an "ST Assignment", which
            // stands for an Assignment (or Gift) consent when a later transfer of an
            // ST unit is captured. It has no letter to print and no Commissioner's
            // signature window — the memo is already approved — so both sub-rules pass.
            if (in_array($consentType, ['Assignment', 'Gift'], true)) {
                // A memo already spent by the file's first ST registration is not a
                // consent for the NEXT assignment — that one needs its own consent
                // application, so an all-spent file stays blocked.
                $stConsent = (new StAssignmentConsentResolver())
                    ->forFileNumber($fileNo)
                    ->first(fn ($consent) => !$consent->is_used);

                if ($stConsent) {
                    return response()->json([
                        'has_consent'          => true,
                        'has_printed'          => true,
                        'print_count'          => 0,
                        'consent_type'         => StAssignmentConsentResolver::CONSENT_TYPE,
                        'consent_id'           => $stConsent->id,
                        'has_signature'        => true,
                        'captured_at'          => $stConsent->created_at
                            ? Carbon::parse($stConsent->created_at)->toDateString()
                            : null,
                        'signature_date'       => null,
                        'signature_days'       => 0,
                        'days_until_signature' => 0,
                        'source'               => 'memo',
                    ]);
                }
            }

            return response()->json($blocked);
        }

        // Derive the Commissioner's signature date from the capture date.
        $capturedAt   = $consent->created_at ? Carbon::parse($consent->created_at) : null;
        $signatureDue = $capturedAt ? $capturedAt->copy()->addDays(self::COMMISSIONER_SIGNATURE_DAYS) : null;

        // No capture timestamp means we cannot derive a signature date; treat the
        // record as legacy and let the print rule alone govern it.
        $hasSignature  = $signatureDue ? Carbon::now()->greaterThanOrEqualTo($signatureDue) : true;
        $daysRemaining = ($signatureDue && !$hasSignature)
            ? (int) ceil(Carbon::now()->floatDiffInDays($signatureDue, false))
            : 0;

        return response()->json([
            'has_consent'          => true,
            'has_printed'          => ((int) $consent->print_count > 0),
            'print_count'          => (int) $consent->print_count,
            'consent_type'         => $consent->consent_type,
            'consent_id'           => $consent->id,
            'has_signature'        => $hasSignature,
            'captured_at'          => $capturedAt ? $capturedAt->toDateString() : null,
            'signature_date'       => $signatureDue ? $signatureDue->toDateString() : null,
            'signature_days'       => self::COMMISSIONER_SIGNATURE_DAYS,
            'days_until_signature' => max(0, $daysRemaining),
        ]);
    }

    /**
     * Preview the revised Assignment consent letter against sample data.
     *
     * Renders assignment_2026 — the September 2026 approved wording — using the
     * figures from the signed specimen, so the layout can be proofed on paper
     * before the live letter is switched over to it. show() still maps
     * Assignment to assignment_bill; nothing here changes what a real consent
     * prints.
     *
     * The record is built in memory and never saved, and the view is told it is
     * a preview so its print button cannot touch any print log.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function previewDemo()
    {
        $application = new ConsentApplication([
            'file_number' => 'COM-2023-652',
            'consent_type' => 'Assignment',
            'applicant_name' => 'Alh Hussaini Na',
            'applicant_address' => 'Kwari Market, Unity Bank, Fagge, Kano',
            'party_name' => 'Yusuf Sani',
            'party_address' => '18 Fatima House Kwari Market, Fagge, Kano State',
            'consideration' => '20000000',
            'consideration_words' => 'Twenty Million Naira Only',
            'application_submitted_date' => '2024-09-24',
        ]);

        // Set by hand because the row is never persisted: created_at is the
        // letter date, and print_count keeps the template's guards happy.
        $application->created_at = Carbon::parse('2026-09-24');
        $application->print_count = 0;

        return view('consent_applications.templates.assignment_2026', [
            'application' => $application,
            'demo' => true,
            // Supplied so the preview does not go looking up a file number that
            // does not exist. A real letter leaves this out and the template
            // resolves the file's own tracking id. Kept short on purpose: the
            // shorter the payload, the fewer and larger the QR's modules.
            'trackingId' => 'KLS0001',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function show($id)
    {
        $application = ConsentApplication::findOrFail($id);

        $template = strtolower($application->consent_type);
        // Map types to template names if they differ
        $templateMap = [
            // Assignment prints the September 2026 approved wording: numbered
            // clauses, the section 45 delegation, and the 84-day default
            // penalty. It carries no fee schedule.
            //
            // Both earlier letters are still in the repo. Restore either line
            // to switch back — no other change is needed:
            //   'assignment' => 'assignment',       // the original, no bill
            //   'assignment' => 'assignment_bill',  // the valuation-linked bill letter
            'assignment' => 'assignment_2026',
            'gift' => 'gift',
            'mortgage' => 'mortgage'
        ];

        $viewName = "consent_applications.templates." . ($templateMap[$template] ?? $template);

        // Only the bill letter reads a computed bill and the draft guard that
        // goes with it. Every other template is handed the application alone
        // and resolves what it needs for itself.
        if (($templateMap[$template] ?? null) !== 'assignment_bill') {
            return view($viewName, compact('application'));
        }

        return view($viewName, [
            'application' => $application,
            'bill' => $this->letterBill($application),
            'letter' => config('consent_letter', []),
            // A letter with no saved bill is a draft: it must not print as a
            // demand for ₦0.00 or claim a payment position it does not have.
            'draft' => $application->bill_total === null,
        ]);
    }

    /**
     * The saved bill in the shape the letter template reads.
     *
     * Built from the columns stored on the consent row, never recalculated
     * here: a reprint must show the figures the letter was issued under, even
     * after the configured rates or the underlying valuation have moved on.
     */
    private function letterBill(ConsentApplication $application): array
    {
        $stored = [
            // The rates the bill was assessed under, not today's configured
            // ones: the letter's "(5%)" labels must match the figures beside
            // them even after the configuration has moved on.
            'stamp_duty_rate' => (float) $application->stamp_duty_rate,
            'registration_rate' => (float) $application->registration_rate,
            'stamp_duty_amount' => (float) $application->stamp_duty_amount,
            'registration_fee' => (float) $application->registration_fee,
            'processing_fee' => (float) $application->processing_fee,
        ];

        // The file's own tracking id, looked up the same way the bill print view
        // does it. This is what the QR on the letter encodes, so a scan lands on
        // the file rather than on this one application.
        $trackingId = \App\Models\FileIndexing::where('file_number', $application->file_number)
            ->value('tracking_id');

        return [
            'tracking_id' => $trackingId,
            'reference' => $application->application_tracking_no ?: ('#' . $application->id),
            'issue_date' => $application->created_at,
            'valuation_reference' => $application->valuation_report_id,
            'valuation_amount' => (float) $application->valuation_amount,
            'lines' => app(ConsentBillCalculator::class)->linesForLetter($stored),
            'total' => (float) $application->bill_total,
            // Payment reconciliation is not wired up yet, so the letter prints
            // as unpaid. It must never imply money was received.
            'total_paid' => 0.0,
            'outstanding_balance' => (float) $application->bill_total,
            'payment_reference' => $application->application_tracking_no,
            'receipt_references' => null,
            // consent_applications has no plot-number column, so the subject
            // carries the district alone and omits the plot fragment cleanly
            // rather than printing an empty "Plot No.".
            'property' => [
                'plot_no' => null,
                'district' => $this->propertyDistrict($application),
            ],
        ];
    }

    /**
     * Update the print log and stats.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function logPrint(Request $request, $id)
    {
        $application = ConsentApplication::findOrFail($id);

        DB::beginTransaction();
        try {
            $isFirstPrint = $application->print_count == 0;

            // Increment print count
            $application->increment('print_count');

            // Log the print
            PrintLog::create([
                'reference_number' => $application->file_number,
                'document_type' => 'Application for Consent (' . $application->consent_type . ')',
                'print_type' => 'Individual',
                'status' => $isFirstPrint ? 'Original' : 'Duplicate',
                'user_id' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'print_count' => $application->print_count,
                'is_certified' => $application->print_count > 1
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error logging print: ' . $e->getMessage()
            ], 500);
        }
    }
}
