@echo off
title FedEx Tracker - Prerequisites Check
echo ========================================
echo    FedEx Tracker Prerequisites Check
echo ========================================
echo.

echo Checking if Docker is installed...
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo ❌ ERROR: Docker is not installed or not running!
    echo.
    echo Please install Docker Desktop from:
    echo https://www.docker.com/products/docker-desktop/
    echo.
    echo After installation, make sure Docker Desktop is running.
    echo.
    pause
    exit /b 1
)

echo ✅ Docker is installed!
echo.

echo Checking if Docker is running...
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo ❌ ERROR: Docker is not running!
    echo.
    echo Please start Docker Desktop and try again.
    echo.
    pause
    exit /b 1
)

echo ✅ Docker is running!
echo.
echo ========================================
echo    All prerequisites are satisfied!
echo ========================================
echo.
echo You can now run "Start FedEx Tracker.bat"
echo.
timeout /t 5