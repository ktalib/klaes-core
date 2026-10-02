# SQL Server User Setup Script
# Run this in PowerShell as Administrator

$sqlFilePath = "c:\xampp\htdocs\klaes\create_sql_user.sql"
$serverInstance = "(local)"

Write-Host "Creating SQL Server user 'klasUser' with full database permissions..." -ForegroundColor Green
Write-Host "Server: $serverInstance"
Write-Host "Database: klas"
Write-Host ""

# Method 1: Using sqlcmd (if SQL Server tools are installed)
try {
    # Check if sqlcmd is available
    $sqlcmdPath = & where.exe sqlcmd 2>$null
    
    if ($sqlcmdPath) {
        Write-Host "Using sqlcmd to execute script..." -ForegroundColor Yellow
        & sqlcmd -S $serverInstance -i $sqlFilePath -E
        Write-Host "Script executed successfully!" -ForegroundColor Green
    }
    else {
        Write-Host "sqlcmd not found. Trying PowerShell SMO approach..." -ForegroundColor Yellow
        
        # Method 2: Using PowerShell SMO (SQL Server Management Objects)
        [System.Reflection.Assembly]::LoadWithPartialName('Microsoft.SqlServer.SMO') | out-null
        
        $srv = New-Object ('Microsoft.SqlServer.Management.Smo.Server') $serverInstance
        $db = $srv.Databases['klas']
        
        if ($db) {
            Write-Host "Connected to database 'klas'" -ForegroundColor Green
            
            # Create login
            $login = $srv.Logins['klasUser']
            if (-not $login) {
                $login = New-Object ('Microsoft.SqlServer.Management.Smo.Login') ($srv, 'klasUser')
                $login.LoginType = [Microsoft.SqlServer.Management.Smo.LoginType]::SqlLogin
                $login.Create('12WithStrongPassword')
                Write-Host "✓ Login 'klasUser' created" -ForegroundColor Green
            }
            else {
                Write-Host "✓ Login 'klasUser' already exists" -ForegroundColor Yellow
            }
            
            # Create database user
            $user = $db.Users['klasUser']
            if (-not $user) {
                $user = New-Object ('Microsoft.SqlServer.Management.Smo.User') ($db, 'klasUser')
                $user.Login = 'klasUser'
                $user.Create()
                Write-Host "✓ Database user 'klasUser' created" -ForegroundColor Green
            }
            else {
                Write-Host "✓ Database user 'klasUser' already exists" -ForegroundColor Yellow
            }
            
            # Add to db_owner role
            $dbOwnerRole = $db.Roles['db_owner']
            $dbOwnerRole.AddMember('klasUser')
            Write-Host "✓ User added to db_owner role (full permissions)" -ForegroundColor Green
            
            Write-Host ""
            Write-Host "✓ Setup completed successfully!" -ForegroundColor Green
        }
        else {
            Write-Host "ERROR: Could not connect to database 'klas'" -ForegroundColor Red
            Write-Host "Make sure SQL Server is running and the database exists." -ForegroundColor Red
        }
    }
}
catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Alternative: Run the SQL script manually:" -ForegroundColor Yellow
    Write-Host "1. Open SQL Server Management Studio (SSMS)"
    Write-Host "2. Connect to (local)"
    Write-Host "3. Open file: $sqlFilePath"
    Write-Host "4. Execute (F5)"
}

Write-Host ""
Write-Host "Your connection string is configured as:" -ForegroundColor Cyan
Write-Host "  Host: (local)"
Write-Host "  Database: klas"
Write-Host "  Username: klasUser"
Write-Host "  Password: 12WithStrongPassword"
