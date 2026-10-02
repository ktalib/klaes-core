<?php

namespace App\Http\Controllers;

use App\Models\PlotExtension;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MlsPlotExtensionController extends Controller
{
    /**
     * Store a Plot Extension transaction.
     *
     * Unlike a File Extension (which generates a brand-new "<no> AND EXTENSION"
     * file number), a Plot Extension RETAINS the original file number and is saved
     * as an isolated transaction linked to that original file. No serial is consumed
     * and no record is created in mls_file_no / fileNumber.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'original_file_no' => 'required|string|max:100',
            'tracking_id'      => 'nullable|string|max:50',
            'file_name'        => 'nullable|string|max:500',
            'land_use'         => 'nullable|string|max:50',
            'purpose_id'       => 'nullable|integer',
            'location'         => 'nullable|string',
            'lga'              => 'nullable|string|max:255',
            'district'         => 'nullable|string|max:255',
            'plot_no'          => 'nullable|string|max:100',
            'tp_no'            => 'nullable|string|max:100',
            'customer_type'    => 'nullable|string|max:100',
            'phone_no'         => 'nullable|string|max:100',
            'address'          => 'nullable|string|max:1000',
            'is_indexed'       => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $createdBy = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($createdBy === '') {
            $createdBy = $user->name ?? $user->email ?? 'System';
        }

        try {
            $record = PlotExtension::create([
                'original_file_no' => $validated['original_file_no'],
                'tracking_id'      => $validated['tracking_id'] ?? null,
                'file_name'        => $validated['file_name'] ?? null,
                'land_use'         => $validated['land_use'] ?? null,
                'purpose_id'       => $validated['purpose_id'] ?? null,
                'location'         => $validated['location'] ?? null,
                'lga'              => $validated['lga'] ?? null,
                'district'         => $validated['district'] ?? null,
                'plot_no'          => $validated['plot_no'] ?? null,
                'tp_no'            => $validated['tp_no'] ?? null,
                'customer_type'    => $validated['customer_type'] ?? null,
                'phone_no'         => $validated['phone_no'] ?? null,
                'address'          => $validated['address'] ?? null,
                'is_indexed'       => (bool) ($validated['is_indexed'] ?? false),
                'created_by'       => $createdBy,
                'is_deleted'       => 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plot Extension saved successfully for ' . $record->original_file_no . '.',
                'data'    => [
                    'id'          => $record->id,
                    'file_number' => $record->original_file_no,
                    'file_name'   => $record->file_name,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Plot Extension store failed', [
                'error'            => $e->getMessage(),
                'original_file_no' => $validated['original_file_no'] ?? null,
                'user_id'          => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save Plot Extension: ' . $e->getMessage(),
            ], 500);
        }
    }
}
