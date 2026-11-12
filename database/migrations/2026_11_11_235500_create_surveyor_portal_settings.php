<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | MLPP-SG portal settings (Configurable Entries > Surveyor Portal) move from MySQL `settings` to SQL Server
 | (user, 2026-11-11: "everything important, including configurable entries, should be in SQL Server").
 | MySQL held no portal rows when this ran, so nothing is copied. Values are text; lists are JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('surveyor_portal_settings')) {
            return;
        }
        $schema->create('surveyor_portal_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->text('value')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('surveyor_portal_settings');
    }
};
