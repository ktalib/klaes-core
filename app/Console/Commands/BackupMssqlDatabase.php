<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackupMssqlDatabase extends Command
{
    protected $signature = 'backup:mssql {--force-cleanup : Force cleanup of old backups}';

    protected $description = 'Backup MSSQL database via direct connection and clean up old backups';

    protected $backupPath = '\\\\DC-01\\dbBackup';
    protected $retentionDays = 7;

    public function handle(): int
    {
        try {
            // Get MSSQL database name
            $database = env('DB_SQLSRV_DATABASE', 'klas');

            // Ensure backup directory exists
            if (!is_dir($this->backupPath)) {
                if (!@mkdir($this->backupPath, 0777, true)) {
                    $this->error("Failed to create backup directory: {$this->backupPath}");
                    Log::error("Backup failed: Cannot create directory {$this->backupPath}");
                    return self::FAILURE;
                }
            }

            // Generate backup filename with timestamp
            $timestamp = Carbon::now()->format('Y-m-d_His');
            $backupFile = "{$this->backupPath}\\{$database}_{$timestamp}.bak";

            // Build T-SQL BACKUP command - execute on remote MSSQL server
            $sqlCommand = "BACKUP DATABASE [{$database}] TO DISK = N'{$backupFile}' WITH INIT, NAME = N'Full Backup of {$database}', STATS = 10, CHECKSUM;";

            $this->line("Starting MSSQL database backup...");
            $this->line("Database: {$database}");
            $this->line("Backup file: {$backupFile}");
            $this->line("(Backup will be created on remote SQL Server)");

            // Execute backup via PHP sqlsrv extension
            try {
                DB::connection('sqlsrv')->statement($sqlCommand);
            } catch (Exception $e) {
                $this->error("Failed to execute backup command: " . $e->getMessage());
                Log::error("MSSQL Backup failed: " . $e->getMessage());
                return self::FAILURE;
            }

            $this->info("✓ Database backup completed successfully");
            $this->info("  File: {$backupFile}");
            $this->info("  Location: Remote SQL Server (10.50.1.1)");

            Log::info("MSSQL Backup completed: {$backupFile}");

            // Note: Cannot verify file existence since it's on remote server
            // Cleanup logic also disabled for remote backups
            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Backup error: " . $e->getMessage());
            Log::error("MSSQL Backup exception: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Clean up backup files older than retention period
     */
    protected function cleanupOldBackups(string $database): void
    {
        try {
            $cutoffDate = Carbon::now()->subDays($this->retentionDays);
            $pattern = "{$this->backupPath}\\{$database}_*.bak";
            
            $files = glob($pattern);
            if (!$files) {
                return;
            }

            $deletedCount = 0;
            foreach ($files as $file) {
                $fileTime = filemtime($file);
                if ($fileTime === false) {
                    continue;
                }

                $fileDate = Carbon::createFromTimestamp($fileTime);
                
                if ($fileDate->lessThan($cutoffDate)) {
                    if (@unlink($file)) {
                        $deletedCount++;
                        Log::info("Cleanup: Deleted old backup {$file}");
                    }
                }
            }

            if ($deletedCount > 0) {
                $this->info("✓ Cleanup: Removed {$deletedCount} backup(s) older than {$this->retentionDays} days");
            }
        } catch (\Exception $e) {
            Log::warning("Backup cleanup error: " . $e->getMessage());
        }
    }

    /**
     * Format bytes to human-readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
