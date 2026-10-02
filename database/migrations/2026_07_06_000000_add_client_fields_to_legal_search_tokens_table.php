<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('legal_search_tokens', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('legal_search_tokens', 'client_name')) {
                $table->string('client_name')->nullable()->after('applicant_name');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('legal_search_tokens', 'client_address')) {
                $table->string('client_address')->nullable()->after('client_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('legal_search_tokens', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('legal_search_tokens', 'client_address')) {
                $table->dropColumn('client_address');
            }
            if (Schema::connection('sqlsrv')->hasColumn('legal_search_tokens', 'client_name')) {
                $table->dropColumn('client_name');
            }
        });
    }
};
