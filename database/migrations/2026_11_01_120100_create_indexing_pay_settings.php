<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Configurable Entries > Indexing Pay. One row per version of the rate card; a version applies
// from its effective_from date until the next one, so a rate change never alters days already
// earned. The settings themselves are a JSON document (see config/indexing_pay.php for the keys).
// No seed row: until one is saved, IndexingPaySettings serves the approved card from config.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('indexing_pay_settings', function (Blueprint $t) {
            $t->id();
            $t->date('effective_from')->unique();
            $t->text('settings');
            $t->string('note', 500)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('indexing_pay_settings');
    }
};
