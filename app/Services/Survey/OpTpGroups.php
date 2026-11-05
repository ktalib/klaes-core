<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyTpNumber;
use App\Support\TpNumberKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OPs grouped by layout (CR 03112026 items 2-3). The TP number on an OP is free text written many ways,
 * so TpNumberKey reduces each spelling to one layout and the groups and aliases record the result. Deeds
 * rows are never written. A spelling first seen after the last sync has no group until the next one.
 */
class OpTpGroups
{
    private const OP = "instrument_type = 'Occupancy Permit (OP)'";

    private function db()
    {
        return DB::connection('sqlsrv');
    }

    public static function installed(): bool
    {
        return Schema::connection('sqlsrv')->hasTable('op_tp_groups');
    }

    /** Groups for a dropdown, in serial order: id, label, op_count. */
    public function options(): Collection
    {
        if (! self::installed()) return collect();

        return $this->db()->table('op_tp_groups')->where('needs_review', 0)
            ->orderByRaw('CASE WHEN serial_sort IS NULL THEN 1 ELSE 0 END')->orderBy('serial_sort')->orderBy('group_key')
            ->get(['id', 'label', 'op_count', 'survey_tp_number_id']);
    }

    /** The group a TP number written on an OP belongs to. */
    public function forRaw(?string $tp): ?object
    {
        $raw = self::raw($tp);
        if ($raw === null || ! self::installed()) return null;

        return $this->db()->table('op_tp_aliases as a')->join('op_tp_groups as g', 'g.id', '=', 'a.op_tp_group_id')
            ->where('a.raw_tp', $raw)->first(['g.id', 'g.label', 'g.survey_tp_number_id']);
    }

    /**
     * The live check behind the TP No box on Capture OP (user, 2026-11-05): whether the number can be
     * saved, and which layout in Public Layout Plans it names. Not being in the register is only a note.
     *
     * @return array{ok:bool, message:?string, layout:?string, in_register:bool, ops:int}
     */
    public function check(?string $tp): array
    {
        if ($problem = TpNumberKey::problem($tp)) {
            return ['ok' => false, 'message' => $problem, 'layout' => null, 'in_register' => false, 'ops' => 0];
        }

        $key = TpNumberKey::key($tp);
        $register = \Illuminate\Support\Facades\Cache::remember('survey:tp-register-keys', 600, function () {
            $out = [];
            foreach (SurveyTpNumber::listed()->whereNotNull('tp_no')->get(['tp_no']) as $t) {
                if ($k = TpNumberKey::key($t->tp_no)) $out[$k][] = trim($t->tp_no);
            }

            return $out;
        });
        $ops = self::installed() ? (int) $this->db()->table('op_tp_groups')->where('group_key', $key)->value('op_count') : 0;
        $layouts = array_values(array_unique($register[$key] ?? []));

        return [
            'ok' => true,
            'message' => $layouts
                ? (count($layouts) > 1 ? 'In Public Layout Plans more than once: ' . implode(', ', $layouts) . '.' : "Layout {$layouts[0]} in Public Layout Plans.")
                : 'Not in Public Layout Plans. It will still save; check the number if this layout should be there.',
            'layout' => $layouts[0] ?? null,
            'in_register' => (bool) $layouts,
            'ops' => $ops,
        ];
    }

    /** What a showing records for an OP's layout, when nobody picked one: the register row if linked, else the number as written. */
    public function showingValues(?string $tp): array
    {
        $group = $this->forRaw($tp);

        return ['survey_tp_number_id' => $group->survey_tp_number_id ?? null, 'tp_no' => $group->label ?? (self::raw($tp) ? trim((string) $tp) : null)];
    }

    /** The spelling as stored in op_tp_aliases. */
    public static function raw(?string $tp): ?string
    {
        $raw = mb_strtoupper(trim((string) $tp));

        return $raw === '' ? null : mb_substr($raw, 0, 255);
    }

    /**
     * Plot number order inside a layout (recording, 2026-11-05): residential numbers, then the C, IND
     * and SS series, each by number. Anything else comes last, as written.
     *
     * @return array{0:int, 1:int, 2:string}
     */
    public static function plotSort(?string $plot): array
    {
        $p = strtoupper(trim((string) $plot));
        if (preg_match('/^(\d+)(.*)$/', $p, $m)) return [0, (int) $m[1], $m[2]];
        if (preg_match('/^(C|IND|SS)[\s\-\/.]*(\d+)(.*)$/', $p, $m)) return [['C' => 1, 'IND' => 2, 'SS' => 3][$m[1]], (int) $m[2], $m[3]];

        return [$p === '' ? 5 : 4, 0, $p];
    }

