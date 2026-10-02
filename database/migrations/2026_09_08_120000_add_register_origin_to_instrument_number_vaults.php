<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * instrument_number_vaults.start_volume / start_serial — where a register began.
 *
 * WHY
 * The "Fill a missing registration number" picker measures a register from the
 * first number KLAES ever issued in it up to the number its vault stands on now,
 * and lists everything in between that no registration holds. Until now that
 * floor was INFERRED: the lowest surviving registration in the register's lowest
 * volume. An inferred floor cannot tell "never issued" from "issued and then
 * deleted", so a hole at the very START of a register is invisible by
 * construction — delete the earliest registration and the floor silently slides
 * up to the next one, taking the hole with it.
 *
 * That is what the Deed of Mortgage register looks like today. Its four
 * KLAES-era registrations run 89/89/22 .. 92/92/22 unbroken, so the survey
 * reports nothing missing and the picker never appears — an honest answer to the
 * question it is able to ask, and the wrong answer if the book really began
 * below 89.
 *
 * WHAT THESE COLUMNS ARE
 * The office's own record of the first number in the book, per vault, set on the
 * "Registration Vault Configuration" panel under Manage Instrument Types. When
 * they are set the survey walks from THERE — through volumes holding nothing,
 * which is the point: a book nobody has captured is missing every number in it.
 *
 * NULL MEANS "AS BEFORE". Both columns nullable with no default, and
 * MissingRegistrationParticularsService falls back to the inferred floor
 * whenever either is missing, absent, or describes no register at all (a floor
 * above the vault's own position). So nothing changes for any register until
 * somebody records where it started, and a site that has not run this migration
 * yet surveys exactly as it did.
 *
 * NOTHING HERE NUMBERS ANYTHING. The allocator never reads these columns — they
 * are the floor of a survey, not a starting point for issuing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('instrument_number_vaults', 'start_volume')) {
            $schema->table('instrument_number_vaults', function (Blueprint $table) {
                $table->integer('start_volume')->nullable();
            });
        }

        if (!$schema->hasColumn('instrument_number_vaults', 'start_serial')) {
            $schema->table('instrument_number_vaults', function (Blueprint $table) {
                $table->integer('start_serial')->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (['start_volume', 'start_serial'] as $column) {
            if ($schema->hasColumn('instrument_number_vaults', $column)) {
                $schema->table('instrument_number_vaults', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
