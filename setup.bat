@echo off
REM ──────────────────────────────────────────────
REM Mizaan Platform — Windows installer (.bat)
REM Verifies prerequisites and installs the system in one shot.
REM ──────────────────────────────────────────────
setlocal enabledelayedexpansion
chcp 65001 >nul

echo.
echo ================================================
echo    Mizaan Platform - Windows installer
echo ================================================
echo.

REM 1) verify prerequisites
echo [1/7] Checking prerequisites...
where php >nul 2>&1 || (echo   [X] PHP not found. Install PHP 8.3+ first. & exit /b 1)
for /f "tokens=*" %%i in ('php -r "echo PHP_VERSION;"') do set PHP_V=%%i
echo   [OK] PHP !PHP_V!

where composer >nul 2>&1 || (echo   [X] Composer not found. & exit /b 1)
echo   [OK] Composer present

where node >nul 2>&1 || (echo   [X] Node.js not found. Install Node 22 LTS. & exit /b 1)
for /f "tokens=*" %%i in ('node --version') do set NODE_V=%%i
echo   [OK] Node !NODE_V!

where npm >nul 2>&1 || (echo   [X] npm not found. & exit /b 1)
echo   [OK] npm present

REM 2) .env
echo.
echo [2/7] Preparing .env ...
if not exist .env (
    copy .env.example .env >nul
    echo   [OK] .env created from template
) else (
    echo   [!] .env already exists — skipping
)

REM 3) composer + npm
echo.
echo [3/7] Installing composer dependencies (may take a minute) ...
call composer install --no-interaction --prefer-dist || exit /b 1
echo   [OK] composer packages installed

echo.
echo [4/7] Installing npm packages ...
call npm install --silent || exit /b 1
echo   [OK] npm packages installed

REM 4) key
echo.
echo [5/7] Generating APP_KEY ...
findstr /b /c:"APP_KEY=base64:" .env >nul
if errorlevel 1 (
    call php artisan key:generate --ansi
    echo   [OK] APP_KEY set
) else (
    echo   [OK] APP_KEY already set
)

REM 5) database
echo.
echo [6/7] Setting up database ...
findstr /b /c:"DB_CONNECTION=sqlite" .env >nul
if not errorlevel 1 (
    if not exist database mkdir database
    if not exist database\database.sqlite type nul > database\database.sqlite
    echo   [OK] SQLite file ready
)

call php artisan migrate --force --ansi || exit /b 1
echo   [OK] Migrations applied

call php artisan db:seed --force --ansi || exit /b 1
echo   [OK] Seed complete

REM 6) storage + vite build
echo.
echo [7/7] Linking storage and building assets ...
call php artisan storage:link 2>nul
call npm run build --silent || exit /b 1
echo   [OK] Assets built

REM 7) summary
echo.
echo ================================================
echo    Installation complete
echo ================================================
echo.
echo Default admin:
echo   Email:    admin@mizaan.local
echo   Password: Admin@123
echo   ^^^^ change immediately after first login
echo.
echo Next steps:
echo   1. Set ANTHROPIC_API_KEY in .env for AI features
echo   2. Start the stack:
echo        composer dev
echo      or manually in 3 terminals:
echo        php artisan serve
echo        php artisan queue:work
echo        npm run dev
echo   3. Open http://localhost:8000
echo.
echo Optional - train AI on references:
echo   php artisan mizaan:train-from-folder "C:\path\to\references"
echo.
echo See INSTALL.md and REQUIREMENTS.md for full docs.
echo.

endlocal
