<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KANGIS Block Indexing (user, 2026-10-20): each KANGIS file on a block land file adds
 * its holder's name to the land file's File Title ("A, B and C"). Holder names can
 * contain "AND" themselves, so the list is kept apart rather than re-read from the title:
 *  - kangis_base_title    the land file's own title before any KANGIS name was added
 *  - kangis_holder_names  JSON list of the names added, in order
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'kangis_base_title')) {
                $table->text('kangis_base_title')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'kangis_holder_names')) {
                $table->text('kangis_holder_names')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
            $table->dropColumn(['kangis_base_title', 'kangis_holder_names']);
        });
    }
};
