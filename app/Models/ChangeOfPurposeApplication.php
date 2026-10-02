<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangeOfPurposeApplication extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'change_of_purpose_applications';

    protected $primaryKey = 'id';

    public $timestamps = true;

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_COMMISSIONED = 'commissioned';

    protected $fillable = [
        'source_module',
        // Batch Change of Purpose: how many plots this one file covers. Null or 1
        // is an ordinary single application. See the 2026_09_28_110000 migration.
        'plot_count',
        'file_no',
        'land_use',
        'purpose',
        'new_purpose',
        'plot_no',
        'plan_no',
        'location',
        'district',
        'lga',
        'applicant_name',
        'phone',
        'residential_address',
        'status',
        'remarks',
        'captured_by',
        'updated_by',
        'knupda_status',
        'knupda_fee',
        'land_value',
        'knupda_remarks',
        'application_generated_at',
        'recommendation_generated_at',
        // Recommendation memo fields — captured on the Generate Recommendation card.
        'rec_page_application',
        'rec_page_planning',
        'rec_page_site_plan',
        'rec_title_alias',
        'rec_measurement_a',
        'rec_measurement_b',
        'rec_term_years',
        'rec_commencement_date',
        'rec_residual_years',
        'site_plan',
        'is_deleted',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'rec_commencement_date' => 'date',
        'plot_count' => 'integer',
    ];

    /**
     * Does this application cover several plots?
     *
     * One file, several plots - not several files. The count decides how many file
     * numbers commissioning mints off the one source.
     */
    public function isBatch(): bool
    {
        return (int) ($this->plot_count ?? 1) > 1;
    }

    /** How many plots this application covers; 1 when none was recorded. */
    public function plotCount(): int
    {
        return max(1, (int) ($this->plot_count ?? 1));
    }
}
