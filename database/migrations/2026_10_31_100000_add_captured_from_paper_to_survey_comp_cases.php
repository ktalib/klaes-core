<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lands keyed in from a paper list (Capture Compensation List) are existing records like the imported
// ones: no Generate OP on them (CR 30102026 item 32, user 2026-10-31). Null on every existing row.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('survey_comp_cases', function (Blueprint $t) {
            $t->boolean('captured_from_paper')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_comp_cases', function (Blueprint $t) {
            $t->dropColumn('captured_from_paper');
        });
    }
};
