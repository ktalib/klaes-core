<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * file_indexings.root_of_title — the pre-grant instrument/holder a title springs
 * from, keyed in by hand on the File Indexing form.
 *
 * TitleHolderResolver already DERIVES a Root of Title from the transaction chain,
 * but only for files that have a pre-grant dealing on record. For everything else
 * the line prints a dash, and nobody could put the answer in — the indexer holds
 * the physical file and is the one person who can read it off the documents.
 * This column is that answer; the resolver reads it as its last-resort fallback,
 * exactly as it already falls back to the indexed original_holder/current_holder.
 *
 * Plain string, NOT the JSON-array shape of current_holder/original_holder: a file
 * has one root, however many names are on the holder lines. formattedHolder()
 * handles a plain string, so the column is readable through the same accessor.
 *
 * Nullable in the database on purpose. The "must be entered" rule belongs to the
 * form (required on the input, required in store()/update() validation); the
 * column has to stay NULLable because ~133k historic rows predate the field and
 * the non-form write paths (commissioning auto-indexing, batch imports, the
 * scanning importer) have no value to supply.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('file_indexings', 'root_of_title')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->string('root_of_title', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('file_indexings', 'root_of_title')) {
            $schema->table('file_indexings', function (Blueprint $table) {
                $table->dropColumn('root_of_title');
            });
        }
    }
};