    /** Sort OP rows by plot number, the plot series first. */
    public static function sortByPlot(array $ops): array
    {
        usort($ops, fn ($a, $b) => self::plotSort($a['plot_no'] ?? null) <=> self::plotSort($b['plot_no'] ?? null));

        return $ops;
    }

    /**
     * Work out the groups and, unless $apply is false, save them in short chunks.
     *
     * @return array{total:int, with_tp:int, no_tp:int, spellings:int, groups:int, in_register:int, in_register_ops:int, new_groups:int, new_spellings:int}
     */
    public function sync(bool $apply): array
    {
        $db = $this->db();
        $rows = $db->select("
            SELECT tp_no, op_type, COUNT(*) n FROM instrument_capture WITH (NOLOCK) WHERE " . self::OP . " AND (is_deleted IS NULL OR is_deleted = 0) GROUP BY tp_no, op_type
            UNION ALL
            SELECT tp_no, op_type, COUNT(*) FROM pra WITH (NOLOCK) WHERE " . self::OP . " AND instrument_capture_id IS NULL AND (is_deleted IS NULL OR is_deleted = 0) GROUP BY tp_no, op_type");

        $total = 0;
        $groups = [];
        $aliases = [];
        foreach ($rows as $r) {
            $n = (int) $r->n;
            $total += $n;
            $raw = self::raw($r->tp_no);
            $p = TpNumberKey::parse($raw);
            if ($p['key'] === null) continue;

            $k = $p['key'];
            $groups[$k] ??= ['key' => $k, 'parts' => $p, 'ops' => 0, 'res' => 0, 'direct' => 0, 'spell' => []];
            $groups[$k]['ops'] += $n;
            $groups[$k][stripos((string) $r->op_type, 'Resettlement') !== false ? 'res' : 'direct'] += $n;
            $groups[$k]['spell'][$raw] = ($groups[$k]['spell'][$raw] ?? 0) + $n;
        }

        // A number written with no agency (TP//328B) joins the one agency that has that serial. With two, it stays apart.
        $bySerial = [];
        foreach (array_keys($groups) as $k) {
            if (str_contains($k, '/')) $bySerial[substr(strrchr($k, '/'), 1)][] = $k;
        }
        foreach ($groups as $k => $g) {
            if (str_contains($k, '/') || count($bySerial[$k] ?? []) !== 1) continue;
            $to = $bySerial[$k][0];
            foreach (['ops', 'res', 'direct'] as $f) $groups[$to][$f] += $g[$f];
            foreach ($g['spell'] as $s => $n) $groups[$to]['spell'][$s] = ($groups[$to]['spell'][$s] ?? 0) + $n;
            unset($groups[$k]);
        }

        // Typing slips someone has said belong to another layout (op_tp_merges, user 2026-11-05). A merge whose
        // target has no OPs left is skipped, so nothing is folded into a group that is not there.
        $merged = [];
        $merges = self::merges();
        foreach ($merges as $from => $to) {
            for ($hops = 0; isset($merges[$to]) && $to !== $from && $hops < 10; $hops++) $to = $merges[$to];   // a chain a -> b -> c
            if (! isset($groups[$from], $groups[$to]) || $from === $to) continue;
            foreach (['ops', 'res', 'direct'] as $f) $groups[$to][$f] += $groups[$from][$f];
            foreach ($groups[$from]['spell'] as $s => $n) $groups[$to]['spell'][$s] = ($groups[$to]['spell'][$s] ?? 0) + $n;
            unset($groups[$from]);
            $merged[] = $from;
        }

        // The Public Layout Plans register. A number drawn twice there cannot name one layout, so it stays unlinked.
        $register = [];
        foreach (SurveyTpNumber::listed()->whereNotNull('tp_no')->get(['id', 'tp_no']) as $t) {
            if ($k = TpNumberKey::key($t->tp_no)) $register[$k][] = $t;
        }

        $inRegister = 0;
        $inRegisterOps = 0;
        $withTp = 0;
        $values = [];
        foreach ($groups as $k => $g) {
            $listed = $register[$k] ?? [];
            $withTp += $g['ops'];
            if ($listed) { $inRegister++; $inRegisterOps += $g['ops']; }
            arsort($g['spell']);
            $single = count($listed) === 1 ? $listed[0] : null;
            preg_match('/\d+/', (string) $g['parts']['serial'], $m);
            $values[$k] = [
                'label' => mb_substr($single->tp_no ?? array_key_first($g['spell']), 0, 255),
                'prefix' => $g['parts']['prefix'], 'agency' => $g['parts']['agency'], 'code' => $g['parts']['code'], 'serial' => $g['parts']['serial'],
                'serial_sort' => isset($m[0]) ? min((int) $m[0], 2147483647) : null,
                'survey_tp_number_id' => $single->id ?? null,
                'register_rows' => count($listed),
                'op_count' => $g['ops'], 'resettlement_count' => $g['res'], 'direct_count' => $g['direct'],
                // Not a layout number at all (KN., MIPP, M): left out of the dropdowns for someone to look at.
                'needs_review' => preg_match('/\d/', $k) ? 0 : 1,
            ];
            foreach ($g['spell'] as $s => $n) $aliases[$s] = ['key' => $k, 'n' => $n];
        }

        $stats = [
            'total' => $total, 'with_tp' => $withTp, 'no_tp' => $total - $withTp, 'spellings' => count($aliases), 'groups' => count($groups),
            'in_register' => $inRegister, 'in_register_ops' => $inRegisterOps, 'new_groups' => count($groups), 'new_spellings' => count($aliases),
        ];
        if (! self::installed()) return $stats;

        $haveGroups = $db->table('op_tp_groups')->pluck('id', 'group_key')->all();
        $haveAliases = $db->table('op_tp_aliases')->pluck('op_tp_group_id', 'raw_tp')->all();
        $stats['new_groups'] = count(array_diff_key($values, $haveGroups));
        $stats['new_spellings'] = count(array_diff_key($aliases, $haveAliases));
        if (! $apply) return $stats;

        $now = now();
        foreach ($values as $k => $v) {
            if (isset($haveGroups[$k])) {
                $db->table('op_tp_groups')->where('id', $haveGroups[$k])->update($v + ['updated_at' => $now]);
            } else {
                $haveGroups[$k] = $db->table('op_tp_groups')->insertGetId($v + ['group_key' => $k, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        foreach ($aliases as $raw => $a) {
            // A spelling that is all digits ("3991") comes back as an int array key; compared as an int, SQL
            // Server would try to read every raw_tp as a number and fail. Always a string.
            $raw = (string) $raw;
            $id = $haveGroups[$a['key']];
            if (array_key_exists($raw, $haveAliases)) {
                $db->table('op_tp_aliases')->where('raw_tp', $raw)->update(['op_tp_group_id' => $id, 'op_count' => $a['n'], 'updated_at' => $now]);
            } else {
                $db->table('op_tp_aliases')->insert(['raw_tp' => $raw, 'op_tp_group_id' => $id, 'op_count' => $a['n'], 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // A merged-away group has no spellings left now; its row goes so the dropdowns stop offering it.
        if ($merged) {
            $gone = array_values(array_intersect_key($haveGroups, array_flip($merged)));
            $still = $gone ? $db->table('op_tp_aliases')->whereIn('op_tp_group_id', $gone)->count() : 0;
            if ($gone && $still === 0) $db->table('op_tp_groups')->whereIn('id', $gone)->delete();
        }
        $stats['merged'] = count($merged);

        return $stats;
    }

    private static ?array $mergeMap = null;

    /** from_key => to_key, from op_tp_merges. */
    public static function merges(): array
    {
        if (self::$mergeMap !== null) return self::$mergeMap;
        if (! Schema::connection('sqlsrv')->hasTable('op_tp_merges')) return self::$mergeMap = [];

        return self::$mergeMap = DB::connection('sqlsrv')->table('op_tp_merges')->pluck('to_key', 'from_key')->all();
    }

    /** Forget the cached merge list (after adding merges in the same process). */
    public static function forgetMerges(): void
    {
        self::$mergeMap = null;
    }
}
