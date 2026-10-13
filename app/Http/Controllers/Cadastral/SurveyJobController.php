<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralIndexCard;
use App\Models\Cadastral\CadastralSurveyJob;
use App\Models\Cadastral\CadastralSurveyor;
use App\Http\Controllers\Cadastral\Concerns\LocksFileValues;
use App\Services\AuditService;
use App\Services\Cadastral\CadastralAddress;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\Cadastral\SurveyJobNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Survey job numbers and Instructions to Surveyor (concept note 4.3c).
 *
 * THE NUMBER FORMAT IS UNCONFIRMED. The note calls for SURCON compliance and
 * nobody here knows the pattern SURCON mandates; the screens say so, loudly,
 * while config/cadastral_module.php still carries the placeholder. Issuing a
 * hundred numbers in the wrong format is a worse problem than waiting for the
 * Surveyor-General's office to answer.
 *
 * Number allocation happens inside a transaction so two officers clicking
 * Generate at the same moment serialise rather than collide; the unique index on
 * job_number is the backstop.
 *
 * THE FILE (rebuild D-UI). A job is registered against an INDEXED file, picked
 * with the shared file picker: a survey job can come before the file reaches
 * Cadastral intake (the card it is later written onto is commissioned after
 * the job, often), so the scope is any live file_indexings row, not a receipt.
 * The number is taken from that row, and the title and location from the
 * file's registered receipt where it has one, else from the row
 * (LocksFileValues); what both leave blank is the officer's.
 *
 * INDEX-CARD SYNC (Phase 5). Whenever a job is registered, re-linked or issued
 * for a file that has an index card, the job is linked to the card and its
 * number is written onto the card (syncCard) -- inside the same transaction, so
 * the card never shows a number for a job that was not saved. A card already
 * carrying a live job's number keeps it; the officer changes it on the card.
 */
class SurveyJobController extends Controller
{
    /** A job in one of these states is never written onto a card. */
    private const DEAD_STATUSES = ['Cancelled', 'Rejected'];

    use LocksFileValues;

    /** Form fields a job takes from its file; supplied ones are locked. The builder's prop_plot is the job's plot. */
    private const FILE_FIELDS = ['file_title', 'prop_house', 'prop_plot', 'prop_street', 'prop_district', 'prop_lga', 'prop_state'];

    public function __construct(
        private SurveyJobNumberGenerator $numbers,
        private AuditService $audit,
        private CadastralRegistryLookup $lookup,
    ) {}

