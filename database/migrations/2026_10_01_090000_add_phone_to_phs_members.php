<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A mobile number for each PHS Portal member, and the date it was proved.
 *
 * phs_members had no phone column at all, while PhsMember::$fillable has listed
 * one since the model was written. That is not a harmless mismatch: both
 * PhsOrganizationController::storeMember() and
 * PhsAuthController::registerWithToken() pass 'phone' into create(), so SQL
 * Server answered "Invalid column name 'phone'" and adding a member through the
 * organization console failed outright. This migration is what those two call
 * sites have always assumed was there.
 *
 *   phone              the member's own mobile, in the 0XXXXXXXXXX storage form
 *                      (PhsMember::normalizePhone). NOT the institution's line
 *                      in phs_institutions.phone, which is a shared office
 *                      number and useless as a second factor.
 *   phone_verified_at  set only when a code sent to that number was typed back.
 *                      Null means "never proved", which is what the sign-in gate
 *                      reads; a member who changes their number has it cleared
 *                      and is asked again.
 *
 * Both columns are additive and nullable, so every one of the existing rows
 * stays valid and untouched -- they simply read as "no number yet", which is
 * true. Nothing is read, rewritten or moved by this migration.
 */
return new class extends Migration {
    private const TABLE = 'phs_members';

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable(self::TABLE)) {
            return;
        }

        $schema->table(self::TABLE, function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn(self::TABLE, 'phone')) {
                $table->string('phone', 30)->nullable();
            }

            if (!$schema->hasColumn(self::TABLE, 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable(self::TABLE)) {
            return;
        }

        foreach (['phone', 'phone_verified_at'] as $column) {
            if (!$schema->hasColumn(self::TABLE, $column)) {
                continue;
            }

            $schema->table(self::TABLE, function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        }
    }
};
