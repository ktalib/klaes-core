<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KLAES Mobile Apps (System Admin): one row per Android app the Ministry hands
 * out, holding the APK, icon and the details shown on its download page.
 * The files themselves live on the private_files disk, not in this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('mobile_apps')) {
            $schema->create('mobile_apps', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('slug', 60)->unique();
                $table->string('name', 150);
                $table->string('short_description', 500)->nullable();
                $table->text('description')->nullable();
                $table->string('version', 50)->nullable();
                $table->string('min_android', 50)->nullable();
                $table->string('icon_path', 500)->nullable();
                $table->string('apk_path', 500)->nullable();
                $table->string('apk_original_name', 255)->nullable();
                $table->unsignedBigInteger('apk_size')->nullable();
                $table->dateTime('apk_uploaded_at')->nullable();
                $table->unsignedInteger('download_count')->default(0);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        $apps = [
            ['slug' => 'file-tracking', 'name' => 'File Tracking Mobile App'],
            ['slug' => 'valuation-compensation', 'name' => 'Valuation for Compensation Mobile App'],
            ['slug' => 'special-assignment', 'name' => 'Special Assignment Mobile App'],
            ['slug' => 'survey', 'name' => 'Survey Mobile App'],
        ];

        foreach ($apps as $app) {
            $exists = DB::connection('sqlsrv')->table('mobile_apps')->where('slug', $app['slug'])->exists();
            if (! $exists) {
                DB::connection('sqlsrv')->table('mobile_apps')->insert($app + [
                    'download_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('mobile_apps');
    }
};
