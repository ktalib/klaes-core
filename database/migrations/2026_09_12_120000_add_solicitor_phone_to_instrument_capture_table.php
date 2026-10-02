<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('instrument_capture') && !$schema->hasColumn('instrument_capture', 'solicitor_phone')) {
            $schema->table('instrument_capture', function (Blueprint $table) {
                $table->string('solicitor_phone', 30)->nullable()->after('solicitor_name');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('instrument_capture') && $schema->hasColumn('instrument_capture', 'solicitor_phone')) {
            $schema->table('instrument_capture', function (Blueprint $table) {
                $table->dropColumn('solicitor_phone');
            });
        }
    }
};
