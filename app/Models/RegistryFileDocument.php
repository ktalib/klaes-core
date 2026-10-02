<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single scanned image / document inside a registry file folder.
 */
class RegistryFileDocument extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'registry_file_documents';

    protected $fillable = [
        'registry_file_folder_id',
        'category',
        'filename',
        'relative_path',
        'extension',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(RegistryFileFolder::class, 'registry_file_folder_id');
    }

    /** Whether this document is a previewable image (vs. e.g. a PDF). */
    public function isImage(): bool
    {
        return in_array(strtolower((string) $this->extension), (array) config('registry_sources.image_extensions', []), true);
    }
}
