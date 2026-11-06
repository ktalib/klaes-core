<?php

namespace App\Services;

use App\Support\PlaceholderPhone;
use Illuminate\Support\Facades\DB;

/**
 * The applicant's phone and address for a file, from where FC and FEFR saved them (user,
 * 2026-11-05: backfill Applicant Address and Phone No from FC/FEFR on applications, then to
 * RofO).
 *
 * In order, first non-blank value wins per field:
 *   1. mls_file_no.phone_no / address      what File Commissioning (FC) saved;
 *   2. fileNumber.phone_no / address       the registry copy FC keeps, for older files;
 *   3. file_indexings.phone / residence_address   what FEFR saved.
 *
 * A placeholder phone (08000000000 typed because the applicant gave none) is not a phone, so
 * it never backfills anything. See App\Support\PlaceholderPhone.
 *
 * Reads only. A caller that already holds a value keeps it: this fills blanks.
 */
class ApplicantContactResolver
{
    /** Bound parameters per statement stay well under SQL Server's 2100. */
    private const CHUNK = 500;

    /** @var array<int,array{table:string,key:string,phone:string,address:string,live:bool}> */
    private const SOURCES = [
        ['table' => 'mls_file_no', 'key' => 'full_file_number', 'phone' => 'phone_no', 'address' => 'address', 'live' => true],
        ['table' => 'fileNumber', 'key' => 'mlsfNo', 'phone' => 'phone_no', 'address' => 'address', 'live' => true],
        ['table' => 'file_indexings', 'key' => 'file_number', 'phone' => 'phone', 'address' => 'residence_address', 'live' => false],
    ];

    /**
     * @param iterable<string|null> $fileNumbers
     * @return array<string,array{phone:?string,address:?string}> keyed by self::key()
     */
    public function forFiles(iterable $fileNumbers): array
    {
        $numbers = [];
        foreach ($fileNumbers as $fileNumber) {
            $text = trim((string) $fileNumber);
            if ($text !== '' && $text !== '—' && $text !== '-') {
                $numbers[self::key($text)] = $text;
            }
        }

        $found = [];
        foreach (self::SOURCES as $source) {
            // A file already answered on both fields needs no further look.
            $pending = array_filter($numbers, fn ($text, $key) => !isset($found[$key]['phone'], $found[$key]['address']), ARRAY_FILTER_USE_BOTH);

            foreach (array_chunk(array_values($pending), self::CHUNK) as $chunk) {
                $query = DB::connection('sqlsrv')->table($source['table'])
                    ->whereIn($source['key'], $chunk)
                    ->orderByDesc('id');
                if ($source['live']) {
                    $query->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0));
                }

                foreach ($query->get([$source['key'] . ' as file_key', $source['phone'] . ' as phone', $source['address'] . ' as address']) as $row) {
                    $key = self::key($row->file_key);
                    $phone = self::cleanPhone($row->phone);
                    $address = self::clean($row->address);
                    if ($phone !== null) {
                        $found[$key]['phone'] ??= $phone;
                    }
                    if ($address !== null) {
                        $found[$key]['address'] ??= $address;
                    }
                }
            }
        }

        $out = [];
        foreach ($numbers as $key => $text) {
            $out[$key] = ['phone' => $found[$key]['phone'] ?? null, 'address' => $found[$key]['address'] ?? null];
        }

        return $out;
    }

    /**
     * One file, with its live OSS application heard first: that is what the applicant
     * gave OSS, so FC and FEFR only fill what it left blank.
     *
     * @return array{phone:?string,address:?string}
     */
    public function forApplication(?string $fileNumber): array
    {
        $key = self::key($fileNumber);
        $application = DB::connection('sqlsrv')->table('oss_applications')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_no))) = ?', [$key])
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first(['phone', 'address', 'residential_address', 'correspondence_address']);

        $fallback = $this->forFiles([$fileNumber])[$key] ?? ['phone' => null, 'address' => null];

        return [
            'phone' => self::cleanPhone($application->phone ?? null) ?? $fallback['phone'],
            'address' => self::clean($application->residential_address ?? null)
                ?? self::clean($application->address ?? null)
                ?? self::clean($application->correspondence_address ?? null)
                ?? $fallback['address'],
        ];
    }

    public static function key(?string $fileNumber): string
    {
        return strtoupper(trim((string) $fileNumber));
    }

    private static function clean($value): ?string
    {
        $text = trim((string) $value);

        return in_array(strtoupper($text), ['', '-', '—', 'N/A', 'NA', 'NIL', 'NONE', 'NULL'], true) ? null : $text;
    }

    private static function cleanPhone($value): ?string
    {
        $phone = self::clean($value);

        return $phone === null || PlaceholderPhone::is($phone) ? null : $phone;
    }
}
