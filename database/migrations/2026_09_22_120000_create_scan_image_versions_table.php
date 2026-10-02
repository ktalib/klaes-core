<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::connection('sqlsrv')->create('scan_image_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('scanning_id')->index();
            $table->unsignedBigInteger('replaced_by');
            $table->text('old_path');
            $table->text('new_path');
            $table->text('old_metadata');
            $table->text('source_path');
            $table->timestamp('created_at');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('scan_image_versions');
    }
};
