<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan Description in the CAD 20 / CAD 13 form (change request 26-10-2026).
 *
 * Additive only. Each cadastral_pillars row becomes one boundary line: its beacon, then the
 * distance and bearing to the next beacon (the last line runs back to the first, as on CAD 20).
 * The beacon type goes in the existing pillar_type column. The plan description gets the
 * header fields the forms print: where the land is situated, the plan drawing number, the
 * plan it was copied from (CAD 13), dimensions for irregular plots and which form it prints on.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('cadastral_pillars', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('cadastral_pillars', 'distance_m')) {
                $table->decimal('distance_m', 12, 3)->nullable();
            }
            if (!$schema->hasColumn('cadastral_pillars', 'bearing_deg')) {
                $table->smallInteger('bearing_deg')->nullable();
            }
            if (!$schema->hasColumn('cadastral_pillars', 'bearing_min')) {
                $table->tinyInteger('bearing_min')->nullable();
            }
            if (!$schema->hasColumn('cadastral_pillars', 'bearing_sec')) {
                $table->decimal('bearing_sec', 5, 2)->nullable();
            }
            if (!$schema->hasColumn('cadastral_pillars', 'in_beacon_register')) {
                $table->boolean('in_beacon_register')->nullable();
            }
        });

        $schema->table('cadastral_plan_descriptions', function (Blueprint $table) use ($schema) {
            foreach ([
                'situated_at' => 255,
                'plan_drawing_no' => 100,
                'copied_from_plan_no' => 100,
                'description_form' => 10,
            ] as $column => $length) {
                if (!$schema->hasColumn('cadastral_plan_descriptions', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
            if (!$schema->hasColumn('cadastral_plan_descriptions', 'dimensions')) {
                $table->text('dimensions')->nullable();
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->table('cadastral_pillars', function (Blueprint $table) {
            $table->dropColumn(['distance_m', 'bearing_deg', 'bearing_min', 'bearing_sec', 'in_beacon_register']);
        });
        $schema->table('cadastral_plan_descriptions', function (Blueprint $table) {
            $table->dropColumn(['situated_at', 'plan_drawing_no', 'copied_from_plan_no', 'description_form', 'dimensions']);
        });
    }
};
