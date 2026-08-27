@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-finance-hr-db-prod.ps1" %*
set EXITCODE=%ERRORLEVEL%
if not "%EXITCODE%"=="0" (
    echo.
    echo setup-finance-hr-db-prod failed with exit code %EXITCODE%
    exit /b %EXITCODE%
)
echo.
echo finance_hr DB setup finished successfully.
exit /b 0
