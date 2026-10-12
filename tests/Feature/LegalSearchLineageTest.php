<?php

namespace Tests\Feature;

use App\Services\LegalSearchService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LegalSearchService::lineageFor() — the file's predecessors and successors,
 * as the LAAS Portal reads them.
 */
class LegalSearchLineageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');

        Schema::connection('sqlsrv')->create('decommissioned_files', function (Blueprint $t) {
            $t->increments('id');
            $t->string('file_no')->nullable();
            $t->string('mls_file_no')->nullable();
            $t->string('file_name')->nullable();
            $t->text('successor_file_no')->nullable();
            $t->text('decommissioning_reason')->nullable();
            $t->dateTime('decommissioning_date')->nullable();
            $t->string('event_type')->nullable();
            $t->integer('false_decommissioning')->nullable();
        });
        Schema::connection('sqlsrv')->create('file_indexings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('file_number')->nullable();
            $t->string('file_title')->nullable();
            $t->text('related_fileno')->nullable();
            $t->dateTime('deleted_at')->nullable();
        });
    }

    public function test_child_of_a_batch_subdivision_finds_its_mother(): void
    {
        // One mother retired into three children: successor_file_no is a CSV list.
        DB::connection('sqlsrv')->table('decommissioned_files')->insert([
            'file_no' => 'RES-2021-13',
            'mls_file_no' => 'RES-2021-13',
            'successor_file_no' => 'RES-2026-20, RES-2026-21,RES-2026-22',
            'decommissioning_reason' => 'Plot Subdivision into 3 fragments',
            'decommissioning_date' => '2026-09-14 10:00:00',
        ]);

        $lineage = app(LegalSearchService::class)->lineageFor('RES-2026-21');

        $this->assertSame(['RES-2021-13'], $lineage['previous_file_nos']);
        $this->assertFalse($lineage['is_superseded']);
    }

    public function test_a_file_whose_number_is_only_a_substring_of_a_successor_is_not_a_child(): void
    {
        DB::connection('sqlsrv')->table('decommissioned_files')->insert([
            'file_no' => 'RES-2021-13',
            'successor_file_no' => 'RES-2026-210,RES-2026-211',
        ]);

        $lineage = app(LegalSearchService::class)->lineageFor('RES-2026-21');

        $this->assertSame([], $lineage['previous_file_nos']);
    }

    public function test_retired_mother_lists_every_successor(): void
    {
        DB::connection('sqlsrv')->table('decommissioned_files')->insert([
            'file_no' => 'RES-2021-13',
            'mls_file_no' => 'RES-2021-13',
            'successor_file_no' => 'RES-2026-20,RES-2026-21',
            'decommissioning_reason' => 'Plot Subdivision into 2 fragments',
            'decommissioning_date' => '2026-09-14 10:00:00',
        ]);

        $lineage = app(LegalSearchService::class)->lineageFor('RES-2021-13');

        $this->assertTrue($lineage['is_superseded']);
        $this->assertSame(['RES-2026-20', 'RES-2026-21'], array_column($lineage['successor_files'], 'file_no'));
    }
}
