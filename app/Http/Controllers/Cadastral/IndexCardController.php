<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralChart;
use App\Models\Cadastral\CadastralFileReceipt;
use App\Models\Cadastral\CadastralIndexCard;
use App\Models\Cadastral\CadastralSurveyJob;
use App\Http\Controllers\Cadastral\Concerns\LocksFileValues;
use App\Services\Cadastral\CadastralAddress;
use App\Services\Cadastral\CadastralDocuments;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\Cadastral\IndexCardMovements;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The index card register (concept note 4.3b, rebuild plan Phase 5).
 *
 * This is the index card as DATA. The existing CadastralIndexCardController is a
 * browser over scanned card IMAGES on disk, and stays exactly as it is; the
 * image_folder column joins a scan to its data row where both exist.
 *
 * COMMISSIONED FROM INTAKE. The File No is picked from REGISTERED intake
 * receipts, not from the raw source index. A receipt is the module's record that
 * the physical file has arrived in Cadastral and cleared the duplicate hold; its
 * owner and location were already taken from the source file at intake (Phase
 * 2), with the blanks the source left filled by the clerk. Picking from the
 * source index again would let a card be raised for a file Cadastral has never
 * received, and would re-ask questions intake already answered.
 *
 * The file is picked with the shared file picker (scope: registered receipt).
 * Owner, plot and location are copied from the receipt ON THE SERVER; nothing
 * the browser posts about them is used — except for a value the receipt left
 * blank, which the officer may fill in here (LocksFileValues).
 *
 * MOVEMENT. The file's movement record is read live from file_tracker for the
 * same file number. The card's own stage inside Cadastral (Commissioned, With
 * Charting …) is recorded in audit_logs by IndexCardMovements — see that class
 * for why neither is a new table.
 */
class IndexCardController extends Controller
{
    use LocksFileValues;

    /** Form fields a card takes from its receipt; supplied ones are locked. */
    private const FILE_FIELDS = ['file_title', 'plot_no', 'prop_house', 'prop_street', 'prop_district', 'prop_lga', 'prop_state'];

    /** Receipts in these states can no longer have a card raised from them. */
    private const DEAD_RECEIPTS = ['Rejected', 'Returned'];

    /** Jobs in these states are not offered as the card's survey job. */
    private const DEAD_JOBS = ['Cancelled', 'Rejected'];

    public function __construct(
        private CadastralRegistryLookup $lookup,
        private IndexCardMovements $movements,
    ) {}

