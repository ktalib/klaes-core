<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Survey Print File Labels (user, 2026-11-09: "clone the one for this survey ... since CO files do not have a
 | grouping table, create grouping table for them as well").
 |   co_grouping                          CO numbers, shaped like the other Survey series' grouping tables
 |                                        (lpkn_grouping …): one row per CO file, with a tracking ID and batch numbers.
 |                                        Filled from survey_co_files by survey:co-grouping; new CO files add their row.
 |   survey_print_label_batches / _items  the label batches, cloned from kangis_print_label_* ; items carry the
 |                                        series (prefix) with the grouping row id, as ids repeat across tables.
 |   survey_rack_shelf_labels             rack / shelf labels, cloned from kangis_rack_shelf_labels.
 | Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('co_grouping')) {
            $s->create('co_grouping', function (Blueprint $t) {
                $t->increments('id');
                $t->string('co_awaiting_fileno', 100)->nullable();
                $t->unsignedBigInteger('survey_co_file_id')->nullable();
                $t->date('date')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->string('indexed_by', 100)->nullable();
                $t->dateTime('date_index')->nullable();
                $t->integer('year')->nullable();
                $t->string('landuse', 255)->nullable();
                $t->dateTime('created_at')->nullable();
                $t->dateTime('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->dateTime('deleted_at')->nullable();
                $t->string('deleted_by', 100)->nullable();
                $t->string('number', 100)->nullable();
                $t->string('registry', 255)->nullable();
                $t->string('co_fileno', 100)->nullable();
                $t->string('mapping', 255)->nullable();
                $t->string('group', 100)->nullable();
                $t->string('mdc_batch_no', 100)->nullable();
                $t->string('sys_batch_no', 100)->nullable();
                $t->string('registry_batch_no', 100)->nullable();
                $t->string('tracking_id', 100)->nullable();
                $t->string('test_control', 255)->nullable();
                $t->string('indexing_mapping', 255)->nullable();
                $t->string('shelf_rack', 100)->nullable();
                $t->string('year_batch_no', 100)->nullable();
                $t->index('co_awaiting_fileno', 'co_grouping_fileno_idx');
                $t->index('registry_batch_no', 'co_grouping_batch_idx');
                $t->index('survey_co_file_id', 'co_grouping_co_file_idx');
            });
        }

        if (! $s->hasTable('survey_print_label_batches')) {
            $s->create('survey_print_label_batches', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('batch_number', 100);
                $t->string('prefix', 20)->nullable();
                $t->string('sys_batch_no', 100)->nullable();
                $t->integer('batch_size')->default(0);
                $t->integer('generated_count')->default(0);
                $t->string('status', 30)->default('pending');
                $t->string('full_label', 30)->nullable();
                $t->string('rack_primary', 10)->nullable();
                $t->string('rack_secondary', 10)->nullable();
                $t->integer('shelf_number')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->dateTime('printed_at')->nullable();
                $t->timestamps();
                $t->index(['prefix', 'status'], 'survey_plb_prefix_status_idx');
            });
        }

        if (! $s->hasTable('survey_print_label_batch_items')) {
            $s->create('survey_print_label_batch_items', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('batch_id');
                $t->unsignedBigInteger('file_indexing_id')->nullable();
                $t->string('file_number', 100)->nullable();
                $t->string('prefix', 20)->nullable();
                $t->string('file_title', 255)->nullable();
                $t->string('plot_number', 100)->nullable();
                $t->string('district', 100)->nullable();
                $t->string('lga', 100)->nullable();
                $t->string('land_use_type', 100)->nullable();
                $t->string('shelf_location', 50)->nullable();
                $t->text('qr_code_data')->nullable();
                $t->string('barcode_data', 200)->nullable();
                $t->integer('label_position')->default(0);
                $t->boolean('is_printed')->default(false);
                $t->dateTime('printed_at')->nullable();
                $t->timestamps();
                $t->string('grouping_id', 50)->nullable();   // the series' grouping row id (with prefix)
                $t->index('batch_id', 'survey_plbi_batch_idx');
                $t->index(['prefix', 'grouping_id'], 'survey_plbi_grouping_idx');
                $t->index('file_number', 'survey_plbi_fileno_idx');
            });
        }

        if (! $s->hasTable('survey_rack_shelf_labels')) {
            $s->create('survey_rack_shelf_labels', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('rack', 10);
                $t->string('shelf', 10);
                $t->string('full_label', 20);
                $t->boolean('is_used')->default(false);
                $t->string('counter', 20)->default('0');
                $t->string('assigned', 255)->nullable();
                $t->string('status', 30)->default('Available');
                $t->string('reserved_by', 255)->nullable();
                $t->dateTime('reserved_at')->nullable();
                $t->timestamps();
                $t->index('full_label', 'survey_rsl_label_idx');
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        foreach (['survey_rack_shelf_labels', 'survey_print_label_batch_items', 'survey_print_label_batches', 'co_grouping'] as $t) {
            $s->dropIfExists($t);
        }
    }
};
