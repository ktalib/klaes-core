<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GKN > Applications (user transcript, 2026-10-25): GKN, LPKN, LPPL and Misc KN files
 * in one typed table with tabs, plus "Commission Application Files", which opens
 * the existing GKN file number generator (gkn-generation.index).
 *
 * Read from file_indexings: existing files are indexed there and the generator
 * writes a file_indexings row for each number it commissions, so one table covers
 * both. Nothing here writes.
 */
class GknApplicationController extends Controller
{
    /** Tab => file-number prefixes (LIKE patterns) and chip colour. */
    public const TYPES = [
        'gkn'  => ['label' => 'GKN',     'like' => ['GKN%'],             'colour' => 'green'],
        'lpkn' => ['label' => 'LPKN',    'like' => ['LPKN%'],            'colour' => 'blue'],
        'lppl' => ['label' => 'LPPL',    'like' => ['LPPL%'],            'colour' => 'yellow'],
        'misc' => ['label' => 'Misc KN', 'like' => ['MISC KN%', 'MISC-KN%', 'MISCKN%'], 'colour' => 'purple'],
    ];

    public const COMMISSIONED_SOURCE = 'GKN FileNo Commissioning';

    public static function typeOf(?string $fileNumber): ?string
    {
        $n = strtoupper(ltrim((string) $fileNumber));
        foreach (self::TYPES as $k => $t) {
            foreach ($t['like'] as $like) {
                if (str_starts_with($n, rtrim($like, '%'))) return $k;
            }
        }

        return null;
    }

    public function index(Request $r)
    {
        $tab = array_key_exists($r->query('type'), self::TYPES) ? $r->query('type') : null;

        $base = function (?string $type) {
            $q = DB::connection('sqlsrv')->table('file_indexings')
                ->whereNotNull('file_number')
                ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0));
            $likes = $type ? self::TYPES[$type]['like'] : collect(self::TYPES)->pluck('like')->flatten()->all();

            return $q->where(function ($w) use ($likes) {
                foreach ($likes as $like) $w->orWhere('file_number', 'like', $like);
            });
        };

        $q = $base($tab)->select(['id', 'file_number', 'file_title', 'plot_number', 'district', 'lga', 'location',
            'land_use_type', 'source', 'created_at']);

        if ($term = trim((string) $r->query('q', ''))) {
            $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
            $q->where(fn ($w) => $w->where('file_number', 'like', $like)
                ->orWhere('file_title', 'like', $like)
                ->orWhere('district', 'like', $like)
                ->orWhere('lga', 'like', $like));
        }
        if ($src = $r->query('source')) {
            $src === 'commissioned'
                ? $q->where('source', self::COMMISSIONED_SOURCE)
                : $q->where(fn ($w) => $w->whereNull('source')->orWhere('source', '!=', self::COMMISSIONED_SOURCE));
        }

        $counts = ['all' => $base(null)->count()];
        foreach (array_keys(self::TYPES) as $k) $counts[$k] = $base($k)->count();

        return view('survey_module.gkn.applications', [
            'files'  => $q->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString(),
            'tab'    => $tab,
            'counts' => $counts,
        ]);
    }
}
