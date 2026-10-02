<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\AllocationListEntry;
use App\Models\User;

/**
 * AllocationListEntryController
 *
 * Handles CRUD operations for the Allocation List (allocation_list_stage table).
 * Supports:
 *  - Listing entries (DataTable-ready)
 *  - Capturing an EXISTING allocation (file number + name + location + year)
 *  - File number lookup, which backfills file title and location
 *  - Editing / updating an entry
 *  - Deleting an entry
 *  - Row info lookup (fetchrowinfo) for edit modal
 *  - CSV import (max 100 records)
 *  - CSV template download
 */
class AllocationListEntryController extends Controller
{
    /** The field names read badly on the form ("the file no field"). */
    private const VALIDATION_MESSAGES = [
        'file_no.required'       => 'File number is required.',
        'allottee_name.required' => 'Name is required.',
        'allocation_year.digits' => 'Year must be a 4-digit year.',
    ];

    // ─── View ────────────────────────────────────────────────────────────────

    /**
     * Return the main index view with data needed to populate form dropdowns.
     */
    public function index(Request $request)
    {
        $pageTitle = __('Allocation List');
        $pageDescription = __('Capture and track existing land allocations');

        // Location builder reference data. Districts are free text backed by a
        // datalist — the registries hold values outside this list and a strict
        // <select> would silently drop them on backfill.
        $districts = DB::connection('sqlsrv')
            ->table('districts')
            ->where('is_active', 1)
            ->orderBy('name')
            ->pluck('name');

        $lgas = DB::connection('sqlsrv')
            ->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->pluck('StatLGAs.LGAName');

        return view('allocation_list_entry.index', compact('districts', 'lgas', 'pageTitle', 'pageDescription'));
    }

    // ─── AJAX: all entries as JSON ────────────────────────────────────────────

