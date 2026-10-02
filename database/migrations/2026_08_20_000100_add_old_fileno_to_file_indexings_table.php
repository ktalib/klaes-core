<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * file_indexings.old_fileno — the indexing-side mirror of mls_file_no.old_fileno.
 *
 * related_fileno on this table is a JSON array of *sibling* files. An old file
 * number is a different relationship (the same physical file under a previous
 * number), so it gets its own column rather than being folded into that array,
 * exactly as the Edit modal's checkbox already separates the two.
 *
 * Full history lives in old_file_numbers; this column holds the current value so
 * indexing screens and searches can read it without a join.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('file_indexings', 'old_fileno')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->string('old_fileno', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('file_indexings', 'old_fileno')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->dropColumn('old_fileno');
            });
        }
    }
};
