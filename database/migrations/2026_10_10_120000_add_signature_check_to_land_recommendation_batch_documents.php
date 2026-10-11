<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The signature check run on a signed Brief Recommendation (mother) as it is
 * uploaded: 'detected', 'not_detected' (uploaded anyway, after a warning) or
 * 'unchecked' (the check could not run), and how many signature-like ink marks
 * it found. See BriefRec.checkSignature in public/js/brief-recommendation.js.
 *
 * Nullable, no defaults: rows uploaded before the check existed stay NULL.
 */
return new class extends Migration
{
    private array $columns = ['signature_check', 'signature_marks'];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('land_recommendation_batch_documents', function (Blueprint $table) use ($schema) {
            $add = fn (string $column) => !$schema->hasColumn('land_recommendation_batch_documents', $column);

            if ($add('signature_check')) $table->string('signature_check', 20)->nullable();
            if ($add('signature_marks')) $table->integer('signature_marks')->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $existing = array_values(array_filter($this->columns, fn ($c) => $schema->hasColumn('land_recommendation_batch_documents', $c)));

        if ($existing) {
            $schema->table('land_recommendation_batch_documents', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
