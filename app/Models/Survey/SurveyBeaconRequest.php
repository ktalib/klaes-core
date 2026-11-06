<?php

namespace App\Models\Survey;

/**
 * A request for beacon numbers to the Surveyor General (CR 03112026 items 19-21), from the portal or
 * from a Cadastral I to S. Submitted, then Issued or Rejected by the SG, then Accepted by the requester.
 * The numbers themselves are ordinary Beacon Register lines, linked by survey_beacon_request_id.
 */
class SurveyBeaconRequest extends SurveyModel
{
    protected $table = 'survey_beacon_requests';

    public const SUBMITTED = 'Submitted';
    public const ISSUED = 'Issued';
    public const REJECTED = 'Rejected';
    public const ACCEPTED = 'Accepted';

    public const SOURCE_PORTAL = 'portal';
    public const SOURCE_CADASTRAL = 'cadastral_its';

    protected $casts = ['reviewed_at' => 'datetime', 'accepted_at' => 'datetime', 'latitude' => 'float', 'longitude' => 'float'];

    public function lines()
    {
        return $this->hasMany(SurveyBeaconRegister::class, 'survey_beacon_request_id')->orderBy('beacon_start_no');
    }

    public function account()
    {
        return $this->belongsTo(SurveyorPortalAccount::class, 'surveyor_portal_account_id');
    }

    public function getSourceLabelAttribute(): string
    {
        return $this->source === self::SOURCE_CADASTRAL ? 'Cadastral I to S' : 'Portal';
    }

    public function getTypeLabelAttribute(): string
    {
        return SurveyBeaconRegister::LAYOUT_TYPES[$this->layout_type] ?? (string) $this->layout_type;
    }

    /** Next BRQ-YYYY-0001. The unique index on request_no catches two at once. */
    public static function nextNumber(): string
    {
        $prefix = 'BRQ-' . now()->year . '-';
        $last = static::withTrashed()->where('request_no', 'like', $prefix . '%')->max('request_no');

        return $prefix . str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), 4, '0', STR_PAD_LEFT);
    }
}
