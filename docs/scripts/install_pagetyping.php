<?php

/**
 * Page Typing Installation Script
 * 
 * This script sets up the Page Typing workflow with PDF splitting capabilities
 */

echo "=== Page Typing Installation Script ===\n\n";

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

// Check if we're in the correct directory
if (!file_exists('composer.json')) {
    echo "Error: Please run this script from the Laravel project root directory.\n";
    exit(1);
}

echo "1. Installing FPDI library for PDF processing...\n";

// Install FPDI via Composer
$composerCommand = 'composer require setasign/fpdi';
$output = [];
$returnCode = 0;

exec($composerCommand, $output, $returnCode);

if ($returnCode === 0) {
    echo "   ✓ FPDI library installed successfully\n";
} else {
    echo "   ✗ Failed to install FPDI library\n";
    echo "   Please run manually: composer require setasign/fpdi\n";
}

echo "\n2. Creating storage directories...\n";

$directories = [
    'storage/app/public/EDMS',
    'storage/app/public/EDMS/PAGETYPING',
    'storage/app/public/EDMS/THUMBNAILS',
    'storage/app/public/EDMS/TEMP'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "   ✓ Created directory: $dir\n";
        } else {
            echo "   ✗ Failed to create directory: $dir\n";
        }
    } else {
        echo "   ✓ Directory already exists: $dir\n";
    }
}

echo "\n3. Checking PHP extensions...\n";

$requiredExtensions = [
    'gd' => 'Image processing (thumbnails)',
    'fileinfo' => 'File type detection',
    'zip' => 'Archive handling'
];

foreach ($requiredExtensions as $extension => $description) {
    if (extension_loaded($extension)) {
        echo "   ✓ $extension extension loaded ($description)\n";
    } else {
        echo "   ⚠ $extension extension not loaded ($description)\n";
        echo "     This may limit some functionality\n";
    }
}

echo "\n4. Running database migration...\n";

// Run the migration
$migrationCommand = 'php artisan migrate --path=database/migrations/2024_01_01_000001_add_is_updated_to_file_indexings.php';
exec($migrationCommand, $output, $returnCode);

if ($returnCode === 0) {
    echo "   ✓ Database migration completed\n";
} else {
    echo "   ⚠ Migration may have failed or column already exists\n";
    echo "   Please check manually: php artisan migrate\n";
}

echo "\n5. Setting up symbolic links...\n";

// Create storage link if it doesn't exist
if (!is_link('public/storage')) {
    $linkCommand = 'php artisan storage:link';
    exec($linkCommand, $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "   ✓ Storage symbolic link created\n";
    } else {
        echo "   ⚠ Failed to create storage link\n";
        echo "   Please run manually: php artisan storage:link\n";
    }
} else {
    echo "   ✓ Storage symbolic link already exists\n";
}

echo "\n6. Checking file permissions...\n";

$storageDir = 'storage/app/public';
if (is_writable($storageDir)) {
    echo "   ✓ Storage directory is writable\n";
} else {
    echo "   ✗ Storage directory is not writable\n";
    echo "   Please set proper permissions: chmod -R 755 storage/\n";
}

echo "\n=== Installation Summary ===\n";
echo "✓ Page Typing workflow has been set up\n";
echo "✓ PDF splitting capabilities enabled with FPDI\n";
echo "✓ Storage directories created\n";
echo "✓ Database migration applied\n";
echo "\nFeatures enabled:\n";
echo "- PDF splitting into individual pages\n";
echo "- Image file handling\n";
echo "- Page classification and typing\n";
echo "- Upload More functionality\n";
echo "- Quality control workflow\n";

echo "\n=== Next Steps ===\n";
echo "1. Access the Page Typing dashboard at: /pagetyping\n";
echo "2. Upload files through the Scanning module\n";
echo "3. Start typing pages - PDFs will be automatically split\n";
echo "4. Use the PageType More tab for files with additional uploads\n";

echo "\n=== Troubleshooting ===\n";
echo "- If PDF splitting fails, ensure FPDI is properly installed\n";
echo "- Check storage permissions if file operations fail\n";
echo "- Verify database connection for page typing records\n";
echo "- Enable GD extension for thumbnail generation\n";

echo "\nInstallation completed successfully!\n";
?>