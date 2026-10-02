<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records WHICH kind of match an officer declared before moving permits onto a
 * file's Property ID:
 *
 *   change_of_ownership    — the permits are being attached to a file whose
 *                            ownership has changed hands
 *   no_change_of_ownership — the permits belong to the file as it already stands
 *
 * The officer picks one before any matching is possible, and it is written onto
 * every row of the batch. This is a land registry: the basis on which a batch of
 * ownership records was reassigned is exactly the sort of thing that gets asked
 * about later, and `op_propid_matches` is already the audit trail for it — a
 * choice held only in the browser would answer nothing after the fact.
 *
 * Nullable on purpose: every row written before this existed has no declared
 * mode, and inventing one for them would be a lie.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = 'op_propid_matches';

        if (!Schema::connection('sqlsrv')->hasTable($table)) {
            return;
        }

        if (!Schema::connection('sqlsrv')->hasColumn($table, 'match_mode')) {
            Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) {
                $blueprint->string('match_mode', 30)->nullable();
            });
        }

        // Constrained in the database too, so a value the application does not
        // recognise can never reach the audit trail.
        DB::connection('sqlsrv')->statement(
            "IF OBJECT_ID('chk_opm_match_mode', 'C') IS NULL
             ALTER TABLE op_propid_matches
             ADD CONSTRAINT chk_opm_match_mode
             CHECK (match_mode IS NULL OR match_mode IN ('change_of_ownership','no_change_of_ownership'))"
        );
    }

    public function down(): void
    {
        $table = 'op_propid_matches';

        if (!Schema::connection('sqlsrv')->hasTable($table)) {
            return;
        }

        DB::connection('sqlsrv')->statement(
            "IF OBJECT_ID('chk_opm_match_mode', 'C') IS NOT NULL
             ALTER TABLE op_propid_matches DROP CONSTRAINT chk_opm_match_mode"
        );

        if (Schema::connection('sqlsrv')->hasColumn($table, 'match_mode')) {
            Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('match_mode');
            });
        }
    }
};
