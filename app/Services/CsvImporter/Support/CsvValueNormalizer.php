<?php

namespace App\Services\CsvImporter\Support;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CsvValueNormalizer
{
    private const SQL_SERVER_MIN_YEAR = 1753;
    private const SQL_SERVER_MAX_YEAR = 9999;
    private const SQL_DEFAULT_FALLBACK_DATE = '1900-01-01';

    public function formatValue($value, bool $numeric = false): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        if ($numeric) {
            if (is_float($value) && floor($value) === $value) {
                return (string) (int) $value;
            }

            if (is_numeric($value)) {
                $stringValue = (string) $value;
                return Str::endsWith($stringValue, '.0')
                    ? Str::replaceLast('.0', '', $stringValue)
                    : $stringValue;
            }
        }

        $stringValue = trim((string) $value);

        return $stringValue;
    }

    public function normalizeString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = is_string($value) ? trim($value) : trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        $lowered = strtolower($stringValue);
        if (in_array($lowered, ['nan', 'none', 'null', 'undefined', 'n/a'], true)) {
            return null;
        }

        return $stringValue;
    }

    public function normalizeNumericField($value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        if (!is_numeric($normalized)) {
            return $normalized;
        }

        $floatValue = (float) $normalized;

        return fmod($floatValue, 1) === 0.0
            ? (string) (int) $floatValue
            : (string) $floatValue;
    }

    public function normalizeOldKnNumber($value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        return str_replace('-', ' ', $normalized);
    }

    public function collapseWhitespace(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    }

    public function stripAllWhitespace(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/\s+/', '', (string) $value) ?? '';
    }

    public function removeFileNumberSuffixes(?string $value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        $patterns = [
            '/\s+AND\s+EXTENSION\s*$/i',
            '/\s*&\s*EXTENSION\s*$/i',
        ];

        $cleaned = $normalized;

        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned) ?? $cleaned;
        }

        return trim($cleaned) === '' ? null : trim($cleaned);
    }

    public function standardizeFileNumber(?string $value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        $base = $this->removeFileNumberSuffixes($normalized) ?? $normalized;

        return Str::upper($base);
    }

    public function normalizeFileNumberForMatch(?string $value): ?string
    {
        $standardized = $this->standardizeFileNumber($value);
        if ($standardized === null) {
            return null;
        }

        $noDash = str_replace('-', '', $standardized);

        return preg_replace('/\s+/', '', $noDash);
    }

    public function combineLocation(?string ...$parts): ?string
    {
        $filtered = array_values(array_filter(array_map([$this, 'normalizeString'], $parts)));

        return empty($filtered) ? null : implode(', ', $filtered);
    }

    public function buildRegNo(array $record): ?string
    {
        $serial = $this->normalizeString(Arr::get($record, 'serial_no'));
        $page = $this->normalizeString(Arr::get($record, 'page_no'));
        $volume = $this->normalizeString(Arr::get($record, 'vol_no'));

        if ($serial && $page && $volume) {
            return "{$serial}/{$page}/{$volume}";
        }

        return null;
    }

    public function coerceSqlDate(?string $value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        $parsed = $this->parseDate($normalized);
        if ($parsed === null) {
            return self::SQL_DEFAULT_FALLBACK_DATE;
        }

        $year = (int) $parsed->format('Y');
        if ($year < self::SQL_SERVER_MIN_YEAR || $year > self::SQL_SERVER_MAX_YEAR) {
            return self::SQL_DEFAULT_FALLBACK_DATE;
        }

        return $parsed->format('Y-m-d');
    }

    public function formatDateForUi(?string $value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        $parsed = $this->parseDate($normalized);

        return $parsed?->format('d-m-Y');
    }

    public function formatTimeForUi(?string $value): ?string
    {
        $normalized = $this->normalizeString($value);
        if ($normalized === null) {
            return null;
        }

        $formats = ['H:i:s', 'H:i', 'g:i A', 'g:i:s A', 'Hi'];

        foreach ($formats as $format) {
            try {
                $carbon = Carbon::createFromFormat($format, $normalized);

                return $carbon->format('h:i A');
            } catch (\Throwable $exception) {
                continue;
            }
        }

        try {
            return Carbon::parse($normalized)->format('h:i A');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function parseDate(string $value): ?Carbon
    {
        $candidates = [
            $value,
            str_replace('/', '-', $value),
            str_replace('.', '-', $value),
        ];

        foreach ($candidates as $candidate) {
            try {
                return Carbon::parse($candidate);
            } catch (\Throwable $exception) {
                continue;
            }
        }

        return null;
    }
}
