<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Services\TimelineWeightingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class SurrenderReleaseController extends Controller
{
    protected TimelineWeightingService $timelineService;

    public function __construct(TimelineWeightingService $timelineService)
    {
        $this->timelineService = $timelineService;
    }

    /**
     * Display the surrender & release table view.
     */
    public function index()
    {
        $today = Carbon::today();
        
        $baseFilters = function($q) {
            $q->where(function($sub) {
                $sub->whereIn('instrument_type', [
                    'Deed of Surrender and Release',
                    'Deed of Surrender & Release',
                    'Deed of Surrender, and Release',
                    'Surrender and Release',
                    'Surrender & Release'
                ])
                ->orWhereRaw("LOWER(REPLACE(REPLACE(instrument_type, '&', 'and'), '  ', ' ')) IN (?, ?, ?)", [
                    'deed of surrender and release',
                    'deed of surrender & release',
                    'deed of surrender, and release',
                ]);
            })
            ->where(function ($q2) { 
                $q2->whereNull('is_deleted')->orWhere('is_deleted', 0); 
            });
        };

        // Source-specific totals
        $icTotal = DB::connection('sqlsrv')->table('instrument_capture')->where($baseFilters)->count();
        $praTotal = DB::connection('sqlsrv')->table('pra')->where($baseFilters)->count();
        $fhsTotal = DB::connection('sqlsrv')->table('file_history_staging')->where($baseFilters)->count();

        // Daily Records (Combined)
        $dailyRecords = DB::connection('sqlsrv')->table('instrument_capture')->where($baseFilters)->whereDate('created_at', $today)->count() +
            DB::connection('sqlsrv')->table('pra')->where($baseFilters)->whereDate('created_at', $today)->count() +
            DB::connection('sqlsrv')->table('file_history_staging')->where($baseFilters)->whereDate('created_at', $today)->count();

        $totalRecords = $icTotal + $praTotal + $fhsTotal;

        return view('surrender_release.index', compact('dailyRecords', 'totalRecords', 'icTotal', 'praTotal', 'fhsTotal'));
    }

    /**
     * Get data for DataTables.
     */
    public function getData()
    {
        $baseWhere = function($q) {
            $q->whereIn('instrument_type', [
                'Deed of Surrender and Release',
                'Deed of Surrender & Release',
                'Deed of Surrender, and Release',
                'Surrender and Release',
                'Surrender & Release'
            ])
            ->orWhereRaw("LOWER(REPLACE(REPLACE(instrument_type, '&', 'and'), '  ', ' ')) IN (?, ?, ?)", [
                'deed of surrender and release',
                'deed of surrender & release',
                'deed of surrender, and release',
            ]);
        };

        $icQuery = DB::connection('sqlsrv')->table('instrument_capture')
            ->select([
                DB::raw("'ic_' + CAST(id AS NVARCHAR(50)) as id"),
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno) as file_number"),
                DB::raw("registration_number as registration_particulars"),
                'instrument_type',
                'party_1_name as party_1',
                'party_2_name as party_2',
                'party_3_name as party_3',
                'party_4_name as party_4',
                'property_location as location',
                'created_at as date_captured',
                DB::raw("COALESCE(
                    (SELECT TOP 1 TRY_CONVERT(DATETIME, dr.deeds_date)
                     FROM deed_registrations dr
                     WHERE dr.fileno = COALESCE(instrument_capture.mlsFNo, instrument_capture.kangisFileNo, instrument_capture.NewKANGISFileno, instrument_capture.temp_fileno)
                       AND dr.instrument_type IN ('Deed of Surrender and Release','Deed of Surrender & Release','Deed of Surrender, and Release','Surrender and Release','Surrender & Release')
                     ORDER BY dr.created_at DESC),
                    TRY_CONVERT(DATETIME, reg_date)
                ) as transaction_date"),
                DB::raw("(SELECT TOP 1 CAST(dr.deeds_time AS NVARCHAR(50))
                          FROM deed_registrations dr
                          WHERE dr.fileno = COALESCE(instrument_capture.mlsFNo, instrument_capture.kangisFileNo, instrument_capture.NewKANGISFileno, instrument_capture.temp_fileno)
                            AND dr.instrument_type IN ('Deed of Surrender and Release','Deed of Surrender & Release','Deed of Surrender, and Release','Surrender and Release','Surrender & Release')
                          ORDER BY dr.created_at DESC) as deeds_time"),
                DB::raw("CAST(created_by AS NVARCHAR(100)) as created_by"),
                DB::raw("'Instrument Capture' as source_table")
            ])
            ->where($baseWhere)
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        $praQuery = DB::connection('sqlsrv')->table('pra')
            ->select([
                DB::raw("'pra_' + CAST(id AS NVARCHAR(50)) as id"),
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno) as file_number"),
                DB::raw("CAST(ISNULL(regNo, '') AS NVARCHAR(MAX)) as registration_particulars"),
                'instrument_type',
                DB::raw("COALESCE(Surrenderor, Grantor) as party_1"),
                DB::raw("COALESCE(Surrenderee, Grantee) as party_2"),
                DB::raw("NULL as party_3"),
                DB::raw("NULL as party_4"),
                'location',
                'created_at as date_captured',
                DB::raw("TRY_CONVERT(DATETIME, deeds_date) as transaction_date"),
                'deeds_time',
                DB::raw("CAST(created_by AS NVARCHAR(100)) as created_by"),
                DB::raw("'Property Records' as source_table")
            ])
            ->where($baseWhere)
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        $fhsQuery = DB::connection('sqlsrv')->table('file_history_staging')
            ->select([
                DB::raw("'fhs_' + CAST(id AS NVARCHAR(50)) as id"),
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno, temp_fileno) as file_number"),
                DB::raw("CAST(ISNULL(regNo, '') AS NVARCHAR(MAX)) as registration_particulars"),
                'instrument_type',
                DB::raw("COALESCE(Surrenderor, Grantor) as party_1"),
                DB::raw("COALESCE(Surrenderee, Grantee) as party_2"),
                'party_3',
                'party_4',
                'location',
                'created_at as date_captured',
                DB::raw("TRY_CONVERT(DATETIME, deeds_date) as transaction_date"),
                'deeds_time',
                DB::raw("CAST(created_by AS NVARCHAR(100)) as created_by"),
                DB::raw("'File History Staging' as source_table")
            ])
            ->where($baseWhere)
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        $unionQuery = $icQuery->unionAll($praQuery)->unionAll($fhsQuery);
        $unionSql = $unionQuery->toSql();
        $unionBindings = $unionQuery->getBindings();

        $query = DB::connection('sqlsrv')
            ->table(DB::raw("({$unionSql}) as combined"))
            ->setBindings($unionBindings)
            ->select('*');

        $userMap = DB::table('users')->select('id', 'first_name', 'last_name')->get()->keyBy('id');

        return DataTables::of($query)
            ->filterColumn('file_number', function($q, $kw) {
                $q->where('file_number', 'like', "%$kw%");
            })
            ->filterColumn('registration_particulars', function($q, $kw) {
                $q->where('registration_particulars', 'like', "%$kw%");
            })
            ->editColumn('created_by', function ($row) use ($userMap) {
                $id = $row->created_by;
                if (!$id) return '—';
                $user = $userMap->get((int) $id);
                if (!$user) return '—';
                return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: '—';
            })
            ->addColumn('associated_mortgages', function ($row) {
                $propId = trim((string) ($row->prop_id ?? ''));
                $fileNo = $row->file_number ?? '';
                return $this->getAssociatedMortgages($fileNo, $propId);
            })
            ->addColumn('timeline_count', function ($row) {
                $propId = trim((string) ($row->prop_id ?? ''));
                $fileNo = $row->file_number ?? '';
                
                if (!$propId && !$fileNo) return 1;

                $rawRecords = $this->timelineService->getRawRecords($fileNo, $propId);
                return max(1, $this->timelineService->getWeightedCount($rawRecords));
            })
            ->editColumn('date_captured', function ($row) {
                try {
                    return $row->date_captured ? Carbon::parse($row->date_captured)->format('Y-m-d H:i') : '—';
                } catch (\Exception $e) {
                    return $row->date_captured ?? '—';
                }
            })
            ->editColumn('transaction_date', function ($row) {
                try {
                    if (!$row->transaction_date) return '—';
                    $parsed  = Carbon::parse($row->transaction_date);
                    $date    = $parsed->format('Y-m-d');
                    $timeStr = trim((string) ($row->deeds_time ?? ''));
                    if ($timeStr !== '') {
                        try {
                            return $date . ' ' . Carbon::parse($timeStr)->format('h:i A');
                        } catch (\Exception $e) {}
                    }
                    // IC records store reg_date as a full datetime — extract time if non-midnight
                    if ($parsed->format('H:i:s') !== '00:00:00') {
                        return $date . ' ' . $parsed->format('g:i A');
                    }
                    return $date;
                } catch (\Exception $e) {
                    return $row->transaction_date ?? '—';
                }
            })
            ->make(true);
    }

    /**
     * Return a single record's normalized data for the edit modal.
     */
    public function edit(string $id): JsonResponse
    {
        [$prefix, $realId] = $this->parseCompositeId($id);
        $table = $this->tableForPrefix($prefix);

        $record = DB::connection('sqlsrv')->table($table)->where('id', $realId)->first();
        if (!$record) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        $r = (array) $record;

        $normalized = [
            'source'                   => $prefix,
            'file_number'              => $r['mlsFNo'] ?? $r['kangisFileNo'] ?? $r['NewKANGISFileno'] ?? $r['fileno'] ?? $r['temp_fileno'] ?? '',
            'registration_particulars' => $prefix === 'ic' ? ($r['registration_number'] ?? '') : ($r['regNo'] ?? ''),
            'instrument_type'          => $r['instrument_type'] ?? '',
            'transaction_date'         => $prefix === 'ic' ? ($r['reg_date'] ?? '') : ($r['deeds_date'] ?? ''),
            // Party 1 — Surrenderor
            'party_1'         => $prefix === 'ic' ? ($r['party_1_name'] ?? '') : ($r['Surrenderor'] ?? $r['Grantor'] ?? ''),
            'party_1_address' => $prefix === 'ic' ? ($r['party_1_address'] ?? '') : null,
            'party_1_phone'   => $prefix === 'ic' ? ($r['party_1_phone'] ?? '') : null,
            'party_1_city'    => $prefix === 'ic' ? ($r['party_1_city'] ?? '') : null,
            'party_1_state'   => $prefix === 'ic' ? ($r['party_1_state'] ?? '') : null,
            // Party 2 — Surrenderee
            'party_2'         => $prefix === 'ic' ? ($r['party_2_name'] ?? '') : ($r['Surrenderee'] ?? $r['Grantee'] ?? ''),
            'party_2_address' => $prefix === 'ic' ? ($r['party_2_address'] ?? '') : null,
            'party_2_phone'   => $prefix === 'ic' ? ($r['party_2_phone'] ?? '') : null,
            'party_2_city'    => $prefix === 'ic' ? ($r['party_2_city'] ?? '') : null,
            'party_2_state'   => $prefix === 'ic' ? ($r['party_2_state'] ?? '') : null,
            // Party 3 & 4
            'party_3'         => $prefix === 'ic' ? ($r['party_3_name'] ?? '') : ($prefix === 'fhs' ? ($r['party_3'] ?? '') : null),
            'party_3_address' => $prefix === 'ic' ? ($r['party_3_address'] ?? '') : null,
            'party_3_phone'   => $prefix === 'ic' ? ($r['party_3_phone'] ?? '') : null,
            'party_4'         => $prefix === 'ic' ? ($r['party_4_name'] ?? '') : ($prefix === 'fhs' ? ($r['party_4'] ?? '') : null),
            'party_4_address' => $prefix === 'ic' ? ($r['party_4_address'] ?? '') : null,
            'party_4_phone'   => $prefix === 'ic' ? ($r['party_4_phone'] ?? '') : null,
            // PRA-only: co-surrenderor
            'surrenderor_2' => $prefix === 'pra' ? ($r['Surrenderor_2'] ?? '') : null,
            // Location
            'location' => $prefix === 'ic' ? ($r['property_location'] ?? '') : ($r['location'] ?? ''),
            'district' => $prefix === 'ic' ? ($r['district'] ?? '') : ($r['districtName'] ?? ''),
            'lga'      => $prefix === 'ic' ? ($r['lga'] ?? '') : ($r['lgsaOrCity'] ?? ''),
            // IC-only extras
            'entry_date'                          => $prefix === 'ic' ? ($r['entry_date'] ?? '') : null,
            'bank_name'                           => $prefix === 'ic' ? ($r['bank_name'] ?? '') : null,
            'surrender_reason'                    => $prefix === 'ic' ? ($r['surrender_reason'] ?? '') : null,
            'compensation_amount'                 => $prefix === 'ic' ? ($r['compensation_amount'] ?? '') : null,
            'release_reg_particulars'             => $prefix === 'ic' ? ($r['release_reg_particulars'] ?? '') : null,
            'original_instrument_reg_particulars' => $prefix === 'ic' ? ($r['original_instrument_reg_particulars'] ?? '') : null,
            // IC property
            'property_description' => $prefix === 'ic' ? ($r['property_description'] ?? '') : null,
            'plot_number'          => $prefix === 'ic' ? ($r['plot_number'] ?? '') : null,
            'plot_size'            => $prefix === 'ic' ? ($r['plot_size'] ?? '') : null,
            // IC solicitor
            'solicitor_name'    => $prefix === 'ic' ? ($r['solicitor_name'] ?? '') : null,
            'solicitor_address' => $prefix === 'ic' ? ($r['solicitor_address'] ?? '') : null,
            // PRA/FHS-only
            'land_use' => in_array($prefix, ['pra', 'fhs']) ? strtoupper($r['land_use'] ?? '') : null,
            'comments' => in_array($prefix, ['pra', 'fhs']) ? ($r['comments'] ?? '') : null,
        ];

        return response()->json($normalized);
    }

    /**
     * Persist edits back to the originating table.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        [$prefix, $realId] = $this->parseCompositeId($id);
        $table = $this->tableForPrefix($prefix);

        $surrenderTypes = [
            'Deed of Surrender and Release',
            'Deed of Surrender & Release',
            'Deed of Surrender, and Release',
            'Surrender and Release',
            'Surrender & Release',
        ];

        $rules = [
            'instrument_type'          => ['required', Rule::in($surrenderTypes)],
            'registration_particulars' => ['nullable', 'string', 'max:100'],
            'transaction_date'         => ['nullable', 'date'],
            'party_1'                  => ['nullable', 'string', 'max:255'],
            'party_2'                  => ['nullable', 'string', 'max:255'],
            'location'                 => ['nullable', 'string', 'max:500'],
        ];

        $v  = $request->validate($rules);
        $in = $request->input();

        $data = ['updated_at' => Carbon::now()];
        $str  = fn($k) => isset($in[$k]) && trim($in[$k]) !== '' ? trim($in[$k]) : null;
        $dt   = fn($k) => isset($in[$k]) && trim($in[$k]) !== '' ? trim($in[$k]) : null;

        if ($prefix === 'ic') {
            $data['instrument_type']     = $v['instrument_type'];
            $data['registration_number'] = $v['registration_particulars'] ?? null;
            $data['reg_date']            = $v['transaction_date'] ?? null;
            $data['entry_date']          = $dt('entry_date');
            // Party 1 — Surrenderor
            $data['party_1_name']    = $str('party_1');
            $data['party_1_address'] = $str('party_1_address');
            $data['party_1_phone']   = $str('party_1_phone');
            $data['party_1_city']    = $str('party_1_city');
            $data['party_1_state']   = $str('party_1_state');
            // Party 2 — Surrenderee
            $data['party_2_name']    = $str('party_2');
            $data['party_2_address'] = $str('party_2_address');
            $data['party_2_phone']   = $str('party_2_phone');
            $data['party_2_city']    = $str('party_2_city');
            $data['party_2_state']   = $str('party_2_state');
            // Party 3 — Co-Surrenderor
            $data['party_3_name']    = $str('party_3');
            $data['party_3_address'] = $str('party_3_address');
            $data['party_3_phone']   = $str('party_3_phone');
            $data['party_4_name']    = $str('party_4');
            $data['party_4_address'] = $str('party_4_address');
            $data['party_4_phone']   = $str('party_4_phone');
            // Surrender details
            $data['bank_name']                           = $str('bank_name');
            $data['surrender_reason']                    = $str('surrender_reason');
            $data['compensation_amount']                 = $str('compensation_amount');
            $data['release_reg_particulars']             = $str('release_reg_particulars');
            $data['original_instrument_reg_particulars'] = $str('original_instrument_reg_particulars');
            // Property
            $data['property_description'] = $str('property_description');
            $data['plot_number']          = $str('plot_number');
            $data['plot_size']            = $str('plot_size');
            $data['district']             = $str('district');
            $data['lga']                  = $str('lga');
            $data['property_location']    = $str('location');
            // Solicitor
            $data['solicitor_name']    = $str('solicitor_name');
            $data['solicitor_address'] = $str('solicitor_address');

        } elseif ($prefix === 'pra') {
            $data['instrument_type'] = $v['instrument_type'];
            $data['regNo']           = $v['registration_particulars'] ?? null;
            $data['deeds_date']      = $v['transaction_date'] ?? null;
            $data['Surrenderor']     = $str('party_1');
            $data['Surrenderee']     = $str('party_2');
            $data['Surrenderor_2']   = $str('surrenderor_2');
            $data['districtName']    = $str('district');
            $data['lgsaOrCity']      = $str('lga');
            $data['location']        = $str('location');
            $data['land_use']        = $str('land_use');
            $data['comments']        = $str('comments');

        } else { // fhs
            $data['instrument_type'] = $v['instrument_type'];
            $data['regNo']           = $v['registration_particulars'] ?? null;
            $data['deeds_date']      = $v['transaction_date'] ?? null;
            $data['Surrenderor']     = $str('party_1');
            $data['Surrenderee']     = $str('party_2');
            $data['party_3']         = $str('party_3');
            $data['party_4']         = $str('party_4');
            $data['districtName']    = $str('district');
            $data['lgsaOrCity']      = $str('lga');
            $data['location']        = $str('location');
            $data['land_use']        = $str('land_use');
            $data['comments']        = $str('comments');
        }

        DB::connection('sqlsrv')->table($table)->where('id', $realId)->update($data);

        return response()->json(['success' => true, 'message' => 'Record updated successfully.']);
    }

    // -------------------------------------------------------------------------

    private function parseCompositeId(string $id): array
    {
        $parts = explode('_', $id, 2);
        if (count($parts) !== 2 || !in_array($parts[0], ['ic', 'pra', 'fhs'])) {
            abort(400, 'Invalid record identifier.');
        }
        return [$parts[0], (int) $parts[1]];
    }

    private function tableForPrefix(string $prefix): string
    {
        return match ($prefix) {
            'ic'  => 'instrument_capture',
            'pra' => 'pra',
            'fhs' => 'file_history_staging',
        };
    }

    /**
     * Get associated mortgages for a given file number or property ID.
     */
    protected function getAssociatedMortgages(?string $fileNumber, ?string $propId): array
    {
        if (empty($fileNumber) && empty($propId)) {
            return [];
        }

        $connection = DB::connection('sqlsrv');

        // 1. Check instrument_capture
        $icQuery = $connection->table('instrument_capture')
            ->select([
                'id',
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno) as file_number"),
                'registration_number as registration_particulars',
                'instrument_type',
                'party_1_name as party_1',
                'party_2_name as party_2',
                'party_3_name as party_3',
                'property_location as location',
                'created_at as date_captured',
                DB::raw("'Instrument Capture' as source")
            ])
            ->where(function($q) {
                $q->whereIn('instrument_type', [
                    'Deed of Mortgage',
                    'Tripartite Mortgage',
                    'Mortgage',
                    'Deed of Tripartite Mortgage',
                    'Legal Mortgage',
                    'Equitable Mortgage'
                ])
                ->orWhereRaw("LOWER(instrument_type) LIKE '%mortgage%'");
            })
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        // 2. Check pra
        $praQuery = $connection->table('pra')
            ->select([
                'id',
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno) as file_number"),
                DB::raw("CAST(ISNULL(regNo, '') AS NVARCHAR(MAX)) as registration_particulars"),
                'instrument_type',
                DB::raw("COALESCE(Mortgagor, Grantor) as party_1"),
                DB::raw("COALESCE(Mortgagee, Grantee) as party_2"),
                DB::raw("NULL as party_3"),
                'location',
                'created_at as date_captured',
                DB::raw("'Property Records' as source")
            ])
            ->where(function($q) {
                $q->whereIn('instrument_type', [
                    'Deed of Mortgage',
                    'Tripartite Mortgage',
                    'Mortgage',
                    'Deed of Tripartite Mortgage',
                    'Legal Mortgage',
                    'Equitable Mortgage'
                ])
                ->orWhereRaw("LOWER(instrument_type) LIKE '%mortgage%'");
            })
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        // 3. Check file_history_staging
        $fhsQuery = $connection->table('file_history_staging')
            ->select([
                'id',
                'prop_id',
                DB::raw("COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, fileno, temp_fileno) as file_number"),
                DB::raw("CAST(ISNULL(regNo, '') AS NVARCHAR(MAX)) as registration_particulars"),
                'instrument_type',
                DB::raw("COALESCE(Mortgagor, Grantor) as party_1"),
                DB::raw("COALESCE(Mortgagee, Grantee) as party_2"),
                'party_3',
                'location',
                'created_at as date_captured',
                DB::raw("'File History Staging' as source")
            ])
            ->where(function($q) {
                $q->whereIn('instrument_type', [
                    'Deed of Mortgage',
                    'Tripartite Mortgage',
                    'Mortgage',
                    'Deed of Tripartite Mortgage',
                    'Legal Mortgage',
                    'Equitable Mortgage'
                ])
                ->orWhereRaw("LOWER(instrument_type) LIKE '%mortgage%'");
            })
            ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); });

        // Filter by prop_id or fileNumber using specific table columns to prevent "column not found" errors
        
        // Instrument Capture specific filter
        $icQuery->where(function($q) use ($fileNumber, $propId) {
            $hasCond = false;
            if (!empty($propId)) {
                $q->where('prop_id', $propId);
                $hasCond = true;
            }
            if (!empty($fileNumber) && $fileNumber !== '—') {
                if ($hasCond) {
                    $q->orWhere('mlsFNo', $fileNumber)
                      ->orWhere('kangisFileNo', $fileNumber)
                      ->orWhere('NewKANGISFileno', $fileNumber)
                      ->orWhere('temp_fileno', $fileNumber);
                } else {
                    $q->where(function($sub) use ($fileNumber) {
                        $sub->where('mlsFNo', $fileNumber)
                            ->orWhere('kangisFileNo', $fileNumber)
                            ->orWhere('NewKANGISFileno', $fileNumber)
                            ->orWhere('temp_fileno', $fileNumber);
                    });
                }
            }
        });

        // PRA specific filter
        $praQuery->where(function($q) use ($fileNumber, $propId) {
            $hasCond = false;
            if (!empty($propId)) {
                $q->where('prop_id', $propId);
                $hasCond = true;
            }
            if (!empty($fileNumber) && $fileNumber !== '—') {
                if ($hasCond) {
                    $q->orWhere('mlsFNo', $fileNumber)
                      ->orWhere('kangisFileNo', $fileNumber)
                      ->orWhere('NewKANGISFileno', $fileNumber)
                      ->orWhere('fileno', $fileNumber);
                } else {
                    $q->where(function($sub) use ($fileNumber) {
                        $sub->where('mlsFNo', $fileNumber)
                            ->orWhere('kangisFileNo', $fileNumber)
                            ->orWhere('NewKANGISFileno', $fileNumber)
                            ->orWhere('fileno', $fileNumber);
                    });
                }
            }
        });

        // File History Staging specific filter
        $fhsQuery->where(function($q) use ($fileNumber, $propId) {
            $hasCond = false;
            if (!empty($propId)) {
                $q->where('prop_id', $propId);
                $hasCond = true;
            }
            if (!empty($fileNumber) && $fileNumber !== '—') {
                if ($hasCond) {
                    $q->orWhere('mlsFNo', $fileNumber)
                      ->orWhere('kangisFileNo', $fileNumber)
                      ->orWhere('NewKANGISFileno', $fileNumber)
                      ->orWhere('fileno', $fileNumber)
                      ->orWhere('temp_fileno', $fileNumber);
                } else {
                    $q->where(function($sub) use ($fileNumber) {
                        $sub->where('mlsFNo', $fileNumber)
                            ->orWhere('kangisFileNo', $fileNumber)
                            ->orWhere('NewKANGISFileno', $fileNumber)
                            ->orWhere('fileno', $fileNumber)
                            ->orWhere('temp_fileno', $fileNumber);
                    });
                }
            }
        });

        $unionQuery = $icQuery->unionAll($praQuery)->unionAll($fhsQuery);
        return $unionQuery->get()->toArray();
    }
}
