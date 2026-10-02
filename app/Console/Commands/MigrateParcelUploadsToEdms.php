<?php

namespace App\Console\Commands;

use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\Edms\ParcelDocumentIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Brings parcel-update uploads into EDMS so they appear in the EDMS workflow and
 * the Virtual Folder System.
 *
 * THE PROBLEM
 * Change of Purpose, Subdivision, Merger and Duplex Parcel Update all accept
 * document uploads, and every one of them writes to its own folder on the public
 * disk — site_plans/, parcel_documents/subdivision/, and so on. None of it lands
 * in the EDMS tree, and none of it creates a scannings or pagetypings row. The
 * result is a file that demonstrably HAS documents but shows zero pages
 * everywhere else in KLAES:
 *
 *     RES-RC-1981-99   2 Change of Purpose site plans on disk
 *                      file_indexings id 117486, 0 pagetypings, 0 scannings
 *
 * WHAT THIS DOES
 * For each referenced upload:
 *   1. COPIES the file to EDMS/SCAN_UPLOAD/{registry}/{fileNo}/{PAPER}/ — the
 *      authoritative original, exactly where a scanned page would live.
 *   2. COPIES it again to EDMS/PAGETYPING/... under its derived page code, which
 *      is the layout the page-typing tool and the VFS both read.
 *   3. Writes the scannings row (naming the SCAN_UPLOAD copy) and the pagetypings
 *      row (naming the PAGETYPING copy), matching the convention already in the
 *      table: definition = page position, definition_code = "{definition}-{code}".
 *   4. Repoints the parcel table's own column at the SCAN_UPLOAD copy, so the
 *      parcel screens and EDMS read the same bytes from the same place.
 *
 * COPIES, NEVER MOVES. The original under site_plans/ or parcel_documents/ is
 * left exactly where it is. This deployment has no backups, so the old file is
 * the only safety net if a path turns out wrong; deleting it is a separate
 * decision for after this is confirmed good.
 *
 * PAGE TYPE MAPPING is declared in DOCUMENT_MAP below. These are judgement calls
 * about how each document should be filed, and they decide which virtual folder
 * it lands in — review them before --apply. They are plain column values and can
 * be corrected later with an UPDATE.
 *
 * Safety: DRY RUN by default · --apply logs every row it creates · --rollback
 * removes only what a run created and restores the original stored path.
 */
class MigrateParcelUploadsToEdms extends Command
{
    protected $signature = 'parcel:migrate-uploads-to-edms
        {--apply : Actually copy and write. Omit for a read-only dry-run report.}
        {--rollback : Undo the last --apply run.}
        {--table= : Restrict to one source table.}
        {--file= : Restrict to one file number.}';

    protected $description = 'Copy parcel-update uploads into EDMS and register them as scannings/pagetypings (dry-run by default).';

    private const CONNECTION = 'sqlsrv';
    private const LOG_TABLE = 'parcel_upload_edms_log';

    /** Stamped as uploader/typist, matching the convention used for system-created rows. */
    private const SYSTEM_USER = 0;

    /**
     * Source tables => the column holding the file number, and the upload columns.
     *
     * duplex_parcel_updates has no file-number column of its own; its numbers live
     * on duplex_parcel_update_files, so it is resolved separately below.
     */
    private const SOURCES = [
        'change_of_purpose_applications' => [
            'file_column' => 'file_no',
            'uploads' => ['site_plan', 'rec_page_application', 'rec_page_planning', 'rec_page_site_plan'],
        ],
        'plot_subdivision_applications' => [
            'file_column' => 'file_no',
            'uploads' => ['site_plan', 'ownership_document', 'application_letter', 'means_of_id', 'tax_clearance'],
        ],
        'plot_merger_applications' => [
            'file_column' => 'file_no',
            'uploads' => ['site_plan', 'ownership_document', 'application_letter', 'means_of_id', 'tax_clearance'],
        ],
        'duplex_parcel_updates' => [
            'file_column' => null,
            'uploads' => ['site_plan'],
        ],
    ];

    /**
     * Upload column => how the page is filed.
     *
     * [PageType.id, PageSubType.id|null, human label for scannings.document_type]
     *
     * A site plan is filed under Survey / Survey Plan rather than Town Planning:
     * the drawing itself is a survey product, and PageSubType 25 is the only
     * "Survey Plan" the register offers under a type that means the drawing rather
     * than a covering letter about one. There is no "Site Plan" subtype in
     * PageSubType at all, which is worth adding later.
     *
     * Where no subtype fits, null is stored rather than a near-miss — a wrong
     * subtype is harder to spot and undo than a missing one.
     */
    private const DOCUMENT_MAP = ParcelDocumentIngestService::DOCUMENT_MAP;

