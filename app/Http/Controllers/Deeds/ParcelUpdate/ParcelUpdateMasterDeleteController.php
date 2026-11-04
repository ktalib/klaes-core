<?php

namespace App\Http\Controllers\Deeds\ParcelUpdate;

use App\Http\Controllers\Controller;
use App\Services\ParcelUpdateMasterDelete;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Master Delete on the Parcel Update listings. Super Admin only, like the MLPP
 * File Number Generator's Master Delete; see ParcelUpdateMasterDelete for the rules.
 */
class ParcelUpdateMasterDeleteController extends Controller
{
    public function __invoke(string $type, int $id, ParcelUpdateMasterDelete $service): JsonResponse
    {
        if (!Auth::user() || Auth::user()->assign_role !== 'Supper Admin') {
            return response()->json(['success' => false, 'message' => 'Only a Super Admin can master-delete.'], 403);
        }

        $result = $service->delete($type, $id, Auth::id());

        $code = [
            'deleted'         => 200,
            'already_deleted' => 409,
            'commissioned'    => 409,
            'not_found'       => 404,
            'bad_type'        => 404,
        ][$result['status']] ?? 422;

        return response()->json([
            'success' => $result['status'] === 'deleted',
            'message' => $result['message'],
        ], $code);
    }
}
