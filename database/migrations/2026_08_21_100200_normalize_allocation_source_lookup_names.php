<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The seeded addressee list spells the judicial title without its period.
 * Every other entry in that list is punctuated ("HON. COMMISSIONER"), and the
 * name is what gets printed on the letter, so it is corrected here.
 *
 * Idempotent: does nothing when the corrected name is already present.
 *
 * "OTHERS (SPECIFY)" is deliberately NOT seeded — it is a UI sentinel. Picking
 * it and typing a name routes through AllocationSourceLookup::remember(),
 * which adds the real name to the list for next time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('allocation_source_lookups')) {
            return;
        }

        $table = DB::connection('sqlsrv')->table('allocation_source_lookups');

        $alreadyCorrect = (clone $table)
            ->where('type', 'addressed_to_other')
            ->where('name', 'HON. JUDGE')
            ->exists();

        if ($alreadyCorrect) {
            // Drop the unpunctuated duplicate rather than colliding with the unique key.
            (clone $table)->where('type', 'addressed_to_other')->where('name', 'HON JUDGE')->delete();

            return;
        }

        (clone $table)
            ->where('type', 'addressed_to_other')
            ->where('name', 'HON JUDGE')
            ->update(['name' => 'HON. JUDGE', 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('allocation_source_lookups')) {
            return;
        }

        DB::connection('sqlsrv')->table('allocation_source_lookups')
            ->where('type', 'addressed_to_other')
            ->where('name', 'HON. JUDGE')
            ->update(['name' => 'HON JUDGE', 'updated_at' => now()]);
    }
};
