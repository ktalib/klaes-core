<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CR 03112026 items 4-7, Capture Farm: Section No (per surveyor), Farmer ID (per person), Farm ID (per
 * farm, kept across sections) and Farm No (within the section). Additive; existing farmers get no IDs
 * until the user says so.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('survey_farmers')) {
            $schema->create('survey_farmers', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('farmer_id_no', 20)->unique('ux_survey_farmers_id_no');
                $t->string('full_name', 255)->index('ix_survey_farmers_name');
                $t->string('phone', 50)->nullable()->index('ix_survey_farmers_phone');
                $t->string('nin', 20)->nullable()->index('ix_survey_farmers_nin');
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! $schema->hasColumn('survey_comp_cases', 'section_no')) {
            $schema->table('survey_comp_cases', fn (Blueprint $t) => $t->integer('section_no')->nullable());
        }

        if (! $schema->hasColumn('survey_beneficiaries', 'survey_farmer_id')) {
            $schema->table('survey_beneficiaries', function (Blueprint $t) {
                $t->unsignedBigInteger('survey_farmer_id')->nullable()->index('ix_survey_beneficiaries_farmer');
                $t->string('farm_id_no', 20)->nullable()->index('ix_survey_beneficiaries_farm');
                $t->integer('farm_no')->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_beneficiaries', 'survey_farmer_id')) {
            $schema->table('survey_beneficiaries', function (Blueprint $t) {
                $t->dropIndex('ix_survey_beneficiaries_farmer');
                $t->dropIndex('ix_survey_beneficiaries_farm');
                $t->dropColumn(['survey_farmer_id', 'farm_id_no', 'farm_no']);
            });
        }
        if ($schema->hasColumn('survey_comp_cases', 'section_no')) {
            $schema->table('survey_comp_cases', fn (Blueprint $t) => $t->dropColumn('section_no'));
        }
        $schema->dropIfExists('survey_farmers');
    }
};
