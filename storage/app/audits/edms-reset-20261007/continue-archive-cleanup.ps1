$ErrorActionPreference='Stop'
Set-Location -LiteralPath 'C:\xampp\htdocs\klas'
$runRoot=(Get-Content -LiteralPath (Join-Path $PSScriptRoot 'active-backup-root.txt')).Trim()
$php='C:\xampp\php\php.exe'
$state=[ordered]@{started_at=(Get-Date).ToString('o');strategy='same_volume_folder_archive';complete=$false;live_changes_started=$false}
$maintenance=$false;$switchStarted=$false;$committed=$false
$mutex=New-Object System.Threading.Mutex($false,'Local\KlaesEdmsReset20261007')
if(!$mutex.WaitOne(0)){throw 'Another coordinator is running.'}
function State([string]$stage){$state.stage=$stage;$state.updated_at=(Get-Date).ToString('o');$state | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $runRoot 'execution-state.json') -Encoding UTF8}
function Read-Json([string]$name){return Get-Content -LiteralPath (Join-Path $runRoot $name) -Raw | ConvertFrom-Json}
try{
 State 'checking_rehearsal'
 $rehearsal=Read-Json 'rehearsal/result.json'
 if(!$rehearsal.rehearsal_passed -or $rehearsal.script_sha256 -ine (Get-FileHash -LiteralPath (Join-Path $PSScriptRoot 'cleanup-database.php') -Algorithm SHA256).Hash){throw 'Current cleanup rehearsal must pass.'}
 if(Test-Path -LiteralPath (Join-Path $runRoot 'cleanup-committed.json')){throw 'Cleanup already committed.'}
 $initial=Read-Json 'database-backup-verified.json'
 if(!$initial.copy_verified -or !$initial.restore_verified -or !$initial.test_restore_complete){throw 'Initial verified backup required.'}
 $initial | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $runRoot 'initial-independent-database-backup.json') -Encoding UTF8
 if(Test-Path -LiteralPath 'storage/framework/down'){throw 'Application already in maintenance.'}
 & $php artisan down --retry=60 | Out-File -LiteralPath (Join-Path $runRoot 'archive-maintenance.log')
 if(!(Test-Path -LiteralPath 'storage/framework/down')){throw 'Could not enable maintenance.'}
 $maintenance=$true
 State 'fresh_database_backup_and_restore_verification'
 $started=[DateTimeOffset]::Now
 & (Join-Path $PSScriptRoot 'backup-database.ps1') -ServerOnly | Out-File -LiteralPath (Join-Path $runRoot 'archive-database-backup.log')
 $db=Read-Json 'database-backup-verified.json'
 if(!$db.restore_verified -or !$db.test_restore_complete -or $db.error -or [DateTimeOffset]$db.started_at -lt $started.AddSeconds(-1)){throw 'Fresh database backup failed verification.'}
 State 'final_audit'
 $started=[DateTimeOffset]::Now
 & $php (Join-Path $PSScriptRoot 'audit.php') (Join-Path $runRoot 'final-audit') | Out-File -LiteralPath (Join-Path $runRoot 'archive-final-audit.log')
 $audit=Read-Json 'final-audit/summary.json'
 if([DateTimeOffset]$audit.started_at -lt $started.AddSeconds(-1) -or [DateTimeOffset]$audit.completed_at -lt $started.AddSeconds(-1)){throw 'Fresh audit failed.'}
 State 'checking_protected_file_references'
 $started=[DateTimeOffset]::Now
 & $php (Join-Path $PSScriptRoot 'prepare-archive.php') | Out-File -LiteralPath (Join-Path $runRoot 'archive-plan-output.log')
 $plan=Read-Json 'archive-plan.json'
 if([DateTimeOffset]$plan.created_at -lt $started.AddSeconds(-1) -or !$plan.directories -or !$plan.files){throw 'Fresh archive plan missing.'}
 State 'archiving_folders_and_restoring_protected_files'
 $switchStarted=$true
 & (Join-Path $PSScriptRoot 'switch-folders.ps1')
 $folders=Read-Json 'folder-switch-result.json'
 if(!$folders.complete -or $folders.archive_plan_sha256 -ine (Get-FileHash -LiteralPath (Join-Path $runRoot 'archive-plan.json') -Algorithm SHA256).Hash){throw 'Folder switch verification failed.'}
 @{folders_verified=$true;fresh_database_backup_verified=$true;files_manifest_verified=$true;strategy='same_volume_folder_archive';files_manifest_sha256=(Get-FileHash -LiteralPath (Join-Path $runRoot 'verified-file-actions.csv') -Algorithm SHA256).Hash.ToLower();final_audit_sha256=(Get-FileHash -LiteralPath (Join-Path $runRoot 'final-audit/summary.json') -Algorithm SHA256).Hash.ToLower()} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runRoot 'ready-for-cleanup.json') -Encoding UTF8
 State 'transactional_database_cleanup'
 $state.live_changes_started=$true
 & $php (Join-Path $PSScriptRoot 'cleanup-database.php') --execute | Out-File -LiteralPath (Join-Path $runRoot 'archive-cleanup-output.log')
 $result=Read-Json 'execution/result.json'
 $committed=[bool]$result.committed
 if(!$committed -or $result.error){throw ('Database cleanup failed: '+$result.error)}
 $state.result=$result.after;$state.deleted=$result.deleted;$state.blind_status_updated=$result.blind_status_updated;$state.file_indexings_updated=$result.file_indexings_updated;$state.protected_files_restored=$folders.protected_files
 $state.complete=$true
 State 'completed'
}catch{
 $state.error=$_.Exception.Message
 if(Test-Path -LiteralPath (Join-Path $runRoot 'cleanup-committed.json')){$committed=$true}
 if($switchStarted -and !$committed){
  try{& (Join-Path $PSScriptRoot 'switch-folders.ps1') -Rollback;$state.folder_rollback_complete=$true}catch{$state.folder_rollback_error=$_.Exception.Message}
 }
 State 'stopped_on_verification_error'
}finally{
 if($maintenance -and !$state.folder_rollback_error){& $php artisan up | Out-File -LiteralPath (Join-Path $runRoot 'archive-maintenance-ended.log')}
 $state.finished_at=(Get-Date).ToString('o');$state | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $runRoot 'execution-state.json') -Encoding UTF8
 $mutex.ReleaseMutex();$mutex.Dispose()
}
