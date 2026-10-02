<?php

namespace App\Http\Controllers;

use App\Models\LandRecommendation;
use App\Models\LandRecommendationBatchDocument;
use App\Services\EdmsScanUploadFolderService;
use App\Support\LandRecommendationLog as RecLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

/**
 * The mother's scanned recommendation letter for a subdivision batch.
 *
 * A subdivision is one grant split into plots, so its children inherit the
 * mother's recommendation instead of earning one each: the letter is printed once,
 * for the mother, and signed on paper. The mothers of these batches have no
 * recommendation record in KLAES — the letter exists only as that signed sheet —
 * so a child has nothing of its own to print and nothing to link to.
 *
 * That sheet is scanned once here and hung off the batch. Every child in the batch
 * then shows View in place of Print, opening this one document; there is no
 * per-child copy to keep in step, and no second upload to reconcile.
 *
 * Uploading again replaces the file and the row, and deletes the scan it replaced
 * — a batch has one current letter, and the old scan is not evidence of anything
 * once a corrected one is signed.
 *
 * ── Where the scan lives ─────────────────────────────────────────────────────
 *
 * In the mother file's own EDMS folder, the same one the scanning and page-typing
 * modules use:
 *
 *      EDMS/SCAN_UPLOAD/{Registry_Slug}/{MOTHER FILE NO}/Recommendation/
 *
 * created if it is not already there. It used to go to a folder of this module's
 * own (LandRecommendationBatchDocument::DIRECTORY), which put the one document
 * that belongs to a file somewhere nobody looking at that file would find it.
 * EdmsScanUploadFolderService supplies the registry slug and the file-number
 * sanitiser, so this folder is byte-identical to the one a scan operator writes
 * into — two spellings of the same file number would otherwise be two folders.
 *
 * The absolute path is resolved through file_storage_path(), NOT storage_path():
 * STORAGE_PATH in .env is what points the EDMS tree at the F: drive on production,
 * and only that helper reads it (the storage_path() override in app/Helper is dead
 * — Laravel defines the function first, so the !function_exists guard never
 * passes). Reading goes back through the model, which tries the EDMS root first
 * and the public disk second, so rows written before this change still open.
 */
class LandRecommendationBatchDocumentController extends Controller
{
    /** 20 MB. A phone photograph of an A4 letter is comfortably inside this. */
    private const MAX_KILOBYTES = 20480;

    /**
     * What a scanner or a phone actually produces. PDF is included because a
     * multi-page letter comes off a departmental scanner as one, and refusing it
     * would send the officer away to convert a file for no reason.
     */
    private const ACCEPTED = 'jpg,jpeg,png,webp,heic,pdf';

