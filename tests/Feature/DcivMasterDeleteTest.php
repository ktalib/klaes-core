<?php

namespace Tests\Feature;

use App\Http\Controllers\DcivMasterDeleteController;
use App\Models\User;
use App\Services\IndexingDuplicateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DcivMasterDeleteTest extends TestCase
{
    private $db;
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();
        // All mutations in this suite use an ephemeral database, never a registry.
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');
        $this->db = DB::connection('sqlsrv');
        $definitions = [
            'file_indexings' => 'id INTEGER PRIMARY KEY, file_number TEXT, registry TEXT, is_deleted INTEGER DEFAULT 0, tracking_id TEXT, file_title TEXT, dciv_fileno TEXT, dciv_status INTEGER, dciv_reason TEXT',
            'dciv_file_no' => 'id INTEGER PRIMARY KEY, full_file_number TEXT, batch_no TEXT, is_deleted INTEGER DEFAULT 0',
            'fileNumber' => 'id INTEGER PRIMARY KEY, mlsfNo TEXT, kangisFileNo TEXT, NewKANGISFileNo TEXT, st_file_no TEXT, tracking_id TEXT',
            'customers_staging' => 'id INTEGER PRIMARY KEY, file_number TEXT',
            'entities_staging' => 'id INTEGER PRIMARY KEY, file_number TEXT',
            'mls_file_no' => 'id INTEGER PRIMARY KEY, full_file_number TEXT',
            'dciv_link' => 'id INTEGER PRIMARY KEY, main_file_number TEXT, related_file_number TEXT',
            'master_dciv_links' => 'id INTEGER PRIMARY KEY, dciv_file_no_id INTEGER, dciv_file_number TEXT, related_file_number TEXT, dciv_reason TEXT',
            'dciv_grouping' => 'id INTEGER PRIMARY KEY, dciv_fileno TEXT, mapping INTEGER',
            'scannings' => 'id INTEGER PRIMARY KEY, file_indexing_id INTEGER',
            'dciv_serial_control' => 'id INTEGER PRIMARY KEY, last_serial INTEGER',
        ];
        foreach ($definitions as $name => $columns) {
            $this->db->statement("CREATE TABLE [$name] ($columns)");
        }
        $user = new User();
        $user->forceFill(['id' => 10, 'assign_role' => 'Supper Admin', 'first_name' => 'Test']);
        Auth::setUser($user);
        $this->controller = new class extends DcivMasterDeleteController {
            public array $audit = [];
            protected function logMasterDelete(string $resourceType, $resourceId, array $snapshot, array $counts, string $what): void
            {
                $this->audit = compact('snapshot', 'counts');
            }
        };
        foreach ([1, 2] as $id) {
            $number = "DCIV-2026-$id";
            $this->db->table('dciv_file_no')->insert(['id' => $id, 'full_file_number' => $number, 'batch_no' => 'BATCH']);
            $this->db->table('file_indexings')->insert(['id' => $id + 100, 'file_number' => $number, 'registry' => 'DCIV']);
            $this->db->table('fileNumber')->insert(['id' => $id, 'mlsfNo' => $number]);
            $this->db->table('dciv_grouping')->insert(['id' => $id, 'dciv_fileno' => $number, 'mapping' => 1]);
        }
        $this->db->table('file_indexings')->insert(['id' => 200, 'file_number' => 'RES-2026-1', 'registry' => 'LAND', 'dciv_fileno' => 'DCIV-2026-1', 'dciv_status' => 1]);
        $this->db->table('dciv_link')->insert(['id' => 1, 'main_file_number' => 'DCIV-2026-1', 'related_file_number' => 'RES-2026-1']);
        $this->db->table('master_dciv_links')->insert(['id' => 1, 'dciv_file_no_id' => 1, 'dciv_file_number' => 'DCIV-2026-1', 'related_file_number' => 'RES-2026-1']);
        $this->db->table('dciv_serial_control')->insert(['id' => 1, 'last_serial' => 2]);
    }

    private function runDelete(string $id = '1', string $confirm = 'DCIV-2026-1')
    {
        return $this->controller->destroy(Request::create('/', 'DELETE', ['confirm' => $confirm]), $id, new IndexingDuplicateService());
    }

    public function test_role_and_confirmation_are_required(): void
    {
        Auth::user()->assign_role = 'Land';
        $this->assertSame(403, $this->runDelete()->getStatusCode());
        Auth::user()->assign_role = 'Supper Admin';
        $this->assertSame(422, $this->runDelete('1', 'wrong')->getStatusCode());
        $this->assertSame(2, $this->db->table('dciv_file_no')->count());
    }

    public function test_deletes_one_file_preserving_siblings_related_files_and_counter(): void
    {
        $response = $this->runDelete();
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertFalse($this->db->table('file_indexings')->where('id', 101)->exists());
        $this->assertFalse($this->db->table('fileNumber')->where('id', 1)->exists());
        $this->assertFalse($this->db->table('dciv_file_no')->where('id', 1)->exists());
        $this->assertTrue($this->db->table('dciv_file_no')->where('id', 2)->exists());
        $this->assertTrue($this->db->table('file_indexings')->where('id', 102)->exists());
        $this->assertSame(0, $this->db->table('file_indexings')->where('id', 200)->value('dciv_status'));
        $this->assertSame(0, $this->db->table('dciv_grouping')->where('id', 1)->value('mapping'));
        $this->assertSame(1, $this->db->table('dciv_grouping')->where('id', 2)->value('mapping'));
        $this->assertSame(2, $this->db->table('dciv_serial_control')->value('last_serial'));
        $this->assertNotEmpty($this->controller->audit['snapshot']);
    }

    public function test_indexed_source_uses_its_own_id_and_rejects_other_registries(): void
    {
        $this->assertSame(404, $this->runDelete('fi-1')->getStatusCode());
        $this->assertSame(404, $this->runDelete('fi-200', 'RES-2026-1')->getStatusCode());
        $this->assertSame(200, $this->runDelete('fi-101')->getStatusCode());
    }

    public function test_existing_scans_block_all_deletions(): void
    {
        $this->db->table('scannings')->insert(['file_indexing_id' => 101]);
        $this->assertSame(409, $this->runDelete()->getStatusCode());
        $this->assertSame(2, $this->db->table('dciv_file_no')->count());
        $this->assertSame(1, $this->db->table('master_dciv_links')->count());
    }

    public function test_failure_after_indexing_purge_rolls_everything_back(): void
    {
        $this->db->statement("CREATE TRIGGER refuse_delete BEFORE DELETE ON dciv_file_no BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $this->assertSame(500, $this->runDelete()->getStatusCode());
        $this->assertTrue($this->db->table('file_indexings')->where('id', 101)->exists());
        $this->assertTrue($this->db->table('fileNumber')->where('id', 1)->exists());
        $this->assertSame(1, $this->db->table('master_dciv_links')->count());
        $this->assertEmpty($this->controller->audit);
    }

    public function test_another_investigation_of_related_land_file_is_preserved(): void
    {
        $this->db->table('master_dciv_links')->insert(['dciv_file_no_id' => 2, 'dciv_file_number' => 'DCIV-2026-2', 'related_file_number' => 'RES-2026-1', 'dciv_reason' => 'Other investigation']);
        $this->assertSame(200, $this->runDelete()->getStatusCode());
        $this->assertSame('DCIV-2026-2', $this->db->table('file_indexings')->where('id', 200)->value('dciv_fileno'));
        $this->assertSame(1, $this->db->table('file_indexings')->where('id', 200)->value('dciv_status'));
    }

    public function test_ambiguous_missing_and_inbound_linked_records_are_not_deleted(): void
    {
        $this->db->table('file_indexings')->insert(['id' => 300, 'file_number' => 'DCIV-2026-1', 'registry' => 'DCIV']);
        $this->assertSame(409, $this->runDelete()->getStatusCode());
        $this->db->table('file_indexings')->whereIn('id', [101, 300])->delete();
        $this->assertSame(409, $this->runDelete()->getStatusCode());
        $this->db->table('file_indexings')->insert(['id' => 101, 'file_number' => 'DCIV-2026-1', 'registry' => 'DCIV']);
        $this->db->table('dciv_link')->insert(['main_file_number' => 'DCIV-2026-2', 'related_file_number' => 'DCIV-2026-1']);
        $this->assertSame(409, $this->runDelete()->getStatusCode());
        $this->assertSame(2, $this->db->table('dciv_file_no')->count());
    }

    public function test_action_is_admin_only_and_escapes_file_number(): void
    {
        $data = ['recordId' => 'fi-101', 'fileNumber' => 'DCIV-"<test>'];
        $html = view('dciv_generation.partials.master-delete', $data)->render();
        $this->assertStringContainsString('/fi-101/master-delete', $html);
        $this->assertStringContainsString('DCIV-&quot;&lt;test&gt;', $html);
        Auth::user()->assign_role = 'Land';
        $this->assertStringNotContainsString('Master Delete', view('dciv_generation.partials.master-delete', $data)->render());
    }
}
