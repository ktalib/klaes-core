<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Records WHICH consent application a captured instrument was registered
     * against.
     *
     * A file can hold several consents over its life — an Assignment consent
     * today, another Assignment consent to a different assignee next year — and
     * the same instrument type is legitimately captured more than once on the
     * same file. Without this link the only way to tell a spent consent from a
     * fresh one is to guess from party names, so the duplicate-registration
     * warning cannot say which consent is still available to use.
     *
     * Nullable on purpose: captures that predate this column, and instrument
     * types that need no consent at all, simply leave it NULL. Legacy rows are
     * inferred by party matching in InstrumentController::checkDuplicate().
     */
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('instrument_capture')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'consent_application_id')) {
            return;
        }

        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            $table->unsignedBigInteger('consent_application_id')->nullable();
            $table->index('consent_application_id', 'ix_instrument_capture_consent_application_id');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('instrument_capture')) {
            return;
        }

        if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'consent_application_id')) {
            return;
        }

        Schema::connection('sqlsrv')->table('instrument_capture', function (Blueprint $table) {
            $table->dropIndex('ix_instrument_capture_consent_application_id');
            $table->dropColumn('consent_application_id');
        });
    }
};
