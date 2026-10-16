<?php

namespace Tests\Feature\FileNumber;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /api/file-numbers/consent-search — the File Number dropdown for the
 * consent-gated instruments on /instruments/create (Assignment, Gift, Mortgage,
 * Tripartite Mortgage). Read-only: it only queries.
 *
 * Temporary "(T)" files are captured on /deeds-applications but never indexed,
 * so the indexing-backed dropdown could not find them and the deed could not be
 * registered. This pins that every consent file is reachable, narrowed by type.
 */
class ConsentFileSearchTest extends TestCase
{
    private function search(array $params): array
    {
        return $this->getJson('/api/file-numbers/consent-search?' . http_build_query($params))
            ->assertOk()
            ->json();
    }

    /** @test */
    public function an_unindexed_temporary_consent_file_is_listed_for_its_consent_type(): void
    {
        $consent = DB::connection('sqlsrv')->table('consent_applications')
            ->where('application_type', 'application_for_censent')
            ->where('file_number', 'like', '%(T)%')
            ->first(['file_number', 'consent_type']);

        if (!$consent) {
            $this->markTestSkipped('No temporary consent file in the data.');
        }

        $json = $this->search(['q' => $consent->file_number, 'consent_type' => $consent->consent_type]);

        $this->assertContains($consent->file_number, array_column($json['results'], 'id'));
        $this->assertArrayHasKey('more', $json['pagination']);
    }

    /**
     * The Create Consent Application dropdown (indexing-search?include_temporary=1)
     * must offer temporary files that have no file_indexings row of their own.
     *
     * @test
     */
    public function indexing_search_offers_temporary_files_when_asked(): void
    {
        $temp = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('file_option', 'temporary')
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where('full_file_number', 'like', '%(T)%')
            ->value('full_file_number');

        if (!$temp) {
            $this->markTestSkipped('No live temporary file in mls_file_no.');
        }

        $json = $this->getJson('/api/file-numbers/indexing-search?' . http_build_query([
            'q' => $temp, 'include_temporary' => 1,
        ]))->assertOk()->json();

        $row = collect($json['results'])->firstWhere('id', $temp);
        $this->assertNotNull($row, "{$temp} missing from the dropdown");
        $this->assertTrue($row['is_temporary']);
        $this->assertArrayHasKey('record', $row);
    }

    /** @test */
    public function results_are_narrowed_to_the_requested_consent_type(): void
    {
        $mortgageFiles = DB::connection('sqlsrv')->table('consent_applications')
            ->where('application_type', 'application_for_censent')
            ->where('consent_type', 'Mortgage')
            ->pluck('file_number')
            ->map(fn ($f) => strtoupper(trim((string) $f)))
            ->all();

        $json = $this->search(['consent_type' => 'Mortgage', 'per_page' => 100]);

        foreach ($json['results'] as $row) {
            $this->assertContains(strtoupper($row['id']), $mortgageFiles);
            $this->assertNotSame('ST Assignment', $row['registry'], 'An ST memo never authorises a mortgage.');
        }
    }
}
