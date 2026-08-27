@echo off
setlocal
cd /d "%~dp0.."
echo Migrate specific_skills DB on production Cloud SQL ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0migrate-specific-skills-prod.ps1" %*
set EXITCODE=%ERRORLEVEL%
if not "%EXITCODE%"=="0" (
    echo migrate-specific-skills-prod failed with exit code %EXITCODE%
)
exit /b %EXITCODE%
