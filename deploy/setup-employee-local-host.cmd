@echo off
setlocal
cd /d "%~dp0.."
echo.
echo CE-GR employee.local setup
echo   - hosts: 127.0.0.1 employee.local
echo   - Apache VirtualHost
echo   - .env APP_URL update
echo.
echo 管理者として実行してください（右クリック -^> 管理者として実行）
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-employee-local-host.ps1"
set EXITCODE=%ERRORLEVEL%
if not "%EXITCODE%"=="0" exit /b %EXITCODE%
echo.
pause
exit /b 0
