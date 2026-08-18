@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0import-specific-skills-prod.ps1" %*
set EXITCODE=%ERRORLEVEL%
if not "%EXITCODE%"=="0" (
    echo.
    echo import-specific-skills-prod failed with exit code %EXITCODE%
    exit /b %EXITCODE%
)
echo.
echo specific_skills import finished successfully.
exit /b 0
