param([switch]$ServerOnly)
$ErrorActionPreference = 'Stop'
$runRoot = (Get-Content -LiteralPath (Join-Path $PSScriptRoot 'active-backup-root.txt')).Trim()
$connection = New-Object System.Data.SqlClient.SqlConnection 'Server=tcp:10.50.1.1,1433;Database=master;Integrated Security=True;TrustServerCertificate=True;Connect Timeout=15'
$state = [ordered]@{database='klas'; started_at=(Get-Date).ToString('o'); backup_complete=$false; restore_verified=$false; test_restore_complete=$false}
function Save-State { $state | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $runRoot 'database-backup-verified.json') -Encoding UTF8 }
function Query-Sql([string]$sql) {
    $command=$connection.CreateCommand(); $command.CommandTimeout=0; $command.CommandText=$sql
    $adapter=New-Object System.Data.SqlClient.SqlDataAdapter $command
    $table=New-Object System.Data.DataTable
    [void]$adapter.Fill($table)
    return ,$table
}
function Execute-Sql([string]$sql) {
    $command=$connection.CreateCommand(); $command.CommandTimeout=0; $command.CommandText=$sql
    [void]$command.ExecuteNonQuery()
}
try {
    $connection.Open()
    $server=Query-Sql "SELECT CAST(SERVERPROPERTY('InstanceDefaultBackupPath') AS nvarchar(500)) backup_path, CAST(SERVERPROPERTY('InstanceDefaultDataPath') AS nvarchar(500)) data_path"
    $drives=Query-Sql 'EXEC master.dbo.xp_fixeddrives'
    $remoteF=$drives.Rows | Where-Object { $_.drive -eq 'F' }
    if(!$remoteF -or [long]$remoteF.'MB free' -lt 102400) { throw 'Insufficient remote F: free space for backup and test restore.' }
    $tag=Split-Path -Leaf $runRoot
    $remoteRoot='F:\KlaesBackups\'+$tag
    New-Item -ItemType Directory -Force -Path ('\\10.50.1.1\F$\KlaesBackups\'+$tag) | Out-Null
    $backupPath=Join-Path $remoteRoot ('klas_verified_'+(Get-Date -Format 'yyyyMMdd_HHmmss')+'.bak')
    $state.server_backup_path=$backupPath
    Save-State
    Execute-Sql ("BACKUP DATABASE [klas] TO DISK = N'"+$backupPath.Replace("'","''")+"' WITH COPY_ONLY, CHECKSUM")
    $state.backup_complete=$true; $state.backup_finished_at=(Get-Date).ToString('o'); Save-State
    Write-Output 'Full database backup completed. Starting restore verification.'
    Execute-Sql ("RESTORE VERIFYONLY FROM DISK = N'"+$backupPath.Replace("'","''")+"' WITH CHECKSUM")
    $state.restore_verified=$true; Save-State
    Write-Output 'Restore verification passed. Starting isolated test restore.'
    $restoreName='klas_edms_restore_'+(Get-Date -Format 'yyyyMMdd_HHmmss')
    $exists=Query-Sql ("SELECT DB_ID('"+$restoreName+"') id")
    if($exists.Rows[0].id -isnot [DBNull]) { throw 'Test-restore database already exists.' }
    $files=Query-Sql ("RESTORE FILELISTONLY FROM DISK = N'"+$backupPath.Replace("'","''")+"'")
    $moves=New-Object System.Collections.Generic.List[string]
    foreach($file in $files.Rows) {
        $extension=if($file.Type -eq 'L'){'.ldf'}else{'.mdf'}
        $destination=Join-Path $remoteRoot ($restoreName+'_'+$file.FileId+$extension)
        $moves.Add("MOVE N'"+([string]$file.LogicalName).Replace("'","''")+"' TO N'"+$destination.Replace("'","''")+"'")
    }
    $state.test_restore_database=$restoreName; Save-State
    Execute-Sql ("RESTORE DATABASE ["+$restoreName+"] FROM DISK=N'"+$backupPath.Replace("'","''")+"' WITH CHECKSUM, RECOVERY, "+($moves -join ', '))
    $counts=Query-Sql ("SELECT (SELECT COUNT_BIG(*) FROM ["+$restoreName+"].dbo.scannings) scannings,(SELECT COUNT_BIG(*) FROM ["+$restoreName+"].dbo.pagetypings) pagetypings,(SELECT COUNT_BIG(*) FROM ["+$restoreName+"].dbo.blind_scannings) blind_scannings")
    $state.restored_counts=@{scannings=[long]$counts.Rows[0].scannings;pagetypings=[long]$counts.Rows[0].pagetypings;blind_scannings=[long]$counts.Rows[0].blind_scannings}
    $state.test_restore_complete=$true; Save-State
    Write-Output 'Isolated test restore passed. Keeping the restored database as a recovery copy.'
    $relative=$backupPath.Substring(3)
    $share='\\10.50.1.1\'+$backupPath.Substring(0,1)+'$\'+$relative
    $local=Join-Path $runRoot (Split-Path -Leaf $backupPath)
    if($ServerOnly){
      $state.verified_backup_location=$share
      $state.copy_skipped='Fresh backup retained on database server; earlier independent local copy retained.'
    }else{
    Copy-Item -LiteralPath $share -Destination $local
    $sourceHash=(Get-FileHash -LiteralPath $share -Algorithm SHA256).Hash
    $copyHash=(Get-FileHash -LiteralPath $local -Algorithm SHA256).Hash
    if($sourceHash -ne $copyHash){throw 'Database backup copy hash mismatch.'}
    $state.local_backup_path=$local; $state.sha256=$copyHash; $state.copy_verified=$true
    }
} catch {
    $state.error=$_.Exception.Message
    Write-Output ('Database backup/verification failed: '+$state.error)
} finally {
    $state.completed_at=(Get-Date).ToString('o'); Save-State
    $state | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $runRoot ('database-backup-state-'+(Get-Date -Format 'yyyyMMdd_HHmmss')+'.json')) -Encoding UTF8
    $connection.Dispose()
}
$state | ConvertTo-Json -Depth 8
