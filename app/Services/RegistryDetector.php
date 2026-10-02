<?php

namespace App\Services;

/**
 * Derives the physical Registry number (1/2/3) for a file number from
 * config/file_ranges.php — the same prefix+year rules FileLocationResolver
 * uses for Quick Search. This is the single source of truth for what
 * file_indexings.registry should contain for the Lands/SIT registry family;
 * it deliberately does not classify files belonging to other registries
 * (ST, SLTR, DCIV, KANGIS, Survey) — those aren't covered by file_ranges.php
 * and are left to general_registry instead.
 */
class RegistryDetector
{
    public function __construct(protected FileLocationResolver $resolver)
    {
    }

    /**
     * @return array{registry:?int, zone:?string, reason:string}
     *   reason is one of: matched, empty_file_number, unparseable_no_year,
     *   prefix_not_in_registry_config, registry_label_has_no_number.
     */
    public function detect(?string $fileNumber): array
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber === '') {
            return ['registry' => null, 'zone' => null, 'reason' => 'empty_file_number'];
        }

        if ($this->resolver->parse($fileNumber) === null) {
            return ['registry' => null, 'zone' => null, 'reason' => 'unparseable_no_year'];
        }

        $range = $this->resolver->matchRange($fileNumber);
        if ($range === null) {
            return ['registry' => null, 'zone' => null, 'reason' => 'prefix_not_in_registry_config'];
        }

        if (!preg_match('/(\d+)/', (string) $range['registry'], $m)) {
            return ['registry' => null, 'zone' => $range['zone'], 'reason' => 'registry_label_has_no_number'];
        }

        return ['registry' => (int) $m[1], 'zone' => $range['zone'], 'reason' => 'matched'];
    }

    /**
     * Convenience wrapper returning just the registry number, or null.
     */
    public function detectNumber(?string $fileNumber): ?int
    {
        return $this->detect($fileNumber)['registry'];
    }
}
