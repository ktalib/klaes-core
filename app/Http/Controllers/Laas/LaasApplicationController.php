<?php

namespace App\Http\Controllers\Laas;

use App\Http\Controllers\Controller;
use App\Models\Laas\LaasApplication;
use App\Models\Laas\LaasDocument;
use App\Services\Laas\LaasNotificationService;
use App\Support\Laas\SroFormSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The applicant's own view of their applications — spec steps (a) and (b), plus
 * the status page they return to for every later stage.
 *
 * The form itself is the official Application for Statutory Right of Occupancy,
 * in all four land-type variants. Its shape lives in
 * App\Support\Laas\SroFormSchema, and the answers are stored as a JSON payload
 * keyed by `oss_applications` column name, so an approved application can be
 * promoted into the live OSS table without a translation layer.
 */
class LaasApplicationController extends Controller
{
    /** Uploads the applicant may attach. */
    public const DOC_TYPES = [
        'passport' => ['label' => 'Passport photograph',      'required' => true],
        'id_card'  => ['label' => 'Means of identification',  'required' => true],
        'sketch'   => ['label' => 'Sketch / site plan',       'required' => false],
        'other'    => ['label' => 'Supporting document',      'required' => false],
    ];

    public function __construct(private LaasNotificationService $notifications)
    {
    }

    /**
     * The application form (spec a). Resumes the applicant's open draft if they
     * left one behind, and opens on whichever land type that draft was for.
     */
    public function form(Request $request)
    {
        $applicant = Auth::guard('laas')->user();

        $draft = LaasApplication::where('laas_applicant_id', $applicant->id)
            ->where('stage', LaasApplication::STAGE_DRAFT)
            ->orderByDesc('id')
            ->first();

        // ?type= wins so the land-type tabs can switch without losing the draft;
        // otherwise resume the draft's own type.
        $type = $request->query('type') ?: ($draft->land_type ?? SroFormSchema::TYPE_RESIDENTIAL);

        if (!SroFormSchema::isValidType($type)) {
            $type = SroFormSchema::TYPE_RESIDENTIAL;
        }

        return view('laas.apply', [
            'draft'     => $draft,
            'applicant' => $applicant,
            'type'      => $type,
            'sections'  => SroFormSchema::sections($type),
            'answers'   => $this->answersFor($draft, $applicant),
            'docTypes'  => self::DOC_TYPES,
        ]);
    }

    /**
     * Autosave. Accepts a partial form and never validates hard — losing a
     * half-typed application to a validation error is exactly what this exists
     * to prevent. The real validation happens in store().
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $applicant = Auth::guard('laas')->user();

        $type = $request->input('land_type');
        if (!SroFormSchema::isValidType($type)) {
            $type = SroFormSchema::TYPE_RESIDENTIAL;
        }

        $draft = LaasApplication::where('laas_applicant_id', $applicant->id)
            ->where('stage', LaasApplication::STAGE_DRAFT)
            ->orderByDesc('id')
            ->first();

        if (!$draft) {
            $draft = new LaasApplication([
                'reference_no'      => LaasApplication::nextReference(),
                'laas_applicant_id' => $applicant->id,
                'stage'             => LaasApplication::STAGE_DRAFT,
            ]);
        }

        // Switching land type keeps the answers already given: the two forms
        // share most keys, and silently discarding the rest would lose work the
        // applicant may come back to.
        $answers = array_merge(
            (array) ($draft->form_data ?? []),
            $this->collect($request, $type)
        );

        $draft->land_type = $type;
        $draft->form_data = $answers;
        $this->applySummary($draft, $type, $answers);
        $draft->save();

        return response()->json([
            'success'      => true,
            'id'           => $draft->id,
            'reference_no' => $draft->reference_no,
            'saved_at'     => now()->format('H:i:s'),
        ]);
    }

    /**
     * Submit (spec b): freeze the answers, stamp the reference, and fire the
     * "received, processing has started" text.
     */
    public function store(Request $request)
    {
        $applicant = Auth::guard('laas')->user();

        $type = $request->input('land_type');
        abort_unless(SroFormSchema::isValidType($type), 422, 'Unknown land type.');

        $request->validate(array_merge(
            SroFormSchema::rules($type),
            ['passport_photo' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png']]
        ));

        $answers = $this->collect($request, $type);
        $answers['prev_allocation_details'] = $this->collectPrevAllocations($request, $type)
            ?? ($answers['prev_allocation_details'] ?? null);

        $application = DB::connection('sqlsrv')->transaction(function () use ($applicant, $type, $answers) {
            $application = LaasApplication::where('laas_applicant_id', $applicant->id)
                ->where('stage', LaasApplication::STAGE_DRAFT)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$application) {
                $application = new LaasApplication([
                    'reference_no'      => LaasApplication::nextReference(),
                    'laas_applicant_id' => $applicant->id,
                ]);
            }

            $application->land_type = $type;
            $application->form_data = $answers;
            $this->applySummary($application, $type, $answers);

            $application->stage        = LaasApplication::STAGE_SUBMITTED;
            $application->submitted_at = now();
            $application->save();

            return $application;
        });

        if ($request->hasFile('passport_photo')) {
            $this->storeDocument($application, $request->file('passport_photo'), 'passport');
        }

        // Outside the transaction on purpose: the gateway is a network call and
        // must not hold a database lock open, nor roll a saved application back
        // if it is slow or down.
        $this->notifications->record($application, LaasApplication::STAGE_SUBMITTED, [
            'title'      => 'Application received',
            'body'       => 'Your application has been received and processing has started.',
            'actor_type' => 'applicant',
            'actor_id'   => $applicant->id,
            'actor_name' => $applicant->name,
        ]);

        return redirect()->route('laas.application.show', $application->reference_no)
            ->with('status', "Application {$application->reference_no} submitted. You will be updated by SMS at each stage.");
    }

