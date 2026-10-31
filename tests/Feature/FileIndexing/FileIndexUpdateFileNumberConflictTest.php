<?php

namespace Tests\Feature\FileIndexing;

use App\Http\Controllers\FileIndexingController;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Editing a file index onto a file number another record already holds used to reach
 * UX_file_indexings_file_number and show the operator the raw SQL error.
 */
class FileIndexUpdateFileNumberConflictTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');
        config(['fileindexing.non_indexable_years' => []]);

        // Raw DDL because the SQLite schema builder drops ->collation(); NOCASE stands in
        // for production's SQL_Latin1_General_CP1_CI_AS.
        DB::connection('sqlsrv')->statement('CREATE TABLE file_indexings (
            id INTEGER PRIMARY KEY,
            file_number VARCHAR COLLATE NOCASE NULL UNIQUE,
            temp_file_no VARCHAR NULL,
            file_title VARCHAR NULL,
            registry_batch_no VARCHAR NULL,
            general_registry VARCHAR NULL,
            is_decommissioned INTEGER NOT NULL DEFAULT 0
        )');

        DB::connection('sqlsrv')->table('file_indexings')->insert([
            ['id' => 1, 'file_number' => 'RES-2003-1001', 'file_title' => 'AISHA YAHAYA HAMZA', 'registry_batch_no' => '1111', 'is_decommissioned' => 0],
            ['id' => 2, 'file_number' => 'RES-2003-100', 'file_title' => 'SALISU GARBA', 'registry_batch_no' => '1101', 'is_decommissioned' => 0],
            ['id' => 3, 'file_number' => 'RES-2003-77', 'file_title' => 'OLD HOLDER', 'registry_batch_no' => null, 'is_decommissioned' => 1],
        ]);
    }

    private function updateRecord(int $id, string $fileNumber): array
    {
        $request = Request::create('/', 'PUT', [
            'file_number' => $fileNumber,
            'file_title' => 'SALISU GARBA',
            'land_use_type' => 'RESIDENTIAL',
            'physical_registry' => 'Registry 2 - Land',
            'general_registry' => 'Land Registry',
            'gender' => 'Male',
            'plot_size' => '450',
            'root_of_title' => 'CofO',
        ]);
        // Unsaved model: the year policy wants a User, nothing here touches the users table.
        $request->setUserResolver(fn () => (new User())->forceFill(['id' => 42]));

        $response = app(FileIndexingController::class)->update($request, $id);

        return [$response->getStatusCode(), $response->getData(true)];
    }

    public function test_renaming_onto_a_number_another_record_holds_is_refused_before_any_write(): void
    {
        [$status, $body] = $this->updateRecord(2, 'RES-2003-1001');

        $this->assertSame(422, $status);
        $this->assertFalse($body['success']);
        $this->assertSame('file_number_taken', $body['error_type']);
        $this->assertSame(
            'File number RES-2003-1001 is already indexed to AISHA YAHAYA HAMZA (record #1, batch 1111). '
            . 'Pick a different file number, or correct that record first.',
            $body['message']
        );
        $this->assertSame(1, $body['conflicting_record']['id']);
        $this->assertStringNotContainsString('SQLSTATE', $body['message']);
        $this->assertSame('RES-2003-100', DB::connection('sqlsrv')->table('file_indexings')->where('id', 2)->value('file_number'));
    }

    public function test_case_and_spacing_variants_conflict_the_same_way_the_index_does(): void
    {
        [$status, $body] = $this->updateRecord(2, '  res-2003-1001 ');

        $this->assertSame(422, $status);
        $this->assertSame('file_number_taken', $body['error_type']);
        $this->assertSame(1, $body['conflicting_record']['id']);
    }

    public function test_a_decommissioned_holder_still_blocks_and_is_named_as_such(): void
    {
        [$status, $body] = $this->updateRecord(2, 'RES-2003-77');

        $this->assertSame(422, $status);
        $this->assertStringContainsString('OLD HOLDER (record #3, decommissioned)', $body['message']);
    }

    public function test_own_number_and_free_numbers_are_not_conflicts(): void
    {
        $controller = app(FileIndexingController::class);
        $find = (new \ReflectionMethod($controller, 'findFileNumberConflict'));
        $find->setAccessible(true);

        $this->assertNull($find->invoke($controller, 2, 'RES-2003-100'));
        $this->assertNull($find->invoke($controller, 2, 'rEs-2003-100'));
        $this->assertNull($find->invoke($controller, 2, 'RES-2003-9999'));
        $this->assertNull($find->invoke($controller, 2, '   '));
        $this->assertNull($find->invoke($controller, 2, null));
        $this->assertSame(1, (int) $find->invoke($controller, 2, 'RES-2003-1001')->id);
    }

    public function test_only_the_file_number_index_violation_is_translated(): void
    {
        $controller = app(FileIndexingController::class);
        $check = new \ReflectionMethod($controller, 'isFileNumberUniqueViolation');
        $check->setAccessible(true);

        // The message SQL Server returned in production on 2026-10-29.
        $indexViolation = new QueryException('update [file_indexings] set [file_number] = ?', ['RES-2003-1001'], new \PDOException(
            "SQLSTATE[23000]: [Microsoft][ODBC Driver 17 for SQL Server][SQL Server]Cannot insert duplicate key row in object "
            . "'dbo.file_indexings' with unique index 'UX_file_indexings_file_number'. The duplicate key value is (RES-2003-1001)."
        ));
        $otherFailure = new QueryException('update [file_indexings] set [plot_size] = ?', ['x'], new \PDOException(
            'SQLSTATE[22018]: Conversion failed when converting the varchar value to data type int.'
        ));

        $this->assertTrue($check->invoke($controller, $indexViolation));
        $this->assertFalse($check->invoke($controller, $otherFailure));
        $this->assertFalse($check->invoke($controller, new \RuntimeException('UX_file_indexings_file_number')));
    }
}
