<?php

namespace App\Services\Survey;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * GKN → Public Survey → Layout Records (recording 286 / Workflow_V02 8.2; user
 * 2026-10-30): the records attached to each layout plan, grouped by layout, with how
 * many times a surveyor showed each plot to its allottee.
 *
 * Records per layout, merged by plot number:
 *   - the allocation lists imported from ALL COMPENSATION (projects with plan_no)
 *   - the Deeds OPs on the layout (instrument_capture / pra tp_no, lpkn_no, layout)
 *   - the showings recorded for those OPs (survey_op_showings, Plot Confirmation)
 *
 * Plan numbers are written many ways (TP/KN/215M, TP/KNUPDA/215M), so layouts are
 * matched on the layout number itself: 215M, 338G, DTF/16, DKD/001; a bare number
 * from the LPKN column is "LPKN 620".
 */
class LayoutRecords
{
    private const CONN = 'sqlsrv';
    private const AUTHORITIES = ['TP', 'BLM', 'KN', 'KNUPDA', 'KAS', 'KASSEPPA', 'MLPP', 'UC', 'KANO'];
    private const OP = 'Occupancy Permit (OP)';

    public function key(?string $raw): ?string
    {
        $s = strtoupper(trim(preg_replace('/\s+/', '', (string) $raw)));
        if ($s === '' || $s === '-' || $s === 'NIL') return null;
        if (preg_match('/^\d+[A-Z]?$/', $s)) return 'LPKN ' . $s;
        $parts = array_values(array_filter(explode('/', $s), 'strlen'));
        if (! $parts) return null;
        $last = end($parts);
        if (preg_match('/^\d+$/', $last) && count($parts) >= 2 && ! in_array($parts[count($parts) - 2], self::AUTHORITIES, true)) {
            return $parts[count($parts) - 2] . '/' . $last;
        }

        return $last;
    }

    /** Every layout with records: key => [key, plans, name, lines, ops, showings]. Cached 10 minutes. */
    public function layouts(): Collection
    {
        return Cache::remember('survey.layout-records.index', 600, function () {
            $db = DB::connection(self::CONN);
            $out = [];
            $add = function (?string $raw, string $field, int $n, ?string $name = null) use (&$out) {
                if (! ($k = $this->key($raw))) return;
                $out[$k] ??= ['key' => $k, 'plans' => [], 'name' => null, 'lines' => 0, 'ops' => 0, 'showings' => 0];
                $out[$k]['plans'][trim($raw)] = ($out[$k]['plans'][trim($raw)] ?? 0) + $n;
                $out[$k][$field] += $n;
                if ($name && ! $out[$k]['name']) $out[$k]['name'] = $name;
            };

            foreach ($db->table('survey_projects as p')->join('survey_comp_cases as c', 'c.survey_project_id', '=', 'p.id')
                         ->join('survey_plot_allocations as a', 'a.survey_comp_case_id', '=', 'c.id')
                         ->whereNotNull('p.plan_no')->whereNull('p.deleted_at')->whereNull('c.deleted_at')->whereNull('a.deleted_at')
                         ->groupBy('p.plan_no', 'p.name')->selectRaw('p.plan_no, p.name, COUNT(*) n')->get() as $r) {
                $add($r->plan_no, 'lines', (int) $r->n, preg_replace('/\s*\([^)]*\)\s*$/', '', $r->name));
            }
            foreach ($this->opLayoutCounts() as $r) $add($r->layout, 'ops', (int) $r->n);
            foreach ($this->showingLayoutCounts() as $r) $add($r->layout, 'showings', (int) $r->n);

            return collect($out)->map(function ($l) { arsort($l['plans']); return $l; })
                ->sortBy('key', SORT_NATURAL)->values();
        });
    }

