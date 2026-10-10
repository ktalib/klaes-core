<?php

namespace App\Models\CadastralDept;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One surveyor's spell on a field jacket. The open row (ended_at null) is the current one. */
class CadastralFieldJacketAssignment extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_field_jacket_assignments';

    protected $guarded = ['id'];

    protected $casts = [
        'assigned_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
