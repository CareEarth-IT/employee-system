@echo off
setlocal
cd /d "%~dp0.."
echo Adding realestate.careearth.net DNS record to careearth-net-zone ...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0add-realestate-dns.ps1"
set EXITCODE=%ERRORLEVEL%
exit /b %EXITCODE%
