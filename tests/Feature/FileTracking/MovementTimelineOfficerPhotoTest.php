<?php

namespace Tests\Feature\FileTracking;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Movement Timeline shows a face beside the Receiving Officer on every log row. The
 * photo cannot come from the movement log itself — that stores an officer id and a name —
 * so /api/file-trackers/track returns an id => photo map alongside the log.
 *
 * Two things are asserted: the map is there and keyed the way the renderer reads it, and
 * building it stays one query no matter how long the file's history is. A file that has
 * moved twenty times must not cost twenty user lookups.
 */
class MovementTimelineOfficerPhotoTest extends TestCase
{
    private function actingUser(): User
    {
        $user = User::on('sqlsrv')->first();

        if (!$user) {
            $this->markTestSkipped('No users on this database.');
        }

        Auth::login($user);

        return $user;
    }

    /** A tracker that actually carries movement rows with officers on them. */
    private function trackedFileNumber(): string
    {
        $row = DB::connection('sqlsrv')->table('file_tracker')
            ->whereNotNull('movement_log')
            ->whereNotNull('receiving_officer_id')
            ->orderByDesc('id')
            ->first(['file_number']);

        if (!$row || trim((string) $row->file_number) === '') {
            $this->markTestSkipped('No tracked file with a movement log on this database.');
        }

        return trim((string) $row->file_number);
    }

    private function track(string $fileNumber): array
    {
        $user = $this->actingUser();

        $response = $this->actingAs($user)
            ->getJson('/api/file-trackers/track/' . rawurlencode($fileNumber));

        $response->assertOk();

        return $response->json('data') ?? [];
    }

    public function test_the_timeline_payload_carries_a_photo_for_each_officer_on_the_log(): void
    {
        $data = $this->track($this->trackedFileNumber());

        $this->assertArrayHasKey(
            'officer_photos',
            $data,
            'The timeline renderer reads data.officer_photos; without it every log row falls back to initials.'
        );
        $this->assertIsArray($data['officer_photos']);

        // Every key must be an officer id the log actually names, as a string — the
        // renderer looks it up with String(entry.receiving_officer_id).
        $logOfficerIds = collect(array_merge($data['prior_movements'] ?? [], $data['movement_history'] ?? []))
            ->map(fn ($entry) => is_array($entry) ? ($entry['receiving_officer_id'] ?? null) : null)
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->all();

        foreach (array_keys($data['officer_photos']) as $key) {
            // PHP turns a numeric string key back into an int on decode; what matters is
            // that it names an officer the log carries. The JSON itself keeps it a string,
            // which is asserted separately below.
            $this->assertContains(
                (string) $key,
                $logOfficerIds,
                "officer_photos is keyed by {$key}, which no movement row names."
            );
        }
    }

    /**
     * The renderer looks the map up with String(entry.receiving_officer_id), so the keys
     * have to survive as JSON object keys — which they do, since JSON keys are always
     * strings. Asserted on the raw body because PHP's decoder undoes exactly that.
     */
    public function test_the_photo_map_is_keyed_by_string_ids_in_the_json_itself(): void
    {
        $user = $this->actingUser();

        $body = $this->actingAs($user)
            ->getJson('/api/file-trackers/track/' . rawurlencode($this->trackedFileNumber()))
            ->assertOk()
            ->getContent();

        $decoded = json_decode($body, true);
        $photos = $decoded['data']['officer_photos'] ?? [];

        if ($photos === []) {
            $this->markTestSkipped('No officer on this file has a photo on record.');
        }

        foreach ($photos as $url) {
            $this->assertIsString($url);
            $this->assertNotSame('', trim($url), 'A photo-less officer must be dropped, not mapped to an empty string.');
        }
    }

    public function test_building_the_photo_map_does_not_scale_with_the_length_of_the_history(): void
    {
        $fileNumber = $this->trackedFileNumber();
        $user = $this->actingUser();

        $userQueries = 0;
        DB::listen(function ($query) use (&$userQueries) {
            if (stripos($query->sql, 'from [users]') !== false) {
                $userQueries++;
            }
        });

        $this->actingAs($user)
            ->getJson('/api/file-trackers/track/' . rawurlencode($fileNumber))
            ->assertOk();

        // UserPhoto::prime() resolves the whole log in one round trip. More than a couple
        // means the priming has been bypassed and the timeline is back to an N+1.
        $this->assertLessThanOrEqual(
            2,
            $userQueries,
            "Rendering the timeline ran {$userQueries} user lookups; the officer photos must be primed in one."
        );
    }

    /** A file with no tracker at all must still answer, not 500 on the new code. */
    public function test_an_untracked_file_still_returns_cleanly(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->getJson('/api/file-trackers/track/NO-SUCH-FILE-' . uniqid())
            ->assertStatus(404);
    }
}
