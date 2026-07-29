@echo off
setlocal
cd /d "%~dp0.."
echo Mapping realestate.careearth.net to Cloud Run in ce-realestate-inside-2606st ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0map-realestate-domain.ps1"
set EXITCODE=%ERRORLEVEL%
exit /b %EXITCODE%
