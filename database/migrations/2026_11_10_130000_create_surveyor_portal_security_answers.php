<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Security questions for the MLPP-SG portal (user, 2026-11-10), the twin of user_security_answers.
 | Portal accounts are not staff users and their ids overlap, so they get their own table. The answer is
 | normalised and hashed like a password (SurveyorSecurityQuestions), never stored readable.
 | Additive: a new table only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasTable('surveyor_portal_security_answers')) {
            return;
        }

        $s->create('surveyor_portal_security_answers', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('surveyor_portal_account_id');
            $t->string('question_key', 60);
            $t->string('answer_hash', 255);
            $t->timestamps();

            $t->unique(['surveyor_portal_account_id', 'question_key'], 'ux_sp_security_answers_account_question');
            $t->index('surveyor_portal_account_id', 'ix_sp_security_answers_account');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('surveyor_portal_security_answers');
    }
};
