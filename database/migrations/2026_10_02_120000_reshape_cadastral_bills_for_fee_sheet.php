<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cadastral_bills, reshaped for the official fee sheet (rebuild plan Phase 7,
 * D1/D2, §3b): "Right of Occupancy - Cadastral Fees and Area".
 *
 * EVERY LINE KEEPS ITS OWN FIGURES. Each of the eight sheet lines gets a unit
 * rate, a quantity and an amount, copied onto the bill when it is issued, so a
 * reprint shows what the applicant was charged even after an admin edits the
 * rates in Configurable Entries -> Cadastral:
 *
 *   investigation_*  Investigation and Search   rate x 1
 *   beacon_*         Beacons                    rate x live cadastral_pillars rows
 *   area_*           Area fee                   schedule fee x 1 (area_ha alongside)
 *   delay_*          Delay                      rate x days
 *   transport_*      Transport                  band fee x 1 (0 when none)
 *   field_work_*     Additional field work      rate x days
 *   office_work_*    Office work                rate x days
 *   plan_print_*     Plan prints                rate x count
 *
 * plus the area and how it was looked up (area_sqm, area_ha, schedule_column,
 * schedule_row_ha, between_rows_rule, above_max_rule), the transport band
 * chosen, the rules still unconfirmed when it was issued (Q1-Q3), the header
 * fields of the sheet (re_cad_ref, rofo_no, bill_date) and the cancellation
 * trail (superseded_by_bill_id, cancelled_*).
 *
 * ADDITIVE ONLY. Every column is nullable and hasColumn-guarded, so a
 * part-applied run can be re-run. The old formula columns (area_rate_per_sqm,
 * land_use_multiplier, zone_multiplier, service_charge_percent, description_fee,
 * service_charge, description_fee_amount) are NOT dropped: they have defaults,
 * nothing writes them any more, and they read 0 / 1 on every new bill. Three old
 * columns keep their meaning and are still filled, because DashboardMetrics
 * reads them: area_fee (= area_amount), pillar_fee (= beacon_amount) with
 * pillar_count_government / pillar_count_private / pillar_unit_price, and
 * grand_total (= the sheet's Total; Fees YTD and Bills Raised sum it).
 *
 * Until this runs the Fee Calculator still previews the official lines, but
 * Save Bill is disabled ("pending installation").
 *
 * down() drops only the columns this adds, and only if they exist.
 *
 * DEPLOY WITH THE PATH FLAG. A bare migrate would run unrelated pending
 * migrations against production, and --pretend is NOT a dry run on sqlsrv (it
 * executes):
 *
 *   php artisan migrate --database=sqlsrv \
 *     --path=database/migrations/2026_10_02_120000_reshape_cadastral_bills_for_fee_sheet.php --force
 */
return new class extends Migration
{
    private const CONN = 'sqlsrv';

    private const TABLE = 'cadastral_bills';

    /** The eight fee-sheet lines, in sheet order. */
    private const LINES = ['investigation', 'beacon', 'area', 'delay', 'transport', 'field_work', 'office_work', 'plan_print'];

    public function up(): void
    {
        $schema = Schema::connection(self::CONN);

        if (! $schema->hasTable(self::TABLE)) {
            return;
        }

        foreach ($this->columns() as $name => $add) {
            if (! $schema->hasColumn(self::TABLE, $name)) {
                $schema->table(self::TABLE, fn (Blueprint $t) => $add($t));
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONN);

        if (! $schema->hasTable(self::TABLE)) {
            return;
        }

        foreach (array_keys($this->columns()) as $name) {
            if ($schema->hasColumn(self::TABLE, $name)) {
                $schema->table(self::TABLE, fn (Blueprint $t) => $t->dropColumn($name));
            }
        }
    }

    /** @return array<string, callable(Blueprint): mixed> column name => adder */
    private function columns(): array
    {
        $cols = [];

        foreach (self::LINES as $line) {
            $cols["{$line}_rate"]   = fn (Blueprint $t) => $t->decimal("{$line}_rate", 14, 2)->nullable();
            $cols["{$line}_qty"]    = fn (Blueprint $t) => $t->integer("{$line}_qty")->nullable();
            $cols["{$line}_amount"] = fn (Blueprint $t) => $t->decimal("{$line}_amount", 16, 2)->nullable();
        }

        return $cols + [
            // The area and how the schedule was read for it.
            'area_sqm'              => fn (Blueprint $t) => $t->decimal('area_sqm', 16, 3)->nullable(),
            'area_ha'               => fn (Blueprint $t) => $t->decimal('area_ha', 12, 4)->nullable(),
            'schedule_column'       => fn (Blueprint $t) => $t->string('schedule_column', 20)->nullable(),   // proposed | current
            'schedule_row_ha'       => fn (Blueprint $t) => $t->decimal('schedule_row_ha', 8, 2)->nullable(), // the row charged
            'between_rows_rule'     => fn (Blueprint $t) => $t->string('between_rows_rule', 20)->nullable(), // next_row_up | interpolate
            'above_max_rule'        => fn (Blueprint $t) => $t->string('above_max_rule', 20)->nullable(),    // refuse | charge_max_row

            // Transport as chosen.
            'transport_band_label'  => fn (Blueprint $t) => $t->string('transport_band_label', 100)->nullable(),
            'transport_km'          => fn (Blueprint $t) => $t->decimal('transport_km', 8, 2)->nullable(),

            // Q1/Q2/Q3 still unconfirmed when the bill was issued, e.g. "Q1,Q3".
            'unconfirmed_rules'     => fn (Blueprint $t) => $t->string('unconfirmed_rules', 100)->nullable(),

            // The sheet's header.
            're_cad_ref'            => fn (Blueprint $t) => $t->string('re_cad_ref', 100)->nullable(),
            'rofo_no'               => fn (Blueprint $t) => $t->string('rofo_no', 100)->nullable(),
            'bill_date'             => fn (Blueprint $t) => $t->date('bill_date')->nullable(),

            // Cancelled, never deleted.
            'superseded_by_bill_id' => fn (Blueprint $t) => $t->unsignedBigInteger('superseded_by_bill_id')->nullable(),
            'cancelled_at'          => fn (Blueprint $t) => $t->dateTime('cancelled_at')->nullable(),
            'cancelled_by'          => fn (Blueprint $t) => $t->unsignedBigInteger('cancelled_by')->nullable(),
            'cancel_reason'         => fn (Blueprint $t) => $t->string('cancel_reason', 500)->nullable(),
        ];
    }
};
