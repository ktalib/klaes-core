<?php
// Test form submission with CSRF token
echo "Getting CSRF token and testing submission...\n";
echo "=====================================\n";

// First, get the form page to extract CSRF token
$getUrl = 'http://localhost:8000/primaryform';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 30
    ]
]);

$formPage = file_get_contents($getUrl, false, $context);
if ($formPage === false) {
    echo "❌ Could not load form page\n";
    exit;
}

// Extract CSRF token
preg_match('/<meta name="csrf-token" content="([^"]+)"/', $formPage, $matches);
if (!isset($matches[1])) {
    echo "❌ Could not find CSRF token\n";
    exit;
}

$csrfToken = $matches[1];
echo "✅ CSRF token found: " . substr($csrfToken, 0, 10) . "...\n";

// Corporate application data
$formData = [
    '_token' => $csrfToken,
    'applicant_type' => 'Corporate',
    'company_name' => 'P S MANDRIDES',
    'rc_number' => '729',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'applicant_title' => 'Managing Director',
    'owner_email' => 'john.doe@psmandrides.com',
    'phone_number' => '08123456789',
    'address' => '123 Corporate Plaza, Lagos State',
    'identification_type' => 'National ID',
    'identification_no' => 'NIN12345678901',
    'land_use' => 'COMMERCIAL',
    'fileno' => 'ST-COM-2025-001',
    'submit_action' => 'save'
];

$postData = http_build_query($formData);

// Create context for POST request with proper headers
$postContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($postData),
            'X-CSRF-TOKEN: ' . $csrfToken,
            'User-Agent: PHP Test Script',
            'Accept: application/json, text/html'
        ],
        'content' => $postData,
        'timeout' => 30
    ]
]);

echo "Submitting form with CSRF token...\n";

$response = file_get_contents($getUrl, false, $postContext);

if ($response === false) {
    echo "❌ Form submission failed\n";
    $error = error_get_last();
    if ($error) {
        echo "Error: " . $error['message'] . "\n";
    }
} else {
    echo "✅ Form submission completed\n";
    echo "Response length: " . strlen($response) . " bytes\n";
    
    // Check if it's JSON
    $json = json_decode($response, true);
    if ($json !== null) {
        echo "✅ JSON Response received:\n";
        echo json_encode($json, JSON_PRETTY_PRINT) . "\n";
        
        if (isset($json['success']) && $json['success']) {
            echo "✅ Form submission successful!\n";
        } else {
            echo "❌ Form submission failed\n";
            if (isset($json['message'])) {
                echo "Error: " . $json['message'] . "\n";
            }
        }
    } else {
        echo "HTML response - checking for success indicators...\n";
        
        if (strpos($response, 'success') !== false) {
            echo "✅ Success message found in response\n";
        }
        
        if (strpos($response, 'error') !== false || strpos($response, 'Error') !== false) {
            echo "❌ Error message found in response\n";
        }
    }
}

echo "\n=== Testing Database (Simplified) ===\n";

// Check if we can verify the submission worked by checking our verification script
exec('php verify_database.php', $output, $returnCode);

if ($returnCode === 0) {
    echo "Database verification output:\n";
    foreach ($output as $line) {
        echo $line . "\n";
    }
} else {
    echo "❌ Database verification script failed\n";
}

echo "\n=== Test Complete ===\n";