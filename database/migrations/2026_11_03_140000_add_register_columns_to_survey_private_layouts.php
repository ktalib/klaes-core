<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Private Layout from the Ministry's "List of Private Layout and their Locations" (user, 2026-11-03:
 * LAYOUT PLANS-1.xlsx). The list gives S/N, File/Applicant Name, Layout No., Location and Year of Approval:
 *   register_sn    the S/N in that list
 *   location_text  the LOCATION as written (free text; the LGA is set only where the text names one)
 *   approval_year  the YEAR OF APPROVAL (a year only, no date)
 *   check_note     what is missing or odd in the list's row, to check against the plan
 *   source         layout_plans_list (the list) or NULL (added on the page)
 * The list has no owner or developer, and some rows no name, so layout_name and owner_name may be empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->table('survey_private_layouts', function (Blueprint $t) use ($schema) {
            if (! $schema->hasColumn('survey_private_layouts', 'register_sn')) $t->integer('register_sn')->nullable()->index();
            if (! $schema->hasColumn('survey_private_layouts', 'location_text')) $t->string('location_text', 500)->nullable();
            if (! $schema->hasColumn('survey_private_layouts', 'approval_year')) $t->smallInteger('approval_year')->nullable();
            if (! $schema->hasColumn('survey_private_layouts', 'check_note')) $t->string('check_note', 500)->nullable();
            if (! $schema->hasColumn('survey_private_layouts', 'source')) $t->string('source', 40)->nullable()->index();
        });
        DB::connection('sqlsrv')->statement('ALTER TABLE survey_private_layouts ALTER COLUMN layout_name NVARCHAR(255) NULL');
        DB::connection('sqlsrv')->statement('ALTER TABLE survey_private_layouts ALTER COLUMN owner_name NVARCHAR(255) NULL');
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->table('survey_private_layouts', function (Blueprint $t) {
            $t->dropIndex(['register_sn']);
            $t->dropIndex(['source']);
            $t->dropColumn(['register_sn', 'location_text', 'approval_year', 'check_note', 'source']);
        });
    }
};
