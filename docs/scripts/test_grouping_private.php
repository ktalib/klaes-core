<?php

require_once __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = new \App\Http\Controllers\Api\GroupingController();

function callPrivate($object, string $methodName) {
    $ref = new ReflectionClass($object);
    $method = $ref->getMethod($methodName);
    $method->setAccessible(true);
    $start = microtime(true);
    try {
        $result = $method->invoke($object);
        $duration = microtime(true) - $start;
        $type = gettype($result);
        $count = is_array($result) ? count($result) : (is_object($result) ? 1 : 0);
        echo sprintf("%s succeeded in %.3f seconds (type: %s, count: %d)\n", $methodName, $duration, $type, $count);
    } catch (Throwable $e) {
        $duration = microtime(true) - $start;
        echo sprintf("%s failed in %.3f seconds: %s\n", $methodName, $duration, $e->getMessage());
    }
}

callPrivate($controller, 'getQuickStats');
callPrivate($controller, 'getQuickLandUse');
callPrivate($controller, 'getQuickActivity');
