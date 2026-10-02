<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registry digital source tables.
 *
 *   registry_sources       — lookup of the on-disk registries (SLTR, Cadastral,
 *                            KANGIS, Physical Planning).
 *   registry_file_folders  — one row per file folder discovered inside a registry
 *                            (folder name == file number).
 *   registry_file_documents— one row per scanned image/document inside a folder.
 *
 * All on the sqlsrv connection (same as physical_registries / file_indexings).
 * Guarded so the migration is safe to re-run.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('registry_sources')) {
            $schema->create('registry_sources', function (Blueprint $table) {
                $table->id();
                $table->string('name', 191);
                $table->string('code', 32)->unique();
                $table->string('folder', 191);            // directory name under the upload root
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('registry_file_folders')) {
            $schema->create('registry_file_folders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('registry_source_id');
                $table->string('file_number', 191);       // folder name == file number
                $table->string('relative_path', 500);     // path relative to the public disk
                $table->unsignedInteger('document_count')->default(0);
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['registry_source_id', 'file_number'], 'reg_folder_source_fileno_uq');
                $table->index('file_number', 'reg_folder_fileno_idx');
            });
        }

        if (! $schema->hasTable('registry_file_documents')) {
            $schema->create('registry_file_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('registry_file_folder_id');
                $table->string('category', 191)->nullable();   // sub-folder (e.g. "A4")
                $table->string('filename', 255);
                $table->string('relative_path', 500);          // path relative to the public disk
                $table->string('extension', 16)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->timestamps();

                // Idempotency key: a given path is imported once per folder.
                $table->unique(['registry_file_folder_id', 'relative_path'], 'reg_doc_folder_path_uq');
                $table->index('registry_file_folder_id', 'reg_doc_folder_idx');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        $schema->dropIfExists('registry_file_documents');
        $schema->dropIfExists('registry_file_folders');
        $schema->dropIfExists('registry_sources');
    }
};
