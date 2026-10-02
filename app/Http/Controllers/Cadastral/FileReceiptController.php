<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralFileReceipt;
use App\Services\Cadastral\CadastralAddress;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\Cadastral\CorrespondenceFiles;
use App\Services\Cadastral\FileNumberFormat;
use App\Services\Cadastral\ReceiptHolds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Cadastral registry's intake queue (concept note 4.1; rebuild Phase 2).
 *
 * A file is logged in by picking it from its source department's index — the
 * clerk chooses the source, then the file from file_indexings over AJAX. The
 * number, owner and location are re-read from that row on the server; nothing
 * the browser posts for them is used, and a free-typed file number cannot be
 * submitted at all.
 *
 * A cadastral copy keeps the SAME file number as the source file, so the same
 * file legitimately appears on several receipts — one per time it comes in —
 * but only one of them may be open at a time (see store()).
 *
 * Registering a receipt commissions its correspondence file, once, through
 * the same writes as the legacy MLS-match screen — or matches the one the file
 * already has (CorrespondenceFiles, Phase 3). A file whose number hits the
 * duplicate register or shares its plot goes On Hold and cannot be registered
 * until an officer clears it (ReceiptHolds); before the hold columns are
 * migrated, registration behaves as it did in Phase 2.
 */
class FileReceiptController extends Controller
{
    /**
     * Intake-queue status, as the brief names it, from the two columns that
     * already exist. No column stores it.
     *
     *   status      correspondence_status              queue status
     *   ----------  ---------------------------------  --------------------
     *   Received    (any)                              Queued
     *   Registered  pending                            In Progress
     *   Registered  matched | created | not_required   Correspondence Done
     *   Archived / Returned / Rejected                 shown as-is (closed)
     *   (any open status, hold_status = On Hold)       On Hold
     *
     * Received is "Queued" whatever correspondence_status says, because that
     * column is computed from file_indexings at intake and is often already
     * "matched" before the registry has done anything with the file.
     */
    public const QUEUE_STATUSES = ['Queued', 'In Progress', 'Correspondence Done'];

    public const QUEUE_ON_HOLD = 'On Hold';

    public function __construct(
        private CadastralRegistryLookup $lookup,
        private ReceiptHolds $holds,
        private CorrespondenceFiles $correspondence,
    ) {}

    /** The queue filter's options: On Hold only once the hold columns exist. */
    public static function queueStatuses(): array
    {
        return ReceiptHolds::available()
            ? [...self::QUEUE_STATUSES, self::QUEUE_ON_HOLD]
            : self::QUEUE_STATUSES;
    }

    /** @return array{0: string, 1: string}  [label, status-badge class] */
    public static function queueStatus(CadastralFileReceipt $receipt): array
    {
        if ($receipt->isOnHold() && ! in_array($receipt->status, CadastralRegistryLookup::CLOSED_RECEIPT_STATUSES, true)) {
            return [self::QUEUE_ON_HOLD, 'rejected'];
        }

        return match ($receipt->status) {
            'Received'   => ['Queued', 'pending'],
            'Registered' => $receipt->correspondence_status === 'pending'
                ? ['In Progress', 'review']
                : ['Correspondence Done', 'completed'],
            default      => [$receipt->status, $receipt->status_badge],
        };
    }

    /** The same mapping as a query constraint, for the filter and the tiles. */
    private static function whereQueue($q, string $queue)
    {
        $holds = ReceiptHolds::available();

        if ($queue === self::QUEUE_ON_HOLD) {
            return $holds
                ? $q->where('hold_status', CadastralFileReceipt::HOLD_ON)
                    ->whereNotIn('status', CadastralRegistryLookup::CLOSED_RECEIPT_STATUSES)
                : $q->whereRaw('1 = 0');
        }

        // A held receipt shows as On Hold, so it is taken out of the other three.
        if ($holds && in_array($queue, self::QUEUE_STATUSES, true)) {
            $q->where(fn ($w) => $w->whereNull('hold_status')->orWhere('hold_status', '!=', CadastralFileReceipt::HOLD_ON));
        }

        return match ($queue) {
            'Queued'              => $q->where('status', 'Received'),
            'In Progress'         => $q->where('status', 'Registered')->where('correspondence_status', 'pending'),
            'Correspondence Done' => $q->where('status', 'Registered')->where('correspondence_status', '!=', 'pending'),
            default               => $q,
        };
    }

