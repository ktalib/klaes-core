<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Survey Department module — standalone schema.
 *
 * Deliberately self-contained: it does not reference dbo.vfc_projects or
 * dbo.valuation_compensations, so existing valuation screens are untouched.
 *
 * Address columns follow the KLAES "address builder" convention already used by
 * LandsOneStopShopApplication (res_addr_house / _plot / _street / _street_other /
 * _district / _district_other / _lga / _state): values are stored as strings, not
 * foreign keys, and a "_other" column holds the free text when the dropdown is
 * set to Other.
 *
 *   prop_*  property / parcel location  -> rendered "District, LGA, State"
 *   addr_*  person's address            -> rendered "Street, Plot|House, District, LGA, State"
 */
return new class extends Migration
{
    private const CONN = 'sqlsrv';

    /** Property / parcel location columns. */
    private function propertyAddress(Blueprint $t): void
    {
        $t->string('prop_house', 100)->nullable();
        $t->string('prop_plot', 100)->nullable();
        $t->string('prop_street', 255)->nullable();
        $t->string('prop_street_other', 255)->nullable();
        $t->string('prop_district', 255)->nullable();
        $t->string('prop_district_other', 255)->nullable();
        $t->string('prop_lga', 255)->nullable();
        $t->string('prop_state', 255)->nullable()->default('Kano');
    }

    /** Person (applicant / beneficiary) address columns. */
    private function personAddress(Blueprint $t): void
    {
        $t->string('addr_house', 100)->nullable();
        $t->string('addr_plot', 100)->nullable();
        $t->string('addr_street', 255)->nullable();
        $t->string('addr_street_other', 255)->nullable();
        $t->string('addr_district', 255)->nullable();
        $t->string('addr_district_other', 255)->nullable();
        $t->string('addr_lga', 255)->nullable();
        $t->string('addr_state', 255)->nullable()->default('Kano');
    }

    private function stamps(Blueprint $t): void
    {
        $t->unsignedBigInteger('created_by')->nullable();
        $t->unsignedBigInteger('updated_by')->nullable();
        $t->timestamps();
        $t->softDeletes();
    }

    public function up(): void
    {
        $s = Schema::connection(self::CONN);

        /* ---------------- Projects: scheme type lives here ---------------- */
        $s->create('survey_projects', function (Blueprint $t) {
            $t->id();
            $t->string('project_code', 50)->unique();
            $t->string('name', 255);
            $t->string('purpose', 100)->nullable();
            // 'monetary' (cash for economic trees) | 'land' (50:50 plot split).
            // Exclusive per project; cases inherit and cannot override.
            $t->string('scheme_type', 20)->default('monetary');
            $t->string('status', 30)->default('Active');   // Active | Draft | Closed
            $t->date('start_date')->nullable();
            $t->text('description')->nullable();
            $this->propertyAddress($t);
            $this->stamps($t);
            $t->index('scheme_type');
            $t->index('status');
        });

        /* ---------------- Compensation cases ---------------- */
        $s->create('survey_comp_cases', function (Blueprint $t) {
            $t->id();
            $t->string('case_ref', 50)->unique();
            $t->unsignedBigInteger('survey_project_id');
            // Denormalised from the project at creation time so a case keeps the
            // scheme it was registered under even if the project is edited.
            $t->string('scheme_type', 20)->default('monetary');
            $t->string('purpose', 100)->nullable();
            $t->string('survey_officer', 255)->nullable();
            $t->date('case_date')->nullable();
            $t->decimal('area_ha', 12, 2)->nullable();
            $t->string('coordinates', 255)->nullable();
            $t->string('gps_reading', 255)->nullable();
            $t->unsignedInteger('num_plots')->nullable();
            $t->string('boundary_file', 500)->nullable();
            $t->text('description')->nullable();
            $t->string('status', 30)->default('Pending');  // Pending|Active|Review|Completed|Rejected
            $this->propertyAddress($t);
            $this->stamps($t);
            $t->foreign('survey_project_id')->references('id')->on('survey_projects');
            $t->index('status');
            $t->index('scheme_type');
        });

        /* ---------------- Beneficiaries ---------------- */
        $s->create('survey_beneficiaries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('survey_comp_case_id')->nullable();
            $t->string('full_name', 255);
            $t->string('phone', 50)->nullable();
            $t->string('nin', 20)->nullable();
            $t->string('status', 30)->default('Pending');  // Verified | Pending | Review
            $t->string('photo_path', 500)->nullable();
            $t->string('bank_name', 255)->nullable();
            $t->string('account_name', 255)->nullable();
            $t->string('account_number', 50)->nullable();
            $this->personAddress($t);
            $this->stamps($t);
            $t->foreign('survey_comp_case_id')->references('id')->on('survey_comp_cases');
            $t->index('nin');
            $t->index('status');
        });

        /* ---------------- Economic tree catalogue + per-case lines ---------------- */
        $s->create('survey_tree_types', function (Blueprint $t) {
            $t->id();
            $t->string('name', 255)->unique();
            $t->string('category', 50)->nullable();          // Cash Crop | Timber | Fruit
            $t->decimal('default_unit_price', 14, 2)->default(0);
            $t->string('status', 20)->default('Active');
            $this->stamps($t);
        });

        $s->create('survey_case_trees', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('survey_comp_case_id');
            $t->unsignedBigInteger('survey_tree_type_id')->nullable();
            $t->string('tree_type', 255);                    // kept as text for free entry
            $t->unsignedInteger('quantity')->default(0);
            $t->decimal('unit_price', 14, 2)->default(0);
            $t->decimal('line_total', 16, 2)->default(0);
            $this->stamps($t);
            $t->foreign('survey_comp_case_id')->references('id')->on('survey_comp_cases');
            $t->foreign('survey_tree_type_id')->references('id')->on('survey_tree_types');
        });

        /* ---------------- Plot allocation (Land-for-Land 50:50) ---------------- */
        $s->create('survey_plot_allocations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('survey_comp_case_id')->nullable();
            $t->unsignedInteger('sr')->nullable();
            $t->string('f_no', 50)->nullable();
            $t->string('farmer_name', 255)->nullable();
            $t->decimal('acreage', 12, 2)->nullable();
            $t->string('creetable', 10)->nullable();         // Yes | No
            $t->decimal('govt_50', 12, 2)->nullable();
            $t->decimal('farmer_50', 12, 2)->nullable();
            $t->string('plot_no', 50)->nullable();
            $t->string('op_no', 50)->nullable();
            $t->string('sign', 100)->nullable();
            $t->string('remarks', 500)->nullable();
            $this->stamps($t);
            $t->foreign('survey_comp_case_id')->references('id')->on('survey_comp_cases');
            $t->index('plot_no');
        });

        /* ---------------- GKN / Misc KN / LPKN registers ---------------- */
        $s->create('survey_gkn_records', function (Blueprint $t) {
            $t->id();
            $t->string('gkn_number', 50)->unique();
            $t->string('title', 255)->nullable();
            $t->decimal('area_ha', 12, 2)->nullable();
            $t->string('land_use', 100)->nullable();
            $t->string('survey_officer', 255)->nullable();
            $t->string('coordinates', 255)->nullable();
            $t->date('record_date')->nullable();
            $t->string('status', 30)->default('Pending');
            $t->string('boundary_file', 500)->nullable();
            $t->text('remarks')->nullable();
            $this->propertyAddress($t);
            $this->stamps($t);
            $t->index('status');
        });

        $s->create('survey_misc_kn', function (Blueprint $t) {
            $t->id();
            $t->string('ref_no', 50)->unique();
            $t->string('title', 255);
            $t->string('category', 50)->nullable();          // Boundary|Dispute|Survey Note|Other
            $t->string('linked_ref', 100)->nullable();
            $t->date('record_date')->nullable();
            $t->string('officer', 255)->nullable();
            $t->text('details')->nullable();
            $this->propertyAddress($t);
            $this->stamps($t);
        });

        $s->create('survey_lpkn', function (Blueprint $t) {
            $t->id();
            $t->string('lpkn_number', 50)->unique();
            $t->string('layout_name', 255);
            $t->unsignedInteger('plot_count')->nullable();
            $t->decimal('area_ha', 12, 2)->nullable();
            $t->string('land_use', 100)->nullable();
            $t->date('record_date')->nullable();
            $t->string('status', 30)->default('Pending');
            $t->text('remarks')->nullable();
            $this->propertyAddress($t);
            $this->stamps($t);
            $t->index('status');
        });

        /* ---------------- Examination queue ---------------- */
        $s->create('survey_examinations', function (Blueprint $t) {
            $t->id();
            $t->string('exam_ref', 50)->unique();
            $t->string('linked_ref', 100)->nullable();       // case / GKN / LPKN reference
            $t->string('exam_type', 100)->nullable();
            $t->string('submitted_by', 255)->nullable();
            $t->string('priority', 20)->default('Normal');   // High | Normal | Low
            $t->string('status', 30)->default('Queued');     // Queued|In Review|Passed|Returned
            $t->text('findings')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $this->stamps($t);
            $t->index('status');
        });

        /* ---------------- Occupancy Permits + workflow ---------------- */
        $s->create('survey_op_records', function (Blueprint $t) {
            $t->id();
            $t->string('op_number', 50)->nullable()->unique();
            $t->unsignedBigInteger('survey_comp_case_id')->nullable();
            $t->unsignedBigInteger('survey_beneficiary_id')->nullable();
            $t->string('scheme_type', 20)->nullable();
            $t->string('plot_or_cash_ref', 255)->nullable();
            $t->decimal('cash_amount', 16, 2)->nullable();
            $t->string('status', 30)->default('Draft');      // Draft|Ready|Issued
            $t->timestamp('issued_at')->nullable();
            $this->stamps($t);
            $t->foreign('survey_comp_case_id')->references('id')->on('survey_comp_cases');
            $t->foreign('survey_beneficiary_id')->references('id')->on('survey_beneficiaries');
            $t->index('status');
        });

        $s->create('survey_op_workflow_steps', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('survey_op_record_id');
            $t->unsignedTinyInteger('step_no');              // 1 Survey .. 5 Land/OSS
            $t->string('step_name', 100);
            $t->string('actor', 255)->nullable();
            $t->string('status', 20)->default('waiting');    // done | active | waiting
            $t->text('note')->nullable();
            $t->timestamp('completed_at')->nullable();
            $this->stamps($t);
            $t->foreign('survey_op_record_id')->references('id')->on('survey_op_records');
            $t->index(['survey_op_record_id', 'step_no']);
        });

        /* ---------------- File movement tracking ---------------- */
        $s->create('survey_file_movements', function (Blueprint $t) {
            $t->id();
            $t->string('file_ref', 100);                     // GKN / case / LPKN reference
            $t->string('from_office', 255)->nullable();
            $t->string('to_office', 255)->nullable();
            $t->string('purpose', 255)->nullable();
            $t->string('status', 30)->default('In Transit'); // In Transit | Received | Returned
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->text('remarks')->nullable();
            $this->stamps($t);
            $t->index('file_ref');
            $t->index('status');
        });
    }

    public function down(): void
    {
        $s = Schema::connection(self::CONN);
        foreach ([
            'survey_file_movements',
            'survey_op_workflow_steps',
            'survey_op_records',
            'survey_examinations',
            'survey_lpkn',
            'survey_misc_kn',
            'survey_gkn_records',
            'survey_plot_allocations',
            'survey_case_trees',
            'survey_tree_types',
            'survey_beneficiaries',
            'survey_comp_cases',
            'survey_projects',
        ] as $table) {
            $s->dropIfExists($table);
        }
    }
};
