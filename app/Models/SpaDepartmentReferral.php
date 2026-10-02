<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaDepartmentReferral extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_department_referrals';

    protected $fillable = [
        'spa_application_id', 'file_number', 'department',
        'referred_by', 'referral_date', 'response_date',
        'notes', 'response_notes', 'status', 'created_by',
    ];

    protected $casts = [
        'referral_date' => 'date',
        'response_date' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }

    public function referredBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'referred_by');
    }
}
