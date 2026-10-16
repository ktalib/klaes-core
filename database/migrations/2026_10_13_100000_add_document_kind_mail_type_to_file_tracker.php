<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File Movement (Department): what a "Not a regular File/Mail" entry is.
 *
 *   document_kind  mail | file   (null for regular files)
 *   mail_type      Memo, Letter, Recommendation, or the "Other" text typed
 *
 * Own columns, not module_meta, so the type can be corrected (Edit details)
 * and filtered or reported on. Additive: two nullable columns and an index;
 * no existing row changes (no non-file entry had been logged when this ran).
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('file_tracker', 'document_kind')) {
            $schema->table('file_tracker', function (Blueprint $table) {
                $table->string('document_kind', 10)->nullable();
            });
        }
        if (!$schema->hasColumn('file_tracker', 'mail_type')) {
            $schema->table('file_tracker', function (Blueprint $table) {
                $table->string('mail_type', 100)->nullable();
                $table->index(['document_kind', 'mail_type'], 'file_tracker_document_kind_mail_type_index');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('file_tracker', 'mail_type')) {
            $schema->table('file_tracker', function (Blueprint $table) {
                $table->dropIndex('file_tracker_document_kind_mail_type_index');
                $table->dropColumn('mail_type');
            });
        }
        if ($schema->hasColumn('file_tracker', 'document_kind')) {
            $schema->table('file_tracker', function (Blueprint $table) {
                $table->dropColumn('document_kind');
            });
        }
    }
};
