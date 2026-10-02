<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('sltr_recommendations') && !$schema->hasColumn('sltr_recommendations', 'revision_period')) {
            $schema->table('sltr_recommendations', function (Blueprint $table) {
                $table->unsignedInteger('revision_period')->nullable()->after('term');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('sltr_recommendations') && $schema->hasColumn('sltr_recommendations', 'revision_period')) {
            $schema->table('sltr_recommendations', function (Blueprint $table) {
                $table->dropColumn('revision_period');
            });
        }
    }
};
