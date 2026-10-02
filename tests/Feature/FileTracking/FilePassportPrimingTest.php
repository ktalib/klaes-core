<?php

namespace Tests\Feature\FileTracking;

use App\Services\FilePassportService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The file tracker list decorates every row on the page, so the applicant photograph shown
 * on each log card must be resolved in bulk. This list has already been taken down once by
 * a per-row lookup of exactly this shape, which is why prime() exists at all.
 *
 * Two things have to hold, and only together: priming must be cheap, AND it must return
 * exactly what the per-file path returns. A fast batch that disagrees with resolve() would
 * put the wrong face on a file.
 */
class FilePassportPrimingTest extends TestCase
{
    private FilePassportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FilePassportService::class);
        FilePassportService::flushCache();
    }

    protected function tearDown(): void
    {
        FilePassportService::flushCache();
        parent::tearDown();
    }

    /** Real file numbers off the tracker list — the exact input the list screen primes. */
    private function sampleFileNumbers(int $limit = 25): array
    {
        return DB::connection('sqlsrv')->table('file_tracker')
            ->whereNotNull('file_number')
            ->limit($limit)
            ->pluck('file_number')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array{0:int,1:array} queries run, and the resolved URLs */
    private function measure(callable $work): array
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $result = $work();

        return [$count, $result];
    }

    public function test_priming_costs_a_fixed_number_of_queries_regardless_of_page_size(): void
    {
        $files = $this->sampleFileNumbers();

        if (count($files) < 5) {
            $this->markTestSkipped('Not enough tracker rows on this database to measure priming.');
        }

        [$queries] = $this->measure(function () use ($files) {
            $this->service->prime($files);
            foreach ($files as $file) {
                $this->service->resolve($file);
            }

            return null;
        });

        // Two lookups (oss_applications, then scannings) plus the schema probes, and NOT
        // one round trip per file. The bound is deliberately loose — what matters is that
        // it does not scale with the number of files.
        $this->assertLessThanOrEqual(
            8,
            $queries,
            "Priming " . count($files) . " files ran {$queries} queries; it must not scale per row."
        );
    }

    public function test_a_primed_lookup_returns_exactly_what_an_unprimed_one_does(): void
    {
        $files = $this->sampleFileNumbers();

        if ($files === []) {
            $this->markTestSkipped('No tracker rows on this database.');
        }

        FilePassportService::flushCache();
        $unprimed = array_map(fn ($f) => $this->service->resolve($f), $files);

        FilePassportService::flushCache();
        $this->service->prime($files);
        $primed = array_map(fn ($f) => $this->service->resolve($f), $files);

        $this->assertSame(
            $unprimed,
            $primed,
            'The batch resolver disagrees with the per-file one, so a card could show the wrong photo.'
        );
    }

    /** The collation is case-insensitive; the cache key must be too, or priming misses. */
    public function test_priming_is_case_insensitive(): void
    {
        $files = $this->sampleFileNumbers(5);

        if ($files === []) {
            $this->markTestSkipped('No tracker rows on this database.');
        }

        $this->service->prime(array_map('strtolower', $files));

        [$queries] = $this->measure(function () use ($files) {
            foreach ($files as $file) {
                $this->service->resolve(strtoupper($file));
            }

            return null;
        });

        $this->assertSame(0, $queries, 'An upper-cased file number missed the primed cache.');
    }

    /** A file number nobody primed still resolves, so single-record screens keep working. */
    public function test_resolve_still_works_without_priming(): void
    {
        $this->assertNull($this->service->resolve('NO-SUCH-FILE-' . uniqid()));
        $this->assertNull($this->service->resolve(''));
        $this->assertNull($this->service->resolve(null));
    }

    /** Priming an empty or junk set must not throw or query. */
    public function test_priming_nothing_is_a_no_op(): void
    {
        [$queries] = $this->measure(function () {
            $this->service->prime([]);
            $this->service->prime(['', '   ', null]);

            return null;
        });

        $this->assertSame(0, $queries);
    }
}
