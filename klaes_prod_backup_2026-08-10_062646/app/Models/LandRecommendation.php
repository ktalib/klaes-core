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

    /**
     * The completion time as it should read on a letter.
     *
     * The form captures a plain number of years, but the records keyed before it
     * did that hold the unit in the text ("2 years", and a handful of "NIL").
     * Printing the raw column would give the new records a bare "2" where every
     * earlier letter says "2 years", so the unit is restored here — and anything
     * that is not a bare number is left exactly as it was recorded.
     */
    public function getDevelopmentPeriodLabelAttribute(): string
    {
        $value = trim((string) ($this->development_period ?? ''));

        if ($value === '' || !ctype_digit($value)) {
            return $value;
        }

        return $value . ((int) $value === 1 ? ' year' : ' years');
    }

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

    public function landUse()
    {
        return $this->belongsTo(LandUse::class, 'land_use_id');
    }

    public function purpose()
    {
        return $this->belongsTo(Purpose::class, 'purpose_id');
    }

    /**
     * Land use and purpose clause are captured as a pair (the purpose list is filtered
     * by the selected land use), so they are displayed together as "Landuse (Purpose
     * Clause)".
     *
     * Only `purpose_of_clause` is denormalized onto the row, and older records were
     * saved with the ids only, so each side falls back to its lookup table before
     * giving up: purpose_of_clause -> purposes.name, land_use -> land_uses.landuse
     * (via land_use_id, or via the purpose's own land use when only purpose_id is
     * set). Either side may still end up blank, in which case whichever one resolved
     * is shown on its own.
     */
    public function getLandusePurposeAttribute(): string
    {
        $purpose = trim((string) ($this->purpose_of_clause ?? ''));
        if ($purpose === '') {
            $purpose = trim((string) ($this->purpose->name ?? ''));
        }

        $landUse = trim((string) ($this->land_use ?? ''));
        if ($landUse === '') {
            $landUse = trim((string) ($this->landUse->landuse ?? $this->purpose->landUse->landuse ?? ''));
        }

        if ($landUse === '' || $purpose === '') {
            return $landUse === '' ? $purpose : $landUse;
        }

        // Much of the existing data already stores the land use inside the purpose
        // ("COMMERCIAL", "COMMERCIAL (HOTEL)" against land use "COMMERCIAL"), so
        // pairing them blindly would print "COMMERCIAL (COMMERCIAL (HOTEL))".
        if (stripos($purpose, $landUse) === 0) {
            return $purpose;
        }

        return "{$landUse} ({$purpose})";
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
