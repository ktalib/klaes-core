<?php

namespace Tests\Unit;

use App\Services\MlsCommissioningOssApplicationService;
use PHPUnit\Framework\TestCase;

class MlsCommissioningOssApplicationServiceTest extends TestCase
{
    /** @dataProvider applicationTypeProvider */
    public function test_it_maps_mls_land_use_to_an_oss_application_type($landUse, $fileNumber, $expected): void
    {
        $service = new MlsCommissioningOssApplicationService();

        $this->assertSame($expected, $service->resolveApplicationType($landUse, $fileNumber));
    }

    public function applicationTypeProvider(): array
    {
        return [
            'residential' => ['RESIDENTIAL', 'RES-2026-1', 'residential'],
            'commercial' => ['COMMERCIAL (LARGE SCALE)', 'COM-2026-1', 'commercial'],
            'con prefix' => [null, 'CON-COM-2026-1', 'commercial'],
            'industrial' => ['INDUSTRIAL', 'IND-2026-1', 'industrial'],
            'agricultural' => ['AGRICULTURE', 'AGR-2026-1', 'agricultural'],
            'safe fallback' => ['SPECIAL', 'SPE-2026-1', 'residential'],
        ];
    }
}
