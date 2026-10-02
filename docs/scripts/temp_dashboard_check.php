<?php
require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

session()->start();
session()->put('errors', new Illuminate\Support\MessageBag());
view()->share('errors', session('errors'));

Illuminate\Support\Facades\Auth::loginUsingId(1);

$controller = $app->make(App\Http\Controllers\GroupingAnalyticsController::class);
$start = microtime(true);
$view = $controller->dashboard();
$view->render();

echo 'render_time=' . round(microtime(true) - $start, 4) . PHP_EOL;
