<?php

namespace App\Services\Sltr;

use Illuminate\Support\Facades\DB;

/**
 * SLTR special case (user, 2026-10-30): a file with a Sale Agreement and a CofO gets a
 * system-generated Transfer of Title between them, when it has none. A Deed of
 * Assignment does not count (user, 2026-10-30):
 *
 *   1. Sales Agreement   Party 1: seller            Party 2: buyer
 *   2. Transfer of Title Party 1: buyer (SA Party 2) Party 2: name on the CofO   <- generated
 *   3. CofO              Party 1: Kano State Govt   Party 2: name on the CofO
 *
 * Generated even when the buyer and the CofO holder are the same person (user's call).
 * The latest Sales Agreement is used when there are several. The ToT carries no date:
 * the real transaction date is not known (user, 2026-10-30), so none is invented. It is
 * saved where a captured plain Transfer of Title goes (file_history_staging), marked by
 * `source` and a "System generated" comment. Runs after Add Property Transaction Details
 * saves an SLTR file, and from `sltr:generate-tot` for files already on record.
 */
class SltrTotGenerator
{
    public const SOURCE = 'SLTR ToT (system generated)';
    public const TYPE = 'Transfer of Title';
    public const COMMENT = 'System generated (SLTR): Sale Agreement buyer to the name on the CofO.';

