<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterDcivLink extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'master_dciv_links';

    protected $fillable = [
        'dciv_file_no_id',
        'dciv_file_number',
        'dciv_reason',
        'related_file_number',
        'land_file_number',
        'sltr_file_number',
        'st_file_number',
        'related_file_type',
        'related_file_title',
        'created_by',
    ];

    /**
     * Classify a related file number into one of Land | SLTR | ST | Other based
     * on its prefix. Mirrors the prefix detection used in
     * FileIndexingController::resolveGroupingTableForRename() and the backfill
     * CASE expression in the create_master_dciv_links migration.
     */
    public static function classifyFileType(?string $fileNo): string
    {
        $f = strtoupper(trim((string) $fileNo));

        if ($f === '') {
            return 'Other';
        }

        if (str_starts_with($f, 'SLTR')) {
            return 'SLTR';
        }

        if (str_starts_with($f, 'ST-') || str_starts_with($f, 'ST ')) {
            return 'ST';
        }

        // Land prefixes — includes conversion (CON-*) and recertification (*-RC)
        // variants, which all resolve to land files.
        foreach (['RES', 'COM', 'IND', 'AG', 'MIXED', 'CON', 'KN', 'MISC', 'SIT', 'GKN', 'LPKN'] as $prefix) {
            if (str_starts_with($f, $prefix)) {
                return 'Land';
            }
        }

        return 'Other';
    }

    /**
     * Build the per-type file-number columns for an insert/update payload,
     * given the related file number and its classified type.
     *
     * @return array{land_file_number: ?string, sltr_file_number: ?string, st_file_number: ?string, related_file_type: string}
     */
    public static function typeColumns(?string $fileNo): array
    {
        $type = self::classifyFileType($fileNo);
        $clean = trim((string) $fileNo);

        return [
            'land_file_number' => $type === 'Land' ? $clean : null,
            'sltr_file_number' => $type === 'SLTR' ? $clean : null,
            'st_file_number'   => $type === 'ST' ? $clean : null,
            'related_file_type' => $type,
        ];
    }
}
