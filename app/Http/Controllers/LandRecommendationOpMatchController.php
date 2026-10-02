<?php

namespace App\Http\Controllers;

use App\Models\LandRecommendation;
use App\Models\LandRecommendationDocument;
use App\Services\OpHolderMatchService;
use App\Support\LandRecommendationLog as RecLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The OP-holder mismatch flow on the Recommendation capture form.
 *
 * When the officer picks a file whose Occupancy Permit was granted to one person
 * while File Indexing holds another — and no transfer on the file explains the
 * change — the form shows the file's chain and offers Match. Match writes the
 * missing Transfer of Title so the chain reads correctly, and the capture then
 * continues in "existing recommendation" mode: no new letter is generated, the
 * approved one is uploaded here instead, and approval waits for it.
 *
 * Generating the transfer is deliberately NOT left to the officer to do by hand in
 * the instrument screens. It is one row with two names already on the file, and
 * asking a person to key it is how the wrong parties, the wrong file or nothing at
 * all ends up recorded.
 *
 * @see \App\Services\OpHolderMatchService  the rule, shared with the audit query
 *      in database/sql/2026_08_29_op_holder_vs_indexing_check.sql
 */
class LandRecommendationOpMatchController extends Controller
{
    /** 20 MB — a phone photograph of an A4 letter is comfortably inside this. */
    private const MAX_KILOBYTES = 20480;

    /** What a scanner or a phone actually produces. */
    private const ACCEPTED = 'jpg,jpeg,png,webp,heic,pdf';

    public function __construct(private OpHolderMatchService $matcher)
    {
    }

    /**
     * Does this file need a Match, and what does its chain look like?
     *
     * Called as the file number is chosen. Always 200 with a payload — the form
     * treats "cannot tell" the same as "does not apply", because a failed check
     * must never block an ordinary capture.
     */
    public function check(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number', ''));

        $state = $this->matcher->check($fileNumber);

        if ($state['applies']) {
            RecLog::info('OP holder mismatch detected on capture', [
                'file_number'   => $fileNumber,
                'op_pra_id'     => $state['op']['pra_id'] ?? null,
                'op_holder'     => $state['op']['holder'] ?? null,
                'indexing_name' => $state['indexing_name'],
            ]);
        }

        return response()->json(['success' => true, 'data' => $state]);
    }

    /**
     * Match: write the missing Transfer of Title for this file.
     *
     * The service re-checks before it writes, so a file matched from a second tab
     * a moment earlier is refused here rather than transferred twice.
     */
    public function match(Request $request)
    {
        $fileNumber = trim((string) $request->input('file_number', ''));

        if ($fileNumber === '') {
            return response()->json(['success' => false, 'message' => 'No file number given.'], 422);
        }

        $result = $this->matcher->generateTot($fileNumber, Auth::id());

        if (! $result['ok']) {
            RecLog::warning('Match refused', ['file_number' => $fileNumber, 'reason' => $result['message']]);

            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        RecLog::info('Match generated Transfer of Title', [
            'file_number' => $fileNumber,
            'pra_id'      => $result['pra_id'],
        ]);

        // Hand back the fresh state so the card can redraw from one source of truth
        // rather than guessing what the write changed.
        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'pra_id'  => $result['pra_id'],
            'data'    => $this->matcher->check($fileNumber),
        ]);
    }

    /**
     * Store (or replace) the approved recommendation letter for one record.
     */
    public function storeDocument(Request $request, $id)
    {
        $request->validate([
            'document' => 'required|file|mimes:' . self::ACCEPTED . '|max:' . self::MAX_KILOBYTES,
        ], [
            'document.mimes' => 'Upload the letter as an image (JPG, PNG, WEBP, HEIC) or a PDF.',
            'document.max'   => 'That file is larger than 20 MB. Scan it at a lower resolution and try again.',
        ]);

        $recommendation = LandRecommendation::find($id);

        if (! $recommendation) {
            return $this->fail($request, 'That recommendation no longer exists.', 404);
        }

        $file = $request->file('document');

        // Named for the record, so the file is identifiable on disk on its own — a
        // scan found in a backup should not need the database to say what it is. The
        // random tail stops a replace within the same second from landing on the name
        // it is replacing, which the cleanup below would then delete.
        $name = 'approved-recommendation-' . $recommendation->id . '-' . now()->format('YmdHis')
            . '-' . strtolower(substr(bin2hex(random_bytes(3)), 0, 6))
            . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');

        $path = $file->storeAs(LandRecommendationDocument::DIRECTORY, $name, 'public');

        if (! $path) {
            RecLog::error('Approved recommendation upload failed to store', [
                'recommendation_id' => $recommendation->id,
                'file_number'       => $recommendation->file_number,
            ]);

            return $this->fail($request, 'The letter could not be saved. Try again.', 500);
        }

        $existing = LandRecommendationDocument::where('land_recommendation_id', $recommendation->id)->first();
        $previousPath = $existing?->path;

        $document = LandRecommendationDocument::updateOrCreate(
            ['land_recommendation_id' => $recommendation->id],
            [
                'file_number'   => $recommendation->file_number,
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getClientMimeType(),
                'size_bytes'    => $file->getSize(),
                'uploaded_by'   => Auth::id(),
                'uploaded_at'   => now(),
            ]
        );

        // A record has one current letter; the scan it replaced is not evidence of
        // anything once a corrected one is signed.
        if ($previousPath && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        RecLog::info('Approved recommendation uploaded', [
            'recommendation_id' => $recommendation->id,
            'file_number'       => $recommendation->file_number,
            'replaced'          => (bool) $previousPath,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Approved recommendation uploaded. This record can now be approved.',
                'document' => $this->documentPayload($document),
            ]);
        }

        return back()->with('success', 'Approved recommendation uploaded.');
    }

    /** The stored letter for one record, or a 404 when there is none. */
    public function showDocument(Request $request, $id)
    {
        $document = LandRecommendationDocument::where('land_recommendation_id', $id)->first();

        if (! $document) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'No approved recommendation has been uploaded for this record.'], 404)
                : abort(404, 'No approved recommendation has been uploaded for this record.');
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'document' => $this->documentPayload($document)]);
        }

        return redirect($document->url());
    }

    /** Remove the stored letter. The record then cannot be approved until a new one is uploaded. */
    public function destroyDocument(Request $request, $id)
    {
        $document = LandRecommendationDocument::where('land_recommendation_id', $id)->first();

        if (! $document) {
            return $this->fail($request, 'There is nothing to remove.', 404);
        }

        Storage::disk('public')->delete($document->path);
        $document->delete();

        RecLog::warning('Approved recommendation removed', ['recommendation_id' => (int) $id]);

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Approved recommendation removed.'])
            : back()->with('success', 'Approved recommendation removed.');
    }

    private function documentPayload(LandRecommendationDocument $document): array
    {
        return [
            'id'            => $document->id,
            'url'           => $document->url(),
            'original_name' => $document->original_name,
            'summary'       => $document->summary(),
            'inline'        => $document->isViewableInline(),
            'uploaded_at'   => optional($document->uploaded_at)->format('d M Y g:i A'),
        ];
    }

    private function fail(Request $request, string $message, int $status)
    {
        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => $message], $status)
            : back()->withErrors(['document' => $message]);
    }
}
