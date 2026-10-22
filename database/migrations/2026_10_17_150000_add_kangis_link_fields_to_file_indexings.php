<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KANGIS Block Indexing (user, 2026-10-17), on the LAND file's file_indexings row:
 *
 *   kangis_link_count     how many live KANGIS Registry records carry this land file
 *                         as their Related File No (NULL/0 = none). It may not pass 1
 *                         unless allow_multiple_kangis is set.
 *   allow_multiple_kangis "multiple KANGIS files to this land file" (NULL/0 = No),
 *                         set by the KANGIS Block Indexing box on a KANGIS index.
 *
 * Both nullable so the ALTER is metadata-only on this large live table; NULL reads
 * as 0 / No. Maintained by App\Services\FileIndexing\KangisBlockIndexing. Existing
 * rows are not backfilled (the multi-linked ones are being exported and treated
 * separately).
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_17_150000_add_kangis_link_fields_to_file_indexings.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        $s->table('file_indexings', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('file_indexings', 'kangis_link_count')) {
                $t->integer('kangis_link_count')->nullable();
            }
            if (! $s->hasColumn('file_indexings', 'allow_multiple_kangis')) {
                $t->boolean('allow_multiple_kangis')->nullable();
            }
        });
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');

        foreach (['kangis_link_count', 'allow_multiple_kangis'] as $column) {
            if ($s->hasColumn('file_indexings', $column)) {
                $s->table('file_indexings', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
