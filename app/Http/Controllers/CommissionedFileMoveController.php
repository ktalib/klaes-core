<?php

namespace App\Http\Controllers;

use App\Services\FileNumber\CommissionedFileMoveService;
use App\Services\FileNumber\StaleNextNumberException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * "Move Commissioned File" on the MLPP File Number Generator list.
 * See App\Services\FileNumber\CommissionedFileMoveService.
 */
class CommissionedFileMoveController extends Controller
{
    public function __construct(private CommissionedFileMoveService $mover)
    {
    }

    public function preview(Request $request): JsonResponse
    {
        if ($denied = $this->deny()) {
            return $denied;
        }

        $validated = $request->validate(['file_number' => 'required|string|max:100']);

        try {
            return response()->json(['success' => true, 'data' => $this->mover->preview($validated['file_number'])]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Move Commissioned File preview failed', ['file_number' => $validated['file_number'], 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not prepare the move.'], 500);
        }
    }

    public function move(Request $request): JsonResponse
    {
        if ($denied = $this->deny()) {
            return $denied;
        }

        $validated = $request->validate([
            'file_number' => 'required|string|max:100',
            'new_file_number' => 'required|string|max:100',
        ]);

        $user = Auth::user();
        $movedBy = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? $user->email ?? 'System');

        try {
            $result = $this->mover->move($validated['file_number'], $validated['new_file_number'], $user->id, $movedBy);
        } catch (StaleNextNumberException $e) {
            return response()->json([
                'success' => false,
                'stale' => true,
                'new_file_number' => $e->currentNext,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Move Commissioned File failed', [
                'file_number' => $validated['file_number'],
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Could not move the file. Nothing was changed.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "{$result['old_file_number']} moved to {$result['new_file_number']}.",
            'data' => $result,
        ]);
    }

    /** Same bar as the other destructive actions in this menu. */
    private function deny(): ?JsonResponse
    {
        if ((Auth::user()->assign_role ?? null) === 'Supper Admin') {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to move a commissioned file.',
        ], 403);
    }
}
