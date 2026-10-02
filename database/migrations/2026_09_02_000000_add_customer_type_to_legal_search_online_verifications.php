<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer type (Individual / Lawyer) and the lawyer's Call-to-Bar number, on the
 * public Online Legal Search identification record.
 *
 * `bar_number_status` records how far the number could actually be checked:
 *
 *   not_applicable — an individual, no number supplied
 *   matched        — the number was found in the text OCR read off the ID
 *   unconfirmed    — recorded, but nothing could confirm it. This is the NORMAL
 *                    outcome: Nigerian general-purpose IDs (NIN slip, driver's
 *                    licence, voter's card) do not print a call-to-bar number,
 *                    and no roll-of-practitioners API is wired up. It never
 *                    blocks payment - if it did, no lawyer could complete a
 *                    search - the approving officer confirms it instead.
 *   rejected       — an external roll positively said the number is not valid.
 *
 * Existing rows are individuals: the column defaults accordingly, so no backfill
 * is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = 'legal_search_online_verifications';

        if (!Schema::connection('sqlsrv')->hasTable($table)) {
            return;
        }

        Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) use ($table) {
            if (!Schema::connection('sqlsrv')->hasColumn($table, 'customer_type')) {
                $blueprint->string('customer_type', 20)->default('individual');
            }

            if (!Schema::connection('sqlsrv')->hasColumn($table, 'call_to_bar_number')) {
                $blueprint->string('call_to_bar_number', 60)->nullable();
            }

            if (!Schema::connection('sqlsrv')->hasColumn($table, 'bar_number_status')) {
                $blueprint->string('bar_number_status', 20)->nullable();
            }
        });

        // Constrained in the database as well as the model, so a value the
        // application does not recognise can never reach these columns.
        $this->addCheck(
            'chk_lsov_customer_type',
            "customer_type IN ('individual','lawyer')"
        );

        $this->addCheck(
            'chk_lsov_bar_status',
            "bar_number_status IS NULL OR bar_number_status IN ('not_applicable','matched','unconfirmed','rejected')"
        );

        if (!$this->indexExists('ix_lsov_customer_type')) {
            DB::connection('sqlsrv')->statement(
                'CREATE INDEX ix_lsov_customer_type ON legal_search_online_verifications (customer_type)'
            );
        }
    }

    public function down(): void
    {
        $table = 'legal_search_online_verifications';

        if (!Schema::connection('sqlsrv')->hasTable($table)) {
            return;
        }

        foreach (['chk_lsov_customer_type', 'chk_lsov_bar_status'] as $constraint) {
            DB::connection('sqlsrv')->statement(
                "IF OBJECT_ID('{$constraint}', 'C') IS NOT NULL
                 ALTER TABLE legal_search_online_verifications DROP CONSTRAINT {$constraint}"
            );
        }

        if ($this->indexExists('ix_lsov_customer_type')) {
            DB::connection('sqlsrv')->statement(
                'DROP INDEX ix_lsov_customer_type ON legal_search_online_verifications'
            );
        }

        Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn(['customer_type', 'call_to_bar_number', 'bar_number_status']);
        });
    }

    private function addCheck(string $name, string $expression): void
    {
        DB::connection('sqlsrv')->statement(
            "IF OBJECT_ID('{$name}', 'C') IS NULL
             ALTER TABLE legal_search_online_verifications
             ADD CONSTRAINT {$name} CHECK ({$expression})"
        );
    }

    private function indexExists(string $name): bool
    {
        return !empty(DB::connection('sqlsrv')->select(
            "SELECT 1 FROM sys.indexes
              WHERE name = ? AND object_id = OBJECT_ID('legal_search_online_verifications')",
            [$name]
        ));
    }
};
