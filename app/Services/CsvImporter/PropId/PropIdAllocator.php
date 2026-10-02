<?php

namespace App\Services\CsvImporter\PropId;

use App\Services\PropertyIdAllocationService;

class PropIdAllocator
{
    public function __construct(
        private readonly PropertyIdAllocationService $allocationService
    ) {
    }

    public function allocate(array $identifiers, array $options = []): int
    {
        $primary = $identifiers['primary_file_number'] ?? null;
        $mls = $identifiers['mlsFNo'] ?? null;
        $kangis = $identifiers['kangisFileNo'] ?? null;
        $newKangis = $identifiers['NewKANGISFileno'] ?? null;

        return $this->allocationService->allocateOrRetrievePropId(
            $primary,
            $mls,
            $kangis,
            $newKangis,
            $options
        );
    }

    public function syncFileHistory(string $fileNumber, int $propId, ?string $testControl = null): void
    {
        $this->allocationService->syncPropIdToFileHistory($fileNumber, $propId, $testControl);
    }
}
