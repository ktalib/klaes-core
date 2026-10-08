<?php

namespace Tests\Feature;

use App\Http\Controllers\SystemAdmin\ConfigurableEntriesController;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DcivSerialEditingTest extends TestCase
{
    public function test_locked_dciv_and_lpcc_counters_can_be_saved_repeatedly_but_survey_stays_locked(): void
    {
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');
        $db = DB::connection('sqlsrv');
        foreach (['dciv_serial_control', 'gkn_serial_control'] as $table) {
            $db->statement("CREATE TABLE $table (id INTEGER PRIMARY KEY, prefix TEXT, year INTEGER, last_serial INTEGER, is_initialized INTEGER, is_locked INTEGER, initialized_at TEXT, initialized_by INTEGER, created_at TEXT, updated_at TEXT)");
        }
        $user = new class extends User {
            public function assignedRoleNames(): array { return ['Supper Admin']; }
            public function isSuperAdmin(): bool { return true; }
        };
        $user->forceFill(['id' => 10]);
        Auth::setUser($user);
        $audit = \Mockery::mock(AuditService::class);
        $audit->shouldReceive('logAction')->times(4)->andReturn(new AuditLog());
        $this->app->instance(AuditService::class, $audit);
        $controller = app(ConfigurableEntriesController::class);
        foreach (['DCIV', 'LPCC'] as $prefix) {
            $db->table('dciv_serial_control')->insert(['prefix' => $prefix, 'year' => date('Y'), 'last_serial' => 100, 'is_initialized' => 1, 'is_locked' => 1]);
            foreach ([120, 90] as $serial) {
                $request = Request::create('/', 'POST', ['name' => $prefix, 'last_serial' => $serial], [], [], ['HTTP_ACCEPT' => 'application/json']);
                $this->app->instance('request', $request);
                $response = $controller->initializeSerial($request, 'deeds');
                $this->assertSame(200, $response->getStatusCode(), $response->getContent());
                $this->assertSame($serial, $db->table('dciv_serial_control')->where('prefix', $prefix)->value('last_serial'));
                $this->assertStringNotContainsString('and locked', $response->getContent());
            }
        }
        $db->table('gkn_serial_control')->insert(['prefix' => 'GKN', 'last_serial' => 100, 'is_initialized' => 1, 'is_locked' => 1]);
        $request = Request::create('/', 'POST', ['name' => 'GKN', 'last_serial' => 120], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->app->instance('request', $request);
        $this->assertSame(422, $controller->initializeSerial($request, 'survey')->getStatusCode());
        $this->assertSame(100, $db->table('gkn_serial_control')->value('last_serial'));
    }

    public function test_locked_dciv_counter_renders_an_edit_form(): void
    {
        $counter = (object) ['name' => 'DCIV', 'label' => 'DCIV', 'year' => 2026, 'exists' => true, 'last_serial' => 100,
            'locked' => true, 'initialized' => true, 'initialized_by' => null, 'initialized_at' => null];
        $register = (object) ['key' => 'deeds', 'label' => ConfigurableEntriesController::SERIAL_REGISTERS['deeds']['label'],
            'icon' => 'book-marked', 'yearly' => true, 'year' => 2026, 'where' => 'DCIV Generation',
            'key_label' => 'Prefix', 'locked_count' => 1, 'counters' => collect([$counter])];
        $html = view('system_admin.configurable_entries.partials.serials', ['registers' => collect(['deeds' => $register])])->render();
        $this->assertStringContainsString('DCIV file numbers', $html);
        $this->assertStringNotContainsString('Deeds', $html);
        $this->assertStringNotContainsString('Set once, locked', $html);
        $this->assertStringContainsString('Save serial', $html);
        $this->assertStringContainsString('name="last_serial"', $html);
        $this->assertStringContainsString('value="100"', $html);
    }
}
