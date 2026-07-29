@echo off
setlocal
cd /d "%~dp0.."
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-realestate-proxy.ps1" %*
exit /b %ERRORLEVEL%
