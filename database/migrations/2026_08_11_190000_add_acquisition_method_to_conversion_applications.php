<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Remember the Property Acquisition Method answered for an LGA Confirmation
     * Sheet (LCS). The answer used to live only in the print URL, so every reprint
     * re-asked the same question — and nothing stopped a second print from
     * answering it differently. `conversion_applications` already holds one row
     * per LCS (it is where the sheet's serial_no is kept and reused on reprint),
     * so the answer belongs beside it.
     *
     * acquisition_method — 'a'..'e', the option letters printed on the sheet.
     * acquisition_other  — the free text typed under "e. Any other (Specify)".
     */
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('conversion_applications')) {
            return;
        }

        Schema::connection('sqlsrv')->table('conversion_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('conversion_applications', 'acquisition_method')) {
                $table->string('acquisition_method', 10)->nullable();
            }

            if (!Schema::connection('sqlsrv')->hasColumn('conversion_applications', 'acquisition_other')) {
                $table->string('acquisition_other', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('conversion_applications')) {
            return;
        }

        $drop = array_values(array_filter(
            ['acquisition_method', 'acquisition_other'],
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
