<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approve / reject as a permission of its own.
 *
 * Approving a recommendation, a memo or an application is an act of authority, not an edit —
 * an officer who may correct a record is not automatically an officer who may approve it. Until
 * now the two were the same permission: the 29 approve/reject routes in this app all matched
 * the `*approve*` / `*reject*` patterns in the `edit` group.
 *
 * THE BACKFILL IS THE POINT OF THIS MIGRATION.
 *
 * Adding a column that defaults to 0 would have silently stripped approval from every officer
 * who has it, because the rollout rule that lets a missing grant through only applies when the
 * user has NO ROW for that module — and permissions:backfill already gave all 537 users a row
 * for every module they hold. The column would exist, read false, and deny.
 *
 * So can_approve is seeded from can_edit: whatever a user could approve yesterday through the
 * edit permission, they can approve today through the approve permission. Nobody gains anything
 * either — a user without edit gets can_approve = 0, which is what they already had.
 *
 * Narrowing who may approve is now a deliberate act on the user's permission grid, which is
 * what was asked for.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    private const TABLE = 'module_permissions';

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable(self::TABLE)) {
            return;
        }

        if (!$schema->hasColumn(self::TABLE, 'can_approve')) {
            $schema->table(self::TABLE, function (Blueprint $table) {
                $table->boolean('can_approve')->default(false)->after('can_edit');
            });
        }

        // Preserve what people can do today. See the note above — this is not a convenience.
        DB::connection('sqlsrv')->table(self::TABLE)->update([
            'can_approve' => DB::raw('can_edit'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable(self::TABLE) && $schema->hasColumn(self::TABLE, 'can_approve')) {
            $schema->table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn('can_approve');
            });
        }
    }
};
