<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkstationPaymentStructureAlias extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'workstation_payment_structure_aliases';

    protected $fillable = [
        'structure_id',
        'alias',
        'normalized_alias',
    ];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(WorkstationPaymentStructure::class, 'structure_id');
    }
}
