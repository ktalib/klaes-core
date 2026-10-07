<?php

namespace Tests\Unit;

use App\Services\FileTracking\FileQrResolver;
use Tests\TestCase;

/**
 * FileQrResolver::normalize() — pure string parsing of every QR shape KLAES
 * prints. No database: resolve() is exercised through the rollback harness.
 */
class FileQrResolverTest extends TestCase
{
    private function n(string $payload): array
    {
        return app(FileQrResolver::class)->normalize($payload);
    }

    public function test_bare_file_tracker_id(): void
    {
        $r = $this->n('TRK-260101120000-AB12-R003');
        $this->assertSame('TRK-260101120000-AB12-R003', $r['tracking_id']);
        $this->assertNull($r['unresolvable']);
    }

    public function test_tracking_sheet_json_carries_file_number(): void
    {
        $r = $this->n(json_encode(['tracking_id' => 'TRK-ABCD2345-EFGH2', 'file_number' => 'RES-2020-123', 'status' => 'Active']));
        $this->assertSame('RES-2020-123', $r['file_number']);
        $this->assertSame('TRK-ABCD2345-EFGH2', $r['tracking_id']);
        $this->assertSame(['TRK-ABCD2345-EFGH2', 'RES-2020-123'], $r['candidates']);
    }

    public function test_recommendation_json_with_tracking_id_only(): void
    {
        $r = $this->n('{"tracking_id":"TRK-ABCD2345-EFGH2"}');
        $this->assertSame(['TRK-ABCD2345-EFGH2'], $r['candidates']);
    }

    public function test_verify_file_url(): void
    {
        $r = $this->n('https://app.klaes.ng/verify-file/RES-2020-123/TRK-ABCD2345-EFGH2');
        $this->assertSame('RES-2020-123', $r['file_number']);
        $this->assertSame('TRK-ABCD2345-EFGH2', $r['tracking_id']);
    }

    public function test_old_filetracker_pipe_text(): void
    {
        $r = $this->n('TRK:TRK-000123|FILE:COM-91-249|KANGIS:KNML 0001|LOC:Registry|RFID:');
        $this->assertSame('COM-91-249', $r['file_number']);
        $this->assertSame(['COM-91-249', 'KNML 0001'], $r['candidates']);

        $r = $this->n('TRK-000123|RES-2020-123|Registry|RF001');
        $this->assertSame('RES-2020-123', $r['file_number']);
    }

    public function test_search_report_numbers(): void
    {
        $r = $this->n('File Number: MLSF: RES-2020-123 | KANGIS: N/A | New KANGIS: KN0001');
        $this->assertSame(['RES-2020-123', 'KN0001'], $r['candidates']);
    }

    public function test_url_encoded_sltr_number_is_decoded(): void
    {
        $r = $this->n('SLTR%2F123%2F2024');
        $this->assertSame('SLTR/123/2024', $r['raw']);
        $this->assertSame(['SLTR/123/2024'], $r['candidates']);
    }

    public function test_prefixed_application_ids(): void
    {
        $this->assertSame(['type' => 'COP', 'id' => '42'], $this->n('COP-42')['prefixed']);
        $this->assertSame(['type' => 'CON', 'id' => '7'], $this->n('con-7')['prefixed']);
        $this->assertSame(['OP/2024/001'], $this->n('OP-VERIFICATION: OP/2024/001')['candidates']);
    }

    public function test_unresolvable_payloads(): void
    {
        foreach (['N/A', 'KanoLandSurvey12', 'a3f9c01b2de', 'OP Ack | John Doe | Plot: 12 | Plan: TP1 | 2026-01-01', ''] as $payload) {
            $this->assertNotNull($this->n($payload)['unresolvable'], "Expected unresolvable: {$payload}");
        }
    }

    public function test_plain_file_number_is_a_candidate(): void
    {
        $r = $this->n('  RES-2020-123 ');
        $this->assertSame(['RES-2020-123'], $r['candidates']);
        $this->assertNull($r['unresolvable']);
    }

    public function test_bare_integer_is_kept_for_lookup(): void
    {
        $this->assertSame(['179239'], $this->n('179239')['candidates']);
    }
}
