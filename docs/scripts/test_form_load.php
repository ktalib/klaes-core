<?php
// Test form loading without errors
$url = 'http://localhost:8000/primaryform';

echo "Testing Primary Form Load...\n";
echo "URL: $url\n";
echo "=====================================\n";

// Create a stream context with timeout
$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'method' => 'GET',
        'header' => [
            'User-Agent: PHP Test Script',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
        ]
    ]
]);

try {
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "❌ Failed to load form page\n";
        print_r(error_get_last());
    } else {
        echo "✅ Form page loaded successfully\n";
        echo "Response length: " . strlen($response) . " bytes\n";
        
        // Check for specific error messages
        if (strpos($response, 'Undefined array key') !== false) {
            echo "❌ Found 'Undefined array key' error in response\n";
            
            // Extract the error context
            $lines = explode("\n", $response);
            foreach ($lines as $lineNum => $line) {
                if (strpos($line, 'Undefined array key') !== false) {
                    echo "Error at line " . ($lineNum + 1) . ": " . trim($line) . "\n";
                    // Show context around the error
                    for ($i = max(0, $lineNum - 2); $i <= min(count($lines) - 1, $lineNum + 2); $i++) {
                        $marker = ($i === $lineNum) ? '>>> ' : '    ';
                        echo $marker . ($i + 1) . ": " . trim($lines[$i]) . "\n";
                    }
                    break;
                }
            }
        } else {
            echo "✅ No 'Undefined array key' errors found\n";
        }
        
        // Check for other common errors
        $errorPatterns = [
            'ErrorException',
            'Fatal error',
            'Parse error',
            'Undefined variable',
            'Call to undefined',
            'Class not found'
        ];
        
        $errorsFound = [];
        foreach ($errorPatterns as $pattern) {
            if (strpos($response, $pattern) !== false) {
                $errorsFound[] = $pattern;
            }
        }
        
        if (!empty($errorsFound)) {
            echo "❌ Found other errors: " . implode(', ', $errorsFound) . "\n";
        } else {
            echo "✅ No common PHP errors found\n";
        }
        
        // Check if form elements are present
        $requiredElements = [
            '<form',
            'name="applicant_type"',
            'name="first_name"',
            'name="company_name"',
            'id="applicant-section"'
        ];
        
        $elementsFound = 0;
        foreach ($requiredElements as $element) {
            if (strpos($response, $element) !== false) {
                $elementsFound++;
            }
        }
        
        echo "Form elements found: $elementsFound/" . count($requiredElements) . "\n";
        
        if ($elementsFound === count($requiredElements)) {
            echo "✅ All required form elements present\n";
        } else {
            echo "❌ Some form elements missing\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";