    /**
     * Store (or replace) the scan for one batch.
     */
    public function store(Request $request, string $batchId)
    {
        $request->validate([
            'document' => 'required|file|mimes:' . self::ACCEPTED . '|max:' . self::MAX_KILOBYTES,
        ], [
            'document.mimes' => 'Upload the letter as an image (JPG, PNG, WEBP, HEIC) or a PDF.',
            'document.max'   => 'That file is larger than 20 MB. Scan it at a lower resolution and try again.',
        ]);

        // The batch has to exist, and it has to be a subdivision: a regular batch is
        // an arbitrary set of unrelated files with no mother whose letter could
        // cover them, and attaching one would say something untrue about all of them.
        $batch = $this->batchOrNull($batchId);

        if (!$batch) {
            return $this->fail($request, 'That record no longer exists.', 404);
        }

        if (trim((string) $batch->batch_mother_file_no) === '') {
            return $this->fail($request, 'There is no mother file on this record, so there is no mother '
                . 'recommendation to upload. Set the mother file number on it first.', 422);
        }

        // A file merged from two or more parents inherits a recommendation from
        // EACH of them, so a Use Subdivision Template record can hold several
        // letters side by side. Asking for the next free slot turns this upload
        // into an addition rather than a replace; without it the key is unchanged
        // and the existing letter is replaced, which is what a re-scan means and
        // what a batch -- which has exactly one mother -- always does.
        $recordId = LandRecommendationBatchDocument::subdivisionTemplateId($batchId);

        if ($recordId !== null && $request->boolean('add_another')) {
            $batchId = LandRecommendationBatchDocument::subdivisionTemplateKey(
                $recordId,
                LandRecommendationBatchDocument::nextSubdivisionTemplateSlot($recordId)
            );
        }

        $file = $request->file('document');

        // Read before the move below. storeAs() left the uploaded temp file where it
        // was, so these could be asked afterwards; move() consumes it, and getSize()
        // stats a path that no longer exists.
        $originalName = $file->getClientOriginalName();
        $mimeType     = $file->getClientMimeType();
        $sizeBytes    = $file->getSize();

        // Named for the batch, so the file is identifiable on disk on its own — a
        // scan found in a backup should not need the database to say what it is.
        // The random tail is not decoration: a replace within the same second would
        // otherwise land on the timestamped name it is replacing, and the cleanup
        // below would be deleting the file it had just written.
        $name = 'mother-recommendation-' . $batchId . '-' . now()->format('YmdHis')
            . '-' . strtolower(substr(bin2hex(random_bytes(3)), 0, 6))
            . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');

        $directory = $this->edmsDirectoryFor($batch->batch_mother_file_no);

        if (!$directory) {
            return $this->fail($request, 'This batch has no usable mother file number, so there is no '
                . 'EDMS folder to file the letter in.', 422);
        }

        $absoluteDirectory = $this->absolute($directory);

        try {
            // Created here rather than left to the uploader: the mother of an old
            // subdivision may never have been scanned, so its EDMS folder need not
            // exist yet — and a letter with nowhere to go is how this ends up back
            // in a folder of its own.
            File::ensureDirectoryExists($absoluteDirectory);

            $file->move($absoluteDirectory, $name);
        } catch (\Throwable $e) {
            RecLog::error('Batch document upload failed to store', [
                'rofo_batch_id' => $batchId,
                'original_name' => $originalName,
                'directory'     => $absoluteDirectory,
                'error'         => $e->getMessage(),
            ]);

            return $this->fail($request, 'The file could not be saved to the EDMS folder for '
                . $batch->batch_mother_file_no . '. Check that the EDMS drive is reachable, then try again.', 500);
        }

        // Stored relative to app/public, the same shape scannings.document_path
        // uses — so the row survives the EDMS tree moving to another drive.
        $path = $directory . '/' . $name;

        $existing = LandRecommendationBatchDocument::where('rofo_batch_id', $batchId)->first();
        $replaced = $existing?->path;

        $document = LandRecommendationBatchDocument::updateOrCreate(
            ['rofo_batch_id' => $batchId],
            [
                'mother_file_no' => $batch->batch_mother_file_no,
                'path'           => $path,
                'original_name'  => $originalName,
                'mime_type'      => $mimeType,
                'size_bytes'     => $sizeBytes,
                'uploaded_by'    => Auth::id(),
                'uploaded_at'    => now(),
            ]
        );

        // Only once the new row is committed. Deleting first would leave the batch
        // with no letter at all if the write below it failed.
        if ($replaced && $replaced !== $path) {
            $this->deleteScan($replaced);
        }

        RecLog::info($replaced ? 'Mother recommendation replaced' : 'Mother recommendation uploaded', [
            'rofo_batch_id'  => $batchId,
            'mother_file_no' => $batch->batch_mother_file_no,
            'children'       => $batch->children,
            'original_name'  => $document->original_name,
            'size_bytes'     => $document->size_bytes,
            'replaced_path'  => $replaced,
        ]);

        // A Use Subdivision Template record is a batch of one, and "all 1 children"
        // is not a sentence. Same fact either way, said the way it reads.
        $single = (int) $batch->children === 1;

        return response()->json([
            'success'  => true,
            'message'  => $replaced
                ? ($single
                    ? 'The mother recommendation was replaced. This record now shows the new copy.'
                    : 'The mother recommendation was replaced. All ' . $batch->children . ' children now show the new copy.')
                : ($single
                    ? 'Uploaded. This record now shows the mother recommendation.'
                    : 'Uploaded. All ' . $batch->children . ' children of this batch now show this recommendation.'),
            'document' => $this->payload($document, $batchId),
        ]);
    }

    /**
     * Open the scan. Every child of the batch links here, so one route answers for
     * the whole batch and the storage path never reaches the page.
     */
    public function show(string $batchId)
    {
        $document = LandRecommendationBatchDocument::where('rofo_batch_id', $batchId)->first();

        // Resolved through the model, which tries the EDMS root before the public
        // disk — a scan uploaded before the move to EDMS still opens from here.
        $absolute = $document?->absolutePath();

        if (!$absolute) {
            if ($document) {
                RecLog::warning('Mother recommendation row has no file behind it', [
                    'rofo_batch_id' => $batchId,
                    'path'          => $document->path,
                ]);
            }

            abort(404, 'No mother recommendation has been uploaded for this batch.');
        }

        // Images and PDFs open in the tab the officer clicked from; anything else is
        // handed over as a download rather than dumped into the browser as bytes.
        return response()->file(
            $absolute,
            $document->isViewableInline()
                ? ['Content-Disposition' => 'inline; filename="' . addslashes($document->original_name ?: basename($document->path)) . '"']
                : []
        );
    }

