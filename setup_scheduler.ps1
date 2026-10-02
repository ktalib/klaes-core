# Setup Windows Task Scheduler for Laravel artisan schedule:run
# Run this script as Administrator

$projectPath = 'C:\xampp\htdocs\klas'
$phpPath = 'C:\xampp\php\php.exe'
$artisanPath = "$projectPath\artisan"
$taskName = 'Laravel Scheduler'
$taskDescription = 'Runs Laravel scheduled commands including database backups'

# Check if task already exists
$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

if ($existingTask) {
    Write-Host "Task '$taskName' already exists. Removing it first..."
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    Start-Sleep -Seconds 2
}

# Create action: Run PHP artisan schedule:run
$action = New-ScheduledTaskAction -Execute $phpPath -Argument "$artisanPath schedule:run" -WorkingDirectory $projectPath

# Create trigger: Run every minute, indefinitely (30 years)
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 10950)

# Create settings
$settings = New-ScheduledTaskSettingsSet -RunOnlyIfNetworkAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -AllowStartIfOnBatteries -DontStopOnIdleEnd

# Register the task
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Description $taskDescription -User "SYSTEM" -RunLevel Highest -Force | Out-Null

Write-Host "✓ Task '$taskName' created successfully!"
Write-Host "  - Runs every minute"
Write-Host "  - User: SYSTEM"
Write-Host ""
Write-Host "Laravel Scheduled Tasks:"
Write-Host "  1. backup:mssql         → Daily at 05:00 (NEW)"
Write-Host "  2. drafts:cleanup       → Daily at 02:00"
Write-Host "  3. attendance:process   → Daily at 05:30"
Write-Host "  4. activity:auto-logout → Every 10 minutes"
Write-Host ""

# Start the task
Start-ScheduledTask -TaskName $taskName

Write-Host "✓ Task started!"
Write-Host ""
Write-Host "Database backups will execute DAILY at 05:00 AM"
Write-Host ""
Write-Host "To verify it's running:"
Write-Host "  - Open Task Scheduler: Win+R → taskschd.msc"
Write-Host "  - Or run: tasklist | findstr php"
