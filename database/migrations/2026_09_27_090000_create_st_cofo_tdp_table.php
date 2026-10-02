<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The TDP — the BACK PAGE of an ST Certificate of Occupancy.
 *
 * An ST C of O is one two-sided document: the front page is produced in KLAES/ST
 * (CofoController::CertificateOfOccupancy, view programmes.print_cofo_front_page) and the
 * back is the TDP, which belongs to KANGIS/GIS. Until now only the front existed anywhere
 * in this codebase — "TDP" appeared solely in CertificationController and some pagetyping
 * JavaScript, and nothing linked one to a certificate.
 *
 * Deliberately a SEPARATE table rather than columns on st_cofo:
 *
 *   - st_cofo is the certificate record and is left untouched;
 *   - a TDP can be replaced (is_active) without rewriting the certificate row;
 *   - a certificate with no TDP yet is the normal mid-workflow state, which a missing row
 *     expresses better than a pile of nullable columns.
 *
 * Shape follows unit_st_memo_uploads, the existing ST document-upload table, so the two
 * read the same way and the same storage convention applies (public disk, storeAs).
 *
 * Keyed on sub_application_id because that is how st_cofo itself links a certificate to its
 * unit; st_cofo_id is carried alongside for a direct join where the certificate is known.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    private const TABLE = 'st_cofo_tdp';

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable(self::TABLE)) {
            return;
        }

        $schema->create(self::TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('sub_application_id')->index();
            // Nullable: a TDP may be filed before the certificate row is generated, and the
            // workflow explicitly does not require the front page to come first.
            $table->unsignedBigInteger('st_cofo_id')->nullable()->index();

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
