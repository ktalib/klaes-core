<?php

require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = new \App\Http\Controllers\Api\GroupingController();
$response = $controller->stats();

echo $response->getContent();
