<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The TDP — the BACK PAGE of an SLTR Certificate of Occupancy — when uploaded on the SLTR CofO
 * screen because the plan is not in the GIS store yet.
 *
 * There is deliberately no SLTR certificate table: the certificate is the Deeds instrument
 * capture (instrument_capture, instrument_type 'SLTR Certificate of Occupancy'). This table
 * only holds the uploaded back page, keyed on the file number (what the SLTR queue keys on)
 * with the capture id alongside for a direct join.
 *
 * Shape follows st_cofo_tdp.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    private const TABLE = 'sltr_cofo_tdp';

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable(self::TABLE)) {
            return;
        }

        $schema->create(self::TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('file_no', 100)->index();
            $table->unsignedBigInteger('instrument_capture_id')->nullable()->index();

            $table->string('file_path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            // A replaced TDP is superseded, never deleted — the certificate it backed may
            // already have been printed, and this is a land registry.
            $table->boolean('is_active')->default(true)->index();

            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->dateTime('uploaded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists(self::TABLE);
    }
};
