<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('survey_beacon_requests') || ! $schema->hasTable('survey_beacon_numbers')) {
            throw new RuntimeException('Beacon request and number tables must exist before usage details can be added.');
        }
        if (! $schema->hasColumn('survey_beacon_requests', 'request_type')) {
            $schema->table('survey_beacon_requests', fn (Blueprint $table) => $table->string('request_type', 10)->nullable());
        }

        foreach (['used', 'coord_x', 'coord_y', 'coord_z', 'location_details'] as $column) {
            if ($schema->hasColumn('survey_beacon_numbers', $column)) {
                continue;
            }

            $schema->table('survey_beacon_numbers', function (Blueprint $table) use ($column) {
                if ($column === 'used') {
                    $table->boolean($column)->nullable();
                } elseif (in_array($column, ['coord_x', 'coord_y', 'coord_z'], true)) {
                    $table->decimal($column, 18, 4)->nullable();
                } else {
                    $table->string($column, 500)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('survey_beacon_numbers')) {
            $columns = array_values(array_filter(
                ['used', 'coord_x', 'coord_y', 'coord_z', 'location_details'],
                fn (string $column) => $schema->hasColumn('survey_beacon_numbers', $column)
            ));
            if ($columns) {
                $schema->table('survey_beacon_numbers', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }
        if ($schema->hasColumn('survey_beacon_requests', 'request_type')) {
            $schema->table('survey_beacon_requests', fn (Blueprint $table) => $table->dropColumn('request_type'));
        }
    }
};
