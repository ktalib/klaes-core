<?php

$file = 'resources/views/sectionaltitling/sub_application.blade.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

$stack = [];
$errors = [];

foreach ($lines as $lineNum => $line) {
    $lineNumber = $lineNum + 1;
    
    // Check for @if, @unless, @foreach, @for, @while
    if (preg_match('/@(if|unless|foreach|for|while|switch)\s*\(/', $line, $matches)) {
        $stack[] = [
            'type' => $matches[1],
            'line' => $lineNumber,
            'content' => trim($line)
        ];
        echo "Line $lineNumber: OPEN {$matches[1]} - Stack depth: " . count($stack) . "\n";
    }
    
    // Check for @else, @elseif, @elseunless
    if (preg_match('/@(else|elseif|elseunless)/', $line, $matches)) {
        if (empty($stack)) {
            $errors[] = "Line $lineNumber: @{$matches[1]} without matching @if - " . trim($line);
            echo "Line $lineNumber: ERROR - @{$matches[1]} without matching @if\n";
        } else {
            echo "Line $lineNumber: {$matches[1]} for " . end($stack)['type'] . " from line " . end($stack)['line'] . "\n";
        }
    }
    
    // Check for @endif, @endunless, @endforeach, @endfor, @endwhile
    if (preg_match('/@(endif|endunless|endforeach|endfor|endwhile|endswitch)/', $line, $matches)) {
        if (empty($stack)) {
            $errors[] = "Line $lineNumber: @{$matches[1]} without matching opening - " . trim($line);
            echo "Line $lineNumber: ERROR - @{$matches[1]} without matching opening\n";
        } else {
            $popped = array_pop($stack);
            echo "Line $lineNumber: CLOSE {$matches[1]} (opened at line {$popped['line']}) - Stack depth: " . count($stack) . "\n";
        }
    }
}

echo "\n=== FINAL ANALYSIS ===\n";
if (!empty($stack)) {
    echo "UNCLOSED DIRECTIVES:\n";
    foreach ($stack as $item) {
        echo "  Line {$item['line']}: @{$item['type']} - {$item['content']}\n";
    }
} else {
    echo "All directives properly closed.\n";
}

if (!empty($errors)) {
    echo "\nERRORS FOUND:\n";
    foreach ($errors as $error) {
        echo "  $error\n";
    }
}

echo "\nTotal opening directives: " . (count($stack) > 0 ? "MISMATCHED" : "BALANCED") . "\n";
