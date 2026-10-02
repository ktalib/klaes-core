<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Type and one Category per captured instrument.
 *
 *   Instrument Type           Type                                 | Category
 *   ------------------------  -----------------------------------  | ---------------
 *   Plot Allocation Letter    Land, LGA, Urban Development Board   | —
 *   Occupancy Permit          Resettlement, Direct Allocation, LGA | Old, New
 *   Certificate of Occupancy  Land, Old KANGIS, New KANGIS, LGA    | Old, New
 *   Right of Occupancy        Land, LGA                            | Old, New
 *
 * App\Services\InstrumentTypeCatalog holds that table and is the only copy of it.
 *
 * WHAT THIS ADDS
 *   instrument_category  nvarchar(100) NULL  the Category answer, for every instrument
 *   instrument_subtype   nvarchar(100) NULL  the Type answer, ONLY for instruments with
 *                                            no column of their own — Right of Occupancy
 *                                            and Plot Allocation Letter
 *
 * Type otherwise keeps the column its instrument has always used — op_type for an
 * Occupancy Permit (34k rows), cofo_type for a Certificate of Occupancy (15.5k rows) —
 * so no existing value moves and no reader downstream has to change.
 *
 * WHAT THIS DROPS
 *   pra.op_category — added earlier the same day for a two-value Old OP / New OP
 *   question on Occupancy Permits only. The table above makes Category a question for
 *   three instruments, so it is answered by instrument_category instead. Nothing is
 *   lost: op_category never held a row. Guarded by hasColumn(), so it is a no-op
 *   wherever that column was never deployed.
 *
 * FOUR TABLES, because a capture screen picks its destination from the instrument:
 *   file_history_staging  anything that is neither a CofO nor an Occupancy Permit —
 *                         which is what the Plot Allocation Letter and Right of Occupancy are
 *   CofO_staging          Certificate of Occupancy, and its ST/SLTR variants
 *   pra                   Occupancy Permits, and everything the PRA card writes
 *   pic                   the PRA card's target in record_mode=index
 *
 * Nullable, and NO BACKFILL: no existing row was captured with either question put,
 * and neither answer can be inferred from what is already stored.
 */
return new class extends Migration
{
    private const TABLES = ['pra', 'file_history_staging', 'CofO_staging', 'pic'];

    private const COLUMNS = ['instrument_category', 'instrument_subtype'];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table)) {
                continue;
            }

            foreach (self::COLUMNS as $column) {
                if (!$schema->hasColumn($table, $column)) {
                    $schema->table($table, function (Blueprint $t) use ($column) {
                        $t->string($column, 100)->nullable();
                    });
                }
            }
        }

        // Superseded by instrument_category. Never held a row.
        if ($schema->hasColumn('pra', 'op_category')) {
            $schema->table('pra', function (Blueprint $t) {
                $t->dropColumn('op_category');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('pra') && !$schema->hasColumn('pra', 'op_category')) {
            $schema->table('pra', function (Blueprint $t) {
                $t->string('op_category', 50)->nullable();
            });
        }

        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table)) {
                continue;
            }

            foreach (self::COLUMNS as $column) {
                if ($schema->hasColumn($table, $column)) {
                    $schema->table($table, function (Blueprint $t) use ($column) {
                        $t->dropColumn($column);
                    });
                }
            }
        }
    }
};
