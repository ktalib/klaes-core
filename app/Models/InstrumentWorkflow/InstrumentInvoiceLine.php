<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;

class InstrumentInvoiceLine extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'instrument_invoice_lines';

    public const CODE_OUTSTANDING_TAX = 'outstanding_tax';
    public const CODE_NEW_TAX_BILL = 'new_tax_bill';

    protected $fillable = [
        'invoice_id',
        'code',
        'label',
        'reference',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];
}

