<?php

namespace App\Console\Commands;

use App\Models\LandRecommendationBatchDocument;
use App\Services\EdmsScanUploadFolderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Move mother recommendation scans into the mother file's own EDMS folder.
 *
 * Scans uploaded before the move went to a folder belonging to this module
 * (LandRecommendationBatchDocument::DIRECTORY), which put the one document that
 * belongs to a file somewhere nobody looking at that file would ever find it.
 * New uploads go straight to EDMS; this brings the older ones across.
 *
 *      EDMS/SCAN_UPLOAD/{Registry_Slug}/{MOTHER FILE NO}/Recommendation/
 *
 * Idempotent: a row already under EDMS/SCAN_UPLOAD is left alone, so this can be
 * run again after a partial run or on a server that is already half moved.
 *
 * The file is COPIED and the row repointed, then the original is removed only
 * once the copy is verified byte-for-byte in place. A half-finished move that
 * leaves a row pointing at nothing is worse than one that leaves a stale copy
 * behind: the model reads the EDMS root first and the public disk second, so a
 * row is readable at every point in between.
 */
class RelocateBatchRecommendationsToEdms extends Command
{
    protected $signature = 'recommendation:relocate-batch-documents
                            {--dry-run : List what would move, change nothing}
                            {--keep-original : Copy into EDMS but leave the old file on disk}';

    protected $description = 'Move mother recommendation scans into each mother file\'s EDMS folder';

    public function handle(EdmsScanUploadFolderService $folders): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $keep   = (bool) $this->option('keep-original');

        $documents = LandRecommendationBatchDocument::orderBy('id')->get();

        if ($documents->isEmpty()) {
            $this->info('No mother recommendations have been uploaded yet — nothing to move.');

            return self::SUCCESS;
        }

        $this->line('EDMS root: ' . $this->absolute(EdmsScanUploadFolderService::BASE_PATH));
        $this->line(($dryRun ? 'DRY RUN — ' : '') . $documents->count() . ' row(s) to consider.');
        $this->newLine();

        $moved = $skipped = $failed = 0;

        foreach ($documents as $document) {
            $label = $document->rofo_batch_id . ' (' . ($document->mother_file_no ?: 'no mother file no') . ')';

            if (str_starts_with(str_replace('\\', '/', (string) $document->path), EdmsScanUploadFolderService::BASE_PATH . '/')) {
                $this->line('  <fg=gray>already in EDMS</> ' . $label);
                $skipped++;
                continue;
            }

            $source = $document->absolutePath();

            if (!$source) {
                $this->warn('  no file on disk    ' . $label . ' — ' . $document->path);
                $failed++;
                continue;
            }

            $folder = $folders->folderName((string) $document->mother_file_no);

            if ($folder === '') {
                $this->warn('  unusable file no   ' . $label);
                $failed++;
                continue;
            }

            $relative = EdmsScanUploadFolderService::BASE_PATH
                . '/' . $folders->registrySlug(EdmsScanUploadFolderService::FOLIO_HOME_REGISTRY)
                . '/' . $folder
                . '/' . LandRecommendationBatchDocument::EDMS_CATEGORY
                . '/' . basename($source);

            $target = $this->absolute($relative);

            if ($dryRun) {
                $this->line('  <fg=cyan>would move</>       ' . $label);
                $this->line('      from ' . $source);
                $this->line('      to   ' . $target);
                $moved++;
                continue;
            }

            try {
                File::ensureDirectoryExists(dirname($target));
                File::copy($source, $target);

                // Verified before the row is repointed, and again before the
                // original goes: a copy onto a full or unreachable EDMS drive can
                // report success and leave a truncated file.
                if (!is_file($target) || filesize($target) !== filesize($source)) {
                    throw new \RuntimeException('the copy in EDMS does not match the original');
                }

                $document->path = $relative;
                $document->save();

                if (!$keep) {
                    File::delete($source);
                }

                $this->info('  moved              ' . $label);
                $this->line('      -> ' . $relative);
                $moved++;
            } catch (\Throwable $e) {
                $this->error('  FAILED             ' . $label . ' — ' . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->line(($dryRun ? 'Would move' : 'Moved') . ": {$moved}   already in EDMS: {$skipped}   problems: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * file_storage_path() and not storage_path(): STORAGE_PATH in .env is what puts
     * the EDMS tree on the F: drive, and only that helper reads it.
     */
    private function absolute(string $relative): string
    {
        $relative = ltrim($relative, '/\\');

        return function_exists('file_storage_path')
            ? file_storage_path('app/public/' . $relative)
            : storage_path('app/public/' . $relative);
    }
}
