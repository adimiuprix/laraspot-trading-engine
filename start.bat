@echo off
echo ==========================================
echo Starting Onhush Crypto Exchange
echo ==========================================
echo.

REM Check if node_modules exists
if not exist "node_modules\" (
    echo [ERROR] node_modules not found. Please run: npm install
    pause
    exit /b 1
)

REM Check if vendor exists
if not exist "vendor\" (
    echo [ERROR] vendor not found. Please run: composer install
    pause
    exit /b 1
)

echo [1/4] Starting Laravel Development Server...
start "Laravel Server" cmd /k "php artisan serve"
timeout /t 2 /nobreak >nul

echo [2/4] Starting Vite Dev Server...
start "Vite Dev Server" cmd /k "npm run dev"
timeout /t 2 /nobreak >nul

echo [3/4] Starting Queue Worker...
start "Queue Worker" cmd /k "php artisan queue:work --tries=3 --timeout=90"
timeout /t 2 /nobreak >nul

echo [4/4] Starting Reverb WebSocket Server...
start "Reverb Server" cmd /k "php artisan reverb:start"
timeout /t 2 /nobreak >nul

echo.
echo ==========================================
echo All services started successfully!
echo ==========================================
echo.
echo Services running:
echo - Laravel Server: http://127.0.0.1:8000
echo - Vite Dev Server: http://127.0.0.1:5173
echo - Queue Worker: Processing background jobs
echo - Reverb Server: WebSocket connections
echo.
echo Press any key to stop all services...
pause >nul

echo.
echo Stopping all services...
taskkill /FI "WindowTitle eq Laravel Server*" /T /F >nul 2>&1
taskkill /FI "WindowTitle eq Vite Dev Server*" /T /F >nul 2>&1
taskkill /FI "WindowTitle eq Queue Worker*" /T /F >nul 2>&1
taskkill /FI "WindowTitle eq Reverb Server*" /T /F >nul 2>&1

echo All services stopped.
pause
