# SQL Server User Setup Script
# This script creates a SQL user 'klasUser' with full database permissions

$serverInstance = "(local)"

Write-Host "Creating SQL Server user 'klasUser'..." -ForegroundColor Green

try {
    # Load assemblies
    [System.Reflection.Assembly]::LoadWithPartialName('Microsoft.SqlServer.SMO') | out-null
    
    # Connect to server
    $srv = New-Object Microsoft.SqlServer.Management.Smo.Server($serverInstance)
    
    Write-Host "Connected to SQL Server version $($srv.Version.Major)" -ForegroundColor Green
    
    # Get database
    $db = $srv.Databases['klas']
    if (-not $db) {
        Write-Host "ERROR: Database 'klas' not found" -ForegroundColor Red
        exit
    }
    
    Write-Host "Found database 'klas'" -ForegroundColor Green
    
    # Check if login exists
    $login = $srv.Logins | Where-Object { $_.Name -eq 'klasUser' }
    if ($login) {
        Write-Host "Login 'klasUser' exists. Updating password..." -ForegroundColor Yellow
        $login.ChangePassword('12WithStrongPassword')
    } else {
        Write-Host "Creating login 'klasUser'..." -ForegroundColor Yellow
        $newLogin = New-Object Microsoft.SqlServer.Management.Smo.Login($srv, 'klasUser')
        $newLogin.LoginType = [Microsoft.SqlServer.Management.Smo.LoginType]::SqlLogin
        $newLogin.Create('12WithStrongPassword')
        Write-Host "Login created" -ForegroundColor Green
    }
    
    # Check if database user exists
    $user = $db.Users | Where-Object { $_.Name -eq 'klasUser' }
    if (-not $user) {
        Write-Host "Creating database user 'klasUser'..." -ForegroundColor Yellow
        $newUser = New-Object Microsoft.SqlServer.Management.Smo.User($db, 'klasUser')
        $newUser.Login = 'klasUser'
        $newUser.Create()
        Write-Host "Database user created" -ForegroundColor Green
    } else {
        Write-Host "Database user 'klasUser' already exists" -ForegroundColor Yellow
    }
    
    # Add to db_owner role
    Write-Host "Adding to db_owner role..." -ForegroundColor Yellow
    $role = $db.Roles | Where-Object { $_.Name -eq 'db_owner' }
    $members = $role.EnumMembers()
    
    if ($members -notcontains 'klasUser') {
        $role.AddMember('klasUser')
        Write-Host "User added to db_owner role" -ForegroundColor Green
    } else {
        Write-Host "User already in db_owner role" -ForegroundColor Yellow
    }
    
    Write-Host ""
    Write-Host "SUCCESS! User 'klasUser' is ready to use." -ForegroundColor Green
    Write-Host ""
    Write-Host "Connection details:" -ForegroundColor Cyan
    Write-Host "  Server: (local)" -ForegroundColor White
    Write-Host "  Database: klas" -ForegroundColor White
    Write-Host "  Username: klasUser" -ForegroundColor White
    Write-Host "  Password: 12WithStrongPassword" -ForegroundColor White
    
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
}
