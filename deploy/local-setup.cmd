@echo off
setlocal
cd /d "%~dp0.."

echo Local dev setup (SQLite)
echo.

set DB_CONNECTION=sqlite
set DB_DATABASE=%CD%\database\database.sqlite
set SESSION_DRIVER=file
set CACHE_STORE=file
set QUEUE_CONNECTION=sync
set MAIL_MAILER=log

if not exist "database\database.sqlite" (
    echo Creating database\database.sqlite
    type nul > "database\database.sqlite"
)

echo Running migrations...
php artisan migrate --force
if errorlevel 1 exit /b 1

echo Seeding test users...
php artisan db:seed --force
if errorlevel 1 exit /b 1

echo.
echo Done. Open:
echo   http://employee.local/login
echo.
echo First time: run deploy\setup-employee-local-host.cmd as Administrator
echo.
echo Test accounts:
echo   ga@example.com / password  ^(備品承認^)
echo   employee@example.com / password
echo.
echo Password reset: login -^> forgot-password, enter a registered email.
echo.
echo To use this DB in Apache, set in .env:
echo   DB_CONNECTION=sqlite
echo   DB_DATABASE=%CD%\database\database.sqlite
echo   SESSION_DRIVER=file
echo   CACHE_STORE=file
echo   QUEUE_CONNECTION=sync
exit /b 0
