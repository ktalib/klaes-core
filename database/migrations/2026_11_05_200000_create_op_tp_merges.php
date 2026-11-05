<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TP groups that are typing slips of another layout (user, 2026-11-05: TP/MLPP/DKD/0O1, TP/,LPP/DKD/001 and
 * TP/MLPP/DKD/00 "should be the same" as TP/MLPP/DKD/01). The nightly OP TP sync folds each from_key group
 * into to_key. Deeds rows are never written; OPs keep the TP No as typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('op_tp_merges')) return;

        Schema::connection('sqlsrv')->create('op_tp_merges', function (Blueprint $t) {
            $t->id();
            $t->string('from_key', 150)->unique('ux_op_tp_merges_from');
            $t->string('to_key', 150);
            $t->string('from_label', 255)->nullable();
            $t->string('to_label', 255)->nullable();
            $t->string('note', 500)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('op_tp_merges');
    }
};
