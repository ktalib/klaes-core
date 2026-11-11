<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Print File Label settings per registry (CR 10112026 item 3, user 2026-11-11). shelf_rack_off: the "Override
 | Shelf/Rack" checkbox, kept until someone unticks it. Registries without defined racks (Survey first) then print labels
 | with no shelf/rack on the label or in its QR data. One row per registry key ("survey", later "land", "kangis", ...).
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if (! $s->hasTable('print_label_settings')) {
            $s->create('print_label_settings', function (Blueprint $t) {
                $t->id();
                $t->string('registry', 40)->unique('ux_print_label_settings_registry');
                $t->boolean('shelf_rack_off')->default(false);
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('print_label_settings');
    }
};
