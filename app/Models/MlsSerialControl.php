<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MlsSerialControl extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'mls_serial_control';

    protected $fillable = [
        'land_use',
        'year',
        'last_serial',
        'is_initialized',
        'initialized_at',
        'initialized_by',
        'is_locked'
    ];

    protected $casts = [
        'is_initialized' => 'boolean',
        'is_locked' => 'boolean',
        'year' => 'integer',
        'last_serial' => 'integer',
        'initialized_at' => 'datetime'
    ];

    /**
     * Get the next serial number for a land use and year (thread-safe)
     * Uses database locking to prevent race conditions
     */
    public static function getNextSerial($landUse, $year)
    {
        DB::connection('sqlsrv')->beginTransaction();

        try {
            // Use SQL Server locking hints to ensure thread safety
            $control = self::where('land_use', $landUse)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$control) {
                // Create new control record starting from 1
                $control = self::create([
                    'land_use' => $landUse,
                    'year' => $year,
                    'last_serial' => 1,
                    'is_initialized' => false
                ]);

                $nextSerial = 1;
            } else {
                // Increment the serial
                $nextSerial = $control->last_serial + 1;
                $control->update(['last_serial' => $nextSerial]);
            }

            DB::connection('sqlsrv')->commit();

            Log::info("MLS Serial allocated: {$landUse}-{$year} -> {$nextSerial}");

            return $nextSerial;

        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Error getting next MLS serial', [
                'land_use' => $landUse,
                'year' => $year,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Initialize serial control for a land use/year
     */
    public static function initialize($landUse, $year, $lastSerial, $userId = null)
    {
        $userName = $userId ?: (auth()->user()->name ?? 'System');

        $control = self::updateOrCreate(
            [
                'land_use' => $landUse,
                'year' => $year
            ],
            [
                'last_serial' => $lastSerial,
                'is_initialized' => true,
                'initialized_at' => now(),
                'initialized_by' => $userName,
                'is_locked' => true // Lock immediately after initialization
            ]
        );

        Log::info("MLS Serial initialized", [
            'land_use' => $landUse,
            'year' => $year,
            'last_serial' => $lastSerial,
            'by' => $userName
        ]);

        return $control;
    }

    /**
     * Check if a land use/year combination is locked
     */
    public static function isLocked($landUse, $year)
    {
        $control = self::where('land_use', $landUse)
            ->where('year', $year)
            ->first();

        return $control ? (bool) $control->is_locked : false;
    }

    /**
     * Get current serial without incrementing
     */
    public static function getCurrentSerial($landUse, $year)
    {
        $control = self::where('land_use', $landUse)
            ->where('year', $year)
            ->first();

        return $control ? $control->last_serial : 0;
    }

    /**
     * Get all serial control records for a year
     */
    public static function getAllForYear($year)
    {
        return self::where('year', $year)->get();
    }
}