    /** The records of one layout, one row per plot. */
    public function records(string $key): array
    {
        $layout = $this->layouts()->firstWhere('key', $key);
        if (! $layout) return [null, collect()];
        $raws = array_keys($layout['plans']);
        $db = DB::connection(self::CONN);
        $rows = [];
        // The row for a plot number (one per plot; a record with no plot gets its own row).
        $row = function (?string $plot) use (&$rows): string {
            $k = $plot !== null && trim($plot) !== '' ? strtoupper(preg_replace('/\s+/', '', $plot)) : '#' . count($rows);
            $rows[$k] ??= ['plot' => $plot !== null ? trim($plot) : null, 'names' => [], 'ops' => [], 'lines' => [], 'district' => null, 'lga' => null, 'location' => null];

            return $k;
        };

        // Allocation lists: a farmer line may hold several plots ("1518,1519").
        $lines = $db->table('survey_plot_allocations as a')->join('survey_comp_cases as c', 'c.id', '=', 'a.survey_comp_case_id')
            ->join('survey_projects as p', 'p.id', '=', 'c.survey_project_id')
            ->whereIn('p.plan_no', $raws)->whereNull('a.deleted_at')->whereNull('c.deleted_at')
            ->get(['a.id', 'a.farmer_name', 'a.f_no', 'a.acreage', 'a.plot_no', 'a.remarks', 'c.case_ref', 'c.prop_lga', 'c.survey_officer']);
        foreach ($lines as $l) {
            $plots = $l->plot_no ? preg_split('/\s*[,;&]\s*/', $l->plot_no) : [null];
            foreach ($plots as $p) {
                $k = $row($p);
                if ($l->farmer_name) $rows[$k]['names'][$l->farmer_name] = true;
                $rows[$k]['lines'][] = ['case' => $l->case_ref, 'f_no' => $l->f_no, 'area' => $l->acreage, 'status' => $l->remarks, 'surveyor' => $l->survey_officer];
                $rows[$k]['lga'] ??= $l->prop_lga ? str_replace(' | ', ' & ', $l->prop_lga) : null;
            }
        }

        // Deeds OPs on the layout.
        $opKeys = [];
        foreach ($this->opsOn($raws) as $o) {
            $k = $row($o->plot_no);
            $okey = $o->src . '-' . $o->id;
            $opKeys[$o->src][] = (int) $o->id;
            $rows[$k]['ops'][$okey] = ['key' => $okey, 'serial' => $o->serial, 'allottee' => $o->allottee, 'type' => $o->op_type, 'file' => $o->file_number];
            if ($o->allottee) $rows[$k]['names'][$o->allottee] = true;
            $rows[$k]['district'] ??= $o->district;
            $rows[$k]['lga'] ??= $o->lga;
            // Location is district + LGA (+ state), never the plot number.
            $rows[$k]['location'] ??= $o->location ? trim(preg_replace('/^\s*(plot\s*)?' . preg_quote(trim((string) $o->plot_no), '/') . '\s*,\s*/i', '', $o->location)) : null;
        }

        // Showings of those OPs.
        $showings = collect();
        foreach ($opKeys as $src => $ids) {
            foreach (array_chunk(array_unique($ids), 1000) as $chunk) {
                $showings = $showings->merge($db->table('survey_op_showings')->where('op_source', $src)->whereIn('op_source_id', $chunk)->whereNull('deleted_at')
                    ->orderBy('showing_no')->get(['op_source', 'op_source_id', 'showing_no', 'shown_on', 'shown_at', 'surveyor_name', 'reason', 'allottee_present']));
            }
        }
        $byOp = $showings->groupBy(fn ($s) => $s->op_source . '-' . $s->op_source_id);

        $out = collect($rows)->map(function ($r) use ($byOp) {
            $shows = collect(array_keys($r['ops']))->flatMap(fn ($k) => $byOp->get($k, collect()))->sortBy('shown_on')->values();
            $last = $shows->last();

            return $r + [
                'allottee'   => implode(' / ', array_keys($r['names'])) ?: null,
                'showings'   => $shows->map(fn ($s) => ['no' => (int) $s->showing_no, 'on' => substr((string) $s->shown_on, 0, 10), 'at' => $s->shown_at,
                                                         'surveyor' => $s->surveyor_name, 'reason' => $s->reason,
                                                         'present' => $s->allottee_present === null ? null : (bool) $s->allottee_present])->all(),
                'last_shown' => $last ? substr((string) $last->shown_on, 0, 10) : null,
                'last_by'    => $last->surveyor_name ?? null,
            ];
        // Plot order; records with no plot number recorded (many older OPs) come last.
        })->sort(fn ($a, $b) => ((($a['plot'] ?? '') === '') <=> (($b['plot'] ?? '') === '')) ?: strnatcasecmp((string) $a['plot'], (string) $b['plot']))->values();

        return [$layout, $out];
    }

    public static function forget(): void
    {
        Cache::forget('survey.layout-records.index');
    }

