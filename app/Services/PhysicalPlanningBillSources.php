<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The application types billed from Physical Planning > Bills & Payments. Bills, Payments
 * and Payment Reports are one central area with a tab per type; Sectional Titling keeps its
 * own bill screens (no ?source on Bills/Payments), the others are betterment bills raised
 * from their inspection reports.
 */
class PhysicalPlanningBillSources
{
    /** The Sectional Titling tab of Payment Reports. */
    public const ST = 'pp_st';

    /** Tab order, as the Director set it. */
    public const SOURCES = [
        'pp_sltr' => [
            'label' => 'SLTR',
            'billing_source' => 'PP_SLTR_BETTERMENT_BILL',
            'report_table' => 'sltr_joint_site_inspection_reports',
            'has_measurement_entries' => true,
            // Betterment is charged on the JSI's Recommended area (PP, 2026-11-19).
            'bill_area' => 'recommended',
        ],
        'pp_conversion' => [
            'label' => 'Conversion',
            'billing_source' => 'PP_BETTERMENT_BILL',
            'report_table' => 'joint_site_inspection_reports',
            'has_measurement_entries' => true,
            'bill_area' => 'recommended',
        ],
        'pp_master_jsi' => [
            'label' => 'Master JSI (Post CofO)',
            'billing_source' => 'PP_MASTER_JSI_BETTERMENT_BILL',
            'report_table' => 'master_jsi_reports',
            'has_measurement_entries' => false,
            // Left on the existing area for now (user, 2026-11-19).
            'bill_area' => 'existing',
        ],
    ];

    public static function has(?string $key): bool
    {
        return $key !== null && isset(self::SOURCES[$key]);
    }

    public static function get(string $key): array
    {
        return self::SOURCES[$key];
    }

    /** The source that raised a billing row, or null for Sectional Titling and other bills. */
    public static function keyForBillingSource(?string $billingSource): ?string
    {
        foreach (self::SOURCES as $key => $source) {
            if ($source['billing_source'] === $billingSource) {
                return $key;
            }
        }

        return null;
    }

    /** Is this request inside the central Physical Planning Bills & Payments area? */
    public static function isCentral(Request $request): bool
    {
        $source = $request->get('source');

        return $request->get('url') === 'physical_planning'
            || $source === self::ST
            || self::has($source);
    }

    /** The active tab's key. */
    public static function activeTab(Request $request): string
    {
        $source = $request->get('source');

        return self::has($source) ? $source : self::ST;
    }

    /**
     * Tabs for the central pages.
     *
     * @param  string  $page  bills | payments | reports
     * @return array<int, array{key: string, label: string, url: string}>
     */
    public static function tabs(string $page): array
    {
        $route = $page === 'bills' ? route('programmes.bills') : route('programmes.payments');

        $tabs = [[
            'key' => self::ST,
            'label' => 'Sectional Titling',
            'url' => $route . ($page === 'reports' ? '?url=report&source=' . self::ST : '?url=physical_planning'),
        ]];

        foreach (self::SOURCES as $key => $source) {
            $tabs[] = [
                'key' => $key,
                'label' => $source['label'],
                'url' => $route . '?source=' . $key . ($page === 'reports' ? '&url=report' : ''),
            ];
        }

        return $tabs;
    }

    /**
     * Inspection reports a betterment bill can be raised from, in one shape whichever
     * table they live in. Aliased jsi.
     */
    public function reports(string $key)
    {
        $source = self::get($key);
        $query = DB::connection('sqlsrv')->table($source['report_table'] . ' as jsi');

        if ($key === 'pp_conversion') {
            $query->whereNull('jsi.sub_application_id')
                ->where(function ($q) {
                    $q->where('jsi.source', 'Conversion Applications')
                        ->orWhere('jsi.file_number', 'LIKE', 'CON-%');
                });
        }

        if ($key === 'pp_master_jsi') {
            $query->whereRaw('ISNULL(jsi.is_deleted, 0) = 0');
        }

        return $query->select($this->reportColumns($key));
    }

    /**
     * The JSI's Recommended Total Area Covered (m²), or recommended length x width when only
     * two dimensions were entered; null when the JSI has no recommended area.
     */
    public static function recommendedArea(object $report): ?float
    {
        if (is_numeric($report->recommended_site_area ?? null) && (float) $report->recommended_site_area > 0) {
            return round((float) $report->recommended_site_area, 2);
        }

        if (is_numeric($report->recommended_site_length ?? null) && is_numeric($report->recommended_site_width ?? null)) {
            $area = (float) $report->recommended_site_length * (float) $report->recommended_site_width;
            return $area > 0 ? round($area, 2) : null;
        }

        return null;
    }

    public function report(string $key, int $id): ?object
    {
        return $this->reports($key)->where('jsi.id', $id)->first();
    }

    /** Report columns under the names the bill and payment screens read. */
    public function reportColumns(string $key): array
    {
        if ($key === 'pp_master_jsi') {
            return [
                'jsi.id',
                'jsi.file_number',
                'jsi.applicant_name',
                DB::raw('COALESCE(jsi.recommended_land_use, jsi.existing_land_use) as applied_land_use'),
                DB::raw('jsi.subject_id as application_id'),
                DB::raw('jsi.site_existing_area_sqm as existing_site_area'),
                DB::raw('NULL as existing_site_length'),
                DB::raw('NULL as existing_site_width'),
                DB::raw('NULL as existing_site_measurement_entries'),
                DB::raw('NULL as recommended_site_area'),
                DB::raw('NULL as recommended_site_length'),
                DB::raw('NULL as recommended_site_width'),
                DB::raw('NULL as has_extension'),
                DB::raw('NULL as extension_site_area'),
                'jsi.plot_number',
                'jsi.location',
            ];
        }

        return [
            'jsi.id',
            'jsi.file_number',
            'jsi.applicant_name',
            'jsi.applied_land_use',
            'jsi.application_id',
            'jsi.existing_site_area',
            'jsi.existing_site_length',
            'jsi.existing_site_width',
            'jsi.existing_site_measurement_entries',
            'jsi.recommended_site_area',
            'jsi.recommended_site_length',
            'jsi.recommended_site_width',
            'jsi.has_extension',
            'jsi.extension_site_area',
            'jsi.plot_number',
            'jsi.location',
        ];
    }

    /** Public URL of a stored evidence file (file numbers can contain spaces). */
    public static function evidenceUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return asset('storage/' . implode('/', array_map('rawurlencode', explode('/', $path))));
    }
}
