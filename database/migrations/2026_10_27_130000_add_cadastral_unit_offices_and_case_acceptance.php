<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * File movement logging inside Cadastral (user, 2026-10-27).
 *
 *  - offices: the five Cadastral units with no office of their own, so the Ministry
 *    file tracker (file_tracker.movement_log) can show a file at the exact unit.
 *    Added only when the code is missing; existing offices are not touched.
 *  - cadastral_cases: accepted_at / accepted_by (null accepted_at = waiting for the unit
 *    to accept the file it was sent), and file_tracker_id (the tracker the moves go to).
 *
 * Additive. down() drops the columns and only the office rows this migration added.
 */
return new class extends Migration
{
    private const OFFICES = [
        ['CAD-INFO', 'Asst. Director Cadastral Information', 'CAD-INFO'],
        ['CAD-REP', 'Asst. Director Cadastral Report', 'CAD-REP'],
        ['CAD-PND', 'Asst. Director Plans and Description', 'CAD-PND'],
        ['CAD-GIS', 'Cadastral GIS Unit', 'CAD-GIS'],
        ['CAD-DRW', 'Asst. Director Drawing & Charting', 'CAD-DRW'],
    ];

    public function up(): void
    {
        $db = DB::connection('sqlsrv');
        $now = now();
        foreach (self::OFFICES as [$code, $name, $abbr]) {
            if (!$db->table('offices')->where('office_code', $code)->exists()) {
                $db->table('offices')->insert([
                    'office_code' => $code, 'office_name' => $name, 'office_abbreviation' => $abbr,
                    'department' => 'Cadastral', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        Schema::connection('sqlsrv')->table('cadastral_cases', function (Blueprint $t) {
            if (!Schema::connection('sqlsrv')->hasColumn('cadastral_cases', 'accepted_at')) {
                $t->dateTime('accepted_at')->nullable();
                $t->unsignedBigInteger('accepted_by')->nullable();
                $t->unsignedBigInteger('file_tracker_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('cadastral_cases', function (Blueprint $t) {
            $t->dropColumn(['accepted_at', 'accepted_by', 'file_tracker_id']);
        });
        DB::connection('sqlsrv')->table('offices')
            ->whereIn('office_code', array_column(self::OFFICES, 0))
            ->where('department', 'Cadastral')->delete();
    }
};
