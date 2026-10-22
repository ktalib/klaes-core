<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How a dciv_file_no row came to exist, so the Generated DCIV Records table can
 * tell a real commission from a captured file:
 *   auto     - Generate card, Auto serial          -> COMMISSIONED
 *   manual   - Generate card, Manual serial        -> INDEXED FILE
 *   indexing - mirrored from File Indexing          -> INDEXED FILE
 *   NULL     - older rows where it was never saved -> shown as before
 *
 * Backfills only the unambiguous case: rows mirrored from File Indexing, whose
 * indexing row was created before the dciv_file_no row. Older Generate-card rows
 * stay NULL until the user confirms how to label them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('dciv_file_no', 'origin')) {
            $schema->table('dciv_file_no', function (Blueprint $table) {
                $table->string('origin', 20)->nullable();
            });
        }

        // Matched in PHP and written by id in short chunks: a single UPDATE joining
        // on UPPER(LTRIM(RTRIM())) scans file_indexings per row and holds locks on
        // dciv_file_no long enough to block the live page (klas has no snapshot isolation).
        $db = DB::connection('sqlsrv');
        $rows = $db->table('dciv_file_no')->whereNull('origin')->get(['id', 'full_file_number', 'created_at']);

        $firstIndexedAt = [];
        foreach ($rows->pluck('full_file_number')->filter()->map(fn ($n) => trim((string) $n))->unique()->chunk(500) as $chunk) {
            $db->table('file_indexings')
                ->whereIn('file_number', $chunk->values()->all())
                ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                ->orderBy('id')
                ->get(['file_number', 'created_at'])
                ->each(function ($f) use (&$firstIndexedAt) {
                    $key = strtoupper(trim((string) $f->file_number));
                    $firstIndexedAt[$key] = $firstIndexedAt[$key] ?? $f->created_at;
                });
        }

        $ids = $rows->filter(function ($r) use ($firstIndexedAt) {
            $at = $firstIndexedAt[strtoupper(trim((string) $r->full_file_number))] ?? null;
            // Both are 'Y-m-d H:i:s.vvv' strings; compared as strings to keep the
            // milliseconds, since a mirror usually lands in the same second.
            return $at && strcmp((string) $at, (string) $r->created_at) < 0;
        })->pluck('id');

        foreach ($ids->chunk(200) as $chunk) {
            $db->table('dciv_file_no')->whereIn('id', $chunk->values()->all())->whereNull('origin')->update(['origin' => 'indexing']);
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('dciv_file_no', 'origin')) {
            $schema->table('dciv_file_no', function (Blueprint $table) {
                $table->dropColumn('origin');
            });
        }
    }
};
