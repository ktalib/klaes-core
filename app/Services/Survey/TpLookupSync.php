<?php

namespace App\Services\Survey;

use App\Http\Controllers\Survey\LookupController;
use App\Models\Survey\SurveyTpNumber as TP;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Survey > Public Survey > TP Nos is fed from tp_lookups (user, 2026-10-30): only valid
 * TP numbers (TP::parseLookup), each once, skipping any already on the register. Rows
 * added from the lookups carry no Date Created (nobody created them in KLAES). Empty
 * District / LGA are filled from the records with the same TP No.: File Indexing and the
 * MLS file numbers (fileNumber) first, Instrument Capture and PRA when those have none;
 * the most common value wins.
 *
 * New lookups show up on their own (user, 2026-10-30: "they auto display"): the TP Nos
 * page calls pullNew(), which only reads lookups newer than the last one it saw.
 */
class TpLookupSync
{
    public const REMARK = 'From TP lookups';

    private const LAST_SEEN = 'survey.tp_lookups.last_id';

    /** Source tables holding a TP No. with its district and LGA, best first. */
    private const SOURCES = [
        1 => ['file_indexings' => ['district', 'lga'], 'fileNumber' => ['district', 'lga']],
        2 => ['instrument_capture' => ['district', 'lga'], 'pra' => ['districtName', 'lgsaOrCity']],
    ];

    /** LGA spellings found in the records => the LGA list name. */
    private const LGA_ALIASES = [
        'NASSARAWA' => 'Nasarawa', 'MUNICIPAL' => 'Kano Municipal', 'KANOCITY' => 'Kano Municipal', 'UNGOGGO' => 'Ungogo',
        'KUNBOTSO' => 'Kumbotso', 'KOMBOTSO' => 'Kumbotso', 'D/TOFA' => 'Dawakin Tofa', 'D/KUDU' => 'Dawakin Kudu',
    ];

    /** District values that say nothing. */
    private const NO_DISTRICT = ['', 'OTHER', 'OTHERS', 'NIL', 'NA', 'N/A', 'NONE', 'UNKNOWN'];

