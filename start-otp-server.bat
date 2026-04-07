@echo off
REM NeXLace OTP Server Startup Script
REM This script starts the Node.js server for sending OTP emails

echo ================================
echo NeXLace OTP Email Server
echo ================================
echo.

REM Check if Node.js is installed
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Node.js is not installed!
    echo.
    echo Please install Node.js from: https://nodejs.org/
    echo Download the LTS version and install it.
    echo.
    echo After installation, run this script again.
    echo.
    pause
    exit /b 1
)

for /f "tokens=*" %%v in ('node --version') do echo [OK] Node.js %%v is installed
echo.

REM Navigate to nodemailer directory (relative to this script's location)
cd /d "%~dp0nodemailer"

REM Check if node_modules exists
if not exist "node_modules\" (
    echo [INFO] Installing dependencies...
    echo.
    call npm install
    if %ERRORLEVEL% NEQ 0 (
        echo.
        echo [ERROR] Failed to install dependencies!
        pause
        exit /b 1
    )
    echo.
    echo [OK] Dependencies installed successfully!
    echo.
)

REM Check if port 3000 is already in use
netstat -ano | findstr ":3000 " | findstr "LISTENING" >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    echo [WARNING] Port 3000 is already in use!
    echo Another instance of the OTP server may already be running.
    echo.
    echo If you want to restart, close the other instance first.
    echo.
    pause
    exit /b 1
)

REM Start the server
echo ================================
echo Starting OTP server on port 3000
echo ================================
echo.
echo [INFO] Server URL: http://localhost:3000
echo [INFO] Keep this window open while using NeXLace.
echo [INFO] Press Ctrl+C to stop the server.
echo.

node index.js

echo.
echo [INFO] Server has stopped.
pause
