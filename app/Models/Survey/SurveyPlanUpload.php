<?php

namespace App\Models\Survey;

/**
 * A survey plan uploaded for checking (Survey Update 5g-5i, user 2026-11-10), from KLAES or the MLPP-SG
 * portal. The unit chosen on upload (GKN for public / government, Examination/Verification for private)
 * receives it under Checking & Passing (Survey Plan) > Uploaded Survey Plan.
 */
class SurveyPlanUpload extends SurveyModel
{
    protected $table = 'survey_plan_uploads';

    public const DISK = 'local';

    public const UNIT_GKN = 'GKN';
    public const UNIT_EV = 'Examination/Verification';

    /** Unit => the label on the form. */
    public const UNITS = [
        self::UNIT_GKN => 'GKN (Public / Government)',
        self::UNIT_EV  => 'Examination/Verification (Private)',
    ];

    /** The role whose holders receive a unit's uploads. */
    public const UNIT_ROLES = [
        self::UNIT_GKN => 'Survey - GKN',
        self::UNIT_EV  => 'Survey - Examination/Verification',
    ];

    public const TYPES = ['Final Survey Plan', 'Application Plan'];

    public const FILE_TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff'];

    protected $casts = ['file_size' => 'integer'];

    public static function installed(): bool
    {
        static $has;

        return $has ??= \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('survey_plan_uploads');
    }

    public function portalAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SurveyorPortalAccount::class, 'surveyor_portal_account_id');
    }

    public function getSourceLabelAttribute(): string
    {
        return $this->source === 'portal' ? 'MLPP-SG Portal' : 'KLAES';
    }
}
