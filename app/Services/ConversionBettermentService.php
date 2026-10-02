<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles betterment fee calculation and bill generation for Conversion Applications.
 *
 * Formula:  Total Fee = Base Fee  +  max(0, (Total Area − Base Area) × Rate Per Extra m²)
 *
 * Default rate table (configurable via pp_betterment_rates):
 *   Industrial            100 m²   ₦35,000
 *   Commercial            100 m²   ₦25,000
 *   Petrol Filling Station 100 m²  ₦50,000
 *   Agricultural         5,000 m²  ₦25,000
 *   Residential           100 m²   ₦15,000
 */
class ConversionBettermentService
{
    protected const EXISTING_STRUCTURE_META_DESCRIPTION = '__existing_structure__';
    protected const EXISTING_STRUCTURE_RATE = 7.5;

    /**
     * Get the betterment rate row for a given land-use category.
     */
    public function getRate(string $category): ?object
    {
        return DB::connection('sqlsrv')
            ->table('pp_betterment_rates')
            ->where('is_active', true)
            ->whereRaw('LOWER(category) = ?', [strtolower(trim($category))])
            ->first();
    }

    /**
     * Return all active rate rows (for UI dropdowns).
     */
    public function getAllRates(): \Illuminate\Support\Collection
    {
        return DB::connection('sqlsrv')
            ->table('pp_betterment_rates')
            ->where('is_active', true)
            ->orderBy('category')
            ->get();
    }

    /**
     * Calculate the betterment fee.
     *
     * @param  string $category   Land-use category (Residential, Commercial, etc.)
     * @param  float  $totalArea  Total site area in m²
     * @return array  ['base_fee','excess_area','excess_charge','total_fee','rate']
     */
    public function calculate(string $category, float $totalArea): array
    {
        $rate = $this->getRate($category);

        if (!$rate) {
            // Fallback to Residential if category not found
            $rate = $this->getRate('Residential');
        }

        if (!$rate) {
            return [
                'base_fee' => 0,
                'base_area' => 0,
                'excess_area' => 0,
                'excess_charge' => 0,
                'total_fee' => 0,
                'rate_per_extra_sqm' => 0,
                'category' => $category,
                'error' => 'No rate configuration found',
            ];
        }

        $baseFee = (float) $rate->base_fee;
        $baseArea = (float) $rate->base_area;
        $ratePerExtra = (float) $rate->rate_per_extra_sqm;

        $excessArea = max(0, $totalArea - $baseArea);
        $excessCharge = $excessArea * $ratePerExtra;
        $totalFee = $baseFee + $excessCharge;

        return [
            'base_fee' => $baseFee,
            'base_area' => $baseArea,
            'excess_area' => $excessArea,
            'excess_charge' => $excessCharge,
            'total_fee' => $totalFee,
            'rate_per_extra_sqm' => $ratePerExtra,
            'category' => $rate->category,
        ];
    }

