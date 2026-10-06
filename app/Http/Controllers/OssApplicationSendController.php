<?php

namespace App\Http\Controllers;

use App\Services\OssApplicationSendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Send to OSS Applications" on the action menus. Each module has its own endpoint
 * so the list (and its flags) is fixed by where the officer clicked, never posted.
 */
class OssApplicationSendController extends Controller
{
    /** MLPP File Commissioning → Applications (No Change of Ownership). */
    public function fromMlpp(Request $request, OssApplicationSendService $service): JsonResponse
    {
        $validated = $request->validate(['file_number' => 'required|string|max:150']);

        return $this->respond($service->sendNoChange($validated['file_number']));
    }

    /** OSS FC / FEFR → Applications (Change of Ownership). */
    public function fromOpPage(Request $request, OssApplicationSendService $service): JsonResponse
    {
        $validated = $request->validate(['pra_id' => 'required|integer|min:1']);

        return $this->respond($service->sendChangeOfOwnership((int) $validated['pra_id']));
    }

    private function respond(array $result): JsonResponse
    {
        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