    private function opLayoutCounts(): Collection
    {
        $db = DB::connection(self::CONN);
        $ic = "COALESCE(NULLIF(LTRIM(RTRIM(ic.tp_no)), ''), NULLIF(LTRIM(RTRIM(ic.lpkn_no)), ''))";
        $p = "COALESCE(NULLIF(LTRIM(RTRIM(p.tp_no)), ''), NULLIF(LTRIM(RTRIM(p.lpkn_no)), ''), NULLIF(LTRIM(RTRIM(p.layout)), ''))";

        return $db->table('instrument_capture as ic')->where('ic.instrument_type', self::OP)
            ->where(fn ($q) => $q->whereNull('ic.is_deleted')->orWhere('ic.is_deleted', 0))
            ->whereRaw("{$ic} IS NOT NULL")->groupBy(DB::raw($ic))->selectRaw("{$ic} AS layout, COUNT(*) n")->get()
            ->merge($db->table('pra as p')->where('p.instrument_type', self::OP)->whereNull('p.instrument_capture_id')
                ->where(fn ($q) => $q->whereNull('p.is_deleted')->orWhere('p.is_deleted', 0))
                ->whereRaw("{$p} IS NOT NULL")->groupBy(DB::raw($p))->selectRaw("{$p} AS layout, COUNT(*) n")->get());
    }

    private function showingLayoutCounts(): Collection
    {
        $db = DB::connection(self::CONN);

        return $db->table('survey_op_showings as s')->join('instrument_capture as ic', 'ic.id', '=', 's.op_source_id')
            ->where('s.op_source', 'capture')->whereNull('s.deleted_at')
            ->groupBy(DB::raw("COALESCE(NULLIF(ic.tp_no, ''), ic.lpkn_no)"))->selectRaw("COALESCE(NULLIF(ic.tp_no, ''), ic.lpkn_no) AS layout, COUNT(*) n")->get()
            ->merge($db->table('survey_op_showings as s')->join('pra as p', 'p.id', '=', 's.op_source_id')
                ->where('s.op_source', 'pra')->whereNull('s.deleted_at')
                ->groupBy(DB::raw("COALESCE(NULLIF(p.tp_no, ''), NULLIF(p.lpkn_no, ''), p.layout)"))
                ->selectRaw("COALESCE(NULLIF(p.tp_no, ''), NULLIF(p.lpkn_no, ''), p.layout) AS layout, COUNT(*) n")->get());
    }

    private function opsOn(array $raws): Collection
    {
        $db = DB::connection(self::CONN);
        $out = collect();
        foreach (array_chunk($raws, 500) as $chunk) {
            $out = $out->merge($db->table('instrument_capture as ic')->where('ic.instrument_type', self::OP)
                ->where(fn ($q) => $q->whereNull('ic.is_deleted')->orWhere('ic.is_deleted', 0))
                ->where(fn ($q) => $q->whereIn(DB::raw('LTRIM(RTRIM(ic.tp_no))'), $chunk)
                    ->orWhere(fn ($w) => $w->where(fn ($x) => $x->whereNull('ic.tp_no')->orWhere('ic.tp_no', ''))->whereIn(DB::raw('LTRIM(RTRIM(ic.lpkn_no))'), $chunk)))
                ->get([DB::raw("'capture' AS src"), 'ic.id', DB::raw('CAST(ic.op_serial_number AS NVARCHAR(50)) AS serial'), 'ic.op_type',
                       DB::raw('ic.party_2_name AS allottee'), DB::raw('ic.plot_number AS plot_no'), 'ic.district', 'ic.lga',
                       DB::raw("COALESCE(NULLIF(ic.property_location, ''), ic.property_description) AS location"),
                       DB::raw("COALESCE(NULLIF(ic.mlsFNo, ''), ic.temp_fileno) AS file_number")]));
            $out = $out->merge($db->table('pra as p')->where('p.instrument_type', self::OP)->whereNull('p.instrument_capture_id')
                ->where(fn ($q) => $q->whereNull('p.is_deleted')->orWhere('p.is_deleted', 0))
                ->whereIn(DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(p.tp_no)), ''), NULLIF(LTRIM(RTRIM(p.lpkn_no)), ''), NULLIF(LTRIM(RTRIM(p.layout)), ''))"), $chunk)
                ->get([DB::raw("'pra' AS src"), 'p.id', DB::raw('CAST(p.op_serial_number AS NVARCHAR(50)) AS serial'), 'p.op_type',
                       DB::raw("NULLIF(LTRIM(RTRIM(p.Grantee)), '') AS allottee"), 'p.plot_no', DB::raw('p.districtName AS district'), DB::raw('CAST(NULL AS NVARCHAR(100)) AS lga'),
                       DB::raw("COALESCE(NULLIF(p.location, ''), p.property_description) AS location"),
                       DB::raw("COALESCE(NULLIF(p.mlsFNo, ''), p.temp_fileno) AS file_number")]));
        }

        return $out;
    }
}
