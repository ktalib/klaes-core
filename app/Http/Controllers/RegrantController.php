<?php

namespace App\Http\Controllers;

use App\Models\TitleStatusApplication;
use App\Services\RegrantCorrectionService;
use App\Services\RegrantTermService;
use App\Services\TitleStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Re-grant management.
 *
 * Two views over the same subject:
 *   - "register"  — every Re-grant on record. These live in `title_status_applications`
 *                   with title_type = 'Re-grant'; there is no separate re-grant table.
 *   - "due"       — files whose statutory term has run out and which therefore ought to
 *                   have been re-granted, computed by {@see RegrantTermService}.
 *
 * Raising a Re-grant from the "due" list writes through {@see TitleStatusService} so the
 * record and its file flags are created exactly as the Title Status module would.
 */
class RegrantController extends Controller
{
    public function __construct(
        protected RegrantTermService $termService,
        protected TitleStatusService $titleStatusService,
        protected RegrantCorrectionService $correctionService
    ) {}

    public function index(Request $request): View
    {
        $tab    = $request->input('tab') === 'due' ? 'due' : 'register';
        $limit  = max(10, min((int) $request->input('limit', 25), 200));
        $search = trim((string) $request->input('search'));

        $filters = [
            'source' => trim((string) $request->input('source')),
            'term'   => trim((string) $request->input('term')),
            'search' => $search,
        ];

        if ($tab === 'due') {
            $records = $this->termService->due($filters, $limit)->appends($request->query());
        } else {
            $records = $this->registerQuery($search)
                ->paginate($limit)
                ->appends($request->query());

            $this->attachRelatedFileNo($records);
        }

        // Who captured each Re-grant, for the "Captured By" column. captured_by is the
        // user id TitleStatusService writes; the name opens the shared profile card.
        $capturers = $tab === 'register'
            ? \App\Models\User::whereIn('id', collect($records->items())->pluck('captured_by')->filter()->unique())
                ->get(['id', 'first_name', 'last_name'])->keyBy('id')
            : collect();

        return view('regrant.index', [
            'tab'           => $tab,
            'records'       => $records,
            'capturers'     => $capturers,
            'limit'         => $limit,
            'search'        => $search,
            'filters'       => $filters,
            'stats'         => $this->stats(),
            'unassessable'  => $this->termService->unassessableCounts(),
            'currentYear'   => (int) now()->format('Y'),
        ]);
    }

    /** Every Re-grant application on record, newest first. */
    private function registerQuery(string $search)
    {
        return TitleStatusApplication::query()
            ->from('title_status_applications as regrant_records')
            // Both directions belong in the register: "Re-granted From" on the new file
            // and "Re-granted To" on the old one are stored independently.  Display the
            // relationship once, preferring the successor-side "Re-granted From" record.
            // Legacy records without a direction are reduced to their newest record too.
            ->whereIn('title_type', TitleStatusApplication::REGRANT_TYPES)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->whereNotExists(function ($q) {
                $priority = "CASE reciprocal.title_type
                    WHEN 'Re-granted From' THEN 1
                    WHEN 'Re-grant' THEN 2
                    ELSE 3 END";
                $currentPriority = "CASE regrant_records.title_type
                    WHEN 'Re-granted From' THEN 1
                    WHEN 'Re-grant' THEN 2
                    ELSE 3 END";

                $q->selectRaw('1')
                    ->from('title_status_applications as reciprocal')
                    ->whereColumn('reciprocal.file_no', 'regrant_records.see_fileno')
                    ->whereColumn('reciprocal.see_fileno', 'regrant_records.file_no')
                    ->whereIn('reciprocal.title_type', TitleStatusApplication::REGRANT_TYPES)
                    ->where(fn ($deleted) => $deleted->whereNull('reciprocal.is_deleted')->orWhere('reciprocal.is_deleted', 0))
                    ->whereRaw("({$priority} < {$currentPriority}
                        OR ({$priority} = {$currentPriority} AND reciprocal.id > regrant_records.id))");
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('see_fileno', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%")
                        ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('plot_no', 'LIKE', "%{$search}%")
                        ->orWhere('location', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');
    }

    /**
     * Fill in the "Re-granted from" file for records that were captured without one.
     *
     * 35 of the 71 records on file carry no `see_fileno` because the File Indexing dialog
     * leaves the "See" picker optional. The file's own indexing row usually knows the answer
     * in `related_fileno`, so fall back to that. Two deliberate limits:
     *
     *   - Land files only (`general_registry = 'Lands Registry'`). Other registries link
     *     files for reasons unrelated to a re-grant, so their related file is not evidence.
     *   - Exactly one related file. `related_fileno` is a JSON array and 10 of these records
     *     list two or more; with several candidates there is no basis to pick one, so they
     *     stay unlinked rather than being given a guess.
     *
     * The resolved value is exposed as `derived_see_fileno` and never written back — this is
     * a display aid, not a correction of the stored record.
     */
    private function attachRelatedFileNo($records): void
    {
        $needing = collect($records->items())
            ->filter(fn ($r) => trim((string) $r->see_fileno) === '')
            ->pluck('file_no')
            ->filter()
            ->unique()
            ->values();

        $records->each(fn ($r) => $r->derived_see_fileno = null);

        if ($needing->isEmpty()) {
            return;
        }

        $indexed = DB::connection('sqlsrv')->table('file_indexings')
            ->whereIn('file_number', $needing->all())
            ->where('general_registry', 'Lands Registry')
            ->get(['file_number', 'related_fileno'])
            ->keyBy('file_number');

        foreach ($records as $record) {
            if (trim((string) $record->see_fileno) !== '') {
                continue;
            }

            $row = $indexed->get($record->file_no);
            if (!$row) {
                continue;
            }

            $related = json_decode((string) $row->related_fileno, true);
            if (!is_array($related)) {
                continue;
            }

            $related = array_values(array_filter(
                array_map('trim', $related),
                fn ($value) => $value !== '' && $value !== $record->file_no
            ));

            if (count($related) === 1) {
                $record->derived_see_fileno = $related[0];
            }
        }
    }

    /** Counters for the page header. */
    private function stats(): array
    {
        $registerTotal = $this->registerQuery('')->count();

        // cofo/rofo feed the Instrument filter's option labels, not a card.
        $due = $this->termService->dueCounts();

        return [
            'register_total' => $registerTotal,
            'due_total'      => $due['total'],
            'due_cofo'       => $due['cofo'],
            'due_rofo'       => $due['rofo'],
        ];
    }

    /**
     * Raise a Re-grant against a file from the "due" list. Delegates to TitleStatusService
     * so the application row, the file flags and the linkage are written by the same code
     * path the Title Status module uses — this controller does not duplicate that logic.
     */
    public function raise(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->input('file_no', ''));
        $reason = trim((string) $request->input('reason', ''));

        if ($fileNo === '') {
            return response()->json(['success' => false, 'message' => 'File number is required.'], 422);
        }

        $existing = TitleStatusApplication::where('file_no', $fileNo)
            ->whereIn('title_type', TitleStatusApplication::REGRANT_TYPES)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "A Re-grant is already on record for {$fileNo}.",
            ], 409);
        }

        $details = DB::connection('sqlsrv')->table('file_indexings')
            ->where('file_number', $fileNo)
            ->first();

        $this->titleStatusService->recordRegrant($fileNo, '', [
            'url'              => 'land',
            'file_indexing_id' => $details->id ?? null,
            'prop_id'          => $details->prop_id ?? null,
            'file_title'       => $details->file_title ?? null,
            'applicant_name'   => $details->current_holder ?? ($details->original_holder ?? null),
            'plot_no'          => $details->plot_number ?? null,
            'district'         => $details->district ?? null,
            'lga'              => $details->lga ?? null,
            'location'         => $details->location ?? null,
            'land_use'         => $details->land_use_type ?? null,
        ]);

        if ($reason !== '') {
            TitleStatusApplication::where('file_no', $fileNo)
                ->where('title_type', TitleStatusApplication::TYPE_REGRANT)
                ->latest('id')
                ->limit(1)
                ->update(['reason' => $reason, 'updated_by' => Auth::id()]);
        }

        // The file now has a Re-grant on record, so it must drop out of the due list —
        // which is cached, and would otherwise keep offering it for up to 15 minutes.
        $this->termService->flushCache();

        return response()->json([
            'success' => true,
            'message' => "Re-grant raised for {$fileNo}.",
        ]);
    }

