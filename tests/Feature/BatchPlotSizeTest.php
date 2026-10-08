<?php

namespace Tests\Feature;

use App\Models\LandRecommendation;
use App\Support\BatchPlotSize;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BatchPlotSizeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->statement('CREATE TABLE file_indexings (file_number TEXT, plot_size TEXT, is_deleted INTEGER)');
    }

    public function test_all_missing_files_are_reported_together(): void
    {
        $children = array_map(fn ($i) => ['file_number' => 'CON-RES-2026-' . $i], range(1, 79));
        try {
            BatchPlotSize::validate(['children' => $children]);
            $this->fail('A blank batch must not pass.');
        } catch (ValidationException $e) {
            $this->assertCount(79, $e->errors());
            $this->assertStringContainsString('CON-RES-2026-79', $e->errors()['children.78.area_sqm'][0]);
        }
    }

    public function test_shared_size_and_individual_override_are_resolved(): void
    {
        $result = BatchPlotSize::validate(['area_sqm' => 450, 'children' => [
            ['file_number' => 'RES-1'], ['file_number' => 'RES-2', 'area_sqm' => 600],
        ]]);
        $this->assertSame([450, 600], array_column($result['children'], 'area_sqm'));
    }

    public function test_indexed_size_wins_and_deleted_indexing_does_not_count(): void
    {
        DB::connection('sqlsrv')->table('file_indexings')->insert([
            ['file_number' => ' res-1 ', 'plot_size' => '2,540Sqm', 'is_deleted' => 0],
            ['file_number' => 'RES-2', 'plot_size' => '500', 'is_deleted' => 1],
        ]);
        $result = BatchPlotSize::validate(['children' => [['file_number' => 'RES-1', 'area_sqm' => null]]]);
        $this->assertSame(2540.0, $result['children'][0]['area_sqm']);
        $this->expectException(ValidationException::class);
        BatchPlotSize::validate(['children' => [['file_number' => 'RES-2']]]);
    }

    public function test_explicit_blank_zero_and_negative_never_inherit_shared_size(): void
    {
        foreach ([null, '', 0, -10] as $size) {
            try {
                BatchPlotSize::validate(['area_sqm' => 450, 'children' => [['file_number' => 'RES-1', 'area_sqm' => $size]]]);
                $this->fail('Invalid child size accepted.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('children.0.area_sqm', $e->errors());
            }
        }
    }

    public function test_issued_file_keeps_its_size_and_accepts_explicit_correction(): void
    {
        $existing = [(object) ['file_number' => 'RES-1', 'rofo_status' => LandRecommendation::ROFO_GENERATED, 'area_sqm' => 600]];
        $result = BatchPlotSize::validate(['area_sqm' => 450, 'children' => [['file_number' => 'RES-1']]], $existing);
        $this->assertSame(600, $result['children'][0]['area_sqm']);
        $result = BatchPlotSize::validate(['children' => [['file_number' => 'RES-1', 'area_sqm' => 700]]], $existing);
        $this->assertSame(700, $result['children'][0]['area_sqm']);
    }

    public function test_issued_file_missing_size_is_rejected_on_edit(): void
    {
        $existing = [(object) ['file_number' => 'RES-1', 'rofo_status' => LandRecommendation::ROFO_GENERATED, 'area_sqm' => null]];
        $this->expectException(ValidationException::class);
        BatchPlotSize::validate(['area_sqm' => 450, 'children' => [['file_number' => 'RES-1']]], $existing);
    }
}