    /** Uploads arrive as phone photos and office scans; nothing records a real sheet size. */
    private const PAPER_SIZE = 'A4';

    public function __construct(
        private EdmsDocumentPathResolver $paths,
        private ParcelDocumentIngestService $ingest
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $conn = DB::connection(self::CONNECTION);

        if ($this->option('rollback')) {
            return $this->rollback($conn);
        }

        $apply = (bool) $this->option('apply');
        $cfg = config('database.connections.' . self::CONNECTION);

        $this->info(($apply ? '[APPLY]' : '[DRY-RUN]') . " parcel uploads -> EDMS — {$cfg['host']}/{$cfg['database']}");
        $this->line('Public disk root: ' . config('filesystems.disks.public.root'));
        $this->newLine();

        $runId = date('Ymd-His');
        $log = [];
        $counts = ['copied' => 0, 'skipped' => 0, 'errors' => 0];

        if ($apply) {
            $this->ensureLogTable($conn);
        }

        foreach (self::SOURCES as $table => $spec) {
            if (($only = $this->option('table')) && $only !== $table) {
                continue;
            }

            if (!Schema::connection(self::CONNECTION)->hasTable($table)) {
                $this->warn("  {$table}: table not present, skipped.");
                continue;
            }

            $this->line("<info>{$table}</info>");
            $this->processTable($conn, $table, $spec, $apply, $runId, $log, $counts);
            $this->newLine();
        }

        $this->table(
            [$apply ? 'copied' : 'would copy', 'skipped', 'errors'],
            [[$counts['copied'], $counts['skipped'], $counts['errors']]]
        );

        $this->writeReport($log, $apply, $runId);

        if ($apply) {
            $this->comment('Originals were NOT deleted. Delete them only after confirming the EDMS copies.');
            $this->comment('Undo with: php artisan parcel:migrate-uploads-to-edms --rollback');
        } else {
            $this->comment('Review the report and the DOCUMENT_MAP page types, then re-run with --apply.');
        }

        return self::SUCCESS;
    }

