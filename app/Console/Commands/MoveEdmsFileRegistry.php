<?php

namespace App\Console\Commands;

use App\Services\Edms\EdmsRegistryTransferService;
use Illuminate\Console\Command;

/**
 * Move a file's documents from one registry to another across SCAN_UPLOAD,
 * PAGETYPING and ARCHIVE_Doc_WARE, keeping the DB records in step.
 */
class MoveEdmsFileRegistry extends Command
{
    protected $signature = 'edms:move-registry
                            {file : File number, e.g. RES-2000-2442}
                            {registry : Target registry, e.g. "Cadastral Registry" or "SLTR"}
                            {--reason= : Stored in the audit log}
                            {--user= : User id to record as the actor (defaults to the file creator)}
                            {--dry-run : Show what would move without touching anything}';

    protected $description = 'Move a file\'s scans, typed pages and Doc-WARE archive copies to another registry';

    public function handle(EdmsRegistryTransferService $transfers): int
    {
        $fileNumber = (string) $this->argument('file');
        $registry = (string) $this->argument('registry');
        $dryRun = (bool) $this->option('dry-run');
        $actorId = $this->option('user') ? (int) $this->option('user') : null;

        try {
            $result = $transfers->transferByFileNumber(
                $fileNumber,
                $registry,
                $this->option('reason'),
                true, // always preview first
                $actorId
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->info("File {$result['file_number']}: {$result['from_registry']} -> {$result['to_registry']}");
        $this->line("  {$result['from_slug']}/  ->  {$result['to_slug']}/");
        $this->line('');

        $rows = [];
        foreach ($result['scannings'] as $row) {
            $rows[] = ['scan #' . $row['id'], $row['on_disk'] ? 'yes' : 'MISSING', $row['resolved_path'] ?: '-', $row['target_path'] ?: '-'];
        }
        foreach ($result['pagetypings'] as $row) {
            $rows[] = ['page #' . $row['id'], $row['on_disk'] ? 'yes' : 'MISSING', $row['resolved_path'] ?: '-', $row['target_path'] ?: '-'];
        }

        if (empty($rows)) {
            $this->warn('No scans or typed pages are attached to this file. Only the indexing record would change.');
        } else {
            $this->table(['Record', 'On disk', 'From', 'To'], $rows);
        }

        $counts = $result['counts'];
        $this->line("  scans: {$counts['scannings']}   typed pages: {$counts['pagetypings']}   missing on disk: {$counts['missing_on_disk']}");

        if ($counts['missing_on_disk'] > 0) {
            $this->warn('  Some documents were not found on disk; their records will still be re-pointed.');
        }

        if ($dryRun) {
            $this->line('');
            $this->comment('Dry run — nothing was changed.');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && !$this->confirm("Move {$result['file_number']} to {$result['to_registry']}?", false)) {
            $this->comment('Aborted.');

            return self::SUCCESS;
        }

        try {
            $moved = $transfers->transferByFileNumber($fileNumber, $registry, $this->option('reason'), false, $actorId);
        } catch (\Throwable $e) {
            $this->error('Transfer failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->info(sprintf(
            'Moved %d file(s); re-pointed %d scan(s) and %d typed page(s).',
            $moved['moved']['files'],
            $moved['moved']['scannings'],
            $moved['moved']['pagetypings']
        ));

        return self::SUCCESS;
    }
}
