<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Exception;

class SectionalTitleHelper
{
    /**
     * Generate a unique sectional title file number and insert into necessary tables
     * 
     * @param int $applicationId
     * @param string $createdBy
     * @param array $data
     * @return string The generated sectional title file number
     * @throws Exception
     */
    public static function generateAndInsertFileNumber($applicationId, $createdBy, $data)
    {
        // Start a database transaction to ensure all operations succeed or fail together
        DB::connection('sqlsrv')->beginTransaction();

        try {
            // Generate a unique Sectional_Title_File_No using the correct table
            $currentYear = date('Y');

            // Check if Sectional_Title_File_Numbers table exists
            $sectionalTableExists = Schema::connection('sqlsrv')->hasTable('Sectional_Title_File_Numbers');

            if (!$sectionalTableExists) {
                Log::warning('Sectional_Title_File_Numbers table does not exist', [
                    'connection' => 'sqlsrv',
                    'application_id' => $applicationId
                ]);

                // Use fallback method - just generate a number without db check
                $nextNumber = 1;
                $sectionalTitleFileNo = 'ST/' . $currentYear . '/' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            } else {
                // Get the latest sequence number from Sectional_Title_File_Numbers table
                $lastNumber = DB::connection('sqlsrv')
                    ->table('Sectional_Title_File_Numbers')
                    ->where('year', $currentYear)
                    ->max('sequence_number');

                $nextNumber = $lastNumber ? (int)$lastNumber + 1 : 1;
                $sectionalTitleFileNo = 'ST/' . $currentYear . '/' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

                // Check if this file number already exists to avoid duplicates
                $exists = DB::connection('sqlsrv')
                    ->table('Sectional_Title_File_Numbers')
                    ->where('file_number', $sectionalTitleFileNo)
                    ->exists();

                if ($exists) {
                    // If exists, increment and try again
                    $nextNumber++;
                    $sectionalTitleFileNo = 'ST/' . $currentYear . '/' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                }

                // Log what we're about to insert
                Log::info('Inserting into Sectional_Title_File_Numbers', [
                    'file_number' => $sectionalTitleFileNo,
                    'year' => $currentYear,
                    'sequence_number' => $nextNumber,
                    'application_id' => $applicationId
                ]);

                // Step 1: Insert into Sectional_Title_File_Numbers table
                DB::connection('sqlsrv')->table('Sectional_Title_File_Numbers')->insert([
                    'file_number' => $sectionalTitleFileNo,
                    'year' => $currentYear,
                    'sequence_number' => $nextNumber,
                    'application_id' => $applicationId,
                    'is_active' => 1,
                    'remarks' => 'Mother Application',
                    'created_by' => $createdBy ?? 'System',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Process file numbers
            $mlsFileNo = $data['mlsFileNo'] ?? null;
            $kangisFileNo = $data['kangisFileNo'] ?? null;
            $newKangisFileNo = $data['newKangisFileNo'] ?? null;

            // Step 2: Check for fileNumber table and insert if it exists
            try {
                // Check if the fileNumber table exists
                $fileNumberTableExists = Schema::connection('sqlsrv')->hasTable('fileNumber');

                if (!$fileNumberTableExists) {
                    // Check alternate table names (case sensitivity matters in SQL Server)
                    $alternateTableNames = ['file_number', 'FileNumber', 'file_numbers', 'FileNumbers'];

                    foreach ($alternateTableNames as $tableName) {
                        if (Schema::connection('sqlsrv')->hasTable($tableName)) {
                            $fileNumberTableExists = true;
                            Log::info("Found alternate table name: {$tableName} instead of fileNumber");
                            break;
                        }
                    }

                    // If we still can't find the table, log and skip this step
                    if (!$fileNumberTableExists) {
                        Log::warning('fileNumber table does not exist - skipping file number insertion', [
                            'connection' => 'sqlsrv',
                            'application_id' => $applicationId
                        ]);
                    }
                }

                // Only insert if table exists and we have file numbers
                if ($fileNumberTableExists && ($mlsFileNo || $kangisFileNo || $newKangisFileNo)) {
                    // Use standard insert method instead of IDENTITY_INSERT which requires special permissions
                    DB::connection('sqlsrv')->table('fileNumber')->insert([
                        'application_id' => $applicationId,
                        'kangisFileNo' => $kangisFileNo,
                        'mlsfNo' => $mlsFileNo,
                        'NewKANGISFileNo' => $newKangisFileNo,
                        'location' => null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    Log::info('Inserted into fileNumber table', [
                        'application_id' => $applicationId
                    ]);
                }
            } catch (Exception $e) {
                Log::error('Error with fileNumber table operations', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue processing without failing - we'll try to insert to eRegistry anyway
            }

            // Step 3: Insert into eRegistry table if it exists
            try {
                $eRegistryExists = Schema::connection('sqlsrv')->hasTable('eRegistry');

                if (!$eRegistryExists) {
                    Log::warning('eRegistry table does not exist - skipping eRegistry insertion', [
                        'connection' => 'sqlsrv',
                        'application_id' => $applicationId
                    ]);
                } else {
                    DB::connection('sqlsrv')->table('eRegistry')->insert([
                        'application_id' => $applicationId,
                        'MLSFileNo' => $mlsFileNo,
                        'KANGISFileNo' => $kangisFileNo,
                        'NEWKangisFileNo' => $newKangisFileNo,
                        'Sectional_Title_File_No' => $sectionalTitleFileNo,
                        'Commissioning_Date' => now(),
                        'Decommissioning_Date' => now(),
                        'Site_Plan_Approval' => null,
                        'Survey_Plan_Approval' => null,
                        'Expected_Return_Date' => null,
                        'Current_Office' => null,
                        'created_by' => $createdBy ?? 'System',
                        'modify_by' => null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    Log::info('Inserted into eRegistry table', [
                        'application_id' => $applicationId,
                        'Sectional_Title_File_No' => $sectionalTitleFileNo
                    ]);
                }
            } catch (Exception $e) {
                Log::error('Error inserting into eRegistry table', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue without failing - this is not a critical error
            }

            // Commit transaction if everything succeeds
            DB::connection('sqlsrv')->commit();

            return $sectionalTitleFileNo;
        } catch (Exception $e) {
            // Roll back transaction on any error
            DB::connection('sqlsrv')->rollBack();

            Log::error('Error generating file number', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'applicationId' => $applicationId
            ]);
            throw $e;
        }
    }

    /**
     * Insert buyers from JSON form inputs
     * 
     * @param int $applicationId
     * @param array $records
     * @return void
     */
    public static function insertBuyers($applicationId, $records)
    {
        if (empty($records)) {
            Log::info('No buyer records to insert', ['application_id' => $applicationId]);
            return;
        }

        // Start a database transaction
        DB::connection('sqlsrv')->beginTransaction();

        try {
            Log::info('Starting to insert buyer records', [
                'application_id' => $applicationId,
                'record_count' => count($records)
            ]);

            // Check if buyer_list table exists
            $buyerTableExists = Schema::connection('sqlsrv')->hasTable('buyer_list');

            if (!$buyerTableExists) {
                Log::warning('buyer_list table does not exist - skipping buyer insertion', [
                    'application_id' => $applicationId
                ]);
                DB::connection('sqlsrv')->rollBack();
                return;
            }

            // Check if st_unit_measurements table exists
            $unitMeasurementsExists = Schema::connection('sqlsrv')->hasTable('st_unit_measurements');

            if (!$unitMeasurementsExists) {
                Log::warning('st_unit_measurements table does not exist - measurements will not be stored', [
                    'application_id' => $applicationId
                ]);
            }

            foreach ($records as $index => $record) {
                // Extract buyer data - handle multiple possible field names
                $buyerTitle = $record['buyerTitle'] ?? null;
                $firstName = $record['firstName'] ?? null;
                $middleName = $record['middleName'] ?? null;
                $surname = $record['surname'] ?? null;
                $buyerName = $record['buyerName'] ?? null;
                $unitNo = $record['unit_no'] ?? $record['unitNumber'] ?? null;
                $unitMeasurement = $record['unitMeasurement'] ?? null;
                $landUse = $record['landUse'] ?? null;
                $sectionNumber = $record['sectionNumber'] ?? null;

                if ($unitNo !== null) {
                    $unitNo = strtoupper(trim($unitNo));
                }

                if ($sectionNumber !== null) {
                    $sectionNumber = strtoupper(trim($sectionNumber));
                    if ($sectionNumber === '') {
                        $sectionNumber = null;
                    }
                }

                // If buyerName is not provided, construct it using helper method
                if (!$buyerName && ($firstName || $surname || $buyerTitle)) {
                    $buyerName = self::generateBuyerName($record);
                }

                if (!$buyerName || !$unitNo) {
                    Log::warning('Skipping incomplete buyer record', [
                        'record_index' => $index,
                        'buyer_name' => $buyerName,
                        'unit_no' => $unitNo,
                        'land_use' => $landUse,
                        'first_name' => $firstName,
                        'surname' => $surname
                    ]);
                    continue; // Skip incomplete records
                }

                // Step 1: Insert unit measurement first to get the ID
                $unitMeasurementId = null;
                if ($unitMeasurement && $unitMeasurementsExists) {
                    try {
                        Log::info('Inserting unit measurement', [
                            'unit_no' => $unitNo,
                            'measurement' => $unitMeasurement
                        ]);

                        $unitMeasurementId = DB::connection('sqlsrv')->table('st_unit_measurements')->insertGetId([
                            'application_id' => (string)$applicationId, // Cast to string since column is nvarchar
                            'buyer_id' => null, // Will be updated after buyer creation
                            'unit_no' => $unitNo, // Use unitNo instead of sectionNo
                            'measurement' => $unitMeasurement,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        Log::info('Unit measurement inserted successfully', [
                            'unit_measurement_id' => $unitMeasurementId
                        ]);
                    } catch (Exception $e) {
                        Log::error('Error inserting unit measurement', [
                            'error' => $e->getMessage(),
                            'unit_no' => $unitNo,
                            'measurement' => $unitMeasurement
                        ]);
                        // Continue without unit measurement ID
                    }
                }

                // Step 2: Insert into buyer_list
                try {
                    Log::info('Inserting buyer record', [
                        'buyer_name' => $buyerName,
                        'buyer_title' => $buyerTitle,
                        'unit_no' => $unitNo,
                        'section_number' => $sectionNumber,
                        'land_use' => $landUse,
                        'unit_measurement_id' => $unitMeasurementId
                    ]);

                    $buyerId = DB::connection('sqlsrv')->table('buyer_list')->insertGetId([
                        'application_id' => $applicationId,
                        'unit_measurement_id' => $unitMeasurementId,
                        'buyer_title' => $buyerTitle,
                        'buyer_name' => $buyerName,
                        'unit_no' => $unitNo,
                        'block_no' => $record['blockNo'] ?? ($record['block_no'] ?? null),
                        'section_number' => $sectionNumber,
                        'land_use' => $landUse,
                        'cubic_easurement' => $record['cubicMeasurement'] ?? ($record['cubic_measurement'] ?? null),
                        'created_at' => now(),
                        'updated_at' => now(),
                        'final_conveyance_generated' => 0,
                        'final_conveyance_generated_at' => null
                    ]);

                    Log::info('Buyer inserted successfully', [
                        'buyer_id' => $buyerId
                    ]);

                    // Step 3: Update unit measurement with buyer_id if it was created
                    if ($unitMeasurementId && $unitMeasurementsExists) {
                        try {
                            DB::connection('sqlsrv')->table('st_unit_measurements')
                                ->where('id', $unitMeasurementId)
                                ->update([
                                    'buyer_id' => (string)$buyerId, // Cast to string since column is nvarchar
                                    'updated_at' => now()
                                ]);

                            Log::info('Unit measurement updated with buyer_id', [
                                'unit_measurement_id' => $unitMeasurementId,
                                'buyer_id' => $buyerId
                            ]);
                        } catch (Exception $e) {
                            Log::error('Error updating unit measurement with buyer_id', [
                                'error' => $e->getMessage(),
                                'unit_measurement_id' => $unitMeasurementId,
                                'buyer_id' => $buyerId
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Error inserting buyer record', [
                        'error' => $e->getMessage(),
                        'record' => $record
                    ]);
                    throw $e;
                }
            }

            // Commit the transaction
            DB::connection('sqlsrv')->commit();
            Log::info('All buyer records inserted successfully');
        } catch (Exception $e) {
            // Roll back the transaction on error
            DB::connection('sqlsrv')->rollBack();

            Log::error('Error inserting buyer records', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Generate buyer name from individual components
     * 
     * @param array $buyerRecord
     * @return string
     */
    private static function generateBuyerName($buyerRecord)
    {
        return trim(implode(' ', array_filter([
            $buyerRecord['buyerTitle'] ?? '',
            $buyerRecord['firstName'] ?? '',
            $buyerRecord['middleName'] ?? '',
            $buyerRecord['surname'] ?? ''
        ], function($component) {
            return !empty(trim($component));
        })));
    }
}
