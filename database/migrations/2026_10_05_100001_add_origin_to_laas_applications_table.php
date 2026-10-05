<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tell a portal-filed application from one File Commissioning raised.
 *
 *  origin          'portal' (the applicant filled the form) or 'commissioning'
 *                  (a Land or OSS officer commissioned the file, and the
 *                  application was opened on the applicant's behalf).
 *  mls_file_no_id  The commissioning row it was raised from.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('laas_applications', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('laas_applications', 'origin')) {
                $table->string('origin', 20)->nullable()->after('stage');
            }
            if (!$schema->hasColumn('laas_applications', 'mls_file_no_id')) {
                $table->unsignedBigInteger('mls_file_no_id')->nullable()->after('file_number');
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('laas_applications', function (Blueprint $table) use ($schema) {
            foreach (['origin', 'mls_file_no_id'] as $column) {
                if ($schema->hasColumn('laas_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
