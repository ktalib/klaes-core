<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "File Already Decommissioned Before" as answered (read from the records) when the
 * SPU / APU / Legacy Parcel Update application was saved: "Yes" / "No" for one source
 * file, "FILE=Yes; FILE=No" for several. Additive and nullable only.
 */
return new class extends Migration
{
    private const TABLES = [
        'plot_subdivision_applications',
        'plot_separation_applications',
        'plot_merger_applications',
        'plot_extension_applications',
        'change_of_purpose_applications',
        'duplex_parcel_updates',
        'manual_file_linkages',
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach (self::TABLES as $table) {
            if ($schema->hasTable($table) && !$schema->hasColumn($table, 'previously_decommissioned')) {
                $schema->table($table, function (Blueprint $t) {
                    $t->string('previously_decommissioned', 2000)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach (self::TABLES as $table) {
            if ($schema->hasTable($table) && $schema->hasColumn($table, 'previously_decommissioned')) {
                $schema->table($table, function (Blueprint $t) {
                    $t->dropColumn('previously_decommissioned');
                });
            }
        }
    }
};
