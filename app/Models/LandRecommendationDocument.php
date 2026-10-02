<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The already-approved recommendation letter, scanned, for one file.
 *
 * Files whose Occupancy Permit holder differs from the File Indexing name have
 * been through recommendation once already. That letter is approved and will not
 * go for approval again, so no new one is generated for them — the signed sheet is
 * uploaded here instead, and the record cannot be approved until it is.
 *
 * One row per land_recommendation_id — see the unique index. A re-upload replaces
 * the row (and the file behind it) rather than adding a second.
 *
 * @see \App\Http\Controllers\LandRecommendationOpMatchController
 */
class LandRecommendationDocument extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'land_recommendation_documents';

    /** Where the scans live on the 'public' disk. */
    public const DIRECTORY = 'land_recommendations/approved_letters';

    protected $fillable = [
        'land_recommendation_id',
        'file_number',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'size_bytes'  => 'integer',
    ];

    public function recommendation()
    {
        return $this->belongsTo(LandRecommendation::class, 'land_recommendation_id');
    }

    /** Browser-facing URL for the stored scan, through the public/storage symlink. */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * Does the viewer's browser render this inline, or does it need a download?
     * Images and PDFs open in a tab; anything else is handed over as a file.
     */
    public function isViewableInline(): bool
    {
        $mime = strtolower((string) $this->mime_type);

        return str_starts_with($mime, 'image/') || $mime === 'application/pdf';
    }

    /** What the file is, for a label: "JPG · 1.2 MB". */
    public function summary(): string
    {
        $extension = strtoupper(pathinfo((string) $this->path, PATHINFO_EXTENSION));
        $bytes = (int) $this->size_bytes;

        if ($bytes <= 0) {
            return $extension;
        }

        $size = $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : max(1, (int) round($bytes / 1024)) . ' KB';

        return trim($extension . ' · ' . $size, ' ·');
    }
}
