<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (!Schema::connection('sqlsrv')->hasTable('allocation_source_lookups')) {
            Schema::connection('sqlsrv')->create('allocation_source_lookups', function (Blueprint $table) {
                $table->id();
                // institution_government | institution_other | addressed_to_government | addressed_to_other
                $table->string('type', 50);
                $table->string('name', 255);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['type', 'name']);
            });
        }

        // Seed common defaults only when empty so re-running never clobbers
        // values contributed later via an "Other (Specify)" box.
        if (DB::connection('sqlsrv')->table('allocation_source_lookups')->count() === 0) {
            $now = now();

            $lists = [
                'institution_government' => [
                    'KANO URBAN PLANNING DEVELOPMENT AGENCY',
                    'KSIP',
                    'KANO STATE MINISTRY OF HOUSING',
                    'KANO STATE HOUSING CORPORATION',
                    'LOCAL GOVERNMENT',
                    'KANO STATE MINISTRY OF COMMERCE',
                ],
                'institution_other' => [
                    'NIGERIAN POLICE',
                    'NIGERIAN JUDICIARY',
                ],
                'addressed_to_government' => [
                    'HON. COMMISSIONER',
                    'HON. CHAIRMAN',
                    'MANAGING DIRECTOR',
                    'DIRECTOR GENERAL',
                    'DIRECTOR',
                ],
                'addressed_to_other' => [
                    'COMMISSIONER',
                    'INSPECTOR GENERAL',
                    'HON JUDGE',
                    'CHAIRMAN',
                    'DIRECTOR',
                    'MANAGING DIRECTOR',
                    'HIGH COMMISSIONER',
                    'ROYAL HIGHNESS',
                    'THE CEO',
                ],
            ];

            $rows = [];
            foreach ($lists as $type => $names) {
                foreach ($names as $index => $name) {
                    $rows[] = [
                        'type'       => $type,
                        'name'       => $name,
                        'sort_order' => ($index + 1) * 10,
                        'is_active'  => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::connection('sqlsrv')->table('allocation_source_lookups')->insert($rows);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('allocation_source_lookups');
    }
};
