<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\PropertyRecordFormatter;

class PropertyIndexCardController extends Controller
{
    public function index()
    {
        $PageTitle = 'Property Index Card Assistant';
        $PageDescription = '';

        // Get only the most recent record for featured display (not entire table)
        $featuredRecord = DB::connection('sqlsrv')
            ->table('pic')
            ->whereRaw("(mlsFNo IS NOT NULL AND mlsFNo != '') 
                        OR (kangisFileNo IS NOT NULL AND kangisFileNo != '') 
                        OR (NewKANGISFileno IS NOT NULL AND NewKANGISFileno != '')")
            ->orderByDesc('id')
            ->first();

        $pageLength = 20; // Pagination default matching DataTables config

        return view('property_index_card.index', compact('pageLength', 'PageTitle', 'PageDescription', 'featuredRecord'));
    }

    public function getData(Request $request)
    {
        try {
            $draw = $request->input('draw', 1);
            $start = $request->input('start', 0);
            $length = min((int) $request->input('length', 20), 200);
            if ($length <= 0) {
                $length = 20;
            }
            $searchValue = $request->input('search.value', '');

            $orderColumn = $request->input('order.0.column', 9);
            $orderDirInput = strtolower($request->input('order.0.dir', 'desc'));
            $orderDir = $orderDirInput === 'asc' ? 'asc' : 'desc';

            // Column mapping must match the DataTable column order in the shared partial:
            // 0=S/N, 1=File Number, 2=Reg Particulars, 3=Instrument Type,
            // 4=Party 1, 5=Party 2, 6=Party 3, 7=Party 4, 8=Location,
            // 9=Date Captured, 10=Actions
            $columns = [
                0  => 'id',               // S/N (serial)
                1  => 'kangisFileNo',      // File Number
                2  => 'regNo',             // Registration Particulars
                3  => 'transaction_type',  // Instrument Type
                4  => 'party_1',           // Party 1
                5  => 'party_2',           // Party 2
                6  => 'party_3',           // Party 3
                7  => 'party_4',           // Party 4
                8  => 'property_description', // Location
                9  => 'created_at',        // Date Captured
                10 => 'id',                // Actions
            ];

            $query = DB::connection('sqlsrv')
                ->table('pic')
                ->select([
                    '*',
                    DB::raw("property_description as location_display"),
                ]);

            // Instrument type filter from dropdown
            if ($request->filled('instrument_type')) {
                $query->where('instrument_type', $request->input('instrument_type'));
            }

            if ($searchValue !== '') {
                $query->where(function ($q) use ($searchValue) {
                    $like = "%{$searchValue}%";

                    $q->where('mlsFNo', 'like', $like)
                        ->orWhere('kangisFileNo', 'like', $like)
                        ->orWhere('NewKANGISFileno', 'like', $like)
                        ->orWhere('fileno', 'like', $like)
                        ->orWhere('temp_fileno', 'like', $like)
                        ->orWhere('property_description', 'like', $like)
                        ->orWhere('location', 'like', $like)
                        ->orWhere('regNo', 'like', $like)
                        ->orWhere('transaction_type', 'like', $like)
                        ->orWhere('instrument_type', 'like', $like)
                        ->orWhere('party_1', 'like', $like)
                        ->orWhere('party_2', 'like', $like)
                        ->orWhere('Grantor', 'like', $like)
                        ->orWhere('Grantee', 'like', $like)
                        ->orWhere('Assignor', 'like', $like)
                        ->orWhere('Assignee', 'like', $like);
                });
            }

            $recordsTotal = 1983;
            $recordsFiltered = !empty($searchValue) || $request->filled('instrument_type')
                ? (clone $query)->count()
                : $recordsTotal;

            $orderColumnName = $columns[$orderColumn] ?? 'created_at';

            $orderedQuery = clone $query;

            if ($orderColumnName === 'transaction_date') {
                $orderedQuery->orderByRaw('CASE WHEN transaction_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('transaction_date', $orderDir)
                    ->orderByDesc('id');
            } else {
                $orderedQuery->orderBy($orderColumnName, $orderDir);
                if ($orderColumnName !== 'id') {
                    $orderedQuery->orderBy('id', 'desc');
                }
            }

            $records = $orderedQuery
                ->skip($start)
                ->take($length)
                ->get();

            $timelineCounts = $this->resolveTimelineCounts($records->pluck('prop_id'));

            $data = $records->map(function ($record) use ($timelineCounts) {
                $normalized = PropertyRecordFormatter::normalizeRecord($record);
                $fileNumberDisplay = PropertyRecordFormatter::resolveFileNumber($normalized);
                $landUseDisplay = PropertyRecordFormatter::deriveLandUse($normalized);
                $locationDisplay = PropertyRecordFormatter::formatLocation($normalized);
                $registrationDisplay = PropertyRecordFormatter::formatRegistrationParticulars($normalized);
                $parties = PropertyRecordFormatter::resolvePartyDetails($normalized);
                $propId = $record->prop_id ?? null;
                $timelineCount = $propId ? (int) ($timelineCounts[$propId] ?? 1) : 1;

                return [
                    'id' => $record->id,
                    'prop_id' => $propId,
                    'kangisFileNo' => $record->kangisFileNo,
                    'mlsFNo' => $record->mlsFNo,
                    'NewKANGISFileno' => $record->NewKANGISFileno,
                    'fileno' => $record->fileno ?? null,
                    'temp_fileno' => $record->temp_fileno ?? null,
                    'property_description' => $record->property_description,
                    'file_number_display' => $fileNumberDisplay,
                    'location' => $record->location_display ?? $locationDisplay,
                    'location_display' => $record->location_display ?? $record->property_description,
                    'regNo' => $registrationDisplay,
                    'registration_particulars' => $registrationDisplay,
                    'land_use' => $landUseDisplay,
                    'land_use_type' => $landUseDisplay,
                    'transaction_type' => $record->transaction_type,
                    'instrument_type' => $record->instrument_type,
                    'transaction_date' => $record->transaction_date,
                    'created_at' => $record->created_at,
                    'timeline_count' => max(1, $timelineCount),
                    // Party fields matching the DataTable columns
                    'party_1' => $record->party_1 ?? $parties['from_value'],
                    'party_2' => $record->party_2 ?? $parties['to_value'],
                    'party_3' => $record->party_3 ?? null,
                    'party_4' => $record->party_4 ?? null,
                    'grantor' => $parties['from_value'],
                    'grantor_label' => $parties['from_label'],
                    'grantee' => $parties['to_value'],
                    'grantee_label' => $parties['to_label'],
                ];
            })->all();

            return response()->json([
                'draw' => (int) $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error in PropertyIndexCardController@getData', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Error retrieving property index card data: ' . $e->getMessage(),
            ]);
        }
    }

    protected function resolveTimelineCounts($propIds)
    {
        $ids = collect($propIds)
            ->filter(function ($id) {
                if ($id === null) {
                    return false;
                }

                $trimmed = trim((string) $id);
                return $trimmed !== '';
            })
            ->map(fn ($id) => is_numeric($id) ? (int) $id : trim((string) $id))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::connection('sqlsrv')
            ->table('file_history_staging')
            ->select('prop_id', DB::raw('COUNT(*) as total'))
            ->whereIn('prop_id', $ids->all())
            ->groupBy('prop_id')
            ->pluck('total', 'prop_id');
    }
}
