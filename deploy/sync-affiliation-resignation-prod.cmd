@echo off
setlocal
cd /d "%~dp0.."

set ARGS=
:parse
if "%~1"=="" goto run
if /I "%~1"=="--dry-run" set ARGS=%ARGS% -DryRun
if /I "%~1"=="--skip-build" set ARGS=%ARGS% -SkipBuild
shift
goto parse

:run
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0sync-affiliation-resignation-prod.ps1" %ARGS%
exit /b %ERRORLEVEL%
