<?php

namespace App\Http\Controllers;

use App\Support\BuyerListLog as Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Autosave for the Add/Edit Buyers capture.
 *
 * The buyers form held everything in the DOM until the final "Save Buyers", so
 * anything that emptied the page — a session timeout, a 419, a stray navigation —
 * took the whole capture with it. The browser now posts the form here every few
 * seconds, and offers to restore it on the way back in.
 *
 * ONE DRAFT PER FILE: the row is keyed on the normalised file number, so the same
 * file always updates the same draft instead of accumulating copies. See the
 * migration 2026_08_12_100000_create_buyer_list_drafts_table for the shape.
 *
 * Everything here is best-effort. A draft that cannot be written must never break
 * the capture the officer is in the middle of, so failures are logged to
 * storage/logs/buyer_list.log and reported as a soft `success: false` rather than
 * thrown — the form keeps working, just without a safety net.
 */
class BuyerListDraftController extends Controller
{
    private const TABLE = 'buyer_list_drafts';

    /** Guarded so a database that has not run the migration yet degrades quietly. */
    private static ?bool $tableExists = null;

    public function __construct()
    {
        $this->middleware('auth');
        // Autosave fires on a debounce while typing; the ceiling only exists to
        // stop a runaway loop, not to pace normal keying.
        $this->middleware('throttle:180,1')->only(['save']);
    }

    /**
     * Upsert the in-progress capture for one file.
     */
    public function save(Request $request): JsonResponse
    {
        if (!$this->tableExists()) {
            return response()->json([
                'success' => false,
                'message' => 'Draft storage is not available.',
            ]);
        }

        $validated = $request->validate([
            'application_id' => 'required|integer',
            'file_no' => 'nullable|string|max:120',
            'rows' => 'required|array',
            'client_trace_id' => 'nullable|string|max:64',
        ]);

        $applicationId = (int) $validated['application_id'];
        $rows = $this->sanitizeRows($validated['rows']);
        $fileNo = $this->resolveFileNo($applicationId, $validated['file_no'] ?? null);
        $draftKey = $this->draftKey($fileNo, $applicationId);
        $filled = $this->countFilledRows($rows);

        // An autosave that has nothing in it must not overwrite a draft that has
        // something in it: the form emptying itself is the very failure this
        // table exists to survive, and the empty state arrives as a save too.
        $existing = DB::connection('sqlsrv')->table(self::TABLE)
            ->where('draft_key', $draftKey)
            ->first();

        // An autosave with nothing typed into it has nothing to preserve, and a
        // row written for it would only be an empty draft to step over later.
        if ($filled === 0 && !$existing) {
            return response()->json([
                'success' => false,
                'ignored' => true,
                'message' => 'Nothing to save yet.',
            ]);
        }

        if ($filled === 0 && $existing && (int) $existing->rows_filled > 0) {
            Log::warning('draft save ignored: empty payload would clear a populated draft', [
                'trace_id' => $validated['client_trace_id'] ?? null,
                'draft_key' => $draftKey,
                'application_id' => $applicationId,
                'existing_rows_filled' => $existing->rows_filled,
            ]);

            return response()->json([
                'success' => false,
                'ignored' => true,
                'message' => 'Empty autosave ignored — the saved draft was kept.',
                'draft_key' => $draftKey,
                'rows_filled' => (int) $existing->rows_filled,
                'last_saved_at' => $existing->last_saved_at,
            ]);
        }

        $now = now();

        $values = [
            'draft_name' => $this->draftName($fileNo, $applicationId, $now),
            'file_no' => $fileNo,
            'application_id' => $applicationId,
            'last_saved_by' => Auth::id(),
            'payload' => json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE),
            'rows_total' => count($rows),
            'rows_filled' => $filled,
            'status' => 'open',
            'last_saved_at' => $now,
            'updated_at' => $now,
        ];

        try {
            if ($existing) {
                DB::connection('sqlsrv')->table(self::TABLE)
                    ->where('id', $existing->id)
                    ->update($values);
            } else {
                DB::connection('sqlsrv')->table(self::TABLE)
                    ->insert($values + ['draft_key' => $draftKey, 'created_at' => $now]);
            }
        } catch (\Exception $e) {
            Log::error('draft save failed', [
                'trace_id' => $validated['client_trace_id'] ?? null,
                'draft_key' => $draftKey,
                'application_id' => $applicationId,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Draft could not be saved.',
            ], 500);
        }

        Log::info('draft saved', [
            'trace_id' => $validated['client_trace_id'] ?? null,
            'draft_key' => $draftKey,
            'application_id' => $applicationId,
            'rows_total' => count($rows),
            'rows_filled' => $filled,
            'is_new' => !$existing,
        ]);

