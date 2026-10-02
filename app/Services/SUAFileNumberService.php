<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SUAFileNumberService
{
    /**
     * Generate new SUA file numbers following the required format
     * 
     * Main File Number: ST-[LAND_USE]-[YEAR]-[SEQUENCE]
     * SUA File Number: ST-[LAND_USE]-[YEAR]-[SEQUENCE]-[UNIT_SEQUENCE]
     * 
     * @param string $landUse The land use type (Residential, Commercial, Industrial)
     * @return array ['main' => string, 'sua' => string, 'mls' => string]
     */
    public function generateSUAFileNumbers($landUse)
    {
        try {
            // Map land use to code
            $landUseCode = $this->mapLandUseToCode($landUse);
            $currentYear = date('Y');
            
            // Get the next sequence number for the main file number
            $nextSequence = $this->getNextMainSequence($landUseCode, $currentYear);
            
            // Generate main file number
            $mainFileNo = "ST-{$landUseCode}-{$currentYear}-{$nextSequence}";
            
            // Get the next unit sequence (always starts at 001 for SUA)
            $unitSequence = $this->getNextUnitSequence($landUseCode, $currentYear, $nextSequence);
            $unitSequencePadded = str_pad($unitSequence, 3, '0', STR_PAD_LEFT);
            
            // Generate SUA file number
            $suaFileNo = "ST-{$landUseCode}-{$currentYear}-{$nextSequence}-{$unitSequencePadded}";
            
            // MLS file number should always match the primary file number
            $mlsFileNo = $mainFileNo;
            
            return [
                'main' => $mainFileNo,
                'sua' => $suaFileNo,
                'mls' => $mlsFileNo
            ];
            
        } catch (Exception $e) {
            Log::error('Error generating SUA file numbers: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Store the generated file numbers in the sua_file_numbers table
     * 
     * @param array $fileNumbers Array with 'main', 'sua', 'mls' keys
     * @param string $landUse Full land use name
     * @param int|null $subApplicationId Reference to subapplications table
     * @param int|null $createdBy User ID who created the record
     * @return int The ID of the created record
     */
    public function storeSUAFileNumbers($fileNumbers, $landUse, $subApplicationId = null, $createdBy = null)
    {
        try {
            $landUseCode = $this->mapLandUseToCode($landUse);
            $currentYear = date('Y');
            
            // Extract sequence numbers from the file numbers
            $mainSequence = $this->extractSequenceFromFileNumber($fileNumbers['main']);
            $unitSequence = $this->extractUnitSequenceFromFileNumber($fileNumbers['sua']);
            
            $recordId = DB::connection('sqlsrv')->table('sua_file_numbers')->insertGetId([
                'main_file_number' => $fileNumbers['main'],
                'sua_file_number' => $fileNumbers['sua'],
                'mls_file_number' => $fileNumbers['mls'],
                'land_use_code' => $landUseCode,
                'land_use_full' => $landUse,
                'year' => $currentYear,
                'sequence_number' => $mainSequence,
                'unit_sequence' => $unitSequence,
                'status' => 'active',
                'subapplication_id' => $subApplicationId,
                'is_auto_generated' => 1,
                'generation_method' => 'sua_auto',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            Log::info('SUA file numbers stored successfully', [
                'record_id' => $recordId,
                'main_file_number' => $fileNumbers['main'],
                'sua_file_number' => $fileNumbers['sua'],
                'land_use' => $landUse
            ]);
            
            return $recordId;
            
        } catch (Exception $e) {
            Log::error('Error storing SUA file numbers: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get the next main sequence number for a land use and year
     * This increments only within the same land use group
     * 
     * @param string $landUseCode
     * @param int $year
     * @return int
     */
    private function getNextMainSequence($landUseCode, $year)
    {
        // Get the highest sequence number from mother_applications for this land use and year
        $lastMotherApp = DB::connection('sqlsrv')
            ->table('mother_applications')
            ->where('land_use', $this->mapCodeToLandUse($landUseCode))
            ->whereYear('created_at', $year)
            ->whereNotNull('np_fileno')
            ->orderBy('id', 'desc')
            ->first();
        
        $motherAppSequence = 0;
        if ($lastMotherApp && preg_match("/ST-{$landUseCode}-{$year}-(\d+)/", $lastMotherApp->np_fileno, $matches)) {
            $motherAppSequence = (int)$matches[1];
        }
        
        // Get the highest sequence number from sua_file_numbers for this land use and year
        $lastSUARecord = DB::connection('sqlsrv')
            ->table('sua_file_numbers')
            ->where('land_use_code', $landUseCode)
            ->where('year', $year)
            ->orderBy('sequence_number', 'desc')
            ->first();
        
        $suaSequence = 0;
        if ($lastSUARecord) {
            $suaSequence = $lastSUARecord->sequence_number;
        }
        
        // Return the highest sequence + 1
        return max($motherAppSequence, $suaSequence) + 1;
    }
    
    /**
     * Get the next unit sequence number for a specific main file number
     * For SUA, this always returns 1 (001) since each SUA is standalone
     * 
     * @param string $landUseCode
     * @param int $year
     * @param int $mainSequence
     * @return int
     */
    private function getNextUnitSequence($landUseCode, $year, $mainSequence)
    {
        // For SUA applications, the unit sequence is always 001 since each SUA is standalone
        // Each new SUA gets its own main sequence number and starts with unit 001
        return 1;
    }
    
    /**
     * Map land use name to code
     * 
     * @param string $landUse
     * @return string
     */
    private function mapLandUseToCode($landUse)
    {
        return match(strtoupper($landUse)) {
            'COMMERCIAL' => 'COM',
            'INDUSTRIAL' => 'IND',
            'RESIDENTIAL' => 'RES',
            default => 'RES'
        };
    }
    
    /**
     * Map land use code to full name
     * 
     * @param string $code
     * @return string
     */
    private function mapCodeToLandUse($code)
    {
        return match(strtoupper($code)) {
            'COM' => 'Commercial',
            'IND' => 'Industrial',
            'RES' => 'Residential',
            default => 'Residential'
        };
    }
    
    /**
     * Extract sequence number from main file number
     * 
     * @param string $fileNumber
     * @return int
     */
    private function extractSequenceFromFileNumber($fileNumber)
    {
        if (preg_match('/ST-[A-Z]+-\d+-(\d+)/', $fileNumber, $matches)) {
            return (int)$matches[1];
        }
        return 1;
    }
    
    /**
     * Extract unit sequence from SUA file number
     * 
     * @param string $fileNumber
     * @return int
     */
    private function extractUnitSequenceFromFileNumber($fileNumber)
    {
        if (preg_match('/ST-[A-Z]+-\d+-\d+-(\d+)/', $fileNumber, $matches)) {
            return (int)$matches[1];
        }
        return 1;
    }
    
    /**
     * Get SUA file numbers by subapplication ID
     * 
     * @param int $subApplicationId
     * @return object|null
     */
    public function getSUAFileNumbersBySubApplicationId($subApplicationId)
    {
        return DB::connection('sqlsrv')
            ->table('sua_file_numbers')
            ->where('subapplication_id', $subApplicationId)
            ->where('status', 'active')
            ->first();
    }
    
    /**
     * Update SUA file numbers record with subapplication ID
     * 
     * @param string $suaFileNumber
     * @param int $subApplicationId
     * @return bool
     */
    public function updateSUAFileNumbersWithSubApplicationId($suaFileNumber, $subApplicationId)
    {
        try {
            $affected = DB::connection('sqlsrv')
                ->table('sua_file_numbers')
                ->where('sua_file_number', $suaFileNumber)
                ->update([
                    'subapplication_id' => $subApplicationId,
                    'updated_at' => now()
                ]);
            
            return $affected > 0;
            
        } catch (Exception $e) {
            Log::error('Error updating SUA file numbers with subapplication ID: ' . $e->getMessage());
            return false;
        }
    }
}