    /** The status page: stage tracker, event timeline, documents, answers. */
    public function show(string $reference)
    {
        $application = $this->findOwned($reference);
        $type = $application->land_type ?: SroFormSchema::TYPE_RESIDENTIAL;

        return view('laas.application', [
            'application' => $application,
            'events'      => $application->events()
                                ->where('visible_to_applicant', true)
                                ->orderBy('id')
                                ->get(),
            'documents'   => $application->documents()->orderBy('id')->get(),
            'sections'    => SroFormSchema::sections($type),
            'answers'     => (array) ($application->form_data ?? []),
            'typeLabel'   => SroFormSchema::typeLabel($type),
            'docTypes'    => self::DOC_TYPES,
        ]);
    }

    public function uploadDocument(Request $request, string $reference)
    {
        $application = $this->findOwned($reference);

        $request->validate([
            'doc_type' => ['required', 'string', 'in:' . implode(',', array_keys(self::DOC_TYPES))],
            'file'     => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $this->storeDocument($application, $request->file('file'), $request->input('doc_type'));

        return back()->with('status', 'Document uploaded.');
    }

    public function downloadDocument(string $reference, int $documentId)
    {
        $application = $this->findOwned($reference);

        $document = LaasDocument::where('id', $documentId)
            ->where('laas_application_id', $application->id)
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download(
            $document->path,
            $document->original_name ?: basename($document->path)
        );
    }

    // ------------------------------------------------------------------ bits

    /**
     * The `local` disk, not `public`: identification documents and passport
     * photographs must not be reachable by guessing a URL under /storage. They
     * come back out only through downloadDocument(), which checks ownership.
     */
    private function storeDocument(LaasApplication $application, $file, string $docType): void
    {
        LaasDocument::create([
            'laas_application_id' => $application->id,
            'source'              => LaasDocument::SOURCE_APPLICANT,
            'doc_type'            => $docType,
            'original_name'       => $file->getClientOriginalName(),
            'path'                => $file->store("laas/{$application->id}", 'local'),
            'mime'                => $file->getClientMimeType(),
            'size'                => $file->getSize(),
            'uploaded_by'         => $application->laas_applicant_id,
            'uploaded_at'         => now(),
        ]);
    }

    /** Pull just this land type's fields out of the request. */
    private function collect(Request $request, string $type): array
    {
        $values = [];

        foreach (SroFormSchema::fieldKeys($type) as $key) {
            $value = $request->input($key);

            if ($value !== null && $value !== '') {
                $values[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        return $this->resolveOther($request, $type, $values);
    }

    /**
     * Turn an "Other" selection into the answer the applicant actually typed.
     *
     * Two shapes, because the two are stored differently downstream:
     *
     *  - Street and district have real *_other columns in oss_applications (the
     *    staff modal's "Specify" boxes). Both are written: the main column gets
     *    the real name so every screen and the composed address line read
     *    properly, and the companion column keeps the staff form's own shape so
     *    a promoted record round-trips into it.
     *
     *  - Nationality, occupation, LGA and state have no companion column, so the
     *    typed value simply replaces the selection. Storing the literal "Other"
     *    in `nationality` would tell an officer nothing.
     *
     * The scratch `__specify` inputs are consumed here and never stored — they
     * are not oss_applications columns and could not be promoted.
     */
    private function resolveOther(Request $request, string $type, array $values): array
    {
        foreach (SroFormSchema::otherFields($type) as $key => $companion) {
            $specify = trim((string) $request->input(SroFormSchema::specifyKey($key), ''));

            if (($values[$key] ?? null) !== SroFormSchema::OTHER) {
                continue;
            }

            // "Other" with nothing typed stays as "Other" — an honest record of
            // an unanswered question, rather than a silently blanked field.
            if ($specify === '') {
                continue;
            }

            $values[$key] = $specify;

            if ($companion) {
                $values[$companion] = $specify;
            }
        }

        return $values;
    }

    /**
     * The residential form asks for up to three prior allocations as rows.
     * Stored as JSON in the same column the staff modal writes, so both read
     * back the same way.
     */
    private function collectPrevAllocations(Request $request, string $type): ?string
    {
        if ($type !== SroFormSchema::TYPE_RESIDENTIAL) {
            return null;
        }

        $rows = [];

        for ($i = 1; $i <= SroFormSchema::PREV_ALLOCATION_ROWS; $i++) {
            $row = [
                'plot_no'  => trim((string) $request->input("prev_plot_{$i}")),
                'location' => trim((string) $request->input("prev_location_{$i}")),
                'cert_no'  => trim((string) $request->input("prev_cert_{$i}")),
            ];

            if ($row['plot_no'] !== '' || $row['location'] !== '' || $row['cert_no'] !== '') {
                $rows[] = $row;
            }
        }

        return $rows ? json_encode($rows) : null;
    }

    /**
     * Mirror the few answers the rest of the portal needs onto real columns.
     *
     * The queue, the dashboard and — critically — the SMS notifier read these
     * directly; LaasNotificationService texts `applicant_phone`, so leaving it
     * inside the JSON blob would mean no applicant is ever contacted.
     */
    private function applySummary(LaasApplication $application, string $type, array $answers): void
    {
        $application->applicant_name    = $answers['applicant_name'] ?? $application->applicant_name;
        $application->applicant_phone   = $answers['phone'] ?? $application->applicant_phone;
        $application->applicant_email   = $answers['email'] ?? $application->applicant_email;
        $application->applicant_remarks = $answers['remarks'] ?? null;
        $application->land_use          = SroFormSchema::typeLabel($type);
        $application->applicant_address = $this->addressSummary($type, $answers);
    }

    /** Flatten the applicant's main address block into one readable line. */
    private function addressSummary(string $type, array $answers): ?string
    {
        // Residential states a home address; the business forms give a
        // correspondence address instead, each under its own column prefix.
        $prefix = [
            SroFormSchema::TYPE_RESIDENTIAL  => 'res_addr_',
            SroFormSchema::TYPE_COMMERCIAL   => 'com_corr_',
            SroFormSchema::TYPE_INDUSTRIAL   => 'ind_corr_',
            SroFormSchema::TYPE_AGRICULTURAL => 'agr_corr_',
        ][$type] ?? 'res_addr_';

        $parts = [];

        foreach (array_keys(SroFormSchema::addressParts()) as $part) {
            $value = trim((string) ($answers[$prefix . $part] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return $parts ? implode(', ', $parts) : null;
    }

    /** Draft answers, pre-seeded from the account on a fresh form. */
    private function answersFor(?LaasApplication $draft, $applicant): array
    {
        $answers = (array) ($draft->form_data ?? []);

        // Only seed what the applicant already told us at registration, and only
        // when they have not typed something else.
        $answers['applicant_name'] = $answers['applicant_name'] ?? $applicant->name;
        $answers['phone']          = $answers['phone'] ?? $applicant->phone;
        $answers['email']          = $answers['email'] ?? $applicant->email;

        return $answers;
    }

    /**
     * Resolve a reference to an application the signed-in applicant owns.
     * 404 rather than 403 on someone else's reference — a wrong-owner 403 would
     * confirm that the reference exists.
     */
    private function findOwned(string $reference): LaasApplication
    {
        $applicant = Auth::guard('laas')->user();

        return LaasApplication::where('reference_no', $reference)
            ->where('laas_applicant_id', $applicant->id)
            ->firstOrFail();
    }
}
