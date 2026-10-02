<?php

namespace App\Http\Controllers\LandsOneStopShop;

use App\Http\Controllers\Controller;
use App\Models\LandsOneStopShopApplication;
use App\Models\StreetName;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LossOfDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $limit = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = LandsOneStopShopApplication::query()
            ->where('application_type', 'loss-of-document')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('location', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        return view('lands_one_stop_shop.loss_of_document', compact(
            'records', 'limit', 'states', 'lgas', 'districts', 'streetNames'
        ));
    }

    public function show(int $id): JsonResponse
    {
        $record = LandsOneStopShopApplication::query()
            ->where('application_type', 'loss-of-document')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['application_type'] = 'loss-of-document';
        $data['captured_by'] = Auth::id();
        $data['status'] = LandsOneStopShopApplication::STATUS_PENDING;

        if ($request->hasFile('passport_photo')) {
            $path = $request->file('passport_photo')->store('public/loss_of_document_passports');
            $data['passport_photo'] = $path;
        }

        if (isset($data['passport_photo']) && $data['passport_photo'] instanceof \Illuminate\Http\UploadedFile) {
            unset($data['passport_photo']);
        }

        $record = LandsOneStopShopApplication::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Loss of document application created successfully.',
            'data' => $record,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = LandsOneStopShopApplication::query()
            ->where('application_type', 'loss-of-document')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['application_type'] = 'loss-of-document';
        $data['updated_by'] = Auth::id();

        if ($request->hasFile('passport_photo')) {
            $path = $request->file('passport_photo')->store('public/loss_of_document_passports');
            $data['passport_photo'] = $path;
        }

        if (isset($data['passport_photo']) && $data['passport_photo'] instanceof \Illuminate\Http\UploadedFile) {
            unset($data['passport_photo']);
        }

        $record->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Loss of document application updated successfully.',
            'data' => $record->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = LandsOneStopShopApplication::query()
            ->where('application_type', 'loss-of-document')
            ->findOrFail($id);

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Loss of document application deleted successfully.',
        ]);
    }

    private function rules(): array
    {
        return [
            'applicant_name'          => 'required|string|max:255',
            'file_no'                 => 'nullable|string|max:255',
            'location'                => 'nullable|string|max:2000',
            'residential_address'     => 'nullable|string|max:2000',
            'correspondence_address'  => 'nullable|string|max:2000',
            'nationality'             => 'nullable|string|max:255',
            'occupation'              => 'nullable|string|max:255',
            'phone'                   => 'nullable|string|max:50',
            'email'                   => 'nullable|email|max:255',
            'land_use'                => 'nullable|string|max:255',
            'plot_no'                 => 'nullable|string|max:100',
            'plan_no'                 => 'nullable|string|max:100',
            'remarks'                 => 'nullable|string',
            'passport_photo'          => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ];
    }
}