    public function index(Request $r)
    {
        $q = CadastralFileReceipt::query();

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('file_number', 'like', "%$term%")
                  ->orWhere('file_title', 'like', "%$term%")
                  ->orWhere('receipt_ref', 'like', "%$term%");
            });
        }
        if ($s = $r->query('source_registry')) $q->where('source_registry', $s);
        if ($qs = $r->query('queue'))          self::whereQueue($q, $qs);
        if ($st = $r->query('status'))         $q->where('status', $st);
        if ($c = $r->query('file_class'))      $q->where('file_class', $c);
        if ($r->query('duplicates') === '1')   $q->where('duplicate_flag', true);

        $receipts = $q->orderByDesc('id')->paginate(15)->withQueryString();

        // Tiles read the whole log, not the filtered page — they are a picture of
        // the registry, not of the search.
        $stats = [
            'queued'      => self::whereQueue(CadastralFileReceipt::query(), 'Queued')->count(),
            'in_progress' => self::whereQueue(CadastralFileReceipt::query(), 'In Progress')->count(),
            'done'        => self::whereQueue(CadastralFileReceipt::query(), 'Correspondence Done')->count(),
            'duplicates'  => CadastralFileReceipt::where('duplicate_flag', true)->count(),
            'on_hold'     => ReceiptHolds::available()
                ? self::whereQueue(CadastralFileReceipt::query(), self::QUEUE_ON_HOLD)->count()
                : null,
        ];

        $holdsEnabled = ReceiptHolds::available();

        return view('cadastral_module.registry.receipts', compact('receipts', 'stats', 'holdsEnabled'));
    }

    public function create()
    {
        $receipt = new CadastralFileReceipt([
            'status'      => 'Received',
            'received_at' => now(),
            'prop_state'  => 'Kano',
        ]);

        // After a failed submit, put the picked file back in the picker.
        $picked  = $this->lookup->sourceFile(old('source_registry') ?: null, (int) old('file_indexing_id')) ?: null;
        $sources = $this->intakeSources();

        return view('cadastral_module.registry.receipt_register', compact('receipt', 'picked', 'sources'));
    }

    /**
     * Select2 source for the File No picker: files in one source department
     * whose number starts with what was typed. GET, so it infers `view`.
     */
    public function sourceFiles(Request $r): JsonResponse
    {
        $source = (string) $r->query('source', '');

        if (! in_array($source, $this->intakeSources(), true)) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        return response()->json([
            'results'    => $this->lookup->sourceFiles($source, (string) $r->query('q', '')),
            'pagination' => ['more' => false],
        ]);
    }

    public function store(Request $r)
    {
        $data   = $this->validated($r);
        $source = $data['source_registry'];

        // A new intake always enters the queue at the start.
        $data['status'] = 'Received';

        // First read, unlocked: the slower look-ups (duplicate register, shelf
        // workbooks) run before the transaction so the row lock below is brief.
        $file = $this->requireSourceFile($source, (int) $data['file_indexing_id']);
        $this->applySourceFile($data, $file);

        $data['file_class'] = FileNumberFormat::classify($data['file_number']);
        $this->guardConversionPurpose($data, $data['file_number']);

        // Nullable rules omit the key entirely when the box is left blank.
        $data['shelf_location'] = ($data['shelf_location'] ?? null)
            ?: $this->lookup->shelfLocation($data['file_number']);

        $findings = $this->applyRegistryFindings($data);

        // A hit on intake puts the file On Hold straight away (once the hold
        // columns exist); it cannot be registered until an officer clears it.
        // forceFill, not create(): mass assignment on a guarded model drops keys
        // missing from the column listing Laravel caches per process, so a
        // worker that started before the hold migration would lose them silently.
        $hold = $this->holds->intakeHold($findings);

        // The one-open-receipt rule is enforced here rather than by a filtered
        // unique index (plan, Phase 2). Every intake of a file first takes an
        // update lock on its file_indexings row, so two clerks logging the same
        // file at once are serialised and the second sees the first's receipt.
        $receipt = DB::connection('sqlsrv')->transaction(function () use ($data, $source, $hold) {
            $file = $this->requireSourceFile($source, (int) $data['file_indexing_id'], true);

            if ($file['open_receipt']) {
                throw ValidationException::withMessages([
                    'file_indexing_id' => "{$file['file_number']} is already in the queue as {$file['open_receipt']}. "
                        . 'Archive, return or reject that receipt before logging the file again.',
                ]);
            }

            $this->applySourceFile($data, $file);
            $data['receipt_ref'] = CadastralFileReceipt::nextRef('receipt_ref', 'CRR', 4);

            $receipt = new CadastralFileReceipt($data);
            $receipt->forceFill($hold)->save();

            return $receipt;
        });

        $message = "File {$receipt->file_number} logged as {$receipt->receipt_ref}.";

        if ($receipt->isConversion()) {
            $message .= ' Conversion file: charting is not required.';
        }

        if ($receipt->isOnHold()) {
            $this->holds->audit($receipt, 'CADASTRAL_RECEIPT_HELD', [], [
                'hold_status' => $receipt->hold_status,
                'hold_reason' => $receipt->hold_reason,
            ]);
            $message .= ' It is ON HOLD for investigation and cannot be registered until an officer clears it.';
        }

        if ($receipt->duplicate_flag) {
            // Said out loud rather than buried in a column: this is exactly the
            // moment the clerk can still do something about it.
            return redirect()
                ->route('cadastral-module.registry.receipts')
                ->with('error', $message . ' ' . $receipt->duplicate_note);
        }

        return redirect()
            ->route('cadastral-module.registry.receipts')
            ->with('success', $message);
    }

    public function edit(CadastralFileReceipt $receipt)
    {
        $summary = $this->lookup->summarise($receipt->file_number);
        // No source filter: the receipt's file is shown even if it has since
        // moved registry. It cannot be changed here.
        $picked  = $this->lookup->sourceFile(null, $receipt->file_indexing_id);
        $sources = $this->intakeSources();

        return view('cadastral_module.registry.receipt_register', compact('receipt', 'summary', 'picked', 'sources'));
    }

    /**
     * The file and its source are fixed once logged; a wrong pick is returned
     * or rejected and logged again. The address is re-read from the source row.
     */
    public function update(Request $r, CadastralFileReceipt $receipt)
    {
        $data = $this->validated($r, $receipt);

        if ($file = $this->lookup->sourceFile(null, $receipt->file_indexing_id)) {
            $this->applySourceFile($data, $file, false);
        }

        $data['file_class'] = FileNumberFormat::classify($receipt->file_number);
        $this->guardConversionPurpose($data, $receipt->file_number);

        // Correspondence state is the registration's to set once it has run; the
        // index flag alone would turn "created" back into "matched".
        $this->applyRegistryFindings($data, $receipt->file_number, ! $receipt->registered_at);

        // Registering runs the duplicate hold and commissions the correspondence
        // file; only the Register action does both, so the edit form cannot be a
        // way round either.
        if ($data['status'] === 'Registered' && $receipt->status !== 'Registered') {
            throw ValidationException::withMessages([
                'status' => 'Register the file with the Register action on the intake queue, so the duplicate checks and the correspondence file run.',
            ]);
        }

        DB::connection('sqlsrv')->transaction(function () use ($receipt, $data) {
            $this->guardReopen($receipt, $data['status']);
            $receipt->update($data);
        });

        return redirect()
            ->route('cadastral-module.registry.receipts')
            ->with('success', "{$receipt->receipt_ref} updated.");
    }

    /* --------------------------- status transitions --------------------------- */

    /**
     * Register a receipt: re-run the duplicate checks, then commission or match
     * its correspondence file — once.
     *
     * Lock order is the same as store() and guardReopen(): the file_indexings
     * row first, then the receipt row. The receipt is re-read under its lock, so
     * a double click or a second officer finds it already Registered and stops
     * before CorrespondenceFiles is reached.
     */
    public function markRegistered(CadastralFileReceipt $receipt)
    {
        if ($receipt->status === 'Registered') {
            return back()->with('error', "{$receipt->receipt_ref} is already registered.");
        }

        // Slow reads outside the transaction, so the locks are brief.
        $findings = $this->holds->findings($receipt->file_number);
        $shelf    = $receipt->shelf_location ?: $this->lookup->shelfLocation($receipt->file_number);

        try {
            [$locked, $held, $result, $before] = DB::connection('sqlsrv')->transaction(function () use ($receipt, $findings, $shelf) {
                if ($receipt->file_indexing_id) {
                    $this->lookup->sourceFile(null, $receipt->file_indexing_id, true);
                }

                $locked = CadastralFileReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === 'Registered') {
                    throw ValidationException::withMessages(['status' => "{$locked->receipt_ref} is already registered."]);
                }

                $this->guardReopen($locked, 'Registered');

                $before = ['hold_status' => $locked->hold_status, 'hold_reason' => $locked->hold_reason];

                if ($this->holds->holdForRegistration($locked, $findings)) {
                    // Saved, not thrown: a hold placed here must outlive the refusal.
                    $locked->save();

                    return [$locked, true, null, $before];
                }

                $locked->fill([
                    'status'         => 'Registered',
                    'registered_at'  => now(),
                    'shelf_location' => $shelf,
                ]);

                $result = $this->correspondence->ensureFor($locked);

                $locked->save();

                return [$locked, false, $result, $before];
            });
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        if ($held) {
            if ($before['hold_status'] !== CadastralFileReceipt::HOLD_ON) {
                $this->holds->audit($locked, 'CADASTRAL_RECEIPT_HELD', $before, [
                    'hold_status' => $locked->hold_status,
                    'hold_reason' => $locked->hold_reason,
                ]);
            }

            return back()->with('error',
                "{$locked->receipt_ref} is on hold for investigation and was not registered"
                . ($locked->hold_reason ? ": {$locked->hold_reason}" : '.')
                . ' An officer must clear the hold, with a remark, first.');
        }

        $this->correspondence->audit($locked, $result);

        $message = "{$locked->receipt_ref} registered. " . match ($result['action']) {
            'created' => "Correspondence file {$result['shadow']->ref_number} created.",
            'matched' => 'Matched to its existing correspondence file' . ($result['fileno'] ? " ({$result['fileno']})" : '') . '.',
            default   => 'Its correspondence file was already linked.',
        };

        return back()->with('success', $message);
    }

    /**
     * Put an open receipt On Hold by hand, with a reason. A held receipt cannot
     * be registered. "mark-held" so the route infers `edit`.
     */
    public function markHeld(Request $r, CadastralFileReceipt $receipt)
    {
        if (! ReceiptHolds::available()) {
            return back()->with('error', 'Holds are not available until the hold columns are migrated.');
        }

        $reason = trim((string) Validator::make($r->all(), [
            'hold_reason' => 'required|string|min:3|max:1000',
        ], [
            'hold_reason.required' => 'Give the reason for the hold.',
            'hold_reason.min'      => 'Give the reason for the hold.',
        ])->validate()['hold_reason']);

        try {
            [$locked, $before] = DB::connection('sqlsrv')->transaction(function () use ($receipt, $reason) {
                $locked = CadastralFileReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

                if (in_array($locked->status, CadastralRegistryLookup::CLOSED_RECEIPT_STATUSES, true)) {
                    throw ValidationException::withMessages(['hold_reason' => "{$locked->receipt_ref} is {$locked->status}; only an open receipt can be held."]);
                }
                if ($locked->isOnHold()) {
                    throw ValidationException::withMessages(['hold_reason' => "{$locked->receipt_ref} is already on hold."]);
                }

                $before = ['hold_status' => $locked->hold_status, 'hold_reason' => $locked->hold_reason];
                $this->holds->fillHold($locked, $reason);
                $locked->save();

                return [$locked, $before];
            });
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $this->holds->audit($locked, 'CADASTRAL_RECEIPT_HELD', $before, [
            'hold_status' => $locked->hold_status,
            'hold_reason' => $locked->hold_reason,
        ]);

        return back()->with('success', "{$locked->receipt_ref} put on hold for investigation.");
    }

    /** Clear a hold. The clearing remark is required. "mark-cleared" infers `edit`. */
    public function markCleared(Request $r, CadastralFileReceipt $receipt)
    {
        if (! ReceiptHolds::available()) {
            return back()->with('error', 'Holds are not available until the hold columns are migrated.');
        }

        $note = trim((string) Validator::make($r->all(), [
            'hold_clear_note' => 'required|string|min:3|max:1000',
        ], [
            'hold_clear_note.required' => 'A hold can only be cleared with a remark saying why.',
            'hold_clear_note.min'      => 'A hold can only be cleared with a remark saying why.',
        ])->validate()['hold_clear_note']);

        try {
            [$locked, $before] = DB::connection('sqlsrv')->transaction(function () use ($receipt, $note) {
                $locked = CadastralFileReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isOnHold()) {
                    throw ValidationException::withMessages(['hold_clear_note' => "{$locked->receipt_ref} is not on hold."]);
                }

                $before = ['hold_status' => $locked->hold_status, 'hold_reason' => $locked->hold_reason];
                $this->holds->fillClear($locked, $note);
                $locked->save();

                return [$locked, $before];
            });
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $this->holds->audit($locked, 'CADASTRAL_RECEIPT_HOLD_CLEARED', $before, [
            'hold_status'     => $locked->hold_status,
            'hold_clear_note' => $locked->hold_clear_note,
        ]);

        return back()->with('success', "Hold on {$locked->receipt_ref} cleared. It can now be registered.");
    }

    public function markArchived(CadastralFileReceipt $receipt)
    {
        $receipt->update(['status' => 'Archived', 'archived_at' => now()]);

        return back()->with('success', "{$receipt->receipt_ref} archived.");
    }

    public function markReturned(Request $r, CadastralFileReceipt $receipt)
    {
        $note = trim((string) $r->input('condition_note'));

        $receipt->update([
            'status'         => 'Returned',
            'condition_note' => $note ?: $receipt->condition_note,
        ]);

        return back()->with('success', "{$receipt->receipt_ref} returned to {$receipt->source_registry}.");
    }

    /**
     * Only a Received receipt can be deleted outright.
     *
     * Anything further along is part of the registry's record of what came in;
     * it is closed, not erased. (Note also that can_delete is granted to nobody
     * in module_permissions today, so in practice this route is administrative.)
     */
    public function destroy(CadastralFileReceipt $receipt)
    {
        if ($receipt->status !== 'Received') {
            return back()->with('error',
                "{$receipt->receipt_ref} is {$receipt->status} and cannot be deleted. Return or archive it instead.");
        }

        if ($receipt->reports()->exists()) {
            return back()->with('error',
                "{$receipt->receipt_ref} has cadastral reports against it and cannot be deleted.");
        }

        $ref = $receipt->receipt_ref;
        $receipt->delete();

        return back()->with('success', "{$ref} deleted.");
    }

    /* ------------------------------- helpers ------------------------------- */

    /**
     * The sources a file can be logged in from: the lookup's mapping, limited
     * to what config still lists. Deeds is in config but has no mapping (Q4),
     * so it is never offered.
     */
    private function intakeSources(): array
    {
        return array_values(array_intersect(
            array_keys(CadastralRegistryLookup::INTAKE_SOURCES),
            app(\App\Services\Cadastral\CadastralSettings::class)->sourceRegistries()
        ));
    }

    /** The picked file, re-read and checked against the chosen source. */
    private function requireSourceFile(string $source, int $id, bool $lock = false): array
    {
        $file = $this->lookup->sourceFile($source, $id, $lock);

        if (! $file) {
            throw ValidationException::withMessages([
                'file_indexing_id' => "That file is not in the {$source} registry. Pick it again from the {$source} list.",
            ]);
        }

        if ($file['decommissioned']) {
            throw ValidationException::withMessages([
                'file_indexing_id' => "{$file['file_number']} has been decommissioned"
                    . ($file['successor'] ? " and replaced by {$file['successor']}" : '')
                    . '. Log the current file instead.',
            ]);
        }

        return $file;
    }

    /**
     * Overwrite the file's identity and location with the source row's values.
     *
     * Location: each address field the source row fills wins over whatever was
     * posted. A field the source leaves blank (3,755 Land files have no
     * district) is left to the clerk, and the form leaves exactly those fields
     * open — otherwise those files could never be given a location at all.
     */
    private function applySourceFile(array &$data, array $file, bool $identity = true): void
    {
        if ($identity) {
            $data['file_indexing_id'] = $file['id'];
            $data['file_number']      = $file['file_number'];
            $data['file_title']       = $file['owner'] !== '' ? $file['owner'] : null;
        }

        $address = $file['address'];

        // District and street travel with their "Other" box as a pair.
        foreach (['district', 'street'] as $kind) {
            if ($address['prop_' . $kind] !== null) {
                $data['prop_' . $kind]            = $address['prop_' . $kind];
                $data['prop_' . $kind . '_other'] = $address['prop_' . $kind . '_other'];
            }
            unset($address['prop_' . $kind], $address['prop_' . $kind . '_other']);
        }

        foreach ($address as $col => $value) {
            if ($value !== null) $data[$col] = $value;
        }

        $data = CadastralAddress::normalise($data, 'prop_');
    }

    /** A conversion file is not charted (concept note 4.3a), so it cannot come in for charting. */
    private function guardConversionPurpose(array $data, string $fileNumber): void
    {
        if (($data['file_class'] ?? null) === 'conversion' && ($data['purpose'] ?? null) === 'Charting') {
            throw ValidationException::withMessages([
                'purpose' => "{$fileNumber} is a conversion file; conversion files are not charted. Choose another purpose.",
            ]);
        }
    }

    /** Refuse to bring a closed receipt back open while another receipt holds the file. */
    private function guardReopen(CadastralFileReceipt $receipt, string $newStatus): void
    {
        $closed = CadastralRegistryLookup::CLOSED_RECEIPT_STATUSES;

        if (! $receipt->file_indexing_id || in_array($newStatus, $closed, true) || ! in_array($receipt->status, $closed, true)) {
            return;
        }

        // Same lock as store(), so a reopen and a new intake cannot cross.
        $this->lookup->sourceFile(null, $receipt->file_indexing_id, true);

        $open = $this->lookup->openReceipts([$receipt->file_indexing_id], $receipt->id);

        if ($open) {
            throw ValidationException::withMessages([
                'status' => "{$receipt->file_number} is already open as " . reset($open) . '; only one receipt per file can be open.',
            ]);
        }
    }

    /**
     * Duplicate and correspondence state, recomputed on every save rather than
     * trusted from the form — the register moves under us between page loads.
     *
     * The note comes from ReceiptHolds::findings(), the same function the
     * registration check uses, so an unchanged file produces an identical note
     * and a cleared hold is recognised as covering it.
     *
     * @return array{flag: bool, note: ?string}
     */
    private function applyRegistryFindings(array &$data, ?string $fileNumber = null, bool $withCorrespondence = true): array
    {
        $fileNumber ??= $data['file_number'];

        if ($withCorrespondence) {
            $data['correspondence_status'] = $this->lookup->correspondence($fileNumber)['status'];
        }

        $findings = $this->holds->findings($fileNumber);

        $data['duplicate_flag'] = $findings['flag'];
        $data['duplicate_note'] = $findings['note'];

        return $findings;
    }

    /**
     * Only what the clerk may set. file_number, file_title and the source's
     * address fields are not taken from the request; store() and update() fill
     * them from file_indexings.
     */
    private function validated(Request $r, ?CadastralFileReceipt $existing = null): array
    {
        $rules = [
            'source_reference' => 'nullable|string|max:100',
            'received_from'    => 'nullable|string|max:255',
            'received_at'      => 'required|date',
            'purpose'          => ['nullable', Rule::in(CadastralFileReceipt::PURPOSES)],
            'num_pages'        => 'nullable|integer|min:0|max:100000',
            'condition_note'   => 'nullable|string|max:500',
            'shelf_location'   => 'nullable|string|max:100',
        ]
        // Not required: a source row with no district or LGA is still a real
        // file, and its receipt must be loggable.
        + CadastralAddress::rules('prop_', false);

        if ($existing) {
            $rules['status'] = ['required', Rule::in(CadastralFileReceipt::STATUSES)];
        } else {
            $rules['source_registry']  = ['required', Rule::in($this->intakeSources())];
            $rules['file_indexing_id'] = 'required|integer|min:1';
        }

        $messages = CadastralAddress::messages('prop_') + [
            'source_registry.required'  => 'Choose the source department first.',
            'source_registry.in'        => 'Files cannot be logged in from that source.',
            'file_indexing_id.required' => 'Pick the file from the source department\'s list.',
            'file_indexing_id.integer'  => 'Pick the file from the source department\'s list.',
        ];

        $data = CadastralAddress::normalise(Validator::make($r->all(), $rules, $messages)->validate(), 'prop_');

        $data['received_by'] = $existing?->received_by ?: auth()->id();

        return $data;
    }
}
