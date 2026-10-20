@echo off
REM Test script to verify Docker setup for NutriTrace (Windows)

setlocal enabledelayedexpansion

title NutriTrace Docker Setup Test
echo ========================================
echo  NutriTrace Docker Setup Test
echo ========================================
echo.

set TESTS_PASSED=0
set TESTS_FAILED=0

echo [1/5] Checking Prerequisites
echo ========================================

REM Check Docker
docker --version >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Docker is installed
    set /a TESTS_PASSED+=1
    docker --version
) else (
    echo [FAIL] Docker is not installed
    set /a TESTS_FAILED+=1
)

REM Check Docker Compose
docker compose version >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Docker Compose is available
    set /a TESTS_PASSED+=1
    docker compose version
) else (
    echo [FAIL] Docker Compose is not available
    set /a TESTS_FAILED+=1
)

echo.
echo [2/5] Checking Docker Files
echo ========================================

call :test_file "docker-compose.yml" "Docker Compose configuration"
call :test_file "NutriTrace\Dockerfile" "Laravel Dockerfile"
call :test_file "NutriTrace\ai\Dockerfile" "AI service Dockerfile"
call :test_file ".dockerignore" "Root .dockerignore"
call :test_file "NutriTrace\.dockerignore" "Laravel .dockerignore"
call :test_file "NutriTrace\ai\.dockerignore" "AI .dockerignore"

echo.
echo [3/5] Checking Documentation
echo ========================================

call :test_file "docker\README.md" "Docker deployment guide"
call :test_file "docker\ARCHITECTURE.md" "Architecture documentation"
call :test_file "DOCKER_SETUP.md" "Setup summary"
call :test_file "NEXT_STEPS.md" "Next steps guide"
call :test_file ".env.docker.example" "Environment template"

echo.
echo [4/5] Checking Scripts
echo ========================================

call :test_file "docker-start.sh" "Linux/Mac start script"
call :test_file "docker-start.bat" "Windows start script"

echo.
echo [5/5] Validating Configuration
echo ========================================

REM Validate docker-compose.yml
docker compose config >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] docker-compose.yml syntax is valid
    set /a TESTS_PASSED+=1
) else (
    echo [FAIL] docker-compose.yml has syntax errors
    set /a TESTS_FAILED+=1
)

REM Check APP_KEY
if exist "NutriTrace\.env" (
    findstr /C:"APP_KEY=base64:" "NutriTrace\.env" >nul 2>&1
    if !errorlevel! equ 0 (
        echo [OK] APP_KEY is configured in NutriTrace\.env
        set /a TESTS_PASSED+=1
    ) else (
        echo [WARN] APP_KEY not found in NutriTrace\.env
        echo        Run: cd NutriTrace ^&^& php artisan key:generate
    )
) else (
    echo [WARN] NutriTrace\.env not found
    echo        Copy from NutriTrace\.env.example
)

echo.
echo ========================================
echo  Test Summary
echo ========================================
echo.
echo Passed: %TESTS_PASSED%
echo Failed: %TESTS_FAILED%
echo.

if %TESTS_FAILED% equ 0 (
    echo [SUCCESS] All tests passed! Your Docker setup is ready.
    echo.
    echo Next steps:
    echo   1. Run: docker-start.bat
    echo   2. Access: http://localhost:8000
    echo   3. Read: docker\README.md for more info
    exit /b 0
) else (
    echo [ERROR] Some tests failed. Please fix the issues above.
    exit /b 1
)

:test_file
if exist "%~1" (
    echo [OK] %~2
    set /a TESTS_PASSED+=1
) else (
    echo [FAIL] %~2 - MISSING: %~1
    set /a TESTS_FAILED+=1
)
exit /b
