<?php

require_once __DIR__ . '/vendor/autoload.php';

use RachidLaasri\LaravelInstaller\Helpers\MigrationsHelper;

class MigrationDebugger
{
    use MigrationsHelper;
    
    public function debugMigrations()
    {
        echo "=== Migration Debug Information ===\n";
        
        try {
            $directoryMigrations = $this->getMigrations();
            echo "Directory migrations count: " . count($directoryMigrations) . "\n";
            echo "Directory migrations:\n";
            foreach($directoryMigrations as $migration) {
                echo "  - " . $migration . "\n";
            }
            
            $databaseMigrations = $this->getExecutedMigrations();
            echo "\nDatabase migrations count: " . count($databaseMigrations) . "\n";
            echo "Database migrations:\n";
            foreach($databaseMigrations as $migration) {
                echo "  - " . $migration . "\n";
            }
            
            $difference = count($directoryMigrations) - count($databaseMigrations);
            echo "\nDifference: " . $difference . "\n";
            
            if($difference > 0) {
                echo "\nMigrations in directory but not in database:\n";
                $missing = array_diff($directoryMigrations, $databaseMigrations);
                foreach($missing as $migration) {
                    echo "  - " . $migration . "\n";
                }
            }
            
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            echo "Trace: " . $e->getTraceAsString() . "\n";
        }
    }
}

$debugger = new MigrationDebugger();
$debugger->debugMigrations();