    /**
     * Remove the scan, putting the batch's children back to "Upload".
     */
    public function destroy(Request $request, string $batchId)
    {
        $document = LandRecommendationBatchDocument::where('rofo_batch_id', $batchId)->first();

        if (!$document) {
            return $this->fail($request, 'Nothing has been uploaded for this batch.', 404);
        }

        $this->deleteScan($document->path);
        $document->delete();

        RecLog::warning('Mother recommendation removed', [
            'rofo_batch_id' => $batchId,
            'path'          => $document->path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'The mother recommendation was removed. Upload a new one to restore it for the children.',
        ]);
    }

    /**
     * The batch as a row: its mother and how many children it carries. A batch is
     * not a table of its own — it is a rofo_batch_id shared by its children — so it
     * is summarised from them.
     */
    private function batchOrNull(string $batchId)
    {
        // "Use Subdivision Template": one file printing as a subdivision child with
        // no batch behind it. There is no rofo_batch_id to group on, and the mother
        // it names is its own old_file_number rather than batch_mother_file_no -- so
        // it is addressed by a key built from its id and summarised as a batch of
        // one. Everything downstream reads only batch_mother_file_no and children,
        // so the upload, the EDMS folder and the replace all work unchanged.
        if ($id = LandRecommendationBatchDocument::subdivisionTemplateId($batchId)) {
            $record = LandRecommendation::query()
                ->whereKey($id)
                ->where('use_subdivision_template', true)
                ->first(['id', 'old_file_number']);

            if (!$record) {
                return null;
            }

            return (object) [
                'batch_mother_file_no' => $record->old_file_number,
                'children'             => 1,
            ];
        }

        return LandRecommendation::query()
            ->where('rofo_batch_id', $batchId)
            ->selectRaw('MAX(batch_mother_file_no) AS batch_mother_file_no, COUNT(*) AS children')
            ->havingRaw('COUNT(*) > 0')
            ->first();
    }

    /**
     * The mother file's EDMS folder for this letter, relative to app/public.
     *
     * The registry slug and the file-number sanitiser come from
     * EdmsScanUploadFolderService rather than being spelled out again here: that
     * service is what commissioning and indexing use to make these folders, and a
     * second copy of the rule is how a letter ends up in "IND-1990-63" while the
     * scans sit in "IND 1990 63".
     *
     * Lands Registry, because a subdivision mother is a land file — the same
     * assumption the OSS passport upload makes for the same reason.
     */
    private function edmsDirectoryFor(?string $motherFileNo): ?string
    {
        $service = app(EdmsScanUploadFolderService::class);

        $folder = $service->folderName((string) $motherFileNo);

        if ($folder === '') {
            return null;
        }

        return EdmsScanUploadFolderService::BASE_PATH
            . '/' . $service->registrySlug(EdmsScanUploadFolderService::FOLIO_HOME_REGISTRY)
            . '/' . $folder
            . '/' . LandRecommendationBatchDocument::EDMS_CATEGORY;
    }

    /**
     * A path relative to app/public, as an absolute path on this server.
     *
     * file_storage_path() and not storage_path(): STORAGE_PATH in .env is what puts
     * the EDMS tree on the F: drive, and only this helper reads it. With
     * STORAGE_PATH unset the two are the same path, so a dev box is unaffected.
     */
    private function absolute(string $relative): string
    {
        $relative = ltrim($relative, '/\\');

        return function_exists('file_storage_path')
            ? file_storage_path('app/public/' . $relative)
            : storage_path('app/public/' . $relative);
    }

    /**
     * Delete a scan wherever it actually is — EDMS or the old public-disk folder.
     *
     * Never fatal: the row is the record of the letter, and failing to remove a
     * superseded file must not leave the batch without a current one.
     */
    private function deleteScan(?string $relative): void
    {
        $relative = ltrim((string) $relative, '/\\');

        if ($relative === '') {
            return;
        }

        $document = new LandRecommendationBatchDocument(['path' => $relative]);
        $document->path = $relative;

        $absolute = $document->absolutePath();

        if (!$absolute) {
            return;
        }

        try {
            File::delete($absolute);
        } catch (\Throwable $e) {
            RecLog::warning('Could not delete a superseded mother recommendation', [
                'path'  => $absolute,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * What the page needs to swap a row from "Upload" to "View".
     */
    private function payload(LandRecommendationBatchDocument $document, string $batchId): array
    {
        return [
            'view_url'      => route('land-recommendations.batch-document.show', $batchId),
            'original_name' => $document->original_name,
            'summary'       => $document->summary(),
            'uploaded_at'   => optional($document->uploaded_at)->format('d/m/Y H:i'),
        ];
    }

    private function fail(Request $request, string $message, int $status)
    {
        RecLog::warning('Batch document request refused', [
            'message' => $message,
            'status'  => $status,
        ]);

        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
