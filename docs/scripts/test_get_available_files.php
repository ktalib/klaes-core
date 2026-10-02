<?php

use Illuminate\Http\Request;

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Request::create('/printlabel/api/files', 'GET', [
    'per_page' => 5,
]);

// Simulate authenticated user (first user)
Auth::loginUsingId(1);

$response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";

$content = $response->getContent();

if ($response->headers->get('Content-Type') === 'application/json') {
    $data = json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "Success: " . ($data['success'] ? 'yes' : 'no') . "\n";
        echo "Records: " . count($data['data'] ?? []) . "\n";
        if (!empty($data['data'])) {
            echo "First record shelf values:\n";
            $first = $data['data'][0];
            echo '  shelf_location: ' . ($first['shelf_location'] ?? 'null') . "\n";
            echo '  grouping_shelf_rack: ' . ($first['grouping_shelf_rack'] ?? 'null') . "\n";
        }
    } else {
        echo "Response (raw):\n" . $content . "\n";
    }
} else {
    echo "Response (raw):\n" . $content . "\n";
}
