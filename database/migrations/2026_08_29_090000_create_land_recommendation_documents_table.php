<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The already-approved recommendation letter, scanned, for one file.
 *
 * A file whose Occupancy Permit holder differs from the File Indexing name has
 * already been through recommendation once — the letter exists, signed and
 * approved, and it will not be submitted for approval a second time. The capture
 * form therefore stops generating a NEW recommendation for such a file and asks
 * for that existing letter instead, and approval waits until it is on file.
 *
 * Two changes, one migration, because neither is usable without the other:
 *
 *   land_recommendation_documents            the scan, one row per recommendation
 *   land_recommendations.is_existing_recommendation
 *                                            the flag that says this record needs one
 *
 * The flag has to be STORED rather than re-derived at approval time. Pressing Match
 * writes the missing Transfer of Title, which is exactly the row whose absence made
 * the file qualify — so a moment later the file no longer meets the condition, and a
 * gate that re-asked the question would find nothing to enforce and let the approval
 * through without the letter.
 *
 * Keyed on land_recommendation_id, uniquely: one current letter per recommendation.
 * A re-upload replaces the row and deletes the scan it replaced, the same way the
 * subdivision batch document does.
 *
 * NOTE FOR DEPLOYMENT: artisan's migrations ledger lives in MySQL while these tables
 * are created on sqlsrv. The `migrations` table visible on sqlsrv is stale and must
 * not be trusted. Ship the paired SQL files in database/sql/ alongside this
 * migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('land_recommendation_documents')) {
            $schema->create('land_recommendation_documents', function (Blueprint $table) {
                $table->bigIncrements('id');

                // One current letter per recommendation; a re-upload updates in place.
                $table->unsignedBigInteger('land_recommendation_id')->unique();

                // Denormalised so the row still reads on its own if the recommendation
                // is later renumbered or removed.
                $table->string('file_number', 100)->nullable();

                // Relative to the 'public' disk (storage/app/public), as every other
                // upload in this application stores it.
                $table->string('path', 500);
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();

                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->dateTime('uploaded_at')->nullable();

                $table->timestamps();
            });
        }

        if (! $schema->hasColumn('land_recommendations', 'is_existing_recommendation')) {
            $schema->table('land_recommendations', function (Blueprint $table) {
                $table->boolean('is_existing_recommendation')->default(false);
            });
        }

        // The transfer that Match wrote, so the record says what was done to the file
        // on its behalf. Nullable: only files that went through Match carry one.
        if (! $schema->hasColumn('land_recommendations', 'op_match_tot_pra_id')) {
            $schema->table('land_recommendations', function (Blueprint $table) {
                $table->unsignedBigInteger('op_match_tot_pra_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->dropIfExists('land_recommendation_documents');

        if ($schema->hasColumn('land_recommendations', 'is_existing_recommendation')) {
            $schema->table('land_recommendations', function (Blueprint $table) {
                $table->dropColumn('is_existing_recommendation');
            });
        }

        if ($schema->hasColumn('land_recommendations', 'op_match_tot_pra_id')) {
            $schema->table('land_recommendations', function (Blueprint $table) {
                $table->dropColumn('op_match_tot_pra_id');
            });
        }
    }
};
