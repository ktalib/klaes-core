<?php

namespace App\Http\Controllers\OnlineLegalSearch;

use App\Http\Controllers\Controller;
use App\Http\Requests\OnlineLegalSearch\StoreIdVerificationRequest;
use App\Models\LegalSearchOnlineVerification;
use App\Services\CallToBarVerificationService;
use App\Services\IdNameVerificationService;
use App\Services\LegalSearchApprovalService;
use App\Services\Ocr\OcrException;
use App\Services\Ocr\OcrImagePreprocessor;
use App\Services\Ocr\OcrReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ID NAME verification for the public Online Legal Search payment card.
 *
 * WHAT THIS ESTABLISHES: that the full name the applicant typed also appears in
 * the text OCR read off the identification they uploaded. It is NOT proof that
 * the document is genuine, that it is unaltered, that the uploader is its
 * rightful holder, or that the ID number is valid with the issuing authority.
 * Nothing here or downstream may present it as identity verification.
 *
 * Order of operations, enforced server-side:
 *   validate -> store privately -> OCR -> compare -> persist -> gate payment.
 * The Paystack checkout is opened by the browser (Paystack Inline), so the gate
 * is a session-bound verified row that verifyPayment() re-checks; a browser that
 * skips this endpoint cannot manufacture one.
 */
class OnlineLsIdVerificationController extends Controller
{
    public function __construct(
        private readonly IdNameVerificationService $nameVerification,
        private readonly CallToBarVerificationService $barVerification,
        private readonly OcrReader $ocr,
        private readonly OcrImagePreprocessor $preprocessor,
    ) {
    }

    /**
     * Receive the applicant's identification, verify the name, and decide whether
     * the checkout may open.
     */
    public function store(StoreIdVerificationRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Re-submitting the same identification for the same search must not pile
        // up rows (or duplicate applicant records). The session already holding a
        // verification for this file number is reused and overwritten in place.
        $verification = $this->existingForSession($data['file_number']);

        $frontPath = null;

        try {
            $frontPath = $this->storePrivately($request->file('id_front'), 'front');
        } catch (\Throwable $e) {
            $this->deleteQuietly([$frontPath]);

            Log::error('Online LS identification upload failed', [
                'file_number' => $data['file_number'],
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'We could not save your identification. Please try again.',
            ], 500);
        }

        // --- OCR -------------------------------------------------------------
        // An engine fault must never take down the payment page. It is logged in
        // full for us and reported to the applicant as a temporary outage — NOT as
        // an unreadable document, which would send them into a pointless loop
        // re-photographing a perfectly good ID while the real fault is server-side
        // (a missing tesseract binary being the usual cause).
        //
        // The row is left `pending` rather than `failed`: no comparison ever ran,
        // so recording a name mismatch against this applicant would be untrue.
        try {
            $extractedText = $this->ocrOf($frontPath);
        } catch (OcrException $e) {
            Log::error('Online LS ID verification OCR failed', [
                'file_number' => $data['file_number'],
                'error'       => $e->getMessage(),
                'previous'    => $e->getPrevious()?->getMessage(),
            ]);

            $this->persist($verification, $data, $frontPath, [
                'status' => LegalSearchOnlineVerification::STATUS_PENDING,
                'score'  => 0,
                'extracted_text' => '',
            ], $this->barVerification->check($data['call_to_bar_number'] ?? null, '', ''), $request);

            return $this->outcome(
                LegalSearchOnlineVerification::STATUS_PENDING,
                0,
                (string) config('id_verification.messages.unavailable')
            );
        }

        // The engine ran but found nothing legible. Distinct from the fault above:
        // this one the applicant can fix with a better photo.
        if (trim($extractedText) === '') {
            $this->persist($verification, $data, $frontPath, [
                'status' => LegalSearchOnlineVerification::STATUS_FAILED,
                'score'  => 0,
                'extracted_text' => '',
            ], $this->barVerification->check($data['call_to_bar_number'] ?? null, '', ''), $request);

            return $this->outcome(
                LegalSearchOnlineVerification::STATUS_FAILED,
                0,
                (string) config('id_verification.messages.unreadable')
            );
        }

        $result = $this->nameVerification->compare($data['applicant_full_name'], $extractedText);

        // A lawyer's Call-to-Bar number is checked against the same OCR text, and
        // against a roll of practitioners if one is configured. For an individual
        // there is no number and this returns `not_applicable`.
        $bar = $this->barVerification->check(
            $data['call_to_bar_number'] ?? null,
            $extractedText,
            $data['applicant_full_name']
        );

        // A number the roll positively REJECTS pulls a passing name match down to
        // `review` — a human decides. Nothing else about the bar number moves the
        // status: `unconfirmed` is the ordinary outcome for a genuine lawyer whose
        // ID simply does not print the number, and failing on it would stop every
        // lawyer from completing a search.
        if ($bar['status'] === CallToBarVerificationService::STATUS_REJECTED
            && $result['status'] === LegalSearchOnlineVerification::STATUS_VERIFIED) {
            $result['status'] = LegalSearchOnlineVerification::STATUS_REVIEW;
        }

        $this->persist($verification, $data, $frontPath, $result, $bar, $request);

        return $this->outcome(
            $result['status'],
            $result['score'],
            $this->messageFor($result['status'], $bar)
        );
    }

