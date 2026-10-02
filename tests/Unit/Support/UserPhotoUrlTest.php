<?php

namespace Tests\Unit\Support;

use App\Support\UserPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The avatar URL must be built against the host the app is actually being served from,
 * never against APP_URL.
 *
 * This is not a style preference. `.env` is gitignored, so a code upload leaves production
 * running with whatever APP_URL the last developer had. When the URL was built through
 * Storage::disk('public')->url() — whose root is config('filesystems.disks.public.url'),
 * i.e. APP_URL . '/storage' — every avatar on production was emitted as
 * http://127.0.0.1:8000/storage/..., which the viewer's own browser resolves against their
 * own machine. The result is an empty circle on every screen, with the file present and
 * the database row correct, and nothing in the logs.
 */
class UserPhotoUrlTest extends TestCase
{
    /** Pretend the app is being served from a real deployment hostname. */
    private function servedFrom(string $url): void
    {
        $request = Request::create($url, 'GET');
        $this->app->instance('request', $request);
        URL::setRequest($request);
    }

    public function test_the_url_follows_the_request_host_not_app_url(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);
        config(['filesystems.disks.public.url' => 'http://127.0.0.1:8000/storage']);

        $this->servedFrom('https://klaes.kanostate.gov.ng/profile');

        $url = UserPhoto::url('upload/profile/profile_abc.jpg');

        $this->assertSame(
            'https://klaes.kanostate.gov.ng/storage/upload/profile/profile_abc.jpg',
            $url,
            'The avatar URL is pinned to APP_URL again — production will serve blank circles.'
        );
        $this->assertStringNotContainsString('127.0.0.1', (string) $url);
    }

    public function test_it_keeps_the_scheme_of_the_request(): void
    {
        $this->servedFrom('https://klaes.kanostate.gov.ng/profile');
        $this->assertStringStartsWith('https://', (string) UserPhoto::url('upload/profile/a.jpg'));

        $this->servedFrom('http://klaes.internal/profile');
        $this->assertStringStartsWith('http://', (string) UserPhoto::url('upload/profile/a.jpg'));
    }

    /**
     * The column holds four different shapes, written by four different generations of
     * the upload code. All of them have to resolve under /storage.
     *
     * @dataProvider storedShapes
     */
    public function test_it_resolves_every_stored_shape(string $stored, ?string $expectedPath): void
    {
        $this->servedFrom('https://klaes.test.gov.ng/x');

        $url = UserPhoto::url($stored);

        if ($expectedPath === null) {
            $this->assertNull($url);

            return;
        }

        $this->assertSame('https://klaes.test.gov.ng/storage/' . $expectedPath, $url);
    }

    public static function storedShapes(): array
    {
        return [
            'profile page path'   => ['profiles/MPWyb0KK.jpg', 'profiles/MPWyb0KK.jpg'],
            'user edit path'      => ['upload/profile/profile_abc_123.jpg', 'upload/profile/profile_abc_123.jpg'],
            // Bare filenames were only ever written into upload/profile.
            'bare filename'       => ['profile_abc_123.jpeg', 'upload/profile/profile_abc_123.jpeg'],
            // Legacy rows carry the disk prefix; /storage is already that disk's root.
            'legacy public/ pfx'  => ['public/upload/profile/a.jpg', 'upload/profile/a.jpg'],
            'placeholder'         => ['avatar.png', null],
            'placeholder cased'   => ['Avatar.PNG', null],
            'empty'               => ['', null],
            'whitespace'          => ['   ', null],
        ];
    }

    public function test_it_falls_back_to_the_passport_path_when_profile_is_the_placeholder(): void
    {
        $this->servedFrom('https://klaes.test.gov.ng/x');

        $this->assertSame(
            'https://klaes.test.gov.ng/storage/upload/profile/fallback.jpg',
            UserPhoto::url('avatar.png', 'upload/profile/fallback.jpg')
        );
    }

    public function test_a_user_with_no_photo_at_all_yields_null(): void
    {
        $this->servedFrom('https://klaes.test.gov.ng/x');

        $this->assertNull(UserPhoto::url(null, null));
        $this->assertNull(UserPhoto::url('avatar.png', 'avatar.png'));
    }

    /**
     * The single-record screens gate the <img> on existsOnDisk(), so it has to agree with
     * url() about which file a stored value names. If the two ever applied different shape
     * rules, the profile page would decide a photo is present and then point at a
     * different, non-existent file.
     */
    public function test_exists_on_disk_agrees_with_url_about_which_file_is_named(): void
    {
        Storage::fake('public');

        // A bare filename must be found under upload/profile, the way url() resolves it.
        Storage::disk('public')->put('upload/profile/bare.jpg', 'x');
        $this->assertTrue(UserPhoto::existsOnDisk('bare.jpg'));
        $this->assertTrue(UserPhoto::existsOnDisk('upload/profile/bare.jpg'));
        // And the legacy "public/" prefix names the same file.
        $this->assertTrue(UserPhoto::existsOnDisk('public/upload/profile/bare.jpg'));

        // The profile-page shape.
        Storage::disk('public')->put('profiles/abc.jpg', 'x');
        $this->assertTrue(UserPhoto::existsOnDisk('profiles/abc.jpg'));
    }

    public function test_exists_on_disk_is_false_when_there_is_no_file(): void
    {
        Storage::fake('public');

        // The reported production case: the row is intact, the file was never copied here.
        $this->assertFalse(UserPhoto::existsOnDisk('profiles/NeverCopied.jpg'));

        // And the values that name nothing at all.
        $this->assertFalse(UserPhoto::existsOnDisk('avatar.png'));
        $this->assertFalse(UserPhoto::existsOnDisk(''));
        $this->assertFalse(UserPhoto::existsOnDisk(null));
    }
}
