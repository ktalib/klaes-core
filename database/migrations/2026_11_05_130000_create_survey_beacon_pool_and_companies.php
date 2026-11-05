<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CR 03112026 items 14-16: the pool of public beacon numbers still to be issued, the private survey
 * companies, and the company on a Beacon Register line. Additive: two new tables and two nullable
 * columns; nothing already stored changes. The tables start empty: survey:beacon-pool and
 * survey:seed-companies load them, each a dry run until --apply.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('survey_beacon_pool')) {
            $schema->create('survey_beacon_pool', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('prefix', 10);
                $t->integer('seq');
                $t->string('beacon_no', 30);
                $t->string('status', 20)->default('Available');
                $t->unsignedBigInteger('survey_beacon_register_id')->nullable();
                $t->dateTime('issued_at')->nullable();
                $t->timestamps();
                $t->unique(['prefix', 'seq'], 'ux_survey_beacon_pool_number');
                $t->index(['status', 'prefix', 'seq'], 'ix_survey_beacon_pool_next');
                $t->index('survey_beacon_register_id', 'ix_survey_beacon_pool_entry');
            });
        }

        if (! $schema->hasTable('survey_companies')) {
            $schema->create('survey_companies', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name', 255);
                $t->string('beacon_letters', 3)->nullable();
                $t->string('surveyor_name', 255)->nullable();
                $t->boolean('is_active')->default(true);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->unique('name', 'ux_survey_companies_name');
            });
        }

        if (! $schema->hasColumn('survey_beacon_register', 'company_id')) {
            $schema->table('survey_beacon_register', function (Blueprint $t) {
                $t->unsignedBigInteger('company_id')->nullable();
                $t->string('company_name', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_beacon_register', 'company_id')) {
            $schema->table('survey_beacon_register', function (Blueprint $t) {
                $t->dropColumn(['company_id', 'company_name']);
            });
        }
        $schema->dropIfExists('survey_companies');
        $schema->dropIfExists('survey_beacon_pool');
    }
};
