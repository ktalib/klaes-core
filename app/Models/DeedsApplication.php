<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeedsApplication extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        'draft' => 'Draft',
        'in_review' => 'In Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    protected $connection = 'sqlsrv';

    protected $table = 'deeds_applications';

    protected $fillable = [
        'application_number',
        'file_number',
        'subject',
        'status',
        'submitted_by',
        'submitted_at',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public static function defaultStatus(): string
    {
        return 'draft';
    }
}
