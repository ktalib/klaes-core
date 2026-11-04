<?php

namespace App\Http\Controllers;

use App\Models\MlsCommissionDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Draft mode for the MLPP Commission New File Number card (MLS FC).
 *
 * The card saves itself here (Save Draft, a periodic autosave while it is open,
 * and on close) and the officer resumes it from My Drafts on the generator page.
 * A draft is not validated beyond its shape: it is half-filled by definition.
 * MlsFileNoController's generate endpoints stay the only path that commissions a
 * file; the page reports each success back through committed().
 *
 * Nothing is reserved for a draft. The serial, tracking ID and reclaimed-serial
 * list are read afresh on resume, and the passport is attached again.
 */
class MlsCommissionDraftController extends Controller
{
    /** A batch of 100+ entries with OP context serialises to well under this. */
    private const MAX_PAYLOAD_BYTES = 2 * 1024 * 1024;

    /** The current officer's open drafts, newest first. */
    public function index()
    {
        $drafts = MlsCommissionDraft::query()
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
            'draft_key'  => ['required', 'string', 'regex:/^[A-Za-z0-9-]{8,40}$/'],
            'file_title' => 'nullable|string|max:255',
            'file_type'  => 'nullable|string|max:60',
            'land_use'   => 'nullable|string|max:30',
            'plot_no'    => 'nullable|string|max:100',
            'district'   => 'nullable|string|max:150',
            'lga'        => 'nullable|string|max:150',
            // A JSON string, so a large batch never runs into max_input_vars.
            'payload'    => 'required|string',
        ]);

        if (strlen($validated['payload']) > self::MAX_PAYLOAD_BYTES) {
            return response()->json(['success' => false, 'message' => 'This draft is too large to save.'], 413);
        }

        $payload = json_decode($validated['payload'], true);
        if (!is_array($payload)) {
            return response()->json(['success' => false, 'message' => 'Draft payload was not valid JSON.'], 422);
        }

        $draft = MlsCommissionDraft::query()->where('draft_key', $validated['draft_key'])->first();

        if ($draft && (int) $draft->user_id !== (int) Auth::id()) {
            return response()->json(['success' => false, 'message' => 'This draft belongs to another officer.'], 403);
        }
        if ($draft && $draft->status !== MlsCommissionDraft::STATUS_OPEN) {
            return response()->json([
                'success' => false,
                'status'  => $draft->status,
                'message' => $draft->status === MlsCommissionDraft::STATUS_SUBMITTED
                    ? 'This draft has already been generated.'
                    : 'This draft was discarded.',
            ], 409);
        }

        $clean = fn ($key) => isset($validated[$key]) && trim((string) $validated[$key]) !== ''
            ? trim((string) $validated[$key])
            : null;

        $draft = $draft ?: new MlsCommissionDraft([
            'draft_key' => $validated['draft_key'],
            'user_id'   => Auth::id(),
            'status'    => MlsCommissionDraft::STATUS_OPEN,
        ]);
        $draft->fill([
            'file_title'    => $clean('file_title'),
            'file_type'     => $clean('file_type'),
            'land_use'      => $clean('land_use'),
            'plot_no'       => $clean('plot_no'),
            'district'      => $clean('district'),
            'lga'           => $clean('lga'),
            'payload'       => $payload,
            'last_saved_at' => now(),
        ]);
        $draft->save();

        return response()->json([
            'success' => true,
            'draft'   => $draft->toClientArray(),
            // Saving keeps the session alive; a fresh token keeps Generate off a 419.
            'csrf_token' => csrf_token(),
        ]);
    }

    public function show(string $draftKey)
    {
        $draft = $this->findOwned($draftKey);
        if (!$draft || $draft->status !== MlsCommissionDraft::STATUS_OPEN) {
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

        if ($draft->status === MlsCommissionDraft::STATUS_OPEN) {
            $draft->status = MlsCommissionDraft::STATUS_DISCARDED;
            $draft->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * The card was generated: close the draft and note what it became. Called by
     * the page after any of the generate paths (single, batch, duplex, CoP) succeeds.
     */
    public function committed(Request $request, string $draftKey)
    {
        $validated = $request->validate([
            'file_numbers'   => 'nullable|array|max:500',
            'file_numbers.*' => 'nullable|string|max:150',
        ]);

        $draft = MlsCommissionDraft::query()->where('draft_key', $draftKey)->first();
        if ($draft && (int) $draft->user_id !== (int) Auth::id()) {
            return response()->json(['success' => false, 'message' => 'This draft belongs to another officer.'], 403);
        }
        if (!$draft) {
            // The card's first save may still be on its way. Record the key as generated
            // so that save is refused (409) instead of opening a draft for a done card.
            $draft = new MlsCommissionDraft([
                'draft_key'     => $draftKey,
                'user_id'       => Auth::id(),
                'last_saved_at' => now(),
            ]);
        }

        $numbers = collect($validated['file_numbers'] ?? [])
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->implode(', ');

        $draft->status = MlsCommissionDraft::STATUS_SUBMITTED;
        $draft->committed_file_numbers = $numbers !== '' ? mb_substr($numbers, 0, 2000) : null;
        $draft->save();

        return response()->json(['success' => true]);
    }

    private function findOwned(string $draftKey): ?MlsCommissionDraft
    {
        return MlsCommissionDraft::query()
            ->where('draft_key', $draftKey)
            ->ownedBy(Auth::id())
            ->first();
    }
}
