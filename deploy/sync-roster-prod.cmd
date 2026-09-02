@echo off
setlocal EnableDelayedExpansion
set "DEPLOY_DIR=%~dp0"
cd /d "!DEPLOY_DIR!.."

set "PSARGS="
:loop
if "%~1"=="" goto done
if /i "%~1"=="--dry-run" (set "PSARGS=!PSARGS! -DryRun" & shift & goto loop)
if /i "%~1"=="--skip-build" (set "PSARGS=!PSARGS! -SkipBuild" & shift & goto loop)
if /i "%~1"=="--with-service-deploy" (set "PSARGS=!PSARGS! -WithServiceDeploy" & shift & goto loop)
if /i "%~1"=="--match-email-only" (set "PSARGS=!PSARGS! -MatchEmailOnly" & shift & goto loop)
set "PSARGS=!PSARGS! %~1"
shift
goto loop
:done

powershell -NoProfile -ExecutionPolicy Bypass -File "!DEPLOY_DIR!sync-roster-prod.ps1" !PSARGS!
exit /b %ERRORLEVEL%
