# Enable Mixed Mode Authentication in SQL Server
# This allows SQL logins (non-Windows) to connect

Write-Host "Checking SQL Server Authentication Mode..." -ForegroundColor Green
Write-Host ""

try {
    [System.Reflection.Assembly]::LoadWithPartialName('Microsoft.SqlServer.SMO') | out-null
    
    $srv = New-Object Microsoft.SqlServer.Management.Smo.Server('(local)')
    
    # Check current authentication mode
    $authMode = $srv.Settings.LoginMode
    
    Write-Host "Current Authentication Mode: $authMode" -ForegroundColor Yellow
    Write-Host ""
    
    if ($authMode -eq [Microsoft.SqlServer.Management.Smo.ServerLoginMode]::Mixed) {
        Write-Host "Mixed mode is already enabled" -ForegroundColor Green
    } else {
        Write-Host "Enabling Mixed mode authentication..." -ForegroundColor Yellow
        $srv.Settings.LoginMode = [Microsoft.SqlServer.Management.Smo.ServerLoginMode]::Mixed
        $srv.Alter()
        Write-Host "Mixed mode enabled" -ForegroundColor Green
        Write-Host ""
        Write-Host "NOTE: You may need to restart SQL Server for this change to take effect" -ForegroundColor Cyan
    }
    
    # Also enable the 'sa' account if it's disabled
    Write-Host ""
    Write-Host "Checking 'sa' account..." -ForegroundColor Yellow
    $saLogin = $srv.Logins['sa']
    
    if ($saLogin.IsDisabled) {
        Write-Host "'sa' account is disabled. Enabling it..." -ForegroundColor Yellow
        $saLogin.Disable = $false
        $saLogin.Alter()
        Write-Host "'sa' account is now enabled" -ForegroundColor Green
    } else {
        Write-Host "'sa' account is already enabled" -ForegroundColor Green
    }
    
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}
