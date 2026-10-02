<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAuditLog extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'payroll_audit_logs';

    protected $fillable = [
        'period_id',
        'user_id',
        'actor_id',
        'action',
        'payload',
        'source',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
