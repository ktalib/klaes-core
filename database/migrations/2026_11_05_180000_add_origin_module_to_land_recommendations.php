<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CR 03112026 item 10: Survey raises Land Recommendations and RofOs on the Lands table (user, 2026-11-05),
 * marked so each module's lists show only its own. Additive and nullable: NULL is Lands, as every row is today.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('land_recommendations', 'origin_module')) {
            $schema->table('land_recommendations', function (Blueprint $t) {
                $t->string('origin_module', 20)->nullable()->index('ix_land_recommendations_origin');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('land_recommendations', 'origin_module')) {
            $schema->table('land_recommendations', function (Blueprint $t) {
                $t->dropIndex('ix_land_recommendations_origin');
                $t->dropColumn('origin_module');
            });
        }
    }
};
