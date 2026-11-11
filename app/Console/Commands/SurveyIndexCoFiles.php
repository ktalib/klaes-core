<?php

namespace App\Console\Commands;

use App\Models\Survey\SurveyCoFile;
use App\Services\Survey\SurveyFileIndexing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Put the CO files already in Survey into File Indexing (user, 2026-11-09: "Add all 203 now", list first).
 * Without --apply it only writes the list to storage/app/survey-indexing/. With --apply each CO file
 * without a File Indexing row gets one (registry Survey); a row that already exists is brought up to date
 * from the CO file. --undo=<csv> removes the rows an --apply run created, while they have no scans or tracking.
 */
class SurveyIndexCoFiles extends Command
{
    protected $signature = 'survey:index-co-files {--apply : write the rows} {--undo= : the CSV of an --apply run}';

    protected $description = 'Index the Survey CO files in file_indexings (registry Survey). Dry run unless --apply.';

    public function handle(SurveyFileIndexing $indexing): int
    {
        $db = DB::connection('sqlsrv');
        if ($undo = $this->option('undo')) {
            return $this->undo($undo);
        }

        $apply = (bool) $this->option('apply');
        $dir = storage_path('app/survey-indexing');
        if (! is_dir($dir)) mkdir($dir, 0775, true);
        $csv = $dir . '/co-files-' . now()->format('Ymd-His') . ($apply ? '-applied' : '-dry-run') . '.csv';
        $out = fopen($csv, 'w');
        fputcsv($out, ['S/N', 'CO File No', 'Action', 'File Indexing id', 'Title', 'Plot No.', 'District', 'LGA', 'Location', 'Source', 'Captured on']);

        $counts = ['create' => 0, 'update' => 0, 'crossed_out' => 0];
        $sn = 0;
        foreach (SurveyCoFile::query()->orderBy('id')->get() as $f) {
            $existing = $db->table('file_indexings')->where('file_number', $f->co_file_no)->orderBy('id')->first(['id', 'is_deleted']);
            $action = ! $existing ? 'create' : ((int) $existing->is_deleted === 1 ? 'crossed_out' : 'update');
            $counts[$action]++;
            $id = $existing->id ?? null;
            if ($apply && $action !== 'crossed_out') {
                // The CO file's own creator, as the indexing row's "created by".
                $u = $f->created_by ? \App\Models\User::find($f->created_by) : null;
                $by = $u ? (trim($u->name ?: ($u->first_name . ' ' . $u->last_name)) ?: ($u->email ?: (string) $u->id)) : null;
                $indexing->indexCoFile($f, null, $by ?: 'System');
                $id ??= $db->table('file_indexings')->where('file_number', $f->co_file_no)->orderByDesc('id')->value('id');
            }
            $row = $indexing->coFileRow($f);
            fputcsv($out, [++$sn, $f->co_file_no, $action, $id, $row['file_title'], $row['plot_number'], $row['district'], $row['lga'], $row['location'],
                $f->source, optional($f->created_at)->format('Y-m-d H:i')]);
        }
        fclose($out);

        $this->info(($apply ? 'Indexed' : 'Would index') . ": {$counts['create']} new, {$counts['update']} already indexed (updated from the CO file), "
            . "{$counts['crossed_out']} crossed out in File Indexing (left alone).");
        $this->line("List: {$csv}");

        return 0;
    }

    private function undo(string $csv): int
    {
        if (! is_file($csv)) {
            $this->error("No such file: {$csv}");
            return 1;
        }
        $ids = [];
        $fh = fopen($csv, 'r');
        fgetcsv($fh);
        while (($r = fgetcsv($fh)) !== false) {
            if (($r[2] ?? '') === 'create' && ! empty($r[3])) $ids[] = (int) $r[3];
        }
        fclose($fh);
        // Removes only the rows that run made, and only while nothing hangs on them (no scans, no tracker),
        // so the number can be indexed again. A crossed-out row would keep the number taken.
        $db = DB::connection('sqlsrv');
        $n = $db->table('file_indexings as f')->whereIn('f.id', $ids ?: [0])
            ->whereIn('f.source', [SurveyFileIndexing::SOURCE_CAPTURED, SurveyFileIndexing::SOURCE_COMMISSIONED])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('scannings as s')->whereColumn('s.file_indexing_id', 'f.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('file_tracker as t')->whereColumn('t.file_number', 'f.file_number'))
            ->delete();
        $this->info("Removed {$n} of " . count($ids) . ' row(s) created by that run; the rest have scans or tracking and were kept.');

        return 0;
    }
}
