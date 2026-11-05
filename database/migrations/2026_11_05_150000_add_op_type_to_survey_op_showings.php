<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CR 03112026 item 1: the Compensation entry shows resettlement records only, so a showing carries its
 * OP's type. Additive and nullable: untyped counts as Direct Allocation, as it does for OPs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_op_showings', 'op_type')) {
            $schema->table('survey_op_showings', function (Blueprint $t) {
                $t->string('op_type', 30)->nullable()->index('ix_survey_op_showings_op_type');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_op_showings', 'op_type')) {
            $schema->table('survey_op_showings', function (Blueprint $t) {
                $t->dropIndex('ix_survey_op_showings_op_type');
                $t->dropColumn('op_type');
            });
        }
    }
};
