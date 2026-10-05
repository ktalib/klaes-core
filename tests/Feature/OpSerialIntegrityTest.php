<?php

namespace Tests\Feature;

use App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController;
use App\Services\MlsCommissioningOssApplicationService;
use App\Services\OpSerialSourceResolver;
use App\Services\OpSerialSynchronizer;
use App\Support\OpSerial;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OpSerialIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Every database touched by these tests is explicitly in memory.
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => 'sqlsrv', 'cache.default' => 'array']);
        DB::purge('sqlsrv');
        foreach (['pra', 'instrument_capture', 'oss_applications', 'mls_file_no', 'file_indexings'] as $table) {
            Schema::connection('sqlsrv')->create($table, function (Blueprint $schema) {
                $schema->increments('id');
                foreach (['instrument_type', 'transaction_type', 'system_source', 'op_serial_number', 'mlsFNo', 'fileno', 'file_no',
                    'full_file_number', 'file_number', 'source_op_table', 'source_op_id', 'source_pra_id', 'instrument_capture_id',
                    'source_instrument_capture_id', 'prop_id', 'op_batch', 'temp_fileno', 'party_1', 'party_2', 'party_2_name',
                    'Grantor', 'Grantee', 'file_name', 'location', 'property_description', 'plot_no', 'tp_no', 'lgsaOrCity', 'land_use',
                    'op_type', 'transaction_date', 'regNo', 'serialNo', 'pageNo', 'volumeNo', 'source', 'status', 'application_type',
                    'remarks', 'applicant_name', 'created_by', 'updated_by', 'merger_group_id', 'is_merger_op'] as $field) $schema->string($field)->nullable();
                $schema->integer('is_deleted')->nullable();
                $schema->timestamps();
            });
        }
    }

    /** @dataProvider invalidSerials */
    public function test_missing_and_invalid_op_serials_are_rejected($serial): void
    {
        $this->expectException(ValidationException::class);
        OpSerial::guard(['instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => $serial]);
    }

    public function invalidSerials(): array
    {
        return [[null], [''], [' '], ['0'], ['000'], ['0109'], ['10A'], ['-1'], ['1.5'], [str_repeat('1', 101)]];
    }

    public function test_indexing_unchecked_op_section_ignores_missing_and_stale_serials(): void
    {
        foreach ([null, false, 0, '0'] as $enabled) {
            foreach ([[], ['occupancy_permit_op_serial_number' => ''], ['occupancy_permit_op_serial_number' => 'invalid']] as $data) {
                $validator = \Illuminate\Support\Facades\Validator::make($data, [
                    'occupancy_permit_op_serial_number' => OpSerial::rulesWhenEnabled($enabled),
                ]);
                $this->assertTrue($validator->passes());
                $this->assertArrayNotHasKey('occupancy_permit_op_serial_number', $validator->validated());
            }
        }
    }

    public function test_indexing_checked_op_section_requires_a_valid_serial(): void
    {
        foreach ([true, 1, '1'] as $enabled) {
            foreach ([null, '', '0', 'invalid', '109'] as $serial) {
                $validator = \Illuminate\Support\Facades\Validator::make([
                    'occupancy_permit_op_serial_number' => $serial,
                ], ['occupancy_permit_op_serial_number' => OpSerial::rulesWhenEnabled($enabled)]);
                $this->assertSame($serial === '109', $validator->passes());
            }
        }
    }

    public function test_application_lookup_returns_the_mother_op_even_when_its_serial_is_missing(): void
    {
        Schema::connection('sqlsrv')->create('fileNumber', function (Blueprint $table) {
            $table->increments('id');
            foreach (['mlsfNo', 'FileName', 'plot_no', 'tp_no', 'lga', 'location', 'tracking_id'] as $field) {
                $table->string($field)->nullable();
            }
        });
        $service = \Mockery::mock(\App\Services\Pra\PraRecordService::class);
        $service->shouldReceive('findAllByFileNumber')->with('RES-2025-6064')->once()->andReturn([
            ['id' => 119796, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => null,
                'created_at' => '2025-01-01', 'is_deleted' => 0],
            ['id' => 221970, 'instrument_type' => 'Transfer of Title (OP)', 'op_serial_number' => '999',
                'created_at' => '2026-01-01', 'is_deleted' => 0],
            ['id' => 221971, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '888',
                'created_at' => '2026-02-01', 'is_deleted' => 1],
        ]);
        $response = (new \App\Http\Controllers\LandsOneStopShop\ApplicationController())->lookupFileIndexing(
            Request::create('/', 'GET', ['file_no' => 'RES-2025-6064']), $service
        );
        $data = $response->getData(true)['data'];
        $this->assertTrue($data['has_occupancy_permit']);
        $this->assertSame(119796, $data['op_record_id']);
        $this->assertSame('', $data['op_serial_number']);
    }

    public function test_partial_edits_preserve_serials_and_non_op_records_are_optional(): void
    {
        $old = ['instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109'];
        $this->assertSame('109', OpSerial::guard(['plot_no' => '2832'], $old)['op_serial_number']);
        $this->assertSame(['instrument_type' => 'Deed of Assignment'], OpSerial::guard(['instrument_type' => 'Deed of Assignment']));
        $this->assertSame('109', OpSerial::require(' 109 '));
    }

    public function test_explicit_clearing_or_conversion_to_op_is_rejected(): void
    {
        foreach ([['op_serial_number' => null], ['op_serial_number' => '']] as $input) {
            try { OpSerial::guard($input, ['instrument_type' => 'Occupancy Permit', 'op_serial_number' => '109']); $this->fail('Clearing was accepted.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('op_serial_number', $e->errors()); }
        }
        $this->expectException(ValidationException::class);
        OpSerial::guard(['instrument_type' => 'Occupancy Permit'], ['instrument_type' => 'Deed of Assignment']);
    }

    public function test_commissioning_resolves_the_exact_source_and_rejects_hidden_serial_mismatch(): void
    {
        DB::table('pra')->insert(['id' => 1, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109']);
        DB::table('pra')->insert(['id' => 2, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '110']);
        $resolver = new OpSerialSourceResolver();
        $this->assertSame('110', $resolver->serial(['source_pra_id' => 2]));
        $this->expectException(ValidationException::class);
        $resolver->serial(['source_pra_id' => 2], '109');
    }

    public function test_a_deleted_source_cannot_be_selected(): void
    {
        DB::table('pra')->insert(['id' => 1, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109', 'is_deleted' => 1]);
        $this->expectException(ValidationException::class);
        (new OpSerialSourceResolver())->serial(['source_pra_id' => 1]);
    }

    public function test_linked_tot_inherits_the_mother_serial_and_cannot_diverge(): void
    {
        DB::table('pra')->insert(['id' => 1, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109']);
        $tot = ['instrument_type' => 'Transfer of Title (OP)', 'source_op_table' => 'pra', 'source_op_id' => 1];
        $resolver = new OpSerialSourceResolver();
        $this->assertSame('109', $resolver->guard([], $tot)['op_serial_number']);
        $this->expectException(ValidationException::class);
        $resolver->guard(['op_serial_number' => '110'], $tot);
    }

    public function test_correction_updates_only_linked_records_and_preserves_tot_registration(): void
    {
        DB::table('pra')->insert(['id' => 1, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '110', 'prop_id' => '5']);
        DB::table('pra')->insert(['id' => 2, 'instrument_type' => 'Transfer of Title (OP)', 'source_op_table' => 'pra', 'source_op_id' => 1, 'op_serial_number' => '109', 'regNo' => '0/0/0']);
        DB::table('pra')->insert(['id' => 3, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '54', 'prop_id' => '5']);
        DB::table('mls_file_no')->insert(['full_file_number' => 'RES-2023-6336', 'source_pra_id' => 1]);
        DB::table('oss_applications')->insert(['file_no' => 'RES-2023-6336', 'system_source' => 'OSSOPCHANGEOFNAME']);
        DB::transaction(fn () => (new OpSerialSynchronizer())->sync('pra', 1, '109'));
        $this->assertSame('110', DB::table('pra')->where('id', 2)->value('op_serial_number'));
        $this->assertSame('0/0/0', DB::table('pra')->where('id', 2)->value('regNo'));
        $this->assertSame('54', DB::table('pra')->where('id', 3)->value('op_serial_number'));
        $this->assertSame('110', DB::table('oss_applications')->value('op_serial_number'));
    }

    public function test_conflicting_linked_write_rolls_back_the_source_correction(): void
    {
        DB::table('pra')->insert(['id' => 1, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109']);
        DB::table('pra')->insert(['id' => 2, 'instrument_type' => 'Transfer of Title (OP)', 'source_op_table' => 'pra', 'source_op_id' => 1, 'op_serial_number' => '54']);
        try {
            DB::transaction(function () {
                DB::table('pra')->where('id', 1)->update(['op_serial_number' => '110']);
                (new OpSerialSynchronizer())->sync('pra', 1, '109');
            });
            $this->fail('The conflicting write was accepted.');
        } catch (ValidationException $e) { $this->assertSame('109', DB::table('pra')->where('id', 1)->value('op_serial_number')); }
    }

    public function test_oss_commissioning_mirror_copies_the_serial_from_the_selected_source(): void
    {
        DB::table('pra')->insert(['id' => 1, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109']);
        (new MlsCommissioningOssApplicationService())->sync(['full_file_number' => 'RES-2023-6336', 'source_pra_id' => 1,
            'sub_source' => 'OP Change of Name', 'system_sub_type' => 'OSS', 'file_name' => 'Holder']);
        $this->assertSame('109', DB::table('oss_applications')->value('op_serial_number'));
    }

    public function test_batch_capture_rejects_an_entry_without_serial_before_writing(): void
    {
        $request = Request::create('/', 'POST', ['ops' => [['op_type' => 'OP Resettlement', 'status' => 'Normal', 'grantee' => 'Holder']]]);
        try { (new OpResettlementApplicationController())->opBatchCapture($request); $this->fail('Missing batch serial was accepted.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('ops.0.op_serial_number', $e->errors()); }
        $this->assertSame(0, DB::table('pra')->count());
    }

    public function test_single_capture_and_op_editor_require_serials_before_any_write(): void
    {
        $controller = new OpResettlementApplicationController();
        foreach (['opCaptureAndLink', 'opCaptureForCommissionedFile', 'updateDetails'] as $action) {
            $request = Request::create('/', 'POST', ['tot_pra_id' => 1, 'file_no' => 'RES-2023-6336', 'grantee' => 'Holder',
                'op_type' => 'OP Resettlement', 'status' => 'Normal', 'file_name' => 'Holder', 'customer_type' => 'individual']);
            try {
                if ($action === 'updateDetails') $controller->$action($request, 'pra-1');
                else $controller->$action($request);
                $this->fail('Missing serial was accepted by '.$action);
            } catch (ValidationException $e) { $this->assertArrayHasKey('op_serial_number', $e->errors()); }
        }
        $this->assertSame(0, DB::table('pra')->count());
    }

    public function test_batch_linking_copies_serials_and_is_idempotent(): void
    {
        DB::table('pra')->insert(['id' => 1, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109',
            'party_2' => 'Original Holder', 'prop_id' => '5', 'op_batch' => 'OPB-1']);
        DB::table('mls_file_no')->insert(['full_file_number' => 'RES-2023-6336', 'source_pra_id' => 1, 'file_name' => 'New Holder']);
        DB::table('oss_applications')->insert(['file_no' => 'RES-2023-6336', 'system_source' => 'OSSOPCHANGEOFNAME']);
        $controller = new OpResettlementApplicationController();
        foreach ([1, 2] as $attempt) {
            $response = $controller->linkOpBatchToCommissioned(Request::create('/', 'POST', ['op_batch' => 'OPB-1', 'files' => ['RES-2023-6336']]));
            $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        }
        $this->assertSame(2, DB::table('pra')->count());
        $tot = DB::table('pra')->where('id', '!=', 1)->first();
        $this->assertSame('109', $tot->op_serial_number);
        $this->assertSame('0/0/0', $tot->regNo);
        $this->assertSame('109', DB::table('oss_applications')->value('op_serial_number'));
    }

    public function test_merger_sources_remain_individual_and_the_transfer_aggregation_tracks_a_correction(): void
    {
        DB::table('pra')->insert(['id' => 1, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109', 'merger_group_id' => 'M1', 'is_merger_op' => 1]);
        DB::table('pra')->insert(['id' => 2, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '110', 'merger_group_id' => 'M1', 'is_merger_op' => 1]);
        DB::table('pra')->insert(['id' => 3, 'instrument_type' => 'Transfer of Title (OP)', 'op_serial_number' => '109, 110',
            'merger_group_id' => 'M1', 'is_merger_op' => 0, 'source_op_table' => 'pra', 'source_op_id' => 1]);
        $resolver = new OpSerialSourceResolver();
        $this->assertSame('109, 110', $resolver->guard([], (array) DB::table('pra')->where('id', 3)->first())['op_serial_number']);
        DB::transaction(function () {
            DB::table('pra')->where('id', 2)->update(['op_serial_number' => '111']);
            (new OpSerialSynchronizer())->sync('pra', 2, '110');
        });
        $this->assertSame('109, 111', DB::table('pra')->where('id', 3)->value('op_serial_number'));
        $this->assertSame('109', DB::table('pra')->where('id', 1)->value('op_serial_number'));
    }

    public function test_backfill_dry_run_apply_repeat_and_guarded_rollback(): void
    {
        DB::table('pra')->insert(['id' => 1, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109', 'mlsFNo' => 'RES-2023-6336']);
        DB::table('oss_applications')->insert(['id' => 1, 'file_no' => 'RES-2023-6336', 'system_source' => 'OSSOPCHANGEOFNAME']);
        DB::table('oss_applications')->insert(['id' => 2, 'file_no' => 'RES-2023-6336', 'system_source' => 'OSSOPCHANGEOFNAME', 'op_serial_number' => '54']);
        $this->assertSame(0, Artisan::call('op:serial-integrity'));
        $this->assertNull(DB::table('oss_applications')->where('id', 1)->value('op_serial_number'));
        $this->assertSame(0, Artisan::call('op:serial-integrity', ['--apply' => true]));
        preg_match('/Report: (.+)/', Artisan::output(), $matches);
        $path = trim($matches[1]);
        $this->assertSame('109', DB::table('oss_applications')->where('id', 1)->value('op_serial_number'));
        $this->assertSame('54', DB::table('oss_applications')->where('id', 2)->value('op_serial_number'));
        $report = json_decode(file_get_contents($path), true);
        $this->assertSame('applied', $report['repairs'][0]['status']);
        $this->assertNull($report['repairs'][0]['before']);
        $this->assertSame(0, Artisan::call('op:serial-integrity', ['--apply' => true]));
        $this->assertStringContainsString('Applied 0 verified repairs.', Artisan::output());
        $this->assertSame(0, Artisan::call('op:serial-integrity', ['--rollback' => $path]));
        $this->assertNull(DB::table('oss_applications')->where('id', 1)->value('op_serial_number'));
        $this->assertSame('54', DB::table('oss_applications')->where('id', 2)->value('op_serial_number'));
    }

    public function test_oss_application_serial_edit_updates_its_exact_op_in_the_same_transaction(): void
    {
        DB::table('pra')->insert(['id' => 1, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109', 'mlsFNo' => 'RES-2023-6336']);
        DB::table('oss_applications')->insert(['id' => 1, 'file_no' => 'RES-2023-6336', 'system_source' => 'OSSOPCHANGEOFNAME', 'op_serial_number' => '109']);
        $controller = new \App\Http\Controllers\LandsOneStopShop\ApplicationController();
        $response = $controller->update(Request::create('/', 'POST', ['op_serial_number' => '110']), 1);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertSame('110', DB::table('pra')->where('id', 1)->value('op_serial_number'));
        $this->assertSame('110', DB::table('oss_applications')->where('id', 1)->value('op_serial_number'));
    }

    public function test_oss_application_cannot_save_a_serial_when_its_op_selection_is_ambiguous(): void
    {
        foreach ([1, 2] as $id) DB::table('pra')->insert(['id' => $id, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => '109', 'mlsFNo' => 'RES-2023-6336']);
        DB::table('oss_applications')->insert(['id' => 1, 'file_no' => 'RES-2023-6336', 'system_source' => 'OSSOPCHANGEOFNAME', 'op_serial_number' => '109']);
        try {
            (new \App\Http\Controllers\LandsOneStopShop\ApplicationController())->update(Request::create('/', 'POST', ['op_serial_number' => '110']), 1);
            $this->fail('Ambiguous application edit was accepted.');
        } catch (ValidationException $e) {
            $this->assertSame('109', DB::table('oss_applications')->where('id', 1)->value('op_serial_number'));
            $this->assertSame(['109', '109'], DB::table('pra')->pluck('op_serial_number')->all());
        }
    }
}