    public function index(Request $r)
    {
        $q = CadastralSurveyJob::query()->with(['surveyor', 'indexCard']);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('job_number', 'like', "%$term%")
                  ->orWhere('file_number', 'like', "%$term%")
                  ->orWhere('its_number', 'like', "%$term%")
                  ->orWhere('surveyor_name', 'like', "%$term%")
                  ->orWhere('firm_name', 'like', "%$term%");
            });
        }
        if ($s = $r->query('status')) $q->where('status', $s);

        $jobs = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $stats = [
            'total'    => CadastralSurveyJob::count(),
            'issued'   => CadastralSurveyJob::where('status', 'Issued')->count(),
            'in_field' => CadastralSurveyJob::whereIn('status', ['In Field', 'Submitted'])->count(),
            'accepted' => CadastralSurveyJob::where('status', 'Accepted')->count(),
        ];

        $surveyors = CadastralSurveyor::where('is_active', true)
            ->orderBy('full_name')
            ->get();

        // After a failed register, put the picked file back in the picker.
        $picked = ($id = (int) old('file_indexing_id'))
            ? $this->lookup->resolveFile(['file_indexing_id' => $id, 'scope' => 'indexed'])
            : null;

        return view('cadastral_module.information.survey_jobs', [
            'picked'            => $picked,
            'jobs'              => $jobs,
            'stats'             => $stats,
            'surveyors'         => $surveyors,
            'formatUnconfirmed' => $this->numbers->formatIsPlaceholder(),
            'nextJobNumber'     => $this->numbers->previewJobNumber(),
        ]);
    }

    /**
     * Register a job and allocate its number in one transaction.
     *
     * A job is its scope and the survey's location (user, 2026-10-12); the
     * surveyor and the file are both attached later, at Assign to Surveyor
     * (I to S). The number is allocated now rather than at issue: a job with no
     * number cannot be referred to on paper.
     */
    public function store(Request $r)
    {
        $data = $this->jobDetails($r);

        $job = DB::connection('sqlsrv')->transaction(function () use ($data) {
            $data['job_number'] = $this->numbers->nextJobNumber();
            $data['status'] = 'Draft';

            return CadastralSurveyJob::create($data);
        });

        $this->log('CADASTRAL_SURVEY_JOB_CREATED', $job);

        return back()->with('success', "Survey job {$job->job_number} registered. "
            . 'Attach it to a file and a surveyor at Assign to Surveyor (I to S).');
    }

    /** A job before I to S: its scope and the survey's location (the address builder). */
    private function jobDetails(Request $r): array
    {
        $data = CadastralAddress::normalise(
            $r->validate(['job_scope' => 'nullable|string|max:8000'] + $this->addressRules($r, 'prop_'), $this->addressMessages('prop_')),
            'prop_'
        );
        $data['job_scope'] = trim((string) ($data['job_scope'] ?? '')) ?: null;

        return $data;
    }

    public function edit(CadastralSurveyJob $surveyJob)
    {
        // The file is fixed; the picker shows it locked, as update() re-reads it. A job not yet
        // attached to a file has none.
        $indexingId = $surveyJob->file_number ? $this->indexingIdFor($surveyJob->file_number) : null;

        return view('cadastral_module.information.survey_job_register', [
            'picked'            => $indexingId ? $this->lookup->resolveFile(['file_indexing_id' => $indexingId, 'scope' => 'indexed']) : null,
            'job'               => $surveyJob->load(['surveyor', 'indexCard']),
            'surveyors'         => CadastralSurveyor::where('is_active', true)->orderBy('full_name')->get(),
            'formatUnconfirmed' => $this->numbers->formatIsPlaceholder(),
        ]);
    }

    public function update(Request $r, CadastralSurveyJob $surveyJob)
    {
        if (in_array($surveyJob->status, ['Accepted', 'Cancelled'], true)) {
            return back()->with('error', "{$surveyJob->job_number} is {$surveyJob->status} and can no longer be edited.");
        }

        // Not attached yet: its scope and survey location; the surveyor and file come at I to S.
        if (! $surveyJob->file_number) {
            $surveyJob->update($this->jobDetails($r));

            return back()->with('success', "{$surveyJob->job_number} updated.");
        }

        // The file number is the job's own; what its file supplies is re-read.
        $this->lockFromFile($r, self::FILE_FIELDS, $this->latestRegisteredReceipt($surveyJob->file_number),
            $this->indexingIdFor($surveyJob->file_number), ['file_number' => $surveyJob->file_number]);

        $data = $this->validated($r, $surveyJob);

        // A job commissioned with a Cadastral Department field jacket keeps its
        // surveyor; changing it is a reassignment on the jacket, with a reason.
        if ($surveyJob->field_jacket_no && (int) ($data['cadastral_surveyor_id'] ?? 0) !== (int) $surveyJob->cadastral_surveyor_id) {
            return back()->withInput()->with('error', "{$surveyJob->job_number} belongs to field jacket {$surveyJob->field_jacket_no}; reassign the surveyor on the jacket.");
        }

        $this->snapshotSurveyor($data);

        $synced = DB::connection('sqlsrv')->transaction(function () use ($surveyJob, $data) {
            $surveyJob->update($data);

            return $this->syncCard($surveyJob);
        });

        return back()->with('success', "{$surveyJob->job_number} updated.{$synced}");
    }

    /**
     * Issue the Instruction to Surveyor.
     *
     * Refused for a surveyor whose licence is not current: an instruction to
     * someone who may not practise is the one output of this screen that could
     * put the Ministry in the wrong.
     */
    public function generateIts(Request $r, CadastralSurveyJob $surveyJob)
    {
        $data = $r->validate([
            'its_instructions' => 'required|string|max:8000',
            'its_recipient'    => 'nullable|string|max:255',
            'its_officer_post' => ['nullable', Rule::in(array_keys(config('cadastral_module.posts')))],
        ], [
            'its_instructions.required' => 'The instruction text is required.',
        ]);

        if (! $surveyJob->file_number) {
            return back()->with('error', "{$surveyJob->job_number} is not attached to a file yet. Attach it at Assign to Surveyor (I to S), where the I to S is issued.");
        }

        if ($surveyJob->hasInstruction()) {
            return back()->with('error',
                "{$surveyJob->job_number} already carries instruction {$surveyJob->its_number}.");
        }

        // Issued from the file's workflow page, where it ticks the I to S task.
        if ($surveyJob->field_jacket_no) {
            return back()->with('error', "{$surveyJob->job_number} belongs to field jacket {$surveyJob->field_jacket_no}; issue its I to S from the file's Cadastral Department page.");
        }

        $surveyor = $surveyJob->surveyor;

        if (! $surveyor) {
            return back()->with('error', 'Assign a surveyor before issuing the instruction.');
        }

        if (! $surveyor->canReceiveInstruction()) {
            return back()->with('error',
                "{$surveyor->full_name}'s licence is {$surveyor->licence_status}. An instruction cannot be issued to a surveyor who may not practise.");
        }

        [$job, $synced] = DB::connection('sqlsrv')->transaction(function () use ($surveyJob, $data, $surveyor) {
            $surveyJob->update($data + [
                'its_number'           => $this->numbers->nextItsNumber(),
                'its_issued_at'        => now()->toDateString(),
                'its_recipient'        => $data['its_recipient'] ?? $surveyor->display_name,
                'its_issued_by'        => auth()->user()->name ?? null,
                'its_issued_by_user_id' => auth()->id(),
                'status'               => 'Issued',
                'issued_at'            => now(),
            ]);

            // The job number belongs on the index card, which is where the
            // registry looks for it (concept note 4.3c).
            return [$surveyJob, $this->syncCard($surveyJob)];
        });

        $this->log('CADASTRAL_ITS_ISSUED', $job);

        return back()->with('success', "Instruction {$job->its_number} issued to {$surveyor->display_name}.{$synced}");
    }

    public function printIts(CadastralSurveyJob $surveyJob)
    {
        if (! $surveyJob->hasInstruction()) {
            return back()->with('error', "{$surveyJob->job_number} has no instruction to print yet.");
        }

        return view('cadastral_module.information.its_print', [
            'job'      => $surveyJob->load('surveyor'),
            'postName' => config('cadastral_module.posts')[$surveyJob->its_officer_post] ?? null,
        ]);
    }

    public function markSubmitted(CadastralSurveyJob $surveyJob)
    {
        if (! $surveyJob->hasInstruction()) {
            return back()->with('error', "{$surveyJob->job_number} has not been issued yet.");
        }

        $surveyJob->update(['status' => 'Submitted', 'submitted_at' => now()]);

        return back()->with('success', "{$surveyJob->job_number} marked as submitted.");
    }

    public function markAccepted(CadastralSurveyJob $surveyJob)
    {
        if ($surveyJob->status !== 'Submitted') {
            return back()->with('error', "{$surveyJob->job_number} must be submitted before it can be accepted.");
        }

        $surveyJob->update(['status' => 'Accepted', 'accepted_at' => now()]);

        $this->log('CADASTRAL_SURVEY_JOB_ACCEPTED', $surveyJob);

        return back()->with('success', "{$surveyJob->job_number} accepted.");
    }

    /**
     * The job carries its own copy of the surveyor's name and firm: the
     * directory row can later be renamed or struck off, and the instruction that
     * was issued has to keep reading the way it was issued.
     */
    private function snapshotSurveyor(array &$data): void
    {
        if (empty($data['cadastral_surveyor_id'])) {
            return;
        }

        $surveyor = CadastralSurveyor::find($data['cadastral_surveyor_id']);

        if ($surveyor) {
            $data['surveyor_name'] = $surveyor->full_name;
            $data['firm_name']     = $surveyor->firm_name;
        }
    }

    /**
     * Link the job to its file's index card and write the job number onto it.
     *
     * The card is the one the job names, else the live card for the same file
     * number. The number is written when the card has none, or when the number
     * it carries belongs to a job that is cancelled, rejected or gone; a card
     * already pointing at another live job is left alone -- two live jobs on one
     * file is the officer's call, not this method's. A cancelled or rejected
     * job is never written.
     *
     * Runs inside the caller's transaction. Returns a sentence for the flash.
     */
    private function syncCard(CadastralSurveyJob $job): string
    {
        if (! $job->file_number) {
            return '';   // not attached to a file yet
        }

        $card = $job->cadastral_index_card_id
            ? CadastralIndexCard::find($job->cadastral_index_card_id)
            : CadastralIndexCard::where('file_number', $job->file_number)->first();

        if (! $card) {
            return '';
        }

        if ((int) $job->cadastral_index_card_id !== (int) $card->id) {
            $job->update(['cadastral_index_card_id' => $card->id]);
        }

        if (in_array($job->status, self::DEAD_STATUSES, true) || $card->survey_job_number === $job->job_number) {
            return '';
        }

        $current = $card->survey_job_number
            ? CadastralSurveyJob::where('job_number', $card->survey_job_number)->first()
            : null;

        if ($current && ! in_array($current->status, self::DEAD_STATUSES, true)) {
            return " Index card {$card->card_ref} keeps {$card->survey_job_number}; change it on the card if this job replaces it.";
        }

        $before = $card->survey_job_number;
        $card->update(['survey_job_number' => $job->job_number]);

        // Not best-effort: the card's number changed, and the trail says why.
        $this->audit->logAction(
            'CADASTRAL_INDEX_CARD_JOB_SET',
            'cadastral_index_card',
            $card->id,
            ['survey_job_number' => $before],
            ['survey_job_number' => $job->job_number, 'job_id' => $job->id],
            "{$card->card_ref} ({$card->file_number})"
        );

        return " Job number written onto index card {$card->card_ref}.";
    }

    private function log(string $action, CadastralSurveyJob $job): void
    {
        try {
            $this->audit->logAction(
                $action,
                'cadastral_survey_job',
                $job->id,
                null,
                ['job_number' => $job->job_number, 'its_number' => $job->its_number, 'status' => $job->status],
                "{$job->job_number} (" . ($job->file_number ?: 'no file yet') . ")"
            );
        } catch (\Throwable $e) {
            Log::warning('Cadastral survey job audit failed: ' . $e->getMessage(), ['job_id' => $job->id]);
        }
    }

    /** The picked file, re-read: live, indexed and not decommissioned. */
    private function requireIndexedFile($id): array
    {
        $file = $id ? $this->lookup->sourceFile(null, (int) $id) : null;

        if (! $file) {
            throw ValidationException::withMessages([
                'file_indexing_id' => $id
                    ? 'That file is no longer in the file index. Select it again with the file-number selector.'
                    : 'Select the file with the file-number selector first.',
            ]);
        }

        if ($file['decommissioned']) {
            throw ValidationException::withMessages([
                'file_indexing_id' => "{$file['file_number']} has been decommissioned"
                    . ($file['successor'] ? " and replaced by {$file['successor']}" : '') . '. Register the job on the current file.',
            ]);
        }

        return $file;
    }

    /**
     * The file_indexings row behind a job's number: its receipt's pointer when
     * it has one, else the one live row carrying the number. Several rows
     * carrying it is not guessed between; the receipt's values still apply.
     */
    private function indexingIdFor(?string $fileNumber): ?int
    {
        if ($receipt = $this->latestRegisteredReceipt($fileNumber)) {
            if ($receipt->file_indexing_id) return (int) $receipt->file_indexing_id;
        }

        $live = $this->lookup->matchIndexedFiles($fileNumber)->reject(fn ($m) => (bool) $m->is_decommissioned);

        return $live->count() === 1 ? (int) $live->first()->id : null;
    }

    private function validated(Request $r, ?CadastralSurveyJob $existing = null): array
    {
        $rules = [
            'file_number'           => 'required|string|max:100',
            'file_title'            => 'nullable|string|max:500',
            'cadastral_surveyor_id' => 'nullable|integer',
            'cadastral_index_card_id' => 'nullable|integer',
            'cadastral_report_id'   => 'nullable|integer',
            'job_scope'             => 'nullable|string|max:8000',
            'status'                => ['nullable', Rule::in(CadastralSurveyJob::STATUSES)],
        ] + $this->addressRules($r, 'prop_');

        // No plot column of its own; the builder's prop_plot is it (the ITS prints it).
        $data = CadastralAddress::normalise(
            $r->validate($rules, $this->addressMessages('prop_')),
            'prop_'
        );

        // A card named by id must be the card of this file -- the one mistake
        // that would write a job number onto someone else's card.
        if (! empty($data['cadastral_index_card_id'])) {
            $card = CadastralIndexCard::find($data['cadastral_index_card_id']);

            if (! $card || $card->file_number !== $data['file_number']) {
                throw ValidationException::withMessages([
                    'cadastral_index_card_id' => $card
                        ? "Index card {$card->card_ref} is for {$card->file_number}, not {$data['file_number']}."
                        : 'That index card does not exist.',
                ]);
            }
        }

        return $data;
    }
}
