<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CR 03112026 item 2: OPs grouped by layout. No Deeds table changes: op_tp_aliases maps each spelling of
 * a TP number found on OPs to one op_tp_groups row, and OPs are matched through it. Filled by
 * survey:sync-op-tp-groups.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('op_tp_groups')) {
            $schema->create('op_tp_groups', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('group_key', 150)->unique('ux_op_tp_groups_key');
                $t->string('label', 255);
                $t->string('prefix', 20)->nullable();
                $t->string('agency', 50)->nullable();
                $t->string('code', 100)->nullable();
                $t->string('serial', 100)->nullable();
                $t->integer('serial_sort')->nullable();
                $t->unsignedBigInteger('survey_tp_number_id')->nullable();
                $t->unsignedInteger('register_rows')->default(0);
                $t->unsignedInteger('op_count')->default(0);
                $t->unsignedInteger('resettlement_count')->default(0);
                $t->unsignedInteger('direct_count')->default(0);
                $t->boolean('needs_review')->default(false);
                $t->timestamps();
            });
        }

        if (! $schema->hasTable('op_tp_aliases')) {
            $schema->create('op_tp_aliases', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('raw_tp', 255)->unique('ux_op_tp_aliases_raw');
                $t->unsignedBigInteger('op_tp_group_id')->index('ix_op_tp_aliases_group');
                $t->unsignedInteger('op_count')->default(0);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('op_tp_aliases');
        Schema::connection('sqlsrv')->dropIfExists('op_tp_groups');
    }
};
