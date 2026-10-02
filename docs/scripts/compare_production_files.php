<?php
/**
 * File Comparison Script for Production Deployment
 * 
 * This script helps compare critical files between localhost and production
 * to identify why land use CSV upload works on localhost but not production.
 */

echo "<h2>Production vs Localhost File Comparison</h2>";

// List of critical files for land use functionality
$criticalFiles = [
    'app/Http/Controllers/PrimaryApplicationController.php',
    'app/Helper/SectionalTitleHelper.php', 
    'resources/views/admin/primaryform/step4-buyers.blade.php',
    'public/js/primaryform/buyers.js',
    'public/js/primaryform/buyers-step4.js',
    'public/js/primaryform/buyers-form.js'
];

echo "<h3>File Checksums and Sizes</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>File</th><th>Exists</th><th>Size (bytes)</th><th>MD5 Hash</th><th>Last Modified</th></tr>";

foreach ($criticalFiles as $file) {
    $fullPath = base_path($file);
    echo "<tr>";
    echo "<td>" . $file . "</td>";
    
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        $hash = md5_file($fullPath);
        $modified = date('Y-m-d H:i:s', filemtime($fullPath));
        
        echo "<td style='color: green;'>✅ Yes</td>";
        echo "<td>" . number_format($size) . "</td>";
        echo "<td><code>" . $hash . "</code></td>";
        echo "<td>" . $modified . "</td>";
    } else {
        echo "<td style='color: red;'>❌ No</td>";
        echo "<td colspan='3'>File not found</td>";
    }
    echo "</tr>";
}

echo "</table>";

// Specific content checks
echo "<h3>Content Verification</h3>";

// 1. Check buyers.js for land use functionality
$buyersJsPath = public_path('js/primaryform/buyers.js');
if (file_exists($buyersJsPath)) {
    $jsContent = file_get_contents($buyersJsPath);
    
    echo "<h4>buyers.js Analysis:</h4>";
    echo "<ul>";
    
    // Check for specific land use code patterns
    $patterns = [
        "'land use'" => "Land use header check in CSV parsing",
        '"land use"' => "Land use header check (double quotes)",
        'landUse' => "Land use variable usage", 
        'Land Use' => "Land use text references",
        'buyer.landUse' => "Land use assignment in buyer object",
        'landUseSelect' => "Land use select element handling"
    ];
    
    foreach ($patterns as $pattern => $description) {
        $count = substr_count($jsContent, $pattern);
        $status = $count > 0 ? "✅" : "❌";
        echo "<li>{$status} {$description}: {$count} occurrences</li>";
    }
    
    echo "</ul>";
    
    // Extract the CSV parsing section
    $csvParsingStart = strpos($jsContent, "case 'land use':");
    if ($csvParsingStart !== false) {
        $csvParsingEnd = strpos($jsContent, 'break;', $csvParsingStart) + 6;
        $csvParsingCode = substr($jsContent, $csvParsingStart, $csvParsingEnd - $csvParsingStart);
        echo "<h4>Land Use CSV Parsing Code:</h4>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($csvParsingCode) . "</pre>";
    } else {
        echo "<h4 style='color: red;'>❌ Land Use CSV Parsing Code NOT FOUND</h4>";
    }
    
} else {
    echo "<h4 style='color: red;'>❌ buyers.js file not found</h4>";
}

// 2. Check controller for land use
$controllerPath = app_path('Http/Controllers/PrimaryApplicationController.php');
if (file_exists($controllerPath)) {
    $controllerContent = file_get_contents($controllerPath);
    
    echo "<h4>PrimaryApplicationController.php Analysis:</h4>";
    echo "<ul>";
    
    $patterns = [
        'Land Use' => "Land Use header in CSV template",
        'landUse' => "landUse processing in backend",
        'land_use' => "land_use database field references"
    ];
    
    foreach ($patterns as $pattern => $description) {
        $count = substr_count($controllerContent, $pattern);
        $status = $count > 0 ? "✅" : "❌";
        echo "<li>{$status} {$description}: {$count} occurrences</li>";
    }
    
    echo "</ul>";
}

// 3. Browser cache detection help
echo "<h3>Browser Cache Detection</h3>";
echo "<p>Add this JavaScript to your page to detect cache issues:</p>";
echo "<textarea style='width: 100%; height: 150px;'>
// Detect if JavaScript is cached
const jsTimestamp = '" . filemtime($buyersJsPath) . "';
console.log('Expected JS timestamp:', jsTimestamp);
console.log('Current timestamp:', Math.floor(Date.now() / 1000));

// Force cache bypass
const script = document.createElement('script');
script.src = '/js/primaryform/buyers.js?' + Date.now();
document.head.appendChild(script);

console.log('Forced fresh script load');
</textarea>";

// 4. Quick fix commands
echo "<h3>Quick Fix Commands for Production</h3>";
echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc;'>";
echo "<h4>On Production Server:</h4>";
echo "<pre>";
echo "# Clear all caches\n";
echo "php artisan cache:clear\n";
echo "php artisan config:clear\n";
echo "php artisan view:clear\n";
echo "php artisan route:clear\n\n";

echo "# Force browser cache clear (add to .htaccess or nginx)\n";
echo "# For Apache (.htaccess):\n";
echo "Header set Cache-Control \"no-cache, no-store, must-revalidate\"\n";
echo "Header set Pragma \"no-cache\"\n";
echo "Header set Expires 0\n\n";

echo "# Check file permissions\n";
echo "chmod 644 public/js/primaryform/buyers.js\n";
echo "chown www-data:www-data public/js/primaryform/buyers.js\n";
echo "</pre>";
echo "</div>";

echo "<h3>File Upload Checklist</h3>";
echo "<p>Ensure these files are uploaded to production with EXACT same content:</p>";
echo "<ol>";
foreach ($criticalFiles as $file) {
    echo "<li><code>{$file}</code></li>";
}
echo "</ol>";

// Generate a simple test URL
echo "<h3>Test URLs</h3>";
echo "<p>Test these URLs on both localhost and production:</p>";
echo "<ul>";
echo "<li><a href='/test_land_use_csv.html' target='_blank'>Land Use CSV Test Page</a></li>";
echo "<li><a href='/debug_land_use_csv.php' target='_blank'>Debug Script</a></li>";
echo "</ul>";

?>
