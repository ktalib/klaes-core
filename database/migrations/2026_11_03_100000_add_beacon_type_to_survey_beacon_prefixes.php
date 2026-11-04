<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Beacon prefixes belong to a beacon type (user, 2026-11-03): PBB and PBD are Government, AAA and
 * ABC are Private. Issue Beacon No offers only the chosen type's prefixes. Additive: a new nullable
 * column (NULL = offered for both types); only the four seeded prefixes are given their type.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_beacon_prefixes', 'beacon_type')) {
            $schema->table('survey_beacon_prefixes', function (Blueprint $t) {
                $t->string('beacon_type', 20)->nullable();
            });
        }

        $db = DB::connection('sqlsrv');
        foreach (['PBB' => 'Government', 'PBD' => 'Government', 'AAA' => 'Private', 'ABC' => 'Private'] as $prefix => $type) {
            $db->table('survey_beacon_prefixes')->whereRaw('UPPER(prefix) = ?', [$prefix])->whereNull('beacon_type')
                ->update(['beacon_type' => $type, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_beacon_prefixes', 'beacon_type')) {
            $schema->table('survey_beacon_prefixes', function (Blueprint $t) {
                $t->dropColumn('beacon_type');
            });
        }
    }
};
