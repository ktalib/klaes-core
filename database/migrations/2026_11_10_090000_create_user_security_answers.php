<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Security questions, the sign-in code fallback (user, 2026-11-10). One row per answered question; the
 | answer is normalised and hashed like a password (SecurityQuestionService), never stored readable.
 | question_key is a key of config/security_questions.php. Additive: a new table only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasTable('user_security_answers')) {
            return;
        }

        $s->create('user_security_answers', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('user_id');
            $t->string('question_key', 60);
            $t->string('answer_hash', 255);
            $t->timestamps();

            $t->unique(['user_id', 'question_key'], 'ux_user_security_answers_user_question');
            $t->index('user_id', 'ix_user_security_answers_user');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('user_security_answers');
    }
};
