<?php

namespace Tests\Unit\Support;

use App\Support\MlsRowTarget;
use Tests\TestCase;

/**
 * MlsRowTarget — deciding which table a row on the MLS File Commission list came from.
 *
 * The stakes are specific and were realised in production data. That list is a UNION of
 * three tables whose ids overlap: `fileNumber` (ids 8 … 144790), temporary files taking
 * their id from `mls_file_no` (max 67653), and plot extensions taking theirs from
 * `plot_extensions` (max 6). Every temporary row therefore shares its id with a real,
 * unrelated file — temporary RES-1993-2644(T) is id 1166, and fileNumber 1166 is the live
 * file CON-AG-1987-57.
 *
 * Master Delete resolved the bare id against `fileNumber` unconditionally, so deleting a
 * temporary row purged a different file from five tables. Both directions are asserted
 * here: a key must resolve to the right table, and an unreadable key must resolve to
 * NOTHING rather than defaulting to the one table that can destroy data.
 */
class MlsRowTargetTest extends TestCase
{
    /** @test */
    public function it_maps_row_types_to_their_backing_table(): void
    {
        $this->assertSame(MlsRowTarget::PLOT_EXTENSION, MlsRowTarget::fromRowType('Plot Extension'));
        $this->assertSame(MlsRowTarget::TEMPORARY, MlsRowTarget::fromRowType('Temporary'));

        // getData() emits these for ordinary fileNumber rows.
        $this->assertSame(MlsRowTarget::FILE_NUMBER, MlsRowTarget::fromRowType('MLS'));
        $this->assertSame(MlsRowTarget::FILE_NUMBER, MlsRowTarget::fromRowType(''));
        $this->assertSame(MlsRowTarget::FILE_NUMBER, MlsRowTarget::fromRowType(null));
    }

    /** @test */
    public function row_type_matching_ignores_case_and_padding(): void
    {
        $this->assertSame(MlsRowTarget::PLOT_EXTENSION, MlsRowTarget::fromRowType('  plot extension '));
        $this->assertSame(MlsRowTarget::TEMPORARY, MlsRowTarget::fromRowType('TEMPORARY'));
    }

    /** @test */
    public function it_normalises_a_client_supplied_entity(): void
    {
        $this->assertSame(MlsRowTarget::TEMPORARY, MlsRowTarget::entity('temporary'));
        $this->assertSame(MlsRowTarget::PLOT_EXTENSION, MlsRowTarget::entity('PLOT_EXTENSION'));
        $this->assertSame(MlsRowTarget::FILE_NUMBER, MlsRowTarget::entity('file_number'));
    }

    /** @test */
    public function an_unknown_entity_is_treated_as_a_file_number_row(): void
    {
        // Not a security decision — file_number is the only entity the delete cascade
        // accepts, and it is separately verified against the fileNumber table.
        $this->assertSame(MlsRowTarget::FILE_NUMBER, MlsRowTarget::entity(null));
        $this->assertSame(MlsRowTarget::FILE_NUMBER, MlsRowTarget::entity(''));
        $this->assertSame(MlsRowTarget::FILE_NUMBER, MlsRowTarget::entity('nonsense'));
    }

    /** @test */
    public function it_parses_prefixed_selection_keys(): void
    {
        $this->assertSame(
            ['entity' => MlsRowTarget::FILE_NUMBER, 'id' => 60386],
            MlsRowTarget::parseKey('F:60386')
        );

        // The exact collision that caused the bug: this must NOT come back as fileNumber.
        $this->assertSame(
            ['entity' => MlsRowTarget::TEMPORARY, 'id' => 1166],
            MlsRowTarget::parseKey('T:1166')
        );

        $this->assertSame(
            ['entity' => MlsRowTarget::PLOT_EXTENSION, 'id' => 4],
            MlsRowTarget::parseKey('P:4')
        );
    }

    /** @test */
    public function a_bare_id_still_resolves_for_pages_cached_before_the_fix(): void
    {
        $this->assertSame(
            ['entity' => MlsRowTarget::FILE_NUMBER, 'id' => 123],
            MlsRowTarget::parseKey('123')
        );
    }

    /** @test */
    public function an_unknown_prefix_is_refused_rather_than_guessed(): void
    {
        // "X:1166" could mean anything. Falling back to fileNumber would delete file 1166.
        $this->assertNull(MlsRowTarget::parseKey('X:1166'));
        $this->assertNull(MlsRowTarget::parseKey('FILE:1166'));
    }

    /** @test */
    public function it_refuses_keys_without_a_usable_id(): void
    {
        $this->assertNull(MlsRowTarget::parseKey(''));
        $this->assertNull(MlsRowTarget::parseKey('   '));
        $this->assertNull(MlsRowTarget::parseKey('F:'));
        $this->assertNull(MlsRowTarget::parseKey('F:abc'));
        $this->assertNull(MlsRowTarget::parseKey('F:0'));
        $this->assertNull(MlsRowTarget::parseKey('F:-5'));
        $this->assertNull(MlsRowTarget::parseKey('F:12.5'));
        $this->assertNull(MlsRowTarget::parseKey(null));
    }

    /** @test */
    public function it_does_not_coerce_a_trailing_number_out_of_junk(): void
    {
        // "12abc" casts to 12 in PHP. An id derived that way would delete file 12.
        $this->assertNull(MlsRowTarget::parseKey('12abc'));
        $this->assertNull(MlsRowTarget::parseKey('abc12'));
    }

    /** @test */
    public function it_parses_a_whole_selection_and_reports_what_it_rejected(): void
    {
        $result = MlsRowTarget::parseKeys(['F:1', 'T:1166', 'P:4', 'junk', 'X:9', '']);

        $this->assertSame([
            ['entity' => MlsRowTarget::FILE_NUMBER, 'id' => 1],
            ['entity' => MlsRowTarget::TEMPORARY, 'id' => 1166],
            ['entity' => MlsRowTarget::PLOT_EXTENSION, 'id' => 4],
        ], $result['targets']);

        // Nothing is silently dropped — the UI reports these back to the user.
        $this->assertSame(['junk', 'X:9', ''], $result['rejected']);
    }

    /** @test */
    public function the_same_row_selected_twice_is_deleted_once(): void
    {
        $result = MlsRowTarget::parseKeys(['F:1', 'F:1', '1']);

        $this->assertCount(1, $result['targets']);
        $this->assertSame(1, $result['targets'][0]['id']);
    }

    /** @test */
    public function the_same_id_in_different_tables_stays_two_distinct_targets(): void
    {
        // This is the whole point of the class: F:1166 and T:1166 are different files.
        $result = MlsRowTarget::parseKeys(['F:1166', 'T:1166']);

        $this->assertCount(2, $result['targets']);
        $this->assertSame(MlsRowTarget::FILE_NUMBER, $result['targets'][0]['entity']);
        $this->assertSame(MlsRowTarget::TEMPORARY, $result['targets'][1]['entity']);
    }

    /** @test */
    public function it_labels_entities_for_the_refusal_message(): void
    {
        $this->assertSame('Temporary file', MlsRowTarget::label(MlsRowTarget::TEMPORARY));
        $this->assertSame('Plot Extension', MlsRowTarget::label(MlsRowTarget::PLOT_EXTENSION));
        $this->assertSame('MLS file record', MlsRowTarget::label(MlsRowTarget::FILE_NUMBER));
    }
}
