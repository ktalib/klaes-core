<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandRecommendation extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'land_recommendations';
    public $timestamps = true;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';

    const ROFO_PENDING = 'pending';
    const ROFO_GENERATED = 'generated';

    protected $fillable = [
        'file_number',
        'old_file_number',
        'rofo_batch_id',
        'batch_mother_file_no',
        'batch_seq',
        'applicant_name',
        'purpose_of_clause',
        'location',
        'lga',
        'area_sqm',
        'term',
        'cofo_year',
        'selected_year',
        'ground_rent',
        'effective_date',
        'premium',
        'development_period',
        'survey_fees',
        'preparation_fees',
        'land_use',
        'land_use_id',
        'purpose_id',
        'meeting_date',
        'recommendation',
        'plot_number',
        'house_no',
        'street_name',
        'district',
        'state',
        'layout_plan_no',
        'development_value',
        'development_charge',
        'tracking_id',
        'status',
        'approved_at',
        'rofo_status',
        'rofo_generated_at',
        'rofo_print_count',
        'rofo_survey_fees',
        'rofo_dev_charge',
        'rofo_director_survey',
        'rofo_licensed_surveyor',
        'rofo_land_use_category',
        'rofo_date_generated',
        'rofo_time_generated',
        'print_count',
        'application_date',
        'applicant_address',
        'created_by',
        'updated_by',
        'edit_reason',
        'land_rofo_serial_no',
        'type',
        'application_type',
        'use_standard_template',
        'is_reissuance',
        'reissuance_source',
        'reissued_from_id',
        'reissuance_original_date',
        'num_plots',
        'file_title',
        'premium_words',
        'preparation_fees_words',
        'plot_sizes',
        'page',
        'page_2',
        'page_3',
        'page_4',
        'page_5',
        'purpose_description',
        'dimensions_text',
        'page_survey_report',
        'survey_report',
        'improvement',
        'revision_period',
        'time_of_erection',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'meeting_date' => 'date',
        'premium' => 'decimal:2',
        'ground_rent' => 'decimal:2',
        'survey_fees' => 'decimal:2',
        'development_value' => 'decimal:2',
        // development_charge is stored as free text (e.g. "To follow"), so it must
        // not be cast to a decimal — casting throws on non-numeric values.
        // 'development_charge' => 'decimal:2',
        'approved_at' => 'datetime',
        'rofo_generated_at' => 'datetime',
        'application_date' => 'date',
        'use_standard_template' => 'boolean',
        'is_reissuance' => 'boolean',
        'reissuance_original_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The stored `location` is auto-built as "<plot/house no> <street> <district> <lga> <state>",
     * but Plot No. / House No. are already shown in their own field wherever this is displayed,
     * so strip a leading occurrence of either before showing the location.
     */
    public function getDisplayLocationAttribute(): string
    {
        $location = trim((string) ($this->location ?? ''));

        foreach ([$this->plot_number, $this->house_no] as $prefixValue) {
            $prefixValue = trim((string) ($prefixValue ?? ''));
            if ($prefixValue !== '' && stripos($location, $prefixValue) === 0) {
                $location = trim(substr($location, strlen($prefixValue)));
                break;
            }
        }

        return trim(preg_replace('/\s+/', ' ', $location));
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
