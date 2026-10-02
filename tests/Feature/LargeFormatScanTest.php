<?php

namespace Tests\Feature;

use App\Http\Controllers\LargeFormatScanController;
use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\ScanUploads\LargeFormatSource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LargeFormatScanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');
        Storage::fake('public');
        Storage::fake('lf_test_source');
        config(['large_format_scans.folder' => Storage::disk('lf_test_source')->path('')]);
        Storage::disk('lf_test_source')->put('new.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII='));
        $schema = Schema::connection('sqlsrv');
        $schema->create('file_indexings', function (Blueprint $t) {
            $t->id(); $t->string('file_number'); $t->string('registry'); $t->string('edms_file_type')->nullable();
        });
        $schema->create('scannings', function (Blueprint $t) {
            $t->id(); $t->integer('file_indexing_id'); $t->string('document_path');
            foreach (['original_filename', 'registry', 'status', 'paper_size', 'document_type', 'definition_code'] as $name) $t->string($name)->nullable();
            $t->string('edms_file_type')->nullable();
            foreach (['uploaded_by', 'display_order', 'definition', 'file_size'] as $name) $t->integer($name)->nullable();
            $t->timestamps();
        });
        $schema->create('pagetypings', function (Blueprint $t) {
            $t->id(); $t->integer('scanning_id'); $t->string('file_path');
            foreach (['page_type', 'page_subtype', 'serial_number', 'definition', 'page_number', 'typed_by', 'qc_status'] as $name) $t->string($name);
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_09_22_120000_create_scan_image_versions_table.php'))->up();
        DB::connection('sqlsrv')->table('file_indexings')->insert(['id' => 1, 'file_number' => 'RES-2026-1', 'registry' => 'Lands Registry']);
        Storage::disk('public')->put('EDMS/SCAN_UPLOAD/Lands_Registry/RES-2026-1/old.png', 'old image');
        Storage::disk('public')->put('EDMS/PAGETYPING/Lands_Registry/RES-2026-1/typed.png', 'old typed image');
        DB::connection('sqlsrv')->table('scannings')->insert([
            'id' => 1, 'file_indexing_id' => 1, 'document_path' => 'EDMS/SCAN_UPLOAD/Lands_Registry/RES-2026-1/old.png',
            'registry' => 'Lands Registry', 'status' => 'completed', 'original_filename' => 'old.png',
            'display_order' => 4, 'definition' => 5, 'definition_code' => '5-RES-2026-1', 'uploaded_by' => 9,
        ]);
        DB::connection('sqlsrv')->table('pagetypings')->insert([
            'id' => 7, 'scanning_id' => 1, 'file_path' => 'EDMS/PAGETYPING/Lands_Registry/RES-2026-1/typed.png',
            'page_type' => 'Survey', 'page_subtype' => 'Plan', 'serial_number' => '123', 'definition' => '5',
            'page_number' => '5', 'typed_by' => '8', 'qc_status' => 'passed',
        ]);
    }

    private function save(array $overrides = []): array
    {
        $request = Request::create('/', 'POST', array_merge([
            'source' => 'new.png', 'file_indexing_id' => 1, 'scanning_id' => 1,
            'expected_path' => 'EDMS/SCAN_UPLOAD/Lands_Registry/RES-2026-1/old.png',
        ], $overrides));
        $request->setUserResolver(fn () => (object) ['id' => 42]);
        return app(LargeFormatScanController::class)->save($request, new LargeFormatSource(), new EdmsDocumentPathResolver())->getData(true);
    }

    public function test_replacement_preserves_classification_identity_and_old_images(): void
    {
        $before = (array) DB::connection('sqlsrv')->table('pagetypings')->first();
        $result = $this->save();
        $scan = $result['document'];
        $this->assertSame(1, $scan['id']);
        $this->assertSame('completed', $scan['status']);
        $this->assertSame(4, $scan['display_order']);
        $this->assertSame(5, $scan['definition']);
        $this->assertSame(9, $scan['uploaded_by']);
        $after = (array) DB::connection('sqlsrv')->table('pagetypings')->first();
        foreach ($before as $key => $value) {
            if (!in_array($key, ['file_path', 'updated_at'])) $this->assertSame($value, $after[$key], $key);
        }
        Storage::disk('public')->assertExists([$scan['document_path'], $after['file_path'], $before['file_path'], 'EDMS/SCAN_UPLOAD/Lands_Registry/RES-2026-1/old.png']);
        $history = DB::connection('sqlsrv')->table('scan_image_versions')->first();
        $this->assertSame(42, $history->replaced_by);
        $this->assertSame($scan['document_path'], $history->new_path);
        $this->assertNotEmpty($history->created_at);
    }

    public function test_add_appends_an_untyped_page(): void
    {
        $scan = $this->save(['scanning_id' => null, 'expected_path' => null])['document'];
        $this->assertSame('pending', $scan['status']);
        $this->assertSame(5, $scan['display_order']);
        $this->assertSame(6, $scan['definition']);
        $this->assertSame(0, DB::connection('sqlsrv')->table('pagetypings')->where('scanning_id', $scan['id'])->count());
        $this->assertSame(0, DB::connection('sqlsrv')->table('scan_image_versions')->count());
    }

    public function test_failed_audit_rolls_back_and_removes_only_new_image(): void
    {
        Schema::connection('sqlsrv')->drop('scan_image_versions');
        $before = Storage::disk('public')->allFiles();
        try { $this->save(); $this->fail('Expected audit failure.'); }
        catch (\Illuminate\Database\QueryException $e) { }
        $this->assertSame($before, Storage::disk('public')->allFiles());
        $this->assertSame('old.png', DB::connection('sqlsrv')->table('scannings')->value('original_filename'));
    }

    public function test_failure_after_typed_copy_restores_pointers_and_cleans_new_copies(): void
    {
        DB::connection('sqlsrv')->statement("CREATE TRIGGER reject_scan_update BEFORE UPDATE ON scannings BEGIN SELECT RAISE(ABORT, 'simulated save failure'); END");
        $beforeFiles = Storage::disk('public')->allFiles();
        $beforeTyping = (array) DB::connection('sqlsrv')->table('pagetypings')->first();
        try { $this->save(); $this->fail('Expected scan update failure.'); }
        catch (\Illuminate\Database\QueryException $e) { }
        $this->assertSame($beforeFiles, Storage::disk('public')->allFiles());
        $this->assertSame($beforeTyping, (array) DB::connection('sqlsrv')->table('pagetypings')->first());
        $this->assertSame(0, DB::connection('sqlsrv')->table('scan_image_versions')->count());
    }

    public function test_first_added_page_gets_first_folio(): void
    {
        DB::connection('sqlsrv')->table('file_indexings')->insert(['id' => 2, 'file_number' => 'RES-2026-2', 'registry' => 'Lands Registry']);
        $scan = $this->save(['file_indexing_id' => 2, 'scanning_id' => null, 'expected_path' => null])['document'];
        $this->assertSame(0, $scan['display_order']);
        $this->assertSame(1, $scan['definition']);
    }

    public function test_stale_replacement_does_not_write(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->save(['expected_path' => 'stale.png']);
    }

    public function test_scan_must_belong_to_selected_file(): void
    {
        DB::connection('sqlsrv')->table('file_indexings')->insert(['id' => 2, 'file_number' => 'RES-2026-2', 'registry' => 'Lands Registry']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->save(['file_indexing_id' => 2]);
    }

    /** @dataProvider unsafePaths */
    public function test_source_cannot_escape_master_folder(string $path): void
    {
        $this->expectException(ValidationException::class);
        (new LargeFormatSource())->resolve($path);
    }

    public static function unsafePaths(): array
    {
        return [['../outside.png'], ['..\\outside.png'], ['C:/outside.png'], ['/outside.png'], ['missing.png']];
    }

    public function test_invalid_image_is_rejected_without_writing(): void
    {
        Storage::disk('lf_test_source')->put('bad.png', '<?php echo 1;');
        $this->expectException(ValidationException::class);
        $this->save(['source' => 'bad.png']);
    }
}
