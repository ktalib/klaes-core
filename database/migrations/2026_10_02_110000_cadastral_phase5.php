<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Information (rebuild plan Phase 5, §6 item 3).
 *
 * cadastral_documents — a POINTER, not a store
 * --------------------------------------------
 * A document uploaded on a cadastral screen (a file-status supporting document,
 * a report's manual chart) is filed into the FILE'S EDMS folder as a scan —
 * EDMS/SCAN_UPLOAD/{Registry}/{Type?}/{FILE NUMBER}/{PAPER}/{file} with a
 * `scannings` row — so it reaches Page Typing and Scan Upload like any other
 * scanned page (App\Services\Cadastral\CadastralDocuments). This table only says
 * which cadastral record a scan was filed for:
 *
 *   owner_type, owner_id   cadastral_file_status_event | cadastral_report, and its id
 *   kind                   supporting | manual_chart
 *   scanning_id            the scannings row (the bytes live there, nowhere else)
 *   file_indexing_id       the indexing record the scan was filed under
 *   file_number            as filed, for reading the list without a join
 *   original_name, mime, size, uploaded_by
 *   is_superseded, superseded_at, superseded_by_id
 *                          NEVER DELETED. A replacement manual chart flags the
 *                          one before it; both rows, and both scans, are kept.
 *
 * No document path is stored: scannings.document_path is the authority, and
 * EdmsDocumentPathResolver re-derives it on read if the scan is ever moved.
 * No soft deletes, for the same reason the status events have none.
 *
 * cadastral_index_cards.cadastral_file_receipt_id / file_indexing_id
 * ---------------------------------------------------------------
 * A card is now commissioned from a registered intake receipt (Phase 2), so it
 * keeps the receipt it came from and the file_indexings row the receipt was
 * picked from. That is what files a status document into the right EDMS folder
 * without matching the number again. Both nullable, plain integers (no FK —
 * file_indexings is hand-maintained, as on every cadastral_* table).
 *
 * cadastral_file_status_events.effective_date
 * -------------------------------------------
 * Already created by 2026_09_28_100000 on this database; guarded here so a
 * database built without it still gets it.
 *
 * NO MOVEMENT TABLE. The card's movement stages are recorded in audit_logs
 * (CADASTRAL_INDEX_CARD_MOVED) beside the live file_tracker log; see
 * App\Services\Cadastral\IndexCardMovements for why.
 *
 * The code works before this runs: CadastralDocuments::installed() and
 * CadastralIndexCard::linkInstalled() check once per request; until then the
 * upload fields read "pending installation" and cards are commissioned without
 * the two pointers.
 *
 * EVERY CHANGE IS GUARDED BY hasTable()/hasColumn(), so a part-applied run can
 * be re-run. down() removes only what this migration adds, and only if present
 * — and never drops cadastral_documents once it holds a row.
 *
 * DEPLOY WITH THE PATH FLAG. A bare migrate would run 27 unrelated pending
 * migrations against production, and --pretend is NOT a dry run on sqlsrv (it
 * executes):
 *
 *   php artisan migrate --database=sqlsrv \
 *     --path=database/migrations/2026_10_02_110000_cadastral_phase5.php --force
 */
return new class extends Migration
{
    private const CONN = 'sqlsrv';

    /** Columns added to cadastral_index_cards. */
    private function cardColumns(): array
    {
        return [
            'cadastral_file_receipt_id' => fn (Blueprint $t) => $t->unsignedBigInteger('cadastral_file_receipt_id')->nullable(),
            'file_indexing_id'          => fn (Blueprint $t) => $t->unsignedBigInteger('file_indexing_id')->nullable(),
        ];
    }

    public function up(): void
    {
        $s = Schema::connection(self::CONN);

        if (! $s->hasTable('cadastral_documents')) {
            $s->create('cadastral_documents', function (Blueprint $t) {
                $t->id();
                $t->string('owner_type', 50);
                $t->unsignedBigInteger('owner_id');
                $t->string('kind', 30);
                $t->unsignedBigInteger('scanning_id');
                $t->unsignedBigInteger('file_indexing_id');
                $t->string('file_number', 100)->nullable();
                $t->string('original_name', 255)->nullable();
                $t->string('mime', 100)->nullable();
                $t->unsignedBigInteger('size')->nullable();
                $t->unsignedBigInteger('uploaded_by')->nullable();
                $t->boolean('is_superseded')->default(false);
                $t->dateTime('superseded_at')->nullable();
                $t->unsignedBigInteger('superseded_by_id')->nullable();
                $t->timestamps();
                $t->index(['owner_type', 'owner_id'], 'cad_documents_owner_idx');
                $t->index('scanning_id');
            });
        }

        if ($s->hasTable('cadastral_index_cards')) {
            foreach ($this->cardColumns() as $column => $define) {
                if (! $s->hasColumn('cadastral_index_cards', $column)) {
                    $s->table('cadastral_index_cards', fn (Blueprint $t) => $define($t));
                }
            }
        }

        if ($s->hasTable('cadastral_file_status_events')
            && ! $s->hasColumn('cadastral_file_status_events', 'effective_date')) {
            $s->table('cadastral_file_status_events', fn (Blueprint $t) => $t->date('effective_date')->nullable());
        }
    }

    public function down(): void
    {
        $s = Schema::connection(self::CONN);

        // A pointer row is the only record of which cadastral event a scan was
        // filed for. Dropping the table would lose that silently.
        if ($s->hasTable('cadastral_documents')
            && \Illuminate\Support\Facades\DB::connection(self::CONN)->table('cadastral_documents')->doesntExist()) {
            $s->drop('cadastral_documents');
        }

        if ($s->hasTable('cadastral_index_cards')) {
            foreach (array_keys($this->cardColumns()) as $column) {
                if ($s->hasColumn('cadastral_index_cards', $column)) {
                    $s->table('cadastral_index_cards', fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
        }

        // effective_date belongs to the original create migration; not dropped here.
    }
};
