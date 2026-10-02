<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Department module — standalone schema for the four units of the
 * 2026-08-26 concept note: Registry, Report, Information, Plan & Description.
 *
 * WHAT IS DELIBERATELY NOT HERE
 * -----------------------------
 * Several things the concept note asks for already exist in KLAES and are read
 * through rather than copied, so there is no table for them:
 *
 *   correspondence files  -> file_indexings.is_corresponding_file /
 *                            .corresponding_fileno (47,913 rows already)
 *   file movement records -> file_tracker.movement_log (52,054 rows)
 *   duplicate detection   -> duplicate_fileno + DuplicateCheckService
 *   audit trail           -> audit_logs via AuditService
 *   notifications         -> notifications via UserNotificationService
 *   Land 12               -> survey_report_requests (linked by id, never written)
 *
 * NO FOREIGN KEYS TO LEGACY TABLES. file_indexings (170k) and file_tracker (52k)
 * are hand-maintained and rows are hard-deleted elsewhere; an FK here would make
 * an unrelated deletion fail in production. Those columns are plain integers,
 * validated in PHP.
 *
 * EVERY create() IS GUARDED BY hasTable(). On this database the sqlsrv migration
 * ledger and the schema already disagree (the survey module's tables exist while
 * its ledger row reads Pending), so a part-applied run has to be re-runnable.
 *
 * Address columns follow the KLAES address-builder convention, the same eight
 * per group as survey_* and LandsOneStopShopApplication. Values are strings, not
 * foreign keys, and a "_other" column holds the free text when the dropdown reads
 * Other:
 *
 *   prop_*  property / parcel location -> "District, LGA, State"
 *   addr_*  a person's address         -> "Street, Plot|House, District, LGA, State"
 *
 * The property location never carries the plot number — plot has its own column.
 *
 * DEPLOY WITH THE PATH FLAG. A bare migrate would run 27 unrelated pending
 * migrations against production:
 *
 *   php artisan migrate --database=sqlsrv \
 *     --path=database/migrations/2026_09_28_100000_create_cadastral_module_tables.php --force
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

    /** Person (surveyor / firm) address columns. */
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

        /* ============================ 4.1 REGISTRY ============================ */

        /*
         | The digital file reception log. A cadastral copy keeps the SAME file
         | number as the source file, so file_number is indexed but not unique —
         | one file can come in repeatedly for different purposes.
         */
        if (! $s->hasTable('cadastral_file_receipts')) {
            $s->create('cadastral_file_receipts', function (Blueprint $t) {
                $t->id();
                $t->string('receipt_ref', 50)->unique();
                $t->string('file_number', 100);
                $t->string('file_title', 500)->nullable();
                $t->string('source_registry', 50);              // Land|SLTR|ST|DCIV|KANGIS|Deeds|Other
                $t->string('source_reference', 100)->nullable(); // their dispatch/transmittal no
                $t->string('received_from', 255)->nullable();
                $t->dateTime('received_at')->nullable();
                $t->unsignedBigInteger('received_by')->nullable();
                $t->string('purpose', 50)->nullable();           // Verification|Customary|Statutory|Charting|Enquiry
                $t->unsignedInteger('num_pages')->nullable();
                $t->string('condition_note', 500)->nullable();
                $t->string('file_class', 20)->nullable();        // direct | conversion (from the prefix)
                $t->string('correspondence_status', 20)->default('pending'); // not_required|pending|created|matched
                $t->unsignedBigInteger('file_indexing_id')->nullable();          // pointer, no FK
                $t->unsignedBigInteger('cadastral_shadow_file_id')->nullable();  // pointer, no FK
                $t->boolean('duplicate_flag')->default(false);
                $t->string('duplicate_note', 1000)->nullable();
                $t->string('shelf_location', 100)->nullable();   // from ShelfRackLocator
                $t->string('status', 30)->default('Received');   // Received|Registered|Archived|Returned|Rejected
                $t->dateTime('registered_at')->nullable();
                $t->dateTime('archived_at')->nullable();
                $this->propertyAddress($t);
                $this->stamps($t);
                $t->index('file_number');
                $t->index('status');
                $t->index('source_registry');
                $t->index('received_at');
            });
        }

        /* ========================== 4.3 INFORMATION ========================== */

        /*
         | The charting register. Charts are versioned rather than edited in
         | place: a new version sets is_current = 0 on the one it supersedes, so
         | the history of what was charted when survives.
         |
         | origin / origin_id MIRROR a legacy gisCapture or surveyCadastral row by
         | reference. The module never writes those tables.
         */
        if (! $s->hasTable('cadastral_charts')) {
            $s->create('cadastral_charts', function (Blueprint $t) {
                $t->id();
                $t->string('chart_ref', 50)->unique();
                $t->string('file_number', 100);
                $t->string('file_title', 500)->nullable();
                $t->string('chart_category', 20)->default('direct');   // direct | conversion
                // Conversion files are not charted (concept note 4.3a) — they go
                // straight to index-card commissioning.
                $t->boolean('charting_required')->default(true);
                $t->integer('version')->default(1);
                $t->unsignedBigInteger('supersedes_chart_id')->nullable();
                $t->boolean('is_current')->default(true);
                $t->string('plot_no', 50)->nullable();
                $t->string('block_no', 50)->nullable();
                $t->string('approved_plan_no', 100)->nullable();
                $t->string('tp_plan_no', 100)->nullable();
                $t->string('scheme_plan_no', 100)->nullable();
                $t->string('layout_name', 255)->nullable();
                $t->string('sheet_metric_index', 100)->nullable();
                $t->string('sheet_metric_no', 100)->nullable();
                $t->string('sheet_imperial', 100)->nullable();
                $t->string('sheet_imperial_no', 100)->nullable();
                $t->string('origin', 30)->nullable();       // cadastral_module|gisCapture|surveyCadastral
                $t->unsignedBigInteger('origin_id')->nullable();
                $t->decimal('area_sqm', 18, 3)->nullable();
                $t->string('area_source', 20)->nullable();  // manual|coordinates|legacy
                $t->string('chart_officer_name', 255)->nullable();
                $t->date('charted_on')->nullable();
                $t->string('checked_by', 255)->nullable();
                $t->date('checked_on')->nullable();
                $t->string('manual_chart_path', 500)->nullable();
                $t->string('digital_chart_path', 500)->nullable();
                $t->string('conflict_status', 20)->default('none'); // none|suspected|confirmed|cleared
                $t->string('conflict_note', 1000)->nullable();
                $t->string('status', 30)->default('Draft');  // Draft|Charted|Checked|Approved|Superseded
                $this->propertyAddress($t);
                $this->stamps($t);
                $t->index('file_number');
                $t->index('status');
                $t->index('is_current');
                $t->index(['plot_no', 'block_no'], 'cad_charts_plot_block_idx');
                $t->index('layout_name');
            });
        }

        /*
         | The beacon ring of a chart, one row per corner, in survey order.
         |
         | Columns are deliberately identical to survey_lpkn_coordinates so the
         | two read alike and can be merged later if the Ministry decides the
         | layout-level and file-level instructions are one thing.
         |
         | This table is what makes area computation and any geometric conflict
         | test possible at all — KLAES has no geometry type.
         */
        if (! $s->hasTable('cadastral_chart_coordinates')) {
            $s->create('cadastral_chart_coordinates', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_chart_id');
                $t->integer('sort_order')->default(0);
                $t->string('beacon_id', 50)->nullable();
                $t->decimal('northing', 15, 3)->nullable();
                $t->decimal('easting', 15, 3)->nullable();
                $t->decimal('elevation', 15, 3)->nullable();
                $t->string('bearing', 30)->nullable();
                $t->decimal('distance', 12, 3)->nullable();
                $t->string('remarks', 500)->nullable();
                $this->stamps($t);
                $t->foreign('cadastral_chart_id')->references('id')->on('cadastral_charts');
                $t->index(['cadastral_chart_id', 'sort_order'], 'cad_chart_coord_order_idx');
            });
        }

        /*
         | The index card as DATA. Today CadastralIndexCardController only browses
         | scanned card IMAGES on disk; image_folder joins a scan to its data row.
         |
         | The card's "File Movement Record with timestamps" is NOT a column here:
         | it is read through from file_tracker.movement_log for the same file
         | number, which is what keeps it in step with the rest of KLAES.
         */
        if (! $s->hasTable('cadastral_index_cards')) {
            $s->create('cadastral_index_cards', function (Blueprint $t) {
                $t->id();
                $t->string('card_ref', 50)->unique();
                $t->string('file_number', 100);
                $t->string('file_title', 500)->nullable();
                $t->string('plot_no', 50)->nullable();
                $t->string('block_no', 50)->nullable();
                $t->string('layout_name', 255)->nullable();
                $t->unsignedBigInteger('cadastral_chart_id')->nullable();
                $t->string('survey_job_number', 50)->nullable();
                // open|closed|revoked|reinstated|withdrawn|change_of_purpose
                $t->string('file_status', 30)->default('open');
                $t->dateTime('file_status_changed_at')->nullable();
                $t->string('file_status_reason', 1000)->nullable();
                $t->dateTime('commissioned_at')->nullable();
                $t->unsignedBigInteger('commissioned_by')->nullable();
                $t->string('image_folder', 255)->nullable();
                $t->integer('print_count')->default(0);
                $t->dateTime('last_printed_at')->nullable();
                $this->propertyAddress($t);
                $this->stamps($t);
                $t->foreign('cadastral_chart_id')->references('id')->on('cadastral_charts');
                $t->index('file_number');
                $t->index('file_status');
            });
        }

        /* One card per file, but a soft-deleted card must not block a new one. */
        $this->filteredUnique(
            'cadastral_index_cards',
            'cad_index_cards_fileno_live_uq',
            '[file_number]',
            '[deleted_at] IS NULL'
        );

        /* 4.3(d) — the historical log behind every file status change. */
        if (! $s->hasTable('cadastral_file_status_events')) {
            $s->create('cadastral_file_status_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_index_card_id')->nullable();
                $t->string('file_number', 100);
                $t->string('from_status', 30)->nullable();
                $t->string('to_status', 30);
                $t->text('reason')->nullable();
                $t->date('effective_date')->nullable();
                $t->string('authority_ref', 100)->nullable();
                $t->unsignedBigInteger('actor_user_id')->nullable();
                $t->string('actor_name', 255)->nullable();
                $t->boolean('notified')->default(false);
                $t->dateTime('notified_at')->nullable();
                $this->stamps($t);
                $t->foreign('cadastral_index_card_id')->references('id')->on('cadastral_index_cards');
                $t->index('file_number');
            });
        }

        /* The SURCON / firm directory. cadastral_officers is ministry staff; this is the profession. */
        if (! $s->hasTable('cadastral_surveyors')) {
            $s->create('cadastral_surveyors', function (Blueprint $t) {
                $t->id();
                $t->string('surcon_number', 50)->nullable();
                $t->string('full_name', 255);
                $t->string('firm_name', 255)->nullable();
                $t->string('firm_rc_no', 50)->nullable();
                $t->string('phone', 50)->nullable();
                $t->string('email', 255)->nullable();
                $t->string('licence_status', 30)->default('Active'); // Active|Expired|Suspended|Struck Off
                $t->date('licence_expires_on')->nullable();
                $t->boolean('is_active')->default(true);
                $this->personAddress($t);
                $this->stamps($t);
                $t->index('firm_name');
                $t->index('licence_status');
            });
        }

        $this->filteredUnique(
            'cadastral_surveyors',
            'cad_surveyors_surcon_live_uq',
            '[surcon_number]',
            '[deleted_at] IS NULL AND [surcon_number] IS NOT NULL'
        );

        /*
         | The survey job register and the Instruction to Surveyor it carries.
         |
         | its_* names match survey_lpkn's, and its_signature_* match
         | survey_report_requests', so all three read alike.
         |
         | The job's location is the prop_* address-builder group, not a free-text
         | field beside it.
         */
        if (! $s->hasTable('cadastral_survey_jobs')) {
            $s->create('cadastral_survey_jobs', function (Blueprint $t) {
                $t->id();
                $t->string('job_number', 50)->unique();
                $t->string('file_number', 100);
                $t->string('file_title', 500)->nullable();
                $t->unsignedBigInteger('cadastral_index_card_id')->nullable();
                $t->unsignedBigInteger('cadastral_report_id')->nullable();
                $t->unsignedBigInteger('cadastral_surveyor_id')->nullable();
                // Snapshot: the directory row may later be renamed or struck off.
                $t->string('surveyor_name', 255)->nullable();
                $t->string('firm_name', 255)->nullable();
                $t->text('job_scope')->nullable();
                $t->string('its_number', 50)->nullable();
                $t->date('its_issued_at')->nullable();
                $t->string('its_recipient', 255)->nullable();
                $t->string('its_issued_by', 255)->nullable();
                $t->unsignedBigInteger('its_issued_by_user_id')->nullable();
                $t->string('its_officer_post', 30)->nullable();
                $t->text('its_instructions')->nullable();
                $t->string('its_signature_path', 500)->nullable();
                $t->string('its_signature_method', 50)->nullable();
                $t->dateTime('its_signature_verified_at')->nullable();
                $t->unsignedBigInteger('its_signature_approved_by')->nullable();
                // Draft|Issued|In Field|Submitted|Accepted|Rejected|Cancelled
                $t->string('status', 30)->default('Draft');
                $t->dateTime('issued_at')->nullable();
                $t->dateTime('submitted_at')->nullable();
                $t->dateTime('accepted_at')->nullable();
                $this->propertyAddress($t);
                $this->stamps($t);
                $t->foreign('cadastral_index_card_id')->references('id')->on('cadastral_index_cards');
                $t->foreign('cadastral_surveyor_id')->references('id')->on('cadastral_surveyors');
                $t->index('file_number');
                $t->index('status');
                $t->index('its_number');
            });
        }

        /* ============================= 4.2 REPORT ============================= */

        if (! $s->hasTable('cadastral_reports')) {
            $s->create('cadastral_reports', function (Blueprint $t) {
                $t->id();
                $t->string('report_ref', 50)->unique();
                $t->string('report_type', 20);   // verification | customary | statutory
                $t->unsignedBigInteger('cadastral_file_receipt_id')->nullable();
                $t->string('file_number', 100);
                $t->string('file_title', 500)->nullable();
                $t->string('plot_no', 50)->nullable();
                $t->string('block_no', 50)->nullable();
                $t->string('layout_name', 255)->nullable();
                // Nullable: the chart may be created part-way through the chain.
                $t->unsignedBigInteger('cadastral_chart_id')->nullable();
                $t->unsignedBigInteger('cadastral_survey_job_id')->nullable();
                $t->unsignedTinyInteger('current_step')->default(1);
                $t->string('current_step_key', 40)->nullable();
                // Draft|In Progress|Checked|Approved|Dispatched|Returned|Rejected
                $t->string('status', 30)->default('Draft');
                $t->string('assigned_post', 30)->nullable();
                $t->unsignedBigInteger('assigned_user_id')->nullable();
                $t->text('ground_status')->nullable();   // observation report
                $t->text('chart_status')->nullable();    // observation report
                // These three mirror survey_report_requests' names so an approved
                // report can pre-fill a Land 12 without a translation layer.
                $t->string('survey_necessary', 10)->nullable();
                $t->string('beacon_numbers', 500)->nullable();
                $t->text('plot_description')->nullable();
                // Link only. survey_report_requests holds real records and has no
                // migration; this module never writes it.
                $t->unsignedBigInteger('survey_report_request_id')->nullable();
                $t->date('due_date')->nullable();
                $t->dateTime('dispatched_at')->nullable();
                $t->string('dispatched_to', 255)->nullable();
                $this->propertyAddress($t);
                $this->stamps($t);
                $t->foreign('cadastral_file_receipt_id')->references('id')->on('cadastral_file_receipts');
                $t->foreign('cadastral_chart_id')->references('id')->on('cadastral_charts');
                $t->foreign('cadastral_survey_job_id')->references('id')->on('cadastral_survey_jobs');
                $t->index('file_number');
                $t->index('report_type');
                $t->index('status');
                $t->index('assigned_user_id');
            });
        }

        /* The stage chain, one row per step. Structural twin of survey_op_workflow_steps. */
        if (! $s->hasTable('cadastral_report_steps')) {
            $s->create('cadastral_report_steps', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_report_id');
                $t->unsignedTinyInteger('step_no');
                $t->string('step_key', 40);
                $t->string('step_name', 100);
                $t->string('required_post', 30)->nullable();
                $t->unsignedBigInteger('actor_user_id')->nullable();
                $t->string('actor_name', 255)->nullable();
                $t->string('status', 20)->default('waiting'); // waiting|active|done|returned|skipped
                $t->text('note')->nullable();
                $t->dateTime('started_at')->nullable();
                $t->dateTime('completed_at')->nullable();
                $this->stamps($t);
                $t->foreign('cadastral_report_id')->references('id')->on('cadastral_reports');
                $t->index(['cadastral_report_id', 'step_no'], 'cad_report_steps_order_idx');
            });
        }

        /*
         | The field inspection artefact. GPS is plain lat/long decimals: KLAES
         | mapping is Leaflet points and there is no geometry type anywhere.
         | The location comes from the parent report, so there is no address group.
         */
        if (! $s->hasTable('cadastral_site_inspections')) {
            $s->create('cadastral_site_inspections', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_report_id');
                $t->string('inspection_ref', 50)->nullable();
                $t->date('inspected_on')->nullable();
                $t->string('field_officer_name', 255)->nullable();
                $t->unsignedBigInteger('field_officer_user_id')->nullable();
                $t->decimal('gps_latitude', 10, 7)->nullable();
                $t->decimal('gps_longitude', 10, 7)->nullable();
                $t->decimal('gps_accuracy_m', 8, 2)->nullable();
                $t->string('development_status', 100)->nullable();
                $t->string('occupancy_status', 100)->nullable();
                $t->string('access_road', 100)->nullable();
                $t->boolean('encroachment')->default(false);
                $t->string('encroachment_note', 1000)->nullable();
                $t->text('ground_findings')->nullable();
                $t->text('chart_findings')->nullable();
                $t->string('photo_path', 500)->nullable();
                $t->string('sketch_path', 500)->nullable();
                $this->stamps($t);
                $t->foreign('cadastral_report_id')->references('id')->on('cadastral_reports');
                $t->index('cadastral_report_id');
            });
        }

        /* ===================== 4.4 PLAN AND DESCRIPTION ===================== */

        /*
         | Area is stored ONCE, in square metres. Hectares, acres and plots are
         | accessors computed from it — four stored representations of one number
         | would drift.
         */
        if (! $s->hasTable('cadastral_plan_descriptions')) {
            $s->create('cadastral_plan_descriptions', function (Blueprint $t) {
                $t->id();
                $t->string('pd_ref', 50)->unique();
                $t->string('file_number', 100);
                $t->string('file_title', 500)->nullable();
                $t->unsignedBigInteger('cadastral_chart_id')->nullable();
                $t->unsignedBigInteger('cadastral_report_id')->nullable();
                $t->string('land_use', 50)->nullable();       // Residential|Commercial|...
                $t->string('location_zone', 20)->nullable();  // urban|semi_urban|rural
                $t->decimal('area_sqm', 18, 3)->nullable();
                $t->string('area_source', 20)->nullable();    // manual|coordinates|survey_plan
                $t->unsignedTinyInteger('area_precision')->default(2);
                // Snapshot of the standard plot, so an old bill reprints the same
                // plot count if the standard changes.
                $t->decimal('plot_size_sqm', 12, 2)->nullable();
                $t->string('boundary_north', 255)->nullable();
                $t->string('boundary_south', 255)->nullable();
                $t->string('boundary_east', 255)->nullable();
                $t->string('boundary_west', 255)->nullable();
                $t->string('description_complexity', 20)->default('standard'); // simple|standard|complex
                $t->string('template_key', 50)->nullable();
                $t->text('description_body')->nullable();
                $t->dateTime('description_generated_at')->nullable();
                $t->unsignedBigInteger('description_edited_by')->nullable();
                $t->string('validation_status', 20)->default('unvalidated'); // unvalidated|passed|failed
                $t->string('validation_notes', 1000)->nullable();
                $this->propertyAddress($t);
                $this->stamps($t);
                $t->foreign('cadastral_chart_id')->references('id')->on('cadastral_charts');
                $t->foreign('cadastral_report_id')->references('id')->on('cadastral_reports');
                $t->index('file_number');
            });
        }

        /*
         | Pillars/beacons counted off the survey plan. Both ownership types are
         | priced the same (concept note 4.4b) — 4,000 each. unit_price is a
         | snapshot taken when the bill is issued.
         */
        if (! $s->hasTable('cadastral_pillars')) {
            $s->create('cadastral_pillars', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_plan_description_id');
                $t->integer('sort_order')->default(0);
                $t->string('pillar_number', 50)->nullable();
                $t->string('ownership', 20)->default('government'); // government | private
                $t->string('pillar_type', 50)->nullable();
                $t->decimal('northing', 15, 3)->nullable();
                $t->decimal('easting', 15, 3)->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->string('condition', 30)->nullable();  // Intact|Damaged|Missing|Replaced
                $t->decimal('unit_price', 14, 2)->nullable();
                $this->stamps($t);
                $t->foreign('cadastral_plan_description_id')->references('id')->on('cadastral_plan_descriptions');
                $t->index(['cadastral_plan_description_id', 'sort_order'], 'cad_pillars_order_idx');
            });
        }

        /*
         | The consolidated bill. Every rate is snapshotted alongside the amount it
         | produced, so the bill reprints exactly as issued however config changes.
         |
         | billing_id is a nullable pointer to the central `billing` table. That
         | table is shaped for Sectional Titling (Scheme_Application_Fee,
         | Site_Plan_Fee ... all nvarchar), so nothing is written there yet; see
         | the note in CadastralBillCalculator.
         */
        if (! $s->hasTable('cadastral_bills')) {
            $s->create('cadastral_bills', function (Blueprint $t) {
                $t->id();
                $t->string('bill_ref', 50)->unique();
                $t->unsignedBigInteger('cadastral_plan_description_id');
                $t->string('file_number', 100);
                $t->unsignedBigInteger('billing_id')->nullable();
                // -- rate snapshot --
                $t->decimal('area_rate_per_sqm', 14, 4)->default(0);
                $t->decimal('land_use_multiplier', 8, 4)->default(1);
                $t->decimal('zone_multiplier', 8, 4)->default(1);
                $t->decimal('pillar_unit_price', 14, 2)->default(0);
                $t->decimal('service_charge_percent', 6, 3)->default(0);
                $t->decimal('description_fee', 14, 2)->default(0);
                // -- line amounts --
                $t->decimal('area_fee', 16, 2)->default(0);
                $t->integer('pillar_count_government')->default(0);
                $t->integer('pillar_count_private')->default(0);
                $t->decimal('pillar_fee', 16, 2)->default(0);
                $t->decimal('service_charge', 16, 2)->default(0);
                $t->decimal('description_fee_amount', 16, 2)->default(0);
                $t->decimal('grand_total', 16, 2)->default(0);
                $t->string('status', 20)->default('Draft');  // Draft|Issued|Paid|Cancelled
                $t->dateTime('issued_at')->nullable();
                $t->string('receipt_no', 100)->nullable();
                $t->date('receipt_date')->nullable();
                $this->stamps($t);
                $t->foreign('cadastral_plan_description_id')->references('id')->on('cadastral_plan_descriptions');
                $t->index('file_number');
                $t->index('status');
            });
        }

        /* ================== Additive columns on cadastral_officers ================== */

        /*
         | cadastral_officers already IS the Cadastral staff directory (id, name,
         | rank) and is read by exactly one caller, SurveyReportController's Land 12
         | dropdown. Nullable additive columns cannot disturb it, and extending it
         | means the Land 12 dropdown and the new workflow routing share one
         | directory instead of drifting apart.
         |
         | post_code holds the job post (D_CAD, CHART_OFFICER_I, ...). Posts are
         | deliberately NOT user_roles rows: module_permissions.php is explicit
         | that modules are named menu areas, not job roles.
         */
        if ($s->hasTable('cadastral_officers')) {
            $s->table('cadastral_officers', function (Blueprint $t) use ($s) {
                if (! $s->hasColumn('cadastral_officers', 'user_id')) {
                    $t->unsignedBigInteger('user_id')->nullable();
                }
                if (! $s->hasColumn('cadastral_officers', 'post_code')) {
                    $t->string('post_code', 30)->nullable();
                }
                if (! $s->hasColumn('cadastral_officers', 'department')) {
                    $t->string('department', 100)->nullable();
                }
                if (! $s->hasColumn('cadastral_officers', 'is_active')) {
                    $t->boolean('is_active')->default(true);
                }
            });
        }
    }

    /**
     * A unique index with a WHERE clause. Raw SQL because Laravel's schema
     * builder has no filtered-index API; the guard makes it re-runnable.
     */
    private function filteredUnique(string $table, string $index, string $columns, string $where): void
    {
        $db = DB::connection(self::CONN);

        if (! Schema::connection(self::CONN)->hasTable($table)) {
            return;
        }

        $db->statement(
            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = '{$index}' AND object_id = OBJECT_ID('{$table}')) " .
            "DROP INDEX [{$index}] ON [{$table}]"
        );

        $db->statement("CREATE UNIQUE INDEX [{$index}] ON [{$table}] ({$columns}) WHERE {$where}");
    }

    public function down(): void
    {
        $s = Schema::connection(self::CONN);

        // Reverse dependency order: children before the tables they point at.
        foreach ([
            'cadastral_bills',
            'cadastral_pillars',
            'cadastral_plan_descriptions',
            'cadastral_site_inspections',
            'cadastral_report_steps',
            'cadastral_reports',
            'cadastral_survey_jobs',
            'cadastral_surveyors',
            'cadastral_file_status_events',
            'cadastral_index_cards',
            'cadastral_chart_coordinates',
            'cadastral_charts',
            'cadastral_file_receipts',
        ] as $table) {
            $s->dropIfExists($table);
        }

        // cadastral_officers is NOT dropped — it predates this module. Its four
        // added columns are left in place: they are nullable and harmless, and
        // dropping them would break any Land 12 screen already reading post_code.
    }
};
