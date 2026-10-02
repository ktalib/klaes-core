<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pra.op_category — whether an Occupancy Permit is an Old OP or a New OP.
 *
 * A sibling of op_type, not a replacement for it. op_type says HOW the permit was
 * granted (Resettlement / Direct Allocation / LGA); op_category says WHICH GENERATION
 * of permit the paper is. The two are independent, so they need separate columns.
 *
 * It is asked only for Resettlement and Direct Allocation. The two LGA kinds are
 * outside the question entirely: a Local Government registers nothing in the State
 * deeds registry, so an LGA permit has no generation to place it in.
 *
 * What it changes: an Old OP predates the registry practice that produced a serial,
 * page and volume, so its registration particulars are OPTIONAL. On a New OP they
 * stay required, exactly as before.
 *
 * Nullable, and blank is meaningful: ~4,500 Occupancy Permit rows predate the field
 * and nobody has looked at the paper to say which generation they are. Blank
 * therefore behaves as a New OP does today — registration particulars still
 * required — so no historic row silently loses a rule it was captured under.
 * NO BACKFILL: guessing from a date or a serial would put an unverified fact on
 * the record.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('pra', 'op_category')) {
            $schema->table('pra', function (Blueprint $table) {
                $table->string('op_category', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('pra', 'op_category')) {
            $schema->table('pra', function (Blueprint $table) {
                $table->dropColumn('op_category');
            });
        }
    }
};
