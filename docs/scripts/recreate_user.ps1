# Recreate klasUser with proper SQL authentication configuration

Write-Host "Recreating SQL Server user with proper configuration..." -ForegroundColor Green

try {
    [System.Reflection.Assembly]::LoadWithPartialName('Microsoft.SqlServer.SMO') | out-null
    
    $srv = New-Object Microsoft.SqlServer.Management.Smo.Server('(local)')
    $db = $srv.Databases['klas']
    
    Write-Host "Connected to database 'klas'" -ForegroundColor Green
    
    # Delete existing login if it exists
    $login = $srv.Logins | Where-Object { $_.Name -eq 'klasUser' }
    if ($login) {
        Write-Host "Removing existing login 'klasUser'..." -ForegroundColor Yellow
        
        # Drop the database user first
        $user = $db.Users | Where-Object { $_.Name -eq 'klasUser' }
        if ($user) {
            $user.Drop()
            Write-Host "  Database user dropped" -ForegroundColor Green
        }
        
        # Drop the login
        $login.Drop()
        Write-Host "  Login dropped" -ForegroundColor Green
    }
    
    Write-Host ""
    Write-Host "Creating new login 'klasUser' with SQL authentication..." -ForegroundColor Yellow
    
    # Create new login
    $newLogin = New-Object Microsoft.SqlServer.Management.Smo.Login($srv, 'klasUser')
    $newLogin.LoginType = [Microsoft.SqlServer.Management.Smo.LoginType]::SqlLogin
    $newLogin.Create('12WithStrongPassword')
    
    Write-Host "  Login created" -ForegroundColor Green
    
    Write-Host ""
    Write-Host "Creating database user 'klasUser'..." -ForegroundColor Yellow
    
    # Create database user
    $newUser = New-Object Microsoft.SqlServer.Management.Smo.User($db, 'klasUser')
    $newUser.Login = 'klasUser'
    $newUser.Create()
    
    Write-Host "  Database user created" -ForegroundColor Green
    
    Write-Host ""
    Write-Host "Adding to db_owner role..." -ForegroundColor Yellow
    
    # Add to db_owner
    $role = $db.Roles | Where-Object { $_.Name -eq 'db_owner' }
    $role.AddMember('klasUser')
    
    Write-Host "  User added to db_owner role" -ForegroundColor Green
    
    Write-Host ""
    Write-Host "SUCCESS! User recreated with proper authentication." -ForegroundColor Green
    Write-Host ""
    Write-Host "IMPORTANT: SQL Server may need to be restarted for changes to take effect." -ForegroundColor Cyan
    Write-Host ""
    Write-Host "To manually restart SQL Server:" -ForegroundColor Yellow
    Write-Host "1. Open Services (services.msc)" -ForegroundColor White
    Write-Host "2. Find 'SQL Server (MSSQLSERVER)'" -ForegroundColor White
    Write-Host "3. Right-click and select 'Restart'" -ForegroundColor White
    
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}
