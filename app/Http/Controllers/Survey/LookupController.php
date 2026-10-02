<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Select2 sources for the address builder.
 *
 * Districts (1,818) and streets (826) are flat, searchable lists — districts are
 * not mapped to LGAs in this database, so there is no cascade. Results are paged
 * for Select2's infinite scroll and searched server-side.
 */
class LookupController extends Controller
{
    private const PER_PAGE = 30;

    private function conn()
    {
        return DB::connection('sqlsrv');
    }

    /**
     * Drop lookup rows that are literally named "Other".
     *
     * districts holds one such row and street_names holds "other" — import
     * artifacts that carry no information as a district or street name. They
     * also collide with the "Other (specify)" sentinel this controller appends:
     * both store the value "Other", so once saved there is no way to tell which
     * the user meant, and the dropdown showed "Other" twice.
     *
     * @param  \Illuminate\Database\Query\Builder $q
     */
    private static function excludeLiteralOther($q, string $textCol): void
    {
        $q->whereRaw("LOWER(LTRIM(RTRIM([$textCol]))) <> ?", ['other']);
    }

    /** Shape a query into Select2's {results:[{id,text}], pagination:{more}}. */
    private function paged(Request $r, string $table, string $idCol, string $textCol, ?callable $tap = null): JsonResponse
    {
        $term = trim((string) $r->query('q', ''));
        $page = max(1, (int) $r->query('page', 1));

        $q = $this->conn()->table($table)->select([$idCol . ' as id', $textCol . ' as text']);
        if ($tap) $tap($q);
        self::excludeLiteralOther($q, $textCol);
        if ($term !== '') $q->where($textCol, 'like', '%' . $term . '%');

        $rows = $q->orderBy($textCol)
            ->forPage($page, self::PER_PAGE + 1)
            ->get();

        $more = $rows->count() > self::PER_PAGE;
        if ($more) $rows = $rows->slice(0, self::PER_PAGE)->values();

        // "Other" is offered on every first page, whatever was typed. It used to
        // be hidden unless the term matched the word "Other", which removed it
        // precisely when a search found nothing — the one case where the user
        // most needs to enter a value that is not in the list.
        $results = $rows->map(fn ($x) => ['id' => (string) $x->text, 'text' => (string) $x->text])->all();
        if ($page === 1) {
            $results[] = ['id' => 'Other', 'text' => 'Other (specify)'];
        }

        return response()->json([
            'results'    => $results,
            'pagination' => ['more' => $more],
        ]);
    }

    public function districts(Request $r): JsonResponse
    {
        return $this->paged($r, 'districts', 'id', 'name', fn ($q) => $q->where('is_active', 1));
    }

    public function streets(Request $r): JsonResponse
    {
        return $this->paged($r, 'street_names', 'id', 'name');
    }

    public function lgas(Request $r): JsonResponse
    {
        return $this->paged($r, 'lgas', 'id', 'name', fn ($q) => $q->where('is_active', 1));
    }

    public function states(Request $r): JsonResponse
    {
        return $this->paged($r, 'States', 'StateID', 'StateName');
    }

    /**
     * Whole lists, cached, for rendering <option> tags server-side so a saved
     * value shows without an AJAX round trip.
     */
    public static function options(string $kind): array
    {
        return Cache::remember("survey.lookup.$kind", 3600, function () use ($kind) {
            $c = DB::connection('sqlsrv');

            // Same exclusion as the AJAX endpoints, so the counts shown in the
            // form's helper text match what the dropdown will actually offer.
            $base = function (string $table, string $col) use ($c) {
                $q = $c->table($table)->orderBy($col);
                self::excludeLiteralOther($q, $col);
                return $q;
            };

            return match ($kind) {
                'districts' => $base('districts', 'name')->where('is_active', 1)->pluck('name')->all(),
                'streets'   => $base('street_names', 'name')->pluck('name')->all(),
                'lgas'      => $base('lgas', 'name')->where('is_active', 1)->pluck('name')->all(),
                'states'    => $base('States', 'StateName')->pluck('StateName')->all(),
                default     => [],
            };
        });
    }
}
