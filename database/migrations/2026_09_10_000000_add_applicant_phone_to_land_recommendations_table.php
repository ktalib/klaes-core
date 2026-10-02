<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('land_recommendations') && !$schema->hasColumn('land_recommendations', 'applicant_phone')) {
            $schema->table('land_recommendations', function (Blueprint $table) {
                $table->string('applicant_phone', 30)->nullable()->after('applicant_address');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('land_recommendations') && $schema->hasColumn('land_recommendations', 'applicant_phone')) {
            $schema->table('land_recommendations', function (Blueprint $table) {
                $table->dropColumn('applicant_phone');
            });
        }
    }
};
