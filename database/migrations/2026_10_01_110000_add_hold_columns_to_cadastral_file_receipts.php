<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Investigation hold on cadastral receipts (rebuild plan Phase 3, §6 item 2).
 *
 *   hold_status       NULL | 'On Hold' | 'Cleared'
 *   hold_reason       why the hold was placed — the duplicate / double-allocation
 *                     findings for an automatic hold, the officer's words for a
 *                     manual one
 *   hold_placed_by    users.id, plain integer (no FK, as on the other cadastral_*
 *   hold_placed_at    tables)
 *   hold_cleared_by   users.id of the officer who cleared it
 *   hold_cleared_at
 *   hold_clear_note   the clearing remark; required by the controller
 *
 * The columns hold the CURRENT hold only. Every placement and clearing is also
 * written to audit_logs (CADASTRAL_RECEIPT_HELD / CADASTRAL_RECEIPT_HOLD_CLEARED),
 * which is the history.
 *
 * ALL NULLABLE, NO DEFAULTS, NO INDEXES: an ALTER that only adds nullable
 * columns is a metadata change on SQL Server and does not rewrite the table.
 *
 * The code works before this runs: App\Services\Cadastral\ReceiptHolds checks
 * hasColumn('hold_status') and, while it is missing, hides the hold UI and lets
 * registration behave as it did in Phase 2.
 *
 * EVERY COLUMN IS GUARDED BY hasColumn(), so a part-applied run can be re-run.
 * down() drops only these columns, and only those that exist.
 *
 * DEPLOY WITH THE PATH FLAG. A bare migrate would run 27 unrelated pending
 * migrations against production, and --pretend is NOT a dry run on sqlsrv (it
 * executes):
 *
 *   php artisan migrate --database=sqlsrv \
 *     --path=database/migrations/2026_10_01_110000_add_hold_columns_to_cadastral_file_receipts.php --force
 */
return new class extends Migration
{
    private const CONN  = 'sqlsrv';
    private const TABLE = 'cadastral_file_receipts';

    /** column => definer */
    private function columns(): array
    {
        return [
            'hold_status'     => fn (Blueprint $t) => $t->string('hold_status', 20)->nullable(),
            'hold_reason'     => fn (Blueprint $t) => $t->string('hold_reason', 1000)->nullable(),
            'hold_placed_by'  => fn (Blueprint $t) => $t->unsignedBigInteger('hold_placed_by')->nullable(),
            'hold_placed_at'  => fn (Blueprint $t) => $t->dateTime('hold_placed_at')->nullable(),
            'hold_cleared_by' => fn (Blueprint $t) => $t->unsignedBigInteger('hold_cleared_by')->nullable(),
            'hold_cleared_at' => fn (Blueprint $t) => $t->dateTime('hold_cleared_at')->nullable(),
            'hold_clear_note' => fn (Blueprint $t) => $t->string('hold_clear_note', 1000)->nullable(),
        ];
    }

    public function up(): void
    {
        $s = Schema::connection(self::CONN);

        if (! $s->hasTable(self::TABLE)) {
            return;
        }

        foreach ($this->columns() as $column => $define) {
            if (! $s->hasColumn(self::TABLE, $column)) {
                $s->table(self::TABLE, fn (Blueprint $t) => $define($t));
            }
        }
    }

    public function down(): void
    {
        $s = Schema::connection(self::CONN);

        if (! $s->hasTable(self::TABLE)) {
            return;
        }

        foreach (array_keys($this->columns()) as $column) {
            if ($s->hasColumn(self::TABLE, $column)) {
                $s->table(self::TABLE, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