    private const FILE_COLUMNS = ['fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'];
    private const SALE_TYPES = ['SALE AGREEMENT', 'SALES AGREEMENT', 'AGREEMENT OF SALE'];
    private const COFO_TYPES = ['CERTIFICATE OF OCCUPANCY', 'SLTR CERTIFICATE OF OCCUPANCY', 'ST CERTIFICATE OF OCCUPANCY'];
    /** Copied from the Sales Agreement: the same property. */
    private const PROPERTY_COLUMNS = ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno', 'prop_id', 'property_description',
        'location', 'streetName', 'house_no', 'districtName', 'plot_no', 'lgsaOrCity', 'land_use', 'plot_size', 'tp_no', 'test_control'];

    public static function appliesTo(?string $fileNumber): bool
    {
        return (bool) preg_match('/^\s*SLTR-/i', (string) $fileNumber);
    }

    /**
     * What would happen for this file: ['action' => 'generate'|'skip', 'reason' => ..., 'row' => ?array].
     */
    public function plan(string $fileNumber): array
    {
        $fileNumber = trim($fileNumber);
        if (!self::appliesTo($fileNumber)) {
            return $this->skip('not an SLTR file');
        }

        $sale = $this->latestSale($fileNumber);
        if (!$sale) {
            return $this->skip('no Sale Agreement');
        }

        $cofo = $this->latestCofo($fileNumber);
        if (!$cofo) {
            return $this->skip('no CofO');
        }

        if ($this->hasTransfer($fileNumber)) {
            return $this->skip('Transfer of Title already on file');
        }

        $buyer = $this->name($sale->party_2 ?? null, $sale->Grantee ?? null, $sale->Purchaser ?? null, $sale->Assignee ?? null);
        $holder = $this->name($cofo->Grantee ?? null, $cofo->party_2 ?? null);
        if ($buyer === null) {
            return $this->skip('Sale Agreement has no Party 2 (buyer)');
        }
        if ($holder === null) {
            return $this->skip('CofO has no name (Party 2)');
        }

        $row = [];
        foreach (self::PROPERTY_COLUMNS as $column) {
            $row[$column] = $sale->$column ?? null;
        }
        $row['fileno'] = $row['fileno'] ?: $fileNumber;
        $row['mlsFNo'] = $row['mlsFNo'] ?: $fileNumber;

        return [
            'action' => 'generate',
            'reason' => null,
            'row'    => $row + [
                'transaction_type' => self::TYPE,
                'instrument_type'  => self::TYPE,
                // Unknown: left blank rather than borrowed from the CofO or the agreement.
                'transaction_date' => null,
                'reg_date'         => null,
                'Grantor'          => $buyer,
                'Grantee'          => $holder,
                'Transferor'       => $buyer,
                'Transferee'       => $holder,
                'party_1'          => $buyer,
                'party_2'          => $holder,
                'title_type'       => 'Other',
                'status'           => 'Normal',
                'is_deleted'       => 0,
                'source'           => self::SOURCE,
                'remarks'          => self::COMMENT,
                // Shown by Legal Search and the File History Summary, which mark the row from it.
                'comments'         => self::COMMENT,
                'captured_by'      => 'System',
            ],
            'sale'    => ($sale->_table ?? 'file_history_staging') . ':' . $sale->id,
            'cofo'    => ($cofo->_table ?? 'CofO_staging') . ':' . $cofo->id,
        ];
    }

    /**
     * Insert the Transfer of Title when the plan says so. Returns the new id, or null.
     * The check repeats inside the transaction so two saves at once cannot add two.
     */
    public function generate(string $fileNumber, $userId = null): ?int
    {
        return DB::connection('sqlsrv')->transaction(function () use ($fileNumber, $userId) {
            $plan = $this->plan($fileNumber);
            if ($plan['action'] !== 'generate') {
                return null;
            }
            $now = now();

            return (int) DB::connection('sqlsrv')->table('file_history_staging')->insertGetId($plan['row'] + [
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    private function skip(string $reason): array
    {
        return ['action' => 'skip', 'reason' => $reason, 'row' => null];
    }

    /** Live rows of a table for the file, in whichever file-number column holds it. */
    private function onFile(string $table, string $fileNumber)
    {
        $db = DB::connection('sqlsrv');

        return $db->table($table)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->whereNull('deleted_at')
            ->where(function ($q) use ($fileNumber) {
                foreach (self::FILE_COLUMNS as $column) {
                    $q->orWhere($column, $fileNumber);
                }
            });
    }

    /**
     * The latest Sale Agreement, in File History or PRA. Exact instrument names only:
     * "Affidavit for loss of sale agreement" and the like are not the agreement.
     */
    private function latestSale(string $fileNumber): ?object
    {
        $rows = [];
        foreach (['file_history_staging', 'pra'] as $table) {
            $row = $this->onFile($table, $fileNumber)
                ->whereRaw('UPPER(LTRIM(RTRIM(transaction_type))) IN (' . implode(',', array_fill(0, count(self::SALE_TYPES), '?')) . ')', self::SALE_TYPES)
                ->orderByDesc('transaction_date')->orderByDesc('id')
                ->first();
            if ($row) {
                $row->_table = $table;
                $rows[] = $row;
            }
        }
        usort($rows, fn ($a, $b) => strcmp((string) ($b->transaction_date ?? ''), (string) ($a->transaction_date ?? '')));

        return $rows[0] ?? null;
    }

    /** The latest CofO, from CofO_staging or a CofO row kept in file_history_staging. */
    private function latestCofo(string $fileNumber): ?object
    {
        $rows = [];
        foreach (['CofO_staging', 'file_history_staging'] as $table) {
            $row = $this->onFile($table, $fileNumber)
                // The certificate itself, not e.g. an affidavit for its loss.
                ->whereRaw('UPPER(LTRIM(RTRIM(transaction_type))) IN (' . implode(',', array_fill(0, count(self::COFO_TYPES), '?')) . ')', self::COFO_TYPES)
                ->orderByDesc('transaction_date')->orderByDesc('id')
                ->first();
            if ($row) {
                $row->_table = $table;
                $rows[] = $row;
            }
        }
        usort($rows, fn ($a, $b) => strcmp((string) ($b->transaction_date ?? ''), (string) ($a->transaction_date ?? '')));

        return $rows[0] ?? null;
    }

    /** "If none, the system generates": any Transfer of Title already on the file counts. */
    private function hasTransfer(string $fileNumber): bool
    {
        foreach (['file_history_staging', 'pra', 'CofO_staging'] as $table) {
            if ($this->onFile($table, $fileNumber)->where('transaction_type', 'like', '%transfer of title%')->exists()) {
                return true;
            }
        }

        return false;
    }

    private function name(...$values): ?string
    {
        foreach ($values as $value) {
            $value = trim(preg_replace('/\s+/', ' ', (string) $value));
            if ($value !== '' && $value !== '-') {
                return $value;
            }
        }

        return null;
    }
}
