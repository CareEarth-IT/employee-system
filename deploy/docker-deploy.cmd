@echo off
setlocal
cd /d "%~dp0.."
echo Docker build + push + Cloud Run deploy
echo.
echo Requirements:
echo   - Docker Desktop is running
echo   - gcloud logged in
echo   - .env has APP_KEY and DB_PASSWORD
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0docker-deploy.ps1"
set EXITCODE=%ERRORLEVEL%
if not "%EXITCODE%"=="0" (
    echo.
    echo Docker deploy failed with exit code %EXITCODE%
    exit /b %EXITCODE%
)
echo.
echo Docker deploy finished successfully.
exit /b 0
