@echo off
setlocal
cd /d "%~dp0.."
echo Running bootstrap-gcp.ps1 ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0bootstrap-gcp.ps1"
set EXITCODE=%ERRORLEVEL%
if not "%EXITCODE%"=="0" (
    echo.
    echo Bootstrap failed with exit code %EXITCODE%
    exit /b %EXITCODE%
)
echo.
echo Bootstrap finished successfully.
exit /b 0
