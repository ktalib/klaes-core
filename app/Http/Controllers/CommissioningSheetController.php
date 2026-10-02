<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use App\Services\KangisLandPairService;
use Carbon\Carbon;

class CommissioningSheetController extends Controller
{
    /**
     * Store a new commissioning sheet
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_number' => 'required|string|max:255',
                'file_name' => 'nullable|string|max:500',
                'name_or_allottee' => 'nullable|string|max:500',
                'plot_number' => 'nullable|string|max:255',
                'tp_number' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:500',
                'lga' => 'nullable|string|max:100',
                'date_created' => 'nullable|date',
                'created_by' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['created_user_id'] = Auth::id();
            $data['status'] = 'Draft';
            $data['created_at'] = now();
            $data['updated_at'] = now();

            // Insert into database
            $id = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->insertGetId($data);

            return response()->json([
                'success' => true,
                'message' => 'Commissioning sheet saved successfully',
                'data' => ['id' => $id]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error saving commissioning sheet: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save commissioning sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fill blank property fields from the file's indexing record.
     *
     * An ST primary keeps its location in file_indexings — only unit files store it
     * on the ST row — so a sheet printed straight off the ST table has no Location,
     * TP No, Plot No or LGA to send. Only empty fields are touched, so a caller that
     * supplied its own values (MLS, OSS) is never overridden.
     *
     * @param array $data Sheet payload keyed like file_commissioning_sheets.
     */
    private function fillPropertyFieldsFromIndexing(array $data): array
    {
        $fileNo = trim((string) ($data['file_number'] ?? ''));
        if ($fileNo === '') {
            return $data;
        }

        $missing = empty($data['location']) || empty($data['tp_number'])
            || empty($data['plot_number']) || empty($data['lga']);
        if (!$missing) {
            return $data;
        }

        $indexing = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where(function ($q) use ($fileNo) {
                $q->where('file_number', $fileNo)->orWhere('mls_file_no', $fileNo);
            })
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderByDesc('id')
            ->first();

        if (!$indexing) {
            return $data;
        }

        if (empty($data['location'])) {
            // District and LGA only — the street name belongs to the address, not to
            // the Location line on this sheet. The LGA is upper-cased to match the
            // district, which is stored that way. Stored location is the last resort.
            $data['location'] = implode(', ', array_filter([
                trim((string) ($indexing->district ?? '')),
                mb_strtoupper(trim((string) ($indexing->lga ?? ''))),
            ])) ?: ($indexing->location ?: null);
        }
        if (empty($data['plot_number'])) {
            $data['plot_number'] = $indexing->plot_number ?: null;
        }
        if (empty($data['lga'])) {
            $data['lga'] = $indexing->lga ?: null;
        }

        // file_indexings holds no TP number — that lives on the MLS record.
        if (empty($data['tp_number'])) {
            $mlsRow = DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->where('full_file_number', $fileNo)
                ->orderByDesc('id')
                ->first();

            if ($mlsRow && !empty($mlsRow->tp_no)) {
                $data['tp_number'] = $mlsRow->tp_no;
            }
        }

        return $data;
    }

