@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0grant-specific-skills-app-prod.ps1" %*
set EXITCODE=%ERRORLEVEL%
if not "%EXITCODE%"=="0" (
    echo.
    echo grant-specific-skills-app-prod failed with exit code %EXITCODE%
    exit /b %EXITCODE%
)
echo.
echo GRANT finished successfully.
exit /b 0
