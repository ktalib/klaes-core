<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModules;
use App\Services\TransactionFileNumberCorrectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * "Correct File No. (Main ↔ Temp)" action on the PRA (/propertycard), File History
 * (/file-index-view) and CofO (/propertycard/cofo) transaction tables.
 * See TransactionFileNumberCorrectionService.
 */
class TransactionFileNumberCorrectionController extends Controller
{
    use AuthorizesModules;

    public const MODULE = 'Deeds - Property Records';

    public function __construct(private TransactionFileNumberCorrectionService $service)
    {
    }

    public function candidates(Request $request)
    {
        $this->authorizeModule(self::MODULE, 'edit');

        $validated = $request->validate([
            'table' => 'required|string|in:' . implode(',', array_keys(TransactionFileNumberCorrectionService::TABLES)),
            'id' => 'required|integer|min:1',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->candidates($validated['table'], (int) $validated['id']),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request)
    {
        $this->authorizeModule(self::MODULE, 'edit');

        $validated = $request->validate([
            'table' => 'required|string|in:' . implode(',', array_keys(TransactionFileNumberCorrectionService::TABLES)),
            'id' => 'required|integer|min:1',
            'target' => 'required|string|in:main,temp',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->service->correct(
                $validated['table'],
                (int) $validated['id'],
                $validated['target'],
                (string) ($validated['reason'] ?? ''),
                Auth::id()
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Transaction file number correction failed', [
                'table' => $validated['table'],
                'id' => $validated['id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'The correction could not be saved.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Transaction moved from {$result['from']} to {$result['to']}. prop_id unchanged.",
            'data' => $result,
        ]);
    }
}
