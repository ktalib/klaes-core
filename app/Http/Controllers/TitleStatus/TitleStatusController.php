<?php

namespace App\Http\Controllers\TitleStatus;

use App\Http\Controllers\Controller;
use App\Models\TitleStatusApplication;
use App\Services\TitleStatusParcelRouter;
use App\Services\TitleStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TitleStatusController extends Controller
{
    /**
     * Where fileInfo() looks up a file's property details, in priority order. Each source
     * fills only the fields still empty after the sources above it, so `file_indexings`
     * wins on everything it holds and the later registries top up what it cannot supply
     * (it has no house_no and no state column at all).
     *
     * `map` is field => column, or field => [column, fallback column]. Column names are
     * NOT consistent across these tables — `pra`/`CofO_staging` use districtName/streetName,
     * `fileNumber` is all lowercase, `file_indexings` uses plot_number/land_use_type.
     * A wrong name silently yields null, so verify against the real schema before editing.
     */
    private const DETAIL_SOURCES = [
        [
            'table' => 'file_indexings',
            'key'   => 'file_number',
            'map'   => [
                'file_title'     => 'file_title',
                'current_holder' => ['current_holder', 'original_holder'],
                'plot_no'        => 'plot_number',
                'street_name'    => 'street_name',
                'district'       => 'district',
                'lga'            => 'lga',
                'location'       => 'location',
                'land_use'       => 'land_use_type',
                'plot_size'      => 'plot_size',
                'serial_no'      => 'serial_no',
            ],
        ],
        [
            // The commissioning table — the only source carrying `state`.
            'table' => 'mls_file_no',
            'key'   => 'full_file_number',
            'map'   => [
                'file_title'     => 'file_name',
                'current_holder' => 'file_name',
                'plot_no'        => 'plot_no',
                'district'       => 'district',
                'lga'            => 'lga',
                'state'          => 'state',
                'location'       => 'location',
                'land_use'       => 'land_use',
            ],
        ],
        [
            'table' => 'fileNumber',
            'key'   => 'mlsfNo',
            'map'   => [
                'file_title'     => 'FileName',
                'current_holder' => 'FileName',
                'plot_no'        => 'plot_no',
                'district'       => 'district',
                'lga'            => 'lga',
                'location'       => 'location',
            ],
        ],
        [
            // Deed/instrument registry — the only sources carrying house_no.
            'table' => 'pra',
            'key'   => 'mlsFNo',
            'map'   => [
                'plot_no'     => 'plot_no',
                'house_no'    => 'house_no',
                'street_name' => 'streetName',
                'district'    => 'districtName',
                'location'    => 'location',
                'land_use'    => 'land_use',
                'plot_size'   => 'plot_size',
            ],
        ],
        [
            'table' => 'CofO_staging',
            'key'   => 'mlsFNo',
            'map'   => [
                'plot_no'     => 'plot_no',
                'house_no'    => 'house_no',
                'street_name' => 'streetName',
                'district'    => 'districtName',
                'location'    => 'location',
                'land_use'    => 'land_use',
                'plot_size'   => 'plot_size',
            ],
        ],
    ];

    /** Every property detail fileInfo() resolves. Kept in sync with the form's hidden inputs. */
    private const DETAIL_FIELDS = [
        'file_title', 'current_holder', 'plot_no', 'house_no', 'street_name',
        'district', 'lga', 'state', 'location', 'land_use', 'plot_size', 'serial_no',
    ];

    public function __construct(
        protected TitleStatusService $titleStatusService,
        protected TitleStatusParcelRouter $parcelRouter
    ) {}

    public function index(Request $request): View
    {
        $url    = trim((string) $request->input('url', 'land'));
        $limit  = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        // Deeds and DCIV are downstream registries: they view title-status records
        // raised elsewhere (read-only) so they know a file's status, but cannot act on
        // them. They therefore aggregate every registry's records rather than filtering
        // to their own `url`, and the view hides all action controls.
        $readOnly = in_array($url, ['deeds', 'dciv'], true);

        $records = TitleStatusApplication::query()
            ->when(!$readOnly, fn ($q) => $q->where('url', $url))
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('title_no', 'LIKE', "%{$search}%")
                        ->orWhere('title_type', 'LIKE', "%{$search}%")
                        ->orWhere('plot_no', 'LIKE', "%{$search}%")
                        ->orWhere('location', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($limit);

        $urlLabel             = $this->titleStatusService->urlLabel($url);
        $authorityOptions     = TitleStatusApplication::AUTHORITY_OPTIONS;
        $initiatedByOptions   = TitleStatusApplication::INITIATED_BY_OPTIONS;
        $initiatedByByType    = TitleStatusApplication::INITIATED_BY_BY_TYPE;

        return view('title_status.index', compact('records', 'limit', 'url', 'readOnly', 'urlLabel', 'authorityOptions', 'initiatedByOptions', 'initiatedByByType'));
    }

    public function show(int $id): JsonResponse
    {
        $record = TitleStatusApplication::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $record,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['url']         = $request->input('url', 'land');
        $data['captured_by'] = Auth::id();
        $data['status']      = TitleStatusApplication::STATUS_PENDING;

        // Support selecting several title statuses for the same file in one request.
        // `title_types` (and the parallel `remarks`) take precedence; fall back to the
        // single `title_type`/`remark` for backward compatibility with the standalone module.
        $types   = $request->input('title_types');
        $remarks = $request->input('remarks');
        if (!is_array($types) || empty($types)) {
            $types   = [$data['title_type']];
            $remarks = [$data['remark'] ?? null];
        }

        $records = [];
        $routed  = 0;
        foreach (array_values($types) as $i => $type) {
            $rowData                = $data;
            $rowData['title_type']  = $type;
            if (is_array($remarks) && array_key_exists($i, $remarks)) {
                $rowData['remark'] = $remarks[$i];
            }

            // Parcel-update actions (Subdivision, Merger, Change of Purpose, Extension,
            // Separation) belong in the dedicated Parcel Update tables, not here. Route them
            // there as hidden rows so they stay off the Parcel Update frontend until processed,
            // and never create a title_status_applications row for them.
            if ($this->parcelRouter->isParcelType($type)) {
                $this->parcelRouter->route($type, $rowData);
                $routed++;
                continue;
            }

            $records[] = TitleStatusApplication::create($rowData);
        }

        // Flag source files and push to archive tables. A Title Status update raised from
        // File Indexing is a "false decommissioning" — the file is not actually decommissioned.
        // Parcel-update types are routed elsewhere and are not part of $records.
        if (!empty($records)) {
            $falseDecommissioning = $request->boolean('false_decommissioning');
            $this->titleStatusService->flagAndDecommission($records, $falseDecommissioning);

            // File Indexing can describe either side of a Re-grant. Its ordinary
            // Title Status request is intentionally a false decommissioning, but a
            // directional Re-grant is a replacement and must genuinely retire the
            // old/parent number once the replacement is known.
            foreach ($records as $record) {
                if ($record->title_type === TitleStatusApplication::TYPE_REGRANT_FROM) {
                    $this->titleStatusService->decommissionRegrantParent(
                        (string) $record->see_fileno,
                        (string) $record->file_no
                    );
                } elseif ($record->title_type === TitleStatusApplication::TYPE_REGRANT_TO) {
                    $this->titleStatusService->decommissionRegrantParent(
                        (string) $record->file_no,
                        (string) $record->see_fileno
                    );
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Title Status application created successfully.',
            'data'    => count($records) === 1 ? $records[0] : $records,
            'routed_to_parcel_update' => $routed,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = TitleStatusApplication::findOrFail($id);

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data               = $validator->validated();
        $data['updated_by'] = Auth::id();

        $record->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Title Status application updated successfully.',
            'data'    => $record->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = TitleStatusApplication::findOrFail($id);

        if ($record->status === TitleStatusApplication::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Approved applications cannot be deleted.',
            ], 403);
        }

        $record->update([
            'is_deleted' => 1,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application deleted successfully.',
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $record = TitleStatusApplication::findOrFail($id);

        $record->update([
            'status'      => TitleStatusApplication::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'updated_by'  => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Application approved.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $record = TitleStatusApplication::findOrFail($id);

        $reason = trim((string) $request->input('reason', ''));

        $record->update([
            'status'     => TitleStatusApplication::STATUS_REJECTED,
            'remark'     => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Application rejected.']);
    }

    public function fileInfo(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->input('file_no', ''));

        if (!$fileNo) {
            return response()->json(['success' => false, 'message' => 'File number required.'], 422);
        }

        $data = $this->resolveFileDetails($fileNo);

        if ($data === null) {
            return response()->json(['success' => false, 'message' => 'File not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Resolve every property detail we can find for a file number by walking
     * {@see self::DETAIL_SOURCES} in priority order.
     *
     * Each field is claimed by the first source that actually has a value for it, so a
     * later registry can top up a gap without overwriting better data from a higher
     * priority one. `source_table`/`source_id` record the first table holding the file,
     * which is what the application row links back to.
     *
     * @return array<string,mixed>|null Null when the file is in none of the sources.
     */
    private function resolveFileDetails(string $fileNo): ?array
    {
        $out = array_fill_keys(self::DETAIL_FIELDS, null);
        $out['file_no']      = $fileNo;
        $out['source_table'] = null;
        $out['source_id']    = null;

        $schema = DB::connection('sqlsrv')->getSchemaBuilder();

        foreach (self::DETAIL_SOURCES as $source) {
            // Nothing left to fill — skip the remaining (and heaviest) lookups.
            if (!$this->hasEmptyField($out) && $out['source_table'] !== null) {
                break;
            }

            try {
                if (!$schema->hasTable($source['table'])) {
                    continue;
                }

                $row = DB::connection('sqlsrv')->table($source['table'])
                    ->where($source['key'], $fileNo)
                    ->first();

                if (!$row) {
                    continue;
                }

                if ($out['source_table'] === null) {
                    $out['source_table'] = $source['table'];
                    $out['source_id']    = $row->id ?? null;
                }

                foreach ($source['map'] as $field => $columns) {
                    if ($this->detailValue($out[$field]) !== null) {
                        continue; // already claimed by a higher-priority source
                    }
                    foreach ((array) $columns as $column) {
                        $value = $this->detailValue($row->$column ?? null);
                        if ($value !== null) {
                            $out[$field] = $value;
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("TitleStatus fileInfo: {$source['table']} lookup failed for {$fileNo}: " . $e->getMessage());
            }
        }

        // Last resort: the composite `location` string still holds the address parts that
        // no registry column carries. Fills gaps only — never overrides a real column.
        foreach ($this->parseLocation($out['location']) as $field => $value) {
            if ($out[$field] === null) {
                $out[$field] = $value;
            }
        }

        return $out['source_table'] === null ? null : $out;
    }

    /**
     * Split the composite `location` string into its address components.
     *
     * `file_indexings.location` is stored as "{plot}, {street}, {district}, {lga}, {state}"
     * (segments are frequently omitted), and it is the only place several of these values
     * exist at all — no registry table has a usable `state` column, and `file_indexings`
     * has no `house_no` column.
     *
     * Strategy is the one proven by {@see \App\Console\Commands\BackfillValuationReportAddressParts}:
     * anchor on the segment matching a known LGA and read outwards. Anchoring beats reading
     * fixed positions because segments are routinely missing, and it resolves the
     * "Nasarawa" trap — Nasarawa is both a Kano LGA and a Nigerian state, so taking the
     * last segment as the state mislabels every 3-segment Nasarawa address. Anchored, it
     * is consumed as the LGA and only a segment *after* it can become the state.
     *
     * Every component is validated against its lookup table, so an unrecognised segment
     * yields null instead of a guess.
     *
     * Note the leading segment is the PLOT number, not a house number — `location` is
     * built as "{plot_no}, [street,] {district}, {lga}, {state}". There is no house-number
     * component to recover here; `house_no` only ever comes from pra/CofO_staging.
     *
     * @return array{plot_no:?string,street_name:?string,district:?string,lga:?string,state:?string}
     */
    private function parseLocation(?string $location): array
    {
        $empty = ['plot_no' => null, 'street_name' => null, 'district' => null, 'lga' => null, 'state' => null];

        $location = $this->detailValue($location);
        if ($location === null) {
            return $empty;
        }

        $tokens = array_values(array_filter(
            array_map('trim', explode(',', $location)),
            fn ($token) => $token !== ''
        ));

        if (count($tokens) < 2) {
            return $empty;
        }

        [$lgaNames, $districtNames, $stateNames] = $this->locationLookups();

        // Anchor on the LGA, scanning from the right since the state trails it.
        $lgaIndex = null;
        for ($i = count($tokens) - 1; $i >= 1; $i--) {
            if (isset($lgaNames[mb_strtoupper($tokens[$i])])) {
                $lgaIndex = $i;
                break;
            }
        }

        if ($lgaIndex === null) {
            return $empty;
        }

        $parsed        = $empty;
        $parsed['lga'] = $tokens[$lgaIndex];

        // Anything after the LGA is the state, if it names one. "Kano State" and "Kano"
        // are both written; normalise to the canonical name from the lookup.
        if (isset($tokens[$lgaIndex + 1])) {
            $candidate = trim((string) preg_replace('/\s+state$/i', '', $tokens[$lgaIndex + 1]));
            $parsed['state'] = $stateNames[mb_strtoupper($candidate)] ?? null;
        }

        // The segment before the LGA is the district — but only when it really is one.
        // On short addresses that slot holds street text instead.
        $streetEnd = $lgaIndex;
        if ($lgaIndex >= 1 && isset($districtNames[mb_strtoupper($tokens[$lgaIndex - 1])])) {
            $parsed['district'] = $tokens[$lgaIndex - 1];
            $streetEnd = $lgaIndex - 1;
        }

        // What remains to the left is the plot number, optionally followed by a street.
        // Never split a single segment: "136 & 138" and "PIECE OF LAND" are whole plot
        // values, and chopping them scatters one field across two.
        $head = array_slice($tokens, 0, $streetEnd);

        if (!empty($head)) {
            $parsed['plot_no'] = array_shift($head);
        }
        if (!empty($head)) {
            $parsed['street_name'] = trim(implode(', ', $head)) ?: null;
        }

        return $parsed;
    }

    /**
     * Lookup sets for {@see self::parseLocation()}, keyed upper-case for O(1) matching.
     * Read once per request; the district list alone is ~1,800 rows.
     *
     * @return array{0:array<string,true>,1:array<string,true>,2:array<string,string>}
     */
    private function locationLookups(): array
    {
        static $cache = null;

        if ($cache === null) {
            $lgas = $districts = $states = [];

            try {
                foreach (DB::connection('sqlsrv')->table('StatLGAs')->pluck('LGAName') as $name) {
                    $name = trim((string) $name);
                    if ($name !== '') {
                        $lgas[mb_strtoupper($name)] = true;
                    }
                }
                foreach (DB::connection('sqlsrv')->table('districts')->pluck('name') as $name) {
                    $name = trim((string) $name);
                    if ($name !== '') {
                        $districts[mb_strtoupper($name)] = true;
                    }
                }
                foreach (DB::connection('sqlsrv')->table('States')->pluck('StateName') as $name) {
                    $name = trim((string) $name);
                    if ($name !== '') {
                        $states[mb_strtoupper($name)] = $name;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('TitleStatus fileInfo: location lookups failed: ' . $e->getMessage());
            }

            $cache = [$lgas, $districts, $states];
        }

        return $cache;
    }

    private function hasEmptyField(array $out): bool
    {
        foreach (self::DETAIL_FIELDS as $field) {
            if ($this->detailValue($out[$field] ?? null) === null) {
                return true;
            }
        }

        return false;
    }

    /** Normalise a looked-up value: null, and blank strings, both count as "not supplied". */
    private function detailValue($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function printCertificate(int $id): \Illuminate\Contracts\View\View
    {
        $record = TitleStatusApplication::where('title_type', TitleStatusApplication::TYPE_REVOKE)
            ->findOrFail($id);

        return view('title_status.certificate_revocation', compact('record'));
    }

    public function generateRemark(Request $request): JsonResponse
    {
        $titleType     = (string) $request->input('title_type', '');
        $initiatedBy   = (string) $request->input('initiated_by', '');
        $reason        = (string) $request->input('reason', '');
        $applicantName = (string) $request->input('applicant_name', '');
        $fileNo        = (string) $request->input('file_no', '');
        $seeFileno     = (string) $request->input('see_fileno', '');

        $remark = $this->titleStatusService->generateRemark($titleType, $initiatedBy, $reason, $applicantName, $fileNo, $seeFileno);

        return response()->json(['success' => true, 'remark' => $remark]);
    }

    private function rules(): array
    {
        return [
            'title_type'          => 'required|string|max:100',
            'file_no'             => 'required|string|max:255',
            'see_fileno'          => 'nullable|string|max:255',
            'file_title'          => 'nullable|string|max:500',
            'applicant_name'      => 'nullable|string|max:255',
            'source_table'        => 'nullable|string|max:100',
            'source_id'           => 'nullable|integer',
            'plot_no'             => 'nullable|string|max:100',
            'house_no'            => 'nullable|string|max:100',
            'street_name'         => 'nullable|string|max:255',
            'district'            => 'nullable|string|max:255',
            'lga'                 => 'nullable|string|max:255',
            'state'               => 'nullable|string|max:100',
            'location'            => 'nullable|string|max:2000',
            'land_use'            => 'nullable|string|max:255',
            'cofo_number'         => 'nullable|string|max:255',
            'title_no'            => 'nullable|string|max:255',
            'reg_page'            => 'nullable|string|max:100',
            'reg_volume'          => 'nullable|string|max:100',
            'date_of_issue'       => 'nullable|date',
            'date_of_expiry'      => 'nullable|date',
            'authority'           => 'nullable|string|max:255',
            'authority_reference' => 'nullable|string|max:500',
            'initiated_by'        => 'nullable|string|max:50',
            'reason'              => 'nullable|string',
            'remark'              => 'nullable|string',
        ];
    }
}
