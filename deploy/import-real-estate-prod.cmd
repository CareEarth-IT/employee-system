@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0import-real-estate-prod.ps1" %*
set EXITCODE=%ERRORLEVEL%
if not "%EXITCODE%"=="0" (
    echo.
    echo import-real-estate-prod failed with exit code %EXITCODE%
    exit /b %EXITCODE%
)
echo.
echo real_estate import finished successfully.
exit /b 0
