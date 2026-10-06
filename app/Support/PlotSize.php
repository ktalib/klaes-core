<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The plot size a ground rent is charged on (SLTR and Land), held in square metres.
 *
 * The ground rent on a recommendation is a RATE (e.g. 8.00 Per Square Meters).
 * What the holder owes is that rate times the plot size, so the size has to be
 * on the record before any naira amount can be stated.
 *
 * Where the file has been indexed with a size, that size is the authority: it
 * prefills the form, locks there, and the server re-reads it on save.
 */
class PlotSize
{
    private const SQM_PER_HECTARE = 10000;

    /**
     * Read an indexing plot_size (free text) as square metres.
     *
     * The column holds what the clerk typed: mostly bare square metres for SLTR
     * ("167"), a handful of hectare fractions ("0.0218"), the odd "2,540Sqm",
     * and a great many zeros meaning "not captured". No real plot is under one
     * square metre, so a figure below 1 can only be hectares.
     */
    public static function parse($raw): ?float
    {
        $text = strtolower(trim((string) $raw));
        $text = str_replace([',', ' ', 'sqm', 'sq.m', 'm2', 'm²'], '', $text);

        if ($text === '' || !is_numeric($text)) {
            return null;
        }

        $value = (float) $text;
        if ($value <= 0) {
            return null;
        }

        return round($value < 1 ? $value * self::SQM_PER_HECTARE : $value, 2);
    }

    /**
     * The indexed plot size for a file number, in square metres, or null when
     * the file was never indexed or was indexed without a usable size.
     *
     * Joined on the file-number string, as every indexing lookup is.
     */
    public static function fromIndexing(?string $fileNumber): ?float
    {
        $fileNumber = strtoupper(trim((string) $fileNumber));
        if ($fileNumber === '') {
            return null;
        }

        $sizes = DB::connection('sqlsrv')->table('file_indexings')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$fileNumber])
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->pluck('plot_size');

        foreach ($sizes as $raw) {
            if (($sqm = self::parse($raw)) !== null) {
                return $sqm;
            }
        }

        return null;
    }

    /**
     * Ground rent amount = rate x plot size, in the rate's own unit.
     *
     * Null when either half is missing, or when the rate is in a unit typed under
     * "Other" - there is no safe way to convert "Per Acre (approx.)" into m².
     */
    public static function amount($rate, ?string $unit, $plotSizeSqm): ?float
    {
        if ($rate === null || $rate === '' || $plotSizeSqm === null || $plotSizeSqm === '') {
            return null;
        }

        $unit = strtolower(trim((string) $unit));
        $size = (float) $plotSizeSqm;

        $quantity = match (true) {
            $unit === '' || $unit === 'per square meters' => $size,
            $unit === 'ha'                                => $size / self::SQM_PER_HECTARE,
            default                                       => null,
        };

        return $quantity === null ? null : round((float) $rate * $quantity, 2);
    }
}
