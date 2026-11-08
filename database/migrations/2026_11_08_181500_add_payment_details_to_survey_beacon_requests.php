<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('survey_beacon_requests')) {
            throw new RuntimeException('The survey_beacon_requests table must exist before payment details can be added.');
        }

        if (! $schema->hasColumn('survey_beacon_requests', 'amount_paid')) {
            $schema->table('survey_beacon_requests', fn (Blueprint $table) => $table->decimal('amount_paid', 15, 2)->nullable());
        }
        if (! $schema->hasColumn('survey_beacon_requests', 'payment_date')) {
            $schema->table('survey_beacon_requests', fn (Blueprint $table) => $table->date('payment_date')->nullable());
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $columns = array_values(array_filter(
            ['amount_paid', 'payment_date'],
            fn (string $column) => $schema->hasColumn('survey_beacon_requests', $column)
        ));
        if ($columns) {
            $schema->table('survey_beacon_requests', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
