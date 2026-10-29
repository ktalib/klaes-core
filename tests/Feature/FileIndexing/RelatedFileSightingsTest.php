<?php

namespace Tests\Feature\FileIndexing;

use App\Http\Controllers\FileIndexingController;
use App\Models\User;
use App\Services\FileIndexing\RelatedFileSightings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Related File (Physical File is Sighted)" on File Indexing (user, 2026-10-28):
 * every related file the form submits is answered Yes or No; No is NPF (No Physical
 * File) and is never set on its own. Saving is covered by a rollback harness, since
 * sqlsrv is read-only here; these check the rules and the refusal. Writes nothing.
 */
class RelatedFileSightingsTest extends TestCase
{
    private function request(array $input, string $method = 'PUT'): Request
    {
        $request = Request::create('/fileindexing/1', $method, $input);
        $request->headers->set('Accept', 'application/json');

        return $request;
    }

    public function test_answers_are_keyed_without_case_or_spaces_and_npf_has_no_cover_note(): void
    {
        $answers = app(RelatedFileSightings::class)->answersFrom($this->request([
            RelatedFileSightings::REQUEST_FIELD => json_encode([
                ['file_number' => 'knml 2375', 'sighted' => true, 'cover_missing' => RelatedFileSightings::COVER_MISSING_TEXT],
                ['file_number' => 'RES-1982-1651', 'sighted' => false, 'cover_missing' => 'should be dropped'],
                ['file_number' => 'RES-1982-1652', 'sighted' => null],
                ['file_number' => '  ', 'sighted' => true],
            ]),
        ]));

        $this->assertSame(['KNML2375', 'RES-1982-1651', 'RES-1982-1652'], array_keys($answers));
        $this->assertTrue($answers['KNML2375']['sighted']);
        $this->assertSame(RelatedFileSightings::COVER_MISSING_TEXT, $answers['KNML2375']['cover_missing']);
        $this->assertFalse($answers['RES-1982-1651']['sighted']);
        $this->assertNull($answers['RES-1982-1651']['cover_missing']);
        $this->assertNull($answers['RES-1982-1652']['sighted']);
    }

    public function test_every_related_file_must_be_answered_when_the_form_sends_the_field(): void
    {
        $svc = app(RelatedFileSightings::class);
        $field = RelatedFileSightings::REQUEST_FIELD;

        $refusal = $svc->refusal($this->request([
            'related_fileno' => ['ZZ-NPF-TEST-1', 'ZZ NPF TEST 2'],
            $field => json_encode([['file_number' => 'ZZ-NPF-TEST-1', 'sighted' => false]]),
        ]));
        $this->assertStringStartsWith(RelatedFileSightings::REQUIRED_MESSAGE, (string) $refusal);
        $this->assertStringContainsString('ZZ NPF TEST 2', (string) $refusal);
        $this->assertStringNotContainsString('ZZ-NPF-TEST-1', (string) $refusal);

        $this->assertNull($svc->refusal($this->request([
            'related_fileno' => ['ZZ-NPF-TEST-1'],
            $field => json_encode([['file_number' => 'zz-npf-test-1', 'sighted' => true]]),
        ])), 'answered (case-insensitive match)');

        $this->assertNull($svc->refusal($this->request([
            'related_fileno' => [],
            $field => '[]',
        ])), 'no related file, nothing to answer');

        $this->assertNull($svc->refusal($this->request([
            'related_fileno' => ['RES-1982-1651'],
        ])), 'callers without the field are not checked');
    }

    public function test_an_indexed_related_file_needs_no_answer_and_is_never_npf(): void
    {
        $indexed = DB::connection('sqlsrv')->table('file_indexings')
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->whereNotNull('file_number')
            ->orderByDesc('id')
            ->value('file_number');
        $svc = app(RelatedFileSightings::class);
        $unindexed = 'ZZ-NOT-INDEXED-' . date('YmdHis');

        $this->assertSame([\App\Services\FileIndexing\KangisBlockIndexing::key($indexed) => true], $svc->indexedKeys([$indexed, $unindexed]));

        $refusal = $svc->refusal($this->request([
            'related_fileno' => [$indexed, $unindexed],
            RelatedFileSightings::REQUEST_FIELD => '[]',
        ]));
        $this->assertStringContainsString($unindexed, (string) $refusal);
        $this->assertStringNotContainsString($indexed, (string) $refusal);

        $status = app(FileIndexingController::class)->relatedFileIndexedStatus(Request::create('/x', 'GET', ['file_number' => strtolower($indexed)]));
        $this->assertTrue($status->getData(true)['indexed']);
    }

    public function test_pair_key_ignores_direction_case_and_spaces(): void
    {
        $this->assertSame(
            RelatedFileSightings::pairKey('KNML 2375', 'RES-1982-1651'),
            RelatedFileSightings::pairKey('res-1982-1651', 'knml2375')
        );
    }

    public function test_npf_lookup_reads_without_writing_and_needs_two_files(): void
    {
        $svc = app(RelatedFileSightings::class);
        $this->assertSame([], $svc->npfPairs(['KNML 2375']));
        $this->assertIsArray($svc->npfPairs(['KNML 2375', 'RES-1982-1651']));
    }

    public function test_update_refuses_an_unanswered_related_file_before_saving(): void
    {
        $record = DB::connection('sqlsrv')->table('file_indexings')
            ->where('general_registry', 'KANGIS Registry')
            ->where('related_fileno', 'like', '[[]"%"]')
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first(['id', 'file_number', 'general_registry', 'physical_registry', 'related_fileno']);
        if (!$record) {
            $this->markTestSkipped('No KANGIS record with a related file to read.');
        }
        // A related file with no indexing record of its own: an indexed one needs no answer.
        $related = ['ZZ-NPF-TEST-' . date('YmdHis')];

        $request = $this->request([
            'file_number' => $record->file_number,
            'general_registry' => $record->general_registry,
            'physical_registry' => $record->physical_registry,
            'related_fileno' => $related,
            RelatedFileSightings::REQUEST_FIELD => '[]',
        ]);
        $user = User::on('sqlsrv')->first();
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);

        $response = app(FileIndexingController::class)->update($request, $record->id);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('related_sighting_required', $response->getData(true)['error_type']);
    }
}
