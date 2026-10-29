<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExecutesMasterDelete;
use App\Services\InstrumentRegistrationPurgeService;
use App\Services\RegistrationMoveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Master Delete and Move Registration for the Instrument Capture and Instrument
 * Registration tables. Both are Supper Admin only (ExecutesMasterDelete), like
 * every other Master Delete; the menus hide the entries from everyone else.
 *
 * {ref} is the row id the screen uses: `deed_reg_{id}` / `instr_capture_{id}`
 * on Instrument Registration, the bare capture id on Instrument Capture.
 */
class InstrumentMasterActionsController extends Controller
{
    use ExecutesMasterDelete;

    public function __construct(
        protected InstrumentRegistrationPurgeService $purge,
        protected RegistrationMoveService $mover
    ) {
    }

    /** What a Master Delete of this row would remove, and anything blocking it. */
    public function deletePreview(string $ref): JsonResponse
    {
        if ($deny = $this->denyUnlessMasterDeleter()) {
            return $deny;
        }

        try {
            $unit = $this->purge->resolve($ref);
            $preview = $this->purge->preview($unit);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'reference' => $unit['reference'],
            'label' => $unit['label'],
            'counts' => $preview['counts'],
            'blockers' => $preview['blockers'],
        ]);
    }

    public function masterDestroy(Request $request, string $ref): JsonResponse
    {
        if ($deny = $this->denyUnlessMasterDeleter()) {
            return $deny;
        }

        try {
            $unit = $this->purge->resolve($ref);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($deny = $this->denyUnlessConfirmationMatches($request, $unit['reference'])) {
            return $deny;
        }

        $snapshot = [
            'instrument_capture' => $unit['capture'],
            'deed_registrations' => $unit['deeds']->values()->all(),
        ];

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $counts = $this->purge->purge($unit);
            DB::connection('sqlsrv')->commit();
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Instrument master delete failed', ['ref' => $ref, 'error' => $e->getMessage()]);

            $status = $e instanceof \RuntimeException ? 422 : 500;

            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }

        $this->logMasterDelete(
            'instrument_registration',
            $unit['capture']->id ?? ($unit['deeds']->first()->id ?? $ref),
            $snapshot,
            $counts,
            'Instrument ' . $unit['label']
        );

        $freed = $unit['deeds']->pluck('registration_number')->filter()->implode(', ');

        return response()->json([
            'success' => true,
            'message' => $unit['label'] . ' deleted.'
                . ($freed ? " Registration number {$freed} is free again and appears in the missing-number list for backfill." : ''),
            'details' => $counts,
        ]);
    }

    /** The registration and every instrument type it can move to, with each one's next number. */
    public function moveOptions(string $ref): JsonResponse
    {
        if ($deny = $this->denyUnlessMasterDeleter()) {
            return $deny;
        }

        try {
            return response()->json(['success' => true] + $this->mover->options($this->deedIdFor($ref)));
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function move(Request $request, string $ref): JsonResponse
    {
        if ($deny = $this->denyUnlessMasterDeleter()) {
            return $deny;
        }

        $request->validate([
            'target_type' => 'required|string|max:255',
        ]);

        // Always the target's next number: missing numbers are reused only by a
        // fresh registration (the capture screen's backfill), never by a move.
        try {
            $result = $this->mover->move($this->deedIdFor($ref), $request->input('target_type'));
        } catch (\Throwable $e) {
            Log::warning('Registration move refused', ['ref' => $ref, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        try {
            app(\App\Services\AuditService::class)->logAction(
                'UPDATED',
                'instrument_registration',
                $result['snapshot']['id'] ?? $ref,
                $result['snapshot'],
                ['instrument_type' => $result['to'], 'registration_number' => $result['to_number']],
                "Registration moved from {$result['from']} {$result['from_number']} to {$result['to']} {$result['to_number']}."
            );
        } catch (\Throwable $e) {
            Log::warning('AuditLog failed during registration move', ['ref' => $ref, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => "Moved from {$result['from']} {$result['from_number']} to {$result['to']} {$result['to_number']}. "
                . "{$result['from_number']} is now free in the {$result['from']} register."
                . ($result['voided_documents'] ? ' The old RDS / CoR were voided; generate and print them again.' : ''),
            'registration_number' => $result['to_number'],
        ]);
    }

    /** A Move works on the registration; a capture row resolves to the registration it produced. */
    private function deedIdFor(string $ref): int
    {
        if (preg_match('/^deed_reg_(\d+)$/', $ref, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^(?:instr_capture_)?(\d+)$/', $ref, $m)) {
            $ids = DB::connection('sqlsrv')->table('deed_registrations')
                ->where('instrument_capture_id', (int) $m[1])
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->pluck('id');

            if ($ids->count() === 1) {
                return (int) $ids->first();
            }

            throw new \RuntimeException($ids->isEmpty()
                ? 'This instrument has not been registered yet, so there is no registration to move.'
                : 'This instrument has more than one registration; move it from the Instrument Registration screen.');
        }

        throw new \RuntimeException('Unrecognised record reference.');
    }
}
