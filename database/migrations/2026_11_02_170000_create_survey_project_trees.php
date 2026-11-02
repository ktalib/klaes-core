<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash for economic trees at project level (user, 2026-11-02): on a Monetary Compensation
 * project, the economic trees of the Kano schedule with their stands and rate per stand;
 * amount = stands x rate. Name and Category are copied from the catalogue row so the line
 * still reads right if the catalogue changes. Additive: a new table only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('survey_project_trees')) {
            $schema->create('survey_project_trees', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('survey_project_id')->index();
                $t->unsignedBigInteger('survey_tree_type_id')->nullable();
                $t->string('tree_name', 255);
                $t->string('maturity', 30)->nullable();
                $t->integer('stands');
                $t->decimal('rate', 18, 2);
                $t->decimal('amount', 18, 2);
                $t->integer('sort_order')->default(0);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_project_trees');
    }
};
