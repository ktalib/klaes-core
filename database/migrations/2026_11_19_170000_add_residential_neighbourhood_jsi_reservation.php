<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * JSI reservation list (user, 2026-11-19, PP request): a 10 m Right of Way for sites in
 * residential neighbourhoods, since most SLTR sites are in already developed areas.
 * The list is shared, so it shows on both the SLTR and Conversion JSI. Added once.
 */
return new class extends Migration
{
    private const NAME = 'RESIDENTIAL NEIGHBOURHOOD (10m R.O.W)';

    public function up()
    {
        $exists = DB::connection('sqlsrv')->table('jsi_lut_reservations')
            ->whereRaw('UPPER(LTRIM(RTRIM(name))) = ?', [strtoupper(self::NAME)])
            ->exists();

        if (! $exists) {
            DB::connection('sqlsrv')->table('jsi_lut_reservations')->insert([
                'name' => self::NAME,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        DB::connection('sqlsrv')->table('jsi_lut_reservations')->where('name', self::NAME)->delete();
    }
};
