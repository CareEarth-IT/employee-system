@echo off
setlocal EnableDelayedExpansion
set "DEPLOY_DIR=%~dp0"
cd /d "!DEPLOY_DIR!.."

set "PSARGS="
:loop
if "%~1"=="" goto done
if /i "%~1"=="--dry-run" (set "PSARGS=!PSARGS! -DryRun" & shift & goto loop)
if /i "%~1"=="--skip-build" (set "PSARGS=!PSARGS! -SkipBuild" & shift & goto loop)
if /i "%~1"=="--skip-service-deploy" (set "PSARGS=!PSARGS! -SkipServiceDeploy" & shift & goto loop)
if /i "%~1"=="--limit" (set "PSARGS=!PSARGS! -Limit %~2" & shift & shift & goto loop)
set "ARG=%~1"
if /i "!ARG:~0,8!"=="--limit=" (
    set "PSARGS=!PSARGS! -Limit !ARG:~8!"
    shift
    goto loop
)
set "PSARGS=!PSARGS! %~1"
shift
goto loop
:done

powershell -NoProfile -ExecutionPolicy Bypass -File "!DEPLOY_DIR!import-employees-prod.ps1" !PSARGS!
exit /b %ERRORLEVEL%
