<?php
/*
 * Full backup of the SQL Server `klas` database (10.50.1.1), run by the
 * "KLAES SQL Backup" scheduled task at 06:00 and 18:00 (see setup_db_backup_task.ps1).
 *
 * The BACKUP statement runs on the DB server, so the .bak lands on the DB
 * server's own disk: F:\KlaesBackups\Scheduled (C: there holds the live DB
 * files and must not be filled again - that is what stopped the old job).
 * Pages are checked WITH CHECKSUM and the result confirmed in msdb. Nothing is deleted.
 *
 * Usage: php klas_backup.php            (exit code 0 = backup written and checksummed)
 */

const BACKUP_DIR = 'F:\\KlaesBackups\\Scheduled';
const DATABASE   = 'klas';
const ENV_FILE   = __DIR__ . '/../../.env';
const LOG_FILE   = __DIR__ . '/../../storage/logs/db-backup.log';

date_default_timezone_set('Africa/Lagos');

function logLine(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
    echo $line;
}

function runStatement(PDO $pdo, string $sql): void
{
    $stmt = $pdo->query($sql);
    // BACKUP/RESTORE return informational messages as result sets; drain them all
    // so errors raised late in the batch are surfaced.
    while ($stmt->nextRowset()) {
    }
}

try {
    $env = [];
    foreach (file(ENV_FILE, FILE_IGNORE_NEW_LINES) as $l) {
        if (preg_match('/^(DB_SQLSRV_\w+)=(.*)$/', trim($l), $m)) {
            $env[$m[1]] = trim($m[2], "\"'");
        }
    }

    $pdo = new PDO(
        "sqlsrv:Server={$env['DB_SQLSRV_HOST']},{$env['DB_SQLSRV_PORT']};Database=master;TrustServerCertificate=1",
        $env['DB_SQLSRV_USERNAME'],
        $env['DB_SQLSRV_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 0]
    );

    $file = BACKUP_DIR . '\\' . DATABASE . '_' . date('Y_m_d_Hi') . '.bak';
    $started = microtime(true);
    logLine("START backup of [" . DATABASE . "] to $file");

    runStatement($pdo, "BACKUP DATABASE [" . DATABASE . "] TO DISK = N'$file' "
        . "WITH INIT, CHECKSUM, COMPRESSION, NAME = N'Full Backup of " . DATABASE . "'");
    // RESTORE VERIFYONLY needs CREATE DATABASE, which this login lacks; CHECKSUM
    // already validated every page while writing, so confirm via msdb history.
    $row = $pdo->query("SELECT TOP 1 CAST(compressed_backup_size/1048576 AS int) mb, has_backup_checksums, is_damaged
        FROM msdb.dbo.backupset b JOIN msdb.dbo.backupmediafamily m ON m.media_set_id = b.media_set_id
        WHERE m.physical_device_name = N'$file' ORDER BY b.backup_finish_date DESC")->fetch(PDO::FETCH_ASSOC);

    if (!$row || !$row['has_backup_checksums'] || $row['is_damaged']) {
        throw new RuntimeException("backup of $file not recorded as complete and checksummed in msdb");
    }

    logLine(sprintf('OK   %s checksummed, %s MB, %.0f s', $file, $row['mb'], microtime(true) - $started));
    exit(0);
} catch (Throwable $e) {
    logLine('FAIL ' . $e->getMessage());
    exit(1);
}
