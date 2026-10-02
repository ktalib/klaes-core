@echo off
REM ===========================================================================
REM  STEP 1 - Run this ON THE PRODUCTION (XAMPP) MACHINE before copying
REM  anything over. Double-click it.
REM
REM  It copies the CURRENT production version of every file in this drop into a
REM  timestamped klaes_prod_backup_<date> folder right here. Nothing is written
REM  to the live site.
REM
REM  Only after this succeeds, copy app\ database\ docs\ public\ resources\
REM  routes\ over the live project folder.
REM ===========================================================================

echo.
echo   KLAES - backing up production files before deployment
echo   ----------------------------------------------------
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0backup-production.ps1"

echo.
if errorlevel 1 (
    echo   *** BACKUP FAILED - do NOT copy the new files over yet. ***
    echo.
    echo   If it could not find the project, open a PowerShell window here and run:
    echo     .\backup-production.ps1 -ProdRoot "C:\xampp\htdocs\your-folder-name"
) else (
    echo   Backup complete. You can now copy the folders over the live project.
)

echo.
pause
