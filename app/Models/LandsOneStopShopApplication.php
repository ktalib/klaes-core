<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * LandsOneStopShopApplication
 *
 * Represents an OSS application record (Residential, Commercial, or Industrial).
 * Backed by the `oss_applications` table in SQL Server.
 */
class LandsOneStopShopApplication extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'oss_applications';

    protected $primaryKey = 'id';

    public $timestamps = true;

    public const TYPE_RESIDENTIAL = 'residential';
    public const TYPE_COMMERCIAL = 'commercial';
    public const TYPE_INDUSTRIAL = 'industrial';
    public const TYPE_AGRICULTURAL = 'agricultural';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'instrument_capture_id',
        'application_type',
        'applicant_name',
        'address',
        'phone',
        'email',
        'state_of_origin',
        'lga',
        'file_no',
        'plot_no',
        'plan_no',
        'location',
        'district',
        'land_use',
        'land_area',
        'property_description',
        'purpose',
        'nature_of_development',
        'proposed_dev_value',
        'annual_ground_rent',
        'development_charge',
        'survey_processing_charges',
        'term_years',
        'business_name',
        'business_type',
        'rc_number',
        'industry_type',
        'product_type',
        'environmental_clearance',
        'status',
        'remarks',
        'captured_by',
        'updated_by',
        // ── New form fields ──
        'age',
        'sex',
        'marital_status',
        'husband_name_address',
        'residential_address',
        'correspondence_address',
        'business_address',
        'nationality',
        'occupation',
        'annual_income',
        'prev_allocated',
        'prev_allocation_details',
        'home_domicile',
        'occupation_or_business',
        'nature_of_commerce',
        'company_registered_under',
        'registration_particulars',
        'business_location',
        'annual_income_anticipation',
        'prev_land_purpose',
        'intended_activities',
        'nature_of_occupation',
        'annual_income_turnover',
        'number_of_employees',
        'nature_of_industrial',
        'waste_disposal_requirements',
        'nature_of_agricultural',
        // ── Address builder sub-fields ──
        'res_addr_house',
        'res_addr_plot',
        'res_addr_street',
        'res_addr_street_other',
        'res_addr_district',
        'res_addr_district_other',
        'res_addr_lga',
        'res_addr_state',
        'res_corr_house',
        'res_corr_plot',
        'res_corr_street',
        'res_corr_street_other',
        'res_corr_district',
        'res_corr_district_other',
        'res_corr_lga',
        'res_corr_state',
        'res_biz_house',
        'res_biz_plot',
        'res_biz_street',
        'res_biz_street_other',
        'res_biz_district',
        'res_biz_district_other',
        'res_biz_lga',
        'res_biz_state',
        'com_corr_house',
        'com_corr_plot',
        'com_corr_street',
        'com_corr_street_other',
        'com_corr_district',
        'com_corr_district_other',
        'com_corr_lga',
        'com_corr_state',
        'com_biz_house',
        'com_biz_plot',
        'com_biz_street',
        'com_biz_street_other',
        'com_biz_district',
        'com_biz_district_other',
        'com_biz_lga',
        'com_biz_state',
        'ind_corr_house',
        'ind_corr_plot',
        'ind_corr_street',
        'ind_corr_street_other',
        'ind_corr_district',
        'ind_corr_district_other',
        'ind_corr_lga',
        'ind_corr_state',
        'ind_biz_house',
        'ind_biz_plot',
        'ind_biz_street',
        'ind_biz_street_other',
        'ind_biz_district',
        'ind_biz_district_other',
        'ind_biz_lga',
        'ind_biz_state',
        // Agricultural
        'agr_corr_house',
        'agr_corr_plot',
        'agr_corr_street',
        'agr_corr_street_other',
        'agr_corr_district',
        'agr_corr_district_other',
        'agr_corr_lga',
        'agr_corr_state',
        'agr_biz_house',
        'agr_biz_plot',
        'agr_biz_street',
        'agr_biz_street_other',
        'agr_biz_district',
        'agr_biz_district_other',
        'agr_biz_lga',
        'agr_biz_state',
        // Passport photo
        'passport_photo',
        // System source for Change of Name filtering
        'system_source',
        // Temporary File recommendation tracking
        'recommendation_generated_at',
        'op_serial_number',
        // Subdivision & Merger
        'temp_file_no',
        'file_title',
        'house_no',
        'street_name',
        'state',
        'num_plots',
        'is_deleted',
        'deleted_by',
    ];

    protected $casts = [
        'recommendation_generated_at' => 'datetime',
        'proposed_dev_value' => 'decimal:2',
        'annual_ground_rent' => 'decimal:2',
        'development_charge' => 'decimal:2',
        'survey_processing_charges' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [];

    /* ── Scopes ── */

    public function scopeSearch($query, ?string $term)
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('applicant_name', 'LIKE', "%{$term}%")
                ->orWhere('file_no', 'LIKE', "%{$term}%")
                ->orWhere('plot_no', 'LIKE', "%{$term}%")
                ->orWhere('plan_no', 'LIKE', "%{$term}%")
                ->orWhere('location', 'LIKE', "%{$term}%")
                ->orWhere('property_description', 'LIKE', "%{$term}%")
                ->orWhere('phone', 'LIKE', "%{$term}%")
                ->orWhere('business_name', 'LIKE', "%{$term}%");
        });
    }

    public function scopeOfType($query, ?string $type)
    {
        if (!$type) {
            return $query;
        }

        return $query->where('application_type', $type);
    }

    public function scopeByStatus($query, ?string $status)
    {
        if (!$status) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /* ── Helpers ── */

    public static function typeOptions(): array
    {
        return [
            self::TYPE_RESIDENTIAL => 'Residential',
            self::TYPE_COMMERCIAL => 'Commercial',
            self::TYPE_INDUSTRIAL => 'Industrial',
            self::TYPE_AGRICULTURAL => 'Agricultural',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    public function plotSizes()
    {
        return $this->hasMany(OssApplicationPlotSize::class, 'oss_application_id');
    }
}
