<?php

namespace App\Console\Commands;

use App\Models\BlindScanning;
use App\Services\Survey\TpLargeFormat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Large format scans of the layout plans into EDMS (user, 2026-11-06), filed as Survey's blind scans so they also
 * show on Blind Scan and on Survey's Doc-WARE. Each TP-... folder goes to Survey_Registry_Raw/<folder>/A3, as LF
 * pages always do, whatever folder they were scanned into. Nothing is overwritten: an identical page is skipped
 * and a different one with the same name is saved as "name (2)". Dry run by default.
 */
class SurveyImportTpLf extends Command
{
    private const TAG = 'TPLF_';

    protected $signature = 'survey:import-tp-lf {dir? : the folder holding the TP-... folders}
        {--skip=* : a page to leave out, as TP-KNUPDA-358E/A4/Scan0001.jpg}
        {--user=1 : recorded as the uploader}
        {--apply : copy the pages and record them}
        {--undo : remove only the pages this command filed}';

    protected $description = 'Bring the TP layout plan large format scans into EDMS (Survey_Registry_Raw/<folder>/A3).';

    public function handle(): int
    {
        return $this->option('undo') ? $this->undo() : $this->load();
    }

    private function load(): int
    {
        $source = rtrim(str_replace('\\', '/', (string) $this->argument('dir')), '/');
        if ($source === '' || ! is_dir($source)) {
            $this->error('Give the folder holding the TP-... folders.');

            return self::FAILURE;
        }

        $skip = array_map(fn ($s) => mb_strtolower(str_replace('\\', '/', $s)), (array) $this->option('skip'));
        $seen = [];
        $plan = [];
        foreach (glob($source . '/*', GLOB_ONLYDIR) as $dir) {
            $folder = basename($dir);
            if (! preg_match('/^TP-[A-Za-z0-9_\-.]+$/', $folder)) {
                $this->warn("Not a TP folder, left out: {$folder}");
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $f) {
                if (! $f->isFile() || ! in_array(strtolower($f->getExtension()), ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'webp'], true)) {
                    continue;
                }
                $relative = $folder . '/' . str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
                $md5 = md5_file($f->getPathname());
                if (in_array(mb_strtolower($relative), $skip, true)) {
                    $this->line("Left out as asked: {$relative}");
                    continue;
                }
                if (isset($seen[$md5])) {
                    $this->warn("Same picture as {$seen[$md5]}: {$relative}");
                }
                $seen[$md5] ??= $relative;
                $plan[] = ['folder' => $folder, 'from' => $f->getPathname(), 'name' => $f->getFilename(), 'md5' => $md5, 'size' => $f->getSize()];
            }
        }
        usort($plan, fn ($a, $b) => strnatcasecmp($a['folder'] . '/' . $a['name'], $b['folder'] . '/' . $b['name']));

        $disk = Storage::disk('public');
        $filed = 0;
        foreach ($plan as $p) {
            $target = $this->target($disk, $p);
            if ($target === null) {
                $this->line("Already in EDMS: {$p['folder']}/{$p['name']}");
                continue;
            }
            $this->line(($this->option('apply') ? 'Filed: ' : 'Would file: ') . $target);
            if (! $this->option('apply')) {
                continue;
            }

            $disk->makeDirectory(dirname($target));
            if (! copy($p['from'], $disk->path($target)) || md5_file($disk->path($target)) !== $p['md5']) {
                $disk->delete($target);
                $this->error("Copy failed, nothing recorded for it: {$p['from']}");

                return self::FAILURE;
            }
            $this->record($p, $target);
            $filed++;
        }

        $this->info($this->option('apply') ? "{$filed} page(s) filed." : count($plan) . ' page(s) found. Dry run: nothing changed; --apply files them.');

        return self::SUCCESS;
    }

    /** Where the page goes, or null when the same picture is already there. */
    private function target($disk, array $p): ?string
    {
        $dir = TpLargeFormat::ROOT . '/' . $p['folder'] . '/A3';
        foreach ($disk->exists($dir) ? $disk->files($dir) : [] as $held) {
            if (md5_file($disk->path($held)) === $p['md5']) {
                return null;
            }
        }

        $stem = pathinfo($p['name'], PATHINFO_FILENAME);
        $ext = pathinfo($p['name'], PATHINFO_EXTENSION);
        $target = "{$dir}/{$p['name']}";
        for ($n = 2; $disk->exists($target); $n++) {
            $target = "{$dir}/{$stem} ({$n}).{$ext}";
        }

        return $target;
    }

    /** The row Blind Scan writes for a page (BlindScanningController::saveFilesToDatabase), tagged so --undo can find it. */
    private function record(array $p, string $target): void
    {
        DB::connection('sqlsrv')->table('blind_scannings')->insert([
            'temp_file_id'      => self::TAG . microtime(true) . '_' . uniqid(),
            'file_number'       => $p['folder'],
            'local_pc_path'     => 'Survey_Registry_Raw/' . $p['folder'],
            'original_filename' => basename($target),
            'document_path'     => $target,
            'paper_size'        => 'A3',
            'document_type'     => 'Document',
            'a4_count'          => 0,
            'a3_count'          => 1,
            'total_pages'       => 1,
            'notes'             => 'TP layout plan, large format',
            'status'            => BlindScanning::STATUS_PENDING,
            'uploaded_by'       => (int) $this->option('user'),
            'registry_type'     => 'survey',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function undo(): int
    {
        $disk = Storage::disk('public');
        $rows = DB::connection('sqlsrv')->table('blind_scannings')->where('temp_file_id', 'like', self::TAG . '%')->get(['id', 'document_path']);
        foreach ($rows as $row) {
            if (! str_starts_with($row->document_path, TpLargeFormat::ROOT . '/TP-')) {
                continue;
            }
            $disk->delete($row->document_path);
            DB::connection('sqlsrv')->table('blind_scannings')->where('id', $row->id)->delete();
            $this->line("Removed: {$row->document_path}");

            // The folders go too, once this was all they held.
            foreach ([dirname($row->document_path), dirname($row->document_path, 2)] as $dir) {
                if ($disk->exists($dir) && ! $disk->allFiles($dir) && ! $disk->directories($dir)) {
                    $disk->deleteDirectory($dir);
                }
            }
        }
        $this->info($rows->count() . ' page(s) removed.');

        return self::SUCCESS;
    }
}
