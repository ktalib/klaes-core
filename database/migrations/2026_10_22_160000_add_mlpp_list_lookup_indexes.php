<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indexes for the MLPP File Number Generator list (FileNumberController::getData).
 *
 * Each page enriches its 20 rows with a "newest pra row for this file number" lookup.
 * pra.mlsFNo had no index, so every row scanned all ~181k pra rows: ~0.8 s of the
 * ~1 s it took to fill a page. file_indexing_links.file_indexing_id is the same story
 * on a smaller table (~15k rows, ~80 ms).
 *
 * Additive only; each CREATE is guarded so re-running is harmless, and down() drops
 * only the indexes named here.
 */
return new class extends Migration
{
    private function targets(): array
    {
        return [
            'IX_pra_mlsFNo_id' => 'CREATE INDEX [IX_pra_mlsFNo_id] ON [pra] ([mlsFNo], [id]) INCLUDE ([prop_id], [temp_fileno])',
            'IX_file_indexing_links_file_indexing_id' => 'CREATE INDEX [IX_file_indexing_links_file_indexing_id] ON [file_indexing_links] ([file_indexing_id]) INCLUDE ([file_number])',
        ];
    }

    private function tableFor(string $index): string
    {
        return str_starts_with($index, 'IX_pra_') ? 'pra' : 'file_indexing_links';
    }

    public function up(): void
    {
        $conn = DB::connection('sqlsrv');

        foreach ($this->targets() as $index => $sql) {
            $table = $this->tableFor($index);

            $tableExists = (int) $conn->selectOne('SELECT COUNT(*) AS c FROM sys.tables WHERE name = ?', [$table])->c;
            $named = (int) $conn->selectOne(
                'SELECT COUNT(*) AS c FROM sys.indexes WHERE object_id = OBJECT_ID(?) AND name = ?',
                [$table, $index]
            )->c;

            if ($tableExists === 0 || $named > 0) {
                continue;
            }

            $conn->statement($sql);
        }
    }

    public function down(): void
    {
        $conn = DB::connection('sqlsrv');

        foreach (array_keys($this->targets()) as $index) {
            $table = $this->tableFor($index);
            $exists = (int) $conn->selectOne(
                'SELECT COUNT(*) AS c FROM sys.indexes WHERE object_id = OBJECT_ID(?) AND name = ?',
                [$table, $index]
            )->c;

            if ($exists > 0) {
                $conn->statement("DROP INDEX [{$index}] ON [{$table}]");
            }
        }
    }
};
