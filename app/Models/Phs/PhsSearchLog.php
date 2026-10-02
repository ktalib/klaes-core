<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;

class PhsSearchLog extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'phs_search_logs';

    protected $fillable = [
        'phs_institution_id',
        'phs_member_id',
        'query',
        'file_number',
        'result_count',
        'reference_no',
        'tokens_used',
    ];

    public function institution()
    {
        return $this->belongsTo(PhsInstitution::class, 'phs_institution_id');
    }

    public function member()
    {
        return $this->belongsTo(PhsMember::class, 'phs_member_id');
    }
}
