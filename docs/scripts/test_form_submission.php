<?php
// Test form submission with corporate data
$url = 'http://localhost:8000/primaryform';

echo "Testing Primary Form Submission...\n";
echo "URL: $url\n";
echo "=====================================\n";

// Corporate application data matching user's case
$formData = [
    'applicant_type' => 'Corporate',
    'company_name' => 'P S MANDRIDES',
    'rc_number' => '729',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'applicant_title' => 'Managing Director',
    'owner_email' => 'john.doe@psmandrides.com',
    'phone_number' => ['08123456789'],
    'address' => '123 Corporate Plaza, Lagos State',
    'identification_type' => 'National ID',
    'identification_no' => 'NIN12345678901',
    'land_use' => 'COMMERCIAL',
    'fileno' => 'ST-COM-2025-001',
    'submit_action' => 'save'
];

// Convert to POST data
$postData = http_build_query($formData);

// Create context for POST request
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($postData),
            'User-Agent: PHP Test Script'
        ],
        'content' => $postData,
        'timeout' => 30
    ]
]);

try {
    echo "Submitting form data...\n";
    echo "Data: " . json_encode($formData, JSON_PRETTY_PRINT) . "\n";
    echo "=====================================\n";
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "❌ Form submission failed\n";
        $error = error_get_last();
        if ($error) {
            echo "Error: " . $error['message'] . "\n";
        }
    } else {
        echo "✅ Form submission completed\n";
        echo "Response length: " . strlen($response) . " bytes\n";
        
        // Check for success indicators
        if (strpos($response, '"success"') !== false && strpos($response, 'true') !== false) {
            echo "✅ Success response detected\n";
        } elseif (strpos($response, 'success') !== false) {
            echo "✅ Success message found\n";
        }
        
        // Check for error indicators
        $errorPatterns = [
            'Undefined array key',
            'ErrorException',
            'Fatal error',
            'Parse error',
            'Undefined variable',
            'validation failed',
            'error',
            'failed'
        ];
        
        $errorsFound = [];
        foreach ($errorPatterns as $pattern) {
            if (stripos($response, $pattern) !== false) {
                $errorsFound[] = $pattern;
            }
        }
        
        if (!empty($errorsFound)) {
            echo "❌ Errors found: " . implode(', ', $errorsFound) . "\n";
            
            // Show first few lines of response for debugging
            $lines = array_slice(explode("\n", $response), 0, 10);
            echo "Response preview:\n";
            foreach ($lines as $i => $line) {
                echo ($i + 1) . ": " . trim($line) . "\n";
            }
        } else {
            echo "✅ No errors detected in response\n";
        }
        
        // Check if it's a JSON response
        $json = json_decode($response, true);
        if ($json !== null) {
            echo "✅ Valid JSON response\n";
            echo "JSON Response: " . json_encode($json, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "ℹ️  HTML response (not JSON)\n";
            // Show a snippet if it's HTML
            if (strpos($response, '<html') !== false) {
                echo "HTML response detected - likely rendered form page\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Testing Database After Submission ===\n";

// Check database for the submitted data
try {
    $pdo = new PDO(
        'sqlsrv:Server=EDMASBLD2022\SQLEXPRESS;Database=edmas_new',
        'sa',
        'klaes@2019',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->prepare("
        SELECT TOP 1 *
        FROM mother_applications 
        WHERE company_name = ? OR first_name = ?
        ORDER BY id DESC
    ");
    
    $stmt->execute(['P S MANDRIDES', 'John']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✅ Found record in database\n";
        echo "ID: " . $result['id'] . "\n";
        echo "Company Name: " . ($result['company_name'] ?? 'NULL') . "\n";
        echo "First Name: " . ($result['first_name'] ?? 'NULL') . "\n";
        echo "RC Number: " . ($result['rc_number'] ?? 'NULL') . "\n";
        echo "Email: " . ($result['owner_email'] ?? 'NULL') . "\n";
        echo "Phone: " . ($result['phone_number'] ?? 'NULL') . "\n";
        echo "Applicant Type: " . ($result['applicant_type'] ?? 'NULL') . "\n";
        
        // Count non-null fields
        $nonNullCount = 0;
        $totalFields = count($result);
        foreach ($result as $key => $value) {
            if ($value !== null && $value !== '') {
                $nonNullCount++;
            }
        }
        
        echo "Non-null fields: $nonNullCount/$totalFields\n";
        
        if ($nonNullCount > 5) {
            echo "✅ Most fields populated successfully!\n";
        } else {
            echo "❌ Many fields still NULL - form mapping issue persists\n";
        }
        
    } else {
        echo "❌ No matching record found in database\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";