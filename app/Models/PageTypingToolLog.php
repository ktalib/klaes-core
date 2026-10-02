<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageTypingToolLog extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'page_typing_tool_logs';

    protected $fillable = [
        'file_indexing_id',
        'scanning_id',
        'file_path',
        'action',
        'details',
        'rotation',
        'scale',
        'translate_x',
        'translate_y',
        'performed_by',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'details' => 'array',
        'rotation' => 'float',
        'scale' => 'float',
        'translate_x' => 'float',
        'translate_y' => 'float',
    ];
}
