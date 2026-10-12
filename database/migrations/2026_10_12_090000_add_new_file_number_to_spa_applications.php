<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPAS: the new file number a contravention ends in (2026-10-12).
 *
 * Once the bill is settled the record is handed on for a new file number by
 * one of two routes — a Change of Purpose (existing title: Direct Allocation
 * or an existing Conversion) or a New Conversion (no record before). These
 * columns hold which route, the Change of Purpose it created, and the number
 * once issued. New columns only; nothing existing changes.
 */
return new class extends Migration {
    private const COLUMNS = ['new_file_route', 'cop_application_id', 'new_file_number', 'new_file_at'];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('spa_applications', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('spa_applications', 'new_file_route')) {
                $table->string('new_file_route', 30)->nullable();          // change_of_purpose | new_conversion
            }
            if (! $schema->hasColumn('spa_applications', 'cop_application_id')) {
                $table->unsignedBigInteger('cop_application_id')->nullable();
            }
            if (! $schema->hasColumn('spa_applications', 'new_file_number')) {
                $table->string('new_file_number', 255)->nullable();
            }
            if (! $schema->hasColumn('spa_applications', 'new_file_at')) {
                $table->dateTime('new_file_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach (self::COLUMNS as $column) {
            if ($schema->hasColumn('spa_applications', $column)) {
                $schema->table('spa_applications', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
