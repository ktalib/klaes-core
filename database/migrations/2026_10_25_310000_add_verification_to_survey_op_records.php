<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compensation > Records > OP Verification (2026-10-25): Survey checks each generated OP
 * and marks it ready for Deeds. Survey-side only (user decision): "Sent to Deeds" is a
 * stamp on the Survey record; nothing is written into the Deeds module.
 * Additive only: nullable columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasColumn('survey_op_records', 'verification_status')) {
            return;
        }

        $s->table('survey_op_records', function (Blueprint $t) {
            $t->string('verification_status', 20)->nullable()->index();   // Pending | Verified | Returned | Sent to Deeds
            $t->string('verification_notes', 4000)->nullable();
            $t->dateTime('verified_at')->nullable();
            $t->unsignedBigInteger('verified_by')->nullable();
            $t->dateTime('sent_to_deeds_at')->nullable();
            $t->unsignedBigInteger('sent_to_deeds_by')->nullable();
        });
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        if (! $s->hasColumn('survey_op_records', 'verification_status')) {
            return;
        }
        $s->table('survey_op_records', function (Blueprint $t) {
            $t->dropIndex(['verification_status']);
            $t->dropColumn(['verification_status', 'verification_notes', 'verified_at', 'verified_by', 'sent_to_deeds_at', 'sent_to_deeds_by']);
        });
    }
};