    /**
     * Return all allocation list entries as JSON (for DataTable client-side).
     */
    public function getAllEntries(Request $request)
    {
        try {
            $query = AllocationListEntry::query();

            $records = $query->orderBy('created_at', 'desc')->get();

            // created_by holds a user id; the table shows who captured the row.
            $names = User::whereIn('id', $records->pluck('created_by')->filter()->unique())
                ->get(['id', 'first_name', 'last_name'])
                ->mapWithKeys(fn ($u) => [(string) $u->id => trim($u->first_name . ' ' . $u->last_name)]);

            $records->each(function ($record) use ($names) {
                $record->created_by_name = $names[(string) $record->created_by] ?? null;
            });

            return response()->json([
                'success' => true,
                'data'    => $records,
                'total'   => $records->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry getAllEntries: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── API: single row info (for edit modal) ────────────────────────────────

    /**
     * Fetch info for a single row – used by the AllocationListEntryApi and
     * called directly via the route /allocation-list/fetchrowinfo/{id}.
     */
    public function fetchrowinfo($id)
    {
        try {
            $record = AllocationListEntry::find($id);

            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found'], 404);
            }

            return response()->json(['success' => true, 'data' => $record]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry fetchrowinfo: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Dashboard ────────────────────────────────────────────────────────────

    /**
     * Headline numbers plus the two breakdowns the dashboard draws as bar lists.
     *
     * Aggregated in SQL rather than by pulling the rows down — the list grows
     * without bound and the page asks for this on every save.
     */
    public function stats()
    {
        try {
            $table = fn() => AllocationListEntry::query();

            $hasFileNo = fn($q) => $q->whereNotNull('file_no')
                ->whereRaw("LTRIM(RTRIM(file_no)) <> ''");

            $byYear = $table()
                ->whereNotNull('allocation_year')
                ->whereRaw("LTRIM(RTRIM(allocation_year)) <> ''")
                ->groupBy('allocation_year')
                ->selectRaw('allocation_year as label, COUNT(*) as value')
                ->orderByDesc('allocation_year')
                ->limit(8)
                ->get();

            $byLga = $table()
                ->whereNotNull('lga')
                ->whereRaw("LTRIM(RTRIM(lga)) <> ''")
                ->groupBy('lga')
                ->selectRaw('lga as label, COUNT(*) as value')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->limit(8)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'today'      => $table()->where('created_at', '>=', now()->startOfDay())->count(),
                    'total'      => $table()->count(),
                    'captured'   => $hasFileNo($table())->count(),
                    'this_month' => $table()->where('created_at', '>=', now()->startOfMonth())->count(),
                    'by_year'    => $byYear,
                    'by_lga'     => $byLga,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry stats: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Existing allocation capture ──────────────────────────────────────────

    /**
     * Resolve a file number into the details the capture form backfills:
     * file title, location and the year encoded in the number itself.
     */
    public function lookupFile(Request $request)
    {
        $fileNo = strtoupper(trim((string) $request->get('file_no', '')));

        if ($fileNo === '') {
            return response()->json(['success' => false, 'message' => 'File number is required.'], 422);
        }

        try {
            $details = $this->resolveFileDetails($fileNo);

            return response()->json([
                'success' => true,
                'found'   => $details['found'],
                // Warn at selection time rather than letting the operator fill
                // the whole form before the save rejects it.
                'already_captured' => $this->existingCaptureId($fileNo) !== null,
                'data'    => [
                    'file_no'         => $fileNo,
                    'file_title'      => $details['file_title'],
                    // The location-builder parts, plus the composed value the
                    // form shows (falls back to the raw registry string when the
                    // registry holds no parts to build from).
                    'district'        => $details['district'],
                    'lga'             => $details['lga'],
                    'location'        => $details['location'],
                    'plot_number'     => $details['plot_no'],
                    'allocation_year' => $this->detectYearFromFileNumber($fileNo),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry lookupFile: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Capture one existing allocation. Saved one at a time over AJAX so the
     * operator can keep the form open and keep entering.
     */
    public function storeExisting(Request $request)
    {
        try {
            $validated = $request->validate([
                'file_no'         => 'required|string|max:100',
                'allottee_name'   => 'required|string|max:255',
                'file_title'      => 'nullable|string|max:255',
                'district'        => 'nullable|string|max:100',
                'lga'             => 'nullable|string|max:100',
                'location'        => 'nullable|string|max:255',
                'plot_number'     => 'nullable|string|max:50',
                'allocation_year' => 'nullable|digits:4',
            ], self::VALIDATION_MESSAGES);

            $fileNo = strtoupper(trim($validated['file_no']));
            $name   = $this->normalizeName($validated['allottee_name']);

            // One allocation per file number.
            if ($this->existingCaptureId($fileNo) !== null) {
                return response()->json([
                    'success' => false,
                    'message' => "{$fileNo} is already in the allocation list.",
                    'duplicate' => true,
                ], 422);
            }

            $fileTitle  = strtoupper(trim((string) ($validated['file_title'] ?? '')));
            $plotNumber = strtoupper(trim((string) ($validated['plot_number'] ?? '')));
            $parts      = $this->locationPartsFrom($validated);
            $location   = $this->composeLocation($parts)
                ?? strtoupper(trim((string) ($validated['location'] ?? '')));

            // The form backfills these on selection, but a lookup that failed —
            // or a file picked before the backfill landed — still deserves
            // whatever we can resolve here.
            if ($fileTitle === '' || $location === '' || $plotNumber === '') {
                $details = $this->resolveFileDetails($fileNo);
                $fileTitle = $fileTitle !== '' ? $fileTitle : strtoupper((string) $details['file_title']);
                $plotNumber = $plotNumber !== '' ? $plotNumber : strtoupper((string) $details['plot_no']);

                if ($location === '') {
                    $parts = [
                        'district' => $parts['district'] ?: strtoupper((string) $details['district']),
                        'lga'      => $parts['lga'] ?: strtoupper((string) $details['lga']),
                    ];
                    $location = $this->composeLocation($parts) ?? strtoupper((string) $details['location']);
                }
            }

            // The year is derived from the number, so only trust a supplied value
            // when the number itself yields nothing to derive.
            $year = $this->detectYearFromFileNumber($fileNo) ?? ($validated['allocation_year'] ?? null);

            $nameParts = $this->splitName($name);

            $record = AllocationListEntry::create([
                'file_no'         => $fileNo,
                'file_title'      => $fileTitle ?: null,
                'allottee_name'   => $name,
                // The builder parts are kept alongside the composed string so the
                // row can be re-edited without re-parsing it.
                'district'        => $parts['district'] ?: null,
                'lga'             => $parts['lga'] ?: null,
                'state'           => 'KANO',
                'location'        => $location ?: null,
                'plot_number'     => $plotNumber ?: null,
                'allocation_year' => $year,
                // Keep the legacy name columns populated so the MLS generator and
                // the CSV exports keep reading these rows.
                'first_name'      => $nameParts['first_name'],
                'middle_name'     => $nameParts['middle_name'],
                'last_name'       => $nameParts['last_name'],
                // These rows describe allocations that already happened, so they
                // must never be offered up again as unallocated.
                'is_allocated'    => 1,
                'created_by'      => Auth::id(),
                'updated_by'      => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Allocation for {$fileNo} saved.",
                'data'    => $record->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry storeExisting: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Pull the file title and location for a file number out of the registries.
     *
     * dbo.fileNumber first (the canonical register), then file_indexings, then
     * st_file_numbers — each one only filling in what the previous left blank.
     *
     * The location comes back both as its parts (for the builder fields) and as
     * a composed string. Registries that only hold a free-text location and no
     * parts fall back to that raw string, which is why both are returned.
     *
     * @return array{found:bool, file_title:?string, location:?string,
     *               plot_no:?string, district:?string, lga:?string}
     */
    private function resolveFileDetails(string $fileNo): array
    {
        $fileNo   = trim($fileNo);
        $found    = false;
        $title    = null;
        $rawLocation = null;
        $plotNo   = null;
        $district = null;
        $lga      = null;

        $empty = [
            'found' => false, 'file_title' => null, 'location' => null,
            'plot_no' => null, 'district' => null, 'lga' => null,
        ];

        if ($fileNo === '') {
            return $empty;
        }

        // A temporary number is stored without its "(T)" marker in some columns,
        // so search for both forms. See the temp-number lookup notes on the
        // Legal Search side — a literal-only match misses silently.
        $variants = array_values(array_unique(array_filter([
            $fileNo,
            trim((string) preg_replace('/\(\s*T\s*\)\s*$/i', '', $fileNo)),
        ], fn($v) => $v !== '')));

        $matchAny = function ($query, array $columns) use ($variants) {
            return $query->where(function ($q) use ($columns, $variants) {
                foreach ($columns as $column) {
                    foreach ($variants as $value) {
                        $q->orWhereRaw("{$column} = CAST(? AS VARCHAR(255))", [$value]);
                    }
                }
            });
        };

        // Everything resolved so far, so each source knows what is still missing.
        $incomplete = fn() => $title === null || $plotNo === null
            || $district === null || $lga === null;

        try {
            $row = $matchAny(
                DB::connection('sqlsrv')->table('fileNumber')
                    ->select('FileName', 'location', 'plot_no', 'district', 'lga'),
                ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'temp_file_no']
            )->orderByDesc('id')->first();

            if ($row) {
                $found       = true;
                $title       = $this->blankToNull($row->FileName);
                $rawLocation = $this->blankToNull($row->location);
                $plotNo      = $this->blankToNull($row->plot_no);
                $district    = $this->blankToNull($row->district);
                $lga         = $this->blankToNull($row->lga);
            }
        } catch (\Exception $e) {
            Log::warning('AllocationListEntry resolveFileDetails (fileNumber): ' . $e->getMessage());
        }

        if ($incomplete()) {
            try {
                $row = $matchAny(
                    DB::connection('sqlsrv')->table('file_indexings')
                        ->select('file_title', 'district', 'lga', 'plot_number'),
                    ['file_number', 'temp_file_no', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no']
                )->orderByDesc('id')->first();

                if ($row) {
                    $found    = true;
                    $title    = $title    ?? $this->blankToNull($row->file_title);
                    $plotNo   = $plotNo   ?? $this->blankToNull($row->plot_number);
                    $district = $district ?? $this->blankToNull($row->district);
                    $lga      = $lga      ?? $this->blankToNull($row->lga);
                }
            } catch (\Exception $e) {
                Log::warning('AllocationListEntry resolveFileDetails (file_indexings): ' . $e->getMessage());
            }
        }

        if ($incomplete()) {
            try {
                $row = DB::connection('sqlsrv')->table('st_file_numbers')
                    ->whereIn('fileno', $variants)
                    ->orderByDesc('id')
                    ->first();

                if ($row) {
                    $found = true;
                    $title = $title ?? $this->blankToNull(
                        $row->corporate_name
                            ?: $this->joinNonEmpty([$row->first_name ?? null, $row->surname ?? null], ' ')
                    );
                    $plotNo   = $plotNo   ?? $this->blankToNull($row->property_plot_no ?? null);
                    $district = $district ?? $this->blankToNull($row->property_district ?? null);
                    $lga      = $lga      ?? $this->blankToNull($row->property_lga ?? null);
                }
            } catch (\Exception $e) {
                Log::warning('AllocationListEntry resolveFileDetails (st_file_numbers): ' . $e->getMessage());
            }
        }

        $parts = $this->locationPartsFrom([
            'district' => $district,
            'lga'      => $lga,
        ]);

        return [
            'found'      => $found,
            'file_title' => $title,
            // Resolved but not part of the location while Plot No is out of the
            // builder — kept so reinstating the field needs no new plumbing.
            'plot_no'    => $plotNo,
            'district'   => $district,
            'lga'        => $lga,
            // Built from the parts when there are any; otherwise the registry's
            // own free-text location string.
            'location'   => $this->composeLocation($parts) ?? $rawLocation,
        ];
    }

    /**
     * Read the allocation year out of a file number, e.g. RES-1982-2081 → 1982.
     *
     * Takes the leftmost plausible 4-digit group so a serial that happens to
     * look like a year (…-2081) never wins over the real one. Mirrored by
     * aleDetectYear() in public/js/allocation_list_entry.js.
     */
    private function detectYearFromFileNumber(?string $fileNo): ?string
    {
        $fileNo = trim((string) $fileNo);
        if ($fileNo === '') {
            return null;
        }

        $maxYear = (int) date('Y') + 1;

        foreach (preg_split('/[^0-9]+/', $fileNo) as $group) {
            if (strlen($group) !== 4) {
                continue;
            }
            $year = (int) $group;
            if ($year >= 1900 && $year <= $maxYear) {
                return $group;
            }
        }

        return null;
    }

    /**
     * The location-builder inputs, upper-cased and trimmed.
     *
     * Plot No is out of the builder for now, so the location is district + LGA.
     *
     * @return array{district:string, lga:string}
     */
    private function locationPartsFrom(array $input): array
    {
        return [
            'district' => strtoupper(trim((string) ($input['district'] ?? ''))),
            'lga'      => strtoupper(trim((string) ($input['lga'] ?? ''))),
        ];
    }

    /**
     * Build the location string out of district and LGA, e.g. "ADAKAWA, GAYA".
     * Null when there is nothing to build from, so callers can fall back to
     * whatever raw location they already hold.
     *
     * Mirrored by aleComposeLocation() in public/js/allocation_list_entry.js —
     * the form previews exactly what gets stored.
     */
    private function composeLocation(array $parts): ?string
    {
        return $this->joinNonEmpty([$parts['district'], $parts['lga']]);
    }

    /**
     * A file number may only be captured once. Returns the id of the row that
     * already holds it, ignoring the row being edited.
     */
    private function existingCaptureId(string $fileNo, $ignoreId = null): ?int
    {
        $fileNo = trim($fileNo);
        if ($fileNo === '') {
            return null;
        }

        // Default CI collation makes this case- and trailing-space-insensitive.
        $id = AllocationListEntry::where('file_no', $fileNo)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Upper-case the captured name and collapse the runs of whitespace that
     * come with hand-keyed entry.
     */
    private function normalizeName(string $name): string
    {
        return strtoupper(trim((string) preg_replace('/\s+/', ' ', $name)));
    }

    /**
     * Best-effort split of a single free-text name into the legacy columns.
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return [
            'first_name'  => $parts[0] ?? null,
            'middle_name' => count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : null,
            'last_name'   => count($parts) > 1 ? end($parts) : null,
        ];
    }

    private function blankToNull($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function joinNonEmpty(array $values, string $glue = ', '): ?string
    {
        $filtered = array_filter(array_map(fn($v) => trim((string) $v), $values), fn($v) => $v !== '');
        return empty($filtered) ? null : implode($glue, $filtered);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    /**
     * Insert a new allocation list entry.
     */
    public function store(Request $request)
    {
        try {
            // Support both single entry or multiple entries
            $entries = [];
            
            if ($request->has('entries')) {
                // Bulk entries from the dynamic form
                $entries = $request->input('entries');
            } else {
                // Conventional single entry
                $entries[] = $request->all();
            }

            $inserted = 0;
            $errors = [];

            foreach ($entries as $index => $data) {
                // Basic validation for each entry
                if (empty($data['first_name']) || empty($data['last_name'])) {
                    $errors[] = "Entry #".($index + 1).": First Name and Last Name are required.";
                    continue;
                }

                // Construct full address if not provided
                $addressParts = [
                    !empty($data['plot_number']) ? "PLOT " . strtoupper(trim($data['plot_number'])) : '',
                    strtoupper(trim($data['district'] ?? '')),
                    strtoupper(trim($data['lga'] ?? '')),
                    strtoupper(trim($data['state'] ?? 'KANO'))
                ];
                $allotteeAddress = strtoupper(trim($data['allottee_address'] ?? ''));
                if (empty($allotteeAddress)) {
                    $allotteeAddress = implode(', ', array_filter($addressParts));
                }

                AllocationListEntry::create([
                    'title'        => strtoupper(trim($data['title'] ?? '')),
                    'first_name'   => strtoupper(trim($data['first_name'])),
                    'middle_name'  => strtoupper(trim($data['middle_name'] ?? '')),
                    'last_name'    => strtoupper(trim($data['last_name'])),
                    'plot_number'  => strtoupper(trim($data['plot_number'] ?? '')),
                    'district'     => strtoupper(trim($data['district'] ?? '')),
                    'lga'          => $data['lga'] ?? null,
                    'state'        => $data['state'] ?? 'KANO',
                    'allottee_address' => $allotteeAddress,
                    'created_by'   => Auth::id(),
                    'updated_by'   => Auth::id(),
                ]);
                $inserted++;
            }

            if ($inserted === 0 && !empty($errors)) {
                return response()->json(['success' => false, 'message' => implode(' ', $errors)], 422);
            }

            $message = $inserted > 1 ? "$inserted entries added successfully." : "Entry added successfully.";
            return response()->json(['success' => true, 'message' => $message]);

        } catch (\Exception $e) {
            Log::error('AllocationListEntry store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    /**
     * Update an existing allocation list entry.
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'file_no'         => 'required|string|max:100',
                'allottee_name'   => 'required|string|max:255',
                'file_title'      => 'nullable|string|max:255',
                'district'        => 'nullable|string|max:100',
                'lga'             => 'nullable|string|max:100',
                'location'        => 'nullable|string|max:255',
                'plot_number'     => 'nullable|string|max:50',
                'allocation_year' => 'nullable|digits:4',
            ], self::VALIDATION_MESSAGES);

            $record = AllocationListEntry::find($id);
            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }

            $fileNo    = strtoupper(trim($validated['file_no']));

            // One allocation per file number — but this row may keep its own.
            if ($this->existingCaptureId($fileNo, $id) !== null) {
                return response()->json([
                    'success' => false,
                    'message' => "{$fileNo} is already in the allocation list.",
                    'duplicate' => true,
                ], 422);
            }

            $name      = $this->normalizeName($validated['allottee_name']);
            $nameParts = $this->splitName($name);
            $parts     = $this->locationPartsFrom($validated);
            $location  = $this->composeLocation($parts)
                ?? strtoupper(trim((string) ($validated['location'] ?? '')));

            $record->update([
                'file_no'         => $fileNo,
                'allottee_name'   => $name,
                'file_title'      => $this->blankToNull(strtoupper((string) ($validated['file_title'] ?? ''))),
                'district'        => $this->blankToNull($parts['district']),
                'lga'             => $this->blankToNull($parts['lga']),
                'location'        => $this->blankToNull($location),
                'plot_number'     => $this->blankToNull(strtoupper((string) ($validated['plot_number'] ?? ''))),
                'allocation_year' => $this->detectYearFromFileNumber($fileNo) ?? ($validated['allocation_year'] ?? null),
                'first_name'      => $nameParts['first_name'],
                'middle_name'     => $nameParts['middle_name'],
                'last_name'       => $nameParts['last_name'],
                'updated_by'      => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Entry updated successfully.',
                'data'    => $record->fresh(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    /**
     * Delete an allocation list entry.
     */
    public function destroy($id)
    {
        try {
            $record = AllocationListEntry::find($id);
            if (!$record) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }
            
            $deleted = $record->delete();

            if (!$deleted) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Entry deleted successfully.']);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry destroy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── CSV Import ───────────────────────────────────────────────────────────

    /**
     * Import bulk allocation entries from a CSV file (max 100 records).
     */
    public function importCsv(Request $request)
    {
        try {
            $request->validate([
                'records'   => 'required|array|min:1|max:100',
                'records.*' => 'array',
            ]);

            $records = $request->input('records');

            if (count($records) > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import limit is 100 records per batch.',
                ], 422);
            }

            $inserted = 0;
            $skipped  = 0;

            foreach ($records as $row) {
                $firstName = strtoupper(trim($row['first_name'] ?? ''));
                $lastName  = strtoupper(trim($row['last_name'] ?? ''));

                if (empty($firstName) || empty($lastName)) {
                    $skipped++;
                    continue;
                }

                AllocationListEntry::create([
                    'title'        => strtoupper(trim($row['title'] ?? '')),
                    'first_name'   => $firstName,
                    'middle_name'  => strtoupper(trim($row['middle_name'] ?? '')),
                    'last_name'    => $lastName,
                    'plot_number'  => strtoupper(trim($row['plot_number'] ?? '')),
                    'district'     => strtoupper(trim($row['district'] ?? '')),
                    'lga'          => $row['lga'] ?? null,
                    'state'        => $row['state'] ?? 'Kano',
                    'allottee_address' => strtoupper(trim($row['allottee_address'] ?? '')),
                    'created_by'   => Auth::id(),
                    'updated_by'   => Auth::id(),
                ]);

                $inserted++;
            }

            $message = "$inserted record(s) imported.";
            if ($skipped > 0) {
                $message .= " $skipped record(s) skipped (missing name).";
            }

            return response()->json(['success' => true, 'message' => $message, 'inserted' => $inserted, 'skipped' => $skipped]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry importCsv: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Report Export ────────────────────────────────────────────────────────

    /**
     * Rows for the Allocation List register export (preview, CSV and PDF).
     *
     * Mirrors the instrument capture register export: the client asks for a
     * filtered set, renders it as a preview, then builds the PDF from the very
     * same array it previewed — so what is on screen is what lands in the file.
     */
    public function exportData(Request $request)
    {
        try {
            $query = AllocationListEntry::query();

            if (($year = trim((string) $request->query('year', ''))) !== '') {
                $query->where('allocation_year', $year);
            }
            if (($lga = trim((string) $request->query('lga', ''))) !== '') {
                $query->where('lga', $lga);
            }
            if (($district = trim((string) $request->query('district', ''))) !== '') {
                $query->where('district', $district);
            }
            if (($start = trim((string) $request->query('start_date', ''))) !== '') {
                $query->whereDate('created_at', '>=', $start);
            }
            if (($end = trim((string) $request->query('end_date', ''))) !== '') {
                $query->whereDate('created_at', '<=', $end);
            }

            // Register order: year, then LGA, then the file number itself, so a
            // printed page reads the way the paper register does. Rows with no
            // year (bare plot rows) sort last instead of leading the report.
            $records = $query
                ->orderByRaw('CASE WHEN allocation_year IS NULL THEN 1 ELSE 0 END')
                ->orderBy('allocation_year')
                ->orderBy('lga')
                ->orderBy('file_no')
                ->orderBy('id')
                ->get();

            // created_by holds a user id; the register names who captured the row.
            $names = User::whereIn('id', $records->pluck('created_by')->filter()->unique())
                ->get(['id', 'first_name', 'last_name'])
                ->mapWithKeys(fn ($u) => [(string) $u->id => trim($u->first_name . ' ' . $u->last_name)]);

            $data = $records->map(function ($r) use ($names) {
                $captured = $r->created_at;

                return [
                    'id'              => $r->id,
                    'file_no'         => $r->file_no,
                    'file_title'      => $r->file_title,
                    'allottee_name'   => $r->allottee_name,
                    'plot_number'     => $r->plot_number,
                    'district'        => $r->district,
                    'lga'             => $r->lga,
                    'state'           => $r->state,
                    'location'        => $r->location,
                    'allocation_year' => $r->allocation_year,
                    'captured_on'     => $captured instanceof \DateTimeInterface
                        ? $captured->format('d/m/Y')
                        : (string) $captured,
                    'captured_by'     => $names[(string) $r->created_by] ?? null,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data'    => $data,
                'total'   => $data->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry exportData: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * The distinct years, LGAs and districts actually present in the table, so
     * the export filters only ever offer values that can return rows.
     */
    public function exportFilters()
    {
        try {
            return response()->json([
                'success'   => true,
                'years'     => AllocationListEntry::whereNotNull('allocation_year')
                                    ->distinct()->orderByDesc('allocation_year')->pluck('allocation_year'),
                'lgas'      => AllocationListEntry::whereNotNull('lga')
                                    ->distinct()->orderBy('lga')->pluck('lga'),
                'districts' => AllocationListEntry::whereNotNull('district')
                                    ->distinct()->orderBy('district')->pluck('district'),
            ]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry exportFilters: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─── CSV Template Download ────────────────────────────────────────────────

    /**
     * Download a CSV template for bulk import.
     */
    public function downloadTemplate()
    {
        $headers = ['title', 'first_name', 'middle_name', 'last_name'];

        $titles       = ['Alhaji', 'Hajiya', 'Mallam', 'Malama', 'Engr', 'Dr', 'Alhajiya'];
        $firstNames   = [
            'Musa', 'Amina', 'Sani', 'Zainab', 'Yakubu', 'Hauwa', 'Bashir', 'Hadiza',
            'Umar', 'Fatima', 'Abubakar', 'Maryam', 'Garba', 'Aisha', 'Kabiru', 'Ramatu',
            'Aminu', 'Binta', 'Aliyu', 'Jummai', 'Dauda', 'Safiya', 'Ibrahim', 'Halima',
            'Yusuf', 'Rabi', 'Haruna', 'Bilkisu', 'Bello', 'Hafsat', 'Rabiu', 'Rukayya',
            'Tukur', 'Kaltumi', 'Lawal', 'Suwaiba', 'Isah', 'Zulaiha', 'Hamisu', 'Firdausi',
            'Sulaiman', 'Sajida', 'Shehu', 'Baraka', 'Nuhu', 'Nana', 'Adamu', 'Asiya',
            'Halliru', 'Nusaiba',
        ];
        $midInitials  = ['A', 'B', 'D', 'F', 'G', 'H', 'I', 'K', 'L', 'M', 'N', 'R', 'S', 'T', 'U', 'Y', ''];
        $lastNames    = [
            'Ibrahim', 'Ahmed', 'Hassan', 'Muhammad', 'Abdullahi', 'Garba', 'Saleh', 'Lawal',
            'Usman', 'Abubakar', 'Bello', 'Suleiman', 'Musa', 'Sulaiman', 'Danjuma', 'Rabiu',
            'Aliyu', 'Maigari', 'Bayero', 'Wada', 'Ringim', 'Gaya', 'Tofa', 'Ungogo',
            'Kura', 'Gezawa', 'Tanko', 'Zango', 'Chiroma', 'Makama',
        ];
        $sample = [];
        for ($i = 0; $i < 100; $i++) {
            $sample[] = [
                $titles[$i % count($titles)],
                $firstNames[$i % count($firstNames)],
                $midInitials[$i % count($midInitials)],
                $lastNames[$i % count($lastNames)],
            ];
        }

        $filename = 'allocation_list_template_' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($headers, $sample) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
            fputcsv($f, $headers);
            foreach ($sample as $row) {
                fputcsv($f, $row);
            }
            fclose($f);
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get all unallocated entries (is_allocated = 0) for the MLS Generator dropdown.
     */
    public function getUnallocatedEntries()
    {
        try {
            // Nameless rows are skipped for the same reason the MLS generator
            // skips them: the caller is choosing an allottee, and a bare plot
            // row has no allottee to choose. See FileNumberController::index().
            $entries = AllocationListEntry::where('is_allocated', 0)
                ->where(function ($q) {
                    $q->where(fn ($n) => $n->whereNotNull('allottee_name')->where('allottee_name', '<>', ''))
                      ->orWhere(fn ($n) => $n->whereNotNull('first_name')->where('first_name', '<>', ''));
                })
                // An entry that already carries a file number has nothing for
                // the generator to mint. Same rule as FileNumberController::index().
                ->where(fn ($q) => $q->whereNull('file_no')->orWhere('file_no', ''))
                ->orderBy('first_name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $entries
            ]);
        } catch (\Exception $e) {
            Log::error('AllocationListEntry getUnallocatedEntries: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
