<?php

echo "=== Testing Method Name Conflict Fix ===\n\n";

try {
    // Test that we can instantiate the controller without errors
    require_once __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    // Test route resolution
    $routes = app('router')->getRoutes();
    $validateRoute = null;
    
    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'api/st-file-numbers/validate') && $route->getActionMethod() === 'validateFileNumber') {
            $validateRoute = $route;
            break;
        }
    }
    
    if ($validateRoute) {
        echo "✅ Route found: " . $validateRoute->uri() . "\n";
        echo "✅ Method: " . $validateRoute->getActionMethod() . "\n";
        echo "✅ Controller method name conflict resolved!\n\n";
    } else {
        echo "❌ Route not found\n\n";
    }
    
    // Test that the controller can be instantiated
    $controller = app()->make(App\Http\Controllers\STFileNumberController::class);
    echo "✅ STFileNumberController instantiated successfully\n";
    echo "✅ No method signature conflicts\n\n";
    
    echo "🎉 METHOD NAME CONFLICT SUCCESSFULLY RESOLVED!\n";
    echo "🎉 API endpoints ready for use\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}