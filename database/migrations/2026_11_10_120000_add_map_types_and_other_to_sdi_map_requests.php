<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Survey Update item 5c (user, 2026-11-10): Hydrological, Geological, Engineering, As-Built and
 | Bathymetric maps join the map types, and a request may name an unlisted type ("Others (Specify)"),
 | kept in map_type_other with map_type_id left null. On the staff form and the portal form alike.
 | Additive: a type already listed (any case) is not added again; nothing existing is changed.
 */
return new class extends Migration
{
    private const NEW_TYPES = ['Hydrological Map', 'Geological Map', 'Engineering Map', 'As-Built Map', 'Bathymetric Map'];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('sdi_map_types') || ! $schema->hasTable('sdi_map_requests')) {
            throw new RuntimeException('sdi_map_types and sdi_map_requests must exist first.');
        }
        if (! $schema->hasColumn('sdi_map_requests', 'map_type_other')) {
            $schema->table('sdi_map_requests', fn (Blueprint $t) => $t->string('map_type_other', 150)->nullable());
        }

        $db = DB::connection('sqlsrv');
        $now = now();
        $order = (int) $db->table('sdi_map_types')->max('sort_order');
        foreach (self::NEW_TYPES as $name) {
            if ($db->table('sdi_map_types')->whereRaw('LOWER(LTRIM(RTRIM(name))) = ?', [mb_strtolower($name)])->exists()) {
                continue;
            }
            $db->table('sdi_map_types')->insert(['name' => $name, 'sort_order' => ++$order, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // The types stay (requests may point at them); only the column goes.
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('sdi_map_requests', 'map_type_other')) {
            $schema->table('sdi_map_requests', fn (Blueprint $t) => $t->dropColumn('map_type_other'));
        }
    }
};
