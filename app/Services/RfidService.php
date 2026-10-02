<?php

namespace App\Services;

use App\Models\FileTracking;
use App\Models\FileIndexing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RfidService
{
    /**
     * Generate a unique RFID tag
     */
    public static function generateRfidTag(string $prefix = 'RFID'): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $timestamp = Carbon::now()->format('ymdHis');
            $random = strtoupper(substr(md5(uniqid()), 0, 4));
            $rfidTag = "{$prefix}{$timestamp}{$random}";
            
            $attempt++;
            
            // Check if this tag already exists
            $exists = FileTracking::where('rfid_tag', $rfidTag)->exists();
            
        } while ($exists && $attempt < $maxAttempts);

        if ($exists) {
            throw new \Exception('Unable to generate unique RFID tag after multiple attempts');
        }

        return $rfidTag;
    }

    /**
     * Register RFID tag to a file
     */
    public static function registerTag(int $fileIndexingId, ?string $rfidTag = null): FileTracking
    {
        // Generate RFID tag if not provided
        if (!$rfidTag) {
            $rfidTag = self::generateRfidTag();
        }

        // Validate the tag
        FileTrackingValidationService::validateRfidTag($rfidTag);
        FileTrackingValidationService::validateFileIndexing($fileIndexingId);

        // Find existing tracking or create new one
        $tracking = FileTracking::where('file_indexing_id', $fileIndexingId)->first();

        if ($tracking) {
            // Update existing tracking with RFID tag
            $tracking->rfid_tag = $rfidTag;
            $tracking->addMovementEntry([
                'action' => 'rfid_assigned',
                'rfid_tag' => $rfidTag,
                'reason' => 'RFID tag assigned to existing tracking'
            ]);
            $tracking->save();
        } else {
            // Create new tracking with RFID tag
            $tracking = FileTracking::create([
                'file_indexing_id' => $fileIndexingId,
                'rfid_tag' => $rfidTag,
                'status' => FileTracking::STATUS_ACTIVE,
                'date_received' => Carbon::now(),
            ]);
        }

        Log::info('RFID tag registered', [
            'tracking_id' => $tracking->id,
            'rfid_tag' => $rfidTag,
            'file_indexing_id' => $fileIndexingId,
            'user_id' => auth()->id()
        ]);

        return $tracking;
    }

    /**
     * Scan RFID tag and retrieve file information
     */
    public static function scanTag(string $rfidTag, array $scanData = []): ?FileTracking
    {
        $tracking = FileTracking::with(['fileIndexing', 'currentHandlerUser'])
                                ->where('rfid_tag', $rfidTag)
                                ->first();

        if (!$tracking) {
            Log::warning('RFID tag scan failed - tag not found', [
                'rfid_tag' => $rfidTag,
                'scan_data' => $scanData
            ]);
            return null;
        }

        // Add scan entry to movement history
        $movementData = array_merge([
            'action' => 'rfid_scanned',
            'rfid_tag' => $rfidTag,
            'scan_timestamp' => Carbon::now()->toISOString(),
            'scan_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], $scanData);

        $tracking->addMovementEntry($movementData);

        // Cache the scan for quick access
        $cacheKey = "rfid_scan_{$rfidTag}";
        Cache::put($cacheKey, [
            'tracking_id' => $tracking->id,
            'scanned_at' => Carbon::now()->toISOString(),
            'scan_count' => Cache::get($cacheKey . '_count', 0) + 1
        ], 3600); // Cache for 1 hour

        Log::info('RFID tag scanned successfully', [
            'tracking_id' => $tracking->id,
            'rfid_tag' => $rfidTag,
            'file_number' => $tracking->fileIndexing->file_number ?? 'N/A',
            'user_id' => auth()->id()
        ]);

        return $tracking;
    }

    /**
     * Update file location via RFID scan
     */
    public static function updateLocationViaScan(string $rfidTag, string $newLocation, string $reason = 'RFID location update'): ?FileTracking
    {
        $tracking = self::scanTag($rfidTag, [
            'reason' => $reason,
            'new_location' => $newLocation
        ]);

        if ($tracking) {
            $tracking->updateLocation($newLocation, $reason);
        }

        return $tracking;
    }

    /**
     * Update file handler via RFID scan
     */
    public static function updateHandlerViaScan(string $rfidTag, string $newHandler, string $reason = 'RFID handler update'): ?FileTracking
    {
        $tracking = self::scanTag($rfidTag, [
            'reason' => $reason,
            'new_handler' => $newHandler
        ]);

        if ($tracking) {
            $tracking->updateHandler($newHandler, $reason);
        }

        return $tracking;
    }

    /**
     * Check out file via RFID scan
     */
    public static function checkOutFile(string $rfidTag, string $handler, string $location = null, string $reason = 'File checked out via RFID'): ?FileTracking
    {
        $tracking = self::scanTag($rfidTag, [
            'action' => 'check_out',
            'reason' => $reason,
            'handler' => $handler,
            'location' => $location
        ]);

        if ($tracking) {
            $tracking->updateStatus(FileTracking::STATUS_CHECKED_OUT, $reason);
            $tracking->updateHandler($handler, $reason);
            
            if ($location) {
                $tracking->updateLocation($location, $reason);
            }
        }

        return $tracking;
    }

    /**
     * Check in file via RFID scan
     */
    public static function checkInFile(string $rfidTag, string $location = null, string $reason = 'File checked in via RFID'): ?FileTracking
    {
        $tracking = self::scanTag($rfidTag, [
            'action' => 'check_in',
            'reason' => $reason,
            'location' => $location
        ]);

        if ($tracking) {
            $tracking->updateStatus(FileTracking::STATUS_ACTIVE, $reason);
            
            if ($location) {
                $tracking->updateLocation($location, $reason);
            }
        }

        return $tracking;
    }

    /**
     * Get RFID scan statistics
     */
    public static function getScanStatistics(string $period = 'today'): array
    {
        $startDate = match($period) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::today()
        };

        // Get all trackings with movement history
        $trackings = FileTracking::where('updated_at', '>=', $startDate)->get();
        
        $scanCount = 0;
        $uniqueTags = [];
        $scansByHour = array_fill(0, 24, 0);
        $topScannedFiles = [];

        foreach ($trackings as $tracking) {
            if ($tracking->movement_history) {
                foreach ($tracking->movement_history as $movement) {
                    if ($movement['action'] === 'rfid_scanned' && 
                        Carbon::parse($movement['timestamp'])->gte($startDate)) {
                        
                        $scanCount++;
                        $uniqueTags[$tracking->rfid_tag] = true;
                        
                        $hour = Carbon::parse($movement['timestamp'])->hour;
                        $scansByHour[$hour]++;
                        
                        $fileKey = $tracking->fileIndexing->file_number ?? 'Unknown';
                        if (!isset($topScannedFiles[$fileKey])) {
                            $topScannedFiles[$fileKey] = [
                                'file_number' => $fileKey,
                                'file_title' => $tracking->fileIndexing->file_title ?? 'N/A',
                                'scan_count' => 0
                            ];
                        }
                        $topScannedFiles[$fileKey]['scan_count']++;
                    }
                }
            }
        }

        // Sort top scanned files
        uasort($topScannedFiles, function($a, $b) {
            return $b['scan_count'] - $a['scan_count'];
        });

        return [
            'period' => $period,
            'start_date' => $startDate->toISOString(),
            'total_scans' => $scanCount,
            'unique_tags_scanned' => count($uniqueTags),
            'scans_by_hour' => $scansByHour,
            'top_scanned_files' => array_slice($topScannedFiles, 0, 10),
            'generated_at' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Get RFID tag information
     */
    public static function getTagInfo(string $rfidTag): ?array
    {
        $tracking = FileTracking::with(['fileIndexing', 'currentHandlerUser'])
                                ->where('rfid_tag', $rfidTag)
                                ->first();

        if (!$tracking) {
            return null;
        }

        // Get scan history for this tag
        $scanHistory = [];
        if ($tracking->movement_history) {
            foreach ($tracking->movement_history as $movement) {
                if ($movement['action'] === 'rfid_scanned') {
                    $scanHistory[] = $movement;
                }
            }
        }

        // Get cached scan data
        $cacheKey = "rfid_scan_{$rfidTag}";
        $cachedScan = Cache::get($cacheKey);

        return [
            'rfid_tag' => $rfidTag,
            'tracking_id' => $tracking->id,
            'file_indexing_id' => $tracking->file_indexing_id,
            'file_number' => $tracking->fileIndexing->file_number ?? 'N/A',
            'file_title' => $tracking->fileIndexing->file_title ?? 'N/A',
            'current_status' => $tracking->status,
            'current_location' => $tracking->current_location,
            'current_handler' => $tracking->current_handler,
            'is_overdue' => $tracking->is_overdue,
            'days_until_due' => $tracking->days_until_due,
            'scan_history' => $scanHistory,
            'last_scan' => $cachedScan,
            'total_scans' => count($scanHistory),
            'created_at' => $tracking->created_at,
            'updated_at' => $tracking->updated_at
        ];
    }

    /**
     * Bulk register RFID tags
     */
    public static function bulkRegisterTags(array $fileIndexingIds, string $prefix = 'RFID'): array
    {
        $results = [];
        $errors = [];

        foreach ($fileIndexingIds as $fileIndexingId) {
            try {
                $tracking = self::registerTag($fileIndexingId, null);
                $results[] = [
                    'file_indexing_id' => $fileIndexingId,
                    'tracking_id' => $tracking->id,
                    'rfid_tag' => $tracking->rfid_tag,
                    'status' => 'success'
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'file_indexing_id' => $fileIndexingId,
                    'error' => $e->getMessage(),
                    'status' => 'error'
                ];
            }
        }

        Log::info('Bulk RFID registration completed', [
            'total_requested' => count($fileIndexingIds),
            'successful' => count($results),
            'errors' => count($errors),
            'user_id' => auth()->id()
        ]);

        return [
            'successful' => $results,
            'errors' => $errors,
            'summary' => [
                'total_requested' => count($fileIndexingIds),
                'successful_count' => count($results),
                'error_count' => count($errors)
            ]
        ];
    }

    /**
     * Validate RFID tag format
     */
    public static function isValidRfidFormat(string $rfidTag): bool
    {
        return preg_match('/^[A-Za-z0-9]{6,100}$/', $rfidTag);
    }

    /**
     * Clean RFID tag (remove invalid characters)
     */
    public static function cleanRfidTag(string $rfidTag): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', strtoupper($rfidTag));
    }
}