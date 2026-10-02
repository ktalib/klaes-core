<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileIndexingBill extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'file_indexing_bills';
    protected $guarded = [];

    public const TYPE_BILL_BALANCE = 'bill-balance';
    public const TYPE_GRANT_RENT   = 'grant-rent';

    protected $casts = [
        'amount'       => 'float',
        'from_year'    => 'integer',
        'to_year'      => 'integer',
        'billing_id'   => 'integer',
        'receipt_date' => 'date',
    ];

    public function billing()
    {
        return $this->belongsTo(Billing::class, 'billing_id', 'ID');
    }

    /**
     * Total Bill Balance + Grant Rent amounts for a file number (a file may have
     * several of each — the amounts are summed). Returns null per type when none
     * exist. Shared by the web Quick Search and mobile File Search result cards.
     *
     * @return array{bill_balance: float|null, grant_rent: float|null}
     */
    public static function amountsForFile(?string $fileNumber): array
    {
        $out = ['bill_balance' => null, 'grant_rent' => null];

        if (!$fileNumber) {
            return $out;
        }

        $rows = self::query()
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper(trim($fileNumber))])
            ->get(['bill_type', 'amount']);

        if ($rows->isEmpty()) {
            return $out;
        }

        $bills = $rows->where('bill_type', self::TYPE_BILL_BALANCE);
        $rents = $rows->where('bill_type', self::TYPE_GRANT_RENT);

        $out['bill_balance'] = $bills->count() ? (float) $bills->sum('amount') : null;
        $out['grant_rent']   = $rents->count() ? (float) $rents->sum('amount') : null;

        return $out;
    }
}
