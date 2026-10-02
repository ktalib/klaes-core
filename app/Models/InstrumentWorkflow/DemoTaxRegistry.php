<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in for the BIR server's TIN register, read and written only by the demo
 * driver. Nothing here is a real tax record.
 *
 * ALAES also backs an ABSSIN server from this table (abssin_code, outstanding
 * liability). Kano has no ABSSIN, so those columns do not exist here.
 */
class DemoTaxRegistry extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'demo_tax_registry';

    protected $fillable = [
        'tin',
        'name',
        'phone',
        'nin_hash',
        'rc_number',
        'is_seeded',
    ];

    protected $casts = [
        'is_seeded' => 'boolean',
    ];
}

