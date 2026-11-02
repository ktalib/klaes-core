<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record Plot Showing: who asked for the showing (user, 2026-11-02). The requester's name and
 * address, and whether the requester is the OP's allottee (the name is then the allottee's).
 * Additive: three nullable columns; older showings stay without a requester.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_op_showings', 'requester_name')) {
            $schema->table('survey_op_showings', function (Blueprint $t) {
                $t->boolean('requester_is_allottee')->nullable();
                $t->string('requester_name', 255)->nullable();
                $t->string('requester_address', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_op_showings', 'requester_name')) {
            $schema->table('survey_op_showings', function (Blueprint $t) {
                $t->dropColumn(['requester_is_allottee', 'requester_name', 'requester_address']);
            });
        }
    }
};
