<?php

namespace Tests\Feature;

use App\Http\Controllers\LandsOneStopShop\ApplicationController;
use App\Support\OpAllottee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * An OP's Grantee is its original allottee. Once a Transfer of Title hangs off it,
 * the OSS form must not overwrite it with the new owner (2026-10-29: 35 OPs found
 * overwritten, e.g. RES-2024-383 Sadi Ahmad -> Abdullahi Muhammad Isah).
 */
class OpAllotteeGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => 'sqlsrv', 'cache.default' => 'array']);
        DB::purge('sqlsrv');
        // Serial propagation is covered by OpSerialIntegrityTest; only the Grantee rule is under test here.
        $this->app->instance(\App\Services\OpSerialSynchronizer::class, \Mockery::spy(\App\Services\OpSerialSynchronizer::class));
        Schema::connection('sqlsrv')->create('pra', function (Blueprint $schema) {
            $schema->increments('id');
            foreach (['instrument_type', 'transaction_type', 'system_source', 'op_serial_number', 'mlsFNo', 'fileno',
                'resolved_fileno', 'source_op_table', 'prop_id', 'parent_prop_id', 'party_1', 'party_2', 'Grantor', 'Grantee',
                'merger_group_id', 'is_merger_op', 'updated_by'] as $field) {
                $schema->string($field)->nullable();
            }
            $schema->bigInteger('source_op_id')->nullable();
            $schema->integer('is_deleted')->nullable();
            $schema->timestamps();
        });
        DB::connection('sqlsrv')->table('pra')->insert([
            ['id' => 1, 'instrument_type' => 'Occupancy Permit (OP)', 'mlsFNo' => 'RES-2024-383', 'fileno' => 'RES-2024-383',
                'prop_id' => '62711', 'op_serial_number' => '29672', 'Grantor' => 'KANO STATE GOVERNMENT', 'party_1' => 'KANO STATE GOVERNMENT',
                'Grantee' => 'SADI AHMAD', 'party_2' => 'SADI AHMAD', 'is_deleted' => 0, 'source_op_table' => null, 'source_op_id' => null],
            ['id' => 2, 'instrument_type' => 'Transfer of Title (OP)', 'mlsFNo' => 'RES-2024-383', 'fileno' => 'RES-2024-383',
                'prop_id' => '307798', 'op_serial_number' => '29672', 'Grantor' => 'SADI AHMAD', 'party_1' => 'SADI AHMAD',
                'Grantee' => 'ABDULLAHI MUHAMMAD ISAH', 'party_2' => 'ABDULLAHI MUHAMMAD ISAH', 'is_deleted' => 0,
                'source_op_table' => 'pra', 'source_op_id' => 1],
            ['id' => 3, 'instrument_type' => 'Occupancy Permit (OP)', 'mlsFNo' => 'RES-2024-999', 'fileno' => 'RES-2024-999',
                'prop_id' => '70000', 'op_serial_number' => '30000', 'Grantor' => 'KANO STATE GOVERNMENT', 'party_1' => 'KANO STATE GOVERNMENT',
                'Grantee' => 'MUSA BELLO', 'party_2' => 'MUSA BELLO', 'is_deleted' => 0, 'source_op_table' => null, 'source_op_id' => null],
        ]);
    }

    private function saveGrantee(int $recordId, string $fileNo, string $serial, string $grantee): void
    {
        $method = new \ReflectionMethod(ApplicationController::class, 'syncOccupancyPermitToPra');
        $method->setAccessible(true);
        $method->invoke(new ApplicationController(), Request::create('/', 'POST', [
            'has_occupancy_permit' => 1,
            'occupancy_permit' => ['record_id' => $recordId, 'op_serial_number' => $serial, 'grantee' => $grantee],
        ]), $fileNo);
    }

    private function grantee(int $id): string
    {
        return (string) DB::connection('sqlsrv')->table('pra')->where('id', $id)->value('Grantee');
    }

    public function test_transfer_party_1_names_follow_the_op_link(): void
    {
        $this->assertSame(['SADI AHMAD'], OpAllottee::transferParty1Names('pra', 1, '62711'));
        $this->assertSame([], OpAllottee::transferParty1Names('pra', 3, '70000'));
    }

    public function test_new_owner_cannot_overwrite_the_allottee_of_an_op_with_a_transfer(): void
    {
        try {
            $this->saveGrantee(1, 'RES-2024-383', '29672', 'Abdullahi Muhammad Isah');
            $this->fail('The new owner was written onto the OP.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('occupancy_permit.grantee', $e->errors());
        }
        $this->assertSame('SADI AHMAD', $this->grantee(1));
    }

    public function test_unchanged_allottee_still_saves(): void
    {
        $this->saveGrantee(1, 'RES-2024-383', '29672', 'sadi  ahmad');
        $this->assertSame('sadi  ahmad', $this->grantee(1));
    }

    public function test_op_without_a_transfer_keeps_an_editable_grantee(): void
    {
        $this->saveGrantee(3, 'RES-2024-999', '30000', 'MUSA BELLO ADAMU');
        $this->assertSame('MUSA BELLO ADAMU', $this->grantee(3));
    }
}
