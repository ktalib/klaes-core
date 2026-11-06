<?php

namespace Tests\Feature;

use App\Http\Controllers\IndexingDuplicateController;
use App\Models\User;
use App\Services\IndexingDuplicateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Reverse Duplicate on the Indexing Duplicates page: Super Admin only, and refused while a
 * live record holds the number. READ-ONLY against sqlsrv: nothing here reaches a write.
 */
class IndexingDuplicateReverseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function nonAdmin(): User
    {
        $user = User::query()->whereRaw("LOWER(ISNULL(type, '')) NOT IN ('supper admin', 'super admin')")
            ->orderBy('id')->limit(50)->get()
            ->first(fn (User $u) => !$u->isSuperAdmin());
        if (!$user) {
            $this->markTestSkipped('No non-super-admin account in this database.');
        }

        return $user;
    }

    public function test_only_a_super_admin_can_reverse(): void
    {
        $this->actingAs($this->nonAdmin());
        $controller = app(IndexingDuplicateController::class);
        $service = app(IndexingDuplicateService::class);

        $this->assertSame(403, $controller->reversePreview(1, $service)->getStatusCode());
        $this->assertSame(403, $controller->reverse(new Request(['reason' => 'A test reason']), 1, $service)->getStatusCode());
    }

    public function test_a_number_held_by_a_live_file_blocks_the_reversal(): void
    {
        // Any moved file whose number has been indexed again.
        $row = DB::connection('sqlsrv')->table('indexing_duplicates as d')
            ->join('file_indexings as f', 'f.file_number', '=', 'd.file_number')
            ->whereNull('d.restored_at')
            ->select('d.id')->first();
        if (!$row) {
            $this->markTestSkipped('No moved file has been indexed again.');
        }

        $service = app(IndexingDuplicateService::class);
        $preview = $service->previewReverse((int) $row->id);

        $this->assertSame('blocked', $preview['status']);
        $this->assertNotEmpty($preview['held_by']);
        $this->assertSame('blocked', $service->reverse((int) $row->id, 'A test reason')['status']);
    }

    public function test_a_missing_record_is_reported(): void
    {
        $this->assertSame('not_found', app(IndexingDuplicateService::class)->previewReverse(0)['status']);
    }
}
