<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of supporting evidence on a Master JSI.
 *
 * Kinds: site_photograph | location_coordinates | survey_plan | sketch | other.
 * Uploaded files live on the public disk (storage/app/public/parcel_documents/...)
 * exactly as the parcel-update site plans already do; coordinates and other simple
 * values are kept in `value` instead of a file. An attachment is never replaced or
 * deleted silently — a delete is an explicit, permission-gated user action.
 */
class MasterJsiEvidence extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'master_jsi_evidence';

    public const KIND_PHOTOGRAPH    = 'site_photograph';
    public const KIND_COORDINATES   = 'location_coordinates';
    public const KIND_SURVEY_PLAN   = 'survey_plan';
    public const KIND_SKETCH        = 'sketch';
    public const KIND_OTHER         = 'other';

    public const KINDS = [
        self::KIND_PHOTOGRAPH  => 'Site photograph',
        self::KIND_COORDINATES => 'Location coordinates',
        self::KIND_SURVEY_PLAN => 'Survey plan',
        self::KIND_SKETCH      => 'Sketch / site layout',
        self::KIND_OTHER       => 'Other supporting document',
    ];

    protected $fillable = [
        'master_jsi_report_id',
        'kind',
        'file_path',
        'file_name',
        'description',
        'value',
        'sequence',
        'created_by',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MasterJsiReport::class, 'master_jsi_report_id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst(str_replace('_', ' ', (string) $this->kind));
    }

    public function isImage(): bool
    {
        $ext = mb_strtolower(pathinfo((string) $this->file_path, PATHINFO_EXTENSION));

        return in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true);
    }

    /** Public URL for files on the public disk; null for value-only rows. */
    public function publicUrl(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return asset('storage/' . $this->file_path);
    }
}