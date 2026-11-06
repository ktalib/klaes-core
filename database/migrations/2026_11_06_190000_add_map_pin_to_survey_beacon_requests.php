<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The pin a surveyor drops on the map on the portal's Request Beacons form (user, 2026-11-06).
 * Nullable: the pin is optional, and requests from a Cadastral I to S have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (['latitude', 'longitude'] as $column) {
            if (! $schema->hasColumn('survey_beacon_requests', $column)) {
                $schema->table('survey_beacon_requests', fn (Blueprint $t) => $t->decimal($column, 10, 7)->nullable());
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (['latitude', 'longitude'] as $column) {
            if ($schema->hasColumn('survey_beacon_requests', $column)) {
                $schema->table('survey_beacon_requests', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
