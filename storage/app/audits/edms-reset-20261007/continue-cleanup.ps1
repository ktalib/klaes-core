$ErrorActionPreference='Stop'
Set-Location -LiteralPath 'C:\xampp\htdocs\klas'
$runRoot=(Get-Content -LiteralPath (Join-Path $PSScriptRoot 'active-backup-root.txt')).Trim()
$php='C:\xampp\php\php.exe'
$state=[ordered]@{started_at=(Get-Date).ToString('o');stage='waiting_for_folder_backup';complete=$false;live_changes_started=$false}
$maintenance=$false
$runMutex=New-Object System.Threading.Mutex($false,'Local\KlaesEdmsReset20261007')
if(!$runMutex.WaitOne(0)){throw 'The cleanup coordinator is already running.'}
function State([string]$stage){$state.stage=$stage;$state.updated_at=(Get-Date).ToString('o');$state | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $runRoot 'execution-state.json') -Encoding UTF8}
function Read-Json([string]$path){return Get-Content -LiteralPath $path -Raw | ConvertFrom-Json}
function Run-Audit([string]$logName){
    $started=[DateTimeOffset]::Now
    & $php (Join-Path $PSScriptRoot 'audit.php') (Join-Path $runRoot 'final-audit') | Out-File -LiteralPath (Join-Path $runRoot $logName) -Encoding utf8
    $summary=Read-Json (Join-Path $runRoot 'final-audit\summary.json')
    if([DateTimeOffset]$summary.started_at -lt $started.AddSeconds(-1) -or [DateTimeOffset]$summary.completed_at -lt $started.AddSeconds(-1)){throw 'Fresh audit did not complete; stale results will not be used.'}
}
function Copy-Folders([string]$phase,[switch]$ListOnly){
    foreach($root in @(@{label='F';path='F:\storage\app\public'},@{label='C';path='C:\xampp\htdocs\klas\storage\app\public'})){
        $destination=Join-Path $runRoot ('files\'+$root.label+'-public')
        $log=Join-Path $runRoot ('folder-'+$phase+'-'+$root.label+'.log')
        $options=@('/E','/COPY:DAT','/DCOPY:DAT','/XJ','/MT:8','/BYTES','/NFL','/NDL','/NP','/R:1','/W:1',('/LOG:'+$log))
        if($ListOnly){$options+='/L'}
        & robocopy $root.path $destination @options | Out-Null
        if($LASTEXITCODE -ge 8){throw ('Folder backup failed: '+$log)}
        if($ListOnly -and $LASTEXITCODE -notin @(0,2)){throw ('Folder verification found uncopied/different files: '+$log)}
    }
}
try {
    State 'waiting_for_folder_backup'
    while(!(Test-Path -LiteralPath (Join-Path $runRoot 'folder-copy-F.exitcode'))){
        $copy=Get-CimInstance Win32_Process -Filter "Name='robocopy.exe'" | Where-Object {$_.CommandLine -like ('*'+$runRoot+'*folder-copy-F.log*')}
        if(!$copy){
            Start-Sleep -Seconds 2
            if(Test-Path -LiteralPath (Join-Path $runRoot 'folder-copy-F.exitcode')){break}
            throw 'The initial folder-copy process stopped without a completion result.'
        }
        $state.folder_copy_bytes=[long]$copy.WriteTransferCount
        State 'waiting_for_folder_backup'
        Start-Sleep -Seconds 30
    }
    foreach($label in @('F','C')){if([int](Get-Content -LiteralPath (Join-Path $runRoot ('folder-copy-'+$label+'.exitcode'))) -ge 8){throw 'Initial folder backup reported failures.'}}
    State 'checking_database_backup'
    $dbState=Read-Json (Join-Path $runRoot 'database-backup-verified.json')
    if(!$dbState.restore_verified -or !$dbState.test_restore_complete -or !$dbState.copy_verified){throw 'Initial database backup/copy verification is incomplete.'}
    $rehearsalPath=Join-Path $runRoot 'rehearsal\result.json'
    $scriptHash=(Get-FileHash -LiteralPath (Join-Path $PSScriptRoot 'cleanup-database.php') -Algorithm SHA256).Hash
    $rehearsal=if(Test-Path -LiteralPath $rehearsalPath){Read-Json $rehearsalPath}else{$null}
    if(!$rehearsal.rehearsal_passed -or $rehearsal.script_sha256 -ine $scriptHash){
        State 'rehearsing_on_restored_database'
        & $php (Join-Path $PSScriptRoot 'cleanup-database.php') --rehearse | Out-File -LiteralPath (Join-Path $runRoot 'rehearsal-final-output.log') -Encoding utf8
        $rehearsal=Read-Json $rehearsalPath
        if(!$rehearsal.rehearsal_passed -or $rehearsal.script_sha256 -ine $scriptHash){throw 'The isolated cleanup rehearsal did not pass.'}
    }
    State 'refreshing_backup_before_maintenance'
    Copy-Folders 'online-refresh'
    Run-Audit 'online-audit.log'
    State 'hashing_candidate_backups_online'
    & $php (Join-Path $PSScriptRoot 'prepare-files.php') | Out-File -LiteralPath (Join-Path $runRoot 'online-file-verification.log') -Encoding utf8
    # The online pass caches verified hashes. A concurrent upload can make it
    # incomplete; the final offline pass below must pass in full.
    State 'entering_maintenance'
    if(Test-Path -LiteralPath 'storage\framework\down'){throw 'Application was already in maintenance mode; leaving it unchanged.'}
    & $php artisan down --retry=60 | Out-File -LiteralPath (Join-Path $runRoot 'maintenance.log') -Encoding utf8
    if(!(Test-Path -LiteralPath 'storage\framework\down')){throw 'Maintenance mode was not enabled.'}
    $maintenance=$true
    State 'final_folder_backup_and_verification'
    Copy-Folders 'final-copy'
    Copy-Folders 'final-verify' -ListOnly
    State 'fresh_database_backup_and_test_restore'
    $backupStarted=[DateTimeOffset]::Now
    & (Join-Path $PSScriptRoot 'backup-database.ps1') | Out-File -LiteralPath (Join-Path $runRoot 'database-final-backup.log') -Encoding utf8
    $dbState=Read-Json (Join-Path $runRoot 'database-backup-verified.json')
    if(!$dbState.restore_verified -or !$dbState.test_restore_complete -or !$dbState.copy_verified -or $dbState.error -or [DateTimeOffset]$dbState.started_at -lt $backupStarted.AddSeconds(-1)){throw 'Fresh database backup verification failed.'}
    State 'final_audit'
    Run-Audit 'final-audit.log'
    State 'final_file_hash_verification'
    $verificationStarted=[DateTimeOffset]::Now
    & $php (Join-Path $PSScriptRoot 'prepare-files.php') | Out-File -LiteralPath (Join-Path $runRoot 'final-file-verification.log') -Encoding utf8
    $files=Read-Json (Join-Path $runRoot 'file-verification.json')
    if(!$files.complete -or $files.error -or [DateTimeOffset]$files.started_at -lt $verificationStarted.AddSeconds(-1) -or [DateTimeOffset]$files.completed_at -lt $verificationStarted.AddSeconds(-1)){throw 'Fresh candidate file backup verification failed.'}
    $gate=@{folders_verified=$true;fresh_database_backup_verified=$true;files_manifest_verified=$true;files_manifest_sha256=$files.manifest_sha256;final_audit_sha256=(Get-FileHash -LiteralPath (Join-Path $runRoot 'final-audit\summary.json') -Algorithm SHA256).Hash.ToLower();verified_at=(Get-Date).ToString('o')}
    $gate | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runRoot 'ready-for-cleanup.json') -Encoding UTF8
    State 'transactional_database_cleanup'
    $state.live_changes_started=$true
    & $php (Join-Path $PSScriptRoot 'cleanup-database.php') --execute | Out-File -LiteralPath (Join-Path $runRoot 'cleanup-output.log') -Encoding utf8
    $result=Read-Json (Join-Path $runRoot 'execution\result.json')
    if(!$result.committed -or $result.error){throw 'Database cleanup did not finish successfully; see execution/result.json.'}
    State 'quarantining_verified_unprotected_files'
    & (Join-Path $PSScriptRoot 'quarantine-files.ps1')
    $state.result=$result.after
    $state.deleted=$result.deleted
    $state.blind_status_updated=$result.blind_status_updated
    $state.file_indexings_updated=$result.file_indexings_updated
    $state.complete=$true
    State 'completed'
} catch {
    $state.error=$_.Exception.Message
    State 'stopped_on_verification_error'
} finally {
    if($maintenance){& $php artisan up | Out-File -LiteralPath (Join-Path $runRoot 'maintenance-ended.log') -Encoding utf8}
    $state.finished_at=(Get-Date).ToString('o')
    $state | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $runRoot 'execution-state.json') -Encoding UTF8
    $runMutex.ReleaseMutex()
    $runMutex.Dispose()
}
