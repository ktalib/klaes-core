<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boundary Dispute types (earlier change list p.2; docs/KLAES Survey Module – Workflow_V02.md 5.2):
 * Property Boundary (private, land to land) or Public Boundary, LGA or Interstate.
 * Additive: one nullable column; the table had no rows when this was written.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('survey_boundary_disputes') && ! $schema->hasColumn('survey_boundary_disputes', 'boundary_type')) {
            $schema->table('survey_boundary_disputes', fn (Blueprint $t) => $t->string('boundary_type', 50)->nullable()->index());
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_boundary_disputes', 'boundary_type')) {
            $schema->table('survey_boundary_disputes', function (Blueprint $t) {
                $t->dropIndex(['boundary_type']);
                $t->dropColumn('boundary_type');
            });
        }
    }
};
