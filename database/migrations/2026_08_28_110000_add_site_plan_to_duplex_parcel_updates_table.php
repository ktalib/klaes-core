<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * duplex_parcel_updates.site_plan — the recommended site plan for the duplex.
 *
 * A duplex is several parcel updates carried as ONE instruction, and the officer
 * recommending it works from ONE drawing: the application plan showing every
 * portion the stages act on and, where there is an extension, the extension land
 * beside them. The five single workflows each carry their own site_plan column
 * (parcel_documents/merger, /subdivision, /separation), and a duplex had none —
 * so the drawing the whole recommendation turns on had nowhere to live.
 *
 * One plan per duplex, not one per stage. The stages are legs of a single
 * instruction over the same parcels; splitting the drawing across them would ask
 * the officer to upload the same sheet several times and leave the register
 * unable to say which copy is the plan of record.
 *
 * Stores a relative path on the `public` disk (parcel_documents/duplex/…), the
 * same convention as PlotMergerController / PlotSubdivisionController, so
 * Storage::url() and Storage::delete() work on it unchanged.
 *
 * Nullable: every duplex captured before this column exists has no plan, and the
 * sibling workflows all treat site_plan as optional at capture.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('duplex_parcel_updates', 'site_plan')) {
            $schema->table('duplex_parcel_updates', function (Blueprint $table) {
                $table->string('site_plan', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('duplex_parcel_updates', 'site_plan')) {
            $schema->table('duplex_parcel_updates', function (Blueprint $table) {
                $table->dropColumn('site_plan');
            });
        }
    }
};
