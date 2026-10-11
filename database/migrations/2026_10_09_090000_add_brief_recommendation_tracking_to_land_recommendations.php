<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brief Recommendation (the Permanent Secretary memo) tracking.
 *
 * Download: when, by whom, how many times, and which memo version
 * ('access-road' or 'compensated') was last taken.
 * Upload: the signed PDF, when and by whom. brief_rec_scanning_id links the
 * EDMS scannings row once the upload is filed through SCAN_UPLOAD, so Page
 * Typing sees it like any other module upload.
 *
 * All nullable with no defaults: a default on SQL Server creates a named
 * constraint that has to be found and dropped before the column can go.
 */
return new class extends Migration
{
    private array $columns = [
        'brief_rec_variant',
        'brief_rec_download_count',
        'brief_rec_downloaded_at',
        'brief_rec_downloaded_by',
        'brief_rec_path',
        'brief_rec_original_name',
        'brief_rec_scanning_id',
        'brief_rec_uploaded_at',
        'brief_rec_uploaded_by',
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('land_recommendations', function (Blueprint $table) use ($schema) {
            $add = fn (string $column) => !$schema->hasColumn('land_recommendations', $column);

            if ($add('brief_rec_variant'))        $table->string('brief_rec_variant', 20)->nullable();
            if ($add('brief_rec_download_count')) $table->integer('brief_rec_download_count')->nullable();
            if ($add('brief_rec_downloaded_at'))  $table->dateTime('brief_rec_downloaded_at')->nullable();
            if ($add('brief_rec_downloaded_by'))  $table->unsignedBigInteger('brief_rec_downloaded_by')->nullable();
            if ($add('brief_rec_path'))           $table->string('brief_rec_path', 500)->nullable();
            if ($add('brief_rec_original_name'))  $table->string('brief_rec_original_name', 255)->nullable();
            if ($add('brief_rec_scanning_id'))    $table->unsignedBigInteger('brief_rec_scanning_id')->nullable();
            if ($add('brief_rec_uploaded_at'))    $table->dateTime('brief_rec_uploaded_at')->nullable();
            if ($add('brief_rec_uploaded_by'))    $table->unsignedBigInteger('brief_rec_uploaded_by')->nullable();
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
