<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPAS: the sticker number on the property's QR sticker (2026-10-09), typed or
 * scanned on the Add Land Record form (web, mobile page, app). Optional. One
 * sticker belongs to one record; the forms refuse a sticker already used, so
 * the index is ordinary rather than unique (an offline push must never be
 * lost to a constraint). New column only; nothing existing changes.
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('spa_applications', 'sticker_number')) {
            $schema->table('spa_applications', function (Blueprint $table) {
                $table->string('sticker_number', 100)->nullable();
            });
        }

        $db = DB::connection('sqlsrv');
        if (! $db->selectOne("SELECT 1 AS x FROM sys.indexes WHERE object_id = OBJECT_ID('spa_applications') AND name = 'IX_spa_applications_sticker_number'")) {
            $db->statement('CREATE INDEX IX_spa_applications_sticker_number ON spa_applications (sticker_number) WHERE sticker_number IS NOT NULL');
        }
    }

    public function down(): void
    {
        $db = DB::connection('sqlsrv');
        if ($db->selectOne("SELECT 1 AS x FROM sys.indexes WHERE object_id = OBJECT_ID('spa_applications') AND name = 'IX_spa_applications_sticker_number'")) {
            $db->statement('DROP INDEX IX_spa_applications_sticker_number ON spa_applications');
        }
        if (Schema::connection('sqlsrv')->hasColumn('spa_applications', 'sticker_number')) {
            Schema::connection('sqlsrv')->table('spa_applications', fn (Blueprint $t) => $t->dropColumn('sticker_number'));
        }
    }
};