    /**
     * Add lookups that arrived since the last call, with their District / LGA. Never
     * throws: a failure is logged and the page carries on with what is on the register.
     */
    public function pullNew(): int
    {
        try {
            $since = (int) (Cache::get(self::LAST_SEEN) ?? TP::withTrashed()->max('tp_lookup_id') ?? 0);
            $newest = (int) DB::connection('sqlsrv')->table('tp_lookups')->max('id');
            if ($newest <= $since) return 0;

            return Cache::lock('survey.tp_lookups.sync', 30)->get(function () use ($since, $newest) {
                $plan = $this->plan($since);
                $added = $this->apply($plan);
                if ($added) $this->fillLocations(true, $added);
                Cache::forever(self::LAST_SEEN, $newest);

                return count($added);
            }) ?: 0;
        } catch (\Throwable $e) {
            Log::warning('TP Nos: pulling new tp_lookups failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /** What adding the lookups (all, or those after $sinceId) would do. */
    public function plan(int $sinceId = 0): array
    {
        $onRegister = TP::withTrashed()->get(['id', 'tp_no', 'tp_lookup_id'])->keyBy(fn ($t) => mb_strtoupper($t->tp_no));
        $plan = ['add' => [], 'link' => [], 'rejected' => 0, 'repeats' => 0, 'already' => 0];

        $rows = DB::connection('sqlsrv')->table('tp_lookups')->where('id', '>', $sinceId)->orderBy('id')->get(['id', 'tp_no']);
        foreach ($rows as $row) {
            if (! $parts = TP::parseLookup($row->tp_no)) { $plan['rejected']++; continue; }
            $tpNo = TP::compose(...$parts);
            $key = mb_strtoupper($tpNo);
            if (isset($plan['add'][$key])) { $plan['repeats']++; continue; }
            if ($existing = $onRegister->get($key)) {
                if (! $existing->tp_lookup_id && ! isset($plan['link'][$existing->id])) $plan['link'][$existing->id] = $row->id;
                elseif ((int) $existing->tp_lookup_id === (int) $row->id) $plan['already']++;
                else $plan['repeats']++;
                continue;
            }
            $plan['add'][$key] = [
                'tp_no' => $tpNo, 'prefix' => $parts[0], 'serial_no' => $parts[1], 'serial_sort' => TP::serialSort($parts[1]),
                'tp_lookup_id' => $row->id, 'remarks' => self::REMARK, 'created_at' => null, 'updated_at' => now(),
            ];
        }

        return $plan;
    }

    /** Write a plan; returns the TP Nos added. */
    public function apply(array $plan): array
    {
        DB::connection('sqlsrv')->transaction(function () use ($plan) {
            foreach (array_chunk(array_values($plan['add']), 150) as $chunk) TP::insert($chunk);
            foreach ($plan['link'] as $id => $lookupId) TP::withTrashed()->whereKey($id)->update(['tp_lookup_id' => $lookupId]);
        });

        return array_column($plan['add'], 'tp_no');
    }

    /**
     * Fill empty District / LGA (on the given TP Nos, or the whole register); values staff
     * entered are never replaced. Returns [updates by id, the rows before].
     */
    public function fillLocations(bool $apply, ?array $onlyTpNos = null): array
    {
        $found = $this->locationsFromRecords();
        $updates = [];
        $rows = TP::where(fn ($q) => $q->whereNull('prop_lga')->orWhere('prop_lga', '')->orWhereNull('prop_district')->orWhere('prop_district', ''))
            ->when($onlyTpNos !== null, fn ($q) => $q->whereIn('tp_no', $onlyTpNos ?: ['']))
            ->get(['id', 'tp_no', 'prop_district', 'prop_district_other', 'prop_lga']);

        foreach ($rows as $t) {
            $loc = $found[mb_strtoupper($t->tp_no)] ?? null;
            if (! $loc) continue;
            $set = [];
            if (trim((string) $t->prop_lga) === '' && $loc['lga']) $set['prop_lga'] = $loc['lga'];
            if (trim((string) $t->prop_district) === '' && $loc['district']) {
                $set['prop_district'] = $loc['district'][0];
                $set['prop_district_other'] = $loc['district'][1];
            }
            if ($set) $updates[$t->id] = $set;
        }

        if ($apply) {
            foreach (array_chunk($updates, 200, true) as $chunk) {
                DB::connection('sqlsrv')->transaction(function () use ($chunk) {
                    foreach ($chunk as $id => $set) TP::whereKey($id)->update($set);
                });
            }
        }

        return [$updates, $rows];
    }

    /**
     * TP key => ['lga' => list name|null, 'district' => [prop_district, prop_district_other]|null].
     * Districts on the district list keep its spelling; others go in as Other + the text
     * found. District values that are really an LGA are ignored.
     */
    private function locationsFromRecords(): array
    {
        $key = fn ($v) => preg_replace('/[^A-Z0-9\/]/', '', mb_strtoupper(trim((string) $v)));
        $lgaList = [];
        foreach (LookupController::options('lgas') as $n) if ($n !== 'Unknown') $lgaList[$key($n)] = $n;
        foreach (self::LGA_ALIASES as $a => $n) $lgaList[$a] = $n;
        $districtList = [];
        foreach (LookupController::options('districts') as $n) $districtList[$key($n)] ??= $n;

        $votes = [];   // [tier][tp]['lga'|'district'][value] => count
        $labels = [];  // district key => [spelling => count]
        foreach (self::SOURCES as $tier => $tables) {
            foreach ($tables as $table => [$dc, $lc]) {
                $rows = DB::connection('sqlsrv')->select(
                    "SELECT tp_no, {$dc} AS d, {$lc} AS l, COUNT(*) AS c FROM {$table}
                     WHERE tp_no IS NOT NULL AND LTRIM(RTRIM(tp_no)) <> '' GROUP BY tp_no, {$dc}, {$lc}");
                foreach ($rows as $r) {
                    if (! $parts = TP::parseLookup($r->tp_no)) continue;
                    $tp = mb_strtoupper(TP::compose(...$parts));
                    $lk = $key($r->l);
                    if (isset($lgaList[$lk])) $votes[$tier][$tp]['lga'][$lgaList[$lk]] = ($votes[$tier][$tp]['lga'][$lgaList[$lk]] ?? 0) + $r->c;
                    $text = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $r->d), " `'.,-"));
                    $dk = $key($text);
                    if (in_array($text, self::NO_DISTRICT, true) || $dk === '' || ctype_digit($dk) || isset($lgaList[$dk])) continue;
                    $votes[$tier][$tp]['district'][$dk] = ($votes[$tier][$tp]['district'][$dk] ?? 0) + $r->c;
                    $labels[$dk][$text] = ($labels[$dk][$text] ?? 0) + $r->c;
                }
            }
        }

        $top = function (array $counts) { arsort($counts); return array_key_first($counts); };
        $out = [];
        foreach (array_unique(array_merge(array_keys($votes[1] ?? []), array_keys($votes[2] ?? []))) as $tp) {
            $lga = isset($votes[1][$tp]['lga']) ? $top($votes[1][$tp]['lga']) : (isset($votes[2][$tp]['lga']) ? $top($votes[2][$tp]['lga']) : null);
            $dk = isset($votes[1][$tp]['district']) ? $top($votes[1][$tp]['district']) : (isset($votes[2][$tp]['district']) ? $top($votes[2][$tp]['district']) : null);
            $district = $dk === null ? null
                : (isset($districtList[$dk]) ? [$districtList[$dk], null] : [TP::OTHER, (string) $top($labels[$dk])]);
            if ($lga || $district) $out[$tp] = ['lga' => $lga, 'district' => $district];
        }

        return $out;
    }
}
