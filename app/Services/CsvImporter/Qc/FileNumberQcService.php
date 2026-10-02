<?php

namespace App\Services\CsvImporter\Qc;

use App\Services\CsvImporter\Support\CsvValueNormalizer;
use App\Services\CsvImporter\Support\FileNumberCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FileNumberQcService
{
    public function __construct(
        private readonly CsvValueNormalizer $normalizer,
        private readonly FileNumberCatalog $catalog,
    ) {
    }

    public function analyze(array $records): array
    {
        $issues = [
            'padding' => [],
            'year' => [],
            'spacing' => [],
            'catalog' => [],
        ];

        foreach ($records as $index => $record) {
            $raw = Arr::get($record, 'file_number');
            $displayNumber = $this->normalizer->collapseWhitespace((string) $raw);
            $compactNumber = $this->normalizer->stripAllWhitespace((string) $raw);

            if ($compactNumber === '') {
                continue;
            }

            if ($paddingIssue = $this->checkPaddingIssue($compactNumber, $displayNumber)) {
                $issues['padding'][] = $paddingIssue + ['record_index' => $index];
            }

            if ($yearIssue = $this->checkYearIssue($compactNumber, $displayNumber)) {
                $issues['year'][] = $yearIssue + ['record_index' => $index];
            }

            if ($spacingIssue = $this->checkSpacingIssue((string) $raw, $displayNumber)) {
                $issues['spacing'][] = $spacingIssue + ['record_index' => $index];
            }

            if ($catalogIssue = $this->checkCatalogIssue((string) $raw, $displayNumber)) {
                $issues['catalog'][] = $catalogIssue + ['record_index' => $index];
            }
        }

        return array_filter($issues);
    }

    private function checkPaddingIssue(string $compactNumber, string $displayNumber): ?array
    {
        $pattern = '/^([A-Z]+(?:-[A-Z]+)*)-(\d{4})-(0+)(\d+)(\([^)]*\))?$/';

        if (!preg_match($pattern, $compactNumber, $matches)) {
            return null;
        }

        [, $prefix, $year, , $sequence, $suffix] = $matches;
        $suffix = $suffix ?? '';

        return [
            'issue_type' => 'padding',
            'file_number' => $displayNumber,
            'description' => 'File number has unnecessary leading zeros',
            'suggested_fix' => "{$prefix}-{$year}-{$sequence}{$suffix}",
            'auto_fixable' => true,
            'severity' => 'medium',
        ];
    }

    private function checkYearIssue(string $compactNumber, string $displayNumber): ?array
    {
        $pattern = '/^([A-Z]+(?:-[A-Z]+)*)-(\d{2})-(\d+)(\([^)]*\))?$/';

        if (!preg_match($pattern, $compactNumber, $matches)) {
            return null;
        }

        [, $prefix, $shortYear, $sequence, $suffix] = $matches;
        $suffix = $suffix ?? '';
        $yearInt = (int) $shortYear;
        $year = $yearInt >= 50 ? 1900 + $yearInt : 2000 + $yearInt;

        return [
            'issue_type' => 'year',
            'file_number' => $displayNumber,
            'description' => 'File number uses a 2-digit year; expected 4 digits',
            'suggested_fix' => "{$prefix}-{$year}-{$sequence}{$suffix}",
            'auto_fixable' => true,
            'severity' => 'high',
        ];
    }

    private function checkSpacingIssue(string $raw, string $displayNumber): ?array
    {
        if (!preg_match('/\s/', $raw)) {
            return null;
        }

        $trimmed = trim($raw);
        $suffix = '';
        $base = $trimmed;

        if (preg_match('/\s*(\([^)]*\))$/', $trimmed, $matches)) {
            $suffix = $matches[1];
            $base = rtrim(Str::before($trimmed, $matches[0]), '- ');
        }

        $candidate = $base;

        if (preg_match('/\s/', $base)) {
            $candidate = preg_replace('/\s+/', '-', $base);
            $candidate = preg_replace('/-+/', '-', $candidate);
            $candidate = trim($candidate, '-');
        }

        if ($candidate === '') {
            $candidate = $this->normalizer->stripAllWhitespace($trimmed);
        }

        if ($suffix !== '') {
            $candidate = trim($candidate . ' ' . trim($suffix));
        }

        return [
            'issue_type' => 'spacing',
            'file_number' => $displayNumber,
            'description' => 'File number contains inconsistent spacing',
            'suggested_fix' => $candidate,
            'auto_fixable' => true,
            'severity' => 'medium',
        ];
    }

    private function checkCatalogIssue(string $raw, string $displayNumber): ?array
    {
        $standardized = $this->normalizer->standardizeFileNumber($raw);
        if ($standardized === null) {
            return null;
        }

        $category = $this->catalog->matchFileNumber($standardized);
        if ($category !== null) {
            return null;
        }

        $code = Str::upper(Str::before($standardized, '-'));

        return [
            'issue_type' => 'catalog',
            'file_number' => $displayNumber,
            'description' => sprintf(
                'Prefix "%s" is not listed in correct_fileno catalog',
                $code ?: 'UNKNOWN'
            ),
            'suggested_fix' => null,
            'auto_fixable' => false,
            'severity' => 'high',
        ];
    }
}
