# Enable TCP/IP in SQL Server and restart the service
# Run this as Administrator

Write-Host "Enabling TCP/IP protocol in SQL Server..." -ForegroundColor Green

try {
    # Path to SQL Server Configuration Manager registry
    $regPath = "HKLM:\SOFTWARE\Microsoft\Microsoft SQL Server\MSSQL17.MSSQLSERVER\MSSQLServer\SuperSocketNetLib\Tcp"
    
    # Check if TCP/IP is enabled
    $tcpEnabled = (Get-ItemProperty -Path $regPath -Name "Enabled" -ErrorAction SilentlyContinue).Enabled
    
    if ($tcpEnabled -eq 1) {
        Write-Host "TCP/IP is already enabled" -ForegroundColor Yellow
    } else {
        Write-Host "Enabling TCP/IP..." -ForegroundColor Yellow
        Set-ItemProperty -Path $regPath -Name "Enabled" -Value 1
        Write-Host "TCP/IP enabled" -ForegroundColor Green
    }
    
    # Check Named Pipes
    $npPath = "HKLM:\SOFTWARE\Microsoft\Microsoft SQL Server\MSSQL17.MSSQLSERVER\MSSQLServer\SuperSocketNetLib\NamedPipes"
    $npEnabled = (Get-ItemProperty -Path $npPath -Name "Enabled" -ErrorAction SilentlyContinue).Enabled
    
    if ($npEnabled -eq 1) {
        Write-Host "Named Pipes is already enabled" -ForegroundColor Yellow
    } else {
        Write-Host "Enabling Named Pipes..." -ForegroundColor Yellow
        Set-ItemProperty -Path $npPath -Name "Enabled" -Value 1
        Write-Host "Named Pipes enabled" -ForegroundColor Green
    }
    
    Write-Host ""
    Write-Host "Restarting SQL Server service..." -ForegroundColor Yellow
    
    # Restart SQL Server
    Restart-Service -Name MSSQLSERVER -Force
    
    Write-Host "SQL Server restarted" -ForegroundColor Green
    
    Write-Host ""
    Write-Host "Waiting for SQL Server to fully start..." -ForegroundColor Yellow
    Start-Sleep -Seconds 5
    
    # Verify the service is running
    $service = Get-Service -Name MSSQLSERVER
    if ($service.Status -eq 'Running') {
        Write-Host "SQL Server is running" -ForegroundColor Green
    } else {
        Write-Host "WARNING: SQL Server service is not running" -ForegroundColor Red
    }
    
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Make sure you are running this script as Administrator" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Done! Try your Laravel connection again." -ForegroundColor Green
