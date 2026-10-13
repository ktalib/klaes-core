<?php

namespace App\Services;

/**
 * ST primary-application initial bill (application, processing, site plan).
 *
 * Single source for both the form display (primaryform/partials/initial_bill)
 * and the amounts saved by PrimaryApplicationController::store, so the saved
 * bill never depends on what the browser posts back.
 */
class StInitialBillSchedule
{
    private const RESIDENTIAL = [
        'application_fee' => 10000.00,
        'processing_fee'  => 20000.00,
        'site_plan_fee'   => 10000.00,
    ];

    private const COMMERCIAL = [
        'application_fee' => 20000.00,
        'processing_fee'  => 50000.00,
        'site_plan_fee'   => 10000.00,
    ];

    public static function isMixed(?string $landUse): bool
    {
        return str_starts_with(strtoupper(trim((string) $landUse)), 'MIXED');
    }

    /**
     * @return array{application_fee: float, processing_fee: float, site_plan_fee: float}
     */
    public static function forLandUse(?string $landUse): array
    {
        if (self::isMixed($landUse)) {
            $fees = [];
            foreach (self::RESIDENTIAL as $key => $amount) {
                $fees[$key] = $amount + self::COMMERCIAL[$key];
            }
            return $fees;
        }

        $normalized = strtoupper(trim((string) $landUse));

        return in_array($normalized, ['COMMERCIAL', 'INDUSTRIAL'], true)
            ? self::COMMERCIAL
            : self::RESIDENTIAL;
    }

    public static function total(?string $landUse): float
    {
        return array_sum(self::forLandUse($landUse));
    }
}
