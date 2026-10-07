<?php

namespace Tests\Feature;

use App\Http\Controllers\MlsFileNoController;
use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\Edms\PassportPageTypingService;
use App\Services\EdmsScanUploadFolderService;
use App\Services\FilePassportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class PassportPageTypingTest extends TestCase
{
    private EdmsDocumentPathResolver $paths;
    private string $source = 'EDMS/SCAN_UPLOAD/Lands_Registry/RES-2026-1/passport.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');
        Storage::fake('public');
        FilePassportService::flushCache();
        $this->paths = new class extends EdmsDocumentPathResolver {
            public function absolute(string $path): string
            {
                return Storage::disk('public')->path($path);
            }
        };
        $this->app->instance(EdmsDocumentPathResolver::class, $this->paths);
        $schema = Schema::connection('sqlsrv');
        $schema->create('file_indexings', function (Blueprint $t) {
            $t->id(); $t->string('file_number'); $t->string('registry');
            $t->string('edms_file_type')->nullable(); $t->boolean('is_updated')->default(false); $t->timestamps();
        });
        $schema->create('scannings', function (Blueprint $t) {
            $t->id(); $t->integer('file_indexing_id'); $t->string('document_path');
            foreach (['original_filename', 'registry', 'status', 'paper_size', 'document_type', 'definition_code', 'edms_file_type', 'notes'] as $name) $t->string($name)->nullable();
            foreach (['uploaded_by', 'display_order', 'definition', 'file_size', 'is_pdf_converted'] as $name) $t->integer($name)->nullable();
            $t->timestamps();
        });
        $schema->create('pagetypings', function (Blueprint $t) {
            $t->id(); $t->integer('file_indexing_id'); $t->integer('scanning_id'); $t->string('file_path');
            foreach (['page_type', 'page_subtype', 'serial_number', 'serial_suffix', 'page_code', 'definition_code', 'source', 'registry', 'edms_file_type', 'qc_status'] as $name) $t->string($name)->nullable();
            foreach (['cover_type_id', 'definition', 'page_number', 'typed_by', 'qc_overridden', 'has_qc_issues', 'is_booklet_page', 'is_bcfc_page'] as $name) $t->integer($name)->nullable();
            $t->timestamps();
        });
        $schema->create('PageType', function (Blueprint $t) {$t->id(); $t->string('PageType');});
        $schema->create('PageSubType', function (Blueprint $t) {$t->id(); $t->integer('PageTypeId'); $t->string('PageSubType');});
        $schema->create('CoverType', function (Blueprint $t) {$t->integer('Id'); $t->string('Name');});
        $schema->create('oss_applications', function (Blueprint $t) {$t->id(); $t->string('file_no'); $t->string('passport_photo')->nullable(); $t->timestamps();});
        $db = DB::connection('sqlsrv');
        $db->table('file_indexings')->insert(['id' => 1, 'file_number' => 'RES-2026-1', 'registry' => '2']);
        $db->table('PageType')->insert(['id' => 11, 'PageType' => 'Image']);
        $db->table('PageSubType')->insert(['id' => 28, 'PageTypeId' => 11, 'PageSubType' => 'Passport']);
        $db->table('CoverType')->insert(['Id' => 1, 'Name' => 'Front Cover']);
        $db->table('oss_applications')->insert(['file_no' => 'RES-2026-1', 'passport_photo' => $this->source]);
        Storage::disk('public')->put($this->source, 'passport original');
    }

    private function attributes(): array
    {
        return ['file_indexing_id' => 1, 'document_path' => $this->source, 'document_type' => 'Passport Photograph', 'uploaded_by' => 42, 'paper_size' => 'A4'];
    }

    public function test_passport_is_typed_with_original_and_archive_preserved(): void
    {
        $scan = app(PassportPageTypingService::class)->register($this->attributes());
        $page = $scan->pagetypings->first();
        $this->assertSame('11', $page->page_type);
        $this->assertSame('28', $page->page_subtype);
        $this->assertSame('0', $page->serial_number);
        $this->assertSame('a', $page->serial_suffix);
        $this->assertSame('FC-I-P-0a', $page->page_code);
        $this->assertSame('1-FC-I-P-0a', $page->definition_code);
        $this->assertSame('pending', $page->qc_status);
        $this->assertSame('completed', $scan->status);
        $this->assertSame($this->source, $scan->document_path);
        $this->assertSame(42, $page->typed_by);
        foreach ([$this->source, $page->file_path, str_replace('/PAGETYPING/', '/ARCHIVE_Doc_WARE/', $page->file_path)] as $path) {
            $this->assertSame('passport original', Storage::disk('public')->get($path));
        }
    }

    public function test_replacement_appends_without_overwriting_previous_passport(): void
    {
        $service = app(PassportPageTypingService::class);
        $first = $service->register($this->attributes());
        Storage::disk('public')->put('replacement.jpg', 'replacement');
        $second = $service->register(array_merge($this->attributes(), ['document_path' => 'replacement.jpg']));
        $this->assertSame(2, $second->pagetypings->first()->page_number);
        $this->assertSame('passport original', Storage::disk('public')->get($first->pagetypings->first()->file_path));
        $this->assertSame('replacement', Storage::disk('public')->get($second->pagetypings->first()->file_path));
        $this->assertSame(2, DB::connection('sqlsrv')->table('pagetypings')->count());
    }

    public function test_existing_disk_copy_is_not_overwritten(): void
    {
        $occupied = 'EDMS/PAGETYPING/Lands_Registry/RES-2026-1/A4/1-FC-I-P-0a.jpg';
        Storage::disk('public')->put($occupied, 'existing document');
        $scan = app(PassportPageTypingService::class)->register($this->attributes());
        $this->assertSame(2, $scan->pagetypings->first()->page_number);
        $this->assertSame('existing document', Storage::disk('public')->get($occupied));
    }

    public function test_failed_archive_copy_leaves_no_partial_records_or_typed_copy(): void
    {
        $paths = Mockery::mock($this->paths)->makePartial();
        $paths->shouldReceive('copyWithin')->with($this->source, Mockery::on(fn ($p) => str_contains($p, '/ARCHIVE_Doc_WARE/')))->andReturn(false);
        try {
            (new PassportPageTypingService($paths))->register($this->attributes());
            $this->fail('Expected the failed copy to abort registration.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('copies', $e->getMessage());
        }
        $this->assertSame(0, DB::connection('sqlsrv')->table('scannings')->count());
        $this->assertSame(0, DB::connection('sqlsrv')->table('pagetypings')->count());
        $this->assertSame([$this->source], Storage::disk('public')->allFiles());
    }

    public function test_missing_classification_does_not_create_an_untyped_passport(): void
    {
        DB::connection('sqlsrv')->table('PageSubType')->delete();
        try {
            app(PassportPageTypingService::class)->register($this->attributes());
            $this->fail('Expected missing classification to abort registration.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('classification', $e->getMessage());
        }
        $this->assertSame(0, DB::connection('sqlsrv')->table('scannings')->count());
        Storage::disk('public')->assertExists($this->source);
    }

    public function test_page_insert_failure_rolls_back_scan_and_copies(): void
    {
        // This connection is the isolated SQLite database set up above.
        DB::connection('sqlsrv')->unprepared("CREATE TRIGGER reject_page BEFORE INSERT ON pagetypings BEGIN SELECT RAISE(ABORT, 'test page failure'); END");
        try {
            app(PassportPageTypingService::class)->register($this->attributes());
            $this->fail('Expected the page insert to fail.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('test page failure', $e->getMessage());
        }
        $this->assertSame(0, DB::connection('sqlsrv')->table('scannings')->count());
        $this->assertSame(0, DB::connection('sqlsrv')->table('pagetypings')->count());
        $this->assertSame([$this->source], Storage::disk('public')->allFiles());
    }

    public function test_commissioning_upload_creates_the_typed_passport(): void
    {
        $request = Request::create('/commission', 'POST', [], [], ['passport' => UploadedFile::fake()->createWithContent('passport.jpg', 'commissioned passport')]);
        $method = new ReflectionMethod(MlsFileNoController::class, 'storeCommissioningPassport');
        $method->setAccessible(true);
        $result = $method->invoke(app(MlsFileNoController::class), $request, 'RES-2026-1', ['path' => 'EDMS/SCAN_UPLOAD/Lands_Registry/RES-2026-1']);
        $this->assertTrue($result['stored']);
        $this->assertNotEmpty($result['page_typing_id']);
        $this->assertSame('FC-I-P-0a', DB::connection('sqlsrv')->table('pagetypings')->value('page_code'));
    }

    public function test_edit_upload_auto_types_and_explicit_removal_removes_its_typed_copies(): void
    {
        $folders = Mockery::mock(EdmsScanUploadFolderService::class);
        $folders->shouldReceive('ensureWithFolios')->once()->andReturn(['path' => 'EDMS/SCAN_UPLOAD/Lands_Registry/RES-2026-1']);
        $this->app->instance(EdmsScanUploadFolderService::class, $folders);
        $service = app(FilePassportService::class);
        $result = $service->store(UploadedFile::fake()->createWithContent('passport.jpg', 'edited passport'), 'RES-2026-1', 'oss_commissioning_edit');
        $this->assertTrue($result['stored']);
        $typed = DB::connection('sqlsrv')->table('pagetypings')->value('file_path');
        $this->assertNotNull($typed);
        $this->assertSame($result['path'], DB::connection('sqlsrv')->table('oss_applications')->value('passport_photo'));
        $this->assertTrue($service->remove('RES-2026-1'));
        $this->assertSame(0, DB::connection('sqlsrv')->table('pagetypings')->count());
        $this->assertSame(0, DB::connection('sqlsrv')->table('scannings')->count());
        Storage::disk('public')->assertMissing([$result['path'], $typed, str_replace('/PAGETYPING/', '/ARCHIVE_Doc_WARE/', $typed)]);
        Storage::disk('public')->assertExists($this->source);
    }
}
