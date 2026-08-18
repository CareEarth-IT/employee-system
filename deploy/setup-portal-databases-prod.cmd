@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-portal-databases-prod.ps1" %*
set EXITCODE=%ERRORLEVEL%
if not "%EXITCODE%"=="0" (
    echo.
    echo setup-portal-databases-prod failed with exit code %EXITCODE%
    exit /b %EXITCODE%
)
echo.
echo Portal databases setup finished successfully.
exit /b 0
