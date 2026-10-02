<?php

namespace App\Http\Controllers\Laas;

use App\Http\Controllers\Controller;
use App\Models\Laas\LaasApplicant;
use App\Models\Laas\LaasApplication;
use App\Models\Laas\LaasApplicationEvent;
use App\Models\Laas\LaasDocument;
use App\Models\Laas\LaasStageNotification;
use App\Models\MlsFileNo;
use App\Models\Prefix;
use App\Services\Laas\LaasNotificationService;
use App\Services\Laas\LaasWorkflowService;
use App\Services\MlsSerialAllocationService;
use App\Support\Laas\SroFormSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The staff side of the LAAS workflow, under the normal staff `auth` guard.
 *
 * Only two decisions are made here — the two the spec adds that did not exist
 * before: the Director's approval (step c) and MLP's file-number assignment
 * (step d). Everything after that happens in the Land 12, Recommendation and
 * RoFO modules exactly as it always has; this console only watches.
 */
class LaasAdminController extends Controller
{
    public function __construct(
        private LaasWorkflowService $workflow,
        private LaasNotificationService $notifications,
        private MlsSerialAllocationService $serials
    ) {
    }

    /** The queue: every portal application and where it has reached. */
    public function index(Request $request)
    {
        $stage  = trim((string) $request->query('stage', ''));
        $search = trim((string) $request->query('q', ''));

        $applications = LaasApplication::query()
            ->where('stage', '!=', LaasApplication::STAGE_DRAFT)
            ->when($stage !== '', fn ($q) => $q->where('stage', $stage))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', "%{$search}%")
                          ->orWhere('applicant_name', 'like', "%{$search}%")
                          ->orWhere('applicant_phone', 'like', "%{$search}%")
                          ->orWhere('file_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // One grouped count query rather than one per stage chip. The count has
        // to be aliased and plucked by name — passing a raw expression as
        // pluck()'s value column throws "Illegal offset type".
        $counts = LaasApplication::query()
            ->where('stage', '!=', LaasApplication::STAGE_DRAFT)
            ->groupBy('stage')
            ->selectRaw('stage, count(*) as total')
            ->pluck('total', 'stage')
            ->toArray();

        return view('laas_admin.index', [
            'applications' => $applications,
            'counts'       => $counts,
            'stage'        => $stage,
            'search'       => $search,
            'unreadAlerts' => LaasStageNotification::where('is_read', false)->count(),
        ]);
    }

    /** One application, with everything staff need to act on it. */
    public function show(int $id)
    {
        $application = LaasApplication::findOrFail($id);
        $type = $application->land_type ?: SroFormSchema::TYPE_RESIDENTIAL;

        return view('laas_admin.show', [
            'application' => $application,
            'events'      => $application->events()->orderBy('id')->get(),
            'documents'   => $application->documents()->orderBy('id')->get(),
            // Rendered through the same schema the applicant filled, so the
            // office reads back exactly the questions that were asked.
            'sections'    => SroFormSchema::sections($type),
            'answers'     => (array) ($application->form_data ?? []),
            'typeLabel'   => SroFormSchema::typeLabel($type),
            'prefixes'    => $this->prefixesFor($application),
            'docTypes'    => LaasApplicationController::DOC_TYPES,
        ]);
    }

    /** Spec (c): the Director or assigned officer approves. */
    public function approve(Request $request, int $id)
    {
        $application = LaasApplication::findOrFail($id);

        if ($application->stage !== LaasApplication::STAGE_SUBMITTED) {
            return back()->with('error', 'Only a submitted application can be approved.');
        }

        $this->workflow->advance($application, LaasApplication::STAGE_DIRECTOR_APPROVED, [
            'title'      => 'Approved by the Director',
            'body'       => trim((string) $request->input('remarks')) ?: 'Your application has been approved. A file number will be assigned shortly.',
            'actor_type' => 'staff',
            'columns'    => [
                'director_approved_by' => Auth::id(),
                'director_approved_at' => now(),
            ],
        ]);

        return back()->with('status', "Application {$application->reference_no} approved." . $this->notifiedNote());
    }

    public function reject(Request $request, int $id)
    {
        $application = LaasApplication::findOrFail($id);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($application->hasReached(LaasApplication::STAGE_FILENO_ASSIGNED)) {
            return back()->with('error', 'This application already has a file number and can no longer be rejected here.');
        }

        // Not routed through advance(): `rejected` is off the main line, so the
        // forward-only rank check would refuse it.
        $application->stage            = LaasApplication::STAGE_REJECTED;
        $application->rejection_reason = $data['rejection_reason'];
        $application->save();

        // Recorded directly rather than through the workflow service, because
        // `rejected` is off the main line and advance() is forward-only — so the
        // delivery result comes from this event, not from lastNotificationDelivered().
        $event = $this->notifications->record($application, LaasApplication::STAGE_REJECTED, [
            'title'      => 'Application not approved',
            'body'       => $data['rejection_reason'],
            'actor_type' => 'staff',
        ]);

        $note = $event->sms_status === \App\Models\Laas\LaasApplicationEvent::SMS_SENT
            ? ' The applicant has been notified by SMS.'
            : ' NOTE: the SMS could not be delivered — the applicant will still see this'
              . ' when they sign in to the portal. Consider telephoning them.';

        return back()->with('status', "Application {$application->reference_no} marked as not approved." . $note);
    }

    /**
     * Spec (d): MLP generates and assigns the file number.
     *
     * The serial is drawn through MlsSerialAllocationService — the same stream
     * a land conversion draws from — so a LAAS allocation and a counter
     * allocation can never be handed the same number. That service also skips
     * serials whose file number is already taken anywhere (mls_file_no, the
     * legacy fileNumber table, or file_indexings).
     */
    public function assignFileNumber(Request $request, int $id)
    {
        $application = LaasApplication::findOrFail($id);

        $data = $request->validate([
            'prefix' => ['required', 'string', 'max:50'],
        ]);

        if ($application->stage !== LaasApplication::STAGE_DIRECTOR_APPROVED) {
            return back()->with('error', 'A file number can only be assigned after the Director has approved the application.');
        }

        if ($application->file_number) {
            return back()->with('error', "This application already holds file number {$application->file_number}.");
        }

        $year = (int) date('Y');

        try {
            $allocation = DB::connection('sqlsrv')->transaction(function () use ($application, $data, $year) {
                $allocation = $this->serials->allocateNextFreeSerial($data['prefix'], $year);

                // plot_no / location / lga / district describe the PARCEL, and
                // the Statutory Right of Occupancy form never asks for one —
                // the Ministry allocates the plot. They stay null here and are
                // filled in downstream, once a parcel is actually chosen.
                MlsFileNo::create([
                    'land_use'           => $data['prefix'],
                    'year'               => $year,
                    'serial_number'      => $allocation['serial'],
                    'full_file_number'   => $allocation['file_number'],
                    'file_name'          => $application->applicant_name,
                    'customer_type'      => $this->customerTypeFor($application),
                    'file_option'        => 'normal',
                    'created_by'         => Auth::user()->name ?? Auth::user()->email ?? 'LAAS Portal',
                    'commissioning_date' => now(),

                    // Provenance. `source` records that the file originated on
                    // the public portal; system_sub_type stays within the two
                    // values the MLS/OSS list filters already understand (see
                    // App\Support\OssOpCommissionFilter) rather than adding a
                    // third bucket every existing filter would have to learn.
                    'source'             => 'LAAS Portal',
                    'system_sub_type'    => 'OSS',
                ]);

                return $allocation;
            });
        } catch (\Throwable $e) {
            Log::error('LAAS: file number allocation failed', [
                'reference_no' => $application->reference_no,
                'prefix'       => $data['prefix'],
                'error'        => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'prefix' => 'Could not allocate a file number: ' . $e->getMessage(),
            ]);
        }

        if (!empty($allocation['skipped'])) {
            Log::info('LAAS: serials skipped during allocation', [
                'reference_no' => $application->reference_no,
                'skipped'      => $allocation['skipped'],
            ]);
        }

        $this->workflow->advance($application, LaasApplication::STAGE_FILENO_ASSIGNED, [
            'title'      => 'File Number assigned',
            'body'       => "Your application has been assigned file number {$allocation['file_number']}.",
            'actor_type' => 'staff',
            'columns'    => [
                'file_number'        => $allocation['file_number'],
                'fileno_assigned_by' => Auth::id(),
                'fileno_assigned_at' => now(),
            ],
        ]);

        return back()->with('status', "File number {$allocation['file_number']} assigned." . $this->notifiedNote());
    }

    /**
     * Residential applications are filed by a person; the commercial,
     * industrial and agricultural forms are filed by an applicant *or company*
     * and are treated as corporate for the file record.
     */
    private function customerTypeFor(LaasApplication $application): string
    {
        return $application->land_type === SroFormSchema::TYPE_RESIDENTIAL
            ? 'Individual'
            : 'Corporate';
    }

    /**
     * Whether the applicant was actually reached, in plain words.
     *
     * The SMS gateway refuses messages on content and can simply not deliver,
     * so "approved" and "the applicant knows" are two different facts. Saying
     * they were notified when the text failed sends the office away believing a
     * job is finished that is not — the update is still on the applicant's
     * portal timeline either way, which is what the fallback wording says.
     */
    private function notifiedNote(): string
    {
        if ($this->workflow->lastNotificationDelivered()) {
            return ' The applicant has been notified by SMS.';
        }

        return ' NOTE: the SMS could not be delivered — the applicant will still see this'
             . ' when they sign in to the portal. Consider telephoning them.';
    }

    // ---------------------------------------------------------- applicants

    /** Portal accounts, with what each one has in flight. */
    public function applicants(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $applicants = LaasApplicant::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Two grouped queries rather than two per row.
        $ids = $applicants->pluck('id');

        // sqlsrv returns COUNT(*) as a string; cast so callers can compare
        // numbers rather than discovering "1" !== 1 the hard way.
        $totals = array_map('intval', LaasApplication::whereIn('laas_applicant_id', $ids)
            ->groupBy('laas_applicant_id')
            ->selectRaw('laas_applicant_id, count(*) as total')
            ->pluck('total', 'laas_applicant_id')
            ->toArray());

        $inRegistry = array_map('intval', LaasApplication::whereIn('laas_applicant_id', $ids)
            ->whereIn('stage', LaasApplication::registryStages())
            ->groupBy('laas_applicant_id')
            ->selectRaw('laas_applicant_id, count(*) as total')
            ->pluck('total', 'laas_applicant_id')
            ->toArray());

        return view('laas_admin.applicants', [
            'applicants'   => $applicants,
            'totals'       => $totals,
            'inRegistry'   => $inRegistry,
            'search'       => $search,
            'unreadAlerts' => LaasStageNotification::where('is_read', false)->count(),
        ]);
    }

    /** Suspend an account without destroying anything. The reversible option. */
    public function toggleApplicant(int $id)
    {
        $applicant = LaasApplicant::findOrFail($id);
        $suspending = $applicant->isActive();

        $applicant->forceFill(['status' => $suspending ? 'suspended' : 'active'])->save();

        return back()->with('status', $suspending
            ? "{$applicant->name} can no longer sign in. Their applications are untouched."
            : "{$applicant->name} can sign in again.");
    }

    /**
     * Delete an applicant and everything belonging to them.
     *
     * IRREVERSIBLE, and deliberately refused once any of their applications has
     * reached `fileno_assigned`. At that point a file number exists in
     * mls_file_no and a Land 12, recommendation or RoFO may be keyed to it —
     * removing the applicant would leave those registry rows pointing at nobody
     * and destroy the audit trail for a statutory process. Suspend instead;
     * that is what the toggle above is for.
     *
     * The typed-confirmation phone number is not ceremony: this is the one
     * action in the console that cannot be undone, and a mis-click on a list of
     * similar names would be unrecoverable.
     */
    public function destroyApplicant(Request $request, int $id)
    {
        $applicant = LaasApplicant::findOrFail($id);

        $blocking = LaasApplication::where('laas_applicant_id', $applicant->id)
            ->whereIn('stage', LaasApplication::registryStages())
            ->pluck('file_number', 'reference_no')
            ->toArray();

        if ($blocking) {
            $refs = implode(', ', array_map(
                fn ($ref, $file) => $file ? "{$ref} ({$file})" : $ref,
                array_keys($blocking),
                $blocking
            ));

            return back()->with('error',
                "{$applicant->name} cannot be deleted: " . count($blocking)
                . ' application(s) have already been issued a file number and are part of the land'
                . " registry — {$refs}. Suspend the account instead.");
        }

        $typed = trim((string) $request->input('confirm_phone'));

        if ($typed !== (string) $applicant->phone) {
            return back()->with('error',
                'To delete this applicant, type their phone number exactly as shown.');
        }

        $name  = $applicant->name;
        $phone = $applicant->phone;

        $counts = DB::connection('sqlsrv')->transaction(function () use ($applicant) {
            $applicationIds = LaasApplication::where('laas_applicant_id', $applicant->id)
                ->pluck('id');

            // Uploaded files first: a deleted row would leave the file on disk
            // with nothing pointing at it, and identification documents are
            // exactly what should not be left lying around.
            $documents = LaasDocument::whereIn('laas_application_id', $applicationIds)->get();
            $filesGone = 0;

            foreach ($documents as $document) {
                if ($document->path && Storage::disk('local')->exists($document->path)) {
                    Storage::disk('local')->delete($document->path);
                    $filesGone++;
                }
            }

            $counts = [
                'documents'     => LaasDocument::whereIn('laas_application_id', $applicationIds)->delete(),
                'files'         => $filesGone,
                'events'        => LaasApplicationEvent::whereIn('laas_application_id', $applicationIds)->delete(),
                'alerts'        => LaasStageNotification::whereIn('laas_application_id', $applicationIds)->delete(),
                'applications'  => LaasApplication::whereIn('id', $applicationIds)->delete(),
            ];

            $applicant->delete();

            return $counts;
        });

        Log::warning('LAAS: applicant deleted', [
            'applicant_id' => $id,
            'name'         => $name,
            'phone'        => LaasApplicant::maskPhone($phone),
            'deleted_by'   => Auth::id(),
            'removed'      => $counts,
        ]);

        return redirect()->route('laas-admin.applicants')->with('status',
            "{$name} deleted, with {$counts['applications']} application(s), "
            . "{$counts['events']} timeline entrie(s) and {$counts['documents']} document(s).");
    }

    /** Spec (h): the Land Office / OSS Unit desk inbox. */
    public function alerts(Request $request)
    {
        $alerts = LaasStageNotification::query()
            ->when(!$request->boolean('all'), fn ($q) => $q->where('is_read', false))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $applications = LaasApplication::whereIn('id', $alerts->pluck('laas_application_id'))
            ->get()
            ->keyBy('id');

        return view('laas_admin.alerts', [
            'alerts'       => $alerts,
            'applications' => $applications,
            'showingAll'   => $request->boolean('all'),
            'unreadAlerts' => LaasStageNotification::where('is_read', false)->count(),
        ]);
    }

    public function markAlertRead(int $id)
    {
        LaasStageNotification::where('id', $id)->update([
            'is_read' => true,
            'read_at' => now(),
            'read_by' => Auth::id(),
        ]);

        return back()->with('status', 'Alert cleared.');
    }

    /**
     * The file-number prefixes MLP may choose from for this application.
     *
     * Narrowed to the applicant's stated land use where that maps onto a known
     * land use; otherwise the full list, since the applicant's free-text answer
     * should never block the officer from allocating.
     */
    private function prefixesFor(LaasApplication $application)
    {
        $landUseId = \App\Models\LandUse::where('landuse', $application->land_use)->value('id');

        return Prefix::query()
            ->when($landUseId, fn ($q) => $q->where('land_use_id', $landUseId))
            ->orderBy('prefix')
            ->get(['id', 'prefix', 'land_use_id']);
    }
}
