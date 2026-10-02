<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Regression guard for the 2026-09-24 incident: phpunit's sqlite override was
 * disabled with no .env.testing in place, so RefreshDatabase ran migrate:fresh
 * against the production 'klaes' MySQL database and wiped every table. A
 * follow-up check then showed the 'sqlsrv' connection (production SQL Server,
 * db 'klas') has no isolated test copy either, and a routine migration run
 * wrote to it directly. These tests fail loudly if either protection regresses.
 */
class ProductionSafetyTest extends TestCase
{
    public function test_default_mysql_connection_is_not_the_production_database(): void
    {
        $database = config('database.connections.mysql.database');

        $this->assertNotEquals('klaes', $database);
        $this->assertSame('klaes_testing', $database);
    }

    public function test_sqlsrv_connection_reads_still_work(): void
    {
        $count = DB::connection('sqlsrv')->table('users')->count();

        $this->assertIsInt($count);
    }

    public function test_sqlsrv_connection_blocks_writes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Blocked UPDATE on the 'sqlsrv' connection during tests");

        DB::connection('sqlsrv')->table('users')->where('id', -999999)->update(['signature' => 'test']);
    }

    public function test_sqlsrv_connection_blocks_unprepared_statements(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Blocked STATEMENT on the 'sqlsrv' connection during tests");

        DB::connection('sqlsrv')->statement('DELETE FROM users WHERE id = -999999');
    }
}
