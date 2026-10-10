<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPAS Bill Items: "removed" as its own state, apart from "switched off".
 *
 * Removing an item from the tariff used to only set is_active = 0, so it came
 * straight back in the settings list. A removed item is now flagged, never
 * deleted: bill lines already raised still point at it (spa_bill_lines keeps
 * its own copy of the name and amount), and re-adding the same name revives
 * the row rather than colliding with UQ_spa_bill_items_name.
 *
 * Additive only.
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('spa_bill_items', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('spa_bill_items', 'is_removed')) $table->boolean('is_removed')->default(false);
            if (! $schema->hasColumn('spa_bill_items', 'removed_at')) $table->dateTime('removed_at')->nullable();
            if (! $schema->hasColumn('spa_bill_items', 'removed_by')) $table->string('removed_by', 255)->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $present = array_values(array_filter(['is_removed', 'removed_at', 'removed_by'], fn ($c) => $schema->hasColumn('spa_bill_items', $c)));

        if ($present) {
            // SQL Server keeps a default constraint on is_removed; Laravel drops it with the column.
            $schema->table('spa_bill_items', fn (Blueprint $table) => $table->dropColumn($present));
        }
    }
};
