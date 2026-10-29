<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Related File (Physical File is Sighted)" on File Indexing (user, 2026-10-28).
 *
 * One answer per related file on an indexing record: Yes (sighted) or No, which
 * sets NPF (No Physical File). npf is stored alongside for history but is always
 * the inverse of physical_sighted. An NPF pair is not treated as "kept inside"
 * the KANGIS / Land counterpart by Quick Search or the KANGIS land-file lock.
 * A record with no row for a related file is unanswered (indexed before this
 * existed) and keeps the old behaviour. Every change is logged. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable('file_indexing_related_sightings')) {
            $schema->create('file_indexing_related_sightings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('file_indexing_id');
                $t->string('file_number', 255)->nullable();
                $t->string('related_file_number', 255);
                // KangisBlockIndexing::key(): upper-case, whitespace removed.
                $t->string('related_key', 255);
                $t->boolean('physical_sighted');
                $t->boolean('npf');
                $t->string('cover_missing', 500)->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();

                $t->unique(['file_indexing_id', 'related_key'], 'fi_related_sightings_record_related_unique');
                $t->index(['related_key', 'npf'], 'fi_related_sightings_related_npf_index');
            });
        }

        if (!$schema->hasTable('file_indexing_related_sighting_log')) {
            $schema->create('file_indexing_related_sighting_log', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('file_indexing_id')->index();
                $t->string('file_number', 255)->nullable();
                $t->string('related_file_number', 255);
                // set (first answer), changed, removed (related file taken off the record)
                $t->string('action', 20);
                $t->boolean('old_physical_sighted')->nullable();
                $t->boolean('new_physical_sighted')->nullable();
                $t->boolean('old_npf')->nullable();
                $t->boolean('new_npf')->nullable();
                $t->string('old_cover_missing', 500)->nullable();
                $t->string('new_cover_missing', 500)->nullable();
                $t->unsignedBigInteger('changed_by')->nullable();
                $t->string('changed_by_name', 255)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('file_indexing_related_sighting_log');
        Schema::connection('sqlsrv')->dropIfExists('file_indexing_related_sightings');
    }
};
