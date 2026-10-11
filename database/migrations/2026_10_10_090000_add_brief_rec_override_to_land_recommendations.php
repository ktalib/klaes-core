<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brief Recommendation override: a Supper Admin approving a record before the
 * signed mother-file brief is uploaded. Who, when and why, on each record the
 * override approved.
 *
 * All nullable with no defaults, for the same reason as the tracking columns in
 * 2026_10_09_090000: a default on SQL Server is a named constraint that has to be
 * found and dropped before the column can go.
 */
return new class extends Migration
{
    private array $columns = [
        'brief_rec_override_by',
        'brief_rec_override_at',
        'brief_rec_override_reason',
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('land_recommendations', function (Blueprint $table) use ($schema) {
            $add = fn (string $column) => !$schema->hasColumn('land_recommendations', $column);

            if ($add('brief_rec_override_by'))     $table->unsignedBigInteger('brief_rec_override_by')->nullable();
            if ($add('brief_rec_override_at'))     $table->dateTime('brief_rec_override_at')->nullable();
            if ($add('brief_rec_override_reason')) $table->string('brief_rec_override_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $existing = array_values(array_filter($this->columns, fn ($c) => $schema->hasColumn('land_recommendations', $c)));

        if ($existing) {
            $schema->table('land_recommendations', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
