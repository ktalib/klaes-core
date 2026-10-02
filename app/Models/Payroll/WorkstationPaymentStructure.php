<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkstationPaymentStructure extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'workstation_payment_structures';

    protected $fillable = [
        'code',
        'role_name',
        'work_days',
        'base_salary',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'work_days' => 'integer',
        'base_salary' => 'float',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function aliases(): HasMany
    {
        return $this->hasMany(WorkstationPaymentStructureAlias::class, 'structure_id');
    }
}
