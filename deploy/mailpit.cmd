@echo off
setlocal
cd /d "%~dp0.."

docker ps -a --filter "name=employee-mailpit" --format "{{.Names}}" | findstr /x "employee-mailpit" >nul
if %ERRORLEVEL%==0 (
    echo Mailpit container already exists. Starting...
    docker start employee-mailpit
) else (
    echo Starting Mailpit ^(SMTP :1025, Web UI :8025^)...
    docker run -d --name employee-mailpit -p 8025:8025 -p 1025:1025 axllent/mailpit
)

if errorlevel 1 (
    echo.
    echo Failed. Is Docker Desktop running?
    exit /b 1
)

echo.
echo Mailpit is running.
echo   Web UI : http://localhost:8025
echo   SMTP   : 127.0.0.1:1025
echo.
echo Set in .env for local mail testing:
echo   MAIL_MAILER=smtp
echo   MAIL_HOST=127.0.0.1
echo   MAIL_PORT=1025
echo   MAIL_USERNAME=null
echo   MAIL_PASSWORD=null
echo.
echo Then: php artisan config:clear
echo Submit an equipment purchase or run: php artisan mail:test
exit /b 0
