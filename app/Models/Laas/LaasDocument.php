<?php

namespace App\Models\Laas;

use Illuminate\Database\Eloquent\Model;

class LaasDocument extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'laas_documents';

    public const SOURCE_APPLICANT = 'applicant';
    public const SOURCE_OFFICE    = 'office';

    protected $fillable = [
        'laas_application_id',
        'source',
        'doc_type',
        'original_name',
        'path',
        'mime',
        'size',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'size'        => 'integer',
    ];

    public function application()
    {
        return $this->belongsTo(LaasApplication::class, 'laas_application_id');
    }
}
