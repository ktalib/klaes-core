<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Survey Update 5e (user, 2026-11-10): Record Plot Showing (New) uploads the Evidence of Payment.
 | Kept with the showing on the private disk, beside its photographs. Required on new entries only;
 | showings recorded before today, the Old Register and the Plot Confirmation app have none. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('survey_op_showings')) {
            throw new RuntimeException('survey_op_showings must exist first.');
        }
        $schema->table('survey_op_showings', function (Blueprint $t) use ($schema) {
            if (! $schema->hasColumn('survey_op_showings', 'payment_evidence_path')) $t->string('payment_evidence_path', 500)->nullable();
            if (! $schema->hasColumn('survey_op_showings', 'payment_evidence_name')) $t->string('payment_evidence_name', 255)->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach (['payment_evidence_path', 'payment_evidence_name'] as $c) {
            if ($schema->hasColumn('survey_op_showings', $c)) {
                $schema->table('survey_op_showings', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
