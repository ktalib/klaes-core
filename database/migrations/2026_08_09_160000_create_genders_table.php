<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lookup table for the gender dimension, so the four canonical values stop being
 * copy-pasted into every capture form (MLS file number generation, ST commissioning,
 * file indexing, the commission-fileno modal, ...).
 *
 * The stored value on the consuming tables (file_indexings.gender, mls_file_no.gender,
 * st_file_numbers.gender, ...) is the NAME, not this row's id — the columns are
 * varchars and App\Services\GenderNormalizer::CANON is the authority on what may go
 * in them. This table only decides what a user is offered and in what order; `code`
 * is here for display shorthand (M/F/C/J), never for storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('genders')) {
            return;
        }

        Schema::connection('sqlsrv')->create('genders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('The value stored on the consuming tables — GenderNormalizer::CANON');
            $table->string('code', 5)->nullable()->comment('Display shorthand only (M/F/C/J); never stored as the gender value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('genders');
    }
};
