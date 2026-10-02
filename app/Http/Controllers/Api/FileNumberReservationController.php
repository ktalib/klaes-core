<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FileNumberReservationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class FileNumberReservationController extends Controller
{
    protected $reservationService;

    public function __construct(FileNumberReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    /**
     * Reserve a single serial number
     *
     * POST /api/file-numbers/reserve
     */
    public function reserve(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prefix' => 'required|string|max:20',
            'land_use' => 'required|string|max:20',
            'year' => 'required|integer|min:2020|max:2050',
            'session_id' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $reservation = $this->reservationService->reserveNextSerial(
                $request->prefix,
                $request->land_use,
                $request->year,
                $request->session_id
            );

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reserve serial number'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Serial number reserved successfully',
                'data' => [
                    'reservation_uuid' => $reservation->reservation_uuid,
                    'serial_number' => $reservation->serial_number,
                    'expires_at' => $reservation->expires_at->toIso8601String(),
                    'expires_in_seconds' => $reservation->getTimeRemaining(),
                    'year' => $reservation->year,
                    'prefix' => $reservation->prefix,
                    'land_use' => $reservation->land_use
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error reserving serial number', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while reserving the serial number'
            ], 500);
        }
    }

    /**
     * Reserve multiple serial numbers for batch generation
     *
     * POST /api/file-numbers/reserve-batch
     */
    public function reserveBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prefix' => 'required|string|max:20',
            'land_use' => 'required|string|max:20',
            'year' => 'required|integer|min:2020|max:2050',
            'quantity' => 'required|integer|min:2|max:100',
            'session_id' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $reservations = $this->reservationService->reserveBatchSerials(
                $request->prefix,
                $request->land_use,
                $request->year,
                $request->quantity,
                $request->session_id
            );

            $serialNumbers = array_map(fn($r) => $r->serial_number, $reservations);

            return response()->json([
                'success' => true,
                'message' => 'Batch serial numbers reserved successfully',
                'data' => [
                    'reservation_uuids' => array_map(fn($r) => $r->reservation_uuid, $reservations),
                    'serial_numbers' => $serialNumbers,
                    'start_serial' => min($serialNumbers),
                    'end_serial' => max($serialNumbers),
                    'quantity' => count($reservations),
                    'expires_at' => $reservations[0]->expires_at->toIso8601String(),
                    'expires_in_seconds' => $reservations[0]->getTimeRemaining()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error reserving batch serial numbers', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while reserving batch serial numbers'
            ], 500);
        }
    }

    /**
     * Confirm a reservation (mark it as used)
     *
     * POST /api/file-numbers/confirm-reservation
     */
    public function confirm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reservation_uuid' => 'required|string|max:36',
            'generated_file_number' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->reservationService->confirmReservation(
                $request->reservation_uuid,
                $request->generated_file_number
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Reservation confirmed successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm reservation. It may have expired or is invalid.'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error confirming reservation', [
                'error' => $e->getMessage(),
                'uuid' => $request->reservation_uuid
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while confirming the reservation'
            ], 500);
        }
    }

    /**
     * Release a reservation back to the pool
     *
     * POST /api/file-numbers/release-reservation
     */
    public function release(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reservation_uuid' => 'required|string|max:36'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->reservationService->releaseReservation($request->reservation_uuid);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Reservation released successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Reservation not found or already released'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error releasing reservation', [
                'error' => $e->getMessage(),
                'uuid' => $request->reservation_uuid
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while releasing the reservation'
            ], 500);
        }
    }

    /**
     * Extend a reservation's expiration time
     *
     * POST /api/file-numbers/extend-reservation
     */
    public function extend(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reservation_uuid' => 'required|string|max:36',
            'minutes' => 'nullable|integer|min:1|max:60'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->reservationService->extendReservation(
                $request->reservation_uuid,
                $request->minutes
            );

            if ($result) {
                $reservation = $this->reservationService->getReservation($request->reservation_uuid);

                return response()->json([
                    'success' => true,
                    'message' => 'Reservation extended successfully',
                    'data' => [
                        'expires_at' => $reservation->expires_at->toIso8601String(),
                        'expires_in_seconds' => $reservation->getTimeRemaining()
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to extend reservation. It may have expired or is invalid.'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error extending reservation', [
                'error' => $e->getMessage(),
                'uuid' => $request->reservation_uuid
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while extending the reservation'
            ], 500);
        }
    }

    /**
     * Get reservation status
     *
     * GET /api/file-numbers/reservation-status/{uuid}
     */
    public function status(string $uuid): JsonResponse
    {
        try {
            $reservation = $this->reservationService->getReservation($uuid);

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservation not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'reservation_uuid' => $reservation->reservation_uuid,
                    'serial_number' => $reservation->serial_number,
                    'status' => $reservation->status,
                    'is_active' => $reservation->isActive(),
                    'is_expired' => $reservation->isExpired(),
                    'expires_at' => $reservation->expires_at->toIso8601String(),
                    'expires_in_seconds' => $reservation->getTimeRemaining(),
                    'reserved_at' => $reservation->reserved_at->toIso8601String(),
                    'confirmed_at' => $reservation->confirmed_at?->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting reservation status', [
                'error' => $e->getMessage(),
                'uuid' => $uuid
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while getting reservation status'
            ], 500);
        }
    }

    /**
     * Find available gaps in serial numbers
     *
     * GET /api/file-numbers/find-gaps
     */
    public function findGaps(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prefix' => 'required|string|max:20',
            'year' => 'required|integer|min:2020|max:2050',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $gaps = $this->reservationService->findAvailableGaps(
                $request->prefix,
                $request->year,
                $request->get('limit', 10)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'gaps' => $gaps,
                    'count' => count($gaps)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error finding gaps', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while finding gaps'
            ], 500);
        }
    }
}
