<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CR-11112026-2 item 12: load a transcribed page set of the handwritten Cadastral file register into the
 * staging table cadastral_register_ocr_rows (user, 2026-11-12: staging only, no workflow backfill).
 * The source is a text file (format in its header, e.g. storage/app/cadastral/register-ocr/register-photos-20261112.txt).
 * Dry run without --commit; --undo=<ref> removes a load.
 */
class CadastralStageRegisterOcr extends Command
{
    protected $signature = 'cadastral:stage-register-ocr
        {file? : The transcription text file}
        {--ref= : Import ref (default REG-<today>-<n>)}
        {--commit : Write the rows; without this nothing is written}
        {--undo= : Remove the rows of this import ref}';

    protected $description = 'Stage the transcribed Cadastral file register (photos) for checking';

    public function handle(): int
    {
        $db = DB::connection('sqlsrv');

        if ($undo = $this->option('undo')) {
            $n = $db->table('cadastral_register_ocr_rows')->where('import_ref', $undo)->delete();
            $this->info("Removed $n rows of $undo.");

            return self::SUCCESS;
        }

        $file = $this->argument('file');
        if (! $file || ! is_file($file)) {
            $this->error('Give the transcription file.');

            return self::FAILURE;
        }

        $rows = $this->parse($file);
        $ref = $this->option('ref') ?: $this->nextRef($db);

        // Match each number to its indexed file (file_indexings.file_number, then the (T) temp number).
        $numbers = collect($rows)->pluck('file_no')->filter()->unique()->values();
        $found = collect();
        foreach ($numbers->chunk(500) as $chunk) {
            $found = $found->merge($db->table('file_indexings')->whereIn('file_number', $chunk->all())
                ->orderBy('id')->get(['id', 'file_number'])->unique('file_number')->pluck('id', 'file_number'));
        }

        $byDate = collect($rows)->groupBy(fn ($r) => $r['entry_date'] ?? '—');
        $this->table(['Date', 'Lines', 'Indexed', 'Not indexed', 'To check', 'Struck through'], $byDate->map(fn ($g, $d) => [
            $d, $g->count(), $g->filter(fn ($r) => $found->has($r['file_no']))->count(),
            $g->reject(fn ($r) => $found->has($r['file_no']))->count(), $g->where('needs_check', true)->count(), $g->where('crossed_out', true)->count(),
        ])->values()->all());
        $missing = collect($rows)->reject(fn ($r) => $found->has($r['file_no']))->pluck('file_no')->unique()->values();
        $this->line(count($rows) . ' lines; ' . $missing->count() . ' numbers not in File Indexing: ' . $missing->implode(', '));

        if (! $this->option('commit')) {
            $this->warn("Dry run ($ref): nothing written. Add --commit to stage them.");

            return self::SUCCESS;
        }
        if ($db->table('cadastral_register_ocr_rows')->where('import_ref', $ref)->exists()) {
            $this->error("$ref is already loaded. Undo it first or give another --ref.");

            return self::FAILURE;
        }

        $now = now();
        $insert = array_map(fn ($r) => $r + [
            'import_ref' => $ref,
            'file_indexing_id' => $found->get($r['file_no']),
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);
        $db->transaction(function () use ($db, $insert) {
            foreach (array_chunk($insert, 100) as $chunk) {
                $db->table('cadastral_register_ocr_rows')->insert($chunk);
            }
        });
        $this->info(count($insert) . " lines staged as $ref. Undo: --undo=$ref");

        return self::SUCCESS;
    }

    /** One array per register line, in page order. */
    private function parse(string $file): array
    {
        $rows = [];
        $page = 0;
        $photo = null;
        $line = 0;
        $date = null;
        $inferred = false;
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $raw) {
            $text = trim($raw);
            if ($text === '' || str_starts_with($text, '#')) {
                continue;
            }
            if (preg_match('/^=\s*(\d+)/', $text, $m)) {
                $page = (int) $m[1];
                $photo = "cadastral/register-ocr/photos/page-{$page}.jpeg";
                $line = 0;
                continue;
            }
            if (preg_match('/^@(~?)\s*(\d{2}\/\d{2}\/\d{4})/', $text, $m)) {
                $date = Carbon::createFromFormat('d/m/Y', $m[2])->format('Y-m-d');
                $inferred = $m[1] === '~';
                continue;
            }
            $parts = array_map('trim', explode('|', $text)) + ['', '', '', '', ''];
            [$written, $lf, $dest, $flags, $note] = $parts;
            $line++;
            $rows[] = [
                'page_no' => $page,
                'line_no' => $line,
                'photo_path' => $photo,
                'entry_date' => $date,
                'date_inferred' => $inferred,
                'file_no_written' => $written,
                'file_no' => $this->normalise($written),
                'lf' => $lf === '' ? null : $lf === '1',
                'destination' => $dest !== '' ? $dest : null,
                'crossed_out' => str_contains($flags, 'X'),
                'needs_check' => str_contains($flags, '?'),
                'note' => $note !== '' ? mb_substr($note, 0, 500) : null,
            ];
        }

        return $rows;
    }

    /** RES/89/303 -> RES-1989-303; SLTR/213284 -> SLTR-213284. A 2-digit year is 19xx. */
    private function normalise(string $written): string
    {
        $parts = array_values(array_filter(array_map('trim', explode('/', strtoupper($written))), fn ($p) => $p !== ''));
        $n = count($parts);
        if ($n >= 3 && preg_match('/^\d{2}$/', $parts[$n - 2])) {
            $parts[$n - 2] = '19' . $parts[$n - 2];
        }

        return implode('-', $parts);
    }

    private function nextRef($db): string
    {
        $base = 'REG-' . now()->format('Ymd') . '-';
        $n = 1;
        while ($db->table('cadastral_register_ocr_rows')->where('import_ref', $base . $n)->exists()) {
            $n++;
        }

        return $base . $n;
    }
}
