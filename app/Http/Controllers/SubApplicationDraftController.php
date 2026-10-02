<?php

namespace App\Http\Controllers;

use App\Models\SubApplicationDraft;
use App\Models\SubApplicationDraftCollaborator;
use App\Models\SubApplicationDraftVersion;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SubApplicationDraftController extends Controller
{
    private static ?bool $unitFileNoColumnExists = null;

    private const REQUIRED_FIELDS = [
        'applicant_type',
        'buyer_id',
        'unit_no',
        'land_use',
        'unit_measurement',
        'shared_areas',
    ];

    public function __construct()
    {
        $this->middleware('throttle:120,1')->only(['saveDraft']);
        $this->middleware('auth');
    }

    public function index()
    {
        $request = request();
        $isSUA = $request->boolean('is_sua', false);
        $forceFresh = $request->boolean('fresh');
        $draftId = $request->query('draft_id');
        $subApplicationId = $request->query('sub_application_id');
        $mainApplicationId = $request->query('main_application_id');

        $result = $this->prepareBootstrap(
            $draftId,
            $subApplicationId ? (int) $subApplicationId : null,
            $mainApplicationId ? (int) $mainApplicationId : null,
            $isSUA,
            $forceFresh
        );

        return view('sectionaltitling.sub_application', [
            'draftBootstrap' => $result['bootstrap'],
            'isSUA' => $result['isSUA'],
            'mainApplicationId' => $mainApplicationId,
        ]);
    }

    /**
     * Shared logic to prepare draft bootstrap data
     */
    public function prepareBootstrap(?string $draftId, ?int $subApplicationId, ?int $mainApplicationId, bool $isSUA, bool $forceFresh = false)
    {
        $userId = Auth::id();
        $userDrafts = $this->getUserDraftsCollection($userId, $isSUA);
        $draft = null;

        if ($draftId) {
            $draft = SubApplicationDraft::where('draft_id', $draftId)->first();
        } elseif ($subApplicationId) {
            $draft = SubApplicationDraft::where('sub_application_id', $subApplicationId)->latest('last_saved_at')->first();

            // If no draft exists for this sub_application_id, try to hydrate from the actual record
            if (!$draft && !$forceFresh) {
                $draft = $this->hydrateFromExistingSubApplication((int) $subApplicationId, $userId);
                if ($draft) {
                    $isSUA = $draft->is_sua;
                    $userDrafts = $this->getUserDraftsCollection($userId, $isSUA);
                }
            }
        } elseif (!$forceFresh && $userDrafts->isNotEmpty()) {
            $draft = $userDrafts->first();
        }

        if (!$draft || $forceFresh) {
            $draft = $this->createBlankDraft($subApplicationId, $mainApplicationId, $userId, $isSUA);
            $userDrafts = $this->getUserDraftsCollection($userId, $isSUA);
        }

        return [
            'bootstrap' => $this->buildBootstrapPayload($draft, $userDrafts),
            'isSUA' => $isSUA,
            'draft' => $draft
        ];
    }

    public function saveDraft(Request $request): JsonResponse
    {
        // Handle both old and new autosave formats
        $isOldFormat = $request->has('form_state') && is_string($request->input('form_state'));

        Log::info('[SubApplicationDraftAutosave] saveDraft called', [
            'format' => $isOldFormat ? 'old' : 'new',
            'has_form_state' => $request->has('form_state'),
            'all_keys' => array_keys($request->all()),
        ]);

        if ($isOldFormat) {
            // Handle old format (form_state as JSON string)
            $validated = $request->validate([
                'draft_id' => 'nullable|uuid',
                'sub_application_id' => 'nullable|integer',
                'main_application_id' => 'nullable|integer',
                'is_sua' => 'nullable|boolean',
                'unit_file_no' => 'nullable|string',
                'form_state' => 'required|string',
                'progress_percent' => 'nullable|numeric|min:0|max:100',
                'auto_save' => 'nullable|string',
            ]);

            // Parse the JSON form_state
            $formState = json_decode($validated['form_state'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $formState = [];
            }

            $metadata = [
                'progress_percent' => $validated['progress_percent'] ?? 0,
                'trigger' => $validated['auto_save'] === '1' ? 'auto' : 'manual',
            ];
        } else {
            // Handle new format (form_state as array)
            $validated = $request->validate([
                'draft_id' => 'nullable|uuid',
                'sub_application_id' => 'nullable|integer',
                'main_application_id' => 'nullable|integer',
                'is_sua' => 'nullable|boolean',
                'form_state' => 'required|array',
                'metadata.progress_percent' => 'nullable|numeric|min:0|max:100',
                'metadata.last_completed_step' => 'nullable|integer|min:1|max:4',
                'metadata.auto_save_frequency' => 'nullable|integer|min:10|max:600',
                'metadata.trigger' => 'nullable|string|in:auto,manual,debounced',
                'metadata.client_version' => 'nullable|integer|min:1',
                'metadata.lock_token' => 'nullable|string',
                'metadata.collaborators' => 'nullable|array',
                'metadata.collaborators.*' => 'integer',
            ]);

            $formState = $validated['form_state'];
            $metadata = $request->input('metadata', []);
        }

        // Get unit file number from form state or direct parameter (for old format)
        $unitFileNo = trim((string) Arr::get($formState, 'unit_file_no', ''));
        if (empty($unitFileNo) && $isOldFormat) {
            $unitFileNo = trim((string) ($validated['unit_file_no'] ?? ''));
        }

        $progress = $metadata['progress_percent'] ?? $this->calculateProgress($formState);
        $lastCompletedStep = $metadata['last_completed_step'] ?? $this->inferStepFromState($formState);
        $autoSaveFrequency = $metadata['auto_save_frequency'] ?? 30;
        $trigger = $metadata['trigger'] ?? 'auto';
        $clientVersion = $metadata['client_version'] ?? null;
        $incomingCollaborators = $metadata['collaborators'] ?? [];
        $isSUA = $validated['is_sua'] ?? false;

        Log::info('[SubApplicationDraftAutosave] Processing draft save', [
            'draft_id' => $validated['draft_id'] ?? 'new',
            'unit_file_no' => $unitFileNo,
            'progress' => $progress,
            'is_sua' => $isSUA,
            'form_state_size' => strlen(json_encode($formState)),
        ]);

        DB::connection('sqlsrv')->beginTransaction();

        try {
            $draft = $this->findDraftForUpdate(
                $validated['draft_id'] ?? null,
                $validated['sub_application_id'] ?? null,
                $validated['main_application_id'] ?? null,
                $unitFileNo !== '' ? $unitFileNo : null,
                $isSUA
            );

            $isNewDraft = false;
            if (!$draft) {
                $draft = new SubApplicationDraft();
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

            $draft->sub_application_id = $validated['sub_application_id'] ?? null;
            $draft->main_application_id = $validated['main_application_id'] ?? null;
            $draft->form_state = $this->sanitizeFormState($formState);
            $draft->progress_percent = $progress;
            $draft->last_completed_step = $lastCompletedStep;
            $draft->auto_save_frequency = $autoSaveFrequency;
            $draft->version = ($draft->version ?? 0) + 1;
            $draft->last_saved_by = Auth::id();
            $draft->last_saved_at = now();
            $draft->unit_file_no = $unitFileNo ?: $draft->unit_file_no;
            $draft->is_sua = $isSUA;

            $draft->save();

            // Create version snapshot
            SubApplicationDraftVersion::create([
                'draft_id' => $draft->draft_id,
                'version' => $draft->version,
                'snapshot' => $draft->form_state,
                'created_by' => Auth::id(),
            ]);

            // Sync collaborators
            $this->syncCollaborators($draft, $incomingCollaborators, 'editor', false);

            DB::connection('sqlsrv')->commit();

            Log::info('[SubApplicationDraftAutosave] Draft saved successfully', [
                'draft_id' => $draft->draft_id,
                'version' => $draft->version,
                'progress' => $progress,
            ]);

            // Get updated drafts list for the user
            $userDrafts = $this->getUserDraftsCollection(Auth::id(), $isSUA);
            $updatedDrafts = $this->getUserDraftSummaries(Auth::id(), $isSUA, $draft->draft_id);

            return response()->json([
                'success' => true,
                'draft_id' => $draft->draft_id,
                'version' => $draft->version,
                'progress_percent' => $progress,
                'last_completed_step' => $lastCompletedStep,
                'last_saved_at' => $draft->last_saved_at->toIso8601String(),
                'unit_file_no' => $draft->unit_file_no,
                'is_new' => $isNewDraft,
                'trigger' => $trigger,
                'drafts' => $updatedDrafts,
            ]);

        } catch (Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('[SubApplicationDraftAutosave] Failed to save draft', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save draft. Please try again.',
                'error' => $e->getMessage(),
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

    /**
     * Load draft via POST request (for AJAX calls)
     */
    public function loadDraftPost(Request $request): JsonResponse
    {
        $draftId = $request->input('draft_id');

        if (!$draftId) {
            return response()->json([
                'success' => false,
                'message' => 'Draft ID is required',
            ], 400);
        }

        return $this->loadDraft($draftId);
    }

    public function submitDraft(Request $request)
    {
        $draftId = $request->input('draft_id');
        $hydrateFromDraft = $request->boolean('hydrate_from_draft', true);

        $draft = $draftId ? SubApplicationDraft::where('draft_id', $draftId)->first() : null;

        if ($draft && $hydrateFromDraft) {
            $payload = $draft->form_state ?? [];
            $merged = array_merge($payload, $request->all());

            // Debug log to see what's happening with shared areas data
            Log::info('SubApplicationDraftController submitDraft - data merge', [
                'draft_shared_areas' => $payload['shared_areas'] ?? null,
                'draft_other_areas_detail' => $payload['other_areas_detail'] ?? null,
                'request_shared_areas' => $request->input('shared_areas'),
                'request_other_areas_detail' => $request->input('other_areas_detail'),
                'merged_shared_areas' => $merged['shared_areas'] ?? null,
                'merged_other_areas_detail' => $merged['other_areas_detail'] ?? null,
            ]);

            $request->replace($merged);
        }

        $isSua = $draft?->is_sua ?? $request->boolean('is_sua') ?? $request->boolean('is_sua_unit');
        $subApplicationId = $request->input('sub_application_id');

        if ($isSua) {
            $controller = app(\App\Http\Controllers\SUAController::class);
            if ($subApplicationId) {
                $response = app()->call([$controller, 'update'], [
                    'request' => $request,
                    'id' => $subApplicationId,
                ]);
            } else {
                $response = app()->call([$controller, 'store'], [
                    'request' => $request,
                ]);
            }
        } else {
            $controller = app(\App\Http\Controllers\SecondaryFormController::class);
            if ($subApplicationId) {
                $response = app()->call([$controller, 'update'], [
                    'request' => $request,
                    'id' => $subApplicationId,
                ]);
            } else {
                $response = app()->call([$controller, 'save'], [
                    'request' => $request,
                ]);
            }
        }

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

    public function checkDraft(int $subApplicationId): JsonResponse
    {
        $draft = SubApplicationDraft::where('sub_application_id', $subApplicationId)->freshFirst()->first();

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

        $filename = 'sub-application-draft-' . $draft->draft_id . '.json';

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

        $draft = SubApplicationDraft::where('draft_id', $validated['draft_id'])->first();

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
        $subApplicationId = $request->input('sub_application_id') ? (int) $request->input('sub_application_id') : null;
        $mainApplicationId = $request->input('main_application_id') ? (int) $request->input('main_application_id') : null;
        $isSUA = $request->boolean('is_sua', false);

        $draft = $this->createBlankDraft($subApplicationId, $mainApplicationId, $userId, $isSUA);
        $userDrafts = $this->getUserDraftsCollection($userId, $isSUA);

        $payload = $this->transformDraftResponse($draft, $userDrafts);
        $payload['message'] = 'New draft initialized.';

        return response()->json($payload, 201);
    }

    public function myDrafts(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $isSUA = $request->boolean('is_sua', false);

        return response()->json([
            'success' => true,
            'drafts' => $this->getUserDraftSummaries($userId, $isSUA),
        ]);
    }

    private function hasUnitFileNoColumn(): bool
    {
        if (self::$unitFileNoColumnExists !== null) {
            return self::$unitFileNoColumnExists;
        }

        try {
            self::$unitFileNoColumnExists = Schema::connection('sqlsrv')->hasColumn('sub_application_draft', 'unit_file_no');
        } catch (Exception $exception) {
            Log::warning('Unable to determine unit_file_no column existence', [
                'error' => $exception->getMessage(),
            ]);
            self::$unitFileNoColumnExists = false;
        }

        return self::$unitFileNoColumnExists;
    }

    private function findDraftForUpdate(?string $draftId, ?int $subApplicationId, ?int $mainApplicationId, ?string $unitFileNo = null, bool $isSUA = false): ?SubApplicationDraft
    {
        if ($draftId) {
            return SubApplicationDraft::where('draft_id', $draftId)->lockForUpdate()->first();
        }

        if ($unitFileNo && $this->hasUnitFileNoColumn()) {
            $match = SubApplicationDraft::where('unit_file_no', $unitFileNo)->lockForUpdate()->first();

            if ($match) {
                return $match;
            }
        }

        if ($subApplicationId) {
            return SubApplicationDraft::where('sub_application_id', $subApplicationId)->lockForUpdate()->first();
        }

        if ($mainApplicationId) {
            return SubApplicationDraft::where('main_application_id', $mainApplicationId)
                ->where('is_sua', $isSUA)
                ->lockForUpdate()
                ->first();
        }

        return SubApplicationDraft::where('last_saved_by', Auth::id())
            ->where('is_sua', $isSUA)
            ->lockForUpdate()
            ->first();
    }

    private function createBlankDraft(?int $subApplicationId, ?int $mainApplicationId, int $userId, bool $isSUA = false): SubApplicationDraft
    {
        $now = now();

        return SubApplicationDraft::create([
            'sub_application_id' => $subApplicationId,
            'main_application_id' => $mainApplicationId,
            'form_state' => [],
            'progress_percent' => 0.0,
            'last_completed_step' => 1,
            'auto_save_frequency' => 30,
            'version' => 1,
            'last_saved_by' => $userId,
            'last_saved_at' => $now,
            'is_sua' => $isSUA,
        ]);
    }

    private function getUserDraftsCollection(int $userId, bool $isSUA = false): Collection
    {
        return SubApplicationDraft::where('last_saved_by', $userId)
            ->where('is_sua', $isSUA)
            ->freshFirst()
            ->get();
    }

    private function getUserDraftSummaries(int $userId, bool $isSUA = false, ?string $currentDraftId = null): array
    {
        $drafts = $this->getUserDraftsCollection($userId, $isSUA);

        return $drafts->map(function (SubApplicationDraft $draft) use ($currentDraftId) {
            return [
                'draft_id' => $draft->draft_id,
                'unit_file_no' => $draft->unit_file_no,
                'progress_percent' => $draft->progress_percent,
                'last_completed_step' => $draft->last_completed_step,
                'last_saved_at' => $draft->last_saved_at?->toIso8601String(),
                'label' => $draft->unit_file_no ?: 'Draft ' . substr($draft->draft_id, 0, 8),
                'is_current' => $currentDraftId && $draft->draft_id === $currentDraftId,
            ];
        })->toArray();
    }

    private function buildBootstrapPayload(SubApplicationDraft $draft, Collection $userDrafts): array
    {
        $draftSummaries = $userDrafts->map(function (SubApplicationDraft $d) use ($draft) {
            return [
                'draft_id' => $d->draft_id,
                'unit_file_no' => $d->unit_file_no,
                'progress_percent' => $d->progress_percent,
                'last_completed_step' => $d->last_completed_step,
                'last_saved_at' => $d->last_saved_at?->toIso8601String(),
                'label' => $d->unit_file_no ?: 'Draft ' . substr($d->draft_id, 0, 8),
                'is_current' => $d->draft_id === $draft->draft_id,
            ];
        })->toArray();

        return [
            'draft_id' => $draft->draft_id,
            'version' => $draft->version,
            'form_state' => $draft->form_state ?? [],
            'progress_percent' => $draft->progress_percent,
            'last_completed_step' => $draft->last_completed_step,
            'auto_save_frequency' => $draft->auto_save_frequency,
            'last_saved_at' => $draft->last_saved_at?->toIso8601String(),
            'unit_file_no' => $draft->unit_file_no,
            'is_sua' => $draft->is_sua,
            'collaborators' => $draft->collaborators ?? [],
            'drafts' => $draftSummaries,
            'mode' => count($draftSummaries) > 0 ? 'draft' : 'fresh',
            'sub_application_id' => $draft->sub_application_id,
            'main_application_id' => $draft->main_application_id,
        ];
    }

    private function transformDraftResponse(SubApplicationDraft $draft, ?Collection $userDrafts = null): array
    {
        $payload = [
            'success' => true,
            'draft_id' => $draft->draft_id,
            'version' => $draft->version,
            'form_state' => $draft->form_state ?? [],
            'progress_percent' => $draft->progress_percent,
            'last_completed_step' => $draft->last_completed_step,
            'auto_save_frequency' => $draft->auto_save_frequency,
            'last_saved_at' => $draft->last_saved_at?->toIso8601String(),
            'unit_file_no' => $draft->unit_file_no,
            'is_sua' => $draft->is_sua,
            'collaborators' => $draft->collaborators ?? [],
        ];

        if ($userDrafts) {
            $payload['drafts'] = $this->getUserDraftSummaries($draft->last_saved_by, $draft->is_sua, $draft->draft_id);
        }

        return $payload;
    }

    private function syncCollaborators(SubApplicationDraft $draft, array $userIds, string $role, bool $replace = false): void
    {
        try {
            if ($replace) {
                SubApplicationDraftCollaborator::where('draft_id', $draft->draft_id)->delete();
            }

            $existingUserIds = SubApplicationDraftCollaborator::where('draft_id', $draft->draft_id)
                ->pluck('user_id')
                ->toArray();

            $newUserIds = array_diff($userIds, $existingUserIds);

            foreach ($newUserIds as $userId) {
                SubApplicationDraftCollaborator::create([
                    'draft_id' => $draft->draft_id,
                    'user_id' => $userId,
                    'role' => $role,
                    'invited_at' => now(),
                    'accepted_at' => now(),
                ]);
            }

            $draft->collaborators = SubApplicationDraftCollaborator::where('draft_id', $draft->draft_id)
                ->pluck('user_id')
                ->toArray();
            $draft->save();
        } catch (Exception $e) {
            Log::warning('Failed to sync collaborators', [
                'draft_id' => $draft->draft_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function finalizeDraft(SubApplicationDraft $draft, bool $forceDelete = false): void
    {
        try {
            if ($forceDelete) {
                SubApplicationDraftVersion::where('draft_id', $draft->draft_id)->delete();
                SubApplicationDraftCollaborator::where('draft_id', $draft->draft_id)->delete();
                $draft->delete();

                return;
            }

            $draft->delete();
            SubApplicationDraftVersion::where('draft_id', $draft->draft_id)->delete();
            SubApplicationDraftCollaborator::where('draft_id', $draft->draft_id)->delete();
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

            if ($field === 'shared_areas') {
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
            1 => ['applicant_type', 'buyer_id'],
            2 => ['shared_areas'],
            3 => ['unit_measurement', 'land_use'],
            4 => ['unit_no'],
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

    private function findDraftByIdentifier(string $identifier): ?SubApplicationDraft
    {
        if (Str::isUuid($identifier)) {
            $match = SubApplicationDraft::where('draft_id', $identifier)->first();

            if ($match) {
                return $match;
            }
        }

        if (!$this->hasUnitFileNoColumn()) {
            return null;
        }

        return SubApplicationDraft::where('unit_file_no', $identifier)->first();
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
    /**
     * Hydrate a draft from an existing sub-application record
     */
    private function hydrateFromExistingSubApplication(int $subApplicationId, int $userId): ?SubApplicationDraft
    {
        try {
            $application = DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('id', $subApplicationId)
                ->first();

            if (!$application) {
                return null;
            }

            $isSUA = (bool) ($application->is_sua_unit ?? ($application->unit_type === 'SUA'));

            // Map fields to form_state
            $formState = [
                'sub_application_id' => $application->id,
                'main_application_id' => $application->main_application_id,
                'applicant_type' => $application->applicant_type,
                'applicantType' => $application->applicant_type, // For SecondaryFormController
                'applicant_title' => $application->applicant_title,
                'first_name' => $application->first_name,
                'middle_name' => $application->middle_name,
                'surname' => $application->surname,
                'corporate_name' => $application->corporate_name,
                'rc_number' => $application->rc_number,
                'email' => $application->email ?? $application->owner_email,
                'owner_email' => $application->email ?? $application->owner_email, // For SecondaryFormController
                'phone_number' => $application->phone_number,
                'property_location' => $application->property_location,
                'unit_number' => $application->unit_number,
                'floor_number' => $application->floor_number,
                'block_number' => $application->block_number,
                'unit_size' => $application->unit_size,
                'land_use' => $application->land_use,
                'application_fee' => $application->application_fee,
                'processing_fee' => $application->processing_fee,
                'site_plan_fee' => $application->site_plan_fee,
                'payment_date' => $application->payment_date,
                'receipt_number' => $application->receipt_number,
                'application_comment' => $application->application_comment,
                'scheme_no' => $application->scheme_no,
                'is_sua' => $isSUA ? '1' : '0',
                'unit_file_no' => $application->fileno,
                'mls_fileno' => $application->mls_fileno,
                'np_fileno' => $application->np_fileno,
                'date_captured' => $application->date_captured,
                'allocation_source' => $application->allocation_source,
                'allocation_entity' => $application->allocation_entity,
                'allocation_ref_no' => $application->allocation_ref_no,
                'shared_areas' => json_decode($application->shared_areas, true) ?? [],
                'multiple_owners_names' => json_decode($application->multiple_owners_names, true) ?? [],
                'multiple_owners_address' => json_decode($application->multiple_owners_address, true) ?? [],
                'multiple_owners_email' => json_decode($application->multiple_owners_email, true) ?? [],
                'multiple_owners_phone' => json_decode($application->multiple_owners_phone, true) ?? [],
                'multiple_owners_passport' => json_decode($application->multiple_owners_passport, true) ?? [],
                'multiple_owners_identification_type' => json_decode($application->multiple_owners_identification_type, true) ?? [],
                'multiple_owners_identification_image' => json_decode($application->multiple_owners_identification_image, true) ?? [],
            ];

            // Filter out null values to keep form_state clean
            $formState = array_filter($formState, function ($value) {
                return !is_null($value);
            });

            // Create the draft
            return SubApplicationDraft::create([
                'sub_application_id' => $application->id,
                'main_application_id' => $application->main_application_id,
                'form_state' => $formState,
                'progress_percent' => 100.0, // Existing record is complete
                'last_completed_step' => 4,
                'auto_save_frequency' => 30,
                'version' => 1,
                'last_saved_by' => $userId,
                'last_saved_at' => now(),
                'is_sua' => $isSUA,
            ]);

        } catch (Exception $e) {
            Log::error('Draft hydration failed', [
                'sub_application_id' => $subApplicationId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
