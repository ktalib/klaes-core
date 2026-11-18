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
        // Minna / UTM Zone 32N first: the default (user's surveyor, 2026-11-13; the Beacon Register's own system).
        '26332' => 'Minna / UTM Zone 32N',
        '26391' => 'NTM West Belt (Minna)',
        '26392' => 'NTM Mid Belt (Minna)',
        '26393' => 'NTM East Belt (Minna)',
        '32632' => 'UTM 32N (WGS 84)',
    ];

    public function getCoordSystemLabelAttribute(): ?string
    {
        return $this->coord_system ? (self::COORD_SYSTEMS[$this->coord_system] ?? $this->coord_system) : null;
    }

    protected $casts = ['file_size' => 'integer', 'plot_data' => 'array', 'checked_at' => 'datetime'];

    /*
     | Survey Plan Management (user, Recording 5, 2026-11-16): a Manual Upload is a scanned / hard copy plan with its
     | coordinate system and one reference beacon; a Digital Plotting has no file, its beacons and traverse are plotted
     | on the map (plot_data). Plans from before have no method: they are all manual uploads.
     */
    public const METHOD_MANUAL = 'manual';
    public const METHOD_DIGITAL = 'digital';

    public const METHODS = [
        self::METHOD_MANUAL => 'Manual Upload',
        self::METHOD_DIGITAL => 'Digital Plotting',
    ];

    /**
     * Digital Plotting sends the plotting to the checking unit in one of these (Kunle, 2026-11-17: "automatically send the
     * data in the selected format to the person that will check ... either the format or the traverse with the reference
     * beacon details"). The traverse sheet is made from the saved plotting; the others are built by the card and attached.
     */
    public const CHECK_FORMATS = [
        'traverse' => 'Traverse + reference beacon details (CSV)',
        'shp'      => 'Shapefile',
        'gdb'      => 'Geodatabase',
        'dxf'      => 'DXF (CAD)',
        'geojson'  => 'GeoJSON',
        'kml'      => 'KML (Google Earth)',
        'csv'      => 'CSV (points)',
    ];
    public const CHECK_FILE_TYPES = ['zip', 'dxf', 'geojson', 'json', 'kml', 'csv'];

    public function getMethodAttribute(): string
    {
        return $this->plan_method === self::METHOD_DIGITAL ? self::METHOD_DIGITAL : self::METHOD_MANUAL;
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->method];
    }

    public function isDigital(): bool
    {
        return $this->method === self::METHOD_DIGITAL;
    }

    /** Manual includes the plans from before the split (no method). */
    public function scopeOfMethod($q, ?string $method)
    {
        return match ($method) {
            self::METHOD_DIGITAL => $q->where('plan_method', self::METHOD_DIGITAL),
            self::METHOD_MANUAL => $q->where(fn ($w) => $w->whereNull('plan_method')->orWhere('plan_method', '<>', self::METHOD_DIGITAL)),
            default => $q,
        };
    }

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
        return $this->source === 'portal' ? 'KN-MLPP-SG Portal' : 'KLAES (Web)';
    }

    /*
     | The four stages (Kunle, 2026-11-18), each with its own owner:
     |   1 Upload               the surveyor who drew the plan;
     |   2 Check                the checking unit: falls on location or not, corrections or none; back to the surveyor;
     |   3 Final Survey Plan    the surveyor: correct (if any), produce the final survey plan, upload; to the SG/DSG;
     |   4 Sign                 SG/DSG only: check/download, print, sign, upload.
     | `stage` holds the last stage done, so 1 = uploaded and waiting for the check, 4 = signed. step_no is the one open.
     */
    public const STAGE_UPLOADED = 1;
    /** Checked (with or without corrections): back with the surveyor for the final survey plan. */
    public const STAGE_CORRECTION = 2;
    public const STAGE_FINAL = 3;
    public const STAGE_SIGNED = 4;

    /** The stage names, by step. */
    public const STAGES = [
        1 => 'Upload',
        2 => 'Check',
        3 => 'Final Survey Plan',
        4 => 'Sign (SG/DSG)',
    ];

    /** Whose step it is, by step. */
    public const STAGE_OWNERS = [
        1 => 'Surveyor',
        2 => 'Checking unit',
        3 => 'Surveyor',
        4 => 'SG/DSG only',
    ];

    /** What the plan is waiting for, by `stage`. */
    public const STAGE_STATUS = [
        self::STAGE_UPLOADED => 'Awaiting check',
        self::STAGE_CORRECTION => 'Checked, awaiting the final survey plan',
        self::STAGE_FINAL => 'Awaiting signature (SG/DSG)',
        self::STAGE_SIGNED => 'Signed',
    ];

    /** The status column's value per stage (30 characters at most). */
    public const STAGE_CODES = [
        self::STAGE_UPLOADED => 'Submitted',
        self::STAGE_CORRECTION => 'Checked',
        self::STAGE_FINAL => 'Awaiting Signature',
        self::STAGE_SIGNED => 'Signed',
    ];

    /** Stage 2's two questions (Kunle, 2026-11-18). */
    public const CHECK_LOCATIONS = [
        'falls'     => 'Map falls on location',
        'not_falls' => 'Map does not fall on location (redraw)',
    ];
    public const CHECK_RESULTS = [
        'corrections' => 'Corrections',
        'none'        => 'No corrections',
    ];

    public const SIGN_DIGITAL = 'digital';
    public const SIGN_MANUAL = 'manual';

    /** Holders of these roles sign at stage 4. */
    public const SIGNING_ROLES = ['Survey - SG', 'Survey - DSG'];

    public function files()
    {
        return $this->hasMany(SurveyPlanFile::class, 'survey_plan_upload_id')->orderBy('id');
    }

    /** The Application Plan's entries saved from the pop-up (user, 2026-11-17). */
    public function applicationPlan()
    {
        return $this->hasOne(SurveyApplicationPlan::class, 'survey_plan_upload_id');
    }

    public function referenceBeacons()
    {
        return $this->hasMany(SurveyPlanReferenceBeacon::class, 'survey_plan_upload_id')->orderBy('sort')->orderBy('id');
    }

    public function getStageNoAttribute(): int
    {
        return max(1, min(4, (int) ($this->stage ?: 1)));
    }

    /** The stage open now (1 is done on upload, so a new plan is at 2, Check); a signed plan stays on 4. */
    public function getStepNoAttribute(): int
    {
        return min(4, $this->stage_no + ($this->stage_no === self::STAGE_SIGNED ? 0 : 1));
    }

    public function getStageLabelAttribute(): string
    {
        return $this->stage_no === self::STAGE_SIGNED ? 'Signed' : self::STAGES[$this->step_no];
    }

    public function getCheckLocationLabelAttribute(): ?string
    {
        return self::CHECK_LOCATIONS[$this->check_location] ?? null;
    }

    public function getCheckResultLabelAttribute(): ?string
    {
        return self::CHECK_RESULTS[$this->check_result] ?? null;
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