    /**
     * Generate and save commissioning sheet data (PDF generated on frontend)
     */
    public function generateAndPrint(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_number'        => 'required|string|max:255',
                'file_name'          => 'nullable|string|max:500',
                'name_or_allottee'   => 'nullable|string|max:500',
                'plot_number'        => 'nullable|string|max:255',
                'tp_number'          => 'nullable|string|max:255',
                'location'           => 'nullable|string|max:500',
                'lga'                => 'nullable|string|max:100',
                'date_created'       => 'nullable|string|max:255',
                'created_by'         => 'nullable|string|max:255',
                'commissioning_time' => 'nullable|string|max:50',
                'related_file_number'=> 'nullable|string|max:255',
                'related_file_title' => 'nullable|string|max:255',
                'original_file_no'   => 'nullable|string|max:255',
                'original_op_holder' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['created_user_id'] = Auth::id();
            if (empty($data['created_by'])) {
                $data['created_by'] = Auth::user()->name ?? Auth::user()->email ?? '';
            }
            if (empty($data['date_created'])) {
                $data['date_created'] = now();
            } else {
                try {
                    $data['date_created'] = Carbon::parse($data['date_created']);
                } catch (\Throwable $e) {
                    $data['date_created'] = now();
                }
            }

            // Auto-lookup related file info:
            //   - Application type (Merger, Subdivision, etc.) from mls_file_no.source
            //   - Related file number from fileNumber.related_fileno
            $fileNo = $data['file_number'];

            // 1. Get application type from mls_file_no.source
            // The register's own source WINS over whatever the caller passed. The
            // commissioning modal sends a generic "Conversion" for every file it raises,
            // which printed "CON-RES-2026-2924 (Conversion)" on a file the file list
            // shows, correctly, as a Change of Purpose. mls_file_no.source is the same
            // value that column reads, so the sheet and the list now agree.
            $mlsRow = DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->where('full_file_number', $fileNo)
                ->select('source')
                ->first();

            if ($mlsRow && !empty($mlsRow->source)) {
                // Source values are already human-readable labels
                // (Conversion, Change of Purpose, Subdivision, Merger, ...)
                $data['related_file_title'] = trim($mlsRow->source);
            }

            // A Plot Extension keeps the original file number, so a file already
            // indexed/commissioned under that number has no mls_file_no.source of its
            // own. Prefer the Plot Extension reason over a blank/guessed label.
            if (empty($data['related_file_title'])) {
                $hasPlotExtension = DB::connection('sqlsrv')
                    ->table('plot_extensions')
                    ->where('original_file_no', $fileNo)
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->exists();

                if ($hasPlotExtension) {
                    $data['related_file_title'] = 'Plot Extension';
                }
            }

            // 2. Get related file number from fileNumber.related_fileno
            if (empty($data['related_file_number'])) {
                $fnRow = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $fileNo)
                    ->select('related_fileno')
                    ->first();

                if ($fnRow && !empty($fnRow->related_fileno)) {
                    $data['related_file_number'] = $fnRow->related_fileno;
                }
            }

            // 3. Fill the property fields the caller left blank from the indexed record.
            $data = $this->fillPropertyFieldsFromIndexing($data);

            // commissioning_time is not a DB column; store it temporarily for the print view
            $commissioningTime = $data['commissioning_time'] ?? null;
            unset($data['commissioning_time']);
            // original_file_no is not a DB column either
            unset($data['original_file_no']);
            // original_op_holder is not a DB column either
            $originalOpHolder = $data['original_op_holder'] ?? null;
            unset($data['original_op_holder']);
            $data['status'] = 'Draft';
            $data['created_at'] = now();
            $data['updated_at'] = now();

            // Insert into database
            $id = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->insertGetId($data);

