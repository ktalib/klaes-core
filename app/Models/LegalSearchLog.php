<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalSearchLog extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $fillable = [
        'user_id',
        'search_parameter',
        'search_value',
        'result_status',
        'results_count',
        'lga',
        'receipt_no',
        'printed',
        'direct_link',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
