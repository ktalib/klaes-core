<?php

namespace App\Services;

use App\Models\FileNumberReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FileNumberReservationService
{
    /**
     * Reserve the next available serial number for a given prefix and year
     *
     * @param string $prefix
     * @param string $landUse
     * @param int $year
     * @param string|null $sessionId
     * @return FileNumberReservation|null
     */
    public function reserveNextSerial(string $prefix, string $landUse, int $year, string $sessionId = null): ?FileNumberReservation
    {
        // Use database transaction with locking to prevent race conditions
        return DB::connection('sqlsrv')->transaction(function () use ($prefix, $landUse, $year, $sessionId) {
            
            // Clean up expired reservations first
            $this->releaseExpiredReservations();

            // Get the next available serial number
            $nextSerial = $this->getNextAvailableSerial($prefix, $landUse, $year);

            // Create the reservation
            $reservation = FileNumberReservation::create([
                'prefix' => $prefix,
                'land_use' => $landUse,
                'year' => $year,
                'serial_number' => $nextSerial,
                'session_id' => $sessionId ?? session()->getId(),
                'reserved_by' => Auth::id(),
                'reserved_by_name' => Auth::user()->name ?? 'Unknown',
                'reserved_by_ip' => request()->ip(),
                'status' => 'reserved',
                'metadata' => [
                    'user_agent' => request()->userAgent(),
                    'reserved_from' => 'commission_modal'
                ]
            ]);

            Log::info('Serial number reserved', [
                'reservation_uuid' => $reservation->reservation_uuid,
                'prefix' => $prefix,
                'year' => $year,
                'serial' => $nextSerial,
                'user' => Auth::user()->name ?? 'Guest'
            ]);

            return $reservation;
        }, 5); // Retry up to 5 times if deadlock occurs
    }

    /**
     * Reserve multiple serial numbers for batch generation
     *
     * @param string $prefix
     * @param string $landUse
     * @param int $year
     * @param int $quantity
     * @param string|null $sessionId
     * @return array Array of FileNumberReservation objects
     */
    public function reserveBatchSerials(string $prefix, string $landUse, int $year, int $quantity, string $sessionId = null): array
    {
        return DB::connection('sqlsrv')->transaction(function () use ($prefix, $landUse, $year, $quantity, $sessionId) {
            
            $this->releaseExpiredReservations();

            $nextSerial = $this->getNextAvailableSerial($prefix, $landUse, $year);
            $reservations = [];

            for ($i = 0; $i < $quantity; $i++) {
                $serial = $nextSerial + $i;

                $reservation = FileNumberReservation::create([
                    'prefix' => $prefix,
                    'land_use' => $landUse,
                    'year' => $year,
                    'serial_number' => $serial,
                    'session_id' => $sessionId ?? session()->getId(),
                    'reserved_by' => Auth::id(),
                    'reserved_by_name' => Auth::user()->name ?? 'Unknown',
                    'reserved_by_ip' => request()->ip(),
                    'status' => 'reserved',
                    'metadata' => [
                        'user_agent' => request()->userAgent(),
                        'reserved_from' => 'commission_modal_batch',
                        'batch_position' => $i + 1,
                        'batch_total' => $quantity
                    ]
                ]);

                $reservations[] = $reservation;
            }

            Log::info('Batch serial numbers reserved', [
                'prefix' => $prefix,
                'year' => $year,
                'start_serial' => $nextSerial,
                'end_serial' => $nextSerial + $quantity - 1,
                'quantity' => $quantity,
                'user' => Auth::user()->name ?? 'Guest'
            ]);

            return $reservations;
        }, 5);
    }

    /**
     * Get the next available serial number considering both generated files and active reservations
     *
     * @param string $prefix
     * @param string $landUse
     * @param int $year
     * @return int
     */
    protected function getNextAvailableSerial(string $prefix, string $landUse, int $year): int
    {
        // Get max serial from existing file numbers
        $maxGeneratedSerial = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->where('mlsfNo', 'like', $prefix . '%-' . $year . '-%')
            ->where('SOURCE', 'MLS_Commissioned')
            ->where('type', 'MlsFileNO')
            ->whereNotNull('mlsfNo')
            ->where('mlsfNo', '!=', '')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->lockForUpdate() // Lock to prevent concurrent reads
            ->get()
            ->map(function ($record) {
                // Extract serial number from file number
                if (preg_match('/-(\d+)(?:\(T\))?(?:\s+AND\s+EXTENSION)?$/', $record->mlsfNo, $matches)) {
                    return (int) $matches[1];
                }
                return 0;
            })
            ->max() ?? 0;

        // Get max serial from active reservations
        $maxReservedSerial = FileNumberReservation::where('prefix', $prefix)
            ->where('year', $year)
            ->where(function ($query) {
                $query->where('status', 'reserved')
                    ->where('expires_at', '>', now())
                    ->orWhere('status', 'confirmed');
            })
            ->lockForUpdate() // Lock to prevent concurrent reads
            ->max('serial_number') ?? 0;

        // Return the higher of the two plus 1
        return max($maxGeneratedSerial, $maxReservedSerial) + 1;
    }

    /**
     * Confirm a reservation (mark it as used)
     *
     * @param string $reservationUuid
     * @param string $generatedFileNumber
     * @return bool
     */
    public function confirmReservation(string $reservationUuid, string $generatedFileNumber): bool
    {
        $reservation = FileNumberReservation::where('reservation_uuid', $reservationUuid)->first();

        if (!$reservation) {
            Log::warning('Attempted to confirm non-existent reservation', ['uuid' => $reservationUuid]);
            return false;
        }

        if (!$reservation->isActive()) {
            Log::warning('Attempted to confirm expired/invalid reservation', [
                'uuid' => $reservationUuid,
                'status' => $reservation->status,
                'expires_at' => $reservation->expires_at
            ]);
            return false;
        }

        $result = $reservation->confirm($generatedFileNumber);

        if ($result) {
            Log::info('Reservation confirmed', [
                'uuid' => $reservationUuid,
                'file_number' => $generatedFileNumber,
                'serial' => $reservation->serial_number
            ]);
        }

        return $result;
    }

    /**
     * Release a reservation back to the pool
     *
     * @param string $reservationUuid
     * @return bool
     */
    public function releaseReservation(string $reservationUuid): bool
    {
        $reservation = FileNumberReservation::where('reservation_uuid', $reservationUuid)->first();

        if (!$reservation) {
            return false;
        }

        $result = $reservation->release();

        if ($result) {
            Log::info('Reservation released', [
                'uuid' => $reservationUuid,
                'serial' => $reservation->serial_number
            ]);
        }

        return $result;
    }

    /**
     * Release all expired reservations
     *
     * @return int Number of reservations released
     */
    public function releaseExpiredReservations(): int
    {
        $expiredReservations = FileNumberReservation::expired()->get();

        $count = 0;
        foreach ($expiredReservations as $reservation) {
            if ($reservation->markExpired()) {
                $count++;
            }
        }

        if ($count > 0) {
            Log::info('Released expired reservations', ['count' => $count]);
        }

        return $count;
    }

    /**
     * Extend a reservation's expiration time
     *
     * @param string $reservationUuid
     * @param int $minutes
     * @return bool
     */
    public function extendReservation(string $reservationUuid, int $minutes = null): bool
    {
        $reservation = FileNumberReservation::where('reservation_uuid', $reservationUuid)->first();

        if (!$reservation || !$reservation->isActive()) {
            return false;
        }

        return $reservation->extend($minutes);
    }

    /**
     * Get active reservation by UUID
     *
     * @param string $reservationUuid
     * @return FileNumberReservation|null
     */
    public function getReservation(string $reservationUuid): ?FileNumberReservation
    {
        return FileNumberReservation::where('reservation_uuid', $reservationUuid)->first();
    }

    /**
     * Check if a specific serial number is available
     *
     * @param string $prefix
     * @param int $year
     * @param int $serial
     * @return bool
     */
    public function isSerialAvailable(string $prefix, int $year, int $serial): bool
    {
        // Check if already generated
        $existsInFiles = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->where('mlsfNo', 'like', $prefix . '%-' . $year . '-' . str_pad($serial, 4, '0', STR_PAD_LEFT) . '%')
            ->where('SOURCE', 'MLS_Commissioned')
            ->exists();

        if ($existsInFiles) {
            return false;
        }

        // Check if reserved or confirmed
        $existsInReservations = FileNumberReservation::where('prefix', $prefix)
            ->where('year', $year)
            ->where('serial_number', $serial)
            ->where(function ($query) {
                $query->where('status', 'reserved')
                    ->where('expires_at', '>', now())
                    ->orWhere('status', 'confirmed');
            })
            ->exists();

        return !$existsInReservations;
    }

    /**
     * Find and fill gaps in serial numbers
     *
     * @param string $prefix
     * @param int $year
     * @param int $limit Maximum number of gaps to find
     * @return array Array of available serial numbers
     */
    public function findAvailableGaps(string $prefix, int $year, int $limit = 10): array
    {
        $this->releaseExpiredReservations();

        // Get all used serials (both generated and reserved)
        $usedSerials = collect();

        // From generated files
        $generatedSerials = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->where('mlsfNo', 'like', $prefix . '%-' . $year . '-%')
            ->where('SOURCE', 'MLS_Commissioned')
            ->where('type', 'MlsFileNO')
            ->whereNotNull('mlsfNo')
            ->where('mlsfNo', '!=', '')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->get()
            ->map(function ($record) {
                if (preg_match('/-(\d+)(?:\(T\))?(?:\s+AND\s+EXTENSION)?$/', $record->mlsfNo, $matches)) {
                    return (int) $matches[1];
                }
                return null;
            })
            ->filter();

        $usedSerials = $usedSerials->merge($generatedSerials);

        // From active reservations
        $reservedSerials = FileNumberReservation::where('prefix', $prefix)
            ->where('year', $year)
            ->where(function ($query) {
                $query->where('status', 'reserved')
                    ->where('expires_at', '>', now())
                    ->orWhere('status', 'confirmed');
            })
            ->pluck('serial_number');

        $usedSerials = $usedSerials->merge($reservedSerials)->unique()->sort()->values();

        // Find gaps
        $gaps = [];
        $maxSerial = $usedSerials->max() ?? 0;

        for ($i = 1; $i < $maxSerial && count($gaps) < $limit; $i++) {
            if (!$usedSerials->contains($i)) {
                $gaps[] = $i;
            }
        }

        return $gaps;
    }
}
