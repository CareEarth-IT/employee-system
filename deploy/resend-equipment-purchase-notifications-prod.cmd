@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0resend-equipment-purchase-notifications-prod.ps1" %*
set EXITCODE=%ERRORLEVEL%
if not "%EXITCODE%"=="0" (
    echo.
    echo Resend job failed with exit code %EXITCODE%
    exit /b %EXITCODE%
)
echo.
echo Resend job finished successfully.
exit /b 0
