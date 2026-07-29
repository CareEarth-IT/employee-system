@echo off
setlocal
cd /d "%~dp0.."
echo Granting employee SA invoker on real-estate Cloud Run ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0grant-realestate-invoker.ps1"
set EXITCODE=%ERRORLEVEL%
exit /b %EXITCODE%
