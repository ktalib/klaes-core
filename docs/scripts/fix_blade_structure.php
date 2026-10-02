<?php

$filePath = 'resources/views/sectionaltitling/viewrecorddetail_sub.blade.php';
$content = file_get_contents($filePath);
$lines = explode("\n", $content);

$stack = [];
$issues = [];

foreach ($lines as $lineNum => $line) {
    $trimmed = trim($line);
    $lineNumber = $lineNum + 1;
    
    // Track opening directives
    if (preg_match('/@if\s*\(/', $trimmed)) {
        $stack[] = ['type' => 'if', 'line' => $lineNumber];
        echo "Line $lineNumber: OPEN @if (Stack depth: " . count($stack) . ")\n";
    } elseif (preg_match('/@elseif\s*\(/', $trimmed)) {
        if (empty($stack) || end($stack)['type'] !== 'if') {
            $issues[] = "Line $lineNumber: @elseif without matching @if";
            echo "Line $lineNumber: ❌ ORPHANED @elseif\n";
        } else {
            echo "Line $lineNumber: @elseif (Stack depth: " . count($stack) . ")\n";
        }
    } elseif (preg_match('/@else\s*$/', $trimmed)) {
        if (empty($stack) || end($stack)['type'] !== 'if') {
            $issues[] = "Line $lineNumber: @else without matching @if";
            echo "Line $lineNumber: ❌ ORPHANED @else\n";
        } else {
            echo "Line $lineNumber: @else (Stack depth: " . count($stack) . ")\n";
        }
    } elseif (preg_match('/@endif\s*$/', $trimmed)) {
        if (empty($stack) || end($stack)['type'] !== 'if') {
            $issues[] = "Line $lineNumber: @endif without matching @if";
            echo "Line $lineNumber: ❌ EXTRA @endif (Stack depth: " . count($stack) . ")\n";
        } else {
            $closed = array_pop($stack);
            echo "Line $lineNumber: CLOSE @endif (was opened at line {$closed['line']}, Stack depth: " . count($stack) . ")\n";
        }
    }
}

echo "\n=== ISSUES FOUND ===\n";
foreach ($issues as $issue) {
    echo "❌ $issue\n";
}

if (!empty($stack)) {
    echo "\n=== UNCLOSED @if DIRECTIVES ===\n";
    foreach ($stack as $unclosed) {
        echo "❌ @if opened at line {$unclosed['line']} never closed\n";
    }
}

echo "\nFinal stack depth: " . count($stack) . "\n";
echo "Total issues: " . (count($issues) + count($stack)) . "\n";