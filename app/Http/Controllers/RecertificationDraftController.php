<?php

namespace App\Http\Controllers;

use App\Models\RecertificationDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Draft mode for the recertification form.
 *
 * The form saves itself here (Save Draft, and a debounced autosave) once a land
 * file is chosen, and the officer resumes it from the list at the top of the
 * form. A draft is not validated beyond its shape: it is half-filled by
 * definition. RecertificationController::store() stays the only path that
 * writes an application, and it marks the draft submitted.
 *
 * Uploaded files cannot be kept in a draft; they are attached again on resume.
 */
class RecertificationDraftController extends Controller
{
    private const MAX_PAYLOAD_BYTES = 512 * 1024;

    /** The current officer's open drafts, newest first. */
    public function index()
    {
        $drafts = RecertificationDraft::query()
            ->ownedBy(Auth::id())
            ->open()
            ->orderByDesc('last_saved_at')
            ->limit(50)
            ->get()
            ->map(fn ($d) => $d->toClientArray());

        return response()->json(['success' => true, 'drafts' => $drafts]);
    }

    /** Create or overwrite a draft. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'draft_key'      => 'required|string|max:40',
            'land_file_no'   => 'nullable|string|max:100',
            'applicant_name' => 'nullable|string|max:255',
            'current_step'   => 'nullable|integer|min:1|max:20',
            // A JSON string, so a long form never runs into max_input_vars.
            'payload'        => 'required|string',
        ]);

        if (strlen($validated['payload']) > self::MAX_PAYLOAD_BYTES) {
            return response()->json(['success' => false, 'message' => 'This draft is too large to save.'], 413);
        }

        $payload = json_decode($validated['payload'], true);
        if (!is_array($payload)) {
            return response()->json(['success' => false, 'message' => 'Draft payload was not valid JSON.'], 422);
        }

        $draft = RecertificationDraft::query()->where('draft_key', $validated['draft_key'])->first();

        if ($draft && (int) $draft->user_id !== (int) Auth::id()) {
            return response()->json(['success' => false, 'message' => 'This draft belongs to another officer.'], 403);
        }
        if ($draft && $draft->status !== RecertificationDraft::STATUS_OPEN) {
            return response()->json([
                'success' => false,
                'message' => $draft->status === RecertificationDraft::STATUS_SUBMITTED
                    ? 'This draft has already been submitted.'
                    : 'This draft was discarded.',
            ], 409);
        }

        $landFile = isset($validated['land_file_no']) ? strtoupper(trim($validated['land_file_no'])) : null;

        $draft = $draft ?: new RecertificationDraft([
            'draft_key' => $validated['draft_key'],
            'user_id'   => Auth::id(),
            'status'    => RecertificationDraft::STATUS_OPEN,
        ]);
        $draft->fill([
            'land_file_no'   => $landFile ?: null,
            'applicant_name' => isset($validated['applicant_name']) ? trim($validated['applicant_name']) ?: null : null,
            'current_step'   => $validated['current_step'] ?? 1,
            'payload'        => $payload,
            'last_saved_at'  => now(),
        ]);
        $draft->save();

        // One open draft per land file per officer: the one being worked on wins.
        if ($landFile) {
            RecertificationDraft::query()
                ->ownedBy(Auth::id())
                ->open()
                ->where('land_file_no', $landFile)
                ->where('id', '<>', $draft->id)
                ->update(['status' => RecertificationDraft::STATUS_DISCARDED, 'updated_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'draft'   => $draft->toClientArray(),
            // Saving keeps the session alive; a fresh token keeps the submit off a 419.
            'csrf_token' => csrf_token(),
        ]);
    }

    public function show(string $draftKey)
    {
        $draft = $this->findOwned($draftKey);
        if (!$draft || $draft->status !== RecertificationDraft::STATUS_OPEN) {
            return response()->json(['success' => false, 'message' => 'Draft not found.'], 404);
        }

        return response()->json(['success' => true, 'draft' => $draft->toClientArray(true)]);
    }

    /** Discard: a status change, so a draft thrown away by mistake can still be recovered. */
    public function destroy(string $draftKey)
    {
        $draft = $this->findOwned($draftKey);
        if (!$draft) {
            return response()->json(['success' => false, 'message' => 'Draft not found.'], 404);
        }

        $draft->status = RecertificationDraft::STATUS_DISCARDED;
        $draft->save();

        return response()->json(['success' => true]);
    }

    /** Called by the application store once the application is written. */
    public static function markSubmitted(?string $draftKey, int $applicationId): void
    {
        if (!$draftKey) {
            return;
        }

        RecertificationDraft::query()
            ->where('draft_key', $draftKey)
            ->where('user_id', Auth::id())
            ->update([
                'status'         => RecertificationDraft::STATUS_SUBMITTED,
                'application_id' => $applicationId,
                'updated_at'     => now(),
            ]);
    }

    public static function newKey(): string
    {
        return (string) Str::uuid();
    }

    private function findOwned(string $draftKey): ?RecertificationDraft
    {
        return RecertificationDraft::query()
            ->where('draft_key', $draftKey)
            ->ownedBy(Auth::id())
            ->first();
    }
}
