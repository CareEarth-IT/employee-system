@echo off
setlocal EnableDelayedExpansion
set "DEPLOY_DIR=%~dp0"
cd /d "!DEPLOY_DIR!.."

if "%~1"=="" (
    echo Usage: deploy\delete-users-prod.cmd email1 [email2 ...]
    echo Example:
    echo   deploy\delete-users-prod.cmd admin@careearth.info external_sharing@careearth.info
    exit /b 1
)

set "ARGS="
:loop
if "%~1"=="" goto run
set "ARGS=!ARGS! %1"
shift
goto loop

:run
powershell -NoProfile -ExecutionPolicy Bypass -File "!DEPLOY_DIR!delete-users-prod.ps1" !ARGS!
exit /b %ERRORLEVEL%
