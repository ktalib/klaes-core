<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Services\Survey\ComputationSheetImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Computation sheet.xlsx as it is written, staged (user, 2026-11-06): amber rows on Farm Assessment's Imported tab,
 * one per sheet, not lands or farmers. Read only. A sheet's lines load in place, never by a page load.
 */
class ComputationSheetController extends Controller
{
    use Concerns\ExportsRecords;

    public const PER_PAGE = 50;

    private function table()
    {
        return DB::connection('sqlsrv')->table(ComputationSheetImport::TABLE);
    }

    /** The search box. "[" opens a character class in T-SQL LIKE. */
    private function search($q, ?string $term)
    {
        $term = trim(preg_replace('/\s+/', ' ', (string) $term));
        if ($term === '') return $q;
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';

        return $q->where(function ($w) use ($like) {
            foreach (['farm_id', 'tag_no', 'first_name', 'middle_name', 'last_name', 'sheet_no', 'sheet_name', 'plots', 'remarks'] as $col) {
                $w->orWhere($col, 'like', $like);
            }
            $w->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", [$like])
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
        });
    }

    /** One text column added up over a sheet's lines. Cells that are not numbers count for nothing. */
    private static function sum(string $col): string
    {
        return "SUM(CASE WHEN kind = 'line' THEN TRY_CONVERT(decimal(20,6), REPLACE($col, ',', '')) END) AS $col";
    }

    /** One row per sheet. */
    public function sheets(?string $term)
    {
        return $this->search($this->table(), $term)
            ->selectRaw("sheet_name, SUM(CASE WHEN kind = 'line' THEN 1 ELSE 0 END) AS lines, SUM(CASE WHEN kind = 'line' AND plots IS NOT NULL THEN 1 ELSE 0 END) AS with_plots, "
                . implode(', ', array_map([self::class, 'sum'], ['area_m2', 'area_ha', 'creatable', 'govt_50', 'farm_50'])))
            ->groupBy('sheet_name')->orderBy('sheet_name')->get();
    }

    public function lineCount(): int
    {
        return $this->table()->where('kind', 'line')->count();
    }

    /** A sheet's rows, as table rows for the page to place under the sheet. */
    public function rows(Request $r)
    {
        $page = max(1, (int) $r->query('page', 1));
        $sheet = (string) $r->query('sheet', '');
        $q = $this->search($this->table()->where('sheet_name', $sheet), $r->query('q'));
        $total = (clone $q)->count();

        return view('survey_module.partials.sections._computation_sheet_rows', [
            'rows' => $q->orderBy('source_row')->forPage($page, self::PER_PAGE)->get(),
            'sheet' => $sheet,
            'group' => (int) $r->query('g', 0),
            'first' => ($page - 1) * self::PER_PAGE + 1,
            'nextPage' => $page * self::PER_PAGE < $total ? $page + 1 : null,
            'left' => max(0, $total - $page * self::PER_PAGE),
        ]);
    }

    public function export(Request $r)
    {
        return $this->exportRecords($r, fn () => $this->search($this->table(), $r->query('q'))->orderBy('sheet_name')->orderBy('source_row'), [
            'sheet' => 'Sheet', 'farm_id' => 'Farm ID', 'tag_no' => 'Tag No', 'first' => 'First Name', 'middle' => 'Middle Name', 'last' => 'Last Name', 'sheet_no' => 'Sheet No',
            'area_m2' => 'Area (M²)', 'area_ha' => 'Area (Ha)', 'creatable' => 'Creatable', 'govt' => 'Govt 50%', 'farm' => 'Farm 50%', 'plots' => 'Plots', 'remarks' => 'Remarks',
        ], fn ($x) => [
            'sheet' => $x->sheet_name, 'farm_id' => $x->farm_id, 'tag_no' => $x->tag_no, 'first' => $x->first_name, 'middle' => $x->middle_name, 'last' => $x->last_name, 'sheet_no' => $x->sheet_no,
            'area_m2' => $x->area_m2, 'area_ha' => $x->area_ha, 'creatable' => $x->creatable, 'govt' => $x->govt_50, 'farm' => $x->farm_50, 'plots' => $x->plots, 'remarks' => $x->remarks,
        ], null);
    }
}
