<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Model;

/**
 * A file added to a survey plan after the first upload (CR 11112026-2): the checking unit's
 * correction (stage 2), the uploader's final copy (stage 3) or the SG/DSG's signed copy (stage 4).
 * Kept on the private disk beside the plan; never deleted.
 */
class SurveyPlanFile extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'survey_plan_files';

    protected $guarded = ['id'];

    public const KIND_CORRECTION = 'correction';
    public const KIND_FINAL = 'final';
    public const KIND_SIGNED = 'signed';
    /** Digital Plotting's data for the checker, in the format the plotter chose (stage 1; 2026-11-18). */
    public const KIND_PLOTTED = 'plotted';
    /** file_path of a traverse sheet: made from the saved plotting when opened, nothing on disk. */
    public const GENERATED_TRAVERSE = 'generated:traverse';

    public const KIND_LABELS = [
        self::KIND_PLOTTED => 'Plotted data',
        self::KIND_CORRECTION => 'Correction',
        self::KIND_FINAL => 'Final copy',
        self::KIND_SIGNED => 'Signed copy',
    ];

    protected $casts = ['file_size' => 'integer', 'stage' => 'integer'];

    public function plan()
    {
        return $this->belongsTo(SurveyPlanUpload::class, 'survey_plan_upload_id');
    }

    public function getKindLabelAttribute(): string
    {
        return self::KIND_LABELS[$this->kind] ?? ucfirst((string) $this->kind);
    }
}
