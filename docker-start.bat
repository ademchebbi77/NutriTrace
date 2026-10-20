@echo off
REM !variables! below need delayed expansion.
setlocal enabledelayedexpansion
REM Quick start script for NutriTrace with Docker
REM This script helps you start NutriTrace containers with proper configuration

title NutriTrace Docker Setup
echo ========================================
echo  NutriTrace Docker Quick Start
echo ========================================
echo.

REM Check if Docker is installed
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Docker is not installed or not in PATH
    echo Please install Docker Desktop from https://www.docker.com/products/docker-desktop/
    pause
    exit /b 1
)

echo [OK] Docker is installed
echo.

REM Check if .env file exists
if not exist ".env" (
    echo [INFO] No .env file found. Checking for APP_KEY...
    
    REM Check if NutriTrace/.env exists
    if exist "NutriTrace\.env" (
        echo [INFO] Found NutriTrace\.env file
        
        REM Extract APP_KEY from NutriTrace/.env (everything after the first "=": a key can end with "=")
        for /f "tokens=1,* delims==" %%a in ('findstr /b "APP_KEY=" NutriTrace\.env') do set APP_KEY=%%b

        if defined APP_KEY (
            echo [OK] APP_KEY found in NutriTrace\.env
            REM Random passwords for the containers (MySQL, Grafana), kept in .env only.
            for /f %%p in ('powershell -NoProfile -Command "[guid]::NewGuid().ToString('N')"') do set ROOT_PW=%%p
            for /f %%p in ('powershell -NoProfile -Command "[guid]::NewGuid().ToString('N')"') do set DB_PW=%%p
            for /f %%p in ('powershell -NoProfile -Command "[guid]::NewGuid().ToString('N')"') do set GRAFANA_PW=%%p
            > .env (
                echo APP_KEY=!APP_KEY!
                echo MYSQL_ROOT_PASSWORD=!ROOT_PW!
                echo MYSQL_PASSWORD=!DB_PW!
                echo GRAFANA_ADMIN_PASSWORD=!GRAFANA_PW!
            )
            echo [OK] Created .env file with APP_KEY and random passwords
        ) else (
            echo [WARNING] APP_KEY not found in NutriTrace\.env
            echo Please run: cd NutriTrace ^&^& php artisan key:generate
            pause
            exit /b 1
        )
    ) else (
        echo [WARNING] No Laravel .env file found
        echo.
        echo Please generate an application key first:
        echo   1. cd NutriTrace
        echo   2. copy .env.example .env
        echo   3. php artisan key:generate
        echo   4. Run this script again
        pause
        exit /b 1
    )
) else (
    echo [OK] .env file exists
)

echo.
echo ========================================
echo  AI Service Configuration
echo ========================================
echo.
echo How do you want to run the AI service?
echo.
echo [A] On host machine (GPU support) - RECOMMENDED
echo     - AI service runs directly on your PC with GPU
echo     - Better performance for ML models
echo     - Start ai/start.bat separately
echo.
echo [B] In Docker container (CPU only)
echo     - All services in Docker
echo     - Slower AI inference
echo     - Easier to manage
echo.
set /p AI_CHOICE="Enter your choice (A/B): "

if /i "%AI_CHOICE%"=="A" (
    echo.
    echo [INFO] Using external AI service on host
    echo.
    echo IMPORTANT: Make sure to start the AI service separately:
    echo   1. Open a new terminal
    echo   2. cd NutriTrace\ai
    echo   3. Run: start.bat
    echo.
    echo Press any key when the AI service is running...
    pause >nul
) else if /i "%AI_CHOICE%"=="B" (
    echo.
    echo [INFO] Using containerized AI service
    echo [WARNING] This will use CPU only - slower inference
    echo.
    echo TODO: You need to edit docker-compose.yml manually:
    echo   1. Uncomment the 'ai' service section
    echo   2. Change AI_SERVICE_URL to http://ai:8001
    pause
) else (
    echo [ERROR] Invalid choice. Exiting.
    pause
    exit /b 1
)

echo.
echo ========================================
echo  Starting Docker Containers
echo ========================================
echo.

echo [INFO] Building images (this may take a few minutes on first run)...
docker compose build

if %errorlevel% neq 0 (
    echo [ERROR] Docker build failed
    pause
    exit /b 1
)

echo.
echo [INFO] Starting containers...
docker compose up -d

if %errorlevel% neq 0 (
    echo [ERROR] Failed to start containers
    pause
    exit /b 1
)

echo.
echo [OK] Containers started successfully!
echo.
echo ========================================
echo  Waiting for services to be ready...
echo ========================================
timeout /t 10 /nobreak >nul

echo.
echo [INFO] Checking service health...
docker compose ps

echo.
echo ========================================
echo  NutriTrace is ready!
echo ========================================
echo.
echo - Application: http://localhost:8000
echo - Database: localhost:13306 (from this PC only)
if /i "%AI_CHOICE%"=="A" (
    echo - AI Service: http://localhost:8001 (external)
) else (
    echo - AI Service: http://localhost:8001 (container)
)
echo.
echo Useful commands:
echo   View logs:     docker compose logs -f
echo   Stop:          docker compose down
echo   Restart:       docker compose restart
echo   Shell access:  docker compose exec app bash
echo.
echo Press any key to view logs (Ctrl+C to exit logs)...
pause >nul

docker compose logs -f
