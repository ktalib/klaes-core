<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * Unified ST File Number Service
 * 
 * Handles all ST file number generation following the format:
 * ST-{LAND_USE}-{YEAR}-{SERIAL} for primary applications
 * ST-{LAND_USE}-{YEAR}-{SERIAL}-{UNIT_SEQUENCE} for unit applications
 * 
 * This service replaces the complex FileNumberReservationService and 
 * SUAFileNumberService with a unified, simpler approach.
 */
class STFileNumberService
{
    const STATUS_RESERVED = 'RESERVED';
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_USED = 'USED';
    const STATUS_CANCELLED = 'CANCELLED';
    
    const TYPE_PRIMARY = 'PRIMARY';
    const TYPE_SUA = 'SUA';
    const TYPE_PUA = 'PUA';
    
    /**
     * Generate a primary ST file number
     * 
     * @param string $landUse Land use type (Residential, Commercial, Industrial, Mixed)
     * @param array $applicantData Applicant information
     * @return array ['success' => bool, 'data' => array, 'message' => string]
     */
    public function generatePrimaryFileNumber(string $landUse, array $applicantData): array
    {
        DB::connection('sqlsrv')->beginTransaction();
        
        try {
            $landUseInfo = $this->normalizeLandUse($landUse);
            $year = date('Y');
            
            // Get next serial number
            $serialNo = $this->getNextSerial($landUseInfo['code'], $year);
            
            // Generate file number
            $npFileNo = "ST-{$landUseInfo['code']}-{$year}-{$serialNo}";
            $fileNo = $npFileNo; // Same for primary applications
            $mlsFileNo = $npFileNo; // MLS file number is same as primary
            
            // Double-check uniqueness before proceeding
            $existing = DB::connection('sqlsrv')->table('st_file_numbers')
                ->where('fileno', $fileNo)
                ->exists();
                
            if ($existing) {
                DB::connection('sqlsrv')->rollback();
                return [
                    'success' => false,
                    'data' => null,
                    'message' => "File number {$fileNo} already exists. Please try again."
                ];
            }
            
            // Generate tracking ID
            $trackingId = $this->generateTrackingId();
            
            // Create record
            $recordId = DB::connection('sqlsrv')->table('st_file_numbers')->insertGetId([
                'np_fileno' => $npFileNo,
                'fileno' => $fileNo,
                'mls_fileno' => $mlsFileNo,
                'land_use' => $landUseInfo['full'],
                'land_use_code' => $landUseInfo['code'],
                'serial_no' => $serialNo,
                'unit_sequence' => null,
                'year' => $year,
                'file_no_type' => self::TYPE_PRIMARY,
                'parent_id' => null,
                'mother_application_id' => null,
                'subapplication_id' => null,
                'status' => self::STATUS_RESERVED,
                'reserved_at' => now(),
                'expires_at' => now()->addDay(), // 24 hour expiry for primary
                'tra' => $trackingId,
                'applicant_type' => $applicantData['applicant_type'] ?? 'Individual',
                'applicant_title' => $applicantData['applicant_title'] ?? null,
                'first_name' => $applicantData['first_name'] ?? null,
                'surname' => $applicantData['surname'] ?? null,
                'corporate_name' => $applicantData['corporate_name'] ?? null,
                'rc_number' => $applicantData['rc_number'] ?? null,
                'multiple_owners_names' => isset($applicantData['multiple_owners_names']) ? json_encode($applicantData['multiple_owners_names']) : null,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::connection('sqlsrv')->commit();
            
            Log::info("Generated primary ST file number: {$npFileNo}", [
                'record_id' => $recordId,
                'land_use' => $landUse,
                'tracking_id' => $trackingId,
                'user_id' => Auth::id()
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'id' => $recordId,
                    'np_fileno' => $npFileNo,
                    'fileno' => $fileNo,
                    'mls_fileno' => $mlsFileNo,
                    'land_use' => $landUseInfo['full'],
                    'land_use_code' => $landUseInfo['code'],
                    'serial_no' => $serialNo,
                    'year' => $year,
                    'tracking_id' => $trackingId,
                    'type' => self::TYPE_PRIMARY,
                    'expires_at' => now()->addDay()->toDateTimeString()
                ],
                'message' => 'Primary ST file number generated successfully'
            ];
            
        } catch (Exception $e) {
            DB::connection('sqlsrv')->rollback();
            Log::error('Error generating primary ST file number: ' . $e->getMessage(), [
                'land_use' => $landUse,
                'applicant_data' => $applicantData,
                'user_id' => Auth::id()
            ]);
            
            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to generate primary file number: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate SUA (Standalone Unit Application) file numbers
     * 
     * @param string $landUse Land use type
     * @param array $applicantData Applicant information
     * @return array ['success' => bool, 'data' => array, 'message' => string]
     */
    public function generateSUAFileNumber(string $landUse, array $applicantData): array
    {
        DB::connection('sqlsrv')->beginTransaction();
        
        try {
            $landUseInfo = $this->normalizeLandUse($landUse);
            $year = date('Y');
            
            // Get next serial number
            $serialNo = $this->getNextSerial($landUseInfo['code'], $year);
            
            // Generate file numbers
            $npFileNo = "ST-{$landUseInfo['code']}-{$year}-{$serialNo}"; // Primary/NP file number
            $unitFileNo = "ST-{$landUseInfo['code']}-{$year}-{$serialNo}-001"; // Unit file number (always 001 for SUA)
            $mlsFileNo = $npFileNo; // MLS file number same as primary
            
            // Double-check uniqueness before proceeding
            $existing = DB::connection('sqlsrv')->table('st_file_numbers')
                ->where('fileno', $unitFileNo)
                ->exists();
                
            if ($existing) {
                DB::connection('sqlsrv')->rollback();
                return [
                    'success' => false,
                    'data' => null,
                    'message' => "File number {$unitFileNo} already exists. Please try again."
                ];
            }
            
            // Generate tracking ID
            $trackingId = $this->generateTrackingId();
            
            // Create record
            $recordId = DB::connection('sqlsrv')->table('st_file_numbers')->insertGetId([
                'np_fileno' => $npFileNo,
                'fileno' => $unitFileNo,
                'mls_fileno' => $mlsFileNo,
                'land_use' => $landUseInfo['full'],
                'land_use_code' => $landUseInfo['code'],
                'serial_no' => $serialNo,
                'unit_sequence' => 1, // Always 001 for SUA
                'year' => $year,
                'file_no_type' => self::TYPE_SUA,
                'parent_id' => null,
                'mother_application_id' => null,
                'subapplication_id' => null,
                'status' => self::STATUS_RESERVED,
                'reserved_at' => now(),
                'expires_at' => now()->addDay(), // 24 hour expiry
                'tra' => $trackingId,
                'applicant_type' => $applicantData['applicant_type'] ?? 'Individual',
                'applicant_title' => $applicantData['applicant_title'] ?? null,
                'first_name' => $applicantData['first_name'] ?? null,
                'surname' => $applicantData['surname'] ?? null,
                'corporate_name' => $applicantData['corporate_name'] ?? null,
                'rc_number' => $applicantData['rc_number'] ?? null,
                'multiple_owners_names' => isset($applicantData['multiple_owners_names']) ? json_encode($applicantData['multiple_owners_names']) : null,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::connection('sqlsrv')->commit();
            
            Log::info("Generated SUA file numbers", [
                'record_id' => $recordId,
                'np_fileno' => $npFileNo,
                'unit_fileno' => $unitFileNo,
                'mls_fileno' => $mlsFileNo,
                'land_use' => $landUse,
                'tracking_id' => $trackingId,
                'user_id' => Auth::id()
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'id' => $recordId,
                    'np_fileno' => $npFileNo,
                    'primary_fileno' => $npFileNo,
                    'unit_fileno' => $unitFileNo,
                    'mls_fileno' => $mlsFileNo,
                    'land_use' => $landUseInfo['full'],
                    'land_use_code' => $landUseInfo['code'],
                    'serial_no' => $serialNo,
                    'unit_sequence' => 1,
                    'year' => $year,
                    'tracking_id' => $trackingId,
                    'type' => self::TYPE_SUA,
                    'expires_at' => now()->addDay()->toDateTimeString()
                ],
                'message' => 'SUA file numbers generated successfully'
            ];
            
        } catch (Exception $e) {
            DB::connection('sqlsrv')->rollback();
            Log::error('Error generating SUA file numbers: ' . $e->getMessage(), [
                'land_use' => $landUse,
                'applicant_data' => $applicantData,
                'user_id' => Auth::id()
            ]);
            
            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to generate SUA file numbers: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate PUA (Parented Unit Application) file number
     * 
     * @param string $parentFileNumber Existing primary file number
     * @param array $applicantData Applicant information
     * @param int|null $buyerListId Optional buyer list ID to link
     * @return array ['success' => bool, 'data' => array, 'message' => string]
     */
    public function generatePUAFileNumber(string $parentFileNumber, array $applicantData, ?int $buyerListId = null): array
    {
        DB::connection('sqlsrv')->beginTransaction();
        
        try {
            // Validate and parse parent file number
            $parentInfo = $this->parseFileNumber($parentFileNumber);
            if (!$parentInfo) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Invalid parent file number format'
                ];
            }
            
            // Find parent record
            $parentRecord = DB::connection('sqlsrv')->table('st_file_numbers')
                ->where('np_fileno', $parentFileNumber)
                ->where('file_no_type', self::TYPE_PRIMARY)
                ->first();
                
            if (!$parentRecord) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Parent file number not found'
                ];
            }
            
            // Get next unit sequence for this parent
            $nextUnitSequence = $this->getNextUnitSequence($parentFileNumber);
            $unitSequencePadded = str_pad($nextUnitSequence, 3, '0', STR_PAD_LEFT);
            
            // Generate PUA file number
            $unitFileNo = "{$parentFileNumber}-{$unitSequencePadded}";
            
            // Double-check uniqueness before proceeding
            $existing = DB::connection('sqlsrv')->table('st_file_numbers')
                ->where('fileno', $unitFileNo)
                ->exists();
                
            if ($existing) {
                DB::connection('sqlsrv')->rollback();
                return [
                    'success' => false,
                    'data' => null,
                    'message' => "File number {$unitFileNo} already exists. Please try again."
                ];
            }
            
            // Generate tracking ID
            $trackingId = $this->generateTrackingId();
            
            // Create record
            $recordId = DB::connection('sqlsrv')->table('st_file_numbers')->insertGetId([
                'np_fileno' => $parentFileNumber, // Inherited from parent
                'fileno' => $unitFileNo,
                'mls_fileno' => $parentFileNumber, // MLS same as parent
                'land_use' => $parentRecord->land_use,
                'land_use_code' => $parentRecord->land_use_code,
                'serial_no' => $parentRecord->serial_no,
                'unit_sequence' => $nextUnitSequence,
                'year' => $parentRecord->year,
                'file_no_type' => self::TYPE_PUA,
                'parent_id' => $parentRecord->id,
                'buyer_list_id' => $buyerListId, // NEW: Link to buyer record
                'mother_application_id' => null,
                'subapplication_id' => null,
                'status' => self::STATUS_RESERVED,
                'reserved_at' => now(),
                'expires_at' => now()->addDay(), // 24 hour expiry
                'tra' => $trackingId,
                'applicant_type' => $applicantData['applicant_type'] ?? 'Individual',
                'applicant_title' => $applicantData['applicant_title'] ?? null,
                'first_name' => $applicantData['first_name'] ?? null,
                'surname' => $applicantData['surname'] ?? null,
                'corporate_name' => $applicantData['corporate_name'] ?? null,
                'rc_number' => $applicantData['rc_number'] ?? null,
                'multiple_owners_names' => isset($applicantData['multiple_owners_names']) ? json_encode($applicantData['multiple_owners_names']) : null,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            DB::connection('sqlsrv')->commit();
            
            Log::info("Generated PUA file number: {$unitFileNo}", [
                'record_id' => $recordId,
                'parent_fileno' => $parentFileNumber,
                'unit_sequence' => $nextUnitSequence,
                'tracking_id' => $trackingId,
                'buyer_list_id' => $buyerListId,
                'user_id' => Auth::id()
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'id' => $recordId,
                    'np_fileno' => $parentFileNumber,
                    'unit_fileno' => $unitFileNo,
                    'mls_fileno' => $parentFileNumber,
                    'land_use' => $parentRecord->land_use,
                    'land_use_code' => $parentRecord->land_use_code,
                    'serial_no' => $parentRecord->serial_no,
                    'unit_sequence' => $nextUnitSequence,
                    'year' => $parentRecord->year,
                    'tracking_id' => $trackingId,
                    'type' => self::TYPE_PUA,
                    'parent_id' => $parentRecord->id,
                    'buyer_list_id' => $buyerListId,
                    'expires_at' => now()->addDay()->toDateTimeString()
                ],
                'message' => 'PUA file number generated successfully'
            ];
            
        } catch (Exception $e) {
            DB::connection('sqlsrv')->rollback();
            Log::error('Error generating PUA file number: ' . $e->getMessage(), [
                'parent_file_number' => $parentFileNumber,
                'applicant_data' => $applicantData,
                'user_id' => Auth::id()
            ]);
            
            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to generate PUA file number: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Reserve a file number (alias for backward compatibility)
     */
    public function reserveFileNumber(string $type, array $params): array
    {
        switch (strtoupper($type)) {
            case 'PRIMARY':
                return $this->generatePrimaryFileNumber($params['land_use'], $params['applicant_data'] ?? []);
            case 'SUA':
                return $this->generateSUAFileNumber($params['land_use'], $params['applicant_data'] ?? []);
            case 'PUA':
                return $this->generatePUAFileNumber($params['parent_file_number'], $params['applicant_data'] ?? []);
            default:
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Invalid file number type'
                ];
        }
    }
    
    /**
     * Confirm a reservation (mark as used)
     */
    public function confirmReservation(string $fileNumber, int $applicationId): bool
    {
        try {
            $updated = DB::connection('sqlsrv')->table('st_file_numbers')
                ->where('fileno', $fileNumber)
                ->where('status', self::STATUS_RESERVED)
                ->update([
                    'status' => self::STATUS_USED,
                    'used_at' => now(),
                    'date_commissioned' => now(),
                    'mother_application_id' => $applicationId,
                    'updated_at' => now()
                ]);
            
            if ($updated) {
                Log::info("Confirmed file number reservation: {$fileNumber}", [
                    'application_id' => $applicationId,
                    'user_id' => Auth::id()
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Log::error('Error confirming reservation: ' . $e->getMessage(), [
                'file_number' => $fileNumber,
                'application_id' => $applicationId
            ]);
            return false;
        }
    }
    
    /**
     * Release a reservation
     */
    public function releaseReservation(string $fileNumber): bool
    {
        try {
            $updated = DB::connection('sqlsrv')->table('st_file_numbers')
                ->where('fileno', $fileNumber)
                ->where('status', self::STATUS_RESERVED)
                ->update([
                    'status' => self::STATUS_CANCELLED,
                    'updated_at' => now()
                ]);
            
            if ($updated) {
                Log::info("Released file number reservation: {$fileNumber}", [
                    'user_id' => Auth::id()
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Log::error('Error releasing reservation: ' . $e->getMessage(), [
                'file_number' => $fileNumber
            ]);
            return false;
        }
    }
    
    /**
     * Get file number details
     */
    public function getFileNumberDetails(string $fileNumber): ?object
    {
        return DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('fileno', $fileNumber)
            ->orWhere('np_fileno', $fileNumber)
            ->first();
    }
    
    /**
     * Get units by parent file number
     */
    public function getUnitsByParent(string $parentFileNumber): array
    {
        return DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('np_fileno', $parentFileNumber)
            ->where('file_no_type', self::TYPE_PUA)
            ->orderBy('unit_sequence')
            ->get()
            ->toArray();
    }
    
    /**
     * Get next available serial number for land use/year
     */
    private function getNextSerial(string $landUseCode, int $year): int
    {
        // Get the highest serial from st_file_numbers table for this land use/year
        // Use lockForUpdate to prevent race conditions
        $maxSerial = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('land_use_code', $landUseCode)
            ->where('year', $year)
            ->lockForUpdate()
            ->max('serial_no');
        
        return ($maxSerial ?? 0) + 1;
    }
    
    /**
     * Get next unit sequence for a parent file number
     */
    private function getNextUnitSequence(string $parentFileNumber): int
    {
        // Check ALL units (PUA and SUA types) to avoid duplicates
        // Use lockForUpdate to prevent race conditions
        $maxUnit = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('np_fileno', $parentFileNumber)
            ->whereIn('file_no_type', [self::TYPE_PUA, self::TYPE_SUA])
            ->whereNotNull('unit_sequence')
            ->lockForUpdate()
            ->max('unit_sequence');
        
        return ($maxUnit ?? 0) + 1;
    }
    
    /**
     * Normalize land use input to standard format
     */
    public function normalizeLandUse(string $landUse): array
    {
        return match(strtoupper(trim($landUse))) {
            'COMMERCIAL', 'COMMERCIAL USE' => ['full' => 'Commercial', 'code' => 'COM'],
            'INDUSTRIAL', 'INDUSTRIAL USE', 'INDUSTRY' => ['full' => 'Industrial', 'code' => 'IND'],
            'RESIDENTIAL', 'RESIDENTIAL USE' => ['full' => 'Residential', 'code' => 'RES'],
            'MIXED', 'MIXED USE' => ['full' => 'Mixed', 'code' => 'MIXED'],
            default => ['full' => 'Residential', 'code' => 'RES']
        };
    }
    
    /**
     * Parse file number into components
     */
    private function parseFileNumber(string $fileNumber): ?array
    {
        // Expected format: ST-{LAND_USE}-{YEAR}-{SERIAL}, or the conversion form
        // ST-CON-{LAND_USE}-{YEAR}-{SERIAL} whose serial comes from the MLS CON pool.
        if (preg_match('/^ST-(CON-)?([A-Z]+)-(\d{4})-(\d+)$/', $fileNumber, $matches)) {
            return [
                'prefix' => 'ST',
                'land_use_code' => ($matches[1] !== '' ? 'CON-' : '') . $matches[2],
                'year' => (int)$matches[3],
                'serial' => (int)$matches[4]
            ];
        }
        
        return null;
    }
    
    /**
     * Generate tracking ID in format TRK-XXXXXXXX-XXXXX
     */
    private function generateTrackingId(): string
    {
        $part1 = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        $part2 = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5));
        return "TRK-{$part1}-{$part2}";
    }

    /**
     * Peek at what the next serial number would be without creating a record
     * 
     * @param string $landUseCode
     * @param int $year
     * @return string Formatted serial number (e.g., "001")
     */
    public function peekNextSerial(string $landUseCode, int $year): string
    {
        // Get the highest serial number for this land use and year
        $maxSerial = DB::connection('sqlsrv')->table('st_file_numbers')
            ->where('land_use_code', $landUseCode)
            ->where('year', $year)
            ->max('serial_no');
        
        $nextSerial = $maxSerial ? (int)$maxSerial + 1 : 1;
        
        return str_pad($nextSerial, 3, '0', STR_PAD_LEFT);
    }

}