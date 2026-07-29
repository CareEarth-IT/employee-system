@echo off
setlocal
cd /d "%~dp0.."

if "%~1"=="" (
    echo Usage: deploy\create-approver.cmd EMAIL [PASSWORD]
    echo Example: deploy\create-approver.cmd approver@careearth.net MyPassword123
    echo.
    echo Creates GA approver ^(経理部・総務課^) by default.
    exit /b 1
)

set EMAIL=%~1
set PASSWORD=%~2
if "%PASSWORD%"=="" set PASSWORD=password

php artisan employee:create-approver "%EMAIL%" --password="%PASSWORD%" --type=ga
exit /b %ERRORLEVEL%
