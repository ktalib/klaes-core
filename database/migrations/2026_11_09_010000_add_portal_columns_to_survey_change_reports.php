<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | A Surveyor's Report (Private) written on the KSSGOF portal (user, 2026-11-09): whose it is, the I to S it
 | started from (if any), the issued beacons picked when it was started, and when the surveyor submitted it.
 | Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('survey_change_reports')) {
            throw new RuntimeException('survey_change_reports must exist first.');
        }
        $schema->table('survey_change_reports', function (Blueprint $t) use ($schema) {
            if (! $schema->hasColumn('survey_change_reports', 'surveyor_portal_account_id')) $t->unsignedBigInteger('surveyor_portal_account_id')->nullable()->index();
            if (! $schema->hasColumn('survey_change_reports', 'its_key')) $t->string('its_key', 40)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'its_number')) $t->string('its_number', 100)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'portal_beacons')) $t->string('portal_beacons', 4000)->nullable();
            if (! $schema->hasColumn('survey_change_reports', 'submitted_at')) $t->dateTime('submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach (['surveyor_portal_account_id', 'its_key', 'its_number', 'portal_beacons', 'submitted_at'] as $c) {
            if ($schema->hasColumn('survey_change_reports', $c)) {
                if ($c === 'surveyor_portal_account_id') {
                    $schema->table('survey_change_reports', fn (Blueprint $t) => $t->dropIndex(['surveyor_portal_account_id']));
                }
                $schema->table('survey_change_reports', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
