<?php

namespace Tests\Unit;

use App\Http\Controllers\BlindScanningController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use Tests\TestCase;

/**
 * LF scans added to a file that already has pages. Runs the real upload code
 * against a temporary folder; nothing here touches the database or F:\storage.
 */
class BlindScanLfUploadTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'blind-lf-' . uniqid();
        File::ensureDirectoryExists($this->folder . DIRECTORY_SEPARATOR . 'A4');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    private function putFile(string $relative, string $content): void
    {
        $path = $this->folder . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);
    }

    private function read(string $relative): string
    {
        return file_get_contents($this->folder . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    }

    private function has(string $relative): bool
    {
        return is_file($this->folder . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    }

    /** @param array<string,string> $files path sent by the browser => content */
    private function upload(array $files, bool $lfOnly): array
    {
        $uploads = [];
        foreach ($files as $path => $content) {
            $tmp = tempnam(sys_get_temp_dir(), 'lf');
            file_put_contents($tmp, $content);
            $uploads[] = new UploadedFile($tmp, basename($path), 'image/jpeg', null, true);
        }

        $request = Request::create('/blind-scanning', 'POST', ['paths' => array_keys($files)], [], ['files' => $uploads]);
        $method = new ReflectionMethod(BlindScanningController::class, 'handleDirectUpload');
        $method->setAccessible(true);

        return $method->invoke(app(BlindScanningController::class), $request, $this->folder, $lfOnly);
    }

    public function test_lf_pages_go_to_a3_whatever_the_local_folder_is_called(): void
    {
        $result = $this->upload([
            'LF Scan/IMG_0001.jpg' => 'lf-one',
            'IMG_0002.jpg' => 'lf-two',
            'Deep/Nested/IMG_0003.jpg' => 'lf-three',
        ], true);

        $this->assertSame(['A3/IMG_0001.jpg', 'A3/IMG_0002.jpg', 'A3/IMG_0003.jpg'], $result['saved']);
        $this->assertSame('lf-two', $this->read('A3/IMG_0002.jpg'));
    }

    public function test_lf_upload_leaves_existing_a4_pages_alone(): void
    {
        $this->putFile('A4/IMG_0001.jpg', 'the original a4 page');

        $this->upload(['LF Scan/IMG_0001.jpg' => 'a large format page'], true);

        $this->assertSame('the original a4 page', $this->read('A4/IMG_0001.jpg'));
        $this->assertSame('a large format page', $this->read('A3/IMG_0001.jpg'));
    }

    public function test_a_page_from_an_a4_folder_is_refused_on_an_lf_upload(): void
    {
        $result = $this->upload(['RES-1/A4/IMG_0001.jpg' => 'a normal page'], true);

        $this->assertSame([], $result['saved']);
        $this->assertSame(['RES-1/A4/IMG_0001.jpg'], $result['rejected']);
        $this->assertFalse($this->has('A3/IMG_0001.jpg'));
    }

    public function test_a_different_lf_page_with_the_same_name_is_kept_beside_the_first(): void
    {
        $this->putFile('A3/IMG_0001.jpg', 'first plan');

        $this->upload(['LF Scan/IMG_0001.jpg' => 'other plan'], true);

        $this->assertSame('first plan', $this->read('A3/IMG_0001.jpg'));
        $this->assertSame('other plan', $this->read('A3/IMG_0001 (2).jpg'));
    }

    public function test_same_size_but_different_content_is_not_mistaken_for_a_resend(): void
    {
        $this->putFile('A3/IMG_0001.jpg', 'aaaa');

        $result = $this->upload(['LF Scan/IMG_0001.jpg' => 'bbbb'], true);

        $this->assertSame([], $result['skipped']);
        $this->assertSame('aaaa', $this->read('A3/IMG_0001.jpg'));
        $this->assertSame('bbbb', $this->read('A3/IMG_0001 (2).jpg'));
    }

    public function test_a_resend_is_skipped_even_when_it_was_saved_under_a_2_name(): void
    {
        $this->putFile('A3/IMG_0001.jpg', 'first plan');
        $this->putFile('A3/IMG_0001 (2).jpg', 'other plan');

        $result = $this->upload(['LF Scan/IMG_0001.jpg' => 'other plan'], true);

        $this->assertSame(['A3/IMG_0001.jpg'], $result['skipped']);
        $this->assertFalse($this->has('A3/IMG_0001 (3).jpg'));
    }

    public function test_a_full_file_upload_keeps_its_own_folders(): void
    {
        $result = $this->upload(['A4/IMG_0001.jpg' => 'normal', 'A3/IMG_0001.jpg' => 'large'], false);

        $this->assertSame(['A4/IMG_0001.jpg', 'A3/IMG_0001.jpg'], $result['saved']);
        $this->assertSame('normal', $this->read('A4/IMG_0001.jpg'));
    }
}
