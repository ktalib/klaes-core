<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A contact number for the holder, captured once and carried forward.
 *
 * The valuation is the first time the Ministry visits a property and writes
 * down who holds it, so it is the right place to take the number -- but
 * valuation_reports had no column for one, and neither did consent. The number
 * was therefore re-typed at every stage, or more often simply lost, and a file
 * in the middle of Valuation -> Consent -> Registration had no way to reach its
 * own applicant.
 *
 *   valuation_reports.phone            where the number is first taken. Required
 *                                      on the form from now on; nullable in the
 *                                      column because every report captured
 *                                      before today has none, and an existing
 *                                      report is still a real record.
 *   consent_applications.applicant_phone  backfilled from the file's valuation
 *                                      when the file number is chosen, and read
 *                                      by Instrument Capture in turn.
 *
 * instrument_capture needs nothing: it already carries party_1_phone through
 * party_5_phone and solicitor_phone.
 *
 * Both columns are additive and nullable. No existing row is read, rewritten or
 * moved by this migration.
 */
return new class extends Migration {
    /** table => [column, length] */
    private const COLUMNS = [
        'valuation_reports' => ['phone', 30],
        'consent_applications' => ['applicant_phone', 30],
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (self::COLUMNS as $table => [$column, $length]) {
            if (!$schema->hasTable($table) || $schema->hasColumn($table, $column)) {
                continue;
            }

            $schema->table($table, function (Blueprint $blueprint) use ($column, $length) {
                $blueprint->string($column, $length)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (self::COLUMNS as $table => [$column, $length]) {
            if (!$schema->hasTable($table) || !$schema->hasColumn($table, $column)) {
                continue;
            }

            $schema->table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropColumn($column);
            });
        }
    }
};
