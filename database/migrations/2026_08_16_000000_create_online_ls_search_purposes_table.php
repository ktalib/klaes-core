<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lookup of the purposes a member of the public may give for an Online Legal
 * Search. The public portal accepts nothing outside this list.
 *
 * Deliberately separate from `request_purposes`, which is the internal
 * file-tracking list (~50 workflow purposes such as "5% PAYMENT" or "CAVEAT")
 * and is not meaningful to a public requester.
 */
return new class extends Migration
{
    /**
     * Seeded set. Codes are stable identifiers; names are what the public sees.
     */
    protected array $purposes = [
        ['code' => 'verification_confirmation', 'name' => 'Verification/Confirmation', 'sort_order' => 1],
        ['code' => 'bill_balance',              'name' => 'Bill Balance',              'sort_order' => 2],
        ['code' => 'title_status',              'name' => 'Title Status',              'sort_order' => 3],
        ['code' => 'encumbrance_verification',  'name' => 'Encumbrance Verification',  'sort_order' => 4],
    ];

    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('online_ls_search_purposes')) {
            Schema::connection('sqlsrv')->create('online_ls_search_purposes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name', 150);
                $table->string('description', 255)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
                $table->index('sort_order');
            });
        }

        // Seed idempotently so re-running never duplicates or overwrites edits.
        foreach ($this->purposes as $purpose) {
            $exists = DB::connection('sqlsrv')->table('online_ls_search_purposes')
                ->where('code', $purpose['code'])
                ->exists();

            if (!$exists) {
                DB::connection('sqlsrv')->table('online_ls_search_purposes')->insert($purpose + [
                    'is_active'  => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('online_ls_search_purposes');
    }
};
