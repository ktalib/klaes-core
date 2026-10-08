<?php

namespace Tests\Feature;

use App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController;
use App\Services\OpSerialSynchronizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OpUpdatePreservesValuesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');
        $columns = ['mlsfNo', 'fileno', 'tracking_id', 'source_instrument_capture_id', 'source_pra_id', 'instrument_capture_id',
            'source_op_table', 'source_op_id', 'prop_id', 'system_source', 'SOURCE', 'instrument_type', 'transaction_type',
            'is_deleted', 'op_serial_number', 'FileName', 'file_name', 'file_no', 'file_number', 'full_file_number',
            'customer_name', 'entity_name', 'current_holder', 'customer_type', 'file_type', 'land_use', 'purpose',
            'plot_no', 'plot_number', 'tp_no', 'survey_plan_no', 'location', 'property_description', 'property_address',
            'lga', 'lgsaOrCity', 'district', 'op_type', 'serial_no', 'page_no', 'volume_no', 'registration_number',
            'serialNo', 'pageNo', 'volumeNo', 'regNo', 'deeds_date', 'deeds_time', 'transaction_date', 'instrument_date',
            'party_1_name', 'party_2_name', 'party_1_phone', 'party_2_phone', 'party_1_address', 'party_2_address',
            'Grantor', 'Grantee', 'party_1', 'party_2', 'created_at', 'updated_at'];
        foreach (['fileNumber', 'mls_file_no', 'pra', 'instrument_capture', 'file_indexings', 'customers_staging', 'entities_staging', 'oss_applications'] as $table) {
            Schema::connection('sqlsrv')->create($table, function (Blueprint $schema) use ($columns) {
                $schema->increments('id');
                foreach ($columns as $column) $schema->string($column)->nullable();
            });
        }
        $db = DB::connection('sqlsrv');
        $db->table('fileNumber')->insert(['id' => 10, 'mlsfNo' => 'RES-2026-1', 'tracking_id' => 'T1', 'plot_no' => 'OLD PLOT', 'tp_no' => 'OLD TP', 'location' => 'OLD LOCATION', 'lga' => 'OLD LGA']);
        $db->table('mls_file_no')->insert(['tracking_id' => 'T1', 'source_instrument_capture_id' => 1, 'source_pra_id' => 1, 'location' => 'MLS UNCHANGED']);
        foreach (['pra', 'instrument_capture', 'file_indexings', 'customers_staging', 'entities_staging', 'oss_applications'] as $table) {
            $db->table($table)->insert(['id' => 1, 'mlsfNo' => 'RES-2026-1', 'file_number' => 'RES-2026-1', 'file_no' => 'RES-2026-1',
                'instrument_type' => 'Occupancy Permit (OP)', 'transaction_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109',
                'system_source' => 'OSSOPCHANGEOFNAME', 'instrument_capture_id' => 1, 'prop_id' => 'PROP1',
                'plot_no' => 'OLD PLOT', 'plot_number' => 'OLD PLOT', 'tp_no' => 'OLD TP', 'location' => 'OLD LOCATION',
                'lga' => 'OLD LGA', 'lgsaOrCity' => 'OLD LGA', 'district' => 'OLD DISTRICT', 'land_use' => 'RESIDENTIAL', 'purpose' => 'OLD PURPOSE',
                'regNo' => '10/10/10', 'registration_number' => '10/10/10', 'party_1_phone' => '08011111111', 'party_2_address' => 'OLD ADDRESS']);
        }
        $db->table('pra')->insert(['id' => 2, 'mlsfNo' => 'RES-2026-1', 'prop_id' => 'PROP1', 'system_source' => 'OSSOPCHANGEOFNAME',
            'instrument_type' => 'Transfer of Title (OP)', 'transaction_type' => 'Transfer of Title (OP)', 'op_serial_number' => '109',
            'Grantor' => 'OLD GRANTOR', 'party_1' => 'OLD GRANTOR', 'plot_no' => 'SIBLING PLOT', 'purpose' => 'SIBLING PURPOSE', 'regNo' => '20/20/20']);
        $sync = \Mockery::mock(OpSerialSynchronizer::class);
        $sync->shouldReceive('sync')->andReturn([]);
        $this->app->instance(OpSerialSynchronizer::class, $sync);
    }

    private function save(array $fields = [])
    {
        $payload = array_replace(['customer_type' => 'Individual', 'file_name' => 'UPDATED OWNER', 'pra_id' => 1,
            'row_type' => 'op', 'op_serial_number' => '109', 'plot_number' => null, 'tp_number' => '', 'location' => '   ',
            'lga' => null, 'district' => '', 'land_use' => null, 'purpose' => '', 'registration_number' => null,
            'party_1_phone' => null, 'party_2_address' => ''], $fields);
        return app(OpResettlementApplicationController::class)->updateDetails(Request::create('/', 'PUT', $payload), '10');
    }

    public function test_blanks_preserve_values_across_all_update_sources_and_siblings(): void
    {
        $response = $this->save();
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $db = DB::connection('sqlsrv');
        foreach (['fileNumber', 'pra', 'instrument_capture', 'file_indexings'] as $table) {
            $row = $db->table($table)->where('id', $table === 'fileNumber' ? 10 : 1)->first();
            $this->assertSame('OLD PLOT', $row->plot_no, $table);
            $this->assertSame('OLD TP', $row->tp_no, $table);
            $this->assertSame('OLD LOCATION', $row->location, $table);
        }
        $this->assertSame('SIBLING PLOT', $db->table('pra')->where('id', 2)->value('plot_no'));
        $this->assertSame('SIBLING PURPOSE', $db->table('pra')->where('id', 2)->value('purpose'));
        $this->assertSame('20/20/20', $db->table('pra')->where('id', 2)->value('regNo'));
        $this->assertSame('08011111111', $db->table('instrument_capture')->value('party_1_phone'));
        $this->assertSame('OLD ADDRESS', $db->table('instrument_capture')->value('party_2_address'));
        $this->assertSame('UPDATED OWNER', $db->table('fileNumber')->value('FileName'));
        $this->assertSame('MLS UNCHANGED', $db->table('mls_file_no')->value('location'));
    }

    public function test_nonblank_changes_still_update_linked_fields(): void
    {
        $response = $this->save(['plot_number' => 'NEW PLOT', 'purpose' => 'NEW PURPOSE', 'location' => 'NEW LOCATION']);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        foreach (['fileNumber', 'pra', 'file_indexings'] as $table) {
            $this->assertSame('NEW PLOT', DB::connection('sqlsrv')->table($table)->value('plot_no'));
        }
        $this->assertSame('NEW PLOT', DB::connection('sqlsrv')->table('instrument_capture')->value('plot_number'));
        $this->assertSame('NEW PURPOSE', DB::connection('sqlsrv')->table('pra')->where('id', 2)->value('purpose'));
    }

    public function test_rejected_transaction_keeps_stored_party(): void
    {
        $response = $this->save(['pra_id' => 2, 'row_type' => 'transfer_of_title', 'party_1_name' => '   ']);
        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('OLD GRANTOR', DB::connection('sqlsrv')->table('pra')->where('id', 2)->value('Grantor'));
    }
}
