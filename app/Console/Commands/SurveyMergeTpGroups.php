<?php

namespace App\Console\Commands;

use App\Services\Survey\OpTpGroups;
use App\Support\TpNumberKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Says that some OP TP groups are typing slips of another layout (user, 2026-11-05: "these tp numbers look like
 * they should be the same, and the user made a typo error"). Each merge goes in op_tp_merges and the OP TP sync
 * then shows those OPs under the right layout, tonight and every night. Deeds rows are never written.
 *
 *   survey:merge-tp-groups "TP/MLPP/DKD/00" "TP/MLPP/DKD/0O1" --into="TP/MLPP/DKD/01" [--apply]
 *   survey:merge-tp-groups --csv=path.csv [--apply]        columns typo_group, merge_into (header row)
 *   survey:merge-tp-groups --remove="TP/MLPP/DKD/00" [--apply]   the group comes back on its own
 *   survey:merge-tp-groups --list
 *
 * A TP No is matched by its spelling on OPs first, then by its group key.
 */
class SurveyMergeTpGroups extends Command
{
    protected $signature = 'survey:merge-tp-groups {typos?*} {--into= : The TP No they belong to} {--csv= : A CSV of typo_group,merge_into}
        {--remove= : Take a merge back out} {--note= : Why} {--list : Show the merges} {--apply : Write; without it, only shows}';

    protected $description = 'Fold misspelt OP TP groups into the layout they belong to';

    public function handle(OpTpGroups $groups): int
    {
        $db = DB::connection('sqlsrv');

        if ($this->option('list')) {
            $rows = $db->table('op_tp_merges')->orderBy('to_key')->orderBy('from_key')->get();
            $this->table(['Typo group', 'Merged into', 'Note', 'Added'], $rows->map(fn ($r) => [$r->from_label ?: $r->from_key, $r->to_label ?: $r->to_key, $r->note, $r->created_at])->all());
            return self::SUCCESS;
        }

        if ($remove = $this->option('remove')) {
            $key = $this->keyOf($remove);
            $row = $key ? $db->table('op_tp_merges')->where('from_key', $key)->first() : null;
            if (! $row) { $this->error("No merge for {$remove}."); return self::FAILURE; }
            $this->line("Remove merge {$row->from_label} -> {$row->to_label}.");
            if (! $this->option('apply')) { $this->warn('Dry run. Add --apply.'); return self::SUCCESS; }
            $db->table('op_tp_merges')->where('id', $row->id)->delete();
            OpTpGroups::forgetMerges();
            $groups->sync(true);
            $this->info('Removed; the group is back on its own.');
            return self::SUCCESS;
        }

        $pairs = [];
        if ($csv = $this->option('csv')) {
            if (! is_file($csv)) { $this->error("No file {$csv}."); return self::FAILURE; }
            $f = fopen($csv, 'r');
            $head = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($f) ?: []);
            $iFrom = array_search('typo_group', $head, true); $iTo = array_search('merge_into', $head, true);
            if ($iFrom === false || $iTo === false) { $this->error('The CSV needs typo_group and merge_into columns.'); return self::FAILURE; }
            while (($r = fgetcsv($f)) !== false) if (trim((string) ($r[$iFrom] ?? '')) !== '') $pairs[] = [trim($r[$iFrom]), trim((string) ($r[$iTo] ?? ''))];
            fclose($f);
        } else {
            $into = trim((string) $this->option('into'));
            if (! $this->argument('typos') || $into === '') { $this->error('Give the typo TP numbers and --into=, or --csv=.'); return self::FAILURE; }
            foreach ($this->argument('typos') as $t) $pairs[] = [$t, $into];
        }

        $label = fn (?string $key) => $key ? ($db->table('op_tp_groups')->where('group_key', $key)->value('label') ?? $key) : null;
        $ops = fn (?string $key) => $key ? (int) $db->table('op_tp_groups')->where('group_key', $key)->value('op_count') : 0;
        $plan = [];
        foreach ($pairs as [$from, $to]) {
            $fk = $this->keyOf($from); $tk = $this->keyOf($to);
            $why = match (true) {
                ! $fk => 'no such group', ! $tk => 'target unknown', $fk === $tk => 'already the same layout',
                ! $db->table('op_tp_groups')->where('group_key', $tk)->exists() => 'target has no group', default => null,
            };
            $this->line(sprintf('  %-26s %5s OPs  ->  %-22s %5s OPs  %s', $from, $ops($fk), $label($tk) ?? $to, $ops($tk), $why ? "SKIPPED: {$why}" : ''));
            if (! $why) $plan[$fk] = ['to' => $tk, 'from_label' => $label($fk), 'to_label' => $label($tk)];
        }
        $this->line(count($plan) . ' merge(s) to record.');
        if (! $this->option('apply')) { $this->warn('Dry run. Nothing written. Add --apply.'); return self::SUCCESS; }

        $now = now();
        foreach ($plan as $fk => $p) {
            $db->table('op_tp_merges')->updateOrInsert(['from_key' => $fk], [
                'to_key' => $p['to'], 'from_label' => $p['from_label'], 'to_label' => $p['to_label'],
                'note' => $this->option('note') ?: 'Typing slip of ' . $p['to_label'], 'created_by' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        OpTpGroups::forgetMerges();
        $s = $groups->sync(true);
        $this->info("Recorded. Sync: {$s['groups']} groups, {$s['merged']} merged away, {$s['with_tp']} OPs with a TP No.");

        return self::SUCCESS;
    }

    /** The group a TP No belongs to: by its spelling on OPs, else by its key. */
    private function keyOf(string $tp): ?string
    {
        $db = DB::connection('sqlsrv');
        $raw = OpTpGroups::raw($tp);
        if ($raw && ($k = $db->table('op_tp_aliases as a')->join('op_tp_groups as g', 'g.id', '=', 'a.op_tp_group_id')->where('a.raw_tp', $raw)->value('g.group_key'))) return $k;
        if ($db->table('op_tp_groups')->where('label', $tp)->exists()) return $db->table('op_tp_groups')->where('label', $tp)->value('group_key');
        if ($db->table('op_tp_merges')->where('from_label', $tp)->exists()) return $db->table('op_tp_merges')->where('from_label', $tp)->value('from_key');

        return TpNumberKey::key($tp);
    }
}
