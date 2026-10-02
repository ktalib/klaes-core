<?php
// This is a simple test script to verify the API is working
$url = 'http://localhost/gisedms/st_transfer/debug';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
echo json_encode([
    'status' => $status,
    'response' => json_decode($response, true),
    'raw_response' => $response
], JSON_PRETTY_PRINT);

// Also write to a log file
file_put_contents('api-test-log.txt', date('Y-m-d H:i:s') . "\n" . 
    "Status: $status\n" . 
    "Response: $response\n\n", 
    FILE_APPEND);
