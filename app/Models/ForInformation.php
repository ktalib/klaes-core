<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForInformation extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'for_information';

    protected $fillable = [
        'serial_no',
        'ref_no',
        'file_number',
        'title',
        'commencement_day',
        'signer_name',
        'signer_rank',
        'status',
        'print_count',
        'user_id',
    ];

    protected $casts = [
        'print_count' => 'integer',
    ];
}
