<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Survey settings under Configurable Entities > Survey (CR 30102026, user 2026-10-31):
//   - sharing ratios (item 11): picked per project; the general one stays 50:50;
//   - Classmark ranges (item 8): turn the decimal part of the creatable plots into full / half / quarter plots;
//   - land rates (item 5): the rate per hectare / m2 that fills a land line in the Monetary Compensation calculator;
//   - beacon prefixes (Issue Beacon No).
// survey_projects.farmer_share_pct keeps the ratio a project picked (null = 50, the general one), so editing
// the list later never changes an existing project. Additive only.
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        $s->create('survey_sharing_ratios', function (Blueprint $t) {
            $t->id();
            $t->unsignedTinyInteger('farmer_pct');
            $t->unsignedTinyInteger('govt_pct');
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });

        $s->create('survey_classmarks', function (Blueprint $t) {
            $t->id();
            $t->decimal('from_fraction', 4, 2);
            $t->decimal('to_fraction', 4, 2);
            $t->decimal('plots', 4, 2);
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });

        $s->create('survey_land_rates', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->decimal('rate', 18, 2);
            $t->string('unit', 10);   // hectare | sqm
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });

        $s->create('survey_beacon_prefixes', function (Blueprint $t) {
            $t->id();
            $t->string('prefix', 20);
            $t->string('description', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });

        $s->table('survey_projects', function (Blueprint $t) {
            $t->unsignedTinyInteger('farmer_share_pct')->nullable();
        });

        $now = now();
        $db = DB::connection('sqlsrv');
        // 50:50 is the general ratio; the others are the ones named in the discussion.
        foreach ([[50, 50, true], [40, 60, false], [60, 40, false], [30, 70, false], [70, 30, false]] as [$f, $g, $d]) {
            $db->table('survey_sharing_ratios')->insert(['farmer_pct' => $f, 'govt_pct' => $g, 'is_default' => $d, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        // Our first Classmark (user, 2026-10-31), until Survey gives theirs.
        foreach ([[0.00, 0.09, 0], [0.10, 0.24, 0.25], [0.25, 0.59, 0.5], [0.60, 0.99, 1]] as [$from, $to, $p]) {
            $db->table('survey_classmarks')->insert(['from_fraction' => $from, 'to_fraction' => $to, 'plots' => $p, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        // The first beacon prefixes (WhatsApp, 31 Oct). No land rate is seeded: there is no real rate yet.
        foreach (['PBB', 'PBD', 'AAA', 'ABC'] as $p) {
            $db->table('survey_beacon_prefixes')->insert(['prefix' => $p, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->table('survey_projects', fn (Blueprint $t) => $t->dropColumn('farmer_share_pct'));
        foreach (['survey_beacon_prefixes', 'survey_land_rates', 'survey_classmarks', 'survey_sharing_ratios'] as $table) {
            $s->dropIfExists($table);
        }
    }
};
