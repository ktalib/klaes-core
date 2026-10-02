<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangeOfNameApplication extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'change_of_name_applications';

    protected $primaryKey = 'id';

    public $timestamps = true;

    public const STATUS_PENDING      = 'pending';
    public const STATUS_PROCESSING   = 'processing';
    public const STATUS_APPROVED     = 'approved';
    public const STATUS_REJECTED     = 'rejected';
    public const STATUS_COMMISSIONED = 'commissioned';

    protected $fillable = [
        'file_no',
        'current_name',
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'new_full_name',
        'land_use',
        'location',
        'plot_no',
        'plan_no',
        'phone',
        'residential_address',
        'status',
        'remarks',
        'comment',
        'captured_by',
        'updated_by',
        'is_deleted',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
