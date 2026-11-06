<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('survey_companies')) {
            throw new RuntimeException('The survey_companies table must exist before company registrations can be added.');
        }

        $columns = [
            'company_rc_no' => fn (Blueprint $t) => $t->string('company_rc_no', 50)->nullable(),
            'phone' => fn (Blueprint $t) => $t->string('phone', 30)->nullable(),
            'email' => fn (Blueprint $t) => $t->string('email', 150)->nullable(),
            'addr_house' => fn (Blueprint $t) => $t->string('addr_house', 100)->nullable(),
            'addr_plot' => fn (Blueprint $t) => $t->string('addr_plot', 100)->nullable(),
            'addr_street' => fn (Blueprint $t) => $t->string('addr_street', 255)->nullable(),
            'addr_street_other' => fn (Blueprint $t) => $t->string('addr_street_other', 255)->nullable(),
            'addr_district' => fn (Blueprint $t) => $t->string('addr_district', 255)->nullable(),
            'addr_district_other' => fn (Blueprint $t) => $t->string('addr_district_other', 255)->nullable(),
            'addr_lga' => fn (Blueprint $t) => $t->string('addr_lga', 255)->nullable(),
            'addr_state' => fn (Blueprint $t) => $t->string('addr_state', 255)->nullable(),
            'status' => fn (Blueprint $t) => $t->string('status', 20)->nullable(),
            'review_note' => fn (Blueprint $t) => $t->string('review_note', 1000)->nullable(),
            'reviewed_by' => fn (Blueprint $t) => $t->unsignedBigInteger('reviewed_by')->nullable(),
            'reviewed_at' => fn (Blueprint $t) => $t->dateTime('reviewed_at')->nullable(),
        ];

        foreach ($columns as $name => $addColumn) {
            if (! $schema->hasColumn('survey_companies', $name)) {
                $schema->table('survey_companies', $addColumn);
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $columns = [
            'company_rc_no', 'phone', 'email', 'addr_house', 'addr_plot', 'addr_street', 'addr_street_other',
            'addr_district', 'addr_district_other', 'addr_lga', 'addr_state', 'status', 'review_note', 'reviewed_by', 'reviewed_at',
        ];
        $present = array_values(array_filter($columns, fn ($column) => $schema->hasColumn('survey_companies', $column)));

        if ($present) {
            $schema->table('survey_companies', fn (Blueprint $t) => $t->dropColumn($present));
        }
    }
};
