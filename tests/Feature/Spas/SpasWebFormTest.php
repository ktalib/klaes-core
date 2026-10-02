<?php

namespace Tests\Feature\Spas;

use App\Models\SpaApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The session-authenticated SPAS web endpoints — desktop and mobile Blade forms.
 *
 * WHY THIS EXISTS SEPARATELY FROM SpasSyncApiTest
 * Phase 0 rewired storeLandRecord()/storeFieldData() onto SpaMobileService so
 * the two Blade forms and the offline app share one rule set. That refactor is
 * only safe as long as the web forms keep behaving exactly as they did, and
 * these two endpoints are what both Blade copies post to. Testing the API alone
 * would leave the controller wiring — and the mapPoint payload the mobile map
 * consumes — completely uncovered.
 *
 * Isolation: DatabaseTransactions on sqlsrv, never RefreshDatabase. This is the
 * shared development database.
 */
class SpasWebFormTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['sqlsrv'];

    private function actingAsStaff(): void
    {
        $this->actingAs(User::query()->firstOrFail());
    }

    // -----------------------------------------------------------------------
    // Add Land Record
    // -----------------------------------------------------------------------

    public function test_statutory_land_record_saves(): void
    {
        $this->actingAsStaff();
        $fileNumber = 'ZZ-WEB-'.Str::random(8);

        $this->postJson(route('special-assignment.land-records.store'), [
            'land_title_type' => 'statutory',
            'file_number'     => $fileNumber,
            'owner_name'      => 'ZZ TEST Web Owner',
            'phone'           => '08030000000',
            'location'        => 'Some Street, Nassarawa',
            'land_use_type'   => 'RESIDENTIAL',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'COMMERCIAL',
        ])->assertOk()->assertJson(['success' => true, 'file_number' => $fileNumber]);

        $this->assertDatabaseHas('spa_applications', [
            'file_number' => $fileNumber,
            'status'      => 'open',
        ], 'sqlsrv');
    }

    /**
     * The exact August 2026 production failure: this rule was added server-side
     * while the mobile Blade form had no LGA field, so every customary save from
     * mobile 422'd behind a generic "Save failed".
     */
    public function test_customary_record_without_an_lga_is_rejected_with_a_readable_message(): void
    {
        $this->actingAsStaff();

        $this->postJson(route('special-assignment.land-records.store'), [
            'land_title_type' => 'customary',
            'owner_name'      => 'ZZ TEST No LGA',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'COMMERCIAL',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lga'])
            ->assertJsonFragment(['Please select the LGA for this customary title.']);
    }

    public function test_customary_record_with_an_lga_saves_and_gets_a_server_generated_number(): void
    {
        $this->actingAsStaff();

        $response = $this->postJson(route('special-assignment.land-records.store'), [
            'land_title_type' => 'customary',
            'owner_name'      => 'ZZ TEST Customary Web',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'AGRICULTURAL',
            'lga'             => 'Nassarawa',
            'district'        => 'Kofar Nassarawa',
            'land_use_type'   => 'RESIDENTIAL',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertMatchesRegularExpression('/^SPAS-\d{4}-\d{4}$/', $response->json('file_number'));
    }

    public function test_a_duplicate_file_number_is_refused_with_the_existing_record_date(): void
    {
        $this->actingAsStaff();
        $fileNumber = 'ZZ-WEB-DUP-'.Str::random(8);

        $payload = [
            'land_title_type' => 'statutory',
            'file_number'     => $fileNumber,
            'owner_name'      => 'ZZ TEST First',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'RESIDENTIAL',
        ];

        $this->postJson(route('special-assignment.land-records.store'), $payload)->assertOk();

        // One application per file number. The message names the existing
        // record's date so the user can find it rather than just being blocked.
        $this->postJson(route('special-assignment.land-records.store'), $payload)
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(1, SpaApplication::where('file_number', $fileNumber)->count());
    }

    // -----------------------------------------------------------------------
    // Field Data page — "awaiting location"
    // -----------------------------------------------------------------------

    public function test_the_field_data_page_renders(): void
    {
        // Compiles the Blade for real, so a template error in the
        // awaiting-location block fails here rather than in the office.
        $this->actingAsStaff();

        $this->get(route('special-assignment.field-data'))
            ->assertOk()
            ->assertSee('inspection point(s) plotted', false);
    }

    /**
     * Q5 says a record may sync with no pin. The map filters on
     * whereNotNull('coordinates'), so without this panel those records are
     * absent from the office view entirely — invisible rather than pending.
     */
    public function test_an_inspection_with_no_pin_is_listed_as_awaiting_location(): void
    {
        $this->actingAsStaff();
        $fileNumber = 'ZZ-NOPIN-'.Str::random(8);

        $created = $this->postJson(route('special-assignment.land-records.store'), [
            'land_title_type' => 'statutory',
            'file_number'     => $fileNumber,
            'owner_name'      => 'ZZ TEST Unplaced Owner',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'RESIDENTIAL',
        ])->assertOk();

        // No coordinates at all — the offline-with-no-GPS case.
        $this->postJson(route('special-assignment.field-data.store'), [
            'spa_application_id' => $created->json('id'),
            'file_number'        => $fileNumber,
            'inspection_date'    => now()->toDateString(),
            'findings'           => 'ZZ TEST findings, no GPS fix available.',
        ])->assertOk();

        $this->get(route('special-assignment.field-data'))
            ->assertOk()
            ->assertSee('awaiting location')
            ->assertSee($fileNumber);
    }

    // -----------------------------------------------------------------------
    // Change of Purpose Sheet
    // -----------------------------------------------------------------------

    /**
     * A change of purpose that does not change the purpose is not one.
     *
     * Issuing it would put a sheet on the file recording a conversion that
     * never happened, and flip the application to `certificate_issued` on the
     * strength of it — so the record would read as fully processed off a
     * document that says nothing.
     */
    public function test_a_change_of_purpose_to_the_same_use_is_refused(): void
    {
        $this->actingAsStaff();

        $created = $this->postJson(route('special-assignment.land-records.store'), [
            'land_title_type' => 'statutory',
            'file_number'     => 'ZZ-COP-'.Str::random(8),
            'owner_name'      => 'ZZ TEST Holder',
            'proposed_use'    => 'AGRICULTURAL',
            'existing_use'    => 'COMMERCIAL',
        ])->assertOk();

        $this->postJson(route('special-assignment.certificate.issue'), [
            'spa_application_id' => $created->json('id'),
            'holder_name'        => 'ZZ TEST Holder',
            'new_file_number'    => 'ZZ-NEW-0001',
            'from_use'           => 'AGRICULTURAL',
            'to_use'             => 'AGRICULTURAL',
            'issue_date'         => now()->toDateString(),
        ])->assertStatus(422);

        // The application must not have been advanced by a refused issue.
        $this->assertNotSame(
            'certificate_issued',
            SpaApplication::find($created->json('id'))->status
        );
    }

    /**
     * The land-use values are free text in places, so a raw string comparison
     * would let "COMMERCIAL" past "Commercial ".
     */
    public function test_the_same_use_check_ignores_case_and_padding(): void
    {
        $this->actingAsStaff();

        $created = $this->postJson(route('special-assignment.land-records.store'), [
            'land_title_type' => 'statutory',
            'file_number'     => 'ZZ-COP-'.Str::random(8),
            'owner_name'      => 'ZZ TEST Holder',
            'proposed_use'    => 'AGRICULTURAL',
            'existing_use'    => 'COMMERCIAL',
        ])->assertOk();

        $this->postJson(route('special-assignment.certificate.issue'), [
            'spa_application_id' => $created->json('id'),
            'holder_name'        => 'ZZ TEST Holder',
            'new_file_number'    => 'ZZ-NEW-0002',
            'from_use'           => 'COMMERCIAL',
            'to_use'             => '  commercial ',
            'issue_date'         => now()->toDateString(),
        ])->assertStatus(422);
    }

    // -----------------------------------------------------------------------
    // Edit Land Record (office)
    // -----------------------------------------------------------------------

    public function test_the_office_can_edit_a_record_and_move_its_status(): void
    {
        // Unlike the API, the web path may set `status` — approving a record is
        // office workflow. This is the one behavioural difference between the
        // two callers of the shared update rules.
        $this->actingAsStaff();

        $created = $this->postJson(route('special-assignment.land-records.store'), [
            'land_title_type' => 'statutory',
            'file_number'     => 'ZZ-WEB-EDIT-'.Str::random(8),
            'owner_name'      => 'ZZ TEST Before Edit',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'COMMERCIAL',
        ])->assertOk();

        $this->postJson(route('special-assignment.land-records.update', $created->json('id')), [
            'owner_name'    => 'ZZ TEST After Edit',
            'phone'         => '08011112222',
            'location'      => 'Updated Street',
            'land_use_type' => 'COMMERCIAL',
            'proposed_use'  => 'COMMERCIAL',
            'existing_use'  => 'COMMERCIAL',
            'status'        => 'approved',
        ])->assertOk()->assertJson(['success' => true]);

        $row = SpaApplication::find($created->json('id'));

        $this->assertSame('ZZ TEST After Edit', $row->owner_name);
        $this->assertSame('08011112222', $row->phone);
        $this->assertSame('Updated Street', $row->location);
        $this->assertSame('approved', $row->status);
    }

    public function test_the_web_edit_requires_a_valid_status(): void
    {
        $this->actingAsStaff();

        $created = $this->postJson(route('special-assignment.land-records.store'), [
            'land_title_type' => 'statutory',
            'file_number'     => 'ZZ-WEB-BADSTATUS-'.Str::random(8),
            'owner_name'      => 'ZZ TEST Owner',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'RESIDENTIAL',
        ])->assertOk();

        $this->postJson(route('special-assignment.land-records.update', $created->json('id')), [
            'owner_name'   => 'ZZ TEST Owner',
            'proposed_use' => 'RESIDENTIAL',
            'existing_use' => 'RESIDENTIAL',
            'status'       => 'not-a-real-status',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    // -----------------------------------------------------------------------
    // Log Field Inspection
    // -----------------------------------------------------------------------

    public function test_logging_an_inspection_advances_the_application_and_returns_a_map_point(): void
    {
        $this->actingAsStaff();
        $fileNumber = 'ZZ-WEB-INSP-'.Str::random(8);

        $created = $this->postJson(route('special-assignment.land-records.store'), [
            'land_title_type' => 'statutory',
            'file_number'     => $fileNumber,
            'owner_name'      => 'ZZ TEST Inspection Owner',
            'location'        => 'Some Street',
            'land_use_type'   => 'RESIDENTIAL',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'COMMERCIAL',
        ])->assertOk();

        $response = $this->postJson(route('special-assignment.field-data.store'), [
            'spa_application_id' => $created->json('id'),
            'file_number'        => $fileNumber,
            'inspection_date'    => now()->toDateString(),
            'findings'           => 'ZZ TEST findings from the web form.',
            'coordinates'        => '{"lat":11.9964,"lng":8.5919}',
        ])->assertOk()->assertJson(['success' => true]);

        // The mobile Field Map tab plots this payload directly; dropping it
        // would break the live map update with no server-side error.
        $this->assertNotNull($response->json('mapPoint'), 'mapPoint drives the live map update.');
        $this->assertTrue(
            $response->json('mapPoint.contravening'),
            'Approved RESIDENTIAL vs prevailing COMMERCIAL is a contravention.'
        );

        $this->assertSame('in_progress', SpaApplication::find($created->json('id'))->status);
    }

    public function test_web_inspection_still_requires_a_saved_parent_application(): void
    {
        // The API relaxed this so an offline push can link by client_uuid, but
        // the web forms always have a saved parent and keep the stricter rule.
        $this->actingAsStaff();

        $this->postJson(route('special-assignment.field-data.store'), [
            'file_number'     => 'ZZ-WEB-NOPARENT-'.Str::random(8),
            'inspection_date' => now()->toDateString(),
            'findings'        => 'ZZ TEST orphan attempt.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['spa_application_id']);
    }

    public function test_web_inspection_rejects_unparseable_coordinates(): void
    {
        $this->actingAsStaff();
        $fileNumber = 'ZZ-WEB-COORD-'.Str::random(8);

        $created = $this->postJson(route('special-assignment.land-records.store'), [
            'land_title_type' => 'statutory',
            'file_number'     => $fileNumber,
            'owner_name'      => 'ZZ TEST Coord Owner',
            'proposed_use'    => 'RESIDENTIAL',
            'existing_use'    => 'RESIDENTIAL',
        ])->assertOk();

        $this->postJson(route('special-assignment.field-data.store'), [
            'spa_application_id' => $created->json('id'),
            'file_number'        => $fileNumber,
            'inspection_date'    => now()->toDateString(),
            'findings'           => 'ZZ TEST findings.',
            'coordinates'        => 'behind the big tree',
        ])->assertStatus(422)->assertJson(['success' => false]);
    }
}
