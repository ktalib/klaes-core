<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralFileStatusEvent;
use App\Models\Cadastral\CadastralIndexCard;
use App\Services\AuditService;
use App\Services\Cadastral\CadastralDocuments;
use App\Services\Cadastral\FileStatusNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * File status management (concept note 4.3d): revoked, reinstated, withdrawn,
 * change of purpose, open and close.
 *
 * The card carries the current status; cadastral_file_status_events carries how
 * it got there, append-only. A status is a legal event — who decided it, when it
 * took effect, and under what authority all matter more than the current value.
 *
 * Phase 5 adds, per change: a required effective date, supporting documents
 * filed into the file's EDMS folder as scans (CadastralDocuments — they reach
 * Page Typing like any other page), and a notification on EVERY change, not
 * only revocation and withdrawal (FileStatusNotifier, which explains who hears).
 *
 * The event and its documents are written in one transaction; if anything in
 * it fails, the scans already written to disk are removed again.
 */
class FileStatusController extends Controller
{
    /** At most this many supporting documents per change. */
    private const MAX_DOCUMENTS = 5;

    public function __construct(
        private AuditService $audit,
        private FileStatusNotifier $notifier,
        private CadastralDocuments $documents,
    ) {}

    public function index(Request $r)
    {
        $q = CadastralIndexCard::query();

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('file_number', 'like', "%$term%")
                  ->orWhere('file_title', 'like', "%$term%")
                  ->orWhere('card_ref', 'like', "%$term%");
            });
        }
        if ($s = $r->query('file_status')) $q->where('file_status', $s);

        $cards = $q->orderByDesc('file_status_changed_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $counts = CadastralIndexCard::query()
            ->selectRaw('file_status, COUNT(*) as total')
            ->groupBy('file_status')
            ->pluck('total', 'file_status')
            ->all();

        $recent = CadastralFileStatusEvent::query()
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('cadastral_module.information.file_status', [
            'cards'       => $cards,
            'counts'      => $counts,
            'recent'      => $recent,
            'documents'   => $this->documents->for(CadastralDocuments::OWNER_STATUS_EVENT, $recent->pluck('id')->all())->groupBy('owner_id'),
            'canUpload'   => CadastralDocuments::installed(),
            'maxDocs'     => self::MAX_DOCUMENTS,
        ]);
    }

    /**
     * Change a file's status and log why.
     *
     * The card, the event and its documents are written together: a status
     * with no recorded reason is the thing this screen exists to stop.
     */
    public function update(Request $r, CadastralIndexCard $card)
    {
        $data = $r->validate([
            'to_status'      => ['required', Rule::in(array_keys(CadastralIndexCard::FILE_STATUSES))],
            'reason'         => 'required|string|max:4000',
            'effective_date' => 'required|date',
            'authority_ref'  => 'nullable|string|max:100',
            'documents'      => 'nullable|array|max:' . self::MAX_DOCUMENTS,
            'documents.*'    => CadastralDocuments::fileRule(),
        ], [
            'reason.required'         => 'Remarks are required — a status change without them cannot be defended later.',
            'effective_date.required' => 'The effective date is required: the day the change takes legal effect.',
            'documents.max'           => 'At most ' . self::MAX_DOCUMENTS . ' documents per change.',
        ] + CadastralDocuments::messages('documents.*'));

        $uploads = array_values(array_filter((array) $r->file('documents', [])));

        if ($uploads !== [] && ! CadastralDocuments::installed()) {
            return back()->withInput()->with('error',
                'Document upload is pending installation (the Phase 5 migration). Nothing was changed — apply the status without documents, or wait for the migration.');
        }

        if ($data['to_status'] === $card->file_status) {
            return back()->with('error', "{$card->file_number} is already {$card->file_status_label}.");
        }

        $receipt = $card->sourceReceipt();

        // Refuse before anything is written if the documents have nowhere to go.
        $indexing = $uploads === [] ? null : $this->documents->requireIndexing(
            ($card->getAttributes()['file_indexing_id'] ?? null) ?: $receipt?->file_indexing_id,
            $card->file_number,
            'documents'
        );

        try {
            [$event, $from] = DB::connection('sqlsrv')->transaction(function () use ($card, $data, $uploads, $indexing) {
                // Two officers changing the same file: the second sees the first's status.
                $locked = CadastralIndexCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
                $from   = $locked->file_status;

                if ($from === $data['to_status']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'to_status' => "{$locked->file_number} is already {$locked->file_status_label}.",
                    ]);
                }

                $locked->update([
                    'file_status'            => $data['to_status'],
                    'file_status_changed_at' => now(),
                    'file_status_reason'     => $data['reason'],
                ]);

                $event = CadastralFileStatusEvent::create([
                    'cadastral_index_card_id' => $locked->id,
                    'file_number'             => $locked->file_number,
                    'from_status'             => $from,
                    'to_status'               => $data['to_status'],
                    'reason'                  => $data['reason'],
                    'effective_date'          => $data['effective_date'],
                    'authority_ref'           => $data['authority_ref'] ?? null,
                    'actor_user_id'           => auth()->id(),
                    'actor_name'              => auth()->user()->name ?? null,
                ]);

                foreach ($uploads as $upload) {
                    $this->documents->file(
                        $upload,
                        $indexing,
                        CadastralDocuments::OWNER_STATUS_EVENT,
                        $event->id,
                        CadastralDocuments::KIND_SUPPORTING,
                        "Cadastral file status: {$from} to {$data['to_status']} (event #{$event->id})."
                    );
                }

                return [$event, $from];
            });
        } catch (\Throwable $e) {
            // Disk writes do not roll back with the transaction.
            $this->documents->discardWritten();
            throw $e;
        }

        $card->refresh();

        $notified = $this->recordAndNotify($card, $event, $from, $receipt, count($uploads));

        $label = CadastralIndexCard::FILE_STATUSES[$data['to_status']];

        return back()->with('success', "{$card->file_number} is now {$label}"
            . ($uploads ? ' (' . count($uploads) . ' document(s) filed to the EDMS folder)' : '')
            . ($notified ? "; {$notified} officer(s) notified." : '.'));
    }

    public function history(Request $r)
    {
        $q = CadastralFileStatusEvent::query()->with('card');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where('file_number', 'like', "%$term%");
        }
        if ($s = $r->query('to_status')) $q->where('to_status', $s);

        $events = $q->orderByDesc('id')->paginate(25)->withQueryString();

        $documents = $this->documents
            ->for(CadastralDocuments::OWNER_STATUS_EVENT, $events->pluck('id')->all())
            ->groupBy('owner_id');

        return view('cadastral_module.information.file_status_history', compact('events', 'documents'));
    }

    /**
     * Audit and notification are a record of the change, not the change. A
     * failure here must not undo a status the officer has been told is set.
     *
     * @return int  officers notified
     */
    private function recordAndNotify(CadastralIndexCard $card, CadastralFileStatusEvent $event, ?string $from, $receipt, int $documents): int
    {
        try {
            $this->audit->logAction(
                'CADASTRAL_FILE_STATUS_CHANGED',
                'cadastral_index_card',
                $card->id,
                ['file_status' => $from],
                [
                    'file_status'    => $event->to_status,
                    'reason'         => $event->reason,
                    'effective_date' => optional($event->effective_date)->toDateString(),
                    'event_id'       => $event->id,
                    'documents'      => $documents,
                ],
                "{$card->file_number} ({$card->card_ref})"
            );
        } catch (\Throwable $e) {
            Log::warning('Cadastral file status audit failed: ' . $e->getMessage(), ['card_id' => $card->id]);
        }

        return $this->notifier->notify($card, $event, $receipt);
    }
}
