# Test SQL Server connection
# This verifies if klasUser can connect

Write-Host "Testing SQL Server connection..." -ForegroundColor Green
Write-Host ""

try {
    [System.Reflection.Assembly]::LoadWithPartialName('Microsoft.SqlServer.SMO') | out-null
    
    # Test with Windows Authentication first
    Write-Host "1. Testing with Windows Authentication (sa or admin)..." -ForegroundColor Yellow
    $srv = New-Object Microsoft.SqlServer.Management.Smo.Server('(local)')
    Write-Host "   Connected: $($srv.Name)" -ForegroundColor Green
    Write-Host "   Version: $($srv.Version)" -ForegroundColor Green
    
    # Test with SQL authentication
    Write-Host ""
    Write-Host "2. Testing with SQL Authentication (klasUser)..." -ForegroundColor Yellow
    
    $conn = New-Object System.Data.SqlClient.SqlConnection
    $conn.ConnectionString = "Server=(local);Database=klas;User Id=klasUser;Password=12WithStrongPassword;Encrypt=false;TrustServerCertificate=true;"
    $conn.Open()
    
    if ($conn.State -eq [System.Data.ConnectionState]::Open) {
        Write-Host "   Connected successfully!" -ForegroundColor Green
        Write-Host "   Database: $($conn.Database)" -ForegroundColor Green
        $conn.Close()
    }
    
    Write-Host ""
    Write-Host "SUCCESS! Connection is working." -ForegroundColor Green
    Write-Host ""
    Write-Host "Your connection string:" -ForegroundColor Cyan
    Write-Host "Server=(local);Database=klas;User Id=klasUser;Password=12WithStrongPassword;" -ForegroundColor White
    
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Possible solutions:" -ForegroundColor Yellow
    Write-Host "1. Verify SQL Server is running:" -ForegroundColor White
    Write-Host "   Get-Service MSSQLSERVER" -ForegroundColor Gray
    Write-Host ""
    Write-Host "2. Check if TCP/IP is enabled in SQL Server Configuration Manager" -ForegroundColor White
    Write-Host "3. Verify the password is correct: 12WithStrongPassword" -ForegroundColor White
}