    private function processTable($conn, string $table, array $spec, bool $apply, string $runId, array &$log, array &$counts): void
    {
        $available = Schema::connection(self::CONNECTION)->getColumnListing($table);
        $uploads = array_values(array_intersect($spec['uploads'], $available));

        if (!$uploads) {
            $this->warn('  no upload columns on this deployment.');

            return;
        }

        $rows = $conn->table($table)
            ->where(function ($q) use ($uploads) {
                foreach ($uploads as $col) {
                    $q->orWhere(fn ($w) => $w->whereNotNull($col)->where($col, '<>', ''));
                }
            })
            ->get();

        foreach ($rows as $row) {
            $fileNo = $this->fileNumberFor($conn, $table, $spec, $row);

            if (($only = $this->option('file')) && strcasecmp((string) $fileNo, $only) !== 0) {
                continue;
            }

            if (!$fileNo) {
                $counts['skipped']++;
                $log[] = [$table, $row->id, '', '', '', 'SKIP: no file number on the parcel row'];
                continue;
            }

            // The EDMS tree is keyed by the INDEXED file. Without an indexing row
            // there is no registry to file under and nothing for the VFS to attach
            // the page to, so the upload is reported rather than guessed at.
            $indexing = $conn->table('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper(trim($fileNo))])
                ->first(['id', 'file_number', 'registry']);

            if (!$indexing) {
                $counts['skipped']++;
                $log[] = [$table, $row->id, $fileNo, '', '', 'SKIP: file number is not indexed'];
                continue;
            }

            foreach ($uploads as $column) {
                $stored = trim((string) ($row->$column ?? ''));

                if ($stored === '') {
                    continue;
                }

                try {
                    $this->migrateOne($conn, $table, $row, $column, $stored, $indexing, $apply, $runId, $log, $counts);
                } catch (Throwable $e) {
                    $counts['errors']++;
                    $log[] = [$table, $row->id, $fileNo, $column, $stored, 'ERROR: ' . $e->getMessage()];
                    $this->error("    {$fileNo} {$column}: " . $e->getMessage());
                }
            }
        }
    }

    private function migrateOne($conn, string $table, $row, string $column, string $stored, $indexing, bool $apply, string $runId, array &$log, array &$counts): void
    {
        $fileNo = $indexing->file_number;

        // Already inside the EDMS tree: a re-run, or an upload written after the
        // controllers were switched over. Nothing to do, and copying again would
        // create a duplicate page.
        if (str_starts_with($stored, 'EDMS/')) {
            $counts['skipped']++;
            $log[] = [$table, $row->id, $fileNo, $column, $stored, 'SKIP: already in EDMS'];

            return;
        }

        if (!Storage::disk('public')->exists($stored)) {
            $counts['skipped']++;
            $log[] = [$table, $row->id, $fileNo, $column, $stored, 'SKIP: file not on disk'];

            return;
        }

        [$pageType, $pageSubType, $docLabel] = self::DOCUMENT_MAP[$column] ?? [10, null, 'Parcel Update Document'];

        $extension = pathinfo($stored, PATHINFO_EXTENSION) ?: 'jpg';
        $originalName = basename($stored);

        // Page position continues the file's existing pages rather than restarting
        // at 1, so a file that already has scans does not end up with two page 1s.
        $position = $this->nextPosition($indexing->id);
        $pageCode = $this->pageCode($pageType, $pageSubType, $position);
        $definitionCode = $position . '-' . $pageCode;

        $scanPath = $this->paths->scanUploadPath($indexing->registry, $fileNo, self::PAPER_SIZE, $originalName);
        $typedPath = $this->paths->pageTypingPath($indexing->registry, $fileNo, self::PAPER_SIZE, $definitionCode . '.' . $extension);

        $this->line(sprintf('    %-18s %-22s -> %s', $fileNo, $column, $typedPath));

        $log[] = [$table, $row->id, $fileNo, $column, $stored, ($apply ? 'MIGRATED -> ' : 'WOULD MIGRATE -> ') . $scanPath];

        if (!$apply) {
            $counts['copied']++;

            return;
        }

        // Copy BEFORE the database rows: a row pointing at a file that failed to
        // copy is a broken page in the viewer, whereas a copied file with no row
        // is inert and gets picked up by a re-run.
        if (!$this->paths->copyWithin($stored, $scanPath) || !$this->paths->copyWithin($stored, $typedPath)) {
            throw new \RuntimeException('copy failed for ' . $stored);
        }

        $size = Storage::disk('public')->size($stored);
        $registryName = $this->paths->registryName($indexing->registry);

        $conn->transaction(function () use (
            $conn, $table, $row, $column, $stored, $indexing, $scanPath, $typedPath,
            $originalName, $size, $registryName, $pageType, $pageSubType, $docLabel,
            $pageCode, $definitionCode, $position, $runId, &$counts
        ) {
            $scanningId = $conn->table('scannings')->insertGetId([
                'file_indexing_id' => $indexing->id,
                'document_path' => $scanPath,
                'uploaded_by' => self::SYSTEM_USER,
                'status' => 'completed',
                'original_filename' => $originalName,
                'paper_size' => self::PAPER_SIZE,
                'document_type' => $docLabel,
                'notes' => 'Migrated from ' . $table . '.' . $column . ' (parcel update upload).',
                'display_order' => $position,
                'file_size' => $size,
                'is_pdf_converted' => 0,
                'registry' => $registryName,
                'definition' => $position,
                'definition_code' => mb_substr($position . '-' . $indexing->file_number, 0, 100),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $conn->table('pagetypings')->insert([
                'file_indexing_id' => $indexing->id,
                'page_type' => (string) $pageType,
                'page_subtype' => $pageSubType === null ? null : (string) $pageSubType,
                'serial_number' => '0',
                'page_code' => $pageCode,
                'file_path' => $typedPath,
                'typed_by' => self::SYSTEM_USER,
                'page_number' => $position,
                'scanning_id' => $scanningId,
                'qc_status' => 'pending',
                'qc_overridden' => 0,
                'has_qc_issues' => 0,
                'is_bcfc_page' => 0,
                'is_booklet_page' => 0,
                // Front cover is the only sane default: these are loose documents,
                // not pages lifted off the back of a physical file.
                'cover_type_id' => '1',
                'registry' => $registryName,
                'definition' => $position,
                'definition_code' => $definitionCode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Repoint the parcel row at the EDMS copy so both screens read the same
            // bytes. The original file stays on disk; only this pointer moves.
            $conn->table($table)->where('id', $row->id)->update([$column => $scanPath]);

            $conn->table(self::LOG_TABLE)->insert([
                'run_id' => $runId,
                'source_table' => $table,
                'source_id' => $row->id,
                'source_column' => $column,
                'original_path' => $stored,
                'scan_path' => $scanPath,
                'typed_path' => $typedPath,
                'scanning_id' => $scanningId,
                'file_indexing_id' => $indexing->id,
                'created_at' => now(),
            ]);

            $counts['copied']++;
        });
    }

    /**
     * The file number a parcel row belongs to.
     *
     * duplex_parcel_updates keeps its numbers on the child table; the source file
     * is the one the documents describe, so that is what the upload is filed under.
     */
    private function fileNumberFor($conn, string $table, array $spec, $row): ?string
    {
        if ($spec['file_column']) {
            return trim((string) ($row->{$spec['file_column']} ?? '')) ?: null;
        }

        if ($table === 'duplex_parcel_updates') {
            $number = $conn->table('duplex_parcel_update_files')
                ->where('duplex_parcel_update_id', $row->id)
                ->whereNotNull('source_file_no')
                ->where('source_file_no', '<>', '')
                ->orderBy('sequence')
                ->value('source_file_no');

            return $number ? trim((string) $number) : null;
        }

        return null;
    }

    /** Next free page position for a file, so migrated pages append rather than collide. */
    private function nextPosition(int $fileIndexingId): int
    {
        return $this->ingest->nextPosition($fileIndexingId);
    }

    /**
     * Page code in the register's own shape: {cover}-{type}-{subtype}-{serial}.
     *
     * The type and subtype segments are the initials of each word, capped at four
     * characters — the rule PageType::getCodeAttribute() and
     * PageSubType::getCodeAttribute() already implement, reproduced here so a
     * migrated page is indistinguishable from a hand-typed one.
     */
    private function pageCode(int $pageType, ?int $pageSubType, int $position): string
    {
        return $this->ingest->pageCode($pageType, $pageSubType, $position);
    }

    private function writeReport(array $log, bool $apply, string $runId): void
    {
        $path = storage_path('app/propid-remediation/parcel-uploads-edms-'
            . ($apply ? 'applied' : 'dryrun') . '-' . $runId . '.csv');
        @mkdir(dirname($path), 0775, true);

        if ($h = @fopen($path, 'w')) {
            fputcsv($h, ['source_table', 'source_id', 'file_no', 'column', 'original_path', 'action']);
            foreach ($log as $line) {
                fputcsv($h, $line);
            }
            fclose($h);
            $this->info('Report: ' . $path);
        }
    }

    /**
     * Undo a run: delete the rows it created and put the original path back.
     *
     * The copied files are left on disk. They are harmless once nothing points at
     * them, and deleting files during an undo is how an undo turns into a second
     * incident.
     */
    private function rollback($conn): int
    {
        if (!Schema::connection(self::CONNECTION)->hasTable(self::LOG_TABLE)) {
            $this->error('No log table — nothing to roll back.');

            return self::FAILURE;
        }

        $runId = $conn->table(self::LOG_TABLE)->max('run_id');
        $rows = $conn->table(self::LOG_TABLE)->where('run_id', $runId)->get();
        $undone = 0;

        foreach ($rows as $r) {
            $conn->transaction(function () use ($conn, $r, &$undone) {
                $conn->table('pagetypings')->where('scanning_id', $r->scanning_id)->delete();
                $conn->table('scannings')->where('id', $r->scanning_id)->delete();

                // Only restore the pointer if it still holds what this run wrote.
                $conn->table($r->source_table)
                    ->where('id', $r->source_id)
                    ->where($r->source_column, $r->scan_path)
                    ->update([$r->source_column => $r->original_path]);

                $undone++;
            });
        }

        $conn->table(self::LOG_TABLE)->where('run_id', $runId)->delete();

        $this->info("Rolled back run {$runId}: {$undone} upload(s) undone.");
        $this->comment('Copied files were left in the EDMS tree; nothing points at them now.');

        return self::SUCCESS;
    }

    private function ensureLogTable($conn): void
    {
        if (Schema::connection(self::CONNECTION)->hasTable(self::LOG_TABLE)) {
            return;
        }

        Schema::connection(self::CONNECTION)->create(self::LOG_TABLE, function ($t) {
            $t->bigIncrements('id');
            $t->string('run_id', 32)->index();
            $t->string('source_table', 100);
            $t->unsignedBigInteger('source_id');
            $t->string('source_column', 100);
            $t->string('original_path', 500)->nullable();
            $t->string('scan_path', 500)->nullable();
            $t->string('typed_path', 500)->nullable();
            $t->unsignedBigInteger('scanning_id')->nullable();
            $t->unsignedBigInteger('file_indexing_id')->nullable();
            $t->dateTime('created_at')->nullable();
        });

        $this->line('Created log table ' . self::LOG_TABLE . '.');
    }
}
