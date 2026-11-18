<?php

namespace App\Http\Controllers;

use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * File Status > ST Files (user, 2026-11-18: "it should have its own screen, and also update the one under cadastral"):
 * the ST file numbers (st_file_numbers, the table the ST File Numbers page reads), view only, opened from Survey and
 * Cadastral with ?url=survey|cadastral like the other File Status lists. No commissioning, no delete.
 *
 * Three tabs, Primary / SuA / PuA (user, 2026-11-18). st_file_numbers mostly has no district or LGA, so the location is
 * read from the main application tables (user: "check their main application table; for PuA the District, LGA,
 * Location will be the same as their mother"):
 *   Primary  its own row, else its mother application (mother_applications by ST no.), else its land file's indexing;
 *   PuA      the mother's District / LGA / Location; the plot is its own, else the mother's;
 *   SuA      its own row, else its unit application (subapplications by file no.), else the mother's.
 */
class StFileStatusController extends Controller
{
    public const TABS = ['PRIMARY' => 'Primary', 'SUA' => 'SuA', 'PUA' => 'PuA'];
    public const TYPES = ['PRIMARY' => 'Primary (mother)', 'PUA' => 'PuA (unit)', 'SUA' => 'SuA (unit)'];

    public function index(Request $r)
    {
        $ctx = in_array($r->query('url'), ['survey', 'cadastral'], true) ? $r->query('url') : 'survey';
        // ?fs= is the file a File Status search result was opened for.
        $term = trim((string) ($r->query('q') ?? $r->query('fs', '')));
        $tab = array_key_exists(strtoupper((string) $r->query('tab')), self::TABS) ? strtoupper((string) $r->query('tab')) : 'PRIMARY';
        $status = in_array(strtoupper((string) $r->query('status')), ['ACTIVE', 'USED'], true) ? strtoupper((string) $r->query('status')) : null;

        $db = DB::connection('sqlsrv');
        $filtered = function () use ($db, $term, $status) {
            $q = $db->table('st_file_numbers');
            if ($term !== '') {
                $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
                // A file number finds its whole ST family, as the File Status search does.
                $mothers = $db->table('st_file_numbers')
                    ->where(fn ($w) => $w->where('fileno', $term)->orWhere('np_fileno', $term)->orWhere('mls_fileno', $term))
                    ->whereNotNull('np_fileno')->distinct()->limit(50)->pluck('np_fileno')->all();
                $q->where(function ($w) use ($like, $mothers) {
                    if ($mothers) $w->whereIn('np_fileno', $mothers);
                    $w->orWhere('fileno', 'like', $like)->orWhere('np_fileno', 'like', $like)->orWhere('mls_fileno', 'like', $like)
                      ->orWhere('corporate_name', 'like', $like)->orWhere('multiple_owners_names', 'like', $like)
                      ->orWhereRaw("LTRIM(RTRIM(CONCAT(ISNULL(first_name, ''), ' ', ISNULL(middle_name, ''), ' ', ISNULL(surname, '')))) LIKE ?", [$like])
                      ->orWhereRaw("LTRIM(RTRIM(CONCAT(ISNULL(first_name, ''), ' ', ISNULL(surname, '')))) LIKE ?", [$like])
                      ->orWhere('property_plot_no', 'like', $like)->orWhere('property_district', 'like', $like)->orWhere('property_lga', 'like', $like)
                      // The mother application's location, which most rows take theirs from.
                      ->orWhereIn('np_fileno', fn ($m) => $m->select('np_fileno')->from('mother_applications')
                          ->where(fn ($x) => $x->where('property_district', 'like', $like)->orWhere('property_lga', 'like', $like)->orWhere('property_plot_no', 'like', $like)));
                });
            }

            return $q->when($status, fn ($x) => $x->whereRaw('UPPER(status) = ?', [$status]));
        };

        // Each tab's count, with the search applied.
        $counts = $filtered()->selectRaw('UPPER(file_no_type) t, COUNT(*) n')->groupByRaw('UPPER(file_no_type)')->pluck('n', 't')->all();

        $stats = $db->table('st_file_numbers')->selectRaw("COUNT(*) total,
            SUM(CASE WHEN UPPER(file_no_type) = 'PRIMARY' THEN 1 ELSE 0 END) primaries,
            SUM(CASE WHEN UPPER(file_no_type) IN ('PUA', 'SUA') THEN 1 ELSE 0 END) units,
            SUM(CASE WHEN UPPER(status) = 'USED' THEN 1 ELSE 0 END) used")->first();

        // Newest ST mother first; a unit tab keeps each mother's units together, in unit order.
        $rows = $filtered()->whereRaw('UPPER(file_no_type) = ?', [$tab])
            ->orderByRaw('(SELECT MAX(m.id) FROM st_file_numbers m WHERE m.np_fileno = st_file_numbers.np_fileno) DESC')
            ->orderBy('np_fileno')->orderBy('unit_sequence')->orderBy('id')
            ->select(['id', 'np_fileno', 'fileno', 'mls_fileno', 'file_no_type', 'status', 'applicant_title', 'first_name', 'middle_name', 'surname',
                'corporate_name', 'multiple_owners_names', 'land_use', 'property_plot_no', 'property_district', 'property_lga', 'date_commissioned'])
            ->paginate(25)->withQueryString();

        $places = $this->places($rows->getCollection());

        $rows->getCollection()->transform(function ($r) use ($places) {
            $type = strtoupper((string) $r->file_no_type);
            $primary = $type === 'PRIMARY';
            $owners = trim((string) $r->multiple_owners_names);
            if (str_starts_with($owners, '[')) $owners = implode(', ', array_filter((array) json_decode($owners, true)));
            $r->st_no = $primary ? $r->np_fileno : $r->fileno;
            $r->mother = $primary ? ($r->mls_fileno ?: $r->fileno) : $r->np_fileno;
            $r->type_label = self::TYPES[$type] ?? $r->file_no_type;
            $r->is_primary = $primary;
            $r->applicant = trim((string) $r->corporate_name) !== '' ? $r->corporate_name
                : (trim(implode(' ', array_filter([$r->applicant_title, $r->first_name, $r->middle_name, $r->surname]))) ?: $owners);
            $r->commissioned = $r->date_commissioned ? Carbon::parse($r->date_commissioned)->format('d M Y') : null;

            $own = ['plot' => $this->v($r->property_plot_no), 'district' => $this->v($r->property_district), 'lga' => $this->v($r->property_lga)];
            $mother = $places['mother'][mb_strtoupper((string) $r->np_fileno)] ?? [];
            $place = match ($type) {
                'PUA' => [
                    'plot' => $own['plot'] ?: ($mother['plot'] ?? null),
                    'district' => ($mother['district'] ?? null) ?: $own['district'],
                    'lga' => ($mother['lga'] ?? null) ?: $own['lga'],
                ],
                'SUA' => $this->first([$own, $places['sub'][mb_strtoupper((string) $r->fileno)] ?? [], $mother]),
                default => $this->first([$own, $mother]),
            };
            // Shown in one case (user, 2026-11-18: "some are full caps some are low"); display only, the records are not changed.
            // File numbers and plot numbers keep their format.
            $r->applicant = self::title($r->applicant);
            $r->land_use = self::title($r->land_use);
            $r->plot = $place['plot'] !== null && ! preg_match('/\d/', $place['plot']) ? self::title($place['plot']) : $place['plot'];
            $r->district = self::title($place['district']);
            $r->lga = self::title($place['lga']);
            $r->location = ($r->district || $r->lga)
                ? AddressBuilder::propertyLocation(['district' => $r->district, 'lga' => $r->lga, 'state' => 'Kano']) : null;

            return $r;
        });

        return view('file_status.st_files', [
            'rows' => $rows, 'term' => $term, 'tab' => $tab, 'status' => $status, 'ctx' => $ctx, 'stats' => $stats, 'counts' => $counts,
            'PageTitle' => 'ST Files',
            'PageDescription' => 'Sectional Title file numbers: Primary (mother), SuA and PuA units. View only.',
        ]);
    }

    /**
     * The places this page's rows read: per ST mother no., its mother application's (else its land file's indexing),
     * and per SuA file no., its unit application's. Plot, district, LGA each.
     */
    private function places($rows): array
    {
        $db = DB::connection('sqlsrv');
        $nps = $rows->pluck('np_fileno')->filter()->unique()->values()->all();
        $out = ['mother' => [], 'sub' => []];
        if ($nps) {
            $db->table('mother_applications')->whereIn('np_fileno', $nps)->orderBy('id')
                ->get(['np_fileno', 'property_plot_no', 'property_district', 'property_lga', 'address_plot_no', 'address_district', 'address_lga'])
                ->each(function ($m) use (&$out) {
                    $p = ['plot' => $this->v($m->property_plot_no), 'district' => $this->v($m->property_district), 'lga' => $this->v($m->property_lga)];
                    if (! $p['district'] && ! $p['lga']) return;   // the newest with a place wins
                    $out['mother'][mb_strtoupper($m->np_fileno)] = $p;
                });
            // No mother application place: the land file's indexing (the primary's CON / land file).
            $missing = array_values(array_filter($nps, fn ($n) => ! isset($out['mother'][mb_strtoupper($n)])));
            if ($missing) {
                $land = $db->table('st_file_numbers')->whereIn('np_fileno', $missing)->whereRaw("UPPER(file_no_type) = 'PRIMARY'")
                    ->get(['np_fileno', 'mls_fileno', 'fileno'])
                    ->mapWithKeys(fn ($p) => [mb_strtoupper($p->np_fileno) => $p->mls_fileno ?: $p->fileno])->filter()->all();
                if ($land) {
                    $idx = $db->table('file_indexings')->whereIn('file_number', array_values($land))
                        ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))->orderBy('id')
                        ->get(['file_number', 'plot_number', 'district', 'lga'])
                        ->filter(fn ($i) => $this->v($i->district) || $this->v($i->lga))
                        ->keyBy(fn ($i) => mb_strtoupper($i->file_number));
                    foreach ($land as $np => $file) {
                        if ($i = $idx->get(mb_strtoupper($file))) {
                            $out['mother'][$np] = ['plot' => $this->v($i->plot_number), 'district' => $this->v($i->district), 'lga' => $this->v($i->lga)];
                        }
                    }
                    // Still none: the land file's Deeds records (pra), the row with the most of the place.
                    $left = array_filter($land, fn ($f, $np) => ! isset($out['mother'][$np]), ARRAY_FILTER_USE_BOTH);
                    if ($left) {
                        $pra = $db->table('pra')->whereIn('mlsFNo', array_values($left))
                            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
                            ->get(['mlsFNo', 'plot_no', 'districtName', 'lgsaOrCity'])
                            ->groupBy(fn ($p) => mb_strtoupper($p->mlsFNo))
                            ->map(fn ($g) => $g->sortByDesc(fn ($p) => ($this->v($p->districtName) ? 2 : 0) + ($this->v($p->lgsaOrCity) ? 1 : 0))->first());
                        foreach ($left as $np => $file) {
                            $p = $pra->get(mb_strtoupper($file));
                            if ($p && ($this->v($p->districtName) || $this->v($p->lgsaOrCity))) {
                                $out['mother'][$np] = ['plot' => $this->v($p->plot_no), 'district' => $this->v($p->districtName), 'lga' => $this->v($p->lgsaOrCity)];
                            }
                        }
                    }
                }
            }
        }

        $suas = $rows->filter(fn ($r) => strtoupper((string) $r->file_no_type) === 'SUA')->pluck('fileno')->filter()->unique()->values()->all();
        if ($suas) {
            $db->table('subapplications')->whereIn('fileno', $suas)->orderBy('id')
                ->get(['fileno', 'unit_district', 'unit_lga', 'address_district', 'address_lga'])
                ->each(function ($s) use (&$out) {
                    $p = ['plot' => null, 'district' => $this->v($s->unit_district) ?: $this->v($s->address_district), 'lga' => $this->v($s->unit_lga) ?: $this->v($s->address_lga)];
                    if ($p['district'] || $p['lga']) $out['sub'][mb_strtoupper($s->fileno)] = $p;
                });
        }

        return $out;
    }

    /** The first source with a district or LGA; the plot from the first source that has one. */
    private function first(array $sources): array
    {
        $place = ['plot' => null, 'district' => null, 'lga' => null];
        foreach ($sources as $s) {
            if (! $place['plot'] && ! empty($s['plot'])) $place['plot'] = $s['plot'];
        }
        foreach ($sources as $s) {
            if (! empty($s['district']) || ! empty($s['lga'])) {
                $place['district'] = $s['district'] ?? null;
                $place['lga'] = $s['lga'] ?? null;
                break;
            }
        }

        return $place;
    }

    /** Each word capitalised; of / and / the / for lower case after the first word. */
    private static function title(?string $s): ?string
    {
        $s = trim(preg_replace('/\s+/u', ' ', (string) $s));
        if ($s === '') return null;
        $i = 0;

        return preg_replace_callback("/[\p{L}']+/u", function ($m) use (&$i) {
            $w = mb_strtolower($m[0]);
            if (in_array($w, ['gra', 'ltd', 'plc', 'nig', 'knupda', 'nnpc'], true)) { $i++; return mb_strtoupper($w); }   // abbreviations stay capitals

            return ($i++ > 0 && in_array($w, ['of', 'and', 'the', 'for'], true)) ? $w : mb_strtoupper(mb_substr($w, 0, 1)) . mb_substr($w, 1);
        }, $s);
    }

    private function v($s): ?string
    {
        $s = trim((string) $s);

        return $s === '' || in_array(strtoupper($s), ['N/A', 'NULL', '-'], true) ? null : $s;
    }
}