    /**
     * Generate a unique PPBB-{YYYY}-{SEQ} reference ID.
     */
    public function generateRefId(): string
    {
        $year = date('Y');
        $prefix = "PPBB-{$year}-";

        $lastRef = DB::connection('sqlsrv')
            ->table('billing')
            ->where('ref_id', 'LIKE', "{$prefix}%")
            ->orderByRaw("CAST(REPLACE(ref_id, '{$prefix}', '') AS INT) DESC")
            ->value('ref_id');

        if ($lastRef) {
            $lastSeq = (int) str_replace($prefix, '', $lastRef);
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Create or update a betterment bill in the billing table.
     *
     * @param  int    $jsiReportId   JSI report ID (used as source_id)
     * @param  string $fileNumber
     * @param  string $category      Land-use category for rate lookup
     * @param  float  $totalArea     Total site area in m²
     * @param  int|null $applicationId
     * @return array  ['success','bill','calculation']
     */
    public function generateBill(int $jsiReportId, string $fileNumber, string $category, float $totalArea, ?int $applicationId = null): array
    {
        try {
            $calc = $this->calculate($category, $totalArea);

            $existingStructureMeta = $this->resolveExistingStructureCharge($jsiReportId);
            $calc['base_total_fee'] = (float) $calc['total_fee'];
            $calc['existing_structure_rate'] = self::EXISTING_STRUCTURE_RATE;
            $calc['has_existing_structure'] = $existingStructureMeta['has_existing_structure'];
            $calc['existing_structure_dimensions'] = $existingStructureMeta['dimensions'];
            $calc['existing_structure_volume'] = $existingStructureMeta['volume'];
            $calc['existing_structure_charge'] = $existingStructureMeta['charge'];
            $calc['total_fee'] = (float) $calc['total_fee'] + $existingStructureMeta['charge'];

            if (isset($calc['error'])) {
                return ['success' => false, 'message' => $calc['error']];
            }

            // Check for existing bill
            $existing = DB::connection('sqlsrv')
                ->table('billing')
                ->where('source', 'PP_BETTERMENT_BILL')
                ->where('source_id', (string) $jsiReportId)
                ->first();

            $refId = $existing->ref_id ?? $this->generateRefId();
            $now = now();

            $data = [
                'Sectional_Title_File_No' => $fileNumber,
                'application_id' => $applicationId ? (string) $applicationId : null,
                'Betterment_Charges' => (string) $calc['total_fee'],
                'property_value' => (string) $totalArea,
                'betterment_rate' => (string) $calc['rate_per_extra_sqm'],
                'ref_id' => $refId,
                'source' => 'PP_BETTERMENT_BILL',
                'source_id' => (string) $jsiReportId,
                'Payment_Status' => 'Pending',
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::connection('sqlsrv')
                    ->table('billing')
                    ->where('ID', $existing->ID)
                    ->update($data);
                $billId = $existing->ID;
                $message = 'Betterment bill updated successfully';
            } else {
                $data['created_at'] = $now;
                $billId = DB::connection('sqlsrv')
                    ->table('billing')
                    ->insertGetId($data, 'ID');
                $message = 'Betterment bill generated successfully';
            }

            $bill = DB::connection('sqlsrv')->table('billing')->where('ID', $billId)->first();

            Log::info("PP Betterment Bill: {$message}", [
                'bill_id' => $billId,
                'ref_id' => $refId,
                'jsi_report_id' => $jsiReportId,
                'total_fee' => $calc['total_fee'],
            ]);

            return [
                'success' => true,
                'message' => $message,
                'bill' => $bill,
                'calculation' => $calc,
            ];
        } catch (\Exception $e) {
            Log::error('PP Betterment Bill generation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error generating bill: ' . $e->getMessage()];
        }
    }

    protected function resolveExistingStructureCharge(int $jsiReportId): array
    {
        $default = [
            'has_existing_structure' => false,
            'dimensions' => null,
            'volume' => 0.0,
            'charge' => 0.0,
        ];

        $report = DB::connection('sqlsrv')
            ->table('joint_site_inspection_reports')
            ->select('existing_site_measurement_entries')
            ->where('id', $jsiReportId)
            ->first();

        if (!$report || !isset($report->existing_site_measurement_entries)) {
            return $default;
        }

        $entries = $report->existing_site_measurement_entries;

        if (is_string($entries)) {
            $decoded = json_decode($entries, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $entries = $decoded;
            }
        }

        if (!is_array($entries)) {
            return $default;
        }

        $meta = collect($entries)->first(function ($entry) {
            if (!is_array($entry)) {
                return false;
            }

            return strtolower(trim((string) ($entry['description'] ?? ''))) === self::EXISTING_STRUCTURE_META_DESCRIPTION;
        });

        if (!$meta || !is_array($meta)) {
            return $default;
        }

        $hasExistingStructure = ((int) ($meta['count'] ?? 0)) === 1;
        $dimensions = trim((string) ($meta['measurement_dimension'] ?? ''));

        if (!$hasExistingStructure || $dimensions === '') {
            return $default;
        }

        $parsed = collect(preg_split('/[xX×]/', $dimensions))
            ->map(function ($part) {
                $trimmed = trim((string) $part);
                return is_numeric($trimmed) ? (float) $trimmed : null;
            })
            ->filter(function ($value) {
                return $value !== null && $value >= 0;
            })
            ->values();

        if ($parsed->count() < 3) {
            return $default;
        }

        $volume = $parsed->take(3)->reduce(function ($carry, $value) {
            return $carry * $value;
        }, 1.0);

        if (!is_finite($volume) || $volume <= 0) {
            return $default;
        }

        $charge = $volume * self::EXISTING_STRUCTURE_RATE;

        return [
            'has_existing_structure' => true,
            'dimensions' => implode('x', $parsed->take(3)->all()),
            'volume' => $volume,
            'charge' => $charge,
        ];
    }
}
