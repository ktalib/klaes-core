# SQL Server User Setup - Using Windows Authentication
# Run this in PowerShell as Administrator

$serverInstance = "(local)"

Write-Host "Creating SQL Server user 'klasUser' with full database permissions..." -ForegroundColor Green
Write-Host "Server: $serverInstance" -ForegroundColor Cyan
Write-Host "Database: klas" -ForegroundColor Cyan
Write-Host ""

try {
    # Load SQL Server Management Objects
    [System.Reflection.Assembly]::LoadWithPartialName('Microsoft.SqlServer.SMO') | out-null
    [System.Reflection.Assembly]::LoadWithPartialName('Microsoft.SqlServer.SmoExtended') | out-null
    
    Write-Host "Connecting to SQL Server..." -ForegroundColor Yellow
    
    # Connect using Windows Authentication (no password needed for sa or admin)
    $srv = New-Object ('Microsoft.SqlServer.Management.Smo.Server') $serverInstance
    
    if ($srv.Version) {
        Write-Host "✓ Connected to SQL Server $(($srv.Version.Major).ToString())" -ForegroundColor Green
        
        # Get the database
        $db = $srv.Databases['klas']
        
        if ($db) {
            Write-Host "✓ Found database 'klas'" -ForegroundColor Green
            Write-Host ""
            
            # 1. Create login
            $loginExists = $srv.Logins | Where-Object { $_.Name -eq 'klasUser' }
            
            if (-not $loginExists) {
                Write-Host "Creating login 'klasUser'..." -ForegroundColor Yellow
                $login = New-Object ('Microsoft.SqlServer.Management.Smo.Login') ($srv, 'klasUser')
                $login.LoginType = [Microsoft.SqlServer.Management.Smo.LoginType]::SqlLogin
                $login.Create('12WithStrongPassword')
                Write-Host "✓ Login 'klasUser' created" -ForegroundColor Green
            }
            else {
                Write-Host "✓ Login 'klasUser' already exists" -ForegroundColor Yellow
                # Update password if needed
                $existingLogin = $srv.Logins['klasUser']
                $existingLogin.ChangePassword('12WithStrongPassword')
                Write-Host "✓ Password reset to: 12WithStrongPassword" -ForegroundColor Green
            }
            
            # 2. Create database user
            $userExists = $db.Users | Where-Object { $_.Name -eq 'klasUser' }
            
            if (-not $userExists) {
                Write-Host "Creating database user 'klasUser'..." -ForegroundColor Yellow
                $user = New-Object ('Microsoft.SqlServer.Management.Smo.User') ($db, 'klasUser')
                $user.Login = 'klasUser'
                $user.Create()
                Write-Host "✓ Database user 'klasUser' created" -ForegroundColor Green
            }
            else {
                Write-Host "✓ Database user 'klasUser' already exists" -ForegroundColor Yellow
            }
            
            # 3. Add to db_owner role
            Write-Host "Granting db_owner role..." -ForegroundColor Yellow
            $dbOwnerRole = $db.Roles | Where-Object { $_.Name -eq 'db_owner' }
            
            $isMember = $dbOwnerRole.EnumMembers() -contains 'klasUser'
            
            if (-not $isMember) {
                $dbOwnerRole.AddMember('klasUser')
                Write-Host "✓ User added to db_owner role" -ForegroundColor Green
            }
            else {
                Write-Host "✓ User already in db_owner role" -ForegroundColor Yellow
            }
            
            Write-Host ""
            Write-Host "================================" -ForegroundColor Green
            Write-Host "✓ SETUP COMPLETED SUCCESSFULLY!" -ForegroundColor Green
            Write-Host "================================" -ForegroundColor Green
            Write-Host ""
            Write-Host "Connection Details:" -ForegroundColor Cyan
            Write-Host "  Server:   (local)" -ForegroundColor White
            Write-Host "  Database: klas" -ForegroundColor White
            Write-Host "  Username: klasUser" -ForegroundColor White
            Write-Host "  Password: 12WithStrongPassword" -ForegroundColor White
            Write-Host ""
            Write-Host "Your .env file is already configured correctly:" -ForegroundColor Green
            Write-Host "  DB_SQLSRV_HOST=(local)" -ForegroundColor White
            Write-Host "  DB_SQLSRV_PORT=1433" -ForegroundColor White
            Write-Host "  DB_SQLSRV_DATABASE=klas" -ForegroundColor White
            Write-Host "  DB_SQLSRV_USERNAME=klasUser" -ForegroundColor White
            Write-Host "  DB_SQLSRV_PASSWORD=12WithStrongPassword" -ForegroundColor White
            
        }
        else {
            Write-Host "ERROR: Database 'klas' not found" -ForegroundColor Red
            Write-Host "Available databases:" -ForegroundColor Yellow
            $srv.Databases | Select-Object -Property Name | Format-Table
        }
    }
    else {
        Write-Host "ERROR: Could not connect to SQL Server instance '$serverInstance'" -ForegroundColor Red
        Write-Host "Make sure:" -ForegroundColor Yellow
        Write-Host "  1. SQL Server is running" -ForegroundColor White
        Write-Host "  2. SQL Server is listening on port 1433" -ForegroundColor White
        Write-Host "  3. You are running this script as Administrator" -ForegroundColor White
    }
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Stack Trace:" -ForegroundColor Yellow
    Write-Host "$($_.Exception.StackTrace)" -ForegroundColor Gray
}

Write-Host ""
Read-Host "Press Enter to exit"
