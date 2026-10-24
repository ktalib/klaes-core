<?php

namespace Tests\Feature;

use App\Models\FileIndexing;
use App\Services\Vfs\VfsFolioPresenter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Change Request 22102026 item 32: a file commissioned in KLAES shows its
 * commissioning sheet (and, for a conversion, its LGA confirmation sheet) in the
 * Virtual File/Folder System, not only the scanned passport.
 *
 * READ-ONLY: only the listing is checked here. Drawing a sheet saves a row inside
 * a rolled-back transaction, which the read-only sqlsrv test connection refuses.
 */
class VfsCommissioningDocumentsTest extends TestCase
{
    private function indexedFile(string $like): ?FileIndexing
    {
        $number = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('full_file_number', 'like', $like)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('file_indexings')
                ->whereColumn('file_indexings.file_number', 'mls_file_no.full_file_number'))
            ->orderByDesc('id')
            ->value('full_file_number');

        return $number ? FileIndexing::on('sqlsrv')->where('file_number', $number)->first() : null;
    }

    public function test_commissioned_file_gets_a_commissioning_folder(): void
    {
        $file = $this->indexedFile('COM-%');
        if (!$file) {
            $this->markTestSkipped('No commissioned COM- file is indexed here.');
        }

        $presenter = app(VfsFolioPresenter::class);
        $folder = collect($presenter->folders($file))->firstWhere('key', VfsFolioPresenter::SYSTEM_FOLDER);

        $this->assertNotNull($folder, 'the Commissioning folder is listed');
        $this->assertContains('commissioning-sheet', array_column($presenter->systemDocuments($file), 'key'));

        $tiles = $presenter->documents($file, VfsFolioPresenter::SYSTEM_FOLDER);
        $this->assertSame('Commissioning Sheet', $tiles->items()[0]['label']);
        $this->assertTrue($tiles->items()[0]['is_system']);
    }

    public function test_conversion_file_lists_the_lga_confirmation_sheet(): void
    {
        $number = DB::connection('sqlsrv')->table('conversion_applications')
            ->whereNotNull('serial_no')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('file_indexings')
                ->whereColumn('file_indexings.file_number', 'conversion_applications.full_file_number'))
            ->orderByDesc('id')
            ->value('full_file_number');
        if (!$number) {
            $this->markTestSkipped('No indexed conversion file with an issued LGA sheet.');
        }

        $file = FileIndexing::on('sqlsrv')->where('file_number', $number)->first();
        $keys = array_column(app(VfsFolioPresenter::class)->systemDocuments($file), 'key');

        $this->assertContains('lga-confirmation', $keys);
    }
}
