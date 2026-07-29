@echo off
setlocal
cd /d "%~dp0.."

set FILE=%~1
if "%FILE%"=="" set FILE=database\imports\employees.csv

if not exist "%FILE%" (
    echo CSV not found: %FILE%
    echo.
    echo Copy template:
    echo   copy database\imports\employees.csv.example database\imports\employees.csv
    echo Then edit employees.csv and run again.
    exit /b 1
)

echo Import file: %FILE%
echo.

if /i "%~2"=="--dry-run" (
    php artisan employee:import-bulk "%FILE%" --dry-run %3 %4 %5 %6 %7 %8 %9
) else (
    php artisan employee:import-bulk "%FILE%" %2 %3 %4 %5 %6 %7 %8 %9
)

exit /b %ERRORLEVEL%
