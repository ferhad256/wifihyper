@echo off
title WIFIHYPER Automation Testing
echo ========================================
echo    WIFIHYPER Payment Automation
echo    Command Testing Script
echo ========================================
echo.

cd /d "%~dp0"

echo Testing all automation commands...
echo.

echo 1. Testing Critical Payment Checking...
C:\xampp\php\php.exe artisan payments:check-pending --limit=3 --critical=true
echo.

echo 2. Testing Normal Payment Checking...
C:\xampp\php\php.exe artisan payments:check-pending --limit=3
echo.

echo 3. Testing Batch Auto-Check...
C:\xampp\php\php.exe artisan payment:auto-check-batch --max-attempts=3 --delay=5 --limit=3
echo.

echo 4. Testing Cleanup (Dry Run)...
C:\xampp\php\php.exe artisan payments:cleanup-pending --older-than=24 --dry-run
echo.

echo 5. Testing Reminders (Dry Run)...
C:\xampp\php\php.exe artisan payments:send-reminders --older-than=30 --dry-run
echo.

echo ========================================
echo    All automation commands tested!
echo ========================================
echo.
echo If you see no errors above, your automation is working correctly.
echo.
echo To start the automated scheduler, run: run_scheduler.bat
echo.
pause 