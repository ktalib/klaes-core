<?php

namespace App\Http\Controllers;

use App\Models\MissingFile;
use App\Models\FileTracker;
use App\Services\FileLocationResolver;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MissingFileController extends Controller
{
    /**
     * Display the Missing Files capture page.
     */
    public function index(Request $request)
    {
        $module = $request->get('url', '');

        $perPage = 25;
        // Found files drop out of the working list automatically — they are kept in the
        // table only for the audit trail (found_at / found_by), not shown here.
        $paginator = MissingFile::where('status', '!=', MissingFile::STATUS_FOUND)
            ->orderByDesc('id')
            ->paginate($perPage);

        $missingFiles = $paginator->items();
        $this->annotateTransit($missingFiles);
        $pagination = [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
        ];

        return view('missing_files.index', compact('module', 'missingFiles', 'pagination'));
    }

    /**
     * Return the missing files list as JSON (used to refresh the table).
     */
    public function data(Request $request)
    {
        $query = MissingFile::query();

        if ($status = trim((string) $request->get('status', ''))) {
            $query->where('status', $status);
        } else {
            // Default view hides Found files — once located they auto-drop from the list.
            $query->where('status', '!=', MissingFile::STATUS_FOUND);
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('file_number', 'like', '%' . $search . '%')
                  ->orWhere('file_title', 'like', '%' . $search . '%')
                  ->orWhere('full_label', 'like', '%' . $search . '%');
            });
        }

        $perPage = (int) $request->get('per_page', 25);
        $perPage = $perPage > 0 && $perPage <= 200 ? $perPage : 25;

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $items = $paginator->items();
        $this->annotateTransit($items);

        return response()->json([
            'success' => true,
            'data'    => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * Check whether a file number has already been captured as missing.
     */
    public function check(Request $request)
    {
        $fileNumber = trim((string) $request->get('file_number', ''));

        if ($fileNumber === '') {
            return response()->json(['success' => true, 'exists' => false]);
        }

        $existing = MissingFile::where('file_number', $fileNumber)->first();
        $transit  = $this->transitInfo($fileNumber);

        // Auto-detect the physical registry from config/file_ranges.php (prefix + year),
        // so the "Archive / Registry" dropdown pre-selects itself instead of relying on a
        // manual pick. Null when the file number matches no configured range.
        $range = app(FileLocationResolver::class)->matchRange($fileNumber);

        return response()->json([
            'success'         => true,
            'exists'          => (bool) $existing,
            'message'         => $existing ? $this->duplicateMessage($existing) : null,
            'data'            => $existing,
            'in_transit'      => $transit['in_transit'],
            'transit_location'=> $transit['location'],
            'transit_tracking_id' => $transit['tracking_id'],
            'registry'        => $range['registry'] ?? null,
            'zone'            => $range['zone'] ?? null,
        ]);
    }

    /**
     * Store a newly reported missing file.
     */
    public function store(Request $request)
    {
        $request->merge(['file_number' => trim((string) $request->input('file_number'))]);

        $validated = $request->validate([
            'file_number'       => [
                'required',
                'string',
                'max:100',
                Rule::unique('sqlsrv.missing_files', 'file_number'),
            ],
            'file_title'        => ['nullable', 'string', 'max:255'],
            'property_location' => ['nullable', 'string', 'max:255'],
            'tracking_id'       => ['nullable', 'string', 'max:100'],
            'archive_registry' => ['nullable', 'string', 'max:50'],
            'rack_primary'     => ['nullable', 'string', 'max:10'],
            'rack_secondary'   => ['nullable', 'string', 'max:10'],
            'shelf_number'     => ['nullable', 'string', 'max:10'],
            'full_label'       => ['nullable', 'string', 'max:30'],
            'remarks'          => ['nullable', 'string'],
        ], [
            'file_number.unique' => 'This file has already been recorded as missing.',
        ]);

        try {
            $user = Auth::user();

            // Normalize shelf location from full label (e.g. "A 12" -> "A12").
            $shelfLocation = $validated['full_label'] ?? null;
            if ($shelfLocation !== null) {
                $shelfLocation = strtoupper(str_replace(['-', ' ', '/', '\\'], '', trim($shelfLocation)));
                $shelfLocation = $shelfLocation === '' ? null : $shelfLocation;
            }

            $missing = MissingFile::create([
                'file_number'      => trim($validated['file_number']),
                'file_title'       => $validated['file_title'] ?? null,
                'property_location'=> $validated['property_location'] ?? null,
                'tracking_id'      => $validated['tracking_id'] ?? null,
                'archive_registry' => $validated['archive_registry'] ?? null,
                'rack_primary'     => $validated['rack_primary'] ?? null,
                'rack_secondary'   => $validated['rack_secondary'] ?? null,
                'shelf_number'     => $validated['shelf_number'] ?? null,
                'full_label'       => $validated['full_label'] ?? null,
                'shelf_location'   => $shelfLocation,
                'reported_by'      => $user->id ?? null,
                'reported_by_name' => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : null,
                'remarks'          => $validated['remarks'] ?? null,
                'status'           => MissingFile::STATUS_MISSING,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File recorded as missing.',
                'data'    => $missing,
            ], 201);
        } catch (QueryException $e) {
            // UQ_missing_files_file_number: another user captured the same file first.
            if (in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true)) {
                $existing = MissingFile::where('file_number', $validated['file_number'])->first();

                return response()->json([
                    'success'   => false,
                    'duplicate' => true,
                    'message'   => $existing
                        ? $this->duplicateMessage($existing)
                        : 'This file has already been recorded as missing.',
                ], 422);
            }

            throw $e;
        } catch (\Throwable $e) {
            Log::error('MissingFileController::store failed', [
                'file_number' => $validated['file_number'] ?? null,
                'message'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record the missing file.',
            ], 500);
        }
    }

    /**
     * Flag each missing-file record with its live file_tracker state. A file that is
     * logged out (has a non-terminal tracker) is In-Transit — physically checked out to
     * another office, not truly lost — so the list can distinguish it from a real
     * missing file. Batched to a single tracker query to avoid an N+1 over the page.
     *
     * @param  array|\Illuminate\Support\Collection  $records
     */
    private function annotateTransit($records): void
    {
        $records = collect($records);
        $fileNumbers = $records->pluck('file_number')
            ->filter(fn ($n) => trim((string) $n) !== '')
            ->unique()
            ->values();

        if ($fileNumbers->isEmpty()) {
            return;
        }

        $resolver = app(FileLocationResolver::class);

        // Latest tracker per file number (mirrors FileLocationResolver::findTracker).
        $latest = FileTracker::whereIn('file_number', $fileNumbers->all())
            ->orderByDesc('id')
            ->get()
            ->groupBy('file_number')
            ->map(fn ($group) => $group->first());

        foreach ($records as $rec) {
            $tracker   = $latest->get($rec->file_number);
            $inTransit = $tracker
                && !$resolver->isTerminalTrackerStatus($tracker->status)
                && !empty($tracker->movement_log);

            $rec->in_transit          = (bool) $inTransit;
            $rec->transit_location    = $inTransit ? $this->transitHolderLabel($tracker) : null;
            $rec->transit_tracking_id = $inTransit ? $tracker->tracking_id : null;
        }
    }

    /**
     * "Department - Officer" label for the office currently holding a logged-out file.
     * Falls back gracefully when either side is missing.
     */
    private function transitHolderLabel(FileTracker $tracker): ?string
    {
        // The current holder / office is most reliably read off the active movement,
        // falling back to the tracker columns when the log doesn't carry it.
        $movement = method_exists($tracker, 'getCurrentMovement') ? $tracker->getCurrentMovement() : null;
        $movement = is_array($movement) ? $movement : [];

        $office = trim((string) (
            ($movement['receiving_office_name'] ?? null)
            ?: $tracker->current_office_name
            ?: $tracker->receiving_office_name
            ?: $tracker->destination
        ));

        $officer = trim((string) (
            ($movement['receiving_officer_name'] ?? null)
            ?: $tracker->receiving_officer_name
        ));

        if ($office !== '' && $officer !== '') {
            return $office . ' - ' . $officer;
        }

        return $office !== '' ? $office : ($officer !== '' ? $officer : null);
    }

    /**
     * Resolve the live in-transit state for a single file number.
     *
     * @return array{in_transit: bool, location: ?string, tracking_id: ?string}
     */
    private function transitInfo(string $fileNumber): array
    {
        $none = ['in_transit' => false, 'location' => null, 'tracking_id' => null];

        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return $none;
        }

        $tracker = FileTracker::where('file_number', $fileNumber)
            ->orderByDesc('id')
            ->first();

        if (! $tracker) {
            return $none;
        }

        $resolver = app(FileLocationResolver::class);
        if ($resolver->isTerminalTrackerStatus($tracker->status) || empty($tracker->movement_log)) {
            return $none;
        }

        return [
            'in_transit'  => true,
            'location'    => $this->transitHolderLabel($tracker),
            'tracking_id' => $tracker->tracking_id,
        ];
    }

    /**
     * Human-readable message for an already-captured file number.
     */
    private function duplicateMessage(MissingFile $existing): string
    {
        $when = optional($existing->created_at)->format('d M Y');
        $who  = $existing->reported_by_name;

        $message = '"' . $existing->file_number . '" has already been recorded as missing';
        if ($who)  { $message .= ' by ' . $who; }
        if ($when) { $message .= ' on ' . $when; }

        return $message . ' (status: ' . ($existing->status ?: 'Missing') . ').';
    }

    /**
     * Mark a missing file as found.
     */
    public function markFound(Request $request, $id)
    {
        $missing = MissingFile::find($id);

        if (! $missing) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $user = Auth::user();

        $missing->update([
            'status'        => MissingFile::STATUS_FOUND,
            'found_at'      => now(),
            'found_by'      => $user->id ?? null,
            'found_by_name' => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File marked as found.',
            'data'    => $missing,
        ]);
    }

    /**
     * Delete a missing file record.
     */
    public function destroy($id)
    {
        $missing = MissingFile::find($id);

        if (! $missing) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }

        $missing->delete();

        return response()->json(['success' => true, 'message' => 'Record deleted.']);
    }
}
