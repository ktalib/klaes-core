<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';

$endpoint = $argv[1] ?? '/api/grouping-analytics/stats';

$request = Illuminate\Http\Request::create($endpoint, 'GET');
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);

$status = $response->getStatusCode();
$content = $response->getContent();

echo "Status: {$status}\n";
echo "Body: {$content}\n";

$kernel->terminate($request, $response);
