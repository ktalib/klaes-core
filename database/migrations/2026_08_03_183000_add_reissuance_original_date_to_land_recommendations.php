<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pre-KLAES (legacy) re-issuance creates a NEW recommendation, so its own
 * rofo_generated_at is the date the re-issued letter was produced — today. The
 * letter's superseding notice ("supersedes the previous one issued on ...") needs
 * the ORIGINAL letter's date, which nothing on the record held: the print template
 * fell back to rofo_generated_at/created_at and printed today's date.
 *
 * This column stores that original issue date, keyed in on the recommendation form
 * when the re-issuance is captured. Only the legacy path uses it — the KLAES path
 * re-issues the existing record, whose rofo_generated_at is already the right date.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'reissuance_original_date')) {
            return;
        }

        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->date('reissuance_original_date')->nullable()->after('reissued_from_id');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'reissuance_original_date')) {
            return;
        }

        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dropColumn('reissuance_original_date');
        });
    }
};
