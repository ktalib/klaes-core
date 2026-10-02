<?php

// Debug script to test file number variant generation
// Run from Laravel tinker or directly

function buildFileNumberVariants($fileNumber) {
    $trimmed = trim($fileNumber);
    if ($trimmed === '') {
        return [];
    }

    $variants = [$trimmed];

    $upper = strtoupper($trimmed);
    $lower = strtolower($trimmed);

    $variants[] = $upper;
    $variants[] = $lower;

    $collapsed = preg_replace('/\s+/', '', $trimmed);
    if ($collapsed && $collapsed !== $trimmed) {
        $variants[] = $collapsed;
    }

    $collapsedUpper = preg_replace('/\s+/', '', $upper);
    if ($collapsedUpper && $collapsedUpper !== $upper) {
        $variants[] = $collapsedUpper;
    }

    $collapsedLower = preg_replace('/\s+/', '', $lower);
    if ($collapsedLower && $collapsedLower !== $lower) {
        $variants[] = $collapsedLower;
    }

    $dashToSlash = str_replace('-', '/', $upper);
    if ($dashToSlash !== $upper) {
        $variants[] = $dashToSlash;
    }

    $slashToDash = str_replace('/', '-', $upper);
    if ($slashToDash !== $upper) {
        $variants[] = $slashToDash;
    }

    $segments = preg_split('/[-\/]/', $upper);
    if ($segments !== false && count($segments) >= 2) {
        $serialSegment = array_pop($segments);
        $serialDigits = preg_replace('/\D+/', '', $serialSegment ?? '');

        if ($serialDigits !== '') {
            $serialBase = ltrim($serialDigits, '0');
            if ($serialBase === '') {
                $serialBase = '0';
            }

            $baseLengths = [
                strlen($serialSegment ?? ''),
                strlen($serialDigits),
                2,
                3,
                4,
                5,
            ];

            $dynamicMaxLength = max(6, ($baseLengths[0] ?? 0) + 4, ($baseLengths[1] ?? 0) + 4);
            $dynamicRange = range(2, $dynamicMaxLength);

            $lengthCandidates = array_filter(array_unique(array_merge($baseLengths, $dynamicRange)));

            $prefixHyphen = implode('-', $segments);
            $prefixSlash = implode('/', $segments);

            $delimiters = [];
            if ($prefixHyphen !== '') {
                $delimiters['-'] = $prefixHyphen;
            }
            if ($prefixSlash !== '' && $prefixSlash !== $prefixHyphen) {
                $delimiters['/'] = $prefixSlash;
            }

            foreach ($delimiters as $delimiter => $prefix) {
                $variants[] = $prefix . $delimiter . $serialBase;
                foreach ($lengthCandidates as $length) {
                    $variants[] = $prefix . $delimiter . str_pad($serialBase, $length, '0', STR_PAD_LEFT);
                }
            }
        }
    }

    // Normalize variants by adding uppercase counterparts, then filter duplicates/empties.
    $variants = array_merge($variants, array_map('strtoupper', $variants));

    return array_values(array_unique(array_filter($variants, fn ($value) => $value !== null && $value !== '')));
}

// Test the variant generation
$testNumbers = [
    'CON-RES-2025-6',
    'CON-COM-2010-258',
    'CON-RES-2025-000006',
];

echo "File Number Variant Generation Test\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($testNumbers as $fileNum) {
    echo "Input: $fileNum\n";
    $variants = buildFileNumberVariants($fileNum);
    echo "Generated " . count($variants) . " variants:\n";
    foreach ($variants as $idx => $v) {
        echo "  " . ($idx + 1) . ". $v\n";
    }
    echo "\n";
}
$mlsFileNumbers = [
$foreach ($testNumbers as $fileNum) {
    echo "Input: $fileNum\n";
    $variants = buildFileNumberVariants($fileNum);
    echo "Generated " . count($variants) . " variants:\n";
    foreach ($variants as $idx => $v) {
        echo "  " . ($idx + 1) . ". $v\n";
    }
    echo "\n";
];

?>
