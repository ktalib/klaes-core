<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * CofO Type captured on the Occupancy Permit Details card in File Indexing.
     * Occupancy Permit rows live in `pra` (alongside RofO), which had no column for it,
     * so the dropdown had nowhere to persist. Canonical values match
     * resources/views/partials/cofo_type_options.blade.php:
     * Land CofO / KANGIS CofO - Old / KANGIS CofO - New / SLTR CofO / ST CofO.
     */
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('pra')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('pra', 'cofo_type')) {
            return;
        }

        Schema::connection('sqlsrv')->table('pra', function (Blueprint $table) {
            $table->string('cofo_type', 100)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('pra')) {
            return;
        }

        if (!Schema::connection('sqlsrv')->hasColumn('pra', 'cofo_type')) {
            return;
        }

        Schema::connection('sqlsrv')->table('pra', function (Blueprint $table) {
            $table->dropColumn('cofo_type');
        });
    }
};
