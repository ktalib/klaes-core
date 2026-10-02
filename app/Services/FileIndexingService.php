<?php

namespace App\Services;

use App\Models\FileIndexing;
use App\Models\MlsFileNo;
use Illuminate\Support\Facades\Log;

class FileIndexingService
{
    /**
     * Create a file_indexings record from an MLS file number
     *
     * @param MlsFileNo $mlsFile
     * @return FileIndexing
     */
    public function createFromMlsFileNumber(MlsFileNo $mlsFile, ?string $relatedFileNo = null): FileIndexing
    {
        $data = [
            'tracking_id' => $mlsFile->tracking_id,
            'file_number' => $mlsFile->full_file_number,
            'file_title' => $mlsFile->file_name,
            // Carries the commissioning gender across with its provenance intact.
            // When the commissioning row is itself blank, FileIndexing::creating
            // infers from the title rather than writing another null.
            'gender' => $mlsFile->gender ?? null,
            'gender_source' => $mlsFile->gender ? ($mlsFile->gender_source ?? 'pair') : null,
            'land_use_type' => $this->extractLandUseType($mlsFile->land_use),
            'plot_number' => $mlsFile->plot_no,
            'tp_no' => $mlsFile->tp_no,
            'location' => $mlsFile->location,
            'lga' => $mlsFile->lga,
            'created_by' => $mlsFile->created_by,
            'current_holder' => $mlsFile->file_name,
            'original_holder' => $mlsFile->file_name,
            'workflow_status' => 'indexed',
            'is_updated' => false,
            'is_deleted' => false,
            'parent_prop_id' => null,
            'related_fileno' => null,
        ];

        $data = $this->enrichWithCorrespondingFile($data);

        if ($relatedFileNo) {
            $data['related_fileno'] = json_encode([$relatedFileNo]);
        }

        $fileIndexing = FileIndexing::create($data);

        Log::info('File automatically indexed', [
            'mls_file_id' => $mlsFile->id,
            'file_indexing_id' => $fileIndexing->id,
            'file_number' => $mlsFile->full_file_number
        ]);

        return $fileIndexing;
    }

    /**
     * Create a file_indexings record from raw data array
     *
     * @param array $data
     * @return FileIndexing
     */
    public function createFromFileNumberData(array $data): FileIndexing
    {
        $indexingData = [
            'tracking_id' => $data['tracking_id'] ?? null,
            'file_number' => $data['file_number'],
            'file_title' => $data['file_title'] ?? null,
            'gender' => $data['gender'] ?? null,
            'land_use_type' => isset($data['land_use']) ? $this->extractLandUseType($data['land_use']) : null,
            'plot_number' => $data['plot_number'] ?? null,
            'tp_no' => $data['tp_no'] ?? null,
            'street_name' => $data['street_name'] ?? null,
            'location' => $data['location'] ?? null,
            'lga' => $data['lga'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'created_by' => $data['created_by'] ?? 'System',
            'current_holder' => $data['current_holder'] ?? ($data['file_title'] ?? null),
            'original_holder' => $data['original_holder'] ?? ($data['file_title'] ?? null),
            'workflow_status' => 'indexed',
            'is_updated' => false,
            'is_deleted' => false,
            'parent_prop_id' => $data['parent_prop_id'] ?? null,
            'related_fileno' => $data['related_fileno'] ?? null,
        ];

        // Third lineage level: the root above the parent, so merged files that are
        // more than two generations deep record their original parcel at index time
        // rather than waiting for the propid:backfill-ancestral sweep.
        //
        // tableIsReady() gates the write on the column actually existing: this code
        // may run against a database where the ancestral_prop_id migration has not
        // been applied yet, and indexing must not break there.
        if (!empty($indexingData['parent_prop_id'])) {
            $lineage = app(PropIdLineageService::class);

            if ($lineage->tableIsReady('file_indexings')) {
                $indexingData['ancestral_prop_id'] = $lineage->resolveAncestralForRow(
                    $data['prop_id'] ?? null,
                    $indexingData['parent_prop_id']
                );
            }
        }

        $indexingData = $this->enrichWithCorrespondingFile($indexingData);

        $fileIndexing = FileIndexing::create($indexingData);

        Log::info('File indexed from data array', [
            'file_indexing_id' => $fileIndexing->id,
            'file_number' => $indexingData['file_number']
        ]);

        return $fileIndexing;
    }

    /**
     * Map MLS land use codes to file_indexings land_use_type
     *
     * @param string $landUse
     * @return string
     */
    private function extractLandUseType(string $landUse): string
    {
        // Remove CON- prefix if present (conversion files)
        $baseLandUse = str_replace('CON-', '', $landUse);

        // Remove -RC suffix if present (recertification files)
        $baseLandUse = str_replace('-RC', '', $baseLandUse);

        // Map to full land use type names
        $mapping = [
            'RES' => 'Residential',
            'COM' => 'Commercial',
            'IND' => 'Industrial',
            'AG' => 'Agricultural',
        ];

        return $mapping[$baseLandUse] ?? $landUse; // Return original if not found
    }

    /**
     * Check if a file number already exists in file_indexings
     *
     * @param string $fileNumber
     * @return bool
     */
    public function fileNumberExists(string $fileNumber): bool
    {
        return FileIndexing::where('file_number', $fileNumber)->exists();
    }

    /**
     * Get or create file indexing record
     * Useful to avoid duplicates
     *
     * @param string $fileNumber
     * @param array $data
     * @return FileIndexing
     */
    public function getOrCreate(string $fileNumber, array $data): FileIndexing
    {
        $existing = FileIndexing::where('file_number', $fileNumber)->first();

        if ($existing) {
            Log::info('File indexing already exists', [
                'file_number' => $fileNumber,
                'file_indexing_id' => $existing->id
            ]);
            return $existing;
        }

        return $this->createFromFileNumberData(array_merge($data, ['file_number' => $fileNumber]));
    }
    /**
     * Check if a file number exists in the corresponding_fileno table
     * and return the matching fileno if found.
     *
     * @param string $fileNumber
     * @return string|null
     */
    public function getCorrespondingMatch(string $fileNumber): ?string
    {
        return \DB::connection('sqlsrv')
            ->table('corresponding_fileno')
            ->whereRaw('UPPER(LTRIM(RTRIM(fileno))) = UPPER(?)', [trim((string) $fileNumber)])
            ->value('fileno');
    }

    /**
     * Enrich indexing data with corresponding file information
     *
     * @param array $data
     * @return array
     */
    public function enrichWithCorrespondingFile(array $data): array
    {
        $fileNumber = $data['file_number'] ?? null;
        if (!$fileNumber) {
            return $data;
        }

        $match = $this->getCorrespondingMatch($fileNumber);
        if ($match !== null) {
            $data['is_corresponding_file'] = 1;
            $data['corresponding_fileno'] = $match;
        } else {
            $data['is_corresponding_file'] = 0;
            $data['corresponding_fileno'] = null;
        }

        return $data;
    }
}
