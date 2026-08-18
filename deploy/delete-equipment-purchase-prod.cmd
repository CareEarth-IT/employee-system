@echo off
setlocal
cd /d "%~dp0.."

if "%~1"=="" (
    echo Usage: deploy\delete-equipment-purchase-prod.cmd ^<application_id^> [more_ids...]
    echo Example: deploy\delete-equipment-purchase-prod.cmd 130
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0delete-equipment-purchase-prod.ps1" -Ids "%*"
set EXITCODE=%ERRORLEVEL%

if %EXITCODE% neq 0 (
    echo Delete failed with exit code %EXITCODE%
    exit /b %EXITCODE%
)

echo Delete finished successfully.
exit /b 0