    public function index(Request $r)
    {
        $q = CadastralIndexCard::query()->with('chart');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('file_number', 'like', "%$term%")
                  ->orWhere('file_title', 'like', "%$term%")
                  ->orWhere('card_ref', 'like', "%$term%")
                  ->orWhere('plot_no', 'like', "%$term%")
                  ->orWhere('survey_job_number', 'like', "%$term%");
            });
        }
        if ($s = $r->query('file_status')) $q->where('file_status', $s);

        $cards = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $stats = [
            'total'    => CadastralIndexCard::count(),
            'open'     => CadastralIndexCard::where('file_status', 'open')->count(),
            'revoked'  => CadastralIndexCard::where('file_status', 'revoked')->count(),
            'with_job' => CadastralIndexCard::whereNotNull('survey_job_number')->count(),
        ];

        // After a failed commission, put the picked file back in the picker.
        $picked = ($id = (int) old('cadastral_file_receipt_id'))
            ? $this->lookup->resolveFile(['receipt' => $id, 'scope' => 'receipt', 'purpose' => 'commission'])
            : null;

        return view('cadastral_module.information.index_cards', [
            'cards'   => $cards,
            'stats'   => $stats,
            'latest'  => $this->movements->latestFor($cards->pluck('id')->all()),
            'offices' => $this->movements->trackerOffices($cards->pluck('file_number')->all()),
            'stages'  => IndexCardMovements::STAGES,
            'picked'  => $picked,
        ]);
    }

    /**
     * Select2 source for the Commission form's File No: registered receipts
     * whose file number (or receipt ref) starts with the term. GET and
     * verb-free, so it infers `view`.
     */
    public function intakeFiles(Request $r): JsonResponse
    {
        $term = trim((string) $r->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $search = function (bool $contains) use ($term) {
            $like = ($contains ? '%' : '') . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';

            return CadastralFileReceipt::query()
                ->whereNotNull('registered_at')
                ->whereNotIn('status', self::DEAD_RECEIPTS)
                ->where(fn ($w) => $w->where('file_number', 'like', $like)->orWhere('receipt_ref', 'like', $like))
                ->orderBy('file_number')
                ->limit(25)
                ->get();
        };

        $rows = $search(false);
        if ($rows->isEmpty() && mb_strlen($term) >= 3) {
            $rows = $search(true);
        }

        return response()->json(['results' => $this->formatReceipts($rows), 'pagination' => ['more' => false]]);
    }

    /**
     * One card, with the file's movement record read live from the tracker and
     * the card's own stage history beside it.
     */
    public function show(CadastralIndexCard $card)
    {
        $movements = $this->lookup->movements($card->file_number);

        $card->load(['chart', 'statusEvents', 'surveyJobs']);

        return view('cadastral_module.information.index_card_show', [
            'card'      => $card,
            'tracker'   => $movements['tracker'],
            'movements' => $movements['movements'],
            'stages'    => $this->movements->history($card),
            'stageList' => IndexCardMovements::STAGES,
            'jobs'      => $this->jobsFor($card->file_number),
            'receipt'   => $card->sourceReceipt(),
            'documents' => app(CadastralDocuments::class)
                ->for(CadastralDocuments::OWNER_STATUS_EVENT, $card->statusEvents->pluck('id')->all())
                ->groupBy('owner_id'),
        ]);
    }

    /**
     * Commission a card.
     *
     * One live card per file. The file's file_indexings row is locked first —
     * the same lock intake takes — so two officers commissioning the same file
     * at once queue behind each other and the second sees the first's card; the
     * filtered unique index on file_number is the backstop.
     */
    public function store(Request $r)
    {
        $receipt = $this->requireRegisteredReceipt($r->input('cadastral_file_receipt_id'), 'be given an index card');

        // What the receipt supplies wins over the form; its blanks are the officer's.
        $this->lockFromFile($r, self::FILE_FIELDS, $receipt);

        $data = Validator::make($r->all(), [
            'cadastral_file_receipt_id' => 'required|integer',
            'cadastral_survey_job_id'   => 'nullable|integer',
            'initial_stage'             => ['required', Rule::in(array_keys(IndexCardMovements::STAGES))],
            'movement_note'             => 'nullable|string|max:1000',
            'file_title'                => 'nullable|string|max:500',
            'plot_no'                   => 'nullable|string|max:50',
            'block_no'                  => 'nullable|string|max:50',
            'layout_name'               => 'nullable|string|max:255',
            'image_folder'              => 'nullable|string|max:255',
        ] + $this->addressRules($r, 'prop_', false), [
            'cadastral_file_receipt_id.required' => 'Select the file with the file-number selector first.',
        ] + $this->addressMessages('prop_'))->validate();

        // plot_no is the card's plot field; prop_plot follows it.
        $data = CadastralAddress::normalise($data, 'prop_', 'plot_no');

        $job = $this->requireJob($data['cadastral_survey_job_id'] ?? null, $receipt->file_number);

        try {
            $card = DB::connection('sqlsrv')->transaction(function () use ($data, $receipt, $job) {
                // Serialise on the file: the source row when intake recorded
                // one, and the receipt itself either way.
                if ($receipt->file_indexing_id) {
                    $this->lookup->sourceFile(null, $receipt->file_indexing_id, true);
                }
                CadastralFileReceipt::query()->whereKey($receipt->id)->lockForUpdate()->first();

                if ($existing = $this->liveCardFor($receipt)) {
                    throw ValidationException::withMessages([
                        'cadastral_file_receipt_id' => "{$receipt->file_number} already has index card {$existing->card_ref}. "
                            . 'Update that card instead of commissioning a second.',
                    ]);
                }

                $address = [];
                foreach (\App\Support\AddressBuilder::columns('prop_') as $col) {
                    $address[$col] = $data[$col] ?? null;
                }

                $card = new CadastralIndexCard(array_merge(
                    $address,
                    [
                        'card_ref'          => CadastralIndexCard::nextRef('card_ref', 'IDX', 4),
                        'file_number'       => $receipt->file_number,
                        'file_title'        => $data['file_title'] ?? null,
                        'plot_no'           => $data['plot_no'] ?? null,
                        'block_no'          => $data['block_no'] ?? null,
                        'layout_name'       => $data['layout_name'] ?? null,
                        'image_folder'      => $data['image_folder'] ?? null,
                        'survey_job_number' => $job?->job_number,
                        'file_status'       => 'open',
                        'commissioned_at'   => now(),
                        'commissioned_by'   => auth()->id(),
                    ]
                ));

                // forceFill: columns a pending migration adds are not in the
                // column listing a long-running worker cached.
                if (CadastralIndexCard::linkInstalled()) {
                    $card->forceFill([
                        'cadastral_file_receipt_id' => $receipt->id,
                        'file_indexing_id'          => $receipt->file_indexing_id,
                    ]);
                }

                $card->save();

                if ($job && ! $job->cadastral_index_card_id) {
                    $job->update(['cadastral_index_card_id' => $card->id]);
                }

                $this->movements->record($card, $data['initial_stage'], $data['movement_note'] ?? null);

                return $card;
            });
        } catch (QueryException $e) {
            // The filtered unique index fired: a card was raised between our
            // check and our insert by a path that did not take the lock.
            if (str_contains($e->getMessage(), 'cad_index_cards_fileno_live_uq')) {
                return back()->withInput()->with('error', "{$receipt->file_number} already has a live index card.");
            }
            throw $e;
        }

        return redirect()
            ->route('cadastral-module.index-cards.show', $card)
            ->with('success', "Index card {$card->card_ref} commissioned for {$card->file_number} ("
                . IndexCardMovements::label($data['initial_stage']) . ').');
    }

    /**
     * Amend the card. The file number is fixed at commissioning — a card is
     * linked to its original file — and the survey job must be one of the
     * file's own jobs.
     */
    public function update(Request $r, CadastralIndexCard $card)
    {
        $data = $this->validated($r, $card);

        // File status is changed on the file-status screen, which logs why.
        unset($data['file_status'], $data['file_number']);

        $card->update($data);

        return back()->with('success', "{$card->card_ref} updated.");
    }

    /**
     * Record that the card has moved to another stage inside Cadastral.
     * "save" in the route name, so it infers `edit`.
     */
    public function saveMovement(Request $r, CadastralIndexCard $card)
    {
        $data = $r->validate([
            'stage' => ['required', Rule::in(array_keys(IndexCardMovements::STAGES))],
            'note'  => 'nullable|string|max:1000',
        ]);

        DB::connection('sqlsrv')->transaction(function () use ($card, $data) {
            // Lock the card so two officers moving it at once record in order.
            CadastralIndexCard::query()->whereKey($card->id)->lockForUpdate()->first();

            $current = $this->movements->latestFor([$card->id])[$card->id]['stage'] ?? null;

            if ($current === $data['stage']) {
                throw ValidationException::withMessages([
                    'stage' => "{$card->card_ref} is already " . IndexCardMovements::label($current) . '.',
                ]);
            }

            $this->movements->record($card, $data['stage'], $data['note'] ?? null, $current);
        });

        return back()->with('success', "{$card->card_ref} is now " . IndexCardMovements::label($data['stage']) . '.');
    }

    /**
     * Print view. print_count is incremented so the registry knows how many
     * copies of a card are in circulation.
     */
    public function print(CadastralIndexCard $card)
    {
        $movements = $this->lookup->movements($card->file_number, 12);

        $card->increment('print_count');
        $card->update(['last_printed_at' => now()]);

        return view('cadastral_module.information.index_card_print', [
            'card'      => $card->load(['chart', 'surveyJobs']),
            'movements' => $movements['movements'],
            'stages'    => $this->movements->history($card, 12),
        ]);
    }

    /* ------------------------------ helpers ------------------------------ */

    /** The chosen survey job, which must belong to the same file. */
    private function requireJob($jobId, string $fileNumber): ?CadastralSurveyJob
    {
        if (! $jobId) {
            return null;
        }

        $job = CadastralSurveyJob::find((int) $jobId);

        if (! $job || $job->file_number !== $fileNumber || in_array($job->status, self::DEAD_JOBS, true)) {
            throw ValidationException::withMessages([
                'cadastral_survey_job_id' => "That survey job is not a live job for {$fileNumber}.",
            ]);
        }

        return $job;
    }

    /** The live card for a receipt's file, by number or (once installed) by file-index id. */
    private function liveCardFor(CadastralFileReceipt $receipt): ?CadastralIndexCard
    {
        return CadastralIndexCard::query()
            ->where(function ($w) use ($receipt) {
                $w->where('file_number', $receipt->file_number);

                if ($receipt->file_indexing_id && CadastralIndexCard::linkInstalled()) {
                    $w->orWhere('file_indexing_id', $receipt->file_indexing_id);
                }
            })
            ->first();
    }

    /** @return \Illuminate\Support\Collection<int, CadastralSurveyJob> */
    private function jobsFor(?string $fileNumber)
    {
        return CadastralSurveyJob::query()
            ->where('file_number', $fileNumber)
            ->whereNotIn('status', self::DEAD_JOBS)
            ->orderByDesc('id')
            ->get(['id', 'job_number', 'status', 'cadastral_index_card_id']);
    }

    /**
     * Receipts in the Select2 shape. Location is "District, LGA, State" — the
     * plot is its own field. A file that already has a card is greyed out.
     */
    private function formatReceipts($receipts): array
    {
        $numbers = $receipts->pluck('file_number')->unique()->values()->all();

        $cards = $numbers === [] ? collect() : CadastralIndexCard::query()
            ->whereIn('file_number', $numbers)->pluck('card_ref', 'file_number');

        $jobs = $numbers === [] ? collect() : CadastralSurveyJob::query()
            ->whereIn('file_number', $numbers)
            ->whereNotIn('status', self::DEAD_JOBS)
            ->orderByDesc('id')
            ->get(['id', 'job_number', 'status', 'file_number'])
            ->groupBy('file_number');

        return $receipts->map(function (CadastralFileReceipt $rc) use ($cards, $jobs) {
            $card = $cards[$rc->file_number] ?? null;
            $text = $rc->file_number . ($rc->file_title ? ' — ' . $rc->file_title : '') . " ({$rc->receipt_ref})";

            if ($card) $text .= " — already on card {$card}";

            return [
                'id'          => $rc->id,
                'text'        => $text,
                'file_number' => $rc->file_number,
                'owner'       => (string) $rc->file_title,
                'plot'        => (string) $rc->prop_plot,
                'location'    => CadastralAddress::propertyLocation($rc),
                'type'        => CadastralRegistryLookup::typeLabel($rc->file_number, $rc->source_registry, $rc->file_class),
                'file_class'  => $rc->file_class,
                'source'      => $rc->source_registry,
                'card'        => $card,
                'disabled'    => $card !== null,
                'jobs'        => ($jobs[$rc->file_number] ?? collect())
                    ->map(fn ($j) => ['id' => $j->id, 'text' => "{$j->job_number} ({$j->status})"])
                    ->values()->all(),
            ];
        })->values()->all();
    }

    private function validated(Request $r, CadastralIndexCard $card): array
    {
        $rules = [
            'file_title'         => 'nullable|string|max:500',
            'plot_no'            => 'nullable|string|max:50',
            'block_no'           => 'nullable|string|max:50',
            'layout_name'        => 'nullable|string|max:255',
            'cadastral_chart_id' => 'nullable|integer',
            'survey_job_number'  => 'nullable|string|max:50',
            'image_folder'       => 'nullable|string|max:255',
        ] + $this->addressRules($r, 'prop_', false);

        $validator = Validator::make($r->all(), $rules, $this->addressMessages('prop_'));

        $validator->after(function ($v) use ($r, $card) {
            $chartId = $r->input('cadastral_chart_id');

            if ($chartId) {
                $chart = CadastralChart::find($chartId);

                if (! $chart) {
                    $v->errors()->add('cadastral_chart_id', 'That chart does not exist.');
                } elseif ($chart->file_number !== $card->file_number) {
                    $v->errors()->add('cadastral_chart_id', "That chart is for {$chart->file_number}, not {$card->file_number}.");
                }
            }

            $jobNo = trim((string) $r->input('survey_job_number'));

            if ($jobNo !== '' && ! $this->jobsFor($card->file_number)->contains('job_number', $jobNo)) {
                $v->errors()->add('survey_job_number', "{$jobNo} is not a live survey job for {$card->file_number}.");
            }
        });

        // plot_no is the card's plot field; prop_plot follows it.
        return CadastralAddress::normalise($validator->validate(), 'prop_', 'plot_no');
    }
}
