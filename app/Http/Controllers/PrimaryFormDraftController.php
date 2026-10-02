<?php

namespace App\Http\Controllers;

use App\Models\MotherApplicationDraft;
use App\Models\MotherApplicationDraftCollaborator;
use App\Models\MotherApplicationDraftVersion;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
// Draft autosave for the Primary Application Form logs to storage/logs/primary_form.log
// — see config/logging.php channel "primary_form".
use App\Support\PrimaryFormLog as Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PrimaryFormDraftController extends Controller
{
    private static ?bool $npFileNoColumnExists = null;

    private const REQUIRED_FIELDS = [
        'applicantType',
        'scheme_no',
        'property_street_name',
        'property_lga',
        'property_state',
        'land_use',
        'records',
    ];

    public function __construct()
    {
        $this->middleware('throttle:120,1')->only(['saveDraft']);
        $this->middleware('auth');
    }

    public function index(Request $request)
    {

        $userId = Auth::id();
        $draftId = $request->query('draft_id');
        $applicationId = $request->query('application_id');
        $forceFresh = $request->boolean('fresh');

        $userDrafts = $this->getUserDraftsCollection($userId);

        $draft = null;

        if ($draftId) {
            $draft = MotherApplicationDraft::where('draft_id', $draftId)->first();
        } elseif ($applicationId) {
            $draft = MotherApplicationDraft::where('application_id', $applicationId)->freshFirst()->first();
        } elseif (!$forceFresh && $userDrafts->isNotEmpty()) {
            $draft = $userDrafts->first();
        }

        if (!$draft || $forceFresh) {
            $draft = $this->createBlankDraft($applicationId, $userId);
            $userDrafts = $this->getUserDraftsCollection($userId);
        }

        $bootstrap = $this->buildBootstrapPayload($draft, $userDrafts);

        $baseController = app(PrimaryApplicationController::class);
        $view = $baseController->index($request);

        return $view->with([
            'draftBootstrap' => $bootstrap,
            'draftMeta' => $bootstrap,
        ]);
    }

    public function saveDraft(Request $request): JsonResponse
    {
        Log::info('[DraftAutosave] saveDraft called', [
            'has_form_state' => $request->has('form_state'),
            'form_state_keys' => $request->has('form_state') ? array_keys($request->input('form_state', [])) : [],
            'all_keys' => array_keys($request->all()),
        ]);

        $validated = $request->validate([
            'draft_id' => 'nullable|uuid',
            'application_id' => 'nullable|integer',
            'form_state' => 'required|array',
            'metadata.progress_percent' => 'nullable|numeric|min:0|max:100',
            'metadata.last_completed_step' => 'nullable|integer|min:1|max:5',
            'metadata.auto_save_frequency' => 'nullable|integer|min:10|max:600',
            'metadata.trigger' => 'nullable|string|in:auto,manual,debounced',
            'metadata.client_version' => 'nullable|integer|min:1',
            'metadata.lock_token' => 'nullable|string',
            'metadata.collaborators' => 'nullable|array',
            'metadata.collaborators.*' => 'integer',
        ]);

        $formState = $validated['form_state'];
        $metadata = $request->input('metadata', []);

    $npFileNo = trim((string) Arr::get($formState, 'np_fileno', ''));
        $progress = $metadata['progress_percent'] ?? $this->calculateProgress($formState);
        $lastCompletedStep = $metadata['last_completed_step'] ?? $this->inferStepFromState($formState);
        $autoSaveFrequency = $metadata['auto_save_frequency'] ?? 30;
        $trigger = $metadata['trigger'] ?? 'auto';
        $clientVersion = $metadata['client_version'] ?? null;
        $incomingCollaborators = $metadata['collaborators'] ?? [];

        Log::info('[DraftAutosave] Processing draft save', [
            'draft_id' => $validated['draft_id'] ?? 'new',
            'np_file_no' => $npFileNo,
            'progress' => $progress,
            'form_state_size' => strlen(json_encode($formState)),
        ]);

        DB::connection('sqlsrv')->beginTransaction();

        try {
            $draft = $this->findDraftForUpdate(
                $validated['draft_id'] ?? null,
                $validated['application_id'] ?? null,
                $npFileNo !== '' ? $npFileNo : null
            );

            $isNewDraft = false;
            if (!$draft) {
                $draft = new MotherApplicationDraft();
                $draft->draft_id = (string) Str::uuid();
                $isNewDraft = true;
            }

            if ($clientVersion && $draft->exists && $clientVersion < $draft->version) {
                DB::connection('sqlsrv')->rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'A newer version of this draft exists. Please reload to continue.',
                    'conflict' => true,
                    'server_version' => $draft->version,
                    'server_state' => $draft->form_state,
                ], 409);
            }

            $draft->application_id = $validated['application_id'] ?? $draft->application_id;
            $draft->form_state = $this->sanitizeFormState($formState);
            $draft->progress_percent = $progress;
            $draft->last_completed_step = $lastCompletedStep;
            $draft->auto_save_frequency = $autoSaveFrequency;
            if ($this->hasNpFileNoColumn()) {
                $draft->np_file_no = $npFileNo !== '' ? $npFileNo : null;
            }
            $draft->is_locked = false;
            $draft->locked_by = null;
            $draft->locked_at = null;
            $draft->last_saved_by = Auth::id();
            $draft->last_saved_at = now();
            $draft->last_error = null;

            $analytics = $draft->analytics ?? [];
            $analytics['total_saves'] = ($analytics['total_saves'] ?? 0) + 1;
            $analyticsKey = $trigger === 'manual' ? 'manual_saves' : ($trigger === 'debounced' ? 'debounced_saves' : 'auto_saves');
            $analytics[$analyticsKey] = ($analytics[$analyticsKey] ?? 0) + 1;
            $analytics['last_trigger'] = $trigger;
            $analytics['last_activity_at'] = now()->toIso8601String();
            $analytics['last_step'] = $lastCompletedStep;
            $draft->analytics = $analytics;

            if (!empty($incomingCollaborators)) {
                $draft->collaborators = array_values(array_unique(array_merge($draft->collaborators ?? [], $incomingCollaborators)));
            } elseif (!$draft->collaborators) {
                $draft->collaborators = [Auth::id()];
            }

            $draft->version = ($draft->version ?? 0) + 1;

            $draft->save();

            Log::info('[DraftAutosave] Draft saved to database', [
                'draft_id' => $draft->draft_id,
                'id' => $draft->id ?? 'pending',
                'version' => $draft->version,
            ]);

            $this->recordDraftVersion($draft);
            $this->syncCollaborators($draft, $incomingCollaborators);

            DB::connection('sqlsrv')->commit();

            Log::info('[DraftAutosave] Draft save completed successfully', [
                'draft_id' => $draft->draft_id,
            ]);

            $response = [
                'success' => true,
                'message' => 'Draft saved successfully',
                'draft_id' => $draft->draft_id,
                'version' => $draft->version,
                'progress_percent' => $draft->progress_percent,
                'last_completed_step' => $draft->last_completed_step,
                'auto_save_frequency' => $draft->auto_save_frequency,
                'last_saved_at' => optional($draft->last_saved_at)->toIso8601String(),
                'np_file_no' => $draft->np_file_no,
                'analytics' => $draft->analytics,
                'draft_summary' => $this->summarizeDraft($draft, $draft->draft_id),
            ];
            return response()->json($response);
        } catch (Exception $e) {
            DB::connection('sqlsrv')->rollBack();

            Log::error('[DraftAutosave] Failed to save draft', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save draft right now. Please try again shortly.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function loadDraft(string $identifier): JsonResponse
    {
        $draft = $this->findDraftByIdentifier($identifier);

        if (!$draft) {
            return response()->json([
                'success' => false,
                'message' => 'Draft not found',
            ], 404);
        }

        return response()->json($this->transformDraftResponse($draft));
    }

    public function submitDraft(Request $request)
    {
        $draftId = $request->input('draft_id');
        $hydrateFromDraft = $request->boolean('hydrate_from_draft', true);

        $draft = $draftId ? MotherApplicationDraft::where('draft_id', $draftId)->first() : null;

        if ($draft && $hydrateFromDraft) {
            $payload = $draft->form_state ?? [];
            $merged = array_merge($payload, $request->all());
            $request->replace($merged);
        }

        $response = app(PrimaryApplicationController::class)->store($request);

        if ($this->responseIsSuccessful($response) && $draft) {
            $this->finalizeDraft($draft);

            if ($response instanceof JsonResponse) {
                $data = $response->getData(true);
                $data['draft_deleted'] = true;
                $response->setData($data);
            }
        }

        return $response;
    }

    public function deleteDraft(string $identifier): JsonResponse
    {
        $draft = $this->findDraftByIdentifier($identifier);

        if (!$draft) {
            return response()->json([
                'success' => false,
                'message' => 'Draft not found',
            ], 404);
        }

        $this->finalizeDraft($draft, true);

        return response()->json([
            'success' => true,
            'message' => 'Draft deleted successfully',
        ]);
    }

    public function checkDraft(int $applicationId): JsonResponse
    {
        $draft = MotherApplicationDraft::where('application_id', $applicationId)->freshFirst()->first();

        if (!$draft) {
            return response()->json([
                'success' => true,
                'has_draft' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'has_draft' => true,
            'draft_id' => $draft->draft_id,
            'progress_percent' => $draft->progress_percent,
            'last_completed_step' => $draft->last_completed_step,
            'last_saved_at' => optional($draft->last_saved_at)->toIso8601String(),
        ]);
    }

    public function exportDraft(string $identifier)
    {
        $draft = $this->findDraftByIdentifier($identifier);

        if (!$draft) {
            return response()->json([
                'success' => false,
                'message' => 'Draft not found',
            ], 404);
        }

        $filename = 'primary-application-draft-' . $draft->draft_id . '.json';

        return response()->streamDownload(function () use ($draft) {
            echo json_encode([
                'draft' => $draft->toArray(),
                'versions' => $draft->versions()->orderBy('version')->get()->toArray(),
            ], JSON_PRETTY_PRINT);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function shareDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => 'required|uuid',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'role' => 'nullable|string|max:50',
        ]);

        $draft = MotherApplicationDraft::where('draft_id', $validated['draft_id'])->first();

        if (!$draft) {
            return response()->json([
                'success' => false,
                'message' => 'Draft not found',
            ], 404);
        }

        $this->syncCollaborators($draft, $validated['user_ids'], $validated['role'] ?? 'editor', true);

        return response()->json([
            'success' => true,
            'message' => 'Collaborators updated',
            'collaborators' => $draft->collaborators ?? [],
        ]);
    }

    public function analytics(string $identifier): JsonResponse
    {
        $draft = $this->findDraftByIdentifier($identifier);

        if (!$draft) {
            return response()->json([
                'success' => false,
                'message' => 'Draft not found',
            ], 404);
        }

        $analytics = $draft->analytics ?? [];
        $analytics['versions'] = $draft->versions()->count();
        $analytics['collaborator_count'] = count($draft->collaborators ?? []);

        return response()->json([
            'success' => true,
            'analytics' => $analytics,
            'versions' => $draft->versions()->orderByDesc('version')->limit(5)->get(['version', 'snapshot', 'created_at', 'created_by']),
        ]);
    }

    public function startFresh(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $applicationId = $request->input('application_id') ? (int) $request->input('application_id') : null;

        $draft = $this->createBlankDraft($applicationId, $userId);
        $userDrafts = $this->getUserDraftsCollection($userId);

        $payload = $this->transformDraftResponse($draft, $userDrafts);
        $payload['message'] = 'New draft initialized.';

        return response()->json($payload, 201);
    }

    public function myDrafts(): JsonResponse
    {
        $userId = Auth::id();

        return response()->json([
            'success' => true,
            'drafts' => $this->getUserDraftSummaries($userId),
        ]);
    }

    private function hasNpFileNoColumn(): bool
    {
        if (self::$npFileNoColumnExists !== null) {
            return self::$npFileNoColumnExists;
        }

        try {
            self::$npFileNoColumnExists = Schema::connection('sqlsrv')->hasColumn('mother_application_draft', 'np_file_no');
        } catch (Exception $exception) {
            Log::warning('Unable to determine np_file_no column existence', [
                'error' => $exception->getMessage(),
            ]);
            self::$npFileNoColumnExists = false;
        }

        return self::$npFileNoColumnExists;
    }

    private function findDraftForUpdate(?string $draftId, ?int $applicationId, ?string $npFileNo = null): ?MotherApplicationDraft
    {
        if ($draftId) {
            return MotherApplicationDraft::where('draft_id', $draftId)->lockForUpdate()->first();
        }

        if ($npFileNo && $this->hasNpFileNoColumn()) {
            $match = MotherApplicationDraft::where('np_file_no', $npFileNo)->lockForUpdate()->first();

            if ($match) {
                return $match;
            }
        }

        if ($applicationId) {
            return MotherApplicationDraft::where('application_id', $applicationId)->lockForUpdate()->first();
        }

        return null;
    }

    private function createBlankDraft(?int $applicationId, int $userId): MotherApplicationDraft
    {
        $now = now();

        return MotherApplicationDraft::create([
            'application_id' => $applicationId,
            'form_state' => [],
            'progress_percent' => 0,
            'last_completed_step' => 1,
            'auto_save_frequency' => 30,
            'is_locked' => false,
            'locked_by' => null,
            'locked_at' => null,
            'last_saved_by' => $userId,
            'last_saved_at' => $now,
            'analytics' => $this->defaultDraftAnalytics($now),
            'collaborators' => [$userId],
            'last_error' => null,
        ]);
    }

    private function defaultDraftAnalytics(Carbon $startedAt): array
    {
        return [
            'total_saves' => 0,
            'auto_saves' => 0,
            'manual_saves' => 0,
            'debounced_saves' => 0,
            'started_at' => $startedAt->toIso8601String(),
        ];
    }

    private function buildBootstrapPayload(MotherApplicationDraft $draft, Collection $drafts): array
    {
        $currentId = $draft->draft_id;

        return [
            'draft_id' => $draft->draft_id,
            'application_id' => $draft->application_id,
            'progress_percent' => $draft->progress_percent,
            'last_completed_step' => $draft->last_completed_step,
            'auto_save_frequency' => $draft->auto_save_frequency ?? 30,
            'version' => $draft->version,
            'form_state' => $draft->form_state ?? [],
            'collaborators' => $draft->collaborators ?? [],
            'np_file_no' => $draft->np_file_no,
            'is_locked' => (bool) $draft->is_locked,
            'locked_by' => $draft->locked_by,
            'locked_at' => optional($draft->locked_at)->toIso8601String(),
            'last_saved_at' => optional($draft->last_saved_at)->toIso8601String(),
            'session_timeout_seconds' => config('session.lifetime', 120) * 60,
            'drafts' => $drafts->map(fn (MotherApplicationDraft $item) => $this->summarizeDraft($item, $currentId))->values()->all(),
            'mode' => $this->resolveInitialMode($draft, $drafts),
            'user_draft_count' => $drafts->count(),
        ];
    }

    private function resolveInitialMode(MotherApplicationDraft $currentDraft, Collection $drafts): string
    {
        if ($drafts->count() === 0) {
            return 'fresh';
        }

        if ($drafts->count() === 1) {
            return ($currentDraft->progress_percent ?? 0) > 0 ? 'draft' : 'fresh';
        }

        return 'draft';
    }

    private function getUserDraftsCollection(int $userId): Collection
    {
        return MotherApplicationDraft::where('last_saved_by', $userId)
            ->freshFirst()
            ->limit(50)
            ->get();
    }

    private function getUserDraftSummaries(?int $userId, ?string $currentDraftId = null): array
    {
        if (!$userId) {
            return [];
        }

        return $this->getUserDraftsCollection($userId)
            ->map(fn (MotherApplicationDraft $draft) => $this->summarizeDraft($draft, $currentDraftId))
            ->values()
            ->all();
    }

    private function summarizeDraft(MotherApplicationDraft $draft, ?string $currentDraftId = null): array
    {
        $lastSavedAt = $draft->last_saved_at ? $draft->last_saved_at->copy() : null;
        $progress = $draft->progress_percent ?? 0;
        $npFileNo = $draft->np_file_no ?: Arr::get($draft->form_state, 'np_fileno');

        return [
            'draft_id' => $draft->draft_id,
            'application_id' => $draft->application_id,
            'np_file_no' => $npFileNo,
            'progress_percent' => $progress,
            'last_completed_step' => $draft->last_completed_step ?? 1,
            'last_saved_at' => $lastSavedAt ? $lastSavedAt->toIso8601String() : null,
            'last_saved_human' => $lastSavedAt ? $lastSavedAt->diffForHumans() : null,
            'label' => $this->buildDraftLabel($draft, $progress, $lastSavedAt, $npFileNo),
            'scheme_no' => Arr::get($draft->form_state, 'scheme_no'),
            'property_street_name' => Arr::get($draft->form_state, 'property_street_name'),
            'is_current' => $currentDraftId ? $currentDraftId === $draft->draft_id : false,
        ];
    }

    private function buildDraftLabel(MotherApplicationDraft $draft, float $progress, ?Carbon $lastSavedAt, ?string $npFileNo = null): string
    {
        $identifier = $npFileNo
            ?: Arr::get($draft->form_state, 'scheme_no')
            ?: Arr::get($draft->form_state, 'property_street_name')
            ?: ($draft->application_id ? 'Application #' . $draft->application_id : 'New application');

        $progressDisplay = number_format($progress, 0);
        $savedDisplay = $lastSavedAt ? $lastSavedAt->diffForHumans() : 'never';

        return trim(sprintf('%s • %s%% • %s', $identifier, $progressDisplay, $savedDisplay));
    }

    private function transformDraftResponse(MotherApplicationDraft $draft, ?Collection $drafts = null, bool $includeVersions = true): array
    {
        $userId = Auth::id();
        $collection = $drafts ?? $this->getUserDraftsCollection($userId);

        $response = [
            'success' => true,
            'draft_id' => $draft->draft_id,
            'application_id' => $draft->application_id,
            'np_file_no' => $draft->np_file_no,
            'form_state' => $draft->form_state ?? [],
            'progress_percent' => $draft->progress_percent,
            'last_completed_step' => $draft->last_completed_step,
            'auto_save_frequency' => $draft->auto_save_frequency ?? 30,
            'version' => $draft->version,
            'collaborators' => $draft->collaborators ?? [],
            'locked_by' => $draft->locked_by,
            'locked_at' => optional($draft->locked_at)->toIso8601String(),
            'last_saved_at' => optional($draft->last_saved_at)->toIso8601String(),
            'analytics' => $draft->analytics,
            'drafts' => $collection->map(fn (MotherApplicationDraft $item) => $this->summarizeDraft($item, $draft->draft_id))->values()->all(),
        ];

        if ($includeVersions) {
            $response['versions'] = $draft->versions()->orderByDesc('version')->limit(5)->get(['version', 'snapshot', 'created_at', 'created_by']);
        }

        return $response;
    }

    private function recordDraftVersion(MotherApplicationDraft $draft): void
    {
        try {
            MotherApplicationDraftVersion::create([
                'draft_id' => $draft->draft_id,
                'version' => $draft->version,
                'snapshot' => $draft->form_state ?? [],
                'created_by' => Auth::id(),
            ]);

            $draft->versions()
                ->orderByDesc('version')
                ->skip(20)
                ->take(PHP_INT_MAX)
                ->get()
                ->each->delete();
        } catch (Exception $e) {
            Log::warning('Unable to record draft version', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncCollaborators(MotherApplicationDraft $draft, array $collaborators, string $role = 'editor', bool $overwrite = false): void
    {
        if (empty($collaborators)) {
            return;
        }

        $allCollaborators = $overwrite
            ? array_values(array_unique(array_merge([Auth::id()], $collaborators)))
            : array_values(array_unique(array_merge($draft->collaborators ?? [Auth::id()], $collaborators)));

        $draft->collaborators = $allCollaborators;
        $draft->save();

        try {
            $existing = MotherApplicationDraftCollaborator::where('draft_id', $draft->draft_id)->pluck('user_id')->all();

            $toAttach = array_diff($allCollaborators, $existing);

            foreach ($toAttach as $userId) {
                MotherApplicationDraftCollaborator::create([
                    'draft_id' => $draft->draft_id,
                    'user_id' => $userId,
                    'invited_by' => Auth::id(),
                    'role' => $role,
                    'accepted_at' => now(),
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to sync draft collaborators', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function finalizeDraft(MotherApplicationDraft $draft, bool $forceDelete = false): void
    {
        try {
            if ($forceDelete) {
                MotherApplicationDraftVersion::where('draft_id', $draft->draft_id)->delete();
                MotherApplicationDraftCollaborator::where('draft_id', $draft->draft_id)->delete();
                $draft->delete();

                return;
            }

            $draft->delete();
            MotherApplicationDraftVersion::where('draft_id', $draft->draft_id)->delete();
            MotherApplicationDraftCollaborator::where('draft_id', $draft->draft_id)->delete();
        } catch (Exception $e) {
            Log::warning('Failed to finalize draft', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sanitizeFormState(array $formState): array
    {
        // Remove tokens and internal fields that should not be persisted
        $disallowed = ['_token', 'password', 'password_confirmation'];

        return Arr::except($formState, $disallowed);
    }

    private function calculateProgress(array $formState): float
    {
        $total = count(self::REQUIRED_FIELDS);

        if ($total === 0) {
            return 100.0;
        }

        $completed = 0;

        foreach (self::REQUIRED_FIELDS as $field) {
            $value = Arr::get($formState, $field);

            if ($field === 'records') {
                if (is_array($value) && count($value) > 0) {
                    $completed++;
                }

                continue;
            }

            if (is_array($value)) {
                if (!empty(array_filter($value))) {
                    $completed++;
                }

                continue;
            }

            if (!empty($value)) {
                $completed++;
            }
        }

        $score = round(($completed / $total) * 100, 2);

        return max(0, min(100, $score));
    }

    private function inferStepFromState(array $formState): int
    {
        $stepThresholds = [
            1 => ['applicantType'],
            2 => ['shared_areas'],
            3 => ['application_letter', 'building_plan', 'architectural_design', 'ownership_document', 'survey_plan'],
            4 => ['records'],
            5 => ['comments'],
        ];

        $currentStep = 1;

        foreach ($stepThresholds as $step => $fields) {
            foreach ($fields as $field) {
                $value = Arr::get($formState, $field);

                if (!empty($value)) {
                    $currentStep = max($currentStep, $step);
                    break;
                }
            }
        }

        return $currentStep;
    }

    /**
     * Render a printable summary of the in-flight application.
     */
    public function printPage(Request $request)
    {
        $data = [
            'applicationId' => $request->get('applicationId', 'APP-' . rand(10000, 99999)),
            'landUse' => $request->get('landUse', 'Residential'),
            'applicantType' => $request->get('applicantType'),
            'applicantName' => $request->get('applicantName'),
            'applicantEmail' => $request->get('applicantEmail'),
            'applicantPhone' => $request->get('applicantPhone'),
            'applicantAddress' => $request->get('applicantAddress'),
            'residenceType' => $request->get('residenceType'),
            'units' => $request->get('units'),
            'blocks' => $request->get('blocks'),
            'sections' => $request->get('sections'),
            'fileNumber' => $request->get('fileNumber'),
            'propertyHouseNo' => $request->get('propertyHouseNo'),
            'propertyPlotNo' => $request->get('propertyPlotNo'),
            'propertyStreet' => $request->get('propertyStreet'),
            'propertyDistrict' => $request->get('propertyDistrict'),
            'propertyLGA' => $request->get('propertyLGA'),
            'propertyState' => $request->get('propertyState'),
            'propertyFullAddress' => $request->get('propertyFullAddress'),
            'applicationFee' => $request->get('applicationFee'),
            'processingFee' => $request->get('processingFee'),
            'sitePlanFee' => $request->get('sitePlanFee'),
            'totalFee' => $request->get('totalFee'),
            'receiptNumber' => $request->get('receiptNumber'),
            'paymentDate' => $request->get('paymentDate'),
            'documents' => $request->get('documents', []),
        ];

        return view('primaryform.print-page', $data);
    }

    private function findDraftByIdentifier(string $identifier): ?MotherApplicationDraft
    {
        if (Str::isUuid($identifier)) {
            $match = MotherApplicationDraft::where('draft_id', $identifier)->first();

            if ($match) {
                return $match;
            }
        }

        if (!$this->hasNpFileNoColumn()) {
            return null;
        }

        return MotherApplicationDraft::where('np_file_no', $identifier)->first();
    }

    private function responseIsSuccessful($response): bool
    {
        if ($response instanceof JsonResponse) {
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300 && ($response->getData(true)['success'] ?? false);
        }

        if ($response instanceof RedirectResponse) {
            return $response->getSession()->has('success');
        }

        if ($response instanceof Response) {
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        }

        return false;
    }
}
