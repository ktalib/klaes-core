<?php

namespace App\Http\Controllers\LandsOneStopShop;

use App\Http\Controllers\Controller;
use App\Models\LandsOneStopShop\LandTemporaryFileNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TemporaryFileController extends Controller
{
    public function index(Request $request): View
    {
        $limit  = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = LandTemporaryFileNumber::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%")
                        ->orWhere('address', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $lgas      = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();

        return view('lands_one_stop_shop.temporary_file', compact('records', 'limit', 'search', 'lgas', 'districts'));
    }

    public function show(int $id): JsonResponse
    {
        $record = LandTemporaryFileNumber::findOrFail($id);

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

        $data                = $validator->validated();
        
        if ($data['district'] === 'OTHER' && $request->filled('district_specify')) {
            $data['district'] = $request->input('district_specify');
        }

        $data['captured_by'] = Auth::id() ?? 1;
        $data['status']      = 'Pending';

        $record = LandTemporaryFileNumber::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Temporary File application created successfully.',
            'data'    => $record,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record    = LandTemporaryFileNumber::findOrFail($id);
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data               = $validator->validated();

        if ($data['district'] === 'OTHER' && $request->filled('district_specify')) {
            $data['district'] = $request->input('district_specify');
        }

        $data['updated_by'] = Auth::id() ?? 1;

        $record->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Temporary File application updated successfully.',
            'data'    => $record->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = LandTemporaryFileNumber::findOrFail($id);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Temporary File application deleted successfully.',
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $record = LandTemporaryFileNumber::findOrFail($id);
        $record->update([
            'status'     => 'Approved',
            'updated_by' => Auth::id() ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application approved successfully.',
        ]);
    }

    public function generateRecommendation(int $id): JsonResponse
    {
        $record = LandTemporaryFileNumber::where('status', 'Approved')->findOrFail($id);
        $record->update([
            'recommendation_generated_at' => now(),
            'updated_by'                  => Auth::id() ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recommendation generated.',
        ]);
    }

    public function printRecommendation(int $id)
    {
        $record = LandTemporaryFileNumber::findOrFail($id);

        return view('lands_one_stop_shop.print.temporary_file_recommendation', compact('record'));
    }

    private function rules(): array
    {
        return [
            'applicant_name' => 'required|string|max:255',
            'file_no'        => 'required|string|max:255',
            'plot_no'        => 'nullable|string|max:100',
            'district'       => 'nullable|string|max:255',
            'district_specify' => 'nullable|string|max:255',
            'lga'            => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'address'        => 'nullable|string|max:2000',
            'remarks'        => 'required|string|max:2000',
        ];
    }
}

 