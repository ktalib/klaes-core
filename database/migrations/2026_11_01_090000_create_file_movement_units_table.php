<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Units inside a department for File Movement (CR 30102026 item 34, user 2026-11-01).
 * `department` is the offices.department name File Movement groups by, not departments.id:
 * a unit row in `departments` would show as a department on user forms and get its own
 * "<unit> Director" requester. Managed in Configurable Entities > Units.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('file_movement_units')) {
            Schema::connection('sqlsrv')->create('file_movement_units', function (Blueprint $table) {
                $table->id();
                $table->string('department', 150);
                $table->string('name', 150);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->index('department');
            });
        }

        $now = now();
        $seed = [
            'Land'  => ['File Commissioning', 'Recommendation', 'RofO', 'One Stop Shop (OSS)', 'Land Registration'],
            'Deeds' => ['Bill Balance', 'Legal Search'],
        ];
        foreach ($seed as $department => $names) {
            foreach ($names as $name) {
                $exists = DB::connection('sqlsrv')->table('file_movement_units')
                    ->where('department', $department)->where('name', $name)->exists();
                if (!$exists) {
                    DB::connection('sqlsrv')->table('file_movement_units')->insert([
                        'department' => $department, 'name' => $name, 'is_active' => true,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('file_movement_units');
    }
};
