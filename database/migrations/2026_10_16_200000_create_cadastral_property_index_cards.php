<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Property Index Cards (user, 2026-10-16): one card per file, generated from the Legal Search
 * engine and filed as a PDF to EDMS/SCAN_UPLOAD/Cadastral_Registry/PIC/{FILE NUMBER}/.
 * A row per file: where its PDF is and when it was last generated. Regenerating overwrites
 * the PDF and updates the row; the PDF itself is the card.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_16_200000_create_cadastral_property_index_cards.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('cadastral_property_index_cards')) {
            $s->create('cadastral_property_index_cards', function (Blueprint $t) {
                $t->id();
                $t->string('file_number', 100)->unique();
                $t->unsignedBigInteger('file_indexing_id')->nullable();
                $t->string('file_title', 300)->nullable();
                $t->string('path', 500)->nullable();
                $t->integer('transactions_count')->default(0);
                $t->string('trigger', 20);                       // intake | manual
                $t->unsignedBigInteger('cadastral_case_id')->nullable();
                $t->unsignedBigInteger('generated_by')->nullable();
                $t->dateTime('generated_at')->nullable();
                $t->integer('generation_count')->default(0);
                $t->string('last_error', 1000)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('cadastral_property_index_cards');
    }
};
