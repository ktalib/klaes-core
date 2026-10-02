<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * The two things every Master Delete has in common: who may run one, and the
 * record of having run it.
 *
 * Shared by the Recommendation and RofO master deletes (Land, SLTR, ST) so the
 * gate cannot drift between screens — the same wording as the MLS and OP master
 * deletes it sits alongside.
 */
trait ExecutesMasterDelete
{
    /**
     * Only a Supper Admin may erase a record across tables.
     *
     * Checked on `assign_role` exactly as FileNumberController and
     * OpResettlementApplicationController do. The UI hides the entry as well, but
     * this is the gate that matters: the endpoints are reachable directly.
     *
     * @return JsonResponse|null a 403 to return, or null to carry on
     */
    protected function denyUnlessMasterDeleter(): ?JsonResponse
    {
        if (!Auth::user() || Auth::user()->assign_role !== 'Supper Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action. Only Supper Admin can execute a Master Delete.',
            ], 403);
        }

        return null;
    }

    /**
     * The operator has to type the document's own number back.
     *
     * Not a formality: these screens are lists, the entry sits in a row menu next
     * to Print, and there is no undo behind it. Comparison is case-insensitive and
     * trimmed — the number is being copied off the screen, not remembered.
     *
     * @return JsonResponse|null a 422 to return, or null to carry on
     */
    protected function denyUnlessConfirmationMatches(Request $request, ?string $expected): ?JsonResponse
    {
        $typed = trim((string) $request->input('confirm', ''));

        if ($typed === '' || strcasecmp($typed, trim((string) $expected)) !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation text does not match ' . ($expected ?: 'the record') . '.',
            ], 422);
        }

        return null;
    }

    /**
     * Record what was erased. A master delete leaves nothing behind to inspect,
     * so the audit entry is the only surviving account of it — it carries the
     * whole pre-delete row and the per-table counts.
     *
     * Never allowed to fail the delete: the rows are already gone by the time this
     * runs, so a broken audit table must not roll the work back.
     */
    protected function logMasterDelete(
        string $resourceType,
        $resourceId,
        array $snapshot,
        array $counts,
        string $what
    ): void {
        $detail = implode(', ', array_map(
            fn ($table, $n) => "{$table} ({$n})",
            array_keys($counts),
            array_values($counts)
        ));

        try {
            app(\App\Services\AuditService::class)->logAction(
                'DELETED',
                $resourceType,
                $resourceId,
                $snapshot,
                null,
                "Master Delete executed for {$what}. Affected: {$detail}."
            );
        } catch (\Throwable $e) {
            Log::warning('AuditLog failed during master delete', [
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'error'         => $e->getMessage(),
            ]);
        }

        Log::warning("Master Delete: {$what}", [
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'user_id'       => Auth::id(),
            'counts'        => $counts,
        ]);
    }
}
