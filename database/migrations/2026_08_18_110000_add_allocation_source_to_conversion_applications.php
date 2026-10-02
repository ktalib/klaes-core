<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Remember the Allocation Source confirmed for a Confirmation Sheet (CS).
     *
     * The print card now confirms Allocation Source side by side with the
     * Property Acquisition Method, because the source decides who the sheet is
     * addressed to: a Local Government source addresses its Chairman, a State
     * Government source addresses the allocating entity (KSIP / HOUSING /
     * KUNPDA / a typed one). Kept beside the acquisition answer on the same
     * one-row-per-sheet table so a reprint cannot contradict the copy issued.
     *
     * allocation_source  — 'State Government' | 'Local Government'
     * allocation_entity  — the LGA, or the state entity the sheet goes to
     * allocation_address — the address block printed under a state entity, which
     *                      has no "<name> Local Government, Kano State" form to
     *                      fall back on
     */
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('conversion_applications')) {
            return;
        }

        Schema::connection('sqlsrv')->table('conversion_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('conversion_applications', 'allocation_source')) {
                $table->string('allocation_source', 100)->nullable();
            }

            if (!Schema::connection('sqlsrv')->hasColumn('conversion_applications', 'allocation_entity')) {
                $table->string('allocation_entity', 100)->nullable();
            }

            if (!Schema::connection('sqlsrv')->hasColumn('conversion_applications', 'allocation_address')) {
                $table->string('allocation_address', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('conversion_applications')) {
            return;
        }

        $drop = array_values(array_filter(
            ['allocation_source', 'allocation_entity', 'allocation_address'],
            fn ($column) => Schema::connection('sqlsrv')->hasColumn('conversion_applications', $column)
        ));

        if ($drop === []) {
            return;
        }

        Schema::connection('sqlsrv')->table('conversion_applications', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });
    }
};
