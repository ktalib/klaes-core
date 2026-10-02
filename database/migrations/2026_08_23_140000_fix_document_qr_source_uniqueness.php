<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allow a re-issuance to mint a new document instance.
 *
 * The original index was UNIQUE (document_type, source_id) WHERE source_id IS
 * NOT NULL — one QR per source row, forever. That blocked the re-issuance rule
 * confirmed in the plan (§12.3): a re-issuance on fresh security paper is a NEW
 * document instance, with the previous one marked 'superseded' and both left
 * resolvable.
 *
 * The constraint that actually expresses the intent is "one ACTIVE QR per
 * source": reprints still share the live row, while superseded generations
 * accumulate behind it and keep verifying.
 *
 * NOTE: superseding must happen BEFORE the replacement is inserted, or the two
 * rows are briefly both active and collide. DocumentQrService::supersede()
 * orders it that way.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('sqlsrv')->statement('DROP INDEX IF EXISTS ux_dqr_source ON document_qr_codes');

        DB::connection('sqlsrv')->statement(
            "CREATE UNIQUE INDEX ux_dqr_source_active ON document_qr_codes (document_type, source_id)
             WHERE source_id IS NOT NULL AND status = 'active'"
        );
    }

    public function down(): void
    {
        DB::connection('sqlsrv')->statement('DROP INDEX IF EXISTS ux_dqr_source_active ON document_qr_codes');

        DB::connection('sqlsrv')->statement(
            'CREATE UNIQUE INDEX ux_dqr_source ON document_qr_codes (document_type, source_id)
             WHERE source_id IS NOT NULL'
        );
    }
};
