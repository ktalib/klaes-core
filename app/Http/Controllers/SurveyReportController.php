<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Lga;
use App\Models\CadastralOfficer;
use App\Models\LandOfficer;
use App\Models\StreetName;
use App\Models\SurveyReportRequest;
use App\Services\LandOfficerSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SurveyReportController extends Controller
{
    protected LandOfficerSignatureService $landOfficerSignatureService;

    public function __construct(LandOfficerSignatureService $landOfficerSignatureService)
    {
        $this->landOfficerSignatureService = $landOfficerSignatureService;
    }

    public function index(Request $request)
    {
        $requests = SurveyReportRequest::orderBy('created_at', 'desc')->get();
        $urlContext = $request->query('url', '');

        return view('survey_report.index', compact('requests', 'urlContext'));
    }

    public function create(Request $request)
    {
        $urlContext = $request->query('url', '');
        $prefillFileNo = $request->query('file_no', '');
        $prefillFileTitle = $request->query('file_title', '');

        return view('survey_report.partials.modal', array_merge(
            compact('urlContext', 'prefillFileNo', 'prefillFileTitle'),
            $this->getLocationOptions()
        ));
    }

    public function store(Request $request)
    {
        $data = $this->validateSurveyReportPayload($request);
        $data = $this->sanitizeSurveyReportData($request, $data);

        if (in_array($request->input('url_context'), ['lands', 'lands12'], true)) {
            $this->applyLandsSignatureData($request, $data);
        }

        if ($request->input('url_context') === 'cadastral') {
            $this->applyCadastralSignatureData($request, $data);
        }

        $data['user_id'] = Auth::id();
        $data['status'] = in_array($request->input('url_context'), ['lands', 'lands12']) ? 'Sent to Cadastral' : 'Generated';

        $requestRecord = SurveyReportRequest::create($data);

        // LAAS Portal (spec e/f): if this Land 12 is for a file that came from
        // the public portal, move that application on and show it to the
        // applicant. No-ops silently — and never throws — for every other file.
        if (!empty($data['file_number'])) {
            $laas = app(\App\Services\Laas\LaasWorkflowService::class);

            $laas->advanceByFileNumber(
                $data['file_number'],
                \App\Models\Laas\LaasApplication::STAGE_LAND12_RAISED,
                [
                    'title'   => 'Survey request raised',
                    'body'    => 'A request for survey report (Land 12) has been raised on your file.',
                    'columns' => ['survey_report_request_id' => $requestRecord->id],
                ]
            );

            if ($data['status'] === 'Sent to Cadastral') {
                $laas->advanceByFileNumber(
                    $data['file_number'],
                    \App\Models\Laas\LaasApplication::STAGE_AT_CADASTRAL,
                    [
                        'title' => 'With Cadastral',
                        'body'  => 'Your survey request has been sent to the Cadastral department.',
                    ]
                );
            }
        }

        $this->forgetSignatureVerification('lands');
        $this->forgetSignatureVerification('cadastral');

        return response()->json([
            'success' => true,
            'message' => 'Survey report request generated successfully.',
            'data' => $requestRecord,
        ]);
    }

    public function show(Request $request, $id)
    {
        $requestRecord = SurveyReportRequest::findOrFail($id);
        $isView = true;
        $urlContext = $request->query('url', '');

        return view('survey_report.partials.modal', array_merge(
            compact('requestRecord', 'isView', 'urlContext'),
            $this->getLocationOptions()
        ));
    }

    public function edit(Request $request, $id)
    {
        $requestRecord = SurveyReportRequest::findOrFail($id);
        $urlContext = $request->query('url', '');

        return view('survey_report.partials.modal', array_merge(
            compact('requestRecord', 'urlContext'),
            $this->getLocationOptions()
        ));
    }

    public function update(Request $request, $id)
    {
        $requestRecord = SurveyReportRequest::findOrFail($id);

        $data = $this->validateSurveyReportPayload($request);
        $data = $this->sanitizeSurveyReportData($request, $data);

        if (in_array($request->input('url_context'), ['lands', 'lands12'], true)) {
            $this->applyLandsSignatureData($request, $data);
        }

        if ($request->input('url_context') === 'cadastral') {
            $this->applyCadastralSignatureData($request, $data);
            $data['status'] = 'Completed';
        }

        unset($data['id'], $data['serial_no'], $data['created_at']);
        $requestRecord->update($data);

        // LAAS Portal (spec g): Cadastral has returned the completed survey
        // report. This also opens the recommendation stage and puts the alert on
        // the Land Office desk (spec h) — see LaasWorkflowService.
        if (($data['status'] ?? null) === 'Completed' && !empty($requestRecord->file_number)) {
            app(\App\Services\Laas\LaasWorkflowService::class)->advanceByFileNumber(
                $requestRecord->file_number,
                \App\Models\Laas\LaasApplication::STAGE_LAND12_COMPLETED,
                [
                    'title' => 'Survey report completed',
                    'body'  => 'Cadastral has completed the survey report on your file and returned it to the Land Office.',
                ]
            );
        }

        $this->forgetSignatureVerification('lands');
        $this->forgetSignatureVerification('cadastral');

        return response()->json([
            'success' => true,
            'message' => 'Survey report request updated successfully.',
            'data' => $requestRecord,
        ]);
    }

    private function validateSurveyReportPayload(Request $request): array
    {
        return $request->validate(
            $this->surveyReportRules($request),
            $this->surveyReportMessages()
        );
    }

    private function surveyReportRules(Request $request): array
    {
        $urlContext = (string) $request->input('url_context', '');
        $isLands = in_array($urlContext, ['lands', 'lands12'], true);
        $isCadastral = $urlContext === 'cadastral';

        return [
            'url_context' => ['nullable', 'string'],
            'file_number' => ['required', 'string', 'max:100'],
            'file_title' => ['required', 'string', 'max:255'],
            'lands_section_date' => [Rule::requiredIf(!$isCadastral), 'nullable', 'date'],
            'lands_section_name' => [Rule::requiredIf(!$isCadastral), 'nullable', 'string', 'max:255'],
            'lands_section_rank' => [Rule::requiredIf(!$isCadastral), 'nullable', 'string', 'max:100'],
            'land_officer_id' => [Rule::requiredIf($isLands), 'nullable', 'integer'],
            'signature_verification_method' => [Rule::requiredIf($isLands), 'nullable', Rule::in(['email', 'sms', 'password'])],
            'signature_verified' => ['nullable', 'string'],
            'verification_code' => ['nullable', 'string', 'max:10'],
            'verification_password' => ['nullable', 'string', 'min:6'],
            'director_cadastral_date' => ['nullable', 'date'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'director_rank' => ['nullable', 'string', 'max:100'],
            'director_signature' => ['nullable', 'string', 'max:255'],
            'rofo_no' => [Rule::requiredIf(!$isLands), 'nullable', 'string', 'max:100'],
            'item_g_no' => [Rule::requiredIf(!$isLands), 'nullable', 'string', 'max:100'],
            'plot_description' => [Rule::requiredIf(!$isLands), 'nullable', 'string', 'max:1000'],
            'survey_necessary' => [Rule::requiredIf(!$isLands), 'nullable', 'string', 'max:500'],
            'beacon_numbers' => ['nullable', 'string', 'max:2000'],
            'director_cadastral_approval_date' => ['nullable', 'date'],
            'commissioner_date' => ['nullable', 'date'],
            'director_cadastral_signature_method' => [Rule::requiredIf($isCadastral), 'nullable', Rule::in(['email', 'sms', 'password'])],
            'director_cadastral_signature_verified' => ['nullable', 'string'],
            'director_cadastral_verification_code' => ['nullable', 'string', 'max:10'],
            'director_cadastral_verification_password' => ['nullable', 'string', 'min:6'],
        ];
    }

    private function surveyReportMessages(): array
    {
        return [
            'file_number.required' => 'Select a file number before continuing.',
            'file_title.required' => 'File title is required.',
            'lands_section_date.required' => 'Select the Lands section date.',
            'lands_section_name.required' => 'Officer details are required.',
            'lands_section_rank.required' => 'Select the Lands officer rank.',
            'land_officer_id.required' => 'A signing officer is required before you can submit this request.',
            'signature_verification_method.required' => 'Select how the Lands officer signature will be verified.',
            'rofo_no.required' => 'ROFO number is required.',
            'item_g_no.required' => 'Item G number is required.',
            'plot_description.required' => 'Complete the property location details so the plot description can be generated.',
            'survey_necessary.required' => 'State whether survey will be necessary.',
            'director_cadastral_signature_method.required' => 'Select how the Director Cadastral signature will be verified.',
        ];
    }

    private function sanitizeSurveyReportData(Request $request, array $data): array
    {
        unset(
            $data['url_context'],
            $data['signature_verified'],
            $data['verification_code'],
            $data['verification_password'],
            $data['director_cadastral_signature_verified'],
            $data['director_cadastral_verification_code'],
            $data['director_cadastral_verification_password']
        );

        if ($request->input('url_context') === 'cadastral') {
            unset(
                $data['lands_section_date'],
                $data['lands_section_name'],
                $data['lands_section_rank'],
                $data['land_officer_id'],
                $data['signature_verification_method']
            );
        }

        return $data;
    }

    private function applyLandsSignatureData(Request $request, array &$data): void
    {
        $verificationMethod = (string) $request->input('signature_verification_method', '');
        $officerId = (int) $request->input('land_officer_id');

        if (!$officerId) {
            $officerId = (int) Auth::id();
        }

        if ($request->input('signature_verified') !== '1') {
            throw ValidationException::withMessages([
                'signature_verification_method' => 'Verify the Lands officer signature before sending the request to Cadastral.',
            ]);
        }

        if (!$officerId || !$verificationMethod) {
            throw ValidationException::withMessages([
                'land_officer_id' => 'Please select a signing officer and verification method.',
            ]);
        }

        $landOfficer = $this->resolveLandOfficer($officerId);
        if (!$landOfficer && $verificationMethod !== 'password') {
            throw ValidationException::withMessages([
                'land_officer_id' => 'Selected signing officer was not found.',
            ]);
        }

        if ($verificationMethod === 'password') {
            if (!Auth::check()) {
                throw ValidationException::withMessages([
                    'signature_verification_method' => 'You must be logged in to confirm by password.',
                ]);
            }

            if ($landOfficer && !empty($landOfficer->user_id) && (int) $landOfficer->user_id !== (int) Auth::id()) {
                throw ValidationException::withMessages([
                    'land_officer_id' => 'Password verification can only be used for your own officer profile.',
                ]);
            }
        }

        $resolvedSignature = $landOfficer->signature_file ?? $landOfficer->user?->signature ?? null;
        if ($landOfficer && empty($resolvedSignature)) {
            throw ValidationException::withMessages([
                'land_officer_id' => 'Selected signing officer has no digital signature configured.',
            ]);
        }

        $resolvedOfficerId = $landOfficer->id ?? $officerId;
        $this->assertSignatureVerification(
            'lands',
            $resolvedOfficerId,
            $verificationMethod,
            'Verify the Lands officer signature again before submitting this request.'
        );

        $data['land_officer_id'] = $resolvedOfficerId;
        $data['lands_section_name'] = $landOfficer->name ?? trim((string) ((Auth::user()->first_name ?? '') . ' ' . (Auth::user()->last_name ?? '')));
        $data['lands_section_rank'] = $landOfficer->rank ?? $landOfficer->user?->rank ?? ($request->input('lands_section_rank') ?: 'Assistant Director');
        $data['lands_section_signature'] = $resolvedSignature;
        $data['signature_verification_method'] = $verificationMethod;
        $data['signature_verified_at'] = now();
        $data['signature_approved_by'] = Auth::id();
    }

    private function applyCadastralSignatureData(Request $request, array &$data): void
    {
        $dcMethod = (string) $request->input('director_cadastral_signature_method', '');
        $dcOfficerId = (int) Auth::id();

        if ($request->input('director_cadastral_signature_verified') !== '1') {
            throw ValidationException::withMessages([
                'director_cadastral_signature_method' => 'Verify the Director Cadastral signature before submitting this request.',
            ]);
        }

        if (!$dcMethod) {
            throw ValidationException::withMessages([
                'director_cadastral_signature_method' => 'Please select a verification method for Director Cadastral.',
            ]);
        }

        $dcOfficer = $this->resolveLandOfficer($dcOfficerId);

        if ($dcMethod === 'password' && !Auth::check()) {
            throw ValidationException::withMessages([
                'director_cadastral_signature_method' => 'You must be logged in to confirm by password.',
            ]);
        }

        $dcResolvedSignature = $dcOfficer?->signature_file ?? $dcOfficer?->user?->signature ?? null;
        if ($dcOfficer && empty($dcResolvedSignature)) {
            throw ValidationException::withMessages([
                'director_cadastral_signature_method' => 'Your officer profile has no digital signature configured.',
            ]);
        }

        $resolvedOfficerId = $dcOfficer?->id ?? $dcOfficerId;
        $this->assertSignatureVerification(
            'cadastral',
            $resolvedOfficerId,
            $dcMethod,
            'Verify the Director Cadastral signature again before submitting this request.'
        );

        $data['director_cadastral_signature_method'] = $dcMethod;
        $data['director_cadastral_signature'] = $dcResolvedSignature;
        $data['director_cadastral_signature_verified_at'] = now();
        $data['director_cadastral_signature_approved_by'] = Auth::id();
    }

    private function rememberSignatureVerification(string $scope, int $officerId, string $method): void
    {
        session()->put("survey_report_signature_verifications.{$scope}", [
            'officer_id' => $officerId,
            'method' => $method,
            'auth_user_id' => (int) Auth::id(),
            'verified_at' => now()->timestamp,
        ]);
    }

    private function assertSignatureVerification(string $scope, int $officerId, string $method, string $message): void
    {
        $verification = session()->get("survey_report_signature_verifications.{$scope}");
        $cutoff = now()->subMinutes(15)->timestamp;

        $isValid = is_array($verification)
            && (int) ($verification['officer_id'] ?? 0) === $officerId
            && (string) ($verification['method'] ?? '') === $method
            && (int) ($verification['auth_user_id'] ?? 0) === (int) Auth::id()
            && (int) ($verification['verified_at'] ?? 0) >= $cutoff;

        if (!$isValid) {
            throw ValidationException::withMessages([
                $scope === 'lands' ? 'signature_verification_method' : 'director_cadastral_signature_method' => $message,
            ]);
        }
    }

    private function forgetSignatureVerification(string $scope): void
    {
        session()->forget("survey_report_signature_verifications.{$scope}");
    }

    protected function getLocationOptions(): array
    {
        $districtOptions = District::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $lgaOptions = Lga::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $streetOptions = StreetName::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $cadastralOfficers = CadastralOfficer::orderBy('name')->get();
        // Staff only. signing_officers also holds the document signatories (the
        // Commissioner, the Governor), who are office holders printed on issued
        // documents and have no business in a "who signs this survey report?" list.
        $landOfficers = LandOfficer::staffOfficers()->orderBy('name')->get();

        return compact('districtOptions', 'lgaOptions', 'streetOptions', 'cadastralOfficers', 'landOfficers');
    }

    private function resolveLandOfficer(int $officerId): ?LandOfficer
    {
        if (!$officerId) {
            return null;
        }

        return LandOfficer::find($officerId)
            ?? LandOfficer::where('user_id', $officerId)->first();
    }

    public function sendLandOfficerOtp(Request $request)
    {
        $validated = $request->validate([
            'land_officer_id' => 'required|integer',
            'method' => 'required|in:email,sms',
        ]);

        $landOfficer = $this->resolveLandOfficer((int) $validated['land_officer_id']);
        if (!$landOfficer) {
            return response()->json([
                'success' => false,
                'message' => 'Signing officer not found.',
                'data' => [],
            ], 404);
        }

        $result = $this->landOfficerSignatureService->sendOtp($landOfficer, $validated['method']);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => ['land_officer_id' => $landOfficer->id],
        ], $result['success'] ? 200 : 422);
    }

    public function verifySignature(Request $request)
    {
        $validated = $request->validate([
            'verification_scope'            => ['nullable', Rule::in(['lands', 'cadastral'])],
            'land_officer_id'               => 'required|integer',
            'signature_verification_method' => 'required|in:email,sms,password',
            'verification_code'             => 'nullable|string|max:10',
            'verification_password'         => 'nullable|string',
        ]);

        $scope = (string) ($validated['verification_scope'] ?? 'lands');
        $officerId         = (int) $validated['land_officer_id'];
        $verificationMethod = $validated['signature_verification_method'];

        $landOfficer = $this->resolveLandOfficer($officerId);

        if (!$landOfficer && $verificationMethod !== 'password') {
            return response()->json(['success' => false, 'message' => 'Signing officer not found.'], 404);
        }

        if ($verificationMethod === 'password') {
            if (!Auth::check()) {
                return response()->json(['success' => false, 'message' => 'You must be logged in to confirm by password.'], 401);
            }
            if ($landOfficer && !empty($landOfficer->user_id) && (int) $landOfficer->user_id !== (int) Auth::id()) {
                return response()->json(['success' => false, 'message' => 'Password verification can only be used for your own officer profile.'], 422);
            }
        }

        $resolvedSigPath = $landOfficer?->signature_file ?? $landOfficer?->user?->signature ?? null;
        if ($landOfficer && empty($resolvedSigPath)) {
            return response()->json(['success' => false, 'message' => 'Selected signing officer has no digital signature configured.'], 422);
        }

        $verification = $this->landOfficerSignatureService->verifySecondLevelAuth(
            $landOfficer ?? new LandOfficer(['user_id' => Auth::id()]),
            $verificationMethod,
            $request->input('verification_code'),
            $request->input('verification_password')
        );

        if (!$verification['success']) {
            return response()->json(['success' => false, 'message' => $verification['message']], 422);
        }

        // Files stored via ->store('public/...') carry a 'public/' prefix in the DB.
        // Strip it and use asset() to avoid the double-slash from APP_URL trailing slash.
        $signatureUrl = null;
        if ($resolvedSigPath) {
            $urlPath      = ltrim(preg_replace('#^public/#', '', $resolvedSigPath), '/');
            $signatureUrl = asset('storage/' . $urlPath);
        }
        $this->rememberSignatureVerification($scope, $landOfficer?->id ?? $officerId, $verificationMethod);

        return response()->json([
            'success'       => true,
            'message'       => 'Verification successful.',
            'signature_url' => $signatureUrl,
        ]);
    }

    public function destroy($id)
    {
        $requestRecord = SurveyReportRequest::findOrFail($id);
        $requestRecord->delete();

        return response()->json([
            'success' => true,
            'message' => 'Survey report request deleted successfully.',
            'data' => ['id' => $id],
        ]);
    }

    public function print($id)
    {
        $requestRecord = SurveyReportRequest::findOrFail($id);

        if (empty($requestRecord->serial_no) && !empty($requestRecord->id)) {
            $requestRecord->serial_no = str_pad((string) $requestRecord->id, 6, '0', STR_PAD_LEFT);
            $requestRecord->save();
        }

        $requestRecord->increment('print_count');
        $requestRecord->refresh();

        $watermark = $requestRecord->print_count > 1 ? 'ORIGINAL' : 'ORIGINAL';

        return view('survey_report.print.show', compact('requestRecord', 'watermark'));
    }
}
