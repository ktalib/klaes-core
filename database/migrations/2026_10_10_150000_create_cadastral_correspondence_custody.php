<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Department workflow, step 12: Registry's Attach / Detach
 * correspondence file, kept as a history per case (the last row says where the
 * correspondence file is). "Send to Deeds" checks the file was detached.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_10_150000_create_cadastral_correspondence_custody.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('cadastral_correspondence_custody')) {
            $s->create('cadastral_correspondence_custody', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_case_id');
                $t->string('action', 10);                       // detach | attach
                $t->string('step', 50);                         // workflow step it was done at
                $t->string('correspondence_fileno', 100)->nullable();
                $t->string('shadow_fileno', 50)->nullable();
                $t->string('note', 1000)->nullable();           // e.g. where it was archived
                $t->unsignedBigInteger('done_by')->nullable();
                $t->dateTime('done_at');
                $t->timestamps();
                $t->index('cadastral_case_id');
            });
        }
    }

    public function down(): void
    {
        // Left in place on purpose: no backups here, and the rows are records.
    }
};
