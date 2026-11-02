<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmation Forms are a Survey page, and BUK/Zakara is the layout a form belongs to (user,
 * 2026-11-02). The table built under Legacy Systems the same day (empty) becomes
 * survey_confirmation_forms, and each form records its layout: a Survey project, or a name
 * typed under Other (e.g. "BUK/Zakara Layout"). layout_name is what prints on the form.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('buk_zakara_forms') && ! $schema->hasTable('survey_confirmation_forms')) {
            if (DB::connection('sqlsrv')->table('buk_zakara_forms')->exists()) {
                throw new RuntimeException('buk_zakara_forms has rows; move them by hand before renaming.');
            }
            $schema->rename('buk_zakara_forms', 'survey_confirmation_forms');
        }

        $schema->table('survey_confirmation_forms', function (Blueprint $t) use ($schema) {
            if (! $schema->hasColumn('survey_confirmation_forms', 'survey_project_id')) $t->unsignedBigInteger('survey_project_id')->nullable();
            if (! $schema->hasColumn('survey_confirmation_forms', 'layout_name')) $t->string('layout_name', 255)->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->table('survey_confirmation_forms', function (Blueprint $t) {
            $t->dropColumn(['survey_project_id', 'layout_name']);
        });
        $schema->rename('survey_confirmation_forms', 'buk_zakara_forms');
    }
};
