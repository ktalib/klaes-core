<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CR 03112026 items 11, 17-21: survey types and beacons on the I to S (Survey and Cadastral), beacon
 * requests to the SG, and the Surveyor General's Office portal. Additive: new tables and nullable
 * columns only. The seven survey types are the sheet's own list.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        if (! $schema->hasTable('survey_types')) {
            $schema->create('survey_types', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name', 100);
                $t->boolean('is_active')->default(true);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->unique('name', 'ux_survey_types_name');
            });
            foreach (['Compensation Survey', 'Hydrological Survey', 'Geological Survey', 'Layout Survey', 'Engineering Survey', 'As-Built Survey', 'Detail Survey'] as $name) {
                $db->table('survey_types')->insert(['name' => $name, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        foreach (['survey_its', 'cadastral_survey_jobs'] as $table) {
            if (! $schema->hasColumn($table, 'survey_type')) {
                $schema->table($table, fn (Blueprint $t) => $t->string('survey_type', 100)->nullable());
            }
        }

        // One row per beacon on an I to S. A beacon goes on one job: the unique index is the last guard.
        if (! $schema->hasTable('survey_its_beacons')) {
            $schema->create('survey_its_beacons', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('survey_its_id')->nullable()->index('ix_survey_its_beacons_its');
                $t->unsignedBigInteger('cadastral_survey_job_id')->nullable()->index('ix_survey_its_beacons_job');
                $t->string('beacon_no', 30);
                $t->string('beacon_series', 40);
                $t->integer('beacon_seq');
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->unique(['beacon_series', 'beacon_seq'], 'ux_survey_its_beacons_number');
            });
        }

        if (! $schema->hasTable('surveyor_portal_accounts')) {
            $schema->create('surveyor_portal_accounts', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('registration_id', 40)->unique('ux_surveyor_portal_registration');
                $t->string('surveyor_id_no', 40)->nullable();
                $t->string('surveyor_type', 20);                  // Government (shown as Public) or Private
                $t->unsignedBigInteger('company_id')->nullable();
                $t->string('company_name', 255)->nullable();
                $t->string('name', 255);
                $t->string('phone', 30);
                $t->string('email', 150);
                $t->string('surcon_number', 50)->nullable();
                $t->string('password');
                $t->string('status', 20)->default('pending');     // pending, active, rejected, suspended
                $t->string('review_note', 1000)->nullable();
                $t->unsignedBigInteger('reviewed_by')->nullable();
                $t->dateTime('reviewed_at')->nullable();
                $t->unsignedBigInteger('cadastral_surveyor_id')->nullable();
                $t->unsignedBigInteger('surveyors_list_id')->nullable();
                $t->rememberToken();
                $t->dateTime('last_login_at')->nullable();
                $t->string('reset_code', 255)->nullable();
                $t->dateTime('reset_expires_at')->nullable();
                $t->unsignedTinyInteger('reset_attempts')->default(0);
                $t->timestamps();
                $t->unique('phone', 'ux_surveyor_portal_phone');
                $t->unique('email', 'ux_surveyor_portal_email');
                $t->index('status', 'ix_surveyor_portal_status');
            });
            $db->statement('CREATE UNIQUE INDEX ux_surveyor_portal_surveyor_id ON surveyor_portal_accounts (surveyor_id_no) WHERE surveyor_id_no IS NOT NULL');
        }

        if (! $schema->hasTable('surveyor_portal_notifications')) {
            $schema->create('surveyor_portal_notifications', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('surveyor_portal_account_id')->index('ix_surveyor_portal_notes_account');
                $t->string('title', 255);
                $t->string('body', 1000);
                $t->string('link', 500)->nullable();
                $t->dateTime('read_at')->nullable();
                $t->timestamps();
            });
        }

        if (! $schema->hasTable('survey_beacon_requests')) {
            $schema->create('survey_beacon_requests', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('request_no', 30)->unique('ux_survey_beacon_requests_no');
                $t->string('source', 20);                         // portal, cadastral_its
                $t->unsignedBigInteger('surveyor_portal_account_id')->nullable()->index('ix_survey_beacon_requests_account');
                $t->unsignedBigInteger('cadastral_survey_job_id')->nullable()->index('ix_survey_beacon_requests_job');
                $t->string('layout_type', 20);
                $t->unsignedBigInteger('company_id')->nullable();
                $t->string('company_name', 255)->nullable();
                $t->string('surveyor_name', 255);
                $t->string('surveyor_phone', 30)->nullable();
                $t->string('job_name', 255)->nullable();
                $t->integer('quantity');
                $t->string('plot_no', 100)->nullable();
                $t->string('district', 255)->nullable();
                $t->string('lga', 255)->nullable();
                $t->string('location', 500)->nullable();
                $t->string('remarks', 2000)->nullable();
                $t->string('status', 20)->default('Submitted');   // Submitted, Issued, Rejected, Accepted
                $t->string('review_note', 1000)->nullable();
                $t->string('issued_numbers', 500)->nullable();
                $t->unsignedBigInteger('reviewed_by')->nullable();
                $t->dateTime('reviewed_at')->nullable();
                $t->dateTime('accepted_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index('status', 'ix_survey_beacon_requests_status');
            });
        }

        if (! $schema->hasColumn('survey_beacon_register', 'survey_beacon_request_id')) {
            $schema->table('survey_beacon_register', fn (Blueprint $t) => $t->unsignedBigInteger('survey_beacon_request_id')->nullable());
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_beacon_register', 'survey_beacon_request_id')) {
            $schema->table('survey_beacon_register', fn (Blueprint $t) => $t->dropColumn('survey_beacon_request_id'));
        }
        foreach (['survey_its', 'cadastral_survey_jobs'] as $table) {
            if ($schema->hasColumn($table, 'survey_type')) {
                $schema->table($table, fn (Blueprint $t) => $t->dropColumn('survey_type'));
            }
        }
        foreach (['survey_beacon_requests', 'surveyor_portal_notifications', 'surveyor_portal_accounts', 'survey_its_beacons', 'survey_types'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
