<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | CR 11112026-2 item 9a: the Computation Sheet / KMC Access farmers move to the main Survey tables
 | (survey:promote-computation-sheet). survey_farmers gets import_ref like the other Survey tables, so that
 | batch can be undone exactly. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_farmers', 'import_ref')) {
            $schema->table('survey_farmers', fn (Blueprint $t) => $t->string('import_ref', 50)->nullable()->index('ix_survey_farmers_import_ref'));
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_farmers', 'import_ref')) {
            $schema->table('survey_farmers', function (Blueprint $t) {
                $t->dropIndex('ix_survey_farmers_import_ref');
                $t->dropColumn('import_ref');
            });
        }
    }
};