    /**
     * The applicant-facing sentence, with the lawyer's bar-number outcome appended
     * where there is something worth saying about it.
     */
    private function messageFor(string $status, array $bar): string
    {
        $message = (string) config(
            'id_verification.messages.' . $status,
            config('id_verification.messages.failed')
        );

        $suffix = match ($bar['status']) {
            CallToBarVerificationService::STATUS_MATCHED     => config('id_verification.messages.bar_matched'),
            CallToBarVerificationService::STATUS_UNCONFIRMED => config('id_verification.messages.bar_unconfirmed'),
            CallToBarVerificationService::STATUS_REJECTED    => config('id_verification.messages.bar_rejected'),
            default => null,
        };

        return $suffix ? trim($message . ' ' . $suffix) : $message;
    }

    /**
     * Stream a stored identification image to an authorized reviewer.
     *
     * Gated on the same Director / Deputy Director check that guards the rest of
     * the Online Legal Search approval queue. The path is never in the HTML and
     * never in JavaScript — this route addresses the row and the side, so one
     * applicant's document cannot be reached from another applicant's id, and a
     * guessed storage path leads nowhere because the disk is outside /storage.
     */
    public function document(
        Request $request,
        int $id,
        string $side,
        LegalSearchApprovalService $approvalService
    ): BinaryFileResponse {
        abort_unless(
            $approvalService->isApprover($request->user()),
            403,
            'Only a Director or Deputy Director may view submitted identification.'
        );

        // 'back' is still accepted: rows captured before the back image was
        // dropped have one on file, and a reviewer must still be able to open it.
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $verification = LegalSearchOnlineVerification::findOrFail($id);
        $path = $side === 'front' ? $verification->id_front_path : $verification->id_back_path;

        abort_if(empty($path), 404, 'No document was uploaded for this side.');

        $disk = Storage::disk($this->disk());
        abort_unless($disk->exists($path), 404, 'The stored document is no longer available.');

        // WHY NOT A PUBLIC ASSET: these are government ID documents. Anything
        // under public/ (or reachable via the storage:link symlink and asset())
        // is served straight off disk by the web server, with no PHP and
        // therefore no authorisation - the URL alone would be enough for anyone
        // to read someone's NIN slip. That is exactly what the private disk and
        // this approver-gated route exist to prevent, so serving these as assets
        // is not an option regardless of how much simpler it would be.
        //
        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';

        $response = response()->file($disk->path($path), [
            'Content-Type'           => $mimeType,
            // Inline, and never under the applicant's own uploaded filename.
            'Content-Disposition'    => 'inline; filename="identification-' . $verification->id . '-' . $side . '.' . $extension . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // BinaryFileResponse initialises a public cache policy internally, so set
        // this after creating it. A shared cache must never hold an ID document.
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, max-age=0, no-store');

        return $response;
    }

    /**
     * The verification this browser session already holds for a file number, if
     * any. Session-bound so a token cannot be lifted from one applicant to
     * another, and reused so a resubmission updates rather than duplicates.
     */
    private function existingForSession(string $fileNumber): ?LegalSearchOnlineVerification
    {
        $token = session('ols_verification_token');

        if (!$token) {
            return null;
        }

        return LegalSearchOnlineVerification::where('session_token', $token)
            ->where('file_number', $fileNumber)
            ->first();
    }

    /**
     * OCR one stored image, working on a preprocessed temporary copy.
     *
     * The temporary file is deleted in `finally`, so it goes away on an OCR
     * exception exactly as it does on success. The stored original is untouched.
     */
    private function ocrOf(string $storedPath): string
    {
        $disk = Storage::disk($this->disk());
        $absolute = $disk->path($storedPath);
        $temporary = null;

        try {
            $temporary = $this->preprocessor->prepare($absolute);

            return $this->ocr->text($temporary ?: $absolute);
        } finally {
            $this->preprocessor->discard($temporary);
        }
    }

    /**
     * Save the ID image on the private disk under a generated name.
     *
     * The applicant's own filename is never used: it is attacker-controlled text
     * that would end up on the filesystem. The extension is taken from the
     * validated image type, not from the submitted name.
     */
    private function storePrivately(UploadedFile $file, string $side): string
    {
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $name = sprintf('%s_%s_%s.%s', $side, now()->format('Ymd_His'), Str::random(24), $extension);

        $directory = trim((string) config('id_verification.uploads.directory'), '/')
            . '/' . now()->format('Y/m');

        $path = $file->storeAs($directory, $name, $this->disk());

        if (!$path) {
            throw new \RuntimeException('Private storage returned no path for the identification image.');
        }

        return $path;
    }

    /**
     * Write the verification row.
     *
     * The status and score written here are always the ones this server computed;
     * no value from the request is consulted. `id_verified_at` is stamped only on
     * a genuine pass, so a review or failed result can never read as verified.
     */
    private function persist(
        ?LegalSearchOnlineVerification $existing,
        array $data,
        string $frontPath,
        array $result,
        array $bar,
        Request $request
    ): LegalSearchOnlineVerification {
        $verified = $result['status'] === LegalSearchOnlineVerification::STATUS_VERIFIED;

        // A fresh token per submission: the previous one stops being presentable
        // the moment the applicant re-verifies.
        $token = (string) Str::uuid();

        $attributes = [
            'file_number'               => $data['file_number'],
            'requester_email'           => $data['email'],
            'session_token'             => $token,
            'customer_type'             => $data['customer_type'],
            // Stored normalised, so "SCN 123456" and "scn-123456" are one value on
            // the record and a reviewer is not comparing formatting.
            'call_to_bar_number'        => $bar['normalized'] !== '' ? $bar['normalized'] : null,
            'bar_number_status'         => $bar['status'],
            'applicant_full_name'       => $data['applicant_full_name'],
            'applicant_phone'           => $data['applicant_phone'],
            'applicant_address'         => $data['applicant_address'],
            'identification_type'       => $data['identification_type'],
            'identification_type_other' => $data['identification_type_other'] ?? null,
            'id_front_path'             => $frontPath,
            'id_ocr_text'               => config('id_verification.store_raw_text')
                ? ($result['extracted_text'] ?? null)
                : null,
            'id_name_match_score'       => $result['score'] ?? 0,
            'id_verification_status'    => $result['status'],
            'id_verified_at'            => $verified ? now() : null,
            'ip_address'                => $request->ip(),
        ];

        if ($existing) {
            // Replacing a previous attempt: drop its images so rejected uploads do
            // not accumulate on disk. id_back_path is included for rows written
            // before the back image was dropped - they still have a file to clean up.
            $this->deleteQuietly([
                $existing->id_front_path !== $frontPath ? $existing->id_front_path : null,
                $existing->id_back_path,
            ]);

            $existing->fill($attributes)->save();
            $verification = $existing;
        } else {
            $verification = LegalSearchOnlineVerification::create($attributes);
        }

        session(['ols_verification_token' => $token]);

        return $verification;
    }

    /**
     * The applicant-facing response.
     *
     * Carries the status, the score, and one of the fixed messages — never the
     * OCR transcript, the matched parts, the storage paths, or exception detail.
     */
    private function outcome(string $status, int $score, string $message): JsonResponse
    {
        return response()->json([
            'success'   => $status === LegalSearchOnlineVerification::STATUS_VERIFIED,
            'status'    => $status,
            'score'     => $score,
            'message'   => $message,
            'can_pay'   => $status === LegalSearchOnlineVerification::STATUS_VERIFIED,
        ], 200);
    }

    /** @param array<int, string|null> $paths */
    private function deleteQuietly(array $paths): void
    {
        $disk = Storage::disk($this->disk());

        foreach (array_filter($paths) as $path) {
            try {
                $disk->delete($path);
            } catch (\Throwable $e) {
                Log::warning('Could not delete a superseded identification image', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function disk(): string
    {
        return (string) config('id_verification.uploads.disk', 'ols_private');
    }
}
