<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'consent_applications';

    protected $fillable = [
        'application_tracking_no',
        'file_number',
        'c_of_o_no',
        'consent_type',
        'applicant_name',
        'applicant_address',
        'applicant_phone',
        'party_name',
        'party_address',
        'additional_parties',
        'additional_properties',
        'consideration',
        'consideration_words',
        'application_date',
        'application_submitted_date',
        'application_type',
        'status',
        'print_count',
        'user_id',
        'created_by',
        'property_description',
        'additional_applicants',
        'right_of_occupancy_number',
        'right_of_occupancy_landuse',
        'purpose_of_right_of_occupancy',
        'original_holder_name',
        'correspondence_address',
        'postal_address_gsm',
        'nationality_state_of_origin',
        'nationality',
            'state_of_origin',
            'stage_of_development',
        'location_of_right_of_occupancy',
        'date_of_grant',
        'date_of_grant_purpose',
        'special_mortgage_terms',

        // Valuation-linked consent bill. Written only by the server-side
        // calculator, never straight from request input.
        'valuation_report_id',
        'valuation_amount',
        'cgt_rate',
        'cgt_amount',
        'stamp_duty_rate',
        'registration_rate',
        'stamp_duty_amount',
        'registration_fee',
        'assignment_fee',
        'processing_fee',
        'bill_total',
        'bill_computed_at',
        'bill_overrides',

        // Manual valuation override — an authorised officer accepting a
        // valuation done before KLAES existed. Written only after the
        // permission has been re-checked server-side, never from request input
        // alone. valuation_report_id deliberately stays null on these rows.
        'manual_valuation',
        'manual_valuation_reason',
        'manual_valuation_ref',
        'manual_valuation_by',
        'manual_valuation_user_id',
        'manual_valuation_at',
    ];

    protected $casts = [
        'application_date' => 'date',
        'application_submitted_date' => 'date',
        'date_of_grant' => 'date',
        'bill_computed_at' => 'datetime',
        'valuation_amount' => 'decimal:2',
        'cgt_amount' => 'decimal:2',
        'stamp_duty_amount' => 'decimal:2',
        'registration_fee' => 'decimal:2',
        'assignment_fee' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'bill_total' => 'decimal:2',
        'bill_overrides' => 'array',
        'manual_valuation' => 'boolean',
        'manual_valuation_at' => 'datetime',
        'print_count' => 'integer',
        'additional_parties' => 'array',
        'additional_applicants' => 'array',
        'additional_properties' => 'array'
    ];

    /**
     * The date the applicant's own application bears — what the consent letter
     * means by "further to your application dated …".
     *
     * The form captures this in one of two fields depending on the variant:
     *   - "application" variant → application_submitted_date (the real one)
     *   - "letter" variant      → application_date ("Letter Date")
     * Only one is ever populated; the other is cleared and disabled. So the
     * submitted date wins, and the letter date is the fallback.
     *
     * Returns null when neither is set — callers must not substitute today's
     * date, which is what Carbon::parse(null) silently did.
     *
     * @return \Carbon\Carbon|null
     */
    public function getApplicationDatedAttribute()
    {
        return $this->application_submitted_date ?: $this->application_date;
    }

    /**
     * Get the user who created the application.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
