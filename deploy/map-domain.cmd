@echo off
setlocal
cd /d "%~dp0.."
echo Mapping domain to Cloud Run service employee ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0map-domain.ps1"
set EXITCODE=%ERRORLEVEL%
exit /b %EXITCODE%
