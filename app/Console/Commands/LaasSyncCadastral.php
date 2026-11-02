<?php

namespace App\Console\Commands;

use App\Models\CadastralDept\CadastralCase;
use App\Models\Laas\LaasApplication;
use App\Services\Laas\LaasCadastralProgress;
use App\Services\Laas\LaasWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Portal files already in the Cadastral workflow before LaasCadastralProgress was hooked in
 * (user, 2026-11-02): moves their portal stage to "At Cadastral" and adds the "Received at
 * Cadastral" line, dated when Cadastral received the file. No SMS. Safe to re-run: a file that
 * already has the line is skipped. The progress panel itself needs nothing; it reads live.
 */
class LaasSyncCadastral extends Command
{
    protected $signature = 'laas:sync-cadastral {--dry-run : List what would change without writing}';

    protected $description = 'Bring LAAS portal files already in the Cadastral workflow up to date (no SMS)';

    public function handle(LaasWorkflowService $workflow, LaasCadastralProgress $progress): int
    {
        $db = DB::connection('sqlsrv');
        $todo = [];

        foreach (CadastralCase::query()->orderBy('id')->get() as $case) {
            $app = $workflow->findByFileNumber((string) $case->file_number);
            if (!$app || $app->isReadOnly()) continue;
            $has = $db->table('laas_application_events')->where('laas_application_id', $app->id)
                ->where('title', 'Received at Cadastral')->exists();
            if ($has) continue;
            $todo[] = [$case, $app];
        }

        $this->info(count($todo) . ' portal file(s) to bring up to date.');
        foreach ($todo as [$case, $app]) {
            $this->line("  {$case->case_ref}  {$case->file_number}  {$app->reference_no}  stage {$app->stage}"
                . ($app->canAdvanceTo(LaasApplication::STAGE_AT_CADASTRAL) ? ' -> at_cadastral' : ' (stage kept)'));
        }
        if ($this->option('dry-run') || !$todo) {
            return self::SUCCESS;
        }

        // CSV of the rows before the change.
        $csv = "id,reference_no,file_number,stage,updated_at\n";
        foreach ($todo as [, $app]) {
            $csv .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                [$app->id, $app->reference_no, $app->file_number, $app->stage, $app->updated_at])) . "\n";
        }
        $path = 'backups/laas_applications_before_cadastral_sync_' . now()->format('Ymd_His') . '.csv';
        Storage::disk('local')->put($path, $csv);
        $this->info("Backup: storage/app/{$path}");

        $done = 0;
        foreach ($todo as [$case, $app]) {
            $db->transaction(function () use ($progress, $case, $db, $app, &$done) {
                if ($progress->record($case, null, $case->current_step, 'registered') !== 'opened') return;
                // Date the line when Cadastral received the file, not today.
                $at = $case->received_at ?? $case->created_at;
                $eventId = $db->table('laas_application_events')->where('laas_application_id', $app->id)
                    ->where('title', 'Received at Cadastral')->max('id');
                $db->table('laas_application_events')->where('id', $eventId)->update(['created_at' => $at, 'updated_at' => $at]);
                $done++;
            });
        }
        $this->info("Updated {$done} file(s). No SMS sent.");

        return self::SUCCESS;
    }
}
