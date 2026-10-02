<?php

namespace App\Http\Controllers;

use App\Services\CommissioningRegistryMoveService;
use App\Support\OssOpCommissionFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Re-files a commissioned file under the other commissioning registry.
 *
 * Serves both entry points: "Move to OSS File Commissioning" on the MLS File
 * Number Generator list, and "Move to Land" on the OSS list. One endpoint, because
 * the two directions differ only by the target stamp and must not be allowed to
 * drift apart.
 */
class CommissioningRegistryController extends Controller
{
    public function __construct(private CommissioningRegistryMoveService $mover)
    {
    }

    public function move(Request $request): JsonResponse
    {
        // A move re-files a file between two modules' worklists, so it is held to the
        // same bar as the destructive actions that already sit in these menus.
        if (!$this->authorizedToMove()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to move a file between commissioning registries.',
            ], 403);
        }

        $validated = $request->validate([
            'file_number' => 'required|string|max:100',
            'target' => 'required|string|in:' . OssOpCommissionFilter::MLS . ',' . OssOpCommissionFilter::OSS,
        ]);

        try {
            $result = $this->mover->move(
                $validated['file_number'],
                $validated['target'],
                Auth::id()
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Commissioning registry move failed', [
                'file_number' => $validated['file_number'],
                'target' => $validated['target'],
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not move the file. Nothing was changed.',
            ], 500);
        }

        // "not_found" is the caller naming a file that is not live; that is a bad
        // request rather than a server fault, and the row is left untouched.
        $status = $result['status'] === 'not_found' ? 404 : 200;

        return response()->json([
            'success' => $result['status'] !== 'not_found',
            'message' => $result['message'],
            'data' => $result,
        ], $status);
    }

    private function authorizedToMove(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return ($user->assign_role ?? null) === 'Supper Admin';
    }
}
