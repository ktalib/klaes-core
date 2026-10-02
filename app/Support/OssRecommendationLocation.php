<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The one answer to "where is this OSS file?", for every sheet that prints it.
 *
 * There used to be two. The White Copy prints the Recommendation card's Location
 * box, which the Applications register fills from the live record; the official
 * recommendation printed land_recommendations.location, a snapshot taken when the
 * card was last saved. A location corrected on the file afterwards reached the
 * first and not the second, so the proof an officer read and the document that
 * went out disagreed — and the proof was the correct one.
 *
 * So the ladder lives here, once, and both sheets climb it. It is the register's
 * own ladder, deliberately: the location on the recommendation is then the
 * location in the LOCATION column the officer is looking at when they open the
 * card, and there is no third answer to reconcile.
 *
 *   oss_applications.location            (the newest row for the file)
 *   pra.location                         (its Transfer of Title row)
 *   pra.property_description
 *   instrument_capture.property_description  (the commissioning's source capture)
 *   fileNumber.location
 *
 * See LandsOneStopShop\ApplicationController::indexChangeOfNameApplications(),
 * whose transform this mirrors step for step.
 */
class OssRecommendationLocation
{
    /**
     * A placeholder is not a location.
     *
     * "OTHER" is what the location builder writes when nothing was chosen, and
     * the em dash is what the register prints for a blank cell. Neither says
     * where the land is, so both are stepped over rather than printed.
     */
    private const PLACEHOLDERS = ['', 'OTHER', 'OTHERS', '—', '-'];

    /**
     * The file's location as the record now stands, or $fallback when the record
     * offers nothing usable.
     *
     * $fallback is the stored recommendation value: a file with no usable
     * location on any of its rows must not have a line blanked out that the
     * recommendation already carries.
     */
    public static function resolve(?string $fileNumber, string $fallback = ''): string
    {
        $fileNumber = strtoupper(trim((string) $fileNumber));
        if ($fileNumber === '') {
            return $fallback;
        }

        try {
            $row = static::sourceRow($fileNumber);
        } catch (\Throwable $e) {
            // A print must never fail over the one line it is refining.
            return $fallback;
        }

        if (!$row) {
            return $fallback;
        }

        $ladder = [
            $row->oa_location ?? '',
            $row->pra_location ?? '',
            $row->pra_property_description ?? '',
            $row->ic_location ?? '',
            $row->fn_location ?? '',
        ];

        foreach ($ladder as $candidate) {
            $value = trim((string) $candidate);
            if (!in_array(strtoupper($value), self::PLACEHOLDERS, true)) {
                // Upper-cased for the same reason the register upper-cases it:
                // this is a printed legal description, not free text.
                return strtoupper($value);
            }
        }

        return $fallback;
    }

    /**
     * Every rung of the ladder in one round trip.
     *
     * Each join picks the same row the register picks: the newest application for
     * the file, its newest Transfer of Title, the capture the commissioning was
     * raised from, and the newest file-number entry.
     */
    private static function sourceRow(string $fileNumber): ?object
    {
        $sql = "
            SELECT
                oa.location                 as oa_location,
                p.location                  as pra_location,
                p.property_description      as pra_property_description,
                ic.property_description     as ic_location,
                f.location                  as fn_location
            FROM (
                SELECT TOP 1 location
                FROM oss_applications
                WHERE UPPER(file_no) = ?
                  AND (is_deleted IS NULL OR is_deleted = 0)
                ORDER BY id DESC
            ) as oa
            OUTER APPLY (
                SELECT TOP 1 location, property_description
                FROM pra
                WHERE (UPPER(mlsFNo) = ? OR UPPER(fileno) = ?)
                  AND (instrument_type LIKE '%Transfer of Title%'
                       OR transaction_type LIKE '%Transfer of Title%')
                  AND (is_deleted IS NULL OR is_deleted = 0)
                ORDER BY id DESC
            ) as p
            OUTER APPLY (
                SELECT TOP 1 fn.location, mfn.source_instrument_capture_id
                FROM fileNumber fn
                LEFT JOIN mls_file_no mfn
                       ON mfn.tracking_id = fn.tracking_id
                      AND UPPER(mfn.full_file_number) = ?
                WHERE UPPER(fn.mlsfNo) = ?
                ORDER BY fn.id DESC
            ) as f
            LEFT JOIN instrument_capture ic ON ic.id = f.source_instrument_capture_id
        ";

        return DB::connection('sqlsrv')->selectOne($sql, [
            $fileNumber, $fileNumber, $fileNumber, $fileNumber, $fileNumber,
        ]);
    }
}
