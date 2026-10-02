<?php

namespace App\Services;

use App\Models\FileTracking;
use App\Models\FileIndexing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FileTrackingValidationService
{
    /**
     * Validate RFID tag uniqueness and format
     */
    public static function validateRfidTag(string $rfidTag, ?int $excludeId = null): bool
    {
        // Check format - RFID tags should be alphanumeric and between 6-100 characters
        if (!preg_match('/^[A-Za-z0-9]{6,100}$/', $rfidTag)) {
            throw ValidationException::withMessages([
                'rfid_tag' => ['RFID tag must be alphanumeric and between 6-100 characters']
            ]);
        }

        // Check uniqueness
        $query = FileTracking::where('rfid_tag', $rfidTag);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'rfid_tag' => ['This RFID tag is already assigned to another file']
            ]);
        }

        return true;
    }

    /**
     * Validate QR code uniqueness and format
     */
    public static function validateQrCode(string $qrCode, ?int $excludeId = null): bool
    {
        // Check format - QR codes should be alphanumeric and between 6-100 characters
        if (!preg_match('/^[A-Za-z0-9\-_]{6,100}$/', $qrCode)) {
            throw ValidationException::withMessages([
                'qr_code' => ['QR code must be alphanumeric (with hyphens/underscores) and between 6-100 characters']
            ]);
        }

        // Check uniqueness
        $query = FileTracking::where('qr_code', $qrCode);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'qr_code' => ['This QR code is already assigned to another file']
            ]);
        }

        return true;
    }

    /**
     * Validate due date is in the future
     */
    public static function validateDueDate(?string $dueDate, ?string $dateReceived = null): bool
    {
        if (!$dueDate) {
            return true; // Due date is optional
        }

        $dueDateCarbon = Carbon::parse($dueDate);
        $now = Carbon::now();

        // Due date should be in the future
        if ($dueDateCarbon->isPast()) {
            throw ValidationException::withMessages([
                'due_date' => ['Due date must be in the future']
            ]);
        }

        // If date_received is provided, due_date should be after it
        if ($dateReceived) {
            $dateReceivedCarbon = Carbon::parse($dateReceived);
            if ($dueDateCarbon->lte($dateReceivedCarbon)) {
                throw ValidationException::withMessages([
                    'due_date' => ['Due date must be after the date received']
                ]);
            }
        }

        return true;
    }

    /**
     * Validate file indexing exists and is not already tracked
     */
    public static function validateFileIndexing(int $fileIndexingId, ?int $excludeTrackingId = null): bool
    {
        // Check if file indexing exists
        $fileIndexing = FileIndexing::find($fileIndexingId);
        if (!$fileIndexing) {
            throw ValidationException::withMessages([
                'file_indexing_id' => ['The selected file does not exist']
            ]);
        }

        // Check if file is already being tracked (unless we're updating the same tracking)
        $query = FileTracking::where('file_indexing_id', $fileIndexingId);
        if ($excludeTrackingId) {
            $query->where('id', '!=', $excludeTrackingId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'file_indexing_id' => ['This file is already being tracked']
            ]);
        }

        return true;
    }

    /**
     * Validate status transition
     */
    public static function validateStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            FileTracking::STATUS_ACTIVE => [
                FileTracking::STATUS_CHECKED_OUT,
                FileTracking::STATUS_OVERDUE,
                FileTracking::STATUS_RETURNED,
                FileTracking::STATUS_LOST,
                FileTracking::STATUS_ARCHIVED
            ],
            FileTracking::STATUS_CHECKED_OUT => [
                FileTracking::STATUS_ACTIVE,
                FileTracking::STATUS_OVERDUE,
                FileTracking::STATUS_RETURNED,
                FileTracking::STATUS_LOST
            ],
            FileTracking::STATUS_OVERDUE => [
                FileTracking::STATUS_ACTIVE,
                FileTracking::STATUS_CHECKED_OUT,
                FileTracking::STATUS_RETURNED,
                FileTracking::STATUS_LOST,
                FileTracking::STATUS_ARCHIVED
            ],
            FileTracking::STATUS_RETURNED => [
                FileTracking::STATUS_ACTIVE,
                FileTracking::STATUS_CHECKED_OUT,
                FileTracking::STATUS_ARCHIVED
            ],
            FileTracking::STATUS_LOST => [
                FileTracking::STATUS_ACTIVE,
                FileTracking::STATUS_ARCHIVED
            ],
            FileTracking::STATUS_ARCHIVED => [
                FileTracking::STATUS_ACTIVE
            ]
        ];

        if (!isset($validTransitions[$currentStatus]) || 
            !in_array($newStatus, $validTransitions[$currentStatus])) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from '{$currentStatus}' to '{$newStatus}'"]
            ]);
        }

        return true;
    }

    /**
     * Validate movement data
     */
    public static function validateMovementData(array $movementData): bool
    {
        $validator = Validator::make($movementData, [
            'action' => 'required|string|max:100',
            'from_location' => 'nullable|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'from_handler' => 'nullable|string|max:255',
            'to_handler' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return true;
    }

    /**
     * Validate batch operation data
     */
    public static function validateBatchOperation(array $data): bool
    {
        $validator = Validator::make($data, [
            'action' => 'required|string|in:mark_overdue,extend_due_date,return_files',
            'tracking_ids' => 'required|array|min:1',
            'tracking_ids.*' => 'integer|exists:file_trackings,id',
            'new_due_date' => 'nullable|date|required_if:action,extend_due_date',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional validation for extend_due_date action
        if ($data['action'] === 'extend_due_date' && isset($data['new_due_date'])) {
            self::validateDueDate($data['new_due_date']);
        }

        return true;
    }

    /**
     * Validate location format
     */
    public static function validateLocation(?string $location): bool
    {
        if (!$location) {
            return true; // Location is optional
        }

        // Location should not contain special characters except spaces, hyphens, and underscores
        if (!preg_match('/^[A-Za-z0-9\s\-_\.]+$/', $location)) {
            throw ValidationException::withMessages([
                'current_location' => ['Location contains invalid characters']
            ]);
        }

        return true;
    }

    /**
     * Validate handler format
     */
    public static function validateHandler(?string $handler): bool
    {
        if (!$handler) {
            return true; // Handler is optional
        }

        // Handler should be a valid name format
        if (!preg_match('/^[A-Za-z\s\-\.]+$/', $handler)) {
            throw ValidationException::withMessages([
                'current_handler' => ['Handler name contains invalid characters']
            ]);
        }

        return true;
    }

    /**
     * Comprehensive validation for file tracking creation
     */
    public static function validateFileTrackingCreation(array $data): bool
    {
        // Validate file indexing
        self::validateFileIndexing($data['file_indexing_id']);

        // Validate RFID tag if provided
        if (!empty($data['rfid_tag'])) {
            self::validateRfidTag($data['rfid_tag']);
        }

        // Validate QR code if provided
        if (!empty($data['qr_code'])) {
            self::validateQrCode($data['qr_code']);
        }

        // Validate due date if provided
        if (!empty($data['due_date'])) {
            self::validateDueDate(
                $data['due_date'], 
                $data['date_received'] ?? null
            );
        }

        // Validate location if provided
        if (!empty($data['current_location'])) {
            self::validateLocation($data['current_location']);
        }

        // Validate handler if provided
        if (!empty($data['current_handler'])) {
            self::validateHandler($data['current_handler']);
        }

        return true;
    }

    /**
     * Comprehensive validation for file tracking update
     */
    public static function validateFileTrackingUpdate(array $data, int $trackingId): bool
    {
        $tracking = FileTracking::find($trackingId);
        if (!$tracking) {
            throw ValidationException::withMessages([
                'id' => ['File tracking not found']
            ]);
        }

        // Validate RFID tag if provided
        if (!empty($data['rfid_tag'])) {
            self::validateRfidTag($data['rfid_tag'], $trackingId);
        }

        // Validate QR code if provided
        if (!empty($data['qr_code'])) {
            self::validateQrCode($data['qr_code'], $trackingId);
        }

        // Validate status transition if provided
        if (!empty($data['status'])) {
            self::validateStatusTransition($tracking->status, $data['status']);
        }

        // Validate due date if provided
        if (!empty($data['due_date'])) {
            self::validateDueDate(
                $data['due_date'], 
                $data['date_received'] ?? $tracking->date_received
            );
        }

        // Validate location if provided
        if (!empty($data['current_location'])) {
            self::validateLocation($data['current_location']);
        }

        // Validate handler if provided
        if (!empty($data['current_handler'])) {
            self::validateHandler($data['current_handler']);
        }

        return true;
    }
}