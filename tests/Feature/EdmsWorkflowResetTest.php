<?php

namespace Tests\Feature;

use App\Models\FileIndexing;
use App\Support\EdmsWorkflowReset;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EdmsWorkflowResetTest extends TestCase
{
    private array $baseline = ['reset_at' => '2026-10-07 18:00:00.000', 'scannings_max_id' => 10, 'pagetypings_max_id' => 10];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');
        Storage::fake('public');
        $this->app->useStoragePath(Storage::disk('public')->path('test-storage'));
        mkdir(storage_path('app'), 0777, true);
        foreach (['scannings', 'pagetypings'] as $table) {
            Schema::connection('sqlsrv')->create($table, function (Blueprint $t) {
                $t->id(); $t->integer('file_indexing_id'); $t->integer('display_order')->default(0); $t->dateTime('updated_at');
            });
            DB::connection('sqlsrv')->table($table)->insert(['id' => 10, 'file_indexing_id' => 1, 'updated_at' => '2026-10-07 17:00:00']);
        }
    }

    public function test_preserved_documents_remain_counted_but_progress_restarts(): void
    {
        $file = (new FileIndexing())->forceFill(['id' => 1]);
        $this->assertSame('Typed', $file->status);
        file_put_contents(storage_path('app/edms-workflow-reset.json'), json_encode($this->baseline));
        $this->assertSame('Indexed', $file->status);
        $this->assertSame(1, $file->scannings()->count());
        $this->assertSame(1, $file->pagetypings()->count());
        $this->assertSame([], EdmsWorkflowReset::counts('pagetypings', collect([1]), $this->baseline)->all());
    }

    public function test_new_scanning_and_retyping_advance_progress_after_reset(): void
    {
        file_put_contents(storage_path('app/edms-workflow-reset.json'), json_encode($this->baseline));
        $file = (new FileIndexing())->forceFill(['id' => 1]);
        DB::connection('sqlsrv')->table('scannings')->insert(['id' => 11, 'file_indexing_id' => 1, 'updated_at' => '2026-10-07 18:00:00']);
        $this->assertSame('Scanned', $file->status);
        DB::connection('sqlsrv')->table('pagetypings')->where('id', 10)->update(['updated_at' => '2026-10-07 18:01:00']);
        $this->assertSame('Typed', $file->status);
        $this->assertSame(1, EdmsWorkflowReset::counts('pagetypings', collect([1]), $this->baseline)->get(1));
    }
}
