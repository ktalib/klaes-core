<?php

namespace Tests\Feature;

use App\Services\DashboardApplicationAnalytics;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardApplicationAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');
        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00', 'Africa/Lagos'));
        foreach (['mother_applications', 'subapplications', 'oss_applications'] as $table) {
            Schema::connection('sqlsrv')->create($table, function (Blueprint $schema) {
                $schema->increments('id');
                $schema->string('application_status')->nullable();
                $schema->string('status')->nullable();
                $schema->integer('is_deleted')->nullable();
                $schema->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_trend_counts_real_sources_and_excludes_deleted_and_outside_period(): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('mother_applications')->insert(['created_at' => '2026-10-03 09:00:00', 'application_status' => 'approved']);
        $db->table('subapplications')->insert(['created_at' => '2026-10-03 09:00:00', 'application_status' => 'pending']);
        $db->table('oss_applications')->insert([
            ['created_at' => '2026-10-03 09:00:00', 'status' => 'approved', 'is_deleted' => 0],
            ['created_at' => '2026-10-03 09:00:00', 'status' => 'pending', 'is_deleted' => 1],
            ['created_at' => '2026-09-01 09:00:00', 'status' => 'approved', 'is_deleted' => 0],
        ]);
        $service = new DashboardApplicationAnalytics();
        $rows = $service->get();
        $this->assertCount(7, $rows);
        $this->assertSame('2026-09-28', $rows[0]['date']);
        $this->assertSame(3, $rows[5]['count']);
        $this->assertSame(1, $rows[5]['allocation']);
        $this->assertSame(1, $rows[5]['sectional']);
        $this->assertSame(1, $rows[5]['recertification']);
        $this->assertSame(2, $rows[5]['approved']);
        $approved = $service->get('week', 'approved');
        $this->assertSame(2, $approved[5]['count']);
        $this->assertSame(0, $approved[5]['recertification']);
        $this->assertCount(30, $service->get('month'));
        $this->assertSame(4, array_sum(array_column($service->get('year'), 'count')));
    }

    public function test_empty_period_returns_zero_buckets(): void
    {
        $rows = (new DashboardApplicationAnalytics())->get('quarter');
        $this->assertCount(90, $rows);
        $this->assertSame(0, array_sum(array_column($rows, 'count')));
    }
}
