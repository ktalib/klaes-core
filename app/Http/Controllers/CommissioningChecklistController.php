<?php

namespace App\Http\Controllers;

use App\Services\CommissioningChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The commissioning checklist card on the File Commissioning list, and the automatic
 * check the commissioning modal runs straight after a file or batch is issued.
 */
class CommissioningChecklistController extends Controller
{
    public function check(Request $request, CommissioningChecklistService $checklist): JsonResponse
    {
        $validated = $request->validate([
            'file_number'    => 'nullable|string|max:150',
            'batch_no'       => 'nullable|string|max:100',
            'file_numbers'   => 'nullable|array|max:3000',
            'file_numbers.*' => 'nullable|string|max:150',
        ]);

        $numbers = array_values(array_filter(array_map('trim', $validated['file_numbers'] ?? [])));
        if (!empty($validated['batch_no'])) {
            $numbers = array_merge($numbers, $checklist->batchFileNumbers(trim($validated['batch_no'])));
        }
        if (!empty($validated['file_number'])) {
            $numbers[] = trim($validated['file_number']);
        }

        if (!$numbers) {
            return response()->json(['success' => false, 'message' => 'No file number or batch to check.'], 422);
        }

        try {
            return response()->json(['success' => true] + $checklist->check($numbers));
        } catch (\Throwable $e) {
            \Log::error('Commissioning checklist failed', ['error' => $e->getMessage(), 'count' => count($numbers)]);

            return response()->json(['success' => false, 'message' => 'The checklist could not be run: ' . $e->getMessage()], 500);
        }
    }
}
