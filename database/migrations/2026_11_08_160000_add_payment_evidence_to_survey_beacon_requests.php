<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_beacon_requests', 'payment_evidence_path')) {
            $schema->table('survey_beacon_requests', fn (Blueprint $table) => $table->string('payment_evidence_path', 500)->nullable());
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_beacon_requests', 'payment_evidence_path')) {
            $schema->table('survey_beacon_requests', fn (Blueprint $table) => $table->dropColumn('payment_evidence_path'));
        }
    }
};
