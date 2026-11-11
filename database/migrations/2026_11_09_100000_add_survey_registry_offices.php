<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | DIIT locations for Survey files (user, 2026-11-09): CO files sit at the Compensation Unit, GKN and LPKN files
 | at the GKN Registry. Neither was an office (only Survey units), so files could not be logged out from them.
 | LPPL uses Cadastral Registry (CAD-R) and Misc KN / RDKN the SG's Office (SUR-G); both already exist.
 | Adds the two rows only when their codes are free; down() removes them only while no tracker names them.
 */
return new class extends Migration
{
    private const OFFICES = [
        ['office_code' => 'COMP-U', 'office_name' => 'Compensation Unit', 'office_abbreviation' => 'COMP-U'],
        ['office_code' => 'GKN-R',  'office_name' => 'GKN Registry',      'office_abbreviation' => 'GKN-R'],
    ];

    public function up(): void
    {
        $db = DB::connection('sqlsrv');
        foreach (self::OFFICES as $o) {
            if ($db->table('offices')->where('office_code', $o['office_code'])->exists()) continue;
            $db->table('offices')->insert($o + ['department' => 'Survey', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('sqlsrv');
        foreach (self::OFFICES as $o) {
            $used = $db->table('file_tracker')->where('current_office_code', $o['office_code'])
                ->orWhere('origin_office_code', $o['office_code'])->orWhere('movement_log', 'like', '%"' . $o['office_code'] . '"%')->exists();
            if (! $used) $db->table('offices')->where('office_code', $o['office_code'])->delete();
        }
    }
};
