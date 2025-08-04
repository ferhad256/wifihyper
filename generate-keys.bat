@echo off
REM Yo Payments RSA Key Generation Script for Windows
REM This script generates a private/public key pair for Yo Payments API authentication

echo === Yo Payments RSA Key Generation ===
echo.

REM Check if OpenSSL is available
openssl version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: OpenSSL is not installed or not in PATH.
    echo Please install OpenSSL and add it to your system PATH.
    pause
    exit /b 1
)

REM Create keys directory if it doesn't exist
if not exist "storage\keys" mkdir "storage\keys"

REM Generate private key
echo Generating private key...
openssl genpkey -algorithm RSA -out "storage\keys\yo_payments_private_key.pem" -pkeyopt rsa_keygen_bits:2048

if %errorlevel% equ 0 (
    echo ✅ Private key generated successfully: storage\keys\yo_payments_private_key.pem
) else (
    echo ❌ Failed to generate private key
    pause
    exit /b 1
)

REM Extract public key
echo Extracting public key...
openssl rsa -pubout -in "storage\keys\yo_payments_private_key.pem" -out "storage\keys\yo_payments_public_key.pem"

if %errorlevel% equ 0 (
    echo ✅ Public key extracted successfully: storage\keys\yo_payments_public_key.pem
) else (
    echo ❌ Failed to extract public key
    pause
    exit /b 1
)

echo.
echo === Key Generation Complete ===
echo.
echo IMPORTANT SECURITY NOTES:
echo 1. Private key: storage\keys\yo_payments_private_key.pem
echo    - Keep this file secure and never share it
echo    - Do not commit this file to version control
echo.
echo 2. Public key: storage\keys\yo_payments_public_key.pem
echo    - Share this file with your Yo Payments account representative
echo    - This will be configured under your Yo Payments account profile
echo.
echo 3. Update your .env file with:
echo    YO_PAYMENTS_PUBLIC_KEY_ENABLED=true
echo    YO_PAYMENTS_PRIVATE_KEY_PATH=storage\keys\yo_payments_private_key.pem
echo.
echo 4. Add to .gitignore:
echo    storage\keys\yo_payments_private_key.pem
echo.
pause 