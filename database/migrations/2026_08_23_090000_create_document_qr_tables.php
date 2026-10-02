<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Global QR document registry + print/scan audit.
 *
 * One global table, deliberately — not one per module. The scanner knows
 * NOTHING at the moment it decodes (that is the point of the token), so a
 * per-module table would force the module name into the QR in plaintext and
 * re-introduce the leak the token exists to close. It also makes the token-hash
 * UNIQUE constraint enforceable and keeps Ministry-wide audit queries from
 * becoming twelve-way UNIONs.
 *
 * Module tables remain the source of truth for document CONTENT. This table
 * stores identity and audit only.
 *
 * NOTE FOR DEPLOYMENT: artisan's migrations ledger lives in MySQL while these
 * tables are created on sqlsrv. The `migrations` table visible on sqlsrv is
 * stale and must not be trusted. Ship the paired SQL files in database/sql/
 * alongside this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('document_qr_codes', function (Blueprint $table) {
            $table->bigIncrements('id');

            // what this document is
            $table->string('document_type', 40);
            $table->string('source_table', 128)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // how it reaches a file
            $table->unsignedBigInteger('file_indexing_id')->nullable();

            // Snapshots taken at issue time — cross-check values, NOT truth.
            // Their job is to be compared against what the module tables say
            // now; a mismatch is a finding worth surfacing, not an error to
            // paper over.
            $table->string('file_number', 255)->nullable();
            $table->string('tracking_id', 255)->nullable();

            // 'grouping' | 'commissioning' | 'file_tracker' | 'none'
            // ST files have NO grouping table — their tracking ID is generated
            // at commissioning. Kept as data so the rule is queryable rather
            // than buried in a branch.
            $table->string('tracking_id_source', 40)->nullable();

            // token identity
            $table->smallInteger('qr_version')->default(1);
            $table->smallInteger('key_id');           // survives key rotation
            $table->char('token_hash', 64);           // sha256 of the emitted token

            // 'active' | 'superseded'.
            // 'revoked' is RESERVED and NOT yet in use — document revocation is
            // deferred. Do not write it until that work is picked up. (Distinct
            // from TITLE revocation, which is register data and unrelated to
            // whether the paper is genuine.)
            $table->string('status', 20)->default('active');

            // Set when a re-issuance on fresh security paper replaces this
            // document. The superseded token must KEEP verifying and report
            // "authentic, but superseded by …" — a dead QR is indistinguishable
            // from a forgery.
            $table->unsignedBigInteger('superseded_by_id')->nullable();

            $table->dateTime('issued_at')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->integer('print_count')->default(0);
            $table->dateTime('last_printed_at')->nullable();
            $table->unsignedBigInteger('last_printed_by')->nullable();

            $table->timestamps();

            $table->unique('token_hash', 'ux_dqr_token_hash');
            $table->index('file_indexing_id', 'ix_dqr_file_indexing');
            $table->index('tracking_id', 'ix_dqr_tracking');
            $table->index('file_number', 'ix_dqr_file_number');
        });

        // Approach A: one QR per document instance; reprints share it. Filtered
        // so the many rows that carry no source_id do not collide.
        DB::connection('sqlsrv')->statement(
            'CREATE UNIQUE INDEX ux_dqr_source ON document_qr_codes (document_type, source_id)
             WHERE source_id IS NOT NULL'
        );

        Schema::connection('sqlsrv')->create('document_print_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_qr_id');
            $table->integer('print_number');              // 1 = original
            $table->string('copy_type', 20)->default('reprint');   // original|reprint
            $table->unsignedBigInteger('printed_by')->nullable();
            $table->dateTime('printed_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('batch_reference', 100)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 1000)->nullable();

            $table->index(['document_qr_id', 'print_number'], 'ix_dpl_qr');
            $table->foreign('document_qr_id', 'fk_dpl_qr')
                  ->references('id')->on('document_qr_codes');
        });

        Schema::connection('sqlsrv')->create('document_scan_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_qr_id')->nullable();  // null when nothing resolved
            $table->string('qr_version_seen', 10)->nullable();         // Q1|Q0|REF

            // Raw payload is stored ONLY on failure, as evidence. A table of
            // valid plaintext tokens would be a forgery kit for anyone with
            // read access, so successful scans record the id and nothing else.
            $table->string('raw_payload', 512)->nullable();

            $table->dateTime('scanned_at')->nullable();
            $table->unsignedBigInteger('scanned_by')->nullable();      // null = public
            $table->string('channel', 40)->default('manual');          // qr_scan|manual|public|api
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->string('result', 30);                              // authentic|review|revoked|tampered|notfound|unverifiable
            $table->string('failure_reason', 500)->nullable();

            $table->index(['document_qr_id', 'scanned_at'], 'ix_dsl_qr');
            $table->index('scanned_at', 'ix_dsl_when');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('document_scan_logs');
        Schema::connection('sqlsrv')->dropIfExists('document_print_logs');
        Schema::connection('sqlsrv')->dropIfExists('document_qr_codes');
    }
};
