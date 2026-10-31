<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * KLAES Mobile Apps: the two Survey field apps cloned from SCF on 2026-10-30 (Field Observations,
 * Plot Confirmation) get their own entries, so each has a download page (user, 2026-10-30).
 * Added only when missing, as the original seed did.
 */
return new class extends Migration
{
    private const APPS = [
        ['slug' => 'field-observations', 'name' => 'Field Observations Mobile App',
         'short_description' => 'Computation & Field Observations: survey readings captured in the field.'],
        ['slug' => 'plot-confirmation', 'name' => 'Plot Confirmation Mobile App',
         'short_description' => 'Show OP plots to their allottees and ground-check plots against the Beacon Register.'],
    ];

    public function up(): void
    {
        foreach (self::APPS as $app) {
            if (! DB::connection('sqlsrv')->table('mobile_apps')->where('slug', $app['slug'])->exists()) {
                DB::connection('sqlsrv')->table('mobile_apps')->insert($app + [
                    'download_count' => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::connection('sqlsrv')->table('mobile_apps')->whereIn('slug', array_column(self::APPS, 'slug'))->delete();
    }
};