        return response()->json([
            'success' => true,
            'draft_key' => $draftKey,
            'draft_name' => $values['draft_name'],
            'rows_total' => count($rows),
            'rows_filled' => $filled,
            'last_saved_at' => $now->toIso8601String(),
        ]);
    }

    /**
     * The open draft for an application, if there is one worth resuming.
     */
    public function show(int $applicationId): JsonResponse
    {
        if (!$this->tableExists()) {
            return response()->json(['success' => true, 'has_draft' => false]);
        }

        // Only drafts with work in them are offered — and the filter belongs in the
        // query, not after it: an application that also carries an empty draft (from
        // an older build, or a file number that changed under it) would otherwise
        // have its real work hidden behind the emptier, newer row.
        $draft = DB::connection('sqlsrv')->table(self::TABLE)
            ->where('application_id', $applicationId)
            ->where('status', 'open')
            ->where('rows_filled', '>', 0)
            ->orderByDesc('last_saved_at')
            ->first();

        if (!$draft) {
            return response()->json(['success' => true, 'has_draft' => false]);
        }

        $payload = json_decode($draft->payload ?? '', true);

        $savedByName = null;
        if ($draft->last_saved_by) {
            try {
                // No `name` column on this schema — the display name is the two
                // parts, with the username as the fallback for either being blank.
                $user = DB::connection('sqlsrv')->table('users')
                    ->where('id', $draft->last_saved_by)
                    ->select('first_name', 'last_name', 'username')
                    ->first();

                if ($user) {
                    $savedByName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                    $savedByName = $savedByName !== '' ? $savedByName : ($user->username ?? null);
                }
            } catch (\Exception $e) {
                // A missing name only costs the banner a word; never the restore.
                Log::warning('draft could not resolve last_saved_by name', [
                    'user_id' => $draft->last_saved_by,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'has_draft' => true,
            'draft_key' => $draft->draft_key,
            'draft_name' => $draft->draft_name,
            'rows' => $payload['rows'] ?? [],
            'rows_total' => (int) $draft->rows_total,
            'rows_filled' => (int) $draft->rows_filled,
            'last_saved_at' => $draft->last_saved_at,
            'last_saved_by' => $draft->last_saved_by,
            'last_saved_by_name' => $savedByName,
            // Whoever else may be keying this file, so a shared draft cannot be
            // silently overwritten without the officer being told.
            'is_own' => (int) $draft->last_saved_by === (int) Auth::id(),
        ]);
    }

    /**
     * Close a draft — either because its rows were saved, or the user threw it away.
     *
     * The row is kept (status only) so the log and the table agree on what happened
     * to a capture that someone may later say they lost.
     */
    public function close(Request $request): JsonResponse
    {
        if (!$this->tableExists()) {
            return response()->json(['success' => true]);
        }

        $validated = $request->validate([
            'application_id' => 'required|integer',
            'reason' => 'nullable|string|in:submitted,discarded',
        ]);

        $status = $validated['reason'] ?? 'discarded';

        $affected = DB::connection('sqlsrv')->table(self::TABLE)
            ->where('application_id', (int) $validated['application_id'])
            ->where('status', 'open')
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);

        Log::info('draft closed', [
            'application_id' => $validated['application_id'],
            'status' => $status,
            'rows_affected' => $affected,
        ]);

        return response()->json(['success' => true, 'status' => $status]);
    }

    /**
     * The file number is the draft's identity, so fall back to the record rather
     * than trusting whatever the page happened to render.
     */
    private function resolveFileNo(int $applicationId, ?string $supplied): ?string
    {
        $supplied = trim((string) $supplied);

        if ($supplied !== '' && strtoupper($supplied) !== 'N/A') {
            return $supplied;
        }

        try {
            $fileNo = DB::connection('sqlsrv')->table('mother_applications')
                ->where('id', $applicationId)
                ->value('fileno');

            return $fileNo ? trim($fileNo) : null;
        } catch (\Exception $e) {
            Log::warning('draft could not resolve file number', [
                'application_id' => $applicationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * One key per file. Case and ALL whitespace are stripped, so "COM-1991-46",
     * "com-1991 -46" and "COM-1991 - 46" cannot become three drafts for the one
     * file — which is the whole point of keying on the file number.
     *
     * Stripping spaces is safe here and only here: KANGIS file numbers legitimately
     * contain them ("KNML 1"), so this is a comparison key, never a file number to
     * display or store. file_no keeps the real thing.
     *
     * Applications with no file number yet fall back to the application id — still
     * one draft per application, which is the same guarantee.
     */
    private function draftKey(?string $fileNo, int $applicationId): string
    {
        $fileNo = strtoupper(preg_replace('/\s+/', '', (string) $fileNo));

        if ($fileNo === '' || $fileNo === 'N/A') {
            return 'APP-' . $applicationId;
        }

        return substr($fileNo, 0, 120);
    }

    /**
     * The label the resume banner shows: file number and the day it was last
     * touched. Display only — the key above is what keeps it to one draft.
     */
    private function draftName(?string $fileNo, int $applicationId, $when): string
    {
        // Tidied for reading, not normalised for matching: file numbers are shown
        // upper-case throughout the module, but the real spacing is kept.
        $label = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $fileNo)));

        if ($label === '') {
            $label = 'Application ' . $applicationId;
        }

        return substr($label . ' - ' . $when->format('d M Y'), 0, 190);
    }

    /**
     * Keep only the fields the form actually captures, as strings. A draft is not
     * validated, but it should not become a dumping ground for arbitrary keys.
     */
    private function sanitizeRows(array $rows): array
    {
        $allowed = [
            'buyerTitle', 'customTitle', 'firstName', 'middleName', 'surname',
            'landUse', 'unit_no', 'sectionNumber', 'unitMeasurement', 'cubicMeasurement',
        ];

        $clean = [];

        foreach (array_slice($rows, 0, 500) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $entry = [];
            foreach ($allowed as $field) {
                $entry[$field] = isset($row[$field]) ? substr(trim((string) $row[$field]), 0, 255) : '';
            }

            $clean[] = $entry;
        }

        return $clean;
    }

    /**
     * Rows with anything typed into them — what decides whether a draft is worth
     * restoring, and whether an empty autosave is allowed to overwrite one.
     */
    private function countFilledRows(array $rows): int
    {
        $filled = 0;

        foreach ($rows as $row) {
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    $filled++;
                    break;
                }
            }
        }

        return $filled;
    }

    private function tableExists(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }

        try {
            self::$tableExists = Schema::connection('sqlsrv')->hasTable(self::TABLE);
        } catch (\Exception $e) {
            Log::warning('draft table check failed', ['error' => $e->getMessage()]);
            self::$tableExists = false;
        }

        return self::$tableExists;
    }
}
