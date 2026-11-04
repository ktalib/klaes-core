<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Guarantor's Form (Ministry of Land and Physical Planning) is the second step of capturing a Confirmation
 * Form (user, 2026-11-03), and prints from the form's row menu. The guarantor is the Committee Chairman; the
 * applicant is the form's land owner (or its vendor / agent, guarantor_for), whose details the form already holds.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'guarantor_for', 'guarantor_name', 'guarantor_home_address', 'guarantor_office_address', 'guarantor_occupation',
        'guarantor_rank', 'guarantor_phone', 'guarantor_email', 'guarantor_bvn', 'guarantor_committee', 'guarantor_lga',
        'guarantor_id_type', 'guarantor_id_type_other', 'guarantor_passport_path', 'guarantor_id_path', 'guarantor_signed',
        'guarantor_date', 'guarantor_stamped', 'guarantor_reg_ref', 'guarantor_verified_by', 'guarantor_verified_date',
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $has = fn ($c) => $schema->hasColumn('survey_confirmation_forms', $c);
        $schema->table('survey_confirmation_forms', function (Blueprint $t) use ($has) {
            if (! $has('guarantor_for')) $t->string('guarantor_for', 10)->nullable();             // owner | vendor
            if (! $has('guarantor_name')) $t->string('guarantor_name', 255)->nullable();
            if (! $has('guarantor_home_address')) $t->string('guarantor_home_address', 500)->nullable();
            if (! $has('guarantor_office_address')) $t->string('guarantor_office_address', 500)->nullable();
            if (! $has('guarantor_occupation')) $t->string('guarantor_occupation', 150)->nullable();
            if (! $has('guarantor_rank')) $t->string('guarantor_rank', 150)->nullable();
            if (! $has('guarantor_phone')) $t->string('guarantor_phone', 60)->nullable();
            if (! $has('guarantor_email')) $t->string('guarantor_email', 255)->nullable();
            if (! $has('guarantor_bvn')) $t->string('guarantor_bvn', 11)->nullable();
            if (! $has('guarantor_committee')) $t->string('guarantor_committee', 255)->nullable();
            if (! $has('guarantor_lga')) $t->string('guarantor_lga', 100)->nullable();
            if (! $has('guarantor_id_type')) $t->string('guarantor_id_type', 60)->nullable();
            if (! $has('guarantor_id_type_other')) $t->string('guarantor_id_type_other', 100)->nullable();
            if (! $has('guarantor_passport_path')) $t->string('guarantor_passport_path', 500)->nullable();
            if (! $has('guarantor_id_path')) $t->string('guarantor_id_path', 500)->nullable();
            if (! $has('guarantor_signed')) $t->boolean('guarantor_signed')->default(false);
            if (! $has('guarantor_date')) $t->date('guarantor_date')->nullable();
            if (! $has('guarantor_stamped')) $t->boolean('guarantor_stamped')->default(false);
            if (! $has('guarantor_reg_ref')) $t->string('guarantor_reg_ref', 100)->nullable();
            if (! $has('guarantor_verified_by')) $t->string('guarantor_verified_by', 255)->nullable();
            if (! $has('guarantor_verified_date')) $t->date('guarantor_verified_date')->nullable();
        });
    }

    public function down(): void
    {
        // Booleans carry default constraints in SQL Server; drop those first.
        foreach (['guarantor_signed', 'guarantor_stamped'] as $c) {
            \Illuminate\Support\Facades\DB::connection('sqlsrv')->statement(
                "DECLARE @n sysname = (SELECT d.name FROM sys.default_constraints d JOIN sys.columns c ON c.default_object_id = d.object_id
                  WHERE d.parent_object_id = OBJECT_ID('survey_confirmation_forms') AND c.name = '{$c}');
                 IF @n IS NOT NULL EXEC('ALTER TABLE survey_confirmation_forms DROP CONSTRAINT ' + @n);");
        }
        Schema::connection('sqlsrv')->table('survey_confirmation_forms', fn (Blueprint $t) => $t->dropColumn(self::COLUMNS));
    }
};
