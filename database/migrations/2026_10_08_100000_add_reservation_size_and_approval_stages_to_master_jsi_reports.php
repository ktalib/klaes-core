<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master JSI: Section 9 (reservation & size) and the two-stage approval.
 *
 * Additive only. The existing road_reservation column is kept and now reads as
 * "Existing Road Reservation", so nothing already captured moves.
 *
 *   recommended_reservation  the reservation Physical Planning recommends
 *   size_adequate            "Is the size adequate?" Yes (1) / No (0) / not answered (NULL)
 *   director_approved_*      PP Director stage: submitted -> director_approved
 *   ps_approved_*            PS stage: director_approved -> approved (what MasterJsiGate reads)
 */
return new class extends Migration {
    private const COLUMNS = [
        'recommended_reservation',
        'size_adequate',
        'director_approved_at',
        'director_approved_by',
        'director_remarks',
        'ps_approved_at',
        'ps_approved_by',
        'ps_remarks',
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('master_jsi_reports', function (Blueprint $table) use ($schema) {
            $has = fn ($c) => $schema->hasColumn('master_jsi_reports', $c);

            if (! $has('recommended_reservation')) $table->string('recommended_reservation', 700)->nullable();
            if (! $has('size_adequate'))           $table->boolean('size_adequate')->nullable();
            if (! $has('director_approved_at'))    $table->dateTime('director_approved_at')->nullable();
            if (! $has('director_approved_by'))    $table->unsignedBigInteger('director_approved_by')->nullable();
            if (! $has('director_remarks'))        $table->text('director_remarks')->nullable();
            if (! $has('ps_approved_at'))          $table->dateTime('ps_approved_at')->nullable();
            if (! $has('ps_approved_by'))          $table->unsignedBigInteger('ps_approved_by')->nullable();
            if (! $has('ps_remarks'))              $table->text('ps_remarks')->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        $present = array_values(array_filter(self::COLUMNS, fn ($c) => $schema->hasColumn('master_jsi_reports', $c)));

        if ($present) {
            $schema->table('master_jsi_reports', fn (Blueprint $table) => $table->dropColumn($present));
        }
    }
};
