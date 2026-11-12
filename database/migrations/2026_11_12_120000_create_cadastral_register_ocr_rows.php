<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CR-11112026-2 item 12: the handwritten Cadastral file register, transcribed line by line from photos into a
 * staging table only (user, 2026-11-12). Nothing here feeds a workflow until the user says so.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('cadastral_register_ocr_rows')) {
            return;
        }
        $schema->create('cadastral_register_ocr_rows', function (Blueprint $t) {
            $t->id();
            $t->string('import_ref', 30)->index();           // REG-<date>-<n>
            $t->unsignedSmallInteger('page_no');
            $t->unsignedSmallInteger('line_no');               // line within the page
            $t->string('photo_path', 255)->nullable();         // storage/app/cadastral/register-ocr/photos/page-N.jpeg
            $t->date('entry_date')->nullable();
            $t->boolean('date_inferred')->default(false);      // no heading on the page: carried over
            $t->string('file_no_written', 100);                // as written, dittos filled in
            $t->string('file_no', 100)->nullable()->index();   // KLAES format: RES-1989-303, SLTR-213284
            $t->boolean('lf')->nullable();                     // LF column ticked
            $t->string('destination', 100)->nullable();        // AD/Report, D/Cad, OSS-C, SG, DSG...
            $t->boolean('crossed_out')->default(false);
            $t->boolean('needs_check')->default(false);        // hard to read: check against the photo
            $t->string('note', 500)->nullable();
            $t->unsignedBigInteger('file_indexing_id')->nullable(); // matched file_indexings row, if any
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('cadastral_register_ocr_rows');
    }
};
