<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SURCON Beacon Register (Survey > Examination/Verification > Beacon Register),
 * the digital copy of the printed register book (user, 2026-10-22).
 *
 * One row per issue line. The beacon range is kept as typed (beacon_from /
 * beacon_to) and also split into series + numbers so overlaps can be checked.
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasTable('survey_beacon_register')) {
            return;
        }

        $s->create('survey_beacon_register', function (Blueprint $t) {
            $t->id();
            $t->string('register_no', 30)->nullable();      // printed page no., e.g. 201827
            $t->date('issue_date');
            $t->tinyInteger('quarter');
            $t->tinyInteger('month');
            $t->smallInteger('year');
            $t->string('beacon_from', 30);
            $t->string('beacon_to', 30)->nullable();
            // Parsed range: "C9327A" -> series "C|A", number 9327. Null when unparseable.
            $t->string('beacon_series', 30)->nullable();
            $t->integer('beacon_start_no')->nullable();
            $t->integer('beacon_end_no')->nullable();
            $t->integer('total_issued');
            $t->string('plan_no', 100)->nullable();
            $t->string('surveyor_name', 255);
            $t->string('surveyor_registration', 50)->nullable();
            $t->string('location', 255)->nullable();
            $t->string('payment_mode', 30)->nullable();      // E-Payment | Cash | Other
            $t->string('payment_mode_other', 100)->nullable();
            $t->string('teller_no', 100)->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->date('date_received')->nullable();
            $t->string('received_by', 255)->nullable();       // the book's Signature column
            $t->string('remark', 1000)->nullable();
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->dateTime('reviewed_at')->nullable();
            $t->unsignedBigInteger('audited_by')->nullable();
            $t->dateTime('audited_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index('issue_date');
            $t->index(['year', 'quarter', 'month']);
            $t->index(['beacon_series', 'beacon_start_no', 'beacon_end_no'], 'ix_beacon_range');
            $t->index('surveyor_name');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_beacon_register');
    }
};
