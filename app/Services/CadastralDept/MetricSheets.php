<?php

namespace App\Services\CadastralDept;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The metric map sheets lookup (metric_sheets, 1:2000). READ ONLY.
 *
 * The Charting Card's Sheet No. is picked from this list when the sheet is
 * Metric; Imperial stays typed. The stored value is the sheet's label,
 * "NO 41/17 NW (P-10) Scale:-1:2000", the spelling the Survey screens use.
 */
class MetricSheets
{
    public static function installed(): bool
    {
        return Schema::connection('sqlsrv')->hasTable('metric_sheets');
    }

    /** @return array<int, array{index:string, no:string, label:string}> in index order */
    public function all(): array
    {
        if (!self::installed()) {
            return [];
        }

        return DB::connection('sqlsrv')->table('metric_sheets')->where('is_active', 1)->orderBy('id')
            ->get(['sheet_index', 'sheet_no', 'label'])
            ->map(fn ($r) => ['index' => $r->sheet_index, 'no' => $r->sheet_no, 'label' => $r->label])->all();
    }

    /**
     * The label for what was picked or typed: the full label, the index code
     * (P-10) or the bare sheet number (NO 41/17 NW), in any case. Null if it is
     * not a known sheet.
     */
    public function canonical(?string $value): ?string
    {
        $v = trim(preg_replace('/\s+/', ' ', (string) $value));
        if ($v === '' || !self::installed()) {
            return null;
        }

        $row = DB::connection('sqlsrv')->table('metric_sheets')->where('is_active', 1)
            ->where(fn ($w) => $w->where('label', $v)->orWhere('sheet_index', $v)->orWhere('sheet_no', $v))
            ->first(['label']);

        return $row->label ?? null;
    }
}
