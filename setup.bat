@echo off
echo ========================================
echo WIFIHYPER Setup Script
echo ========================================
echo.

echo Starting XAMPP services...
echo Please ensure XAMPP is installed and MySQL is running
echo.

echo Installing dependencies...
C:\xampp\php\php.exe composer.phar install

echo.
echo Running database migrations...
C:\xampp\php\php.exe artisan migrate

echo.
echo Clearing caches...
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan cache:clear
C:\xampp\php\php.exe artisan view:clear

echo.
echo Setting permissions...
icacls storage /grant Everyone:F /T
icacls bootstrap/cache /grant Everyone:F /T

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Your WIFIHYPER application is ready!
echo.
echo To start the development server:
echo C:\xampp\php\php.exe artisan serve
echo.
echo Or access via XAMPP:
echo http://localhost/wifi-saas/public
echo.
echo Default login credentials:
echo Email: admin@example.com
echo Password: password123
echo.
echo ========================================
pause 