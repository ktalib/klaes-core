<?php

namespace Tests\Feature\Spas;

use App\Models\SpaApplication;
use App\Models\SpaFieldData;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * /api/spas/* — the offline sync surface (Phase 0).
 *
 * NOTE ON ISOLATION: DatabaseTransactions, never RefreshDatabase. These tables
 * live on the shared `sqlsrv` development database alongside real data the team
 * uses through the UI; migrating or truncating it would destroy their work.
 * Every test here rolls back.
 *
 * @see docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md §15
 */
class SpasSyncApiTest extends TestCase
{
    use DatabaseTransactions;

    /** Roll back on the SQL Server connection, not the default MySQL one. */
    protected $connectionsToTransact = ['sqlsrv'];

    private function surveyor(): User
    {
        return User::query()->firstOrFail();
    }

    private function actingAsSurveyor(): User
    {
        $user = $this->surveyor();
        Sanctum::actingAs($user, ['spas-mobile']);

        return $user;
    }

    /** A valid customary land-record push payload. */
    private function recordPayload(array $overrides = []): array
    {
        return array_merge([
            'client_uuid'     => (string) Str::uuid(),
            'land_title_type' => 'customary',
            'owner_name'      => 'ZZ TEST Surveyor Record',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'COMMERCIAL',
            'lga'             => 'Kano Municipal',
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // Auth
    // -----------------------------------------------------------------------

    public function test_sync_endpoints_reject_an_unauthenticated_request(): void
    {
        // Land records carry owner PII; nothing here may be readable without a token.
        $this->getJson('/api/spas/records')->assertUnauthorized();
        $this->getJson('/api/spas/field-data')->assertUnauthorized();
        $this->getJson('/api/spas/lookup/districts')->assertUnauthorized();
        $this->postJson('/api/spas/records', $this->recordPayload())->assertUnauthorized();
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $this->postJson('/api/spas/auth/login', [
            'identifier' => 'definitely-not-a-real-user-'.time(),
            'password'   => 'wrong-password',
        ])->assertStatus(401)->assertJson(['status' => 'error']);
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/spas/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier', 'password']);
    }

    /**
     * The token ability must actually be enforced.
     *
     * Sanctum records abilities on a token but checks nothing unless the
     * `ability` middleware is applied — the alias is not registered by default.
     * Without this, a token minted for the React Native app ('mobile-api')
     * would open every SPAS endpoint, and a surveyor's device token would open
     * the React Native app's.
     */
    public function test_a_token_without_the_spas_ability_is_refused(): void
    {
        Sanctum::actingAs($this->surveyor(), ['mobile-api']);

        $this->getJson('/api/spas/records')->assertForbidden();
        $this->getJson('/api/spas/lookup/lgas')->assertForbidden();
        $this->postJson('/api/spas/records', $this->recordPayload())->assertForbidden();
    }

    public function test_a_wildcard_token_still_works(): void
    {
        // '*' is Sanctum's "all abilities" and must keep working, or an admin
        // token issued elsewhere would be locked out of its own API.
        Sanctum::actingAs($this->surveyor(), ['*']);

        $this->getJson('/api/spas/lookup/lgas')->assertOk();
    }

    /**
     * Login is the one route reachable without a token, so the API-wide 60/min
     * is a comfortable password-guessing budget. It is throttled to 5/min.
     */
    public function test_login_is_rate_limited(): void
    {
        $attempt = fn () => $this->postJson('/api/spas/auth/login', [
            'identifier' => 'ZZ-does-not-exist',
            'password'   => 'wrong',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $attempt()->assertStatus(401);
        }

        $attempt()->assertStatus(429);
    }

    // -----------------------------------------------------------------------
    // Push — records
    // -----------------------------------------------------------------------

    public function test_pushing_a_record_creates_it(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->recordPayload();

        $response = $this->postJson('/api/spas/records', $payload)
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('spa_applications', [
            'client_uuid' => $payload['client_uuid'],
            'owner_name'  => 'ZZ TEST Surveyor Record',
            'status'      => 'open',
        ], 'sqlsrv');

        // The server owns the customary sequence — a device-invented number is
        // only ever a placeholder.
        $this->assertMatchesRegularExpression('/^SPAS-\d{4}-\d{4}$/', $response->json('file_number'));
    }

    public function test_a_record_push_requires_a_client_uuid(): void
    {
        // Without it there is no idempotency key and a retry duplicates the row.
        $this->actingAsSurveyor();

        $payload = $this->recordPayload();
        unset($payload['client_uuid']);

        $this->postJson('/api/spas/records', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_uuid']);
    }

    /**
     * The core offline guarantee. A push that succeeded server-side but whose
     * response never reached the handset WILL be retried — that is normal on a
     * field connection, not an error.
     */
    public function test_retrying_the_same_push_does_not_duplicate_the_record(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->recordPayload();

        $first = $this->postJson('/api/spas/records', $payload)->assertStatus(201);

        $second = $this->postJson('/api/spas/records', $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'duplicate' => true]);

        $this->assertSame($first->json('id'), $second->json('id'), 'The retry must return the original row.');
        $this->assertSame(1, SpaApplication::where('client_uuid', $payload['client_uuid'])->count());
    }

    public function test_customary_record_without_an_lga_is_rejected(): void
    {
        // Queuing this offline would fail on every push attempt, long after the
        // surveyor has left the site and can no longer supply the answer.
        $this->actingAsSurveyor();

        $payload = $this->recordPayload();
        unset($payload['lga']);

        $this->postJson('/api/spas/records', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lga']);
    }

    public function test_a_second_application_for_the_same_file_number_is_a_conflict_not_a_validation_error(): void
    {
        $this->actingAsSurveyor();
        $fileNumber = 'ZZ-TEST-CONFLICT-'.Str::random(8);

        $this->postJson('/api/spas/records', $this->recordPayload([
            'land_title_type' => 'statutory',
            'file_number'     => $fileNumber,
        ]))->assertStatus(201);

        // 409, not 422: retrying will never succeed, so the app must route this
        // to a Conflicts list rather than leaving it in the outbox forever.
        $this->postJson('/api/spas/records', $this->recordPayload([
            'land_title_type' => 'statutory',
            'file_number'     => $fileNumber,
        ]))
            ->assertStatus(409)
            ->assertJson(['success' => false, 'conflict' => 'file_number']);
    }

    // -----------------------------------------------------------------------
    // Push — field data, and the flat-FIFO outbox
    // -----------------------------------------------------------------------

    /**
     * The design the nullable FK exists for: an inspection can be pushed before
     * its parent record, so the outbox drains as a flat FIFO with no dependency
     * tracking.
     */
    public function test_an_inspection_can_be_pushed_before_its_parent_record(): void
    {
        $this->actingAsSurveyor();
        $parentUuid = (string) Str::uuid();

        $response = $this->postJson('/api/spas/field-data', [
            'client_uuid'                 => (string) Str::uuid(),
            'spa_application_client_uuid' => $parentUuid,
            'file_number'                 => 'ZZ-TEST-ORPHAN-'.Str::random(8),
            'inspection_date'             => now()->toDateString(),
            'findings'                    => 'ZZ TEST findings recorded on site.',
            'coordinates'                 => '{"lat":11.9964,"lng":8.5919}',
        ])->assertStatus(201)->assertJson(['success' => true]);

        $this->assertNull($response->json('spa_application_id'), 'The parent has not synced yet.');
    }

    public function test_link_orphans_attaches_inspections_to_a_late_arriving_parent(): void
    {
        $this->actingAsSurveyor();
        $parentUuid = (string) Str::uuid();
        $fileNumber = 'ZZ-TEST-LINK-'.Str::random(8);

        // Child first.
        $child = $this->postJson('/api/spas/field-data', [
            'client_uuid'                 => (string) Str::uuid(),
            'spa_application_client_uuid' => $parentUuid,
            'file_number'                 => $fileNumber,
            'inspection_date'             => now()->toDateString(),
            'findings'                    => 'ZZ TEST findings.',
        ])->assertStatus(201);

        // Parent second.
        $parent = $this->postJson('/api/spas/records', $this->recordPayload([
            'client_uuid' => $parentUuid,
        ]))->assertStatus(201);

        $this->postJson('/api/spas/link-orphans')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(
            $parent->json('id'),
            SpaFieldData::find($child->json('id'))->spa_application_id,
            'The orphan must now point at the parent that arrived after it.'
        );

        // Logging an inspection advances the application out of `open`.
        $this->assertSame('in_progress', SpaApplication::find($parent->json('id'))->status);
    }

    public function test_a_second_inspection_for_the_same_file_is_a_conflict(): void
    {
        $this->actingAsSurveyor();
        $fileNumber = 'ZZ-TEST-DUPINSP-'.Str::random(8);

        $base = [
            'file_number'     => $fileNumber,
            'inspection_date' => now()->toDateString(),
            'findings'        => 'ZZ TEST findings.',
        ];

        $this->postJson('/api/spas/field-data', $base + ['client_uuid' => (string) Str::uuid()])
            ->assertStatus(201);

        $this->postJson('/api/spas/field-data', $base + ['client_uuid' => (string) Str::uuid()])
            ->assertStatus(409)
            ->assertJson(['conflict' => 'file_number']);
    }

    public function test_unparseable_coordinates_are_rejected(): void
    {
        $this->actingAsSurveyor();

        $this->postJson('/api/spas/field-data', [
            'client_uuid'     => (string) Str::uuid(),
            'file_number'     => 'ZZ-TEST-COORD-'.Str::random(8),
            'inspection_date' => now()->toDateString(),
            'findings'        => 'ZZ TEST findings.',
            'coordinates'     => 'somewhere near the market',
        ])->assertStatus(422);
    }

    /**
     * The offline app sends every field it knows about, including nulls.
     *
     * A customary record has no file number — the server generates one — so the
     * payload carries `file_number: null`. That used to fail the `string` rule
     * with "The file number must be a string", and offline that is
     * unrecoverable: the push 422s on every retry and the surveyor's record
     * never reaches the office. Observed on a real device 2026-08-16.
     */
    public function test_a_customary_push_with_an_explicit_null_file_number_is_accepted(): void
    {
        $this->actingAsSurveyor();

        $response = $this->postJson('/api/spas/records', [
            'client_uuid'     => (string) Str::uuid(),
            'land_title_type' => 'customary',
            'file_number'     => null,
            'owner_name'      => 'ZZ TEST Customary Null Fileno',
            'lga'             => 'Nasarawa',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'RESIDENTIAL',
        ])->assertStatus(201);

        // The server owns the sequence, so it must hand back a real number.
        $this->assertMatchesRegularExpression('/^SPAS-\d{4}-\d+$/', $response->json('file_number'));
    }

    public function test_a_statutory_push_with_a_null_file_number_is_still_refused(): void
    {
        // Making the rule nullable must not weaken the statutory requirement.
        $this->actingAsSurveyor();

        $this->postJson('/api/spas/records', [
            'client_uuid'     => (string) Str::uuid(),
            'land_title_type' => 'statutory',
            'file_number'     => null,
            'owner_name'      => 'ZZ TEST No Fileno',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'RESIDENTIAL',
        ])->assertStatus(422)->assertJsonValidationErrors(['file_number']);
    }

    // -----------------------------------------------------------------------
    // File index mirror — keyset paging
    // -----------------------------------------------------------------------

    public function test_the_file_index_pages_with_a_keyset_cursor(): void
    {
        $this->actingAsSurveyor();

        $first = $this->getJson('/api/spas/lookup/file-index?after_id=0&limit=5')
            ->assertOk()
            ->assertJsonStructure(['data', 'has_more', 'next_after_id', 'total']);

        $this->assertSame(5, $first->json('count'));
        $this->assertTrue($first->json('has_more'));
        $this->assertGreaterThan(0, $first->json('next_after_id'));

        // A total is counted only on the first page — every later page would
        // pay for a 133k-row count it does not need.
        $this->assertGreaterThan(0, $first->json('total'));

        $second = $this->getJson(
            '/api/spas/lookup/file-index?after_id='.$first->json('next_after_id').'&limit=5'
        )->assertOk();

        $this->assertNull($second->json('total'), 'Only the first page should carry a total.');

        // The pages must not overlap, or a mirror would store the same rows
        // repeatedly and never reach the end.
        $this->assertEmpty(
            array_intersect(
                array_column($first->json('data'), 'file_number'),
                array_column($second->json('data'), 'file_number')
            ),
            'Keyset pages overlapped — the cursor is not advancing correctly.'
        );
    }

    public function test_the_mirror_path_omits_the_expensive_holder_column(): void
    {
        // current_holder is NVARCHAR(MAX) and yields no name in practice, so a
        // mirror must not pay a LOB read per row for it. The search path still
        // may. This asserts the contract the client relies on: owner_name is
        // blank while mirroring, and file_title carries the name instead.
        $this->actingAsSurveyor();

        $row = $this->getJson('/api/spas/lookup/file-index?after_id=0&limit=1')
            ->assertOk()->json('data.0');

        $this->assertSame('', $row['owner_name']);
        $this->assertArrayHasKey('file_title', $row);
    }

    public function test_a_search_without_a_cursor_is_not_a_mirror(): void
    {
        $this->actingAsSurveyor();

        $response = $this->getJson('/api/spas/lookup/file-index?limit=3')->assertOk();

        $this->assertFalse($response->json('has_more'));
        $this->assertNull($response->json('next_after_id'));
    }

    // -----------------------------------------------------------------------
    // Update — offline edits pushed back
    // -----------------------------------------------------------------------

    private function pushRecord(array $overrides = []): array
    {
        $payload = $this->recordPayload($overrides);
        $this->postJson('/api/spas/records', $payload)->assertStatus(201);

        return $payload;
    }

    public function test_a_synced_record_can_be_edited(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->pushRecord();

        $this->putJson('/api/spas/records/'.$payload['client_uuid'], [
            'owner_name'   => 'ZZ TEST Corrected Owner',
            'proposed_use' => 'RESIDENTIAL',
            'existing_use' => 'RESIDENTIAL',
            'phone'        => '08099999999',
        ])->assertOk()->assertJson(['success' => true]);

        $row = SpaApplication::where('client_uuid', $payload['client_uuid'])->firstOrFail();

        $this->assertSame('ZZ TEST Corrected Owner', $row->owner_name);
        $this->assertSame('08099999999', $row->phone);
    }

    public function test_editing_an_unsynced_record_returns_404_so_the_edit_stays_queued(): void
    {
        // The create is still ahead of this edit in the outbox.
        $this->actingAsSurveyor();

        $this->putJson('/api/spas/records/'.Str::uuid(), [
            'owner_name'   => 'ZZ TEST Nobody',
            'proposed_use' => 'RESIDENTIAL',
            'existing_use' => 'RESIDENTIAL',
        ])->assertStatus(404);
    }

    public function test_an_edit_cannot_change_the_file_number_or_title_type(): void
    {
        // Identity is fixed at creation — one application per file number is a
        // unique index, so an edit that moved it would be a different record.
        $this->actingAsSurveyor();
        $payload = $this->pushRecord();

        $before = SpaApplication::where('client_uuid', $payload['client_uuid'])->firstOrFail();

        $this->putJson('/api/spas/records/'.$payload['client_uuid'], [
            'owner_name'      => 'ZZ TEST Owner',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'RESIDENTIAL',
            'file_number'     => 'ZZ-HIJACKED-'.Str::random(6),
            'land_title_type' => 'statutory',
        ])->assertOk();

        $after = SpaApplication::where('client_uuid', $payload['client_uuid'])->firstOrFail();

        $this->assertSame($before->file_number, $after->file_number);
        $this->assertSame($before->land_title_type, $after->land_title_type);
    }

    public function test_a_field_device_cannot_change_a_records_status(): void
    {
        // Approving a record is office workflow, not something a handset does.
        $this->actingAsSurveyor();
        $payload = $this->pushRecord();

        $this->putJson('/api/spas/records/'.$payload['client_uuid'], [
            'owner_name'   => 'ZZ TEST Owner',
            'proposed_use' => 'RESIDENTIAL',
            'existing_use' => 'RESIDENTIAL',
            'status'       => 'approved',
        ])->assertOk();

        $this->assertSame(
            'open',
            SpaApplication::where('client_uuid', $payload['client_uuid'])->value('status')
        );
    }

    /**
     * The retry case: a push that succeeded but whose response was lost. It must
     * NOT read as a conflict, or the surveyor gets a meaningless prompt about an
     * edit they themselves made.
     */
    public function test_replaying_an_identical_edit_is_a_no_op_not_a_conflict(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->pushRecord();

        $edit = [
            'owner_name'      => 'ZZ TEST Edited Once',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'RESIDENTIAL',
            'base_updated_at' => SpaApplication::where('client_uuid', $payload['client_uuid'])
                ->firstOrFail()->updated_at->toIso8601String(),
        ];

        $this->putJson('/api/spas/records/'.$payload['client_uuid'], $edit)->assertOk();

        // Same payload, same stale cursor — the device replaying its own write.
        $this->putJson('/api/spas/records/'.$payload['client_uuid'], $edit)
            ->assertOk()
            ->assertJson(['success' => true, 'duplicate' => true]);
    }

    /**
     * Plan §6.3: an office edit to a synced record must not be silently
     * overwritten by a device holding a stale copy.
     */
    public function test_an_edit_against_a_stale_base_is_a_conflict(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->pushRecord();

        $row = SpaApplication::where('client_uuid', $payload['client_uuid'])->firstOrFail();
        $staleCursor = $row->updated_at->copy()->subMinutes(5)->toIso8601String();

        // Someone in the office edits it after the device last synced.
        $row->update(['owner_name' => 'ZZ TEST Office Edit']);

        $this->putJson('/api/spas/records/'.$payload['client_uuid'], [
            'owner_name'      => 'ZZ TEST Device Edit',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'RESIDENTIAL',
            'base_updated_at' => $staleCursor,
        ])
            ->assertStatus(409)
            ->assertJson(['conflict' => 'stale_write'])
            ->assertJsonStructure(['server_updated_at', 'server_row']);

        // The office edit survived.
        $this->assertSame(
            'ZZ TEST Office Edit',
            SpaApplication::where('client_uuid', $payload['client_uuid'])->value('owner_name')
        );
    }

    public function test_omitting_the_base_cursor_is_last_write_wins(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->pushRecord();

        SpaApplication::where('client_uuid', $payload['client_uuid'])
            ->update(['owner_name' => 'ZZ TEST Office Edit']);

        $this->putJson('/api/spas/records/'.$payload['client_uuid'], [
            'owner_name'   => 'ZZ TEST Device Wins',
            'proposed_use' => 'RESIDENTIAL',
            'existing_use' => 'RESIDENTIAL',
        ])->assertOk();

        $this->assertSame(
            'ZZ TEST Device Wins',
            SpaApplication::where('client_uuid', $payload['client_uuid'])->value('owner_name')
        );
    }

    public function test_an_inspection_can_be_edited(): void
    {
        $this->actingAsSurveyor();
        $clientUuid = (string) Str::uuid();

        $this->postJson('/api/spas/field-data', [
            'client_uuid'     => $clientUuid,
            'file_number'     => 'ZZ-TEST-EDIT-'.Str::random(8),
            'inspection_date' => now()->toDateString(),
            'findings'        => 'ZZ TEST original findings.',
        ])->assertStatus(201);

        $this->putJson('/api/spas/field-data/'.$clientUuid, [
            'inspection_date' => now()->toDateString(),
            'findings'        => 'ZZ TEST revised findings after a second visit.',
            'coordinates'     => '{"lat":11.9964,"lng":8.5919}',
        ])->assertOk()->assertJson(['success' => true]);

        $row = SpaFieldData::where('client_uuid', $clientUuid)->firstOrFail();

        $this->assertStringContainsString('revised findings', $row->findings);
        $this->assertSame(['lat' => 11.9964, 'lng' => 8.5919], $row->coordinates);
    }

    /**
     * JSON has a single number type, so a whole-number coordinate comes back as
     * an int, not a float — `12.0` stores and re-reads as `12`. Harmless for
     * arithmetic and unreachable in practice (a plot is never on a whole
     * degree), but asserted so the client knows not to expect a strict float.
     */
    public function test_whole_number_coordinates_round_trip_as_ints(): void
    {
        $this->actingAsSurveyor();
        $clientUuid = (string) Str::uuid();

        $this->postJson('/api/spas/field-data', [
            'client_uuid'     => $clientUuid,
            'file_number'     => 'ZZ-TEST-WHOLE-'.Str::random(8),
            'inspection_date' => now()->toDateString(),
            'findings'        => 'ZZ TEST findings.',
            'coordinates'     => '{"lat":12.0,"lng":8.0}',
        ])->assertStatus(201);

        $coords = SpaFieldData::where('client_uuid', $clientUuid)->firstOrFail()->coordinates;

        $this->assertEquals(['lat' => 12, 'lng' => 8], $coords);
        $this->assertEqualsWithDelta(12.0, $coords['lat'], 0.0001);
    }

    // -----------------------------------------------------------------------
    // Photos
    // -----------------------------------------------------------------------

    public function test_photo_upload_for_an_unsynced_parent_returns_404_so_the_app_retries(): void
    {
        // A 404 tells the device to keep the upload queued until the parent
        // lands. Anything reading as terminal would make it discard the photos.
        Storage::fake('public');
        $this->actingAsSurveyor();

        $this->post('/api/spas/photos', [
            'entity_type' => 'record',
            'client_uuid' => (string) Str::uuid(),   // never pushed
            'photos'      => [UploadedFile::fake()->image('plot.jpg')],
        ])->assertStatus(404);
    }

    public function test_photos_attach_to_a_record_that_has_already_synced(): void
    {
        // Text syncs first and photos follow, because a record that reaches the
        // office without images beats one that never syncs because a 3 MB
        // upload keeps timing out on a 2G link.
        Storage::fake('public');
        $this->actingAsSurveyor();

        $payload = $this->recordPayload();
        $this->postJson('/api/spas/records', $payload)->assertStatus(201);

        $this->post('/api/spas/photos', [
            'entity_type' => 'record',
            'client_uuid' => $payload['client_uuid'],
            'photos'      => [
                UploadedFile::fake()->image('plot-front.jpg'),
                UploadedFile::fake()->image('plot-side.jpg'),
            ],
        ])->assertOk()->assertJson(['success' => true]);

        $stored = SpaApplication::where('client_uuid', $payload['client_uuid'])->firstOrFail()->photos;

        $this->assertCount(2, $stored);

        foreach ($stored as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_photo_upload_rejects_a_non_image(): void
    {
        Storage::fake('public');
        $this->actingAsSurveyor();

        $this->post('/api/spas/photos', [
            'entity_type' => 'record',
            'client_uuid' => (string) Str::uuid(),
            'photos'      => [UploadedFile::fake()->create('notes.pdf', 100)],
        ])->assertStatus(422)->assertJsonValidationErrors(['photos.0']);
    }

    // -----------------------------------------------------------------------
    // Pull
    // -----------------------------------------------------------------------

    public function test_records_pull_returns_the_sync_envelope(): void
    {
        $this->actingAsSurveyor();

        $this->getJson('/api/spas/records')
            ->assertOk()
            ->assertJsonStructure(['success', 'count', 'server_time', 'has_more', 'data']);
    }

    public function test_a_pushed_record_comes_back_on_the_next_pull(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->recordPayload();

        $this->postJson('/api/spas/records', $payload)->assertStatus(201);

        $uuids = collect($this->getJson('/api/spas/records')->json('data'))->pluck('client_uuid');

        $this->assertContains($payload['client_uuid'], $uuids->all());
    }

    /**
     * updated_at is DATETIME2(0) — whole seconds. A strict `>` cursor would
     * permanently skip any row written in the same second as the last row of a
     * page, so the filter is `>=` and the client dedupes.
     */
    public function test_since_cursor_is_inclusive_of_its_own_second(): void
    {
        $this->actingAsSurveyor();
        $payload = $this->recordPayload();

        $this->postJson('/api/spas/records', $payload)->assertStatus(201);

        $row = SpaApplication::where('client_uuid', $payload['client_uuid'])->firstOrFail();

        $uuids = collect(
            $this->getJson('/api/spas/records?since='.urlencode($row->updated_at->toIso8601String()))->json('data')
        )->pluck('client_uuid');

        $this->assertContains(
            $payload['client_uuid'],
            $uuids->all(),
            'A row whose updated_at equals the cursor must still be returned, or it is lost forever.'
        );
    }

    public function test_an_unparseable_since_cursor_falls_back_to_a_full_pull(): void
    {
        // Returning zero rows here would look like "nothing changed" and stall sync.
        $this->actingAsSurveyor();

        $this->getJson('/api/spas/records?since=not-a-timestamp')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    // -----------------------------------------------------------------------
    // Lookups
    // -----------------------------------------------------------------------

    public function test_lga_and_district_lookups_return_the_full_reference_sets(): void
    {
        $this->actingAsSurveyor();

        // Both are mirrored whole on device; the customary address picker is
        // unusable offline without them.
        $this->getJson('/api/spas/lookup/lgas')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(45, 'data');

        $this->getJson('/api/spas/lookup/districts')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_land_use_lookup_excludes_industrial_from_the_customary_subset(): void
    {
        $this->actingAsSurveyor();

        $customary = $this->getJson('/api/spas/lookup/land-uses')
            ->assertOk()
            ->json('customary');

        $upper = array_map(fn ($u) => strtoupper(trim($u)), $customary);

        $this->assertNotContains('INDUSTRIAL', $upper, 'Customary land is only held for RES/COM/AGR.');
    }

    public function test_file_index_lookup_is_bounded(): void
    {
        $this->actingAsSurveyor();

        // file_indexings is far too large to mirror; an unbounded response would
        // be a multi-megabyte download over 2G.
        $response = $this->getJson('/api/spas/lookup/file-index?limit=5')->assertOk();

        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }

    public function test_file_index_lookup_rejects_an_oversized_limit(): void
    {
        $this->actingAsSurveyor();

        $this->getJson('/api/spas/lookup/file-index?limit=99999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_next_customary_file_number_follows_the_expected_shape(): void
    {
        $this->actingAsSurveyor();

        $this->getJson('/api/spas/lookup/next-customary-fileno')
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
