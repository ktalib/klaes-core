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
        self::UNIT_GKN => 'GKN (Public)',
        self::UNIT_EV  => 'Examination/Verification (Private)',
    ];

    /** The role whose holders receive a unit's uploads. */
    public const UNIT_ROLES = [
        self::UNIT_GKN => 'Survey - GKN',
        self::UNIT_EV  => 'Survey - Examination/Verification',
    ];

    public const TYPES = ['Final Survey Plan', 'Application Plan'];

    public const FILE_TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff'];

    /** The coordinate systems a plan's X / Y can be in (Survey Plan Check, user 2026-11-12), by EPSG code. */
    public const COORD_SYSTEMS = [
        '26391' => 'NTM West Belt (Minna)',
        '26392' => 'NTM Mid Belt (Minna)',
        '26393' => 'NTM East Belt (Minna)',
        '32632' => 'UTM 32N (WGS 84)',
    ];

    public function getCoordSystemLabelAttribute(): ?string
    {
        return $this->coord_system ? (self::COORD_SYSTEMS[$this->coord_system] ?? $this->coord_system) : null;
    }

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
        return $this->source === 'portal' ? 'MLPP-SG Portal' : 'KLAES (Web)';
    }

    /*
     | The four stages (CR 11112026-2, item 5). `stage` is where the plan is now.
     |   1 Upload          sent, waiting for the unit to check it;
     |   2 Correction      the unit sent corrections back; waiting for the uploader's final copy;
     |   3 Final copy      at the SG/DSG for signing;
     |   4 Signed          the signed copy is uploaded; done.
     */
    public const STAGE_UPLOADED = 1;
    public const STAGE_CORRECTION = 2;
    public const STAGE_FINAL = 3;
    public const STAGE_SIGNED = 4;

    public const STAGES = [
        self::STAGE_UPLOADED => 'Upload',
        self::STAGE_CORRECTION => 'Correction',
        self::STAGE_FINAL => 'Final Copy',
        self::STAGE_SIGNED => 'Signed Copy',
    ];

    /** What the plan is waiting for, by stage. */
    public const STAGE_STATUS = [
        self::STAGE_UPLOADED => 'Awaiting check',
        self::STAGE_CORRECTION => 'Corrections sent, awaiting final copy',
        self::STAGE_FINAL => 'Awaiting signature (SG/DSG)',
        self::STAGE_SIGNED => 'Signed',
    ];

    /** The status column's value per stage (30 characters at most). */
    public const STAGE_CODES = [
        self::STAGE_UPLOADED => 'Submitted',
        self::STAGE_CORRECTION => 'Corrections Sent',
        self::STAGE_FINAL => 'Awaiting Signature',
        self::STAGE_SIGNED => 'Signed',
    ];

    public const SIGN_DIGITAL = 'digital';
    public const SIGN_MANUAL = 'manual';

    /** Holders of these roles sign at stage 4. */
    public const SIGNING_ROLES = ['Survey - SG', 'Survey - DSG'];

    public function files()
    {
        return $this->hasMany(SurveyPlanFile::class, 'survey_plan_upload_id')->orderBy('id');
    }

    public function referenceBeacons()
    {
        return $this->hasMany(SurveyPlanReferenceBeacon::class, 'survey_plan_upload_id')->orderBy('sort')->orderBy('id');
    }

    public function getStageNoAttribute(): int
    {
        return max(1, min(4, (int) ($this->stage ?: 1)));
    }

    public function getStageLabelAttribute(): string
    {
        return self::STAGES[$this->stage_no];
    }

    public function getStageStatusAttribute(): string
    {
        return self::STAGE_STATUS[$this->stage_no];
    }

    /** The newest file of a kind (final / signed / correction), or null. */
    public function latestFile(string $kind): ?SurveyPlanFile
    {
        return $this->files->where('kind', $kind)->sortByDesc('id')->first();
    }
}
