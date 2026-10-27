<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Computation File (Field Jacket) attached to the correspondence file (change request
 * 26-10-2026; user 2026-10-27: AD P&D commissions it at step 21, Registry detaches it at step 31).
 * Additive: the correspondence file it was attached to (both numbers, as the file page shows
 * them), when and by whom it was attached, and when, by whom and why it was detached.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->table('cadastral_field_jackets', function (Blueprint $table) use ($schema) {
            $has = fn (string $c) => $schema->hasColumn('cadastral_field_jackets', $c);
            if (!$has('correspondence_fileno')) {
                $table->string('correspondence_fileno', 100)->nullable();
            }
            if (!$has('shadow_fileno')) {
                $table->string('shadow_fileno', 100)->nullable();
            }
            if (!$has('attached_at')) {
                $table->dateTime('attached_at')->nullable();
            }
            if (!$has('attached_by')) {
                $table->unsignedBigInteger('attached_by')->nullable();
            }
            if (!$has('detached_at')) {
                $table->dateTime('detached_at')->nullable();
            }
            if (!$has('detached_by')) {
                $table->unsignedBigInteger('detached_by')->nullable();
            }
            if (!$has('detach_note')) {
                $table->string('detach_note', 1000)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('cadastral_field_jackets', function (Blueprint $table) {
            $table->dropColumn(['correspondence_fileno', 'shadow_fileno', 'attached_at', 'attached_by', 'detached_at', 'detached_by', 'detach_note']);
        });
    }
};
