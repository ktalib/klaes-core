<?php

namespace App\Services\Pra;

use App\Models\LandRecommendation;
use App\Models\SltrRecommendation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RofoPraSyncer
{
    /** Every ROFO (Right of Occupancy) is granted by the State. */
    private const GRANTOR = 'KANO STATE GOVERNMENT';

    private PraRecordService $service;

    public function __construct(PraRecordService $service)
    {
        $this->service = $service;
    }

    public function syncLand(LandRecommendation $rec, ?int $userId = null): bool
    {
        $fileNumber = $this->cleanString($rec->file_number);
        if ($fileNumber === null) {
            return false;
        }

        if ($this->praRowExists($fileNumber)) {
            return false;
        }

        $isOss = strtoupper((string) ($rec->type ?? '')) === 'OSS';

        $payload = [
            'mlsFNo'             => $fileNumber,
            'fileno'             => $fileNumber,
            'rofo_number'        => $fileNumber,
            'instrument_type'    => $isOss ? 'Right of Occupancy (OSS)' : 'Right of Occupancy',
            'transaction_type'   => $isOss ? 'Right of Occupancy (OSS)' : 'Right of Occupancy',
            // For Land/OSS RofOs, PRA records the date that the source recommendation
            // was captured. This keeps newly backfilled records aligned with the RofO
            // register's Date Created column.
            'transaction_date'   => $this->landTransactionDate($rec),
            'land_use'           => $rec->land_use,
            'purpose'            => $rec->purpose_of_clause,
            'plot_no'            => $rec->plot_number,
            'location'           => $rec->location,
            'property_description'=> $rec->location,
            'lgsaOrCity'         => $rec->lga ?? null,
            'Grantor'            => self::GRANTOR,
            'party_2'            => $rec->applicant_name,
            'Grantee'            => $rec->applicant_name,
            'source'             => $isOss ? 'oss_rofo' : 'land_rofo',
            'system_source'      => $isOss ? 'OSS_ROFO' : 'LAND_ROFO',
        ];

        return $this->insert($payload, $userId ?? Auth::id(), 'Land', [
            'land_recommendation_id' => $rec->id,
            'file_number' => $fileNumber,
        ]);
    }

    public function syncSltr(SltrRecommendation $rec, ?int $userId = null): bool
    {
        // SLTR has no file_number column — use sltr_number as the identifier
        // and as the rofo_number value (per design: rofo_number == document number).
        $identifier = $this->cleanString($rec->sltr_number);
        if ($identifier === null) {
            return false;
        }

        if ($this->praRowExists($identifier)) {
            return false;
        }

        $payload = [
            'mlsFNo'             => $identifier,
            'fileno'             => $identifier,
            'rofo_number'        => $identifier,
            'instrument_type'    => 'Right of Occupancy (SLTR)',
            'transaction_type'   => 'Right of Occupancy (SLTR)',
            'transaction_date'   => $rec->rofo_date_generated
                ?: ($rec->rofo_generated_at ? $rec->rofo_generated_at->toDateString() : now()->toDateString()),
            'land_use'           => $rec->land_use,
            'purpose'            => $rec->purpose_of_clause,
            'plot_no'            => $rec->plot_number,
            'location'           => $rec->location,
            'property_description'=> $rec->location,
            'lgsaOrCity'         => $rec->lga,
            'Grantor'            => self::GRANTOR,
            'party_2'            => $rec->applicant_name,
            'Grantee'            => $rec->applicant_name,
            'source'             => 'sltr_rofo',
            'system_source'      => 'SLTR_ROFO',
        ];

        return $this->insert($payload, $userId ?? Auth::id(), 'SLTR', [
            'sltr_recommendation_id' => $rec->id,
            'sltr_number' => $identifier,
        ]);
    }

    public function syncStUnits(array $subApplicationIds, ?int $userId = null): int
    {
        $subApplicationIds = array_values(array_unique(array_filter(array_map('intval', $subApplicationIds))));
        if (empty($subApplicationIds)) {
            return 0;
        }

        $userId = $userId ?? Auth::id();
        $count = 0;

        try {
            $rows = DB::connection('sqlsrv')->table('subapplications')
                ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
                ->leftJoin('rofo', 'rofo.sub_application_id', '=', 'subapplications.id')
                ->whereIn('subapplications.id', $subApplicationIds)
                ->select(
                    'subapplications.id as sub_application_id',
                    'subapplications.fileno as sub_fileno',
                    'subapplications.applicant_title',
                    'subapplications.first_name',
                    'subapplications.surname',
                    'subapplications.corporate_name',
                    'subapplications.applicant_type',
                    'subapplications.multiple_owners_names',
                    'subapplications.specific_land_use',
                    'mother_applications.fileno as mother_fileno',
                    'mother_applications.property_house_no',
                    'mother_applications.property_plot_no',
                    'mother_applications.property_street_name',
                    'mother_applications.property_district',
                    'mother_applications.property_lga',
                    'mother_applications.land_use as mother_land_use',
                    'mother_applications.approval_date as mother_approval_date',
                    'subapplications.approval_date as sub_approval_date',
                    'rofo.rofo_no',
                    'rofo.location as rofo_location',
                    'rofo.plot_no as rofo_plot_no',
                    'rofo.purpose as rofo_purpose',
                    'rofo.approval_date'
                )
                ->get();

            foreach ($rows as $row) {
                if ($this->syncStRow($row, $userId)) {
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ST ROFO -> PRA batch sync failed', [
                'sub_application_ids' => $subApplicationIds,
                'error' => $e->getMessage(),
            ]);
        }

        return $count;
    }

    private function syncStRow($row, ?int $userId): bool
    {
        $fileNumber = $this->cleanString($row->sub_fileno ?? $row->mother_fileno ?? null);
        if ($fileNumber === null) {
            return false;
        }

        if ($this->praRowExists($fileNumber)) {
            return false;
        }

        $ownerName = $this->resolveStOwnerName($row);
        $locationParts = array_filter([
            $row->property_house_no ?? null,
            $row->property_plot_no ?? null,
            $row->property_street_name ?? null,
            $row->property_district ?? null,
        ]);
        $location = $row->rofo_location ?: trim(implode(' ', $locationParts));

        $payload = [
            'mlsFNo'             => $fileNumber,
            'fileno'             => $fileNumber,
            'rofo_number'        => $fileNumber,
            'instrument_type'    => 'Right of Occupancy (ST)',
            'transaction_type'   => 'Right of Occupancy (ST)',
            'transaction_date'   => $row->approval_date
                ?: ($row->sub_approval_date ?? null)
                ?: ($row->mother_approval_date ?? null)
                ?: now()->toDateString(),
            'land_use'           => $row->specific_land_use ?: $row->mother_land_use,
            'purpose'            => $row->rofo_purpose,
            'plot_no'            => $row->rofo_plot_no ?: ($row->property_plot_no ?? null),
            'house_no'           => $row->property_house_no,
            'location'           => $location,
            'property_description'=> $location,
            'lgsaOrCity'         => $row->property_lga,
            'Grantor'            => self::GRANTOR,
            'party_2'            => $ownerName,
            'Grantee'            => $ownerName,
            'source'             => 'st_rofo',
            'system_source'      => 'ST_ROFO',
        ];

        return $this->insert($payload, $userId, 'ST', [
            'sub_application_id' => $row->sub_application_id,
            'file_number' => $fileNumber,
        ]);
    }

    private function insert(array $payload, ?int $userId, string $flavour, array $logContext): bool
    {
        try {
            $this->service->createRecord($payload, $userId);
            return true;
        } catch (\Throwable $e) {
            Log::warning($flavour . ' ROFO -> PRA sync failed', array_merge($logContext, [
                'error' => $e->getMessage(),
            ]));
            return false;
        }
    }

    /** Return the source recommendation's capture date for PRA. */
    private function landTransactionDate(LandRecommendation $rec): string
    {
        $createdAt = $rec->created_at;

        return $createdAt instanceof \DateTimeInterface
            ? $createdAt->format('Y-m-d')
            : (!empty($createdAt) ? substr(trim((string) $createdAt), 0, 10) : now()->toDateString());
    }

    private function praRowExists(string $rofoNumber): bool
    {
        return DB::connection('sqlsrv')->table('pra')
            ->where('rofo_number', $rofoNumber)
            ->exists();
    }

    private function cleanString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function resolveStOwnerName($row): ?string
    {
        $type = strtolower(trim((string) ($row->applicant_type ?? '')));

        if ($type === 'corporate' && !empty($row->corporate_name)) {
            return trim($row->corporate_name);
        }

        if ($type === 'multiple' && !empty($row->multiple_owners_names)) {
            $names = json_decode($row->multiple_owners_names, true);
            if (is_array($names) && !empty($names)) {
                return implode(', ', $names);
            }
        }

        $parts = array_filter([
            trim((string) ($row->applicant_title ?? '')),
            trim((string) ($row->first_name ?? '')),
            trim((string) ($row->surname ?? '')),
        ]);

        return !empty($parts) ? implode(' ', $parts) : null;
    }
}