    /**
     * What a correction would change, without writing anything.
     *
     * The dialog renders this verbatim so the officer reads the consequence — which
     * files change hands, which retirement is released, what cannot be released — before
     * confirming. On a register with no backups the preview is the safety mechanism.
     */
    public function correctionPreview(Request $request, int $id): JsonResponse
    {
        if (!$this->canCorrect()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to correct Re-grant records.'], 403);
        }

        try {
            return response()->json([
                'success' => true,
                'plan'    => $this->correctionService->preview($id, $this->mode($request)),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Apply a correction — swap the two file numbers round, or withdraw the Re-grant
     * entirely. Both unwind the retirement the original entry caused; see
     * {@see RegrantCorrectionService}.
     */
    public function correct(Request $request, int $id): JsonResponse
    {
        if (!$this->canCorrect()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to correct Re-grant records.'], 403);
        }

        $mode = $this->mode($request);
        $note = trim((string) $request->input('note', ''));

        try {
            $plan = $this->correctionService->apply($id, $mode, $note !== '' ? $note : null);
        } catch (\Throwable $e) {
            Log::error('Re-grant correction failed', ['id' => $id, 'mode' => $mode, 'message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $message = $mode === RegrantCorrectionService::MODE_SWAP
            ? "Direction corrected — {$plan['resulting']['successor']} is now recorded as re-granted from {$plan['resulting']['parent']}."
            : "Re-grant withdrawn. {$plan['current']['successor']} and {$plan['current']['parent']} are no longer linked.";

        return response()->json([
            'success'  => true,
            'message'  => $message,
            'warnings' => $plan['warnings'],
        ]);
    }

    /** Only the two corrections are accepted; anything else is rejected rather than guessed. */
    private function mode(Request $request): string
    {
        $mode = (string) $request->input('mode', RegrantCorrectionService::MODE_SWAP);

        if (!in_array($mode, [RegrantCorrectionService::MODE_SWAP, RegrantCorrectionService::MODE_UNDO], true)) {
            throw new \InvalidArgumentException('Unknown correction.');
        }

        return $mode;
    }

    /**
     * Super Admin only, for now.
     *
     * A correction rewrites title-status flags across every source table and releases a
     * real decommissioning, so while the tool is new it is held to the narrowest audience
     * rather than the Regrant module's `edit` grant. Widening it later means swapping this
     * one predicate for `$user->canDo('Regrant', 'edit')` — and the matching @if in
     * regrant/index.blade.php, which must agree with this or the menu lies about what it
     * can do.
     *
     * isSuperAdmin(), not `type === 'super admin'`: this system records a Super Admin three
     * ways — the account type, the "supper admin" spelling, and the assigned role — and the
     * literal check silently refuses the other two.
     */
    private function canCorrect(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->isSuperAdmin();
    }
}
