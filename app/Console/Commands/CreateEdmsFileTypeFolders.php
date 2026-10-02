<?php

namespace App\Console\Commands;

use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\Edms\EdmsFileType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Create the EDMS master file-type folders under every registry.
 *
 * Step one of the master-folder work: the folders have to exist before anyone
 * can be asked to file into them, and an operator browsing SCAN_UPLOAD should
 * see the same skeleton under every registry whether or not a file has landed
 * in it yet.
 *
 *   EDMS/SCAN_UPLOAD/{Registry}/Regular
 *                              /Merger/Children
 *                              /Merger/New_File
 *                              /Subdivision/Mother
 *                              /Subdivision/Children
 *                              /Extension/Old
 *                              /Extension/New
 *                              /Temporary_File
 *                              /Change_of_Purpose/Old
 *                              /Change_of_Purpose/New
 *
 * Applied to SCAN_UPLOAD, PAGETYPING and ARCHIVE_Doc_WARE — the three trees a
 * file's documents travel through. BLIND_SCAN is left alone: its folders hold
 * documents that have not been matched to a file yet, so the type is unknown.
 *
 * Creates only; it never deletes, never moves a document, and is safe to re-run
 * after a new registry appears.
 */
class CreateEdmsFileTypeFolders extends Command
{
    protected $signature = 'edms:create-file-type-folders
                            {--registry=* : Limit to these registries (name or slug); defaults to every registry folder on disk}
                            {--tree=* : Limit to these trees: scan_upload, pagetyping, archive}
                            {--dry-run : List what would be created without touching the disk}';

    protected $description = 'Create the Regular / Parcel Update / Title Status master folders (from the edms_file_types lookup table) under each EDMS registry';

    /** tree option => root path */
    private const TREES = [
        'scan_upload' => EdmsDocumentPathResolver::SCAN_UPLOAD_ROOT,
        'pagetyping'  => EdmsDocumentPathResolver::PAGETYPING_ROOT,
        'archive'     => EdmsDocumentPathResolver::ARCHIVE_ROOT,
    ];

    /** @var EdmsDocumentPathResolver */
    private $paths;

    public function __construct(EdmsDocumentPathResolver $paths)
    {
        parent::__construct();

        $this->paths = $paths;
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $trees = $this->resolveTrees();

        if ($trees === null) {
            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $skeleton = EdmsFileType::folderSkeleton();

        $created = 0;
        $existed = 0;
        $failed = 0;

        $registries = $this->registries();

        foreach ($trees as $root) {

            if (empty($registries)) {
                $this->warn("  {$root} — no registry folders found, skipped.");
                continue;
            }

            $this->line('');
            $this->info($root);

            foreach ($registries as $registrySlug) {
                $madeHere = 0;

                foreach ($skeleton as $folder) {
                    $path = $root . '/' . $registrySlug . '/' . $folder;

                    if ($disk->exists($path)) {
                        $existed++;
                        continue;
                    }

                    if ($dryRun) {
                        $created++;
                        $madeHere++;
                        $this->line("    + {$path}");
                        continue;
                    }

                    if ($disk->makeDirectory($path)) {
                        $created++;
                        $madeHere++;
                    } else {
                        $failed++;
                        $this->error("    ! could not create {$path}");
                    }
                }

                $this->line(sprintf(
                    '  %-28s %s',
                    $registrySlug,
                    $madeHere > 0 ? "{$madeHere} folder(s) created" : 'already complete'
                ));
            }
        }

        $this->line('');
        $this->line(sprintf(
            '%s%d created, %d already present%s.',
            $dryRun ? '[dry run] ' : '',
            $created,
            $existed,
            $failed > 0 ? ", {$failed} FAILED" : ''
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string,string>|null tree key => root path, or null when the
     *                                   --tree option names something unknown
     */
    private function resolveTrees(): ?array
    {
        $requested = array_filter((array) $this->option('tree'));

        if (empty($requested)) {
            return self::TREES;
        }

        $trees = [];
        foreach ($requested as $key) {
            $key = strtolower(trim($key));

            if (!isset(self::TREES[$key])) {
                $this->error("Unknown tree '{$key}'. Valid: " . implode(', ', array_keys(self::TREES)) . '.');

                return null;
            }

            $trees[$key] = self::TREES[$key];
        }

        return $trees;
    }

    /**
     * The registry folders to build under — the same set in every tree.
     *
     * The union of the canonical registry map and whatever is already on disk in
     * any of the trees. The map alone would miss the folders the scanning modules
     * grew on their own (Cadastral_Index_card, Physical_Planning_Registry); the
     * disk alone would give each tree a different skeleton, since ARCHIVE_Doc_WARE
     * only has folders for registries whose files have reached it. Operators
     * browse all three, so all three get the same shape.
     *
     * With --registry the names are resolved through the same slug logic every
     * reader uses, so "KANGIS" and "KANGIS Registry" both hit KANGIS_Registry.
     *
     * @return string[]
     */
    private function registries(): array
    {
        $requested = array_filter((array) $this->option('registry'));

        if (!empty($requested)) {
            return array_values(array_unique(array_map(
                fn ($registry) => $this->paths->registrySlug($registry),
                $requested
            )));
        }

        $slugs = array_values($this->paths->registryMap());

        foreach (self::TREES as $root) {
            foreach (Storage::disk('public')->directories($root) as $folder) {
                $name = basename($folder);

                // Housekeeping folders the scanning modules keep beside the
                // registries (_qc_backups), and the master folders themselves on
                // a re-run.
                if (str_starts_with($name, '_') || in_array($name, EdmsFileType::folderSkeleton(), true)) {
                    continue;
                }

                $slugs[] = $name;
            }
        }

        $slugs = array_values(array_unique($slugs));
        sort($slugs);

        return $slugs;
    }
}
