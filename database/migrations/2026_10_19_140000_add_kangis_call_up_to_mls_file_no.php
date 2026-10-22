<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recertification calls a land file up into KANGIS. The land file's commissioning
 * row records the KANGIS file number, when it was called up, and the
 * recertification application that did it. Additive and nullable: no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('mls_file_no', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('mls_file_no', 'kangis_file_no')) {
                $table->string('kangis_file_no', 50)->nullable();
            }
            if (!$schema->hasColumn('mls_file_no', 'kangis_called_up_at')) {
                $table->dateTime('kangis_called_up_at')->nullable();
            }
            if (!$schema->hasColumn('mls_file_no', 'recertification_application_id')) {
                $table->unsignedBigInteger('recertification_application_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (['kangis_file_no', 'kangis_called_up_at', 'recertification_application_id'] as $column) {
            if ($schema->hasColumn('mls_file_no', $column)) {
                $schema->table('mls_file_no', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
