# Registers the "KLAES SQL Backup" scheduled task: full backup of the SQL Server
# `klas` database at 06:00 and 18:00 daily via klas_backup.php.
# Run from an elevated PowerShell. Re-running replaces the task.

$taskName = "KLAES SQL Backup"
$php      = "C:\xampp\php\php.exe"
$script   = "C:\xampp\htdocs\klas\scripts\db_backup\klas_backup.php"

$action   = New-ScheduledTaskAction -Execute $php -Argument "`"$script`"" -WorkingDirectory "C:\xampp\htdocs\klas"
$triggers = @(
    New-ScheduledTaskTrigger -Daily -At "06:00"
    New-ScheduledTaskTrigger -Daily -At "18:00"
)
# StartWhenAvailable: a run missed while the server was off happens at next boot.
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Hours 2) -MultipleInstances IgnoreNew `
    -RestartCount 2 -RestartInterval (New-TimeSpan -Minutes 15)

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $triggers -Settings $settings `
    -Description "Full backup of SQL Server klas (10.50.1.1) to F:\KlaesBackups\Scheduled on the DB server. Log: storage\logs\db-backup.log" `
    -User "SYSTEM" -RunLevel Highest -Force | Out-Null

Get-ScheduledTask -TaskName $taskName | Get-ScheduledTaskInfo | Select-Object TaskName, NextRunTime
