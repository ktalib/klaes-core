<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('survey_change_reports')) {
            return;
        }

        $schema->table('survey_change_reports', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('survey_change_reports', 'location')) $table->string('location', 500)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'grid_origin')) $table->string('grid_origin', 255)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'control_points')) $table->string('control_points', 500)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'boundaries_pointed_by')) $table->string('boundaries_pointed_by', 255)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'boundaries_pointed_on')) $table->date('boundaries_pointed_on')->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'commenced_on')) $table->date('commenced_on')->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'completed_on')) $table->date('completed_on')->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'distance_km')) $table->decimal('distance_km', 10, 2)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'computed_on')) $table->string('computed_on', 255)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'delay_reason')) $table->string('delay_reason', 2000)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'on_layout_plan')) $table->boolean('on_layout_plan')->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'layout_plan_no')) $table->string('layout_plan_no', 100)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'corners_demarcated')) $table->boolean('corners_demarcated')->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'hard_by')) $table->string('hard_by', 500)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'encroachment')) $table->boolean('encroachment')->default(false);
            if (! $schema->hasColumn('survey_change_reports', 'encroachment_details')) $table->string('encroachment_details', 2000)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'developed')) $table->boolean('developed')->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'developed_with')) $table->string('developed_with', 500)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'beacons_not_emplaced')) $table->string('beacons_not_emplaced', 500)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'beacons_reason')) $table->string('beacons_reason', 1000)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'previous_kn')) $table->string('previous_kn', 100)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'previous_kn_coincides')) $table->boolean('previous_kn_coincides')->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'transport_by')) $table->string('transport_by', 20)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'remarks')) $table->string('remarks', 4000)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'field_jacket_checklist')) $table->string('field_jacket_checklist', 1000)->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('survey_change_reports')) {
            return;
        }

        $columns = [
            'location', 'grid_origin', 'control_points', 'boundaries_pointed_by', 'boundaries_pointed_on',
            'commenced_on', 'completed_on', 'distance_km', 'computed_on', 'delay_reason', 'on_layout_plan',
            'layout_plan_no', 'corners_demarcated', 'hard_by', 'encroachment', 'encroachment_details',
            'developed', 'developed_with', 'beacons_not_emplaced', 'beacons_reason', 'previous_kn',
            'previous_kn_coincides', 'transport_by', 'remarks', 'field_jacket_checklist',
        ];
        $existing = array_values(array_filter($columns, fn ($column) => $schema->hasColumn('survey_change_reports', $column)));
        if ($existing) {
            $schema->table('survey_change_reports', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
