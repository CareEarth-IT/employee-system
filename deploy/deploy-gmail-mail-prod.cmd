@echo off
setlocal
cd /d "%~dp0.."

echo Deploy Gmail SMTP to production Cloud Run ...
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0deploy-gmail-mail-prod.ps1" %*
set EXITCODE=%ERRORLEVEL%

if %EXITCODE% neq 0 (
    echo Gmail SMTP deploy failed with exit code %EXITCODE%
    exit /b %EXITCODE%
)

echo Gmail SMTP deploy finished successfully.
exit /b 0
