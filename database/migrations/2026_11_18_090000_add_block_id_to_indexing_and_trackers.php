<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Block ID (user, 2026-11-18).
 *
 * file_indexings.block_id  system-generated ID of a block (Land Block Indexing, or a
 *                          land file holding several KANGIS files). Set on the block's
 *                          land file row when it is indexed as a block. A block is
 *                          tracked under this ID instead of the file's own tracking ID.
 * file_tracker.block_id    the block a tracker was opened for.
 * file_tracker.block_members  JSON list of the block's files ticked for this movement
 *                          (keys from BlockTrackingGroup); null = the whole block.
 *
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('file_indexings', 'block_id')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->string('block_id', 40)->nullable();
            });
        }

        if (!$schema->hasColumn('file_tracker', 'block_id')) {
            $schema->table('file_tracker', function (Blueprint $table) {
                $table->string('block_id', 40)->nullable();
            });
        }

        if (!$schema->hasColumn('file_tracker', 'block_members')) {
            $schema->table('file_tracker', function (Blueprint $table) {
                $table->text('block_members')->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach ([['file_tracker', 'block_members'], ['file_tracker', 'block_id'], ['file_indexings', 'block_id']] as [$t, $c]) {
            if ($schema->hasColumn($t, $c)) {
                $schema->table($t, fn (Blueprint $table) => $table->dropColumn($c));
            }
        }
    }
};