            return response()->json([
                'success' => true,
                'message' => 'Commissioning sheet data saved successfully',
                'data' => [
                    'id' => $id,
                    'commissioning_time' => $commissioningTime,
                    'original_op_holder' => $originalOpHolder
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error saving commissioning sheet data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save commissioning sheet data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all commissioning sheets
     */
    public function index()
    {
        try {
            $sheets = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $sheets
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching commissioning sheets: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch commissioning sheets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific commissioning sheet
     */
    public function show($id)
    {
        try {
            $sheet = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->where('id', $id)
                ->first();

            if (!$sheet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commissioning sheet not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $sheet
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching commissioning sheet: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch commissioning sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a commissioning sheet
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_number' => 'required|string|max:255',
                'file_name' => 'nullable|string|max:500',
                'name_or_allottee' => 'nullable|string|max:500',
                'plot_number' => 'nullable|string|max:255',
                'tp_number' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:500',
                'lga' => 'nullable|string|max:100',
                'date_created' => 'nullable|date',
                'created_by' => 'nullable|string|max:255',
                'approved_by' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:Draft,Approved,Printed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['updated_at'] = now();

            $affected = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->where('id', $id)
                ->update($data);

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commissioning sheet not found or no changes made'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Commissioning sheet updated successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error updating commissioning sheet: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update commissioning sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a commissioning sheet
     */
    public function destroy($id)
    {
        try {
            $affected = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->where('id', $id)
                ->delete();

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commissioning sheet not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Commissioning sheet deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error deleting commissioning sheet: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete commissioning sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print a specific commissioning sheet.
     */
    public function print($id)
    {
        try {
            $sheet = DB::connection('sqlsrv')
                ->table('file_commissioning_sheets')
                ->where('id', $id)
                ->first();

            if (!$sheet) {
                abort(404, 'Commissioning sheet not found');
            }

            $data = (array) $sheet;

            // Source commissioning date/time from DCIV metadata table.
            $dcivMeta = DB::connection('sqlsrv')
                ->table('dciv_file_no')
                ->select('commissioning_date', 'commissioning_time', 'tracking_id')
                ->where('full_file_number', $data['file_number'] ?? null)
                ->orderByDesc('id')
                ->first();

            if ($dcivMeta) {
                $data['commissioning_date'] = $dcivMeta->commissioning_date;
                $data['commissioning_time'] = $dcivMeta->commissioning_time;
                $data['tracking_id'] = $dcivMeta->tracking_id;
            }

            // Sheets saved before the location fallback existed (and any saved with the
            // fields blank) still print the property details from the indexed record.
            $data = $this->fillPropertyFieldsFromIndexing($data);

            // The District prints on its own line, between the Plot No and the Location.
            // It is not a column on file_commissioning_sheets, so it is resolved here.
            $data['district'] = $this->resolveDistrict((string) ($data['file_number'] ?? ''));

            // The LGA prints in caps wherever it appears in the Location line — including
            // on sheets stored before that rule existed.
            $lga = trim((string) ($data['lga'] ?? ''));
            if ($lga !== '' && !empty($data['location'])) {
                $data['location'] = preg_replace(
                    '/' . preg_quote($lga, '/') . '/i',
                    mb_strtoupper($lga),
                    (string) $data['location']
                );
            }

            // Accept commissioning_time from query param (passed from OSS table)
            if (empty($data['commissioning_time']) && request()->has('commissioning_time')) {
                $data['commissioning_time'] = request()->input('commissioning_time');
            }

            // Accept original_op_holder from query param (passed from OSS table)
            if (empty($data['original_op_holder']) && request()->has('original_op_holder')) {
                $data['original_op_holder'] = request()->input('original_op_holder');
            }

            // Fallback: use created_at for time/date when DCIV data is unavailable
            if (empty($data['commissioning_time']) && !empty($data['created_at'])) {
                try {
                    $data['commissioning_time'] = \Carbon\Carbon::parse($data['created_at'])->format('h:i A');
                } catch (\Throwable $e) {}
            }
            if (empty($data['commissioning_date']) && empty($data['date_created']) && !empty($data['created_at'])) {
                try {
                    $data['date_created'] = \Carbon\Carbon::parse($data['created_at'])->format('Y-m-d');
                } catch (\Throwable $e) {}
            }

            // Resolve user name from created_user_id when created_by is empty
            if (empty($data['created_by']) && !empty($data['created_user_id'])) {
                $user = DB::connection('sqlsrv')->table('users')->where('id', $data['created_user_id'])->first();
                if ($user) {
                    $data['created_by'] = $user->name ?? $user->email ?? '';
                }
            }

            // ── Auto-resolve related file info ────────────────────────────────
            //   - Application type from mls_file_no.source (e.g. Merger, Subdivision)
            //   - Related file number from fileNumber.related_fileno
            $fileNo = $data['file_number'] ?? '';

            // 1. Get application type from mls_file_no.source
            // The register's own source WINS over whatever the caller passed. The
            // commissioning modal sends a generic "Conversion" for every file it raises,
            // which printed "CON-RES-2026-2924 (Conversion)" on a file the file list
            // shows, correctly, as a Change of Purpose. mls_file_no.source is the same
            // value that column reads, so the sheet and the list now agree.
            $mlsRow = DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->where('full_file_number', $fileNo)
                ->select('source')
                ->first();

            if ($mlsRow && !empty($mlsRow->source)) {
                // Source values are already human-readable labels
                // (Conversion, Change of Purpose, Subdivision, Merger, ...)
                $data['related_file_title'] = trim($mlsRow->source);
            }

            // A Plot Extension keeps the original file number, so a file already
            // indexed/commissioned under that number has no mls_file_no.source of its
            // own. Prefer the Plot Extension reason over a blank/guessed label.
            if (empty($data['related_file_title'])) {
                $hasPlotExtension = DB::connection('sqlsrv')
                    ->table('plot_extensions')
                    ->where('original_file_no', $fileNo)
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->exists();

                if ($hasPlotExtension) {
                    $data['related_file_title'] = 'Plot Extension';
                }
            }

            // 2. Get related file number from fileNumber.related_fileno, falling back to
            //    the file's own indexing row (a subdivision child, a conversion).
            if (empty($data['related_file_number'])) {
                $related = $this->resolveRelatedFileNumber($fileNo);

                if ($related !== '') {
                    $data['related_file_number'] = $related;
                }
            }

            // 3. SIT files carry a reason that prints after the Location
            if (empty($data['sit_reason']) && stripos($fileNo, 'SIT-') === 0) {
                $sitRow = DB::connection('sqlsrv')
                    ->table('mls_file_no')
                    ->where('full_file_number', $fileNo)
                    ->select('sit_reason')
                    ->first();

                if ($sitRow && !empty($sitRow->sit_reason)) {
                    $data['sit_reason'] = $sitRow->sit_reason;
                }
            }

            // 4. An ST sheet quotes both numbers: the file's own, then the primary it
            //    sits under, bracketed after it. Only a genuine second number is kept —
            //    an ST primary is its own mls_fileno and would otherwise print twice.
            if (stripos($fileNo, 'ST-') === 0 && empty($data['st_primary_file_number'])) {
                $stRow = DB::connection('sqlsrv')
                    ->table('st_file_numbers')
                    ->where('fileno', $fileNo)
                    ->orderByDesc('id')
                    ->select('mls_fileno')
                    ->first();

                $stPrimary = trim((string) ($stRow->mls_fileno ?? ''));

                if ($stPrimary !== '' && strcasecmp($stPrimary, trim($fileNo)) !== 0) {
                    $data['st_primary_file_number'] = $stPrimary;
                }
            }

            // 5. A Re-Issuance carries the duplicated number it replaces. It is kept
            //    on mls_file_no.old_fileno, and the sheet names it under the new one.
            //    Guarded, because the column post-dates some deployments.
            if (empty($data['old_file_number'])
                && Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'old_fileno')) {
                $oldRow = DB::connection('sqlsrv')
                    ->table('mls_file_no')
                    ->where('full_file_number', $fileNo)
                    ->select('old_fileno')
                    ->first();

                $oldFileNo = trim((string) ($oldRow->old_fileno ?? ''));

                if ($oldFileNo !== '' && strcasecmp($oldFileNo, trim($fileNo)) !== 0) {
                    $data['old_file_number'] = $oldFileNo;
                }
            }

            // 6. The applicant's passport photograph, filed at commissioning, prints on the sheet.
            $data['passport_image'] = $this->resolveCommissioningPassport($fileNo)
                ?? $this->resolveCommissioningPassport((string) ($data['related_file_number'] ?? ''));

            // 7. Both quoted numbers print with their KANGIS/land counterpart, e.g.
            //    "COM-2005-78 (KNML 74)" — a KANGIS number is an alias of a land file,
            //    so the reader expects the pair whichever of the two was recorded.
            //    Done last, so the raw numbers above still drive the lookups.
            //    ST keeps its own two-line treatment and is left alone.
            //    The sheet's own number is excluded from the pair: it is already printed
            //    on the File No line above, and a recertification IS the land file of its
            //    own KANGIS alias, so pairing would repeat it here as if it were another
            //    file ("RES-RC-2026-8 (KN 6873)" against RES-RC-2026-8).
            $pairs = app(KangisLandPairService::class);
            if (!empty($data['old_file_number'])) {
                $data['old_file_number'] = $pairs->formatList($data['old_file_number'], $fileNo);
            }
            if (!empty($data['related_file_number']) && stripos($fileNo, 'ST-') !== 0) {
                $data['related_file_number'] = $pairs->formatList($data['related_file_number'], $fileNo);
            }

            return view('commissioning_sheet.pdf', compact('data'));

        } catch (\Exception $e) {
            \Log::error('Error printing commissioning sheet: ' . $e->getMessage());
            abort(500, 'Failed to print commissioning sheet');
        }
    }

    /**
     * Passport photograph for a file number, as a data URI.
     *
     * The commissioning sheet is also built client-side with jsPDF (Generate File Number /
     * Commission modals), which cannot read the storage disk — it asks here instead.
     */
    public function passportPhoto(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number', ''));

        if ($fileNumber === '') {
            return response()->json(['success' => false, 'image' => null], 200);
        }

        $image = $this->resolveCommissioningPassport($fileNumber);

        return response()->json([
            'success' => (bool) $image,
            'image'   => $image,
        ]);
    }

    /**
     * The other file numbers a commissioning sheet quotes: the old (duplicated) number a
     * Re-Issuance replaces, and the related file the record was raised from. The district
     * rides along, since it has no field on the commissioning form either.
     *
     * The two numbers come back print-ready — paired with their KANGIS/land counterpart,
     * e.g. "COM-2005-78 (KNML 74)". The client-side PDF builder (Generate File Number /
     * Commission modals) has no DB access and asks here instead.
     */
    public function fileLinks(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number', ''));

        if ($fileNumber === '') {
            return response()->json([
                'success'             => false,
                'old_file_number'     => '',
                'related_file_number' => '',
                'district'            => '',
                'source'              => '',
            ], 200);
        }

        $pairs = app(KangisLandPairService::class);
        $old = '';
        $related = '';

        try {
            // The old (duplicated) number lives on mls_file_no; the column post-dates
            // some deployments, so it is checked before it is read.
            if (Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'old_fileno')) {
                $oldRow = DB::connection('sqlsrv')
                    ->table('mls_file_no')
                    ->where('full_file_number', $fileNumber)
                    ->select('old_fileno')
                    ->first();

                $candidate = trim((string) ($oldRow->old_fileno ?? ''));
                // An entry equal to the file's own number is not a previous number.
                if ($candidate !== '' && strcasecmp($candidate, $fileNumber) !== 0) {
                    $old = $pairs->formatList($candidate, $fileNumber);
                }
            }

            $relatedRaw = $this->resolveRelatedFileNumber($fileNumber);
            if ($relatedRaw !== '') {
                // Excluded for the same reason as on the printed sheet: the file's own
                // number already leads the sheet and must not reappear beside its alias.
                $related = $pairs->formatList($relatedRaw, $fileNumber);
            }
        } catch (\Throwable $e) {
            \Log::warning('Commissioning sheet file links lookup failed: ' . $e->getMessage());
        }

        // The label printed after the File No, e.g. "(Change of Purpose)". The sheet
        // builder otherwise guesses it from the number's prefix, and every CON- file
        // guesses "Conversion" — so a Change of Purpose printed as a Conversion while
        // the file list, reading this same column, said otherwise.
        $source = '';
        try {
            $source = trim((string) DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->where('full_file_number', $fileNumber)
                ->orderByDesc('id')
                ->value('source'));
        } catch (\Throwable $e) {
            \Log::warning('Commissioning sheet source lookup failed: ' . $e->getMessage());
        }

        return response()->json([
            'success'             => true,
            'old_file_number'     => $old,
            'related_file_number' => $related,
            'district'            => $this->resolveDistrict($fileNumber),
            'source'              => $source,
        ]);
    }

    /**
     * The district a file sits in, for the sheet's own District line.
     *
     * file_commissioning_sheets has no district column, so the value is resolved from the
     * file's record every time the sheet is drawn: the indexed record first, then the MLS
     * record. Both store it the way it prints, so it is passed through untouched.
     */
    private function resolveDistrict(string $fileNumber): string
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return '';
        }

        try {
            $district = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where(function ($q) use ($fileNumber) {
                    $q->where('file_number', $fileNumber)->orWhere('mls_file_no', $fileNumber);
                })
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderByDesc('id')
                ->value('district');

            $district = trim((string) $district);
            if ($district !== '') {
                return $district;
            }

            return trim((string) DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->where('full_file_number', $fileNumber)
                ->orderByDesc('id')
                ->value('district'));
        } catch (\Throwable $e) {
            \Log::warning('Commissioning sheet district lookup failed: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * The file a record was raised from, as stored (a JSON array, a comma list or a
     * bare number) — empty when it has none.
     *
     * Two places hold it: fileNumber.related_fileno, written only when someone types the
     * number into the Edit modal, and the file's own file_indexings row, written at
     * creation for a file raised from another (a subdivision child, a conversion).
     * Without the second, a whole batch of subdivided files prints with no related file.
     */
    private function resolveRelatedFileNumber(string $fileNumber): string
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return '';
        }

        $fnRow = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->where('mlsfNo', $fileNumber)
            ->select('related_fileno')
            ->first();

        $related = trim((string) ($fnRow->related_fileno ?? ''));
        if ($related !== '') {
            return $related;
        }

        $isKangis = app(KangisLandPairService::class)->isKangisFormat($fileNumber);

        $ownIndexing = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('file_number', $fileNumber)
            ->whereNull('deleted_at')
            // A KANGIS-registry row is the alias of some land file, not this file's own
            // record; its back-link is not a related file. Only skipped when the sheet is
            // for a land file, since a KANGIS number's own row IS that row.
            ->when(!$isKangis, function ($q) {
                $q->where(function ($w) {
                    $w->whereNull('registry')->orWhere('registry', '!=', 'KANGIS');
                });
            })
            ->value('related_fileno');

        return trim((string) $ownIndexing);
    }

    /**
     * Find the passport photograph filed against a commissioned file and return it as a
     * data URI so it prints (and PDFs) without depending on a reachable storage URL.
     *
     * Two places hold it, in the order the commissioning writes them:
     *   1. a `scannings` row of document_type "Passport Photograph" hanging off the
     *      file's file_indexings record (written by MlsFileNoController);
     *   2. oss_applications.passport_photo, mirrored there for the OSS record.
     *
     * Best-effort: any lookup or read failure simply means no photograph is printed.
     */
    private function resolveCommissioningPassport(string $fileNumber): ?string
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return null;
        }

        try {
            $path = DB::connection('sqlsrv')
                ->table('scannings as s')
                ->join('file_indexings as fi', 'fi.id', '=', 's.file_indexing_id')
                ->where('fi.file_number', $fileNumber)
                ->where('s.document_type', 'Passport Photograph')
                ->orderByDesc('s.id')
                ->value('s.document_path');

            if (empty($path)) {
                $path = DB::connection('sqlsrv')
                    ->table('oss_applications')
                    ->where('file_no', $fileNumber)
                    ->whereNotNull('passport_photo')
                    ->orderByDesc('id')
                    ->value('passport_photo');
            }

            return $this->passportDataUri($path);
        } catch (\Throwable $e) {
            \Log::warning('Could not resolve commissioning passport photograph', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Read a stored passport image into a base64 data URI. Paths are stored relative to
     * the public disk, but older/mirrored rows may carry a `storage/...` public path or a
     * full URL — the first two are read from disk, a URL is passed straight through.
     */
    private function passportDataUri($path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        $relative = ltrim(str_replace("\\", '/', $path), '/');
        // The public disk is intentionally rooted at STORAGE_PATH (F:\\storage in
        // this installation), not Laravel's framework storage directory.  Checking
        // only storage_path() made a correctly uploaded commissioning passport look
        // missing when the sheet was generated.
        $publicDiskPath = function_exists('file_storage_path')
            ? file_storage_path('app/public/' . $relative)
            : storage_path('app/public/' . $relative);
        $candidates = [
            $publicDiskPath,
            public_path($relative),
            public_path('storage/' . $relative),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $contents = @file_get_contents($candidate);
                if ($contents === false || $contents === '') {
                    continue;
                }

                $mime = 'image/jpeg';
                if (function_exists('mime_content_type')) {
                    $detected = @mime_content_type($candidate);
                    if (is_string($detected) && str_starts_with($detected, 'image/')) {
                        $mime = $detected;
                    }
                }

                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            }
        }

        return null;
    }
}
