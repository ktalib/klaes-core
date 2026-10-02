<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * source_module — which module raised a parcel update: Deeds or Land.
 *
 * Every parcel-update register is reachable from BOTH sidebars, separated only by a
 * query string (`?mode=deeds` / `?mode=land`), and that mode has only ever labelled
 * the page. Nothing on the record said where it came from, so a register listing both
 * modules' work could not tell them apart, and neither could anyone reading it later.
 *
 * Named source_module rather than `source` on purpose: duplex_parcel_updates already
 * carries source_file_nos — the parcel files an instruction acts on — and a bare
 * `source` beside it would read as the same thing. The column is labelled "Source" in
 * the UI, where there is no such ambiguity.
 *
 * Values are 'deeds' and 'land', the vocabulary the sidebar links already use, so the
 * mode on the request is stored as it arrives rather than translated.
 *
 * Nullable, and deliberately NOT backfilled. The 1,019 rows that exist were captured
 * before anything recorded this, and the database has two user accounts between them,
 * so captured_by cannot attribute them either. They read "—", which is true, instead
 * of being assigned an origin nobody can evidence.
 */
return new class extends Migration
{
    /** table => the column the new one is added after, for readability in the catalog. */
    private const TABLES = [
        'duplex_parcel_updates',
        'change_of_purpose_applications',
        'plot_subdivision_applications',
        'plot_merger_applications',
        'plot_extension_applications',
        'plot_separation_applications',
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table) || $schema->hasColumn($table, 'source_module')) {
                continue;
            }

            $schema->table($table, function (Blueprint $t) {
                $t->string('source_module', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'source_module')) {
                continue;
            }

            $schema->table($table, function (Blueprint $t) {
                $t->dropColumn('source_module');
            });
        }
    }
};
