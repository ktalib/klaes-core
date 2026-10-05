<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SltrRecommendation extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'sqlsrv';
    protected $table = 'sltr_recommendations';

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';

    const ROFO_PENDING   = 'pending';
    const ROFO_GENERATED = 'generated';

    const LAND_USE_OPTIONS = [
        'Residential',
        'Commercial',
        'Industrial',
        'Agricultural',
        'Institutional',
        'Mixed Use',
        'Public Use',
        'Recreational',
    ];

    const PURPOSE_OPTIONS = [
        'RESIDENTIAL (VERY HIGH DENSITY)',
        'RESIDENTIAL (HIGH DENSITY)',
        'RESIDENTIAL (MEDIUM DENSITY)',
        'RESIDENTIAL (LOW DENSITY)',
        'COMMERCIAL',
        'COMMERCIAL (GENERAL)',
        'COMMERCIAL (RETAIL)',
        'INDUSTRIAL (LIGHT)',
        'INDUSTRIAL (HEAVY)',
        'AGRICULTURAL',
        'INSTITUTIONAL',
        'MIXED USE',
        'PUBLIC USE',
        'RECREATIONAL',
    ];

    protected $fillable = [
        'sltr_number',
        'applicant_name',
        'applicant_address',
        'applicant_phone',
        'application_date',
        // The date the letter is issued, printed on it as DATE OF ISSUE. Its own
        // column: application_date is the applicant's and rofo_date_generated is
        // this system's, and neither is the printer's to edit.
        'date_issued',
        'location',
        'lga',
        'land_use',
        // Which line of the fee schedule this recommendation was priced from --
        // or, for "Other", the sub-type the officer named when there was no line.
        'land_use_subtype',
        'plot_number',
        'page_application',
        'page_survey',
        'page_planning',
        'term',
        'revision_period',
        'ground_rent',
        // The unit the rent is charged by, stored as the words that print.
        'ground_rent_unit',
        // Plot size in m², the quantity the ground rent rate is charged on.
        'plot_size',
        'processing_fee',
        'purpose_of_clause',
        'notes',
        'status',
        'approved_at',
        'approved_by',
        'rofo_status',
        'rofo_generated_at',
        'rofo_print_count',
        'rofo_director_survey',
        'rofo_licensed_surveyor',
        'rofo_date_generated',
        'created_by',
        'updated_by',
        'sltr_rofo_serial_no',
        'printed_at',
    ];

    /** The fixed choices offered for the ground rent unit; anything else is "Other". */
    public const GROUND_RENT_UNITS = \App\Support\GroundRentUnit::OPTIONS;

    /**
     * The rent as it should read on a letter: the figure and the unit it is charged
     * by, e.g. "5,000.00 Per Square Meters".
     *
     * Replaces the " per sq meter" that the recommendation template used to append
     * to every record regardless of what was agreed.
     */
    public function getGroundRentLabelAttribute(): string
    {
        return \App\Support\GroundRentUnit::label($this->ground_rent, $this->ground_rent_unit);
    }

    /**
     * What is owed: the ground rent RATE times the plot size. Null until both are
     * known, or when the rate is in a unit typed under "Other".
     */
    public function getGroundRentAmountAttribute(): ?float
    {
        return \App\Support\SltrPlotSize::amount($this->ground_rent, $this->ground_rent_unit, $this->plot_size);
    }

    protected $casts = [
        'application_date'   => 'date',
        'date_issued'        => 'date',
        'approved_at'        => 'datetime',
        'rofo_generated_at'  => 'datetime',
        'rofo_date_generated'=> 'date',
        'printed_at'         => 'datetime',
        'ground_rent'        => 'decimal:2',
        'plot_size'          => 'decimal:2',
        'processing_fee'     => 'decimal:2